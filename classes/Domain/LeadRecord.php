<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Domain;

use Grav\Plugin\GoosializeLeads\Application\CaptureCommand;
use Grav\Plugin\GoosializeLeads\Validation\ValidationError;
use Grav\Plugin\GoosializeLeads\Validation\ValidationResult;

final class LeadRecord
{
    /** @param array<string,mixed> $data */
    private function __construct(private readonly array $data)
    {
    }

    /**
     * @param callable(int):string $entropy
     * @param callable():\DateTimeInterface $clock
     */
    public static function fromCommand(
        CaptureCommand $command,
        callable $entropy,
        callable $clock
    ): ValidationResult {
        $idResult = (new LeadIdGenerator())->generate($entropy);
        if (!$idResult->isValid()) {
            return $idResult;
        }
        try {
            $time = $clock();
        } catch (\Throwable) {
            return self::failure('invalid_timestamp', 'created_at');
        }
        if (!$time instanceof \DateTimeInterface) {
            return self::failure('invalid_timestamp', 'created_at');
        }
        $copy = \DateTimeImmutable::createFromInterface($time)->setTimezone(new \DateTimeZone('UTC'));
        $timestamp = $copy->format('Y-m-d\TH:i:s.u\Z');
        $commandData = $command->toArray();
        $trusted = $command->trusted();
        $consent = [
            'granted' => true,
            'version' => $trusted['consent_version'],
            'captured_at' => $timestamp,
        ];
        $record = [
            'schema_version' => 1,
            'id' => $idResult->value(),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'status' => 'new',
            'revision' => 1,
            'source' => $commandData['source'],
            'form_name' => $commandData['form_name'],
            'locale' => $commandData['locale'],
            'consent' => $consent,
            'idempotency' => [
                'key_version' => null,
                'key_hash' => null,
                'payload_fingerprint' => null,
            ],
            'full_name' => $commandData['full_name'],
            'email' => $commandData['email'],
            'phone' => $commandData['phone'],
            'company' => $commandData['company'],
            'message' => $commandData['message'],
            'resource_id' => $commandData['resource_id'],
            'source_path' => $commandData['source_path'],
            'campaign' => $commandData['campaign'],
        ];

        return ValidationResult::success(new self($record));
    }

    public function id(): string
    {
        return $this->data['id'];
    }

    public function capturedAt(): string
    {
        return $this->data['created_at'];
    }

    public function status(): string
    {
        return $this->data['status'];
    }

    /** @return array<string,mixed> */
    public function data(): array
    {
        return $this->data;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function serialize(LeadRecord $record): ValidationResult
    {
        if ($record !== $this) {
            throw new \InvalidArgumentException('Serialization requires the receiver.');
        }
        $error = $this->canonicalError();
        if ($error !== null) {
            return ValidationResult::failure([$error]);
        }
        try {
            $json = json_encode(
                $this->toArray(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) . "\n";
        } catch (\Throwable) {
            return self::failure('canonical_serialization_failed', null);
        }
        if (strlen($json) > 32768) {
            return self::failure('canonical_record_too_large', null);
        }

        return ValidationResult::success($json);
    }

    private function canonicalError(): ?ValidationError
    {
        $keys = [
            'schema_version', 'id', 'created_at', 'updated_at', 'status', 'revision',
            'source', 'form_name', 'locale', 'consent', 'idempotency', 'full_name',
            'email', 'phone', 'company', 'message', 'resource_id', 'source_path', 'campaign',
        ];
        if (array_keys($this->data) !== $keys) {
            return new ValidationError('canonical_serialization_failed', null);
        }
        if ($this->data['schema_version'] !== 1) return new ValidationError('invalid_schema_version', 'schema_version');
        if (!is_string($this->data['id']) || preg_match('/\A[0-9a-f]{32}\z/D', $this->data['id']) !== 1) return new ValidationError('invalid_generated_id', 'id');
        if (!$this->validTimestamp($this->data['created_at'])) return new ValidationError('invalid_timestamp', 'created_at');
        if (!$this->validTimestamp($this->data['updated_at'])) return new ValidationError('invalid_updated_timestamp', 'updated_at');
        if ($this->data['created_at'] !== $this->data['updated_at']) return new ValidationError('capture_timestamps_mismatch', 'updated_at');
        if ($this->data['status'] !== 'new') return new ValidationError('invalid_initial_status', 'status');
        if ($this->data['revision'] !== 1) return new ValidationError('invalid_initial_revision', 'revision');
        if (!is_string($this->data['source']) || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $this->data['source']) !== 1) return new ValidationError('invalid_source', 'source');
        if (!is_string($this->data['form_name']) || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $this->data['form_name']) !== 1) return new ValidationError('invalid_form_name', 'form_name');
        if ($this->data['locale'] !== null
            && (!is_string($this->data['locale']) || preg_match('/\A[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*\z/D', $this->data['locale']) !== 1)
        ) return new ValidationError('invalid_locale', 'locale');
        if (!is_array($this->data['consent']) || array_is_list($this->data['consent'])) return new ValidationError('canonical_serialization_failed', null);
        if (!array_key_exists('version', $this->data['consent'])) return new ValidationError('consent_version_missing', 'consent.version');
        if (!is_string($this->data['consent']['version'])
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $this->data['consent']['version']) !== 1
        ) return new ValidationError('invalid_consent_version', 'consent.version');
        if (!array_key_exists('captured_at', $this->data['consent'])) return new ValidationError('consent_timestamp_missing', 'consent.captured_at');
        if (!$this->validTimestamp($this->data['consent']['captured_at'])
            || $this->data['consent']['captured_at'] !== $this->data['created_at']
        ) return new ValidationError('invalid_consent_timestamp', 'consent.captured_at');
        if (array_keys($this->data['consent']) !== ['granted', 'version', 'captured_at']
            || $this->data['consent']['granted'] !== true
        ) return new ValidationError('canonical_serialization_failed', null);
        if (!is_array($this->data['idempotency']) || array_keys($this->data['idempotency']) !== ['key_version', 'key_hash', 'payload_fingerprint']) return new ValidationError('invalid_idempotency', 'idempotency');
        $idempotency = $this->data['idempotency'];
        if ($idempotency['key_version'] !== null && (!is_int($idempotency['key_version']) || $idempotency['key_version'] < 1)) return new ValidationError('invalid_idempotency_key_version', 'idempotency.key_version');
        if ($idempotency['key_hash'] !== null && (!is_string($idempotency['key_hash']) || preg_match('/\A[0-9a-f]{64}\z/D', $idempotency['key_hash']) !== 1)) return new ValidationError('invalid_idempotency_key_hash', 'idempotency.key_hash');
        if ($idempotency['payload_fingerprint'] !== null && (!is_string($idempotency['payload_fingerprint']) || preg_match('/\A[0-9a-f]{64}\z/D', $idempotency['payload_fingerprint']) !== 1)) return new ValidationError('invalid_payload_fingerprint', 'idempotency.payload_fingerprint');

        return null;
    }

    private function validTimestamp(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D', $value) === 1;
    }

    private static function failure(string $code, ?string $field): ValidationResult
    {
        return ValidationResult::failure([new ValidationError($code, $field)]);
    }
}
