<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Security;

use Grav\Plugin\GoosializeLeads\Application\CaptureCommand;
use Grav\Plugin\GoosializeLeads\Storage\StorageException;

final class IdempotencyKeyRing
{
    /** @var array<int,string> */
    private readonly array $keys;

    /** @param array<int,string> $encodedKeys */
    public function __construct(private readonly ?int $activeVersion, array $encodedKeys)
    {
        if ($activeVersion === null && $encodedKeys === []) {
            $this->keys = [];
            return;
        }
        if ($activeVersion === null || $activeVersion < 1 || $encodedKeys === [] || !array_key_exists($activeVersion, $encodedKeys)) {
            throw new StorageException('key_configuration_invalid');
        }
        $decoded = [];
        foreach ($encodedKeys as $version => $encoded) {
            if (!is_int($version) || $version < 1 || !is_string($encoded)) {
                throw new StorageException('key_configuration_invalid');
            }
            $key = base64_decode($encoded, true);
            if ($key === false || strlen($key) !== 32 || base64_encode($key) !== $encoded || in_array($key, $decoded, true)) {
                throw new StorageException('key_configuration_invalid');
            }
            $decoded[$version] = $key;
        }
        ksort($decoded, SORT_NUMERIC);
        $this->keys = $decoded;
    }

    public function enabled(): bool { return $this->activeVersion !== null; }
    public function activeVersion(): ?int { return $this->activeVersion; }

    public function keyDigest(?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null) return null;
        if (!$this->enabled()) throw new StorageException('key_configuration_invalid');
        if (preg_match('/\A[A-Za-z0-9._~-]{16,128}\z/D', $idempotencyKey) !== 1) {
            throw new \InvalidArgumentException('Invalid idempotency key.');
        }
        return hash('sha256', $idempotencyKey);
    }

    public function canonicalPayload(CaptureCommand $command): string
    {
        try {
            return json_encode($command->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        } catch (\Throwable $e) {
            throw new StorageException('unexpected_storage_failure', $e);
        }
    }

    public function payloadDigest(CaptureCommand $command): ?string
    {
        if (!$this->enabled()) return null;
        return hash_hmac('sha256', $this->canonicalPayload($command), $this->keys[$this->activeVersion]);
    }

    public function verify(int $version, string $payloadBytes, string $expectedDigest): bool
    {
        if ($version < 1 || preg_match('/\A[0-9a-f]{64}\z/D', $expectedDigest) !== 1 || !str_ends_with($payloadBytes, "\n")) {
            throw new \InvalidArgumentException('Invalid verification input.');
        }
        if (!isset($this->keys[$version])) throw new StorageException('key_configuration_invalid');
        return hash_equals(hash_hmac('sha256', $payloadBytes, $this->keys[$version]), $expectedDigest);
    }
}
