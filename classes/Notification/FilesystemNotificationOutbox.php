<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class FilesystemNotificationOutbox implements NotificationOutbox
{
    private const MAX_EVENT_BYTES = 512;
    private const MAX_ROOT_ENTRIES = 256;
    private const MAX_SHARD_ENTRIES = 10000;
    private readonly string $root;
    private readonly \Closure $entropy;
    private readonly \Closure $sync;
    private readonly \Closure $publish;

    /**
     * @param callable(int):string $entropy
     * @param callable(resource):bool|null $sync
     * @param callable(string,string):bool|null $publish
     */
    public function __construct(
        string $userDataRoot,
        callable $entropy,
        ?callable $sync = null,
        ?callable $publish = null
    ) {
        if (
            $userDataRoot === ''
            || str_contains($userDataRoot, "\0")
            || $userDataRoot[0] !== '/'
            || preg_match('#(^|/)\.{1,2}(/|$)#', $userDataRoot) === 1
            || str_contains($userDataRoot, '://')
        ) {
            throw new \InvalidArgumentException('Invalid notification outbox.');
        }
        $this->entropy = \Closure::fromCallable($entropy);
        $this->sync = $sync === null
            ? static fn ($handle): bool => function_exists('fsync') && @fsync($handle)
            : \Closure::fromCallable($sync);
        $this->publish = $publish === null
            ? static fn (string $temporary, string $final): bool => @link($temporary, $final)
            : \Closure::fromCallable($publish);

        $normalized = rtrim((string) preg_replace('#/+#', '/', $userDataRoot), '/') ?: '/';
        $stat = @lstat($normalized);
        $real = @realpath($normalized);
        if (
            $stat === false
            || $real === false
            || ($stat['mode'] & 0170000) !== 0040000
            || is_link($normalized)
        ) {
            throw new \InvalidArgumentException('Invalid notification outbox.');
        }
        $this->root = $this->ensureDirectories($real, ['goosialize-leads', 'v1', 'notification-outbox', 'events']);
    }

    public function ensure(NotificationEvent $event): NotificationEnqueueResult
    {
        $eventId = $event->eventId();
        $bytes = $event->canonicalBytes();
        if (preg_match('/\A[0-9a-f]{64}\z/D', $eventId) !== 1 || strlen($bytes) > self::MAX_EVENT_BYTES) {
            return NotificationEnqueueResult::failure('outbox_security_invalid');
        }

        $lock = null;
        $lockPath = null;
        $temporary = null;
        $temporaryOwned = false;
        try {
            $shardPath = $this->root . '/' . substr($eventId, 0, 2);
            if (@lstat($shardPath) === false && $this->entryCount($this->root) >= self::MAX_ROOT_ENTRIES) {
                return NotificationEnqueueResult::failure('outbox_capacity_exceeded');
            }
            $shard = $this->ensureDirectories($this->root, [substr($eventId, 0, 2)]);
            $final = $shard . '/' . $eventId . '.json';
            $lockPath = $shard . '/' . $eventId . '.lock';
            if (@lstat($final) !== false) {
                return $this->compareExisting($final, $bytes);
            }
            if ($this->entryCount($shard) > self::MAX_SHARD_ENTRIES - 3) {
                return NotificationEnqueueResult::failure('outbox_capacity_exceeded');
            }

            $lock = $this->openExclusive($lockPath, 'c+b');
            if (!is_resource($lock) || !@flock($lock, LOCK_EX)) {
                return NotificationEnqueueResult::failure('outbox_unavailable');
            }
            if (@lstat($final) !== false) {
                return $this->compareExisting($final, $bytes);
            }

            $suffix = ($this->entropy)(8);
            if (!is_string($suffix) || strlen($suffix) !== 8) {
                return NotificationEnqueueResult::failure('outbox_unavailable');
            }
            $temporary = $shard . '/.event-' . $eventId . '-' . bin2hex($suffix) . '.tmp';
            $handle = $this->openExclusive($temporary, 'x+b');
            if (!is_resource($handle)) {
                return NotificationEnqueueResult::failure('outbox_unavailable');
            }
            $temporaryOwned = true;
            $written = @fwrite($handle, $bytes);
            $ok = $written === strlen($bytes) && @fflush($handle) && ($this->sync)($handle) && @fclose($handle);
            if (!$ok) {
                if (is_resource($handle)) @fclose($handle);
                return NotificationEnqueueResult::failure('outbox_unavailable');
            }
            if (!($this->publish)($temporary, $final)) {
                if (@lstat($final) !== false) {
                    return $this->compareExisting($final, $bytes);
                }
                return NotificationEnqueueResult::failure('outbox_unavailable');
            }
            if (!@chmod($final, 0600) || !$this->regularMode($final, 0600)) {
                return NotificationEnqueueResult::failure('outbox_security_invalid');
            }
            @unlink($temporary);
            $temporary = null;
            $temporaryOwned = false;
            $directory = @fopen($shard, 'r');
            if (!is_resource($directory) || !($this->sync)($directory) || !@fclose($directory)) {
                if (is_resource($directory)) @fclose($directory);
                return NotificationEnqueueResult::failure('outbox_unavailable');
            }
            return NotificationEnqueueResult::created();
        } catch (\Throwable) {
            return NotificationEnqueueResult::failure('outbox_unavailable');
        } finally {
            if ($temporaryOwned && is_string($temporary)) @unlink($temporary);
            if (is_resource($lock)) {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
            if (is_string($lockPath)) @unlink($lockPath);
        }
    }

    private function compareExisting(string $path, string $expected): NotificationEnqueueResult
    {
        $beforeStat = @lstat($path);
        if (
            $beforeStat === false
            || ($beforeStat['mode'] & 0170000) !== 0100000
            || ($beforeStat['mode'] & 0777) !== 0600
            || is_link($path)
        ) {
            return NotificationEnqueueResult::failure('outbox_security_invalid');
        }
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) return NotificationEnqueueResult::failure('outbox_unavailable');
        $pathStat = @lstat($path);
        $handleStat = @fstat($handle);
        if (
            $pathStat === false
            || $handleStat === false
            || ($pathStat['mode'] & 0170000) !== 0100000
            || ($pathStat['mode'] & 0777) !== 0600
            || $beforeStat['dev'] !== $pathStat['dev']
            || $beforeStat['ino'] !== $pathStat['ino']
            || $pathStat['dev'] !== $handleStat['dev']
            || $pathStat['ino'] !== $handleStat['ino']
            || is_link($path)
        ) {
            @fclose($handle);
            return NotificationEnqueueResult::failure('outbox_security_invalid');
        }
        $bytes = @fread($handle, self::MAX_EVENT_BYTES + 1);
        $extra = @fread($handle, 1);
        @fclose($handle);
        if (!is_string($bytes) || $extra !== '' || strlen($bytes) > self::MAX_EVENT_BYTES) {
            return NotificationEnqueueResult::failure('outbox_event_conflict');
        }
        return hash_equals($expected, $bytes)
            ? NotificationEnqueueResult::existing()
            : NotificationEnqueueResult::failure('outbox_event_conflict');
    }

    /** @param list<string> $segments */
    private function ensureDirectories(string $base, array $segments): string
    {
        $current = $base;
        foreach ($segments as $segment) {
            if (preg_match('/\A(?:[a-z0-9][a-z0-9-]*|[0-9a-f]{2})\z/D', $segment) !== 1) {
                throw new \InvalidArgumentException('Invalid notification outbox.');
            }
            $next = $current . '/' . $segment;
            $stat = @lstat($next);
            if ($stat === false) {
                $old = umask(0077);
                try {
                    @mkdir($next, 0700);
                } finally {
                    umask($old);
                }
                $stat = @lstat($next);
            }
            $real = @realpath($next);
            if (
                $stat === false
                || $real === false
                || ($stat['mode'] & 0170000) !== 0040000
                || is_link($next)
                || ($real !== $this->rootIfInitialized() && !str_starts_with($real, $base . '/'))
                || !@chmod($next, 0700)
                || ((@fileperms($next) ?: 0) & 0777) !== 0700
            ) {
                throw new \RuntimeException();
            }
            $current = $real;
        }
        return $current;
    }

    private function rootIfInitialized(): string
    {
        return isset($this->root) ? $this->root : '';
    }

    private function openExclusive(string $path, string $mode)
    {
        if (@lstat($path) !== false && is_link($path)) return false;
        $old = umask(0077);
        try {
            $handle = @fopen($path, $mode);
        } finally {
            umask($old);
        }
        $pathStat = @lstat($path);
        $handleStat = is_resource($handle) ? @fstat($handle) : false;
        if (
            !is_resource($handle)
            || $pathStat === false
            || $handleStat === false
            || ($pathStat['mode'] & 0170000) !== 0100000
            || $pathStat['dev'] !== $handleStat['dev']
            || $pathStat['ino'] !== $handleStat['ino']
            || !@chmod($path, 0600)
            || !$this->regularMode($path, 0600)
        ) {
            if (is_resource($handle)) @fclose($handle);
            return false;
        }
        return $handle;
    }

    private function regularMode(string $path, int $mode): bool
    {
        $stat = @lstat($path);
        return $stat !== false
            && ($stat['mode'] & 0170000) === 0100000
            && ($stat['mode'] & 0777) === $mode
            && !is_link($path);
    }

    private function entryCount(string $directory): int
    {
        $entries = @scandir($directory);
        if (!is_array($entries)) throw new \RuntimeException();
        return count($entries) - 2;
    }
}
