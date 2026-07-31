<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Integration;

final class LeadCaptureResultV1
{
    private const OUTCOMES = [
        'created',
        'replayed',
        'validation_failed',
        'idempotency_conflict',
        'unavailable',
    ];

    /**
     * @param list<array{code:string,field:?string}> $errors
     */
    private function __construct(
        private readonly string $outcome,
        private readonly array $errors
    ) {
    }

    public static function created(): self
    {
        return new self('created', []);
    }

    public static function replayed(): self
    {
        return new self('replayed', []);
    }

    /**
     * @param list<array{code:string,field:?string}> $errors
     */
    public static function validationFailed(array $errors): self
    {
        if ($errors === [] || !array_is_list($errors)) {
            throw new \InvalidArgumentException('Validation errors are required.');
        }

        foreach ($errors as $error) {
            if (
                !is_array($error)
                || array_keys($error) !== ['code', 'field']
                || !is_string($error['code'])
                || $error['code'] === ''
                || ($error['field'] !== null && !is_string($error['field']))
            ) {
                throw new \InvalidArgumentException('Invalid public validation error.');
            }
        }

        return new self('validation_failed', $errors);
    }

    public static function idempotencyConflict(): self
    {
        return new self('idempotency_conflict', []);
    }

    public static function unavailable(): self
    {
        return new self('unavailable', []);
    }

    public function outcome(): string
    {
        return $this->outcome;
    }

    /** @return list<array{code:string,field:?string}> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array{
     *   outcome:string,
     *   errors:list<array{code:string,field:?string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome,
            'errors' => $this->errors,
        ];
    }
}
