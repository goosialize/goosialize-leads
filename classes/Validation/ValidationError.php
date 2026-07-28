<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Validation;

final class ValidationError
{
    private const CODES = [
        'object_required', 'too_many_fields', 'unknown_field', 'trusted_field_override',
        'invalid_type', 'null_forbidden', 'required', 'empty_required', 'invalid_utf8',
        'unicode_normalization_unavailable', 'unicode_normalization_failed',
        'forbidden_control', 'forbidden_noncharacter', 'html_not_allowed',
        'multiline_not_allowed', 'too_long', 'invalid_full_name', 'invalid_first_name',
        'invalid_last_name', 'invalid_email', 'invalid_phone', 'invalid_company',
        'invalid_locale', 'invalid_resource_id', 'invalid_source_path',
        'invalid_campaign_source', 'invalid_campaign_medium', 'invalid_campaign_name',
        'invalid_campaign_term', 'invalid_campaign_content', 'consent_missing',
        'invalid_consent_granted', 'consent_version_missing', 'invalid_consent_version',
        'consent_timestamp_missing', 'invalid_consent_timestamp', 'name_required',
        'incomplete_name_pair', 'conflicting_name_forms', 'contact_required',
        'inquiry_required', 'invalid_generated_id', 'invalid_schema_version',
        'invalid_timestamp', 'invalid_updated_timestamp', 'capture_timestamps_mismatch',
        'invalid_initial_status', 'invalid_initial_revision', 'invalid_source',
        'invalid_form_name', 'invalid_idempotency', 'invalid_idempotency_key_version',
        'invalid_idempotency_key_hash', 'invalid_payload_fingerprint',
        'canonical_serialization_failed', 'canonical_record_too_large',
    ];

    public function __construct(
        private readonly string $code,
        private readonly ?string $field
    ) {
        if (!in_array($code, self::CODES, true)) {
            throw new \InvalidArgumentException('Unknown validation error code.');
        }
        if ($field !== null && preg_match('/\A[A-Za-z0-9_.-]+\z/D', $field) !== 1) {
            throw new \InvalidArgumentException('Invalid validation error field.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function field(): ?string
    {
        return $this->field;
    }

    /** @return array{code:string,field:?string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'field' => $this->field];
    }
}
