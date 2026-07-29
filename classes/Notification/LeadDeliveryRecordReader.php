<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class LeadDeliveryRecordReader
{
    private const MAX_RECORD_BYTES = 32768;
    private readonly string $recordsRoot;

    public function __construct(string $userDataRoot)
    {
        if ($userDataRoot === '' || $userDataRoot[0] !== '/' || str_contains($userDataRoot, "\0")) {
            throw new \InvalidArgumentException('Invalid user data root.');
        }
        $this->recordsRoot = rtrim($userDataRoot, '/') . '/goosialize-leads/v1/records';
    }

    /** @return array<string,mixed> */
    public function read(string $leadId): array
    {
        if (preg_match('/\A[0-9a-f]{32}\z/D', $leadId) !== 1) {
            throw new \RuntimeException('lead_invalid');
        }
        if (!is_dir($this->recordsRoot) || is_link($this->recordsRoot)) {
            throw new \RuntimeException('lead_missing');
        }
        $rootCount = 0;
        $candidateCount = 0;
        $match = null;
        foreach ($this->entries($this->recordsRoot, 256) as $year) {
            if (++$rootCount > 256 || preg_match('/\A\d{4}\z/D', $year) !== 1) {
                throw new \RuntimeException('lead_invalid');
            }
            $yearPath = $this->recordsRoot . '/' . $year;
            $this->assertDirectory($yearPath);
            $monthCount = 0;
            foreach ($this->entries($yearPath, 256) as $month) {
                if (++$monthCount > 256 || preg_match('/\A\d{2}\z/D', $month) !== 1) {
                    throw new \RuntimeException('lead_invalid');
                }
                $monthPath = $yearPath . '/' . $month;
                $this->assertDirectory($monthPath);
                foreach ($this->entries($monthPath, 10000 - $candidateCount) as $filename) {
                    if (++$candidateCount > 10000 || preg_match('/\A[0-9a-f]{32}\.json\z/D', $filename) !== 1) {
                        throw new \RuntimeException('lead_invalid');
                    }
                    if ($filename === $leadId . '.json') {
                        if ($match !== null) {
                            throw new \RuntimeException('lead_invalid');
                        }
                        $match = [$monthPath . '/' . $filename, $year, $month];
                    }
                }
            }
        }
        if ($match === null) {
            throw new \RuntimeException('lead_missing');
        }
        return $this->readRecord($match[0], $leadId, $match[1], $match[2]);
    }

    /** @return list<string> */
    private function entries(string $directory, int $limit): array
    {
        $handle = @opendir($directory);
        if (!is_resource($handle)) throw new \RuntimeException('lead_invalid');
        $entries = [];
        try {
            while (($entry = readdir($handle)) !== false) {
                if ($entry !== '.' && $entry !== '..') {
                    if (count($entries) >= $limit) throw new \RuntimeException('lead_invalid');
                    $entries[] = $entry;
                }
            }
        } finally {
            closedir($handle);
        }
        sort($entries, SORT_STRING);
        return $entries;
    }

    private function assertDirectory(string $path): void
    {
        $stat = @lstat($path);
        $real = @realpath($path);
        if (
            $stat === false || $real === false || is_link($path)
            || ($stat['mode'] & 0170000) !== 0040000
            || ($stat['mode'] & 0777) !== 0700
            || !str_starts_with($real, $this->recordsRoot . '/')
        ) throw new \RuntimeException('lead_invalid');
    }

    /** @return array<string,mixed> */
    private function readRecord(string $path, string $id, string $year, string $month): array
    {
        $before = @lstat($path);
        $real = @realpath($path);
        if (
            $before === false || $real === false || is_link($path)
            || ($before['mode'] & 0170000) !== 0100000
            || ($before['mode'] & 0777) !== 0600
            || !str_starts_with($real, $this->recordsRoot . '/')
            || !is_int($before['size']) || $before['size'] < 2 || $before['size'] > self::MAX_RECORD_BYTES
        ) throw new \RuntimeException('lead_invalid');
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) throw new \RuntimeException('lead_invalid');
        try {
            $after = @lstat($path);
            $opened = @fstat($handle);
            if (
                $after === false || $opened === false
                || $before['dev'] !== $after['dev'] || $before['ino'] !== $after['ino']
                || $after['dev'] !== $opened['dev'] || $after['ino'] !== $opened['ino']
                || $after['size'] !== $opened['size']
                || ($opened['mode'] & 0170000) !== 0100000 || ($opened['mode'] & 0777) !== 0600
            ) throw new \RuntimeException('lead_invalid');
            $bytes = @stream_get_contents($handle, self::MAX_RECORD_BYTES + 1);
        } finally {
            @fclose($handle);
        }
        if (!is_string($bytes) || strlen($bytes) !== $before['size'] || !str_ends_with($bytes, "\n") || preg_match('//u', $bytes) !== 1) {
            throw new \RuntimeException('lead_invalid');
        }
        try {
            $record = json_decode($bytes, true, 8, JSON_THROW_ON_ERROR);
            $canonical = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (\Throwable) {
            throw new \RuntimeException('lead_invalid');
        }
        if (!is_array($record) || array_is_list($record) || !hash_equals($canonical, $bytes) || !$this->validRecord($record, $id, $year, $month)) {
            throw new \RuntimeException('lead_invalid');
        }
        return $record;
    }

    /** @param array<string,mixed> $record */
    private function validRecord(array $record, string $id, string $year, string $month): bool
    {
        $keys = [
            'schema_version', 'id', 'created_at', 'updated_at', 'status', 'revision',
            'source', 'form_name', 'locale', 'consent', 'idempotency', 'full_name',
            'email', 'phone', 'company', 'message', 'resource_id', 'source_path', 'campaign',
        ];
        if (array_keys($record) !== $keys || $record['schema_version'] !== 1 || $record['id'] !== $id) return false;
        $created = $record['created_at'];
        if (!$this->timestamp($created) || substr($created, 0, 4) !== $year || substr($created, 5, 2) !== $month
            || $record['updated_at'] !== $created || $record['status'] !== 'new' || $record['revision'] !== 1
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', (string) $record['source']) !== 1
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', (string) $record['form_name']) !== 1
        ) return false;
        foreach (['locale','full_name','email','phone','company','message','resource_id','source_path'] as $field) {
            if ($record[$field] !== null && !is_string($record[$field])) return false;
        }
        $consent = $record['consent'];
        if (!is_array($consent) || array_keys($consent) !== ['granted','version','captured_at']
            || $consent['granted'] !== true || !is_string($consent['version'])
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $consent['version']) !== 1
            || $consent['captured_at'] !== $created
        ) return false;
        $idem = $record['idempotency'];
        if (!is_array($idem) || array_keys($idem) !== ['key_version','key_hash','payload_fingerprint']) return false;
        $allNull = $idem['key_version'] === null && $idem['key_hash'] === null && $idem['payload_fingerprint'] === null;
        $allValid = is_int($idem['key_version']) && $idem['key_version'] > 0
            && is_string($idem['key_hash']) && preg_match('/\A[0-9a-f]{64}\z/D', $idem['key_hash']) === 1
            && is_string($idem['payload_fingerprint']) && preg_match('/\A[0-9a-f]{64}\z/D', $idem['payload_fingerprint']) === 1;
        if (!$allNull && !$allValid) return false;
        $campaign = $record['campaign'];
        if ($campaign !== null) {
            if (!is_array($campaign) || array_keys($campaign) !== ['utm_source','utm_medium','utm_campaign','utm_term','utm_content']) return false;
            foreach ($campaign as $value) if ($value !== null && !is_string($value)) return false;
        }
        return true;
    }

    private function timestamp(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D', $value) === 1;
    }
}
