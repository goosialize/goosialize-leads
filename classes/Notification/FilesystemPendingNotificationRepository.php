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

    public function __construct(string $userDataRoot)
    {
        if ($userDataRoot === '' || $userDataRoot[0] !== '/' || str_contains($userDataRoot, "\0")) {
            throw new \InvalidArgumentException('Invalid user data root.');
        }
        $this->baseRoot = rtrim($userDataRoot, '/') . '/goosialize-leads/v1/notification-outbox';
        $this->pendingRoot = $this->baseRoot . '/events';
        $this->lockRoot = $this->baseRoot . '/delivery-locks';
        $this->sentRoot = $this->baseRoot . '/sent';
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
                    if (file_exists($archive) || is_link($archive)) {
                        if (!$this->archiveMatches($archive, $eventId, $bytes)) return 'archive_conflict';
                        if (!@unlink($pendingPath) || !$this->syncDirectory(dirname($pendingPath))) return 'archive_failed';
                        return 'already_archived';
                    }
                    $result = $consumer($event);
                    if (!in_array($result, ['transport_success','transport_failed','message_invalid','lead_missing','lead_invalid'], true)) {
                        return 'event_invalid';
                    }
                    if ($result !== 'transport_success') return $result;
                    if (!$this->publishArchive($pendingPath, $archive, $eventId, $bytes, $eventHandle)) {
                        return 'delivery_uncertain';
                    }
                    return 'delivered';
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

    private function archiveMatches(string $path, string $eventId, string $bytes): bool
    {
        try {
            [, $archiveBytes, $handle] = $this->readEvent($path, $eventId, $this->sentRoot);
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
