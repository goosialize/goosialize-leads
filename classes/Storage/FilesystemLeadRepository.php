<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Storage;

use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;

final class FilesystemLeadRepository implements LeadRepository
{
    private const RECORD_LIMIT = 32768;
    private const SIDECAR_LIMIT = 1024;
    private readonly string $root;
    private readonly \Closure $temporaryEntropy;
    private bool $probed = false;

    /** @param callable(int):string $temporaryEntropy */
    public function __construct(
        string $userDataRoot,
        callable $temporaryEntropy,
        private readonly IdempotencyKeyRing $keyRing
    ) {
        if ($userDataRoot === ''
            || str_contains($userDataRoot, "\0")
            || $userDataRoot[0] !== '/'
            || preg_match('#(^|/)\.{1,2}(/|$)#', $userDataRoot) === 1
            || str_contains($userDataRoot, '://')
        ) {
            throw new \InvalidArgumentException('Invalid storage constructor input.');
        }
        $this->temporaryEntropy = \Closure::fromCallable($temporaryEntropy);
        $normalized = preg_replace('#/+#', '/', $userDataRoot);
        if (!is_string($normalized)) throw new \InvalidArgumentException('Invalid storage root.');
        $normalized = rtrim($normalized, '/') ?: '/';
        $stat = @lstat($normalized);
        if ($stat !== false && ($stat['mode'] & 0170000) === 0120000) {
            throw new StorageException('symlink_detected');
        }
        $real = @realpath($normalized);
        if ($stat === false || $real === false || ($stat['mode'] & 0170000) !== 0040000 || is_link($normalized)) {
            throw new StorageException('root_invalid');
        }
        $this->root = $this->ensureDirectory($real, ['goosialize-leads', 'v1']);
    }

    public function persist(PersistenceRequest $request): PersistenceResult
    {
        $lock = null;
        $result = null;
        $failure = null;
        try {
            $lockPath = $this->root . '/.capture.lock';
            $old = umask(0077);
            try {
                $lock = @fopen($lockPath, 'c+b');
            } finally {
                umask($old);
            }
            if (!is_resource($lock)) throw new StorageException('lock_failed');
            if (!@chmod($lockPath, 0600) || !$this->regularMode($lockPath, 0600)) throw new StorageException('permission_failed');
            if (!@flock($lock, LOCK_EX)) throw new StorageException('lock_failed');
            if (!$this->probed) {
                $this->probeHardLink();
                $this->probed = true;
            }
            $result = $this->persistLocked($request);
        } catch (StorageException $e) {
            $failure = $e;
        } catch (\Throwable $e) {
            $failure = new StorageException('unexpected_storage_failure', $e);
        }
        if (is_resource($lock)) {
            $unlocked = @flock($lock, LOCK_UN);
            $closed = @fclose($lock);
            if ((!$unlocked || !$closed) && $failure === null) {
                $failure = new StorageException('lock_failed');
            }
        }
        if ($failure !== null) throw $failure;
        if (!$result instanceof PersistenceResult) throw new StorageException('unexpected_storage_failure');
        return $result;
    }

