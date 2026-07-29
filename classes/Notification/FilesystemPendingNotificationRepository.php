<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class FilesystemPendingNotificationRepository implements PendingNotificationRepository
{
    private const MAX_EVENT_BYTES = 512;
    private readonly string $baseRoot;
    private readonly string $pendingRoot;
    private readonly string $lockRoot;
    private readonly string $sentRoot;
    private readonly string $deadLetterRoot;
    private readonly string $stateRoot;

    public function __construct(string $userDataRoot)
    {
        if ($userDataRoot === '' || $userDataRoot[0] !== '/' || str_contains($userDataRoot, "\0")) {
            throw new \InvalidArgumentException('Invalid user data root.');
        }
        $this->baseRoot = rtrim($userDataRoot, '/') . '/goosialize-leads/v1/notification-outbox';
        $this->pendingRoot = $this->baseRoot . '/events';
        $this->lockRoot = $this->baseRoot . '/delivery-locks';
        $this->sentRoot = $this->baseRoot . '/sent';
        $this->deadLetterRoot = $this->baseRoot . '/dead-letter';
        $this->stateRoot = $this->baseRoot . '/delivery-state';
    }

    /** @return list<string> */
    public function pending(int $limit): array
    {
        if ($limit < 1 || $limit > 50) throw new \InvalidArgumentException('Invalid delivery limit.');
        if (!is_dir($this->pendingRoot)) return [];
        $this->assertDirectory($this->pendingRoot, $this->baseRoot);
        $rootCount = 0;
        $total = 0;
        $events = [];
        $root = @opendir($this->pendingRoot);
        if (!is_resource($root)) throw new \RuntimeException('event_invalid');
        try {
            while (($shard = readdir($root)) !== false) {
                if ($shard === '.' || $shard === '..') continue;
                if (++$rootCount > 256 || preg_match('/\A[0-9a-f]{2}\z/D', $shard) !== 1) {
                    throw new \RuntimeException('event_invalid');
                }
                $shardPath = $this->pendingRoot . '/' . $shard;
                $this->assertDirectory($shardPath, $this->pendingRoot);
                $handle = @opendir($shardPath);
                if (!is_resource($handle)) throw new \RuntimeException('event_invalid');
                try {
                    while (($name = readdir($handle)) !== false) {
                        if ($name === '.' || $name === '..') continue;
                        if (++$total > 10000) throw new \RuntimeException('event_invalid');
                        if ($this->isTransient($shardPath . '/' . $name, $name)) continue;
                        if (preg_match('/\A([0-9a-f]{64})\.json\z/D', $name, $matches) !== 1
                            || substr($matches[1], 0, 2) !== $shard
                        ) throw new \RuntimeException('event_invalid');
                        [$event, , $eventHandle] = $this->readEvent($shardPath . '/' . $name, $matches[1]);
                        try {
                            $events[] = [$event->createdAt(), $event->eventId()];
                        } finally {
                            @fclose($eventHandle);
                        }
                    }
                } finally {
                    closedir($handle);
                }
            }
        } finally {
            closedir($root);
        }
        usort($events, static fn (array $a, array $b): int => $a <=> $b);
        return array_map(static fn (array $event): string => $event[1], array_slice($events, 0, $limit));
    }

    /** @param callable(NotificationEvent):string $consumer */
    public function process(string $eventId, callable $consumer): string
    {
        return $this->withLockedEvent($eventId, function (DeliveryEventLease $lease) use ($consumer): string {
            $archive = $lease->archiveStatus();
            if ($archive === 'archive_matching') return $lease->archive();
            if ($archive === 'archive_conflict') return 'archive_conflict';
            $result = $consumer($lease->event());
            if (!in_array($result, ['transport_success','transport_failed','message_invalid','lead_missing','lead_invalid'], true)) return 'event_invalid';
            if ($result !== 'transport_success') return $result;
            $archiveResult = $lease->archive();
            return $archiveResult === 'archive_failed' ? 'delivery_uncertain' : $archiveResult;
        });
    }

    /** @return array{eligible:list<string>,recovery:list<string>,deferred:int,codes:array<string,int>} */
    public function eligible(int $limit, DeliveryStateRepository $states, DeliveryClock $clock, bool $retriesOnly): array
    {
        if ($limit < 1 || $limit > 50) throw new \InvalidArgumentException('Invalid delivery limit.');
        $eligible = $recovery = []; $deferred = 0; $codes = [];
        $now = $clock->now();
        $scanned = 0;
        $pendingEvents = $this->discoverRetryCandidates($scanned, $codes);
        $pendingIds = array_fill_keys(array_column($pendingEvents, 1), true);
        foreach ($pendingEvents as [, $eventId]) {
            try {
                $state = $states->read($eventId);
                if ($state === null) {
                    if (!$retriesOnly && count($eligible) + count($recovery) < $limit) $eligible[] = $eventId;
                } elseif ($state->state() === 'dead_lettered' || $state->state() === 'attempting') {
                    if (count($eligible) + count($recovery) < $limit) $recovery[] = $eventId;
                } elseif ($state->eligibleAt($now)) {
                    if (count($eligible) + count($recovery) < $limit) $eligible[] = $eventId;
                } elseif ($state->state() === 'retry_wait') {
                    $deferred++;
                }
            } catch (\Throwable) {
                $codes['state_invalid'] = ($codes['state_invalid'] ?? 0) + 1;
            }
        }
        $this->scanStateOrphans($states, $now, $pendingIds, $scanned, $codes);
        ksort($codes, SORT_STRING);
        return ['eligible' => $eligible, 'recovery' => $recovery, 'deferred' => $deferred, 'codes' => $codes];
    }

    /**
     * @param array<string,int> $codes
     * @return list<array{string,string}>
     */
    private function discoverRetryCandidates(int &$scanned, array &$codes): array
    {
        if (!is_dir($this->pendingRoot)) return [];
        try {
            $this->assertDirectory($this->pendingRoot, $this->baseRoot);
        } catch (\Throwable) {
            $codes['event_invalid'] = ($codes['event_invalid'] ?? 0) + 1;
            return [];
        }
        $events = [];
        $root = @opendir($this->pendingRoot);
        if (!is_resource($root)) {
            $codes['event_invalid'] = ($codes['event_invalid'] ?? 0) + 1;
            return [];
        }
        try {
            while (($shard = readdir($root)) !== false) {
                if ($shard === '.' || $shard === '..') continue;
                if ($scanned >= 10000) {
                    $codes['event_invalid'] = ($codes['event_invalid'] ?? 0) + 1;
                    break;
                }
                $scanned++;
                if (preg_match('/\A[0-9a-f]{2}\z/D', $shard) !== 1) {
                    $codes['event_invalid'] = ($codes['event_invalid'] ?? 0) + 1;
                    continue;
                }
                $shardPath = $this->pendingRoot . '/' . $shard;
                try {
                    $this->assertDirectory($shardPath, $this->pendingRoot);
                    $handle = @opendir($shardPath);
                    if (!is_resource($handle)) throw new \RuntimeException('event_invalid');
                    try {
                        while (($name = readdir($handle)) !== false) {
                            if ($name === '.' || $name === '..') continue;
                            if ($scanned >= 10000) {
                                $codes['event_invalid'] = ($codes['event_invalid'] ?? 0) + 1;
                                break 2;
                            }
                            $scanned++;
                            $path = $shardPath . '/' . $name;
                            try {
                                if ($this->isTransient($path, $name)) continue;
                                if (preg_match('/\A([0-9a-f]{64})\.json\z/D', $name, $matches) !== 1
                                    || substr($matches[1], 0, 2) !== $shard
                                ) throw new \RuntimeException('event_invalid');
                                [$event, , $eventHandle] = $this->readEvent($path, $matches[1]);
                                try {
                                    $events[] = [$event->createdAt(), $event->eventId()];
                                } finally {
                                    @fclose($eventHandle);
                                }
                            } catch (\Throwable) {
                                $codes['event_invalid'] = ($codes['event_invalid'] ?? 0) + 1;
                            }
                        }
                    } finally {
                        closedir($handle);
                    }
                } catch (\Throwable) {
                    $codes['event_invalid'] = ($codes['event_invalid'] ?? 0) + 1;
                }
            }
        } finally {
            closedir($root);
        }
        usort($events, static fn (array $a, array $b): int => $a <=> $b);
        return $events;
    }

    /** @param array<string,true> $pendingIds @param array<string,int> $codes */
    private function scanStateOrphans(
        DeliveryStateRepository $states,
        \DateTimeImmutable $now,
        array $pendingIds,
        int &$scanned,
        array &$codes
    ): void {
        if (!is_dir($this->stateRoot) || $scanned >= 10000) return;
        try {
            $this->assertDirectory($this->stateRoot, $this->baseRoot);
            $root = @opendir($this->stateRoot);
            if (!is_resource($root)) throw new \RuntimeException('state_invalid');
            try {
                while (($shard = readdir($root)) !== false) {
                    if ($shard === '.' || $shard === '..') continue;
                    if ($scanned >= 10000) break;
                    $scanned++;
                    if (preg_match('/\A[0-9a-f]{2}\z/D', $shard) !== 1) {
                        $codes['state_invalid'] = ($codes['state_invalid'] ?? 0) + 1;
                        continue;
                    }
                    $path = $this->stateRoot . '/' . $shard;
                    try {
                        $this->assertDirectory($path, $this->stateRoot);
                        $handle = @opendir($path);
                        if (!is_resource($handle)) throw new \RuntimeException('state_invalid');
                        try {
                            while (($name = readdir($handle)) !== false) {
                                if ($name === '.' || $name === '..') continue;
                                if ($scanned >= 10000) break 2;
                                $scanned++;
                                if (preg_match('/\A([0-9a-f]{64})\.json\z/D', $name, $matches) !== 1
                                    || substr($matches[1], 0, 2) !== $shard
                                ) {
                                    $codes['state_invalid'] = ($codes['state_invalid'] ?? 0) + 1;
                                    continue;
                                }
                                $eventId = $matches[1];
                                if (isset($pendingIds[$eventId])) continue;
                                try {
                                    $state = $states->read($eventId);
                                    if ($state === null || !$this->recoverSentOrphan($eventId, $state, $states, $now)) {
                                        $codes['state_orphaned'] = ($codes['state_orphaned'] ?? 0) + 1;
                                    } else {
                                        $codes['state_orphaned'] = ($codes['state_orphaned'] ?? 0) + 1;
                                    }
                                } catch (\Throwable) {
                                    $codes['state_invalid'] = ($codes['state_invalid'] ?? 0) + 1;
                                }
                            }
                        } finally {
                            closedir($handle);
                        }
                    } catch (\Throwable) {
                        $codes['state_invalid'] = ($codes['state_invalid'] ?? 0) + 1;
                    }
                }
            } finally {
                closedir($root);
            }
        } catch (\Throwable) {
            $codes['state_invalid'] = ($codes['state_invalid'] ?? 0) + 1;
        }
    }

    private function recoverSentOrphan(
        string $eventId,
        DeliveryState $state,
        DeliveryStateRepository $states,
        \DateTimeImmutable $now
    ): bool {
        $shard = substr($eventId, 0, 2);
        $pendingPath = $this->pendingRoot . '/' . $shard . '/' . $eventId . '.json';
        $sentPath = $this->sentRoot . '/' . $shard . '/' . $eventId . '.json';
        $this->ensureDirectory($this->baseRoot);
        $this->ensureDirectory($this->lockRoot);
        $this->ensureDirectory($this->lockRoot . '/' . $shard);
        $lockPath = $this->lockRoot . '/' . $shard . '/' . $eventId . '.lock';
        $before = @lstat($lockPath);
        if ($before !== false && (is_link($lockPath) || ($before['mode'] & 0170000) !== 0100000
            || ($before['mode'] & 0777) !== 0600
        )) return false;
        $lock = @fopen($lockPath, 'c+b');
        if (!is_resource($lock)) return false;
        try {
            @chmod($lockPath, 0600);
            $opened = @fstat($lock);
            $after = @lstat($lockPath);
            if ($opened === false || $after === false || $opened['dev'] !== $after['dev']
                || $opened['ino'] !== $after['ino'] || ($opened['mode'] & 0777) !== 0600
                || !@flock($lock, LOCK_EX | LOCK_NB)
            ) return false;
            try {
                if (file_exists($pendingPath) || is_link($pendingPath)
                    || (!file_exists($sentPath) && !is_link($sentPath))
                ) return false;
                $current = $states->read($eventId);
                if ($current === null || $current->revision() !== $state->revision()) return false;
                [, , $handle] = $this->readEvent($sentPath, $eventId, $this->sentRoot);
                @fclose($handle);
                if ($current->state() === 'delivered') return true;
                if ($current->state() === 'dead_lettered') return false;
                $states->save($current->delivered($now), $current->revision());
                return true;
            } finally {
                @flock($lock, LOCK_UN);
            }
        } finally {
            @fclose($lock);
        }
    }

    /** @param callable(DeliveryEventLease):string $consumer */
    public function withLockedEvent(string $eventId, callable $consumer): string
    {
        if (preg_match('/\A[0-9a-f]{64}\z/D', $eventId) !== 1) return 'event_invalid';
        $shard = substr($eventId, 0, 2);
        $pendingPath = $this->pendingRoot . '/' . $shard . '/' . $eventId . '.json';
        try {
            $this->ensureDirectory($this->baseRoot);
            $this->ensureDirectory($this->lockRoot);
            $this->ensureDirectory($this->lockRoot . '/' . $shard);
            $lockPath = $this->lockRoot . '/' . $shard . '/' . $eventId . '.lock';
            $before = @lstat($lockPath);
            if ($before !== false && (is_link($lockPath) || ($before['mode'] & 0170000) !== 0100000 || ($before['mode'] & 0777) !== 0600)) {
                return 'event_invalid';
            }
            $lock = @fopen($lockPath, 'c+b');
            if (!is_resource($lock)) return 'event_invalid';
            @chmod($lockPath, 0600);
            $lockStat = @fstat($lock);
            $pathStat = @lstat($lockPath);
            if ($lockStat === false || $pathStat === false || $lockStat['dev'] !== $pathStat['dev']
                || $lockStat['ino'] !== $pathStat['ino'] || ($lockStat['mode'] & 0777) !== 0600
            ) {
                fclose($lock);
                return 'event_invalid';
            }
            if (!@flock($lock, LOCK_EX | LOCK_NB)) {
                fclose($lock);
                return 'delivery_contended';
            }
            try {
                [$event, $bytes, $eventHandle] = $this->readEvent($pendingPath, $eventId);
                try {
                    $archive = $this->sentRoot . '/' . $shard . '/' . $eventId . '.json';
                    $deadLetter = $this->deadLetterRoot . '/' . $shard . '/' . $eventId . '.json';
                    $active = (object) ['value' => true];
                    $guard = static function () use ($active): void {
                        if (!$active->value) throw new \RuntimeException('delivery_lease_invalid');
                    };
                    $lease = new class(
                        $event,
                        $guard,
                        fn (): string => (file_exists($archive) || is_link($archive))
                            ? ($this->archiveMatches($archive, $eventId, $bytes, $this->sentRoot) ? 'archive_matching' : 'archive_conflict')
                            : 'archive_absent',
                        fn (): string => (file_exists($deadLetter) || is_link($deadLetter))
                            ? ($this->archiveMatches($deadLetter, $eventId, $bytes, $this->deadLetterRoot) ? 'dead_letter_matching' : 'dead_letter_conflict')
                            : 'dead_letter_absent',
                        function () use ($archive, $pendingPath, $eventId, $bytes, $eventHandle): string {
                            if (file_exists($archive) || is_link($archive)) {
                                if (!$this->archiveMatches($archive, $eventId, $bytes, $this->sentRoot)) return 'archive_conflict';
                                return @unlink($pendingPath) && $this->syncDirectory(dirname($pendingPath)) ? 'already_archived' : 'archive_failed';
                            }
                            return $this->publishArchive($pendingPath, $archive, $eventId, $bytes, $eventHandle) ? 'delivered' : 'archive_failed';
                        },
                        function () use ($deadLetter, $pendingPath, $eventId, $bytes, $eventHandle): string {
                            try {
                                $this->ensureDirectory($this->deadLetterRoot);
                                $this->ensureDirectory(dirname($deadLetter));
                            } catch (\Throwable) { return 'dead_letter_failed'; }
                            if (file_exists($deadLetter) || is_link($deadLetter)) {
                                return $this->archiveMatches($deadLetter, $eventId, $bytes, $this->deadLetterRoot)
                                    ? 'dead_letter_existing' : 'dead_letter_conflict';
                            }
                            $opened = @fstat($eventHandle); $source = @lstat($pendingPath);
                            if (!$opened || !$source || $opened['dev'] !== $source['dev'] || $opened['ino'] !== $source['ino']
                                || !@link($pendingPath, $deadLetter)) return 'dead_letter_failed';
                            $published = @lstat($deadLetter);
                            if (!$published || $published['dev'] !== $opened['dev'] || $published['ino'] !== $opened['ino']
                                || !@chmod($deadLetter, 0600) || !@fsync($eventHandle) || !$this->syncDirectory(dirname($deadLetter))) return 'dead_letter_failed';
                            return 'dead_letter_published';
                        },
                        fn (): bool => (!file_exists($pendingPath) && !is_link($pendingPath))
                            || (@unlink($pendingPath) && $this->syncDirectory(dirname($pendingPath)))
                    ) implements DeliveryEventLease {
                        public function __construct(
                            private NotificationEvent $notificationEvent,
                            private $guard,
                            private $archiveStatusCallback,
                            private $deadLetterStatusCallback,
                            private $archiveCallback,
                            private $publishCallback,
                            private $removeCallback
                        ) {}
                        public function event(): NotificationEvent { ($this->guard)(); return $this->notificationEvent; }
                        public function archiveStatus(): string { ($this->guard)(); return ($this->archiveStatusCallback)(); }
                        public function deadLetterStatus(): string { ($this->guard)(); return ($this->deadLetterStatusCallback)(); }
                        public function archive(): string { ($this->guard)(); return ($this->archiveCallback)(); }
                        public function publishDeadLetter(): string { ($this->guard)(); return ($this->publishCallback)(); }
                        public function removePending(): bool { ($this->guard)(); return ($this->removeCallback)(); }
                    };
                    try {
                        $result = $consumer($lease);
                    } catch (\Throwable) {
                        return 'event_invalid';
                    } finally {
                        $active->value = false;
                    }
                    $allowed = [
                        'transport_success','transport_failed','message_invalid','lead_missing','lead_invalid',
                        'delivered','already_archived','archive_conflict','archive_failed','delivery_uncertain',
                        'dead_lettered','dead_letter_existing','dead_letter_conflict','dead_letter_failed',
                        'state_invalid','state_clock_invalid','state_revision_stale','state_transition_invalid',
                        'state_unavailable','transport_uncertain','transport_exception',
                        'reconcile_completed','reconcile_rejected','reconcile_failed',
                    ];
                    return in_array($result, $allowed, true) ? $result : 'event_invalid';
                } finally {
                    @fclose($eventHandle);
                }
            } catch (\Throwable) {
                return 'event_invalid';
            } finally {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        } catch (\Throwable) {
            return 'event_invalid';
        }
    }

    private function publishArchive(string $pending, string $archive, string $eventId, string $bytes, $handle): bool
    {
        try {
            $this->ensureDirectory($this->sentRoot);
            $this->ensureDirectory(dirname($archive));
        } catch (\Throwable) {
            return false;
        }
        $opened = @fstat($handle);
        $source = @lstat($pending);
        if ($opened === false || $source === false || $opened['dev'] !== $source['dev']
            || $opened['ino'] !== $source['ino'] || $opened['size'] !== $source['size']
            || ($source['mode'] & 0170000) !== 0100000 || ($source['mode'] & 0777) !== 0600
        ) return false;
        if (!@link($pending, $archive)) {
            return (file_exists($archive) || is_link($archive))
                && $this->archiveMatches($archive, $eventId, $bytes)
                && @unlink($pending)
                && $this->syncDirectory(dirname($pending));
        }
        $published = @lstat($archive);
        $sourceAfter = @lstat($pending);
        if ($published === false || $sourceAfter === false
            || $published['dev'] !== $opened['dev'] || $published['ino'] !== $opened['ino']
            || $sourceAfter['dev'] !== $opened['dev'] || $sourceAfter['ino'] !== $opened['ino']
        ) return false;
        if (!@chmod($archive, 0600) || !@fsync($handle) || !$this->syncDirectory(dirname($archive))) return false;
        if (!@unlink($pending) || !$this->syncDirectory(dirname($pending))) return false;
        return true;
    }

    private function archiveMatches(string $path, string $eventId, string $bytes, ?string $root = null): bool
    {
        try {
            [, $archiveBytes, $handle] = $this->readEvent($path, $eventId, $root ?? $this->sentRoot);
            @fclose($handle);
            return hash_equals($bytes, $archiveBytes);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{NotificationEvent,string,resource} */
    private function readEvent(string $path, string $eventId, ?string $root = null): array
    {
        $root ??= $this->pendingRoot;
        $before = @lstat($path);
        $real = @realpath($path);
        if ($before === false || $real === false || is_link($path)
            || ($before['mode'] & 0170000) !== 0100000 || ($before['mode'] & 0777) !== 0600
            || !str_starts_with($real, $root . '/') || !is_int($before['size'])
            || $before['size'] < 2 || $before['size'] > self::MAX_EVENT_BYTES
        ) throw new \RuntimeException('event_invalid');
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) throw new \RuntimeException('event_invalid');
        $after = @lstat($path);
        $opened = @fstat($handle);
        if ($after === false || $opened === false || $before['dev'] !== $after['dev'] || $before['ino'] !== $after['ino']
            || $after['dev'] !== $opened['dev'] || $after['ino'] !== $opened['ino'] || $after['size'] !== $opened['size']
            || ($opened['mode'] & 0170000) !== 0100000 || ($opened['mode'] & 0777) !== 0600
        ) {
            fclose($handle);
            throw new \RuntimeException('event_invalid');
        }
        $bytes = @stream_get_contents($handle, self::MAX_EVENT_BYTES + 1);
        if (!is_string($bytes) || strlen($bytes) !== $before['size'] || !str_ends_with($bytes, "\n")) {
            fclose($handle);
            throw new \RuntimeException('event_invalid');
        }
        try {
            $data = json_decode($bytes, true, 6, JSON_THROW_ON_ERROR);
            $event = NotificationEvent::fromLeadRecord([
                'id' => $data['lead_id'] ?? null,
                'created_at' => $data['created_at'] ?? null,
            ]);
        } catch (\Throwable) {
            fclose($handle);
            throw new \RuntimeException('event_invalid');
        }
        if (!hash_equals($eventId, $event->eventId()) || !hash_equals($bytes, $event->canonicalBytes())) {
            fclose($handle);
            throw new \RuntimeException('event_invalid');
        }
        return [$event, $bytes, $handle];
    }

    private function isTransient(string $path, string $name): bool
    {
        $transient = preg_match('/\A[0-9a-f]{64}\.json\.[0-9a-f]{16}\.tmp\z/D', $name) === 1
            || preg_match('/\A[0-9a-f]{64}\.json\.lock\z/D', $name) === 1;
        if (!$transient) return false;
        $stat = @lstat($path);
        if ($stat === false || is_link($path) || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0777) !== 0600) {
            throw new \RuntimeException('event_invalid');
        }
        return true;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0700, false) && !is_dir($path)) throw new \RuntimeException('event_invalid');
        @chmod($path, 0700);
        $this->assertDirectory($path, dirname($path));
    }

    private function assertDirectory(string $path, string $parent): void
    {
        $stat = @lstat($path);
        $real = @realpath($path);
        $parentReal = @realpath($parent);
        if ($stat === false || $real === false || $parentReal === false || is_link($path)
            || ($stat['mode'] & 0170000) !== 0040000 || ($stat['mode'] & 0777) !== 0700
            || ($real !== $parentReal && !str_starts_with($real, rtrim($parentReal, '/') . '/'))
        ) throw new \RuntimeException('event_invalid');
    }

    private function syncDirectory(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) return false;
        try {
            return @fsync($handle);
        } finally {
            fclose($handle);
        }
    }
}
