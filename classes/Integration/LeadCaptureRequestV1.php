<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Integration;

final class LeadCaptureRequestV1
{
    private const FIELDS = [
        'full_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'message',
        'resource_id',
        'source_path',
        'campaign',
        'consent',
    ];

    private const CAMPAIGN_FIELDS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    /** @var array<string,mixed> */
    private readonly array $values;

    /** @param array<string,mixed> $values */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        if (array_is_list($values)) {
            throw new \InvalidArgumentException('Lead capture request must be an object.');
        }

        foreach (array_keys($values) as $field) {
            if (!is_string($field) || !in_array($field, self::FIELDS, true)) {
                throw new \InvalidArgumentException('Unknown Lead capture request field.');
            }
        }

        foreach ([
            'full_name',
            'first_name',
            'last_name',
            'email',
            'phone',
            'company',
            'message',
            'resource_id',
            'source_path',
        ] as $field) {
            if (
                array_key_exists($field, $values)
                && $values[$field] !== null
                && !is_string($values[$field])
            ) {
                throw new \InvalidArgumentException('Invalid Lead capture request value.');
            }
        }

        $campaign = $values['campaign'] ?? null;
        if ($campaign !== null) {
            if (!is_array($campaign) || array_is_list($campaign)) {
                throw new \InvalidArgumentException('Invalid Lead capture campaign.');
            }

            foreach ($campaign as $field => $value) {
                if (
                    !is_string($field)
                    || !in_array($field, self::CAMPAIGN_FIELDS, true)
                    || ($value !== null && !is_string($value))
                ) {
                    throw new \InvalidArgumentException('Invalid Lead capture campaign value.');
                }
            }
        }

        if (($values['consent'] ?? null) !== ['granted' => true]) {
            throw new \InvalidArgumentException('Explicit Lead capture consent is required.');
        }

        $normalized = [];
        foreach (self::FIELDS as $field) {
            $normalized[$field] = $values[$field] ?? null;
        }

        return new self($normalized);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->values;
    }
}