    private function persistLocked(PersistenceRequest $request): PersistenceResult
    {
        $record = $request->record()->toArray();
        $id = $record['id'];
        $year = substr($record['created_at'], 0, 4);
        $month = substr($record['created_at'], 5, 2);
        if (preg_match('/\A[0-9a-f]{32}\z/D', $id) !== 1
            || preg_match('/\A\d{4}\z/D', $year) !== 1
            || preg_match('/\A\d{2}\z/D', $month) !== 1
            || strlen($request->recordBytes()) > self::RECORD_LIMIT
        ) {
            throw new \InvalidArgumentException('Invalid persistence request record.');
        }
        $records = $this->ensureDirectory($this->root, ['records', $year, $month]);
        $recordFinal = $records . '/' . $id . '.json';

        $sidecarFinal = null;
        if ($request->hasIdempotency()) {
            $digest = $request->keyDigest();
            if (!is_string($digest)) throw new \InvalidArgumentException('Missing digest.');
            $sidecars = $this->ensureDirectory($this->root, ['idempotency', substr($digest, 0, 2)]);
            $sidecarFinal = $sidecars . '/' . $digest . '.json';
            if (@lstat($sidecarFinal) !== false) {
                return $this->existingSidecar($sidecarFinal, $request);
            }
            $recovered = $this->recoverMissingSidecar($request, $sidecarFinal);
            if ($recovered !== null) return $recovered;
        }

        if (@lstat($recordFinal) !== false) {
            if (!$this->regularMode($recordFinal, 0600)) throw new StorageException('idempotency_index_invalid');
            return PersistenceResult::idCollision();
        }
        try {
            $this->publishBytes($records, '.tmp-' . $id . '-', $recordFinal, $request->recordBytes(), false);
        } catch (StorageException $e) {
            if ($e->stableCode() === 'publication_failed' && @lstat($recordFinal) !== false) {
                if (!$this->regularMode($recordFinal, 0600)) throw new StorageException('idempotency_index_invalid', $e);
                return PersistenceResult::idCollision();
            }
            throw $e;
        }
        if ($sidecarFinal === null) return PersistenceResult::created($request->record());

        $sidecar = $this->sidecar($request->record()->toArray());
        try {
            $this->publishBytes(dirname($sidecarFinal), '.tmp-sidecar-' . $request->keyDigest() . '-', $sidecarFinal, $sidecar, true);
        } catch (StorageException $e) {
            if ($e->stableCode() === 'publication_failed') {
                throw new StorageException('sidecar_publication_failed', $e);
            }
            throw $e;
        }
        return PersistenceResult::created($request->record());
    }

    private function existingSidecar(string $path, PersistenceRequest $request): PersistenceResult
    {
        $sidecar = $this->readJson($path, self::SIDECAR_LIMIT);
        $keys = ['schema_version', 'key_version', 'key_digest', 'payload_digest', 'lead_id', 'created_at', 'expires_at'];
        if (array_keys($sidecar) !== $keys
            || $sidecar['schema_version'] !== 1
            || !is_int($sidecar['key_version']) || $sidecar['key_version'] < 1
            || $sidecar['key_digest'] !== $request->keyDigest()
            || preg_match('/\A[0-9a-f]{64}\z/D', (string) $sidecar['payload_digest']) !== 1
            || preg_match('/\A[0-9a-f]{32}\z/D', (string) $sidecar['lead_id']) !== 1
            || !$this->timestamp($sidecar['created_at']) || !$this->timestamp($sidecar['expires_at'])
        ) throw new StorageException('idempotency_index_invalid');
        if ($request->record()->capturedAt() >= $sidecar['expires_at']) {
            return PersistenceResult::failure('idempotency_expired');
        }
        $payload = $request->payloadBytes();
        if (!is_string($payload)) throw new \InvalidArgumentException('Missing payload.');
        if (!$this->keyRing->verify($sidecar['key_version'], $payload, $sidecar['payload_digest'])) {
            return PersistenceResult::failure('idempotency_conflict');
        }
        $recordPath = $this->recordPathFor($sidecar['lead_id'], $sidecar['created_at']);
        $stored = $this->readJson($recordPath, self::RECORD_LIMIT);
        $this->validateStoredRecord($stored, $recordPath);
        if (($stored['id'] ?? null) !== $sidecar['lead_id']
            || ($stored['created_at'] ?? null) !== $sidecar['created_at']
            || ($stored['idempotency']['key_version'] ?? null) !== $sidecar['key_version']
            || ($stored['idempotency']['key_hash'] ?? null) !== $sidecar['key_digest']
            || ($stored['idempotency']['payload_fingerprint'] ?? null) !== $sidecar['payload_digest']
        ) throw new StorageException('idempotency_index_invalid');
        return PersistenceResult::replayed($stored);
    }

