<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Storage;

use Grav\Plugin\GoosializeLeads\Admin\LeadIndexCollection;
use Grav\Plugin\GoosializeLeads\Admin\LeadIndexQuery;
use Grav\Plugin\GoosializeLeads\Admin\LeadSummary;

final class FilesystemLeadReadRepository implements LeadReadRepository
{
    private const MAX_DIRECTORY_ENTRIES = 256;
    private const MAX_RECORDS = 10000;
    private const MAX_RECORD_BYTES = 32768;
    private readonly string $recordsRoot;

    public function __construct(string $userDataRoot)
    {
        $base = @realpath($userDataRoot);
        if ($base === false || !@is_dir($base) || @is_link($userDataRoot)) {
            throw new StorageException('lead_index_storage_invalid');
        }
        $this->recordsRoot = $base . '/goosialize-leads/v1/records';
    }

    public function latest(LeadIndexQuery $query): LeadIndexCollection
    {
        try {
            if ($query->limit() !== 100) throw new \InvalidArgumentException('Invalid Lead index query.');
            if (@lstat($this->recordsRoot) === false) return LeadIndexCollection::create([], false, 0);
            $root = $this->checkedDirectory($this->recordsRoot);
            $records = [];
            $directoryEntries = 0;
            $recordCandidates = 0;
            foreach ($this->directoryEntries($root, $directoryEntries) as $year) {
                if (preg_match('/\A\d{4}\z/D', $year) !== 1) throw new StorageException('lead_index_storage_invalid');
                $yearPath = $this->checkedDirectory($root . '/' . $year);
                foreach ($this->directoryEntries($yearPath, $directoryEntries) as $month) {
                    if (preg_match('/\A(?:0[1-9]|1[0-2])\z/D', $month) !== 1) throw new StorageException('lead_index_storage_invalid');
                    $monthPath = $this->checkedDirectory($yearPath . '/' . $month);
                    foreach ($this->recordEntries($monthPath, $recordCandidates) as $name) {
                        $records[] = $this->readRecord($monthPath . '/' . $name, substr($name, 0, 32), $year, $month);
                    }
                }
            }
            usort($records, static function (array $a, array $b): int {
                $time = strcmp($b['created_at'], $a['created_at']);
                return $time !== 0 ? $time : strcmp($a['id'], $b['id']);
            });
            $total = count($records);
            $summaries = array_map(
                static fn (array $record): LeadSummary => LeadSummary::fromRecord($record),
                array_slice($records, 0, $query->limit())
            );
            return LeadIndexCollection::create($summaries, $total > $query->limit(), $total);
        } catch (StorageException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new StorageException('unexpected_storage_failure', $error);
        }
    }

