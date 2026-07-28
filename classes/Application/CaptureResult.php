<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Application;

use Grav\Plugin\GoosializeLeads\Validation\ValidationError;

final class CaptureResult
{
    private const FAILURE_CODES = [
        'validation_failed',
        'invalid_submission_id',
        'idempotency_conflict',
        'storage_unavailable',
        'forms_configuration_invalid',
    ];

    /**
     * @param array{id:string,status:string,created_at:string}|null $record
     * @param list<ValidationError> $errors
     */
    private function __construct(
        private readonly bool $success,
        private readonly ?array $record,
        private readonly bool $replayed,
        private readonly ?string $code,
        private readonly array $errors
    ) {
    }

    /** @param array{id:string,status:string,created_at:string} $record */
    public static function success(array $record, bool $replayed): self
    {
        if (
            array_keys($record) !== ['id', 'status', 'created_at']
            || !is_string($record['id'])
            || $record['id'] === ''
            || !is_string($record['status'])
            || $record['status'] === ''
            || !is_string($record['created_at'])
            || $record['created_at'] === ''
        ) {
            throw new \InvalidArgumentException('Invalid capture record.');
        }

        return new self(true, $record, $replayed, null, []);
    }

    /** @param list<ValidationError> $errors */
    public static function failure(string $code, array $errors = []): self
    {
        if (!in_array($code, self::FAILURE_CODES, true) || !array_is_list($errors)) {
            throw new \InvalidArgumentException('Invalid capture failure.');
        }
        foreach ($errors as $error) {
            if (!$error instanceof ValidationError) {
                throw new \InvalidArgumentException('Invalid capture errors.');
            }
        }
        if ($code !== 'validation_failed' && $errors !== []) {
            throw new \InvalidArgumentException('Invalid capture error invariant.');
        }

        return new self(false, null, false, $code, $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /** @return array{id:string,status:string,created_at:string}|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function replayed(): bool
    {
        return $this->replayed;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    /** @return list<ValidationError> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<array{code:string,field:?string}> */
    public function errorsAsArray(): array
    {
        return array_map(static fn (ValidationError $error): array => $error->toArray(), $this->errors);
    }

    /** @return array{success:bool,record:?array,replayed:bool,code:?string,errors:list<array{code:string,field:?string}>} */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'record' => $this->record,
            'replayed' => $this->replayed,
            'code' => $this->code,
            'errors' => $this->errorsAsArray(),
        ];
    }
}
