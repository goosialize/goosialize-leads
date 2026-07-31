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
     * @param array<string,list<string>> $errors
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
     * @param array<string,list<string>> $errors
     */
    public static function validationFailed(array $errors): self
    {
        if ($errors === [] || array_is_list($errors)) {
            throw new \InvalidArgumentException('Validation errors are required.');
        }

        $normalized = [];

        foreach ($errors as $field => $codes) {
            if (
                !is_string($field)
                || $field === ''
                || !is_array($codes)
                || !array_is_list($codes)
                || $codes === []
            ) {
                throw new \InvalidArgumentException('Invalid public validation errors.');
            }

            $unique = [];

            foreach ($codes as $code) {
                if (!is_string($code) || $code === '') {
                    throw new \InvalidArgumentException('Invalid public validation error code.');
                }

                if (!in_array($code, $unique, true)) {
                    $unique[] = $code;
                }
            }

            $normalized[$field] = $unique;
        }

        ksort($normalized, SORT_STRING);

        return new self('validation_failed', $normalized);
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

    /** @return array<string,list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array{
     *   outcome:string,
     *   errors:array<string,list<string>>
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