    private function recoverMissingSidecar(PersistenceRequest $request, string $sidecarFinal): ?PersistenceResult
    {
        $base = $this->root . '/records';
        if (!is_dir($base)) return null;
        $matches = [];
        $count = 0;
        foreach ($this->recordFiles($base) as $path) {
            if (++$count > 10000) throw new StorageException('idempotency_index_invalid');
            $record = $this->readJson($path, self::RECORD_LIMIT);
            $this->validateStoredRecord($record, $path);
            $idem = $record['idempotency'] ?? null;
            if (!is_array($idem) || ($idem['key_hash'] ?? null) !== $request->keyDigest()) continue;
            $payload = $request->payloadBytes();
            if (!is_string($payload)
                || !is_int($idem['key_version'] ?? null)
                || !is_string($idem['payload_fingerprint'] ?? null)
                || !$this->keyRing->verify($idem['key_version'], $payload, $idem['payload_fingerprint'])
            ) continue;
            $matches[] = $record;
            if (count($matches) > 1) throw new StorageException('idempotency_index_invalid');
        }
        if ($matches === []) return null;
        $bytes = $this->sidecar($matches[0]);
        $this->publishBytes(dirname($sidecarFinal), '.tmp-sidecar-' . $request->keyDigest() . '-', $sidecarFinal, $bytes, true);
        return PersistenceResult::replayed($matches[0]);
    }

