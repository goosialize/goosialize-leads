<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Validation;

use Grav\Plugin\GoosializeLeads\Application\CaptureCommand;
use Grav\Plugin\GoosializeLeads\Domain\LeadRecord;

final class ValidationResult
{
    /**
     * @param list<ValidationError> $errors
     */
    private function __construct(
        private readonly bool $valid,
        private readonly object|string|null $value,
        private readonly array $errors
    ) {
    }

    public static function success(object|string $value): self
    {
        if (!$value instanceof CaptureCommand && !$value instanceof LeadRecord && !is_string($value)) {
            throw new \InvalidArgumentException('Invalid success value.');
        }

        return new self(true, $value, []);
    }

    /**
     * @param list<ValidationError> $errors
     */
    public static function failure(array $errors): self
    {
        if ($errors === [] || !array_is_list($errors)) {
            throw new \InvalidArgumentException('Failure requires an error list.');
        }
        foreach ($errors as $error) {
            if (!$error instanceof ValidationError) {
                throw new \InvalidArgumentException('Failure requires validation errors.');
            }
        }

        return new self(false, null, $errors);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function value(): object|string|null
    {
        return $this->value;
    }

    /** @return list<ValidationError> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<array{code:string,field:?string}> */
    public function errorsAsArray(): array
    {
        return array_map(
            static fn (ValidationError $error): array => $error->toArray(),
            $this->errors
        );
    }
}
