<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

final class RateLimitResult
{
    private function __construct(
        private readonly bool $allowed,
        private readonly int $limit,
        private readonly int $remaining,
        private readonly int $retryAfter,
        private readonly ?string $code
    ) {
    }

    public static function allowed(int $limit, int $remaining): self
    {
        return new self(true, $limit, max(0, $remaining), 0, null);
    }

    public static function limited(int $limit, int $retryAfter): self
    {
        return new self(false, $limit, 0, max(1, $retryAfter), 'RATE_LIMITED');
    }

    public static function unavailable(int $limit): self
    {
        return new self(false, $limit, 0, 0, 'RATE_LIMIT_UNAVAILABLE');
    }

    public function isAllowed(): bool { return $this->allowed; }
    public function limit(): int { return $this->limit; }
    public function remaining(): int { return $this->remaining; }
    public function retryAfter(): int { return $this->retryAfter; }
    public function code(): ?string { return $this->code; }
}