    /** @return list<string> */
    private function recordFiles(string $base): array
    {
        $files = [];
        foreach ($this->safeEntries($base) as $year) {
            if (preg_match('/\A\d{4}\z/D', $year) !== 1) throw new StorageException('idempotency_index_invalid');
            $yp = $base . '/' . $year;
            $this->directoryContained($yp);
            foreach ($this->safeEntries($yp) as $month) {
                if (preg_match('/\A\d{2}\z/D', $month) !== 1) throw new StorageException('idempotency_index_invalid');
                $mp = $yp . '/' . $month;
                $this->directoryContained($mp);
                foreach ($this->safeEntries($mp) as $name) {
                    if (str_starts_with($name, '.tmp-')) continue;
                    if (preg_match('/\A[0-9a-f]{32}\.json\z/D', $name) !== 1) throw new StorageException('idempotency_index_invalid');
                    $path = $mp . '/' . $name;
                    if (!$this->regularMode($path, 0600)) throw new StorageException('idempotency_index_invalid');
                    $files[] = $path;
                }
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    /** @return list<string> */
    private function safeEntries(string $directory): array
    {
        $entries = @scandir($directory);
        if ($entries === false) throw new StorageException('idempotency_index_invalid');
        $entries = array_values(array_diff($entries, ['.', '..']));
        sort($entries, SORT_STRING);
        return $entries;
    }

    /** @param array<string,mixed> $record */
    private function sidecar(array $record): string
    {
        $idem = $record['idempotency'];
        $created = new \DateTimeImmutable($record['created_at']);
        $expires = $created->modify('+30 days')->format('Y-m-d\TH:i:s.u\Z');
        $data = [
            'schema_version' => 1,
            'key_version' => $idem['key_version'],
            'key_digest' => $idem['key_hash'],
            'payload_digest' => $idem['payload_fingerprint'],
            'lead_id' => $record['id'],
            'created_at' => $record['created_at'],
            'expires_at' => $expires,
        ];
        try {
            $bytes = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (\Throwable $e) {
            throw new StorageException('unexpected_storage_failure', $e);
        }
        if (strlen($bytes) > self::SIDECAR_LIMIT) throw new StorageException('unexpected_storage_failure');
        return $bytes;
    }

    private function publishBytes(string $directory, string $prefix, string $final, string $bytes, bool $sidecar): void
    {
        $suffix = $this->temporarySuffix();
        $temporary = $directory . '/' . $prefix . $suffix . '.json';
        $handle = null;
        $published = false;
        try {
            $old = umask(0077);
            try {
                $handle = @fopen($temporary, 'x+b');
            } finally {
                umask($old);
            }
            if (!is_resource($handle)) throw new StorageException('temporary_creation_failed');
            if (!@chmod($temporary, 0600)) throw new StorageException('permission_failed');
            $offset = 0;
            $length = strlen($bytes);
            while ($offset < $length) {
                $written = @fwrite($handle, substr($bytes, $offset));
                if ($written === false) throw new StorageException('write_failed');
                if ($written === 0) throw new StorageException('short_write');
                $offset += $written;
            }
            if ($offset !== $length) throw new StorageException('short_write');
            if (!@fflush($handle)) throw new StorageException('flush_failed');
            if (!function_exists('fsync') || !@fsync($handle)) throw new StorageException('file_fsync_failed');
            if (!$this->regularMode($temporary, 0600)) throw new StorageException('permission_failed');
            if (!@fclose($handle)) throw new StorageException('close_failed');
            $handle = null;
            if (!@link($temporary, $final)) {
                if (@lstat($final) !== false) {
                    if ($sidecar) throw new StorageException('sidecar_publication_failed');
                    throw new StorageException('publication_failed');
                }
                throw new StorageException($sidecar ? 'sidecar_publication_failed' : 'publication_failed');
            }
            $published = true;
            if (!$this->regularMode($final, 0600)
                || @filesize($final) !== $length
                || @fileinode($final) !== @fileinode($temporary)
            ) throw new StorageException($sidecar ? 'sidecar_publication_failed' : 'publication_failed');
        } finally {
            if (is_resource($handle)) @fclose($handle);
            if (@lstat($temporary) !== false && !@unlink($temporary)) {
                if ($published) throw new StorageException('cleanup_failed');
            }
        }
    }

    private function probeHardLink(): void
    {
        $suffix = $this->temporarySuffix();
        $source = $this->root . '/.probe-link-' . $suffix;
        $linked = $source . '.linked';
        $handle = null;
        try {
            $old = umask(0077);
            try { $handle = @fopen($source, 'x+b'); } finally { umask($old); }
            if (!is_resource($handle) || !@chmod($source, 0600)) throw new StorageException('publication_unsupported');
            $bytes = "phase-3b-link-probe\n";
            if (@fwrite($handle, $bytes) !== strlen($bytes) || !@fflush($handle) || !function_exists('fsync') || !@fsync($handle) || !@fclose($handle)) {
                $handle = null;
                throw new StorageException('publication_unsupported');
            }
            $handle = null;
            if (!@link($source, $linked)
                || @fileinode($source) !== @fileinode($linked)
                || @file_get_contents($linked) !== $bytes
            ) throw new StorageException('publication_unsupported');
        } finally {
            if (is_resource($handle)) @fclose($handle);
            $okLinked = @lstat($linked) === false || @unlink($linked);
            $okSource = @lstat($source) === false || @unlink($source);
            if (!$okLinked || !$okSource) throw new StorageException('publication_unsupported');
        }
    }

    private function temporarySuffix(): string
    {
        try { $bytes = ($this->temporaryEntropy)(8); } catch (\Throwable $e) {
            throw new StorageException('temporary_creation_failed', $e);
        }
        if (!is_string($bytes) || strlen($bytes) !== 8) throw new StorageException('temporary_creation_failed');
        return bin2hex($bytes);
    }

    /** @param list<string> $parts */
    private function ensureDirectory(string $base, array $parts): string
    {
        $baseReal = @realpath($base);
        if ($baseReal === false || !is_dir($baseReal) || is_link($base)) throw new StorageException('root_invalid');
        $path = $baseReal;
        foreach ($parts as $part) {
            if (preg_match('/\A[A-Za-z0-9_-]+\z/D', $part) !== 1) throw new StorageException('unsafe_path');
            $path .= '/' . $part;
            $stat = @lstat($path);
            if ($stat === false) {
                $old = umask(0077);
                try { $made = @mkdir($path, 0700); } finally { umask($old); }
                if (!$made && @lstat($path) === false) throw new StorageException('directory_creation_failed');
                if (!@chmod($path, 0700)) throw new StorageException('permission_failed');
            }
            $this->directoryContained($path);
        }
        return $path;
    }

    private function directoryContained(string $path): void
    {
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0040000) throw new StorageException('root_invalid');
        if (is_link($path)) throw new StorageException('symlink_detected');
        $real = @realpath($path);
        $anchor = isset($this->root) ? dirname(dirname($this->root)) : @realpath(dirname($path));
        if ($real === false || !is_string($anchor)
            || ($real !== $anchor && !str_starts_with($real, rtrim($anchor, '/') . '/'))
        ) throw new StorageException('unsafe_path');
        if ((fileperms($path) & 0777) !== 0700 || !$this->ownedByProcess($path)) {
            throw new StorageException('permission_failed');
        }
    }

    private function regularMode(string $path, int $mode): bool
    {
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || is_link($path)) return false;
        $real = @realpath($path);
        return $real !== false
            && ($real === $this->root || str_starts_with($real, $this->root . '/'))
            && ((@fileperms($path) & 0777) === $mode)
            && $this->ownedByProcess($path);
    }

    /** @return array<string,mixed> */
    private function readJson(string $path, int $limit): array
    {
        if (!$this->regularMode($path, 0600)) throw new StorageException('idempotency_index_invalid');
        $size = @filesize($path);
        if (!is_int($size) || $size < 2 || $size > $limit) throw new StorageException('idempotency_index_invalid');
        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || !str_ends_with($bytes, "\n")) throw new StorageException('idempotency_index_invalid');
        try { $data = json_decode($bytes, true, 8, JSON_THROW_ON_ERROR); } catch (\Throwable $e) {
            throw new StorageException('idempotency_index_invalid', $e);
        }
        if (!is_array($data) || array_is_list($data)) throw new StorageException('idempotency_index_invalid');
        try {
            $canonical = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (\Throwable $e) {
            throw new StorageException('idempotency_index_invalid', $e);
        }
        if (!hash_equals($canonical, $bytes)) throw new StorageException('idempotency_index_invalid');
        return $data;
    }

    private function recordPathFor(string $id, string $created): string
    {
        $path = $this->root . '/records/' . substr($created, 0, 4) . '/' . substr($created, 5, 2) . '/' . $id . '.json';
        if (!str_starts_with($path, $this->root . '/')) throw new StorageException('unsafe_path');
        return $path;
    }

    private function timestamp(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D', $value) === 1;
    }

    /** @param array<string,mixed> $record */
    private function validateStoredRecord(array $record, string $path): void
    {
        $keys = [
            'schema_version', 'id', 'created_at', 'updated_at', 'status', 'revision',
            'source', 'form_name', 'locale', 'consent', 'idempotency', 'full_name',
            'email', 'phone', 'company', 'message', 'resource_id', 'source_path', 'campaign',
        ];
        $id = $record['id'] ?? null;
        $created = $record['created_at'] ?? null;
        $consent = $record['consent'] ?? null;
        $idempotency = $record['idempotency'] ?? null;
        $campaign = $record['campaign'] ?? null;
        $nullableStrings = ['locale', 'full_name', 'email', 'phone', 'company', 'message', 'resource_id', 'source_path'];
        if (array_keys($record) !== $keys
            || $record['schema_version'] !== 1
            || !is_string($id) || preg_match('/\A[0-9a-f]{32}\z/D', $id) !== 1
            || !$this->timestamp($created)
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
            || !$this->validStoredIdempotency($idempotency)
            || ($campaign !== null && !$this->validStoredCampaign($campaign))
            || $path !== $this->recordPathFor($id, $created)
        ) {
            throw new StorageException('idempotency_index_invalid');
        }
        foreach ($nullableStrings as $field) {
            if ($record[$field] !== null && !is_string($record[$field])) {
                throw new StorageException('idempotency_index_invalid');
            }
        }
    }

    /** @param array<string,mixed> $idempotency */
    private function validStoredIdempotency(array $idempotency): bool
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

    private function validStoredCampaign(mixed $campaign): bool
    {
        if (!is_array($campaign)
            || array_keys($campaign) !== ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']
        ) {
            return false;
        }
        foreach ($campaign as $value) {
            if ($value !== null && !is_string($value)) return false;
        }
        return true;
    }

    private function ownedByProcess(string $path): bool
    {
        $status = @file_get_contents('/proc/self/status');
        if (!is_string($status) || preg_match('/^Uid:\s+\d+\s+(\d+)\s+/m', $status, $matches) !== 1) {
            return false;
        }
        $owner = @fileowner($path);
        return is_int($owner) && $owner === (int) $matches[1];
    }
}
