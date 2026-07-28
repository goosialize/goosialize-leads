<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

final class ApiParseResult
{
    private const CODES = [
        'EMPTY_BODY', 'PAYLOAD_TOO_LARGE', 'INVALID_UTF8', 'MALFORMED_JSON',
        'TOO_DEEP', 'DUPLICATE_JSON_KEY', 'OBJECT_REQUIRED',
    ];

    /** @param array<string,mixed>|null $object */
    private function __construct(
        private readonly bool $valid,
        private readonly ?array $object,
        private readonly ?string $code
    ) {
    }

    /** @param array<string,mixed> $object */
    public static function valid(array $object): self
    {
        return new self(true, $object, null);
    }

    public static function failure(string $code): self
    {
        if (!in_array($code, self::CODES, true)) {
            throw new \InvalidArgumentException('Invalid API parse code.');
        }
        return new self(false, null, $code);
    }

    public function isValid(): bool { return $this->valid; }
    /** @return array<string,mixed>|null */
    public function object(): ?array { return $this->object; }
    public function code(): ?string { return $this->code; }
}