    /** @return list<string> */
    private function directoryEntries(string $directory, int &$count): array
    {
        $handle = @opendir($directory);
        if (!is_resource($handle)) throw new StorageException('lead_index_storage_invalid');
        $entries = [];
        try {
            while (($entry = @readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') continue;
                if (++$count > self::MAX_DIRECTORY_ENTRIES) {
                    throw new StorageException('lead_index_capacity_exceeded');
                }
                $entries[] = $entry;
            }
        } finally {
            @closedir($handle);
        }
        sort($entries, SORT_STRING);
        return $entries;
    }

    /** @return list<string> */
    private function recordEntries(string $directory, int &$count): array
    {
        $handle = @opendir($directory);
        if (!is_resource($handle)) throw new StorageException('lead_index_storage_invalid');
        $entries = [];
        try {
            while (($entry = @readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') continue;
                if (preg_match('/\A[0-9a-f]{32}\.json\z/D', $entry) !== 1) {
                    throw new StorageException('lead_index_storage_invalid');
                }
                if (++$count > self::MAX_RECORDS) {
                    throw new StorageException('lead_index_capacity_exceeded');
                }
                $entries[] = $entry;
            }
        } finally {
            @closedir($handle);
        }
        sort($entries, SORT_STRING);
        return $entries;
    }

    private function checkedDirectory(string $path): string
    {
        $stat = @lstat($path);
        $real = @realpath($path);
        if ($stat === false || $real === false || @is_link($path) || ($stat['mode'] & 0170000) !== 0040000
            || ($stat['mode'] & 0777) !== 0700
            || ($real !== $this->recordsRoot && !str_starts_with($real, $this->recordsRoot . '/'))
        ) throw new StorageException('lead_index_storage_invalid');
        return $real;
    }

    /** @return array<string,mixed> */
    private function readRecord(string $path, string $expectedId, string $year, string $month): array
    {
        $stat = @lstat($path);
        $real = @realpath($path);
        if ($stat === false || $real === false || @is_link($path) || ($stat['mode'] & 0170000) !== 0100000
            || ($stat['mode'] & 0777) !== 0600 || !str_starts_with($real, $this->recordsRoot . '/')
            || !is_int($stat['size']) || $stat['size'] < 2 || $stat['size'] > self::MAX_RECORD_BYTES
        ) throw new StorageException('lead_index_record_invalid');
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) throw new StorageException('lead_index_record_invalid');
        $closed = false;
        try {
            $opened = @fstat($handle);
            if ($opened === false
                || $opened['dev'] !== $stat['dev']
                || $opened['ino'] !== $stat['ino']
                || ($opened['mode'] & 0170000) !== 0100000
                || ($opened['mode'] & 0777) !== 0600
                || $opened['size'] !== $stat['size']
            ) throw new StorageException('lead_index_record_invalid');
            $bytes = @stream_get_contents($handle, self::MAX_RECORD_BYTES + 1);
        } finally {
            $closed = @fclose($handle);
        }
        if (!$closed || !is_string($bytes) || strlen($bytes) !== $stat['size'] || !str_ends_with($bytes, "\n")
            || preg_match('//u', $bytes) !== 1
        ) throw new StorageException('lead_index_record_invalid');
        try {
            $record = json_decode($bytes, true, 8, JSON_THROW_ON_ERROR);
            $canonical = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (\Throwable $error) {
            throw new StorageException('lead_index_record_invalid', $error);
        }
        if (!is_array($record) || array_is_list($record) || !hash_equals($canonical, $bytes)
            || !$this->validRecord($record, $expectedId, $year, $month)
        ) throw new StorageException('lead_index_record_invalid');
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
        $created = $record['created_at'] ?? null;
        $consent = $record['consent'] ?? null;
        $idempotency = $record['idempotency'] ?? null;
        if (array_keys($record) !== $keys
            || $record['schema_version'] !== 1
            || $record['id'] !== $id
            || !$this->timestamp($created)
            || substr($created, 0, 4) !== $year
            || substr($created, 5, 2) !== $month
            || ($record['updated_at'] ?? null) !== $created
            || ($record['status'] ?? null) !== 'new'
            || ($record['revision'] ?? null) !== 1
            || !is_string($record['source'] ?? null)
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $record['source']) !== 1
            || !is_string($record['form_name'] ?? null)
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $record['form_name']) !== 1
            || !is_array($consent)
            || array_keys($consent) !== ['granted', 'version', 'captured_at']
            || ($consent['granted'] ?? null) !== true
            || !is_string($consent['version'] ?? null)
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $consent['version']) !== 1
            || ($consent['captured_at'] ?? null) !== $created
            || !is_array($idempotency)
            || array_keys($idempotency) !== ['key_version', 'key_hash', 'payload_fingerprint']
            || !$this->validIdempotency($idempotency)
            || !$this->validCampaign($record['campaign'])
        ) return false;
        foreach (['locale', 'full_name', 'email', 'phone', 'company', 'message', 'resource_id', 'source_path'] as $field) {
            if ($record[$field] !== null && !is_string($record[$field])) return false;
        }
        return true;
    }

    private function timestamp(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D', $value) === 1;
    }

    /** @param array<string,mixed> $idempotency */
    private function validIdempotency(array $idempotency): bool
    {
        $allNull = $idempotency['key_version'] === null
            && $idempotency['key_hash'] === null
            && $idempotency['payload_fingerprint'] === null;
        $allValid = is_int($idempotency['key_version'])
            && $idempotency['key_version'] > 0
            && is_string($idempotency['key_hash'])
            && preg_match('/\A[0-9a-f]{64}\z/D', $idempotency['key_hash']) === 1
            && is_string($idempotency['payload_fingerprint'])
            && preg_match('/\A[0-9a-f]{64}\z/D', $idempotency['payload_fingerprint']) === 1;
        return $allNull || $allValid;
    }

    private function validCampaign(mixed $campaign): bool
    {
        if ($campaign === null) return true;
        if (!is_array($campaign)
            || array_keys($campaign) !== ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']
        ) return false;
        foreach ($campaign as $value) {
            if ($value !== null && !is_string($value)) return false;
        }
        return true;
    }
}
