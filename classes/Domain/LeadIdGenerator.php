<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Domain;

use Grav\Plugin\GoosializeLeads\Validation\ValidationError;
use Grav\Plugin\GoosializeLeads\Validation\ValidationResult;

final class LeadIdGenerator
{
    public function __construct()
    {
    }

    /**
     * @param callable(int):string $entropy
     */
    public function generate(callable $entropy): ValidationResult
    {
        try {
            $bytes = $entropy(16);
        } catch (\Throwable) {
            return ValidationResult::failure([new ValidationError('invalid_generated_id', 'id')]);
        }
        if (!is_string($bytes) || strlen($bytes) !== 16) {
            return ValidationResult::failure([new ValidationError('invalid_generated_id', 'id')]);
        }

        return ValidationResult::success(bin2hex($bytes));
    }
}
