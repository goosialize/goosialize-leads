<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Integration;

final class LeadCaptureContextV1
{
    private function __construct(
        private readonly string $source,
        private readonly string $formName,
        private readonly ?string $locale,
        private readonly string $consentVersion,
        private readonly string $idempotencyKey
    ) {
    }

    public static function create(
        string $source,
        string $formName,
        ?string $locale,
        string $consentVersion,
        string $idempotencyKey
    ): self {
        if (!self::validSlug($source)) {
            throw new \InvalidArgumentException('Invalid Lead capture source.');
        }

        if (!self::validSlug($formName)) {
            throw new \InvalidArgumentException('Invalid Lead capture form name.');
        }

        if (
            $locale !== null
            && preg_match('/\A[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*\z/D', $locale) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid Lead capture locale.');
        }

        if (!self::validSlug($consentVersion)) {
            throw new \InvalidArgumentException('Invalid Lead capture consent version.');
        }

        if (preg_match('/\A[A-Za-z0-9._~-]{16,128}\z/D', $idempotencyKey) !== 1) {
            throw new \InvalidArgumentException('Invalid Lead capture idempotency key.');
        }

        return new self(
            $source,
            $formName,
            $locale,
            $consentVersion,
            $idempotencyKey
        );
    }

    public function source(): string
    {
        return $this->source;
    }

    public function formName(): string
    {
        return $this->formName;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function consentVersion(): string
    {
        return $this->consentVersion;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    /** @return array{source:string,form_name:string,locale:?string,consent_version:string} */
    public function trusted(): array
    {
        return [
            'source' => $this->source,
            'form_name' => $this->formName,
            'locale' => $this->locale,
            'consent_version' => $this->consentVersion,
        ];
    }

    private static function validSlug(string $value): bool
    {
        return preg_match(
            '/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D',
            $value
        ) === 1;
    }
}
