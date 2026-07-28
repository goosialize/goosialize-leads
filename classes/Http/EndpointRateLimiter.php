<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

final class EndpointRateLimiter
{
    /** @param callable():int $clock */
    public function __construct(
        private readonly string $userDataRoot,
        private readonly mixed $clock
    ) {
        if ($userDataRoot === '' || !is_callable($clock)) {
            throw new \InvalidArgumentException('Invalid rate limiter configuration.');
        }
    }

    public function check(string $clientAddress, int $limit, int $windowSeconds): RateLimitResult
    {
        $address = filter_var($clientAddress, FILTER_VALIDATE_IP);
        if (!is_string($address) || $limit !== 10 || $windowSeconds !== 60) return RateLimitResult::unavailable($limit);
        if (str_contains($address, ':')) {
            $packed = inet_pton($address);
            if ($packed === false) return RateLimitResult::unavailable($limit);
            $address = strtolower(inet_ntop($packed));
        }
        $root = rtrim($this->userDataRoot, DIRECTORY_SEPARATOR) . '/goosialize-leads/v1/api-rate-limit';
        try {
            $canonicalUserRoot = realpath($this->userDataRoot);
            if ($canonicalUserRoot === false) throw new \RuntimeException();
            if (!is_dir($root) && !@mkdir($root, 0700, true) && !is_dir($root)) throw new \RuntimeException();
            if (!@chmod($root, 0700)) throw new \RuntimeException();
            if (is_link($root) || !is_dir($root)) throw new \RuntimeException();
            $canonicalRoot = realpath($root);
            if ($canonicalRoot === false
                || !str_starts_with($canonicalRoot, $canonicalUserRoot . DIRECTORY_SEPARATOR)
            ) throw new \RuntimeException();
            $entries = @glob($root . '/*.json', GLOB_NOSORT);
            if (!is_array($entries)) throw new \RuntimeException();
            $now = ($this->clock)();
            if (!is_int($now)) throw new \RuntimeException();
            $cleanupPath = $root . '/.cleanup.lock';
            if (is_link($cleanupPath)) throw new \RuntimeException();
            $cleanup = @fopen($cleanupPath, 'c+b');
            if ($cleanup === false || !@chmod($cleanupPath, 0600)) throw new \RuntimeException();
            try {
                $removed = 0;
                if (flock($cleanup, LOCK_EX | LOCK_NB)) {
                    foreach ($entries as $entry) {
                        if ($removed >= 100 || is_link($entry) || !is_file($entry)) continue;
                        $candidateHandle = @fopen($entry, 'r+b');
                        if ($candidateHandle === false) continue;
                        try {
                            if (!flock($candidateHandle, LOCK_EX | LOCK_NB)) continue;
                            $raw = stream_get_contents($candidateHandle);
                            try {
                                $candidate = is_string($raw) ? json_decode($raw, true, 3, JSON_THROW_ON_ERROR) : null;
                            } catch (\Throwable) {
                                $candidate = null;
                            }
                            if (is_array($candidate) && isset($candidate['window_start'])
                                && is_int($candidate['window_start'])
                                && $now >= $candidate['window_start'] + $windowSeconds
                                && unlink($entry)
                            ) $removed++;
                        } finally {
                            flock($candidateHandle, LOCK_UN);
                            fclose($candidateHandle);
                        }
                    }
                    flock($cleanup, LOCK_UN);
                }
            } finally {
                fclose($cleanup);
            }
            if ($removed > 0) {
                $entries = @glob($root . '/*.json', GLOB_NOSORT);
                if (!is_array($entries)) throw new \RuntimeException();
            }
            $path = $root . '/' . hash('sha256', $address) . '.json';
            if (count($entries) >= 10000 && !is_file($path)) throw new \RuntimeException();
            if (is_link($path)) throw new \RuntimeException();
            $handle = @fopen($path, 'c+b');
            if ($handle === false) throw new \RuntimeException();
            if (!@chmod($path, 0600)) throw new \RuntimeException();
            $stat = fstat($handle);
            $pathStat = lstat($path);
            if (!is_array($stat) || !is_array($pathStat)
                || $stat['dev'] !== $pathStat['dev'] || $stat['ino'] !== $pathStat['ino']
            ) {
                fclose($handle);
                throw new \RuntimeException();
            }
            try {
                if (!flock($handle, LOCK_EX)) throw new \RuntimeException();
                rewind($handle);
                $bytes = stream_get_contents($handle);
                if ($bytes === false) throw new \RuntimeException();
                $state = ['window_start' => $now, 'count' => 0];
                if ($bytes !== '') {
                    $decoded = json_decode($bytes, true, 3, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded) || array_keys($decoded) !== ['window_start', 'count']
                        || !is_int($decoded['window_start']) || !is_int($decoded['count'])
                        || $decoded['count'] < 0 || $decoded['window_start'] > $now
                    ) throw new \RuntimeException();
                    $state = $decoded;
                }
                if ($now >= $state['window_start'] + $windowSeconds) {
                    $state = ['window_start' => $now, 'count' => 0];
                }
                if ($state['count'] >= $limit) {
                    return RateLimitResult::limited($limit, max(1, $state['window_start'] + $windowSeconds - $now));
                }
                $state['count']++;
                $encoded = json_encode($state, JSON_THROW_ON_ERROR) . "\n";
                rewind($handle);
                if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                    throw new \RuntimeException();
                }
                return RateLimitResult::allowed($limit, $limit - $state['count']);
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (\Throwable) {
            return RateLimitResult::unavailable($limit);
        }
    }
}
