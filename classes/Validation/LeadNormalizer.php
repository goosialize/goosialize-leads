<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Validation;

final class LeadNormalizer
{
    private const WHITESPACE_FIELDS = [
        'full_name', 'first_name', 'last_name', 'company', 'message',
        'campaign.utm_source', 'campaign.utm_medium', 'campaign.utm_campaign',
        'campaign.utm_term', 'campaign.utm_content',
    ];

    public function __construct()
    {
    }

    public function unicodeCapabilityAvailable(): bool
    {
        return extension_loaded('intl')
            && class_exists(\Normalizer::class)
            && defined(\Normalizer::class . '::FORM_C');
    }

    public function normalizeNfc(string $value): ?string
    {
        if (!$this->unicodeCapabilityAvailable()) {
            return null;
        }
        $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
        if (!is_string($normalized) || !\Normalizer::isNormalized($normalized, \Normalizer::FORM_C)) {
            return null;
        }

        return $normalized;
    }

    public function normalizeWhitespace(string $field, string $value): string
    {
        if (!in_array($field, self::WHITESPACE_FIELDS, true)) {
            throw new \InvalidArgumentException('Unsupported whitespace field.');
        }
        if ($field === 'message') {
            $value = str_replace(["\r\n", "\r"], "\n", $value);
        }
        $value = preg_replace('/\A[\p{Zs}\t]+|[\p{Zs}\t]+\z/u', '', $value) ?? $value;
        if (in_array($field, ['full_name', 'first_name', 'last_name', 'company'], true)) {
            $value = preg_replace('/[\p{Zs}\t]+/u', ' ', $value) ?? $value;
        }

        return $value;
    }

    public function normalizeEmail(string $value): string
    {
        $value = trim($value, " \t");
        if (substr_count($value, '@') !== 1) {
            return $value;
        }
        [$local, $domain] = explode('@', $value, 2);
        $domain = strtr($domain, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');

        return $local . '@' . $domain;
    }
}
