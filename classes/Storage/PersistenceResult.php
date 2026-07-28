<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Storage;

use Grav\Plugin\GoosializeLeads\Domain\LeadRecord;
use Grav\Plugin\GoosializeLeads\Validation\ValidationError;

final class PersistenceResult
{
    private const CODES = [
        'validation_failed', 'invalid_idempotency_key', 'idempotency_conflict',
        'idempotency_expired', 'collision_exhausted', 'storage_unavailable',
    ];

    /**
     * @param array<string,mixed>|null $record
     * @param list<ValidationError> $errors
     */
    private function __construct(
        private readonly string $status,
        private readonly ?array $record,
        private readonly ?string $code,
        private readonly array $errors
    ) {
    }

    public static function created(LeadRecord $record): self
    {
        return new self('created', $record->toArray(), null, []);
    }

    /** @param array<string,mixed> $record */
    public static function replayed(array $record): self
    {
        if ($record === [] || !isset($record['id']) || !is_string($record['id'])) {
            throw new \InvalidArgumentException('Invalid replay record.');
        }
        return new self('replayed', $record, null, []);
    }

    public static function idCollision(): self
    {
        return new self('id_collision', null, null, []);
    }

    /** @param list<ValidationError> $errors */
    public static function failure(string $code, array $errors = []): self
    {
        if (!in_array($code, self::CODES, true) || !array_is_list($errors)) {
            throw new \InvalidArgumentException('Invalid persistence failure.');
        }
        foreach ($errors as $error) {
            if (!$error instanceof ValidationError) {
                throw new \InvalidArgumentException('Invalid persistence errors.');
            }
        }
        if (($code === 'validation_failed') !== ($errors !== [])) {
            throw new \InvalidArgumentException('Invalid persistence error invariant.');
        }
        return new self('failure', null, $code, $errors);
    }

    public function status(): string { return $this->status; }
    /** @return array<string,mixed>|null */
    public function record(): ?array { return $this->record; }
    public function code(): ?string { return $this->code; }
    /** @return list<ValidationError> */
    public function errors(): array { return $this->errors; }
    /** @return list<array{code:string,field:?string}> */
    public function errorsAsArray(): array
    {
        return array_map(static fn (ValidationError $e): array => $e->toArray(), $this->errors);
    }
    public function isSuccess(): bool { return $this->status === 'created' || $this->status === 'replayed'; }
    /** @return array{status:string,record:array<string,mixed>|null,code:?string,errors:list<array{code:string,field:?string}>} */
    public function toArray(): array
    {
        return ['status' => $this->status, 'record' => $this->record, 'code' => $this->code, 'errors' => $this->errorsAsArray()];
    }
}
