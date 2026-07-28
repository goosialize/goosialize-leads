<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

final class ApiRequestResult
{
    private const CODES = [
        'UNKNOWN_MEMBER', 'REQUEST_SCHEMA_INVALID',
        'MISSING_IDEMPOTENCY_KEY', 'INVALID_IDEMPOTENCY_KEY',
    ];

    /**
     * @param array<string,mixed>|null $submitted
     * @param array<string,mixed>|null $trusted
     */
    private function __construct(
        private readonly bool $valid,
        private readonly ?array $submitted,
        private readonly ?array $trusted,
        private readonly ?string $idempotencyKey,
        private readonly ?string $code
    ) {
    }

    /** @param array<string,mixed> $submitted @param array<string,mixed> $trusted */
    public static function valid(array $submitted, array $trusted, string $idempotencyKey): self
    {
        return new self(true, $submitted, $trusted, $idempotencyKey, null);
    }

    public static function failure(string $code): self
    {
        if (!in_array($code, self::CODES, true)) {
            throw new \InvalidArgumentException('Invalid API request code.');
        }
        return new self(false, null, null, null, $code);
    }

    public function isValid(): bool { return $this->valid; }
    /** @return array<string,mixed>|null */
    public function submitted(): ?array { return $this->submitted; }
    /** @return array<string,mixed>|null */
    public function trusted(): ?array { return $this->trusted; }
    public function idempotencyKey(): ?string { return $this->idempotencyKey; }
    public function code(): ?string { return $this->code; }
}
