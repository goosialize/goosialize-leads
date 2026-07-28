<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Validation;

use Grav\Plugin\GoosializeLeads\Application\CaptureCommand;

final class LeadInputValidator
{
    private const SUBMITTED_KEYS = [
        'consent', 'full_name', 'first_name', 'last_name', 'email', 'phone',
        'company', 'message', 'resource_id', 'source_path', 'campaign',
    ];
    private const TRUSTED_KEYS = ['source', 'form_name', 'locale', 'consent_version'];
    private const TRUSTED_OVERRIDES = [
        'schema_version', 'id', 'created_at', 'updated_at', 'status', 'revision',
        'source', 'form_name', 'locale', 'idempotency', 'consent_version',
    ];
    private const CAMPAIGN_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function __construct(private readonly LeadNormalizer $normalizer)
    {
    }

    public function validate(mixed $submitted, array $trusted): ValidationResult
    {
        if (!is_array($submitted) || array_is_list($submitted)) {
            return self::failure('object_required', null);
        }
        if (count($submitted) > 11) {
            return self::failure('too_many_fields', null);
        }
        if (!$this->normalizer->unicodeCapabilityAvailable()) {
            return self::failure('unicode_normalization_unavailable', null);
        }

        $trustedError = $this->validateTrustedShape($trusted);
        if ($trustedError instanceof ValidationResult) {
            return $trustedError;
        }

        $knownErrors = [];
        $unknownErrors = [];
        foreach (array_keys($submitted) as $key) {
            if (!is_string($key) || !in_array($key, self::SUBMITTED_KEYS, true)) {
                $field = $this->normalizeUnknownField((string) $key);
                $code = in_array($key, self::TRUSTED_OVERRIDES, true)
                    ? 'trusted_field_override' : 'unknown_field';
                $unknownErrors[$field] = new ValidationError($code, $field);
            }
        }

        $consent = null;
        if (!array_key_exists('consent', $submitted)) {
            $knownErrors['consent'] = new ValidationError('consent_missing', 'consent');
        } elseif ($submitted['consent'] === null) {
            $knownErrors['consent'] = new ValidationError('null_forbidden', 'consent');
        } elseif (!is_array($submitted['consent'])) {
            $knownErrors['consent'] = new ValidationError('invalid_type', 'consent');
        } elseif (array_keys($submitted['consent']) !== ['granted']) {
            if (!array_key_exists('granted', $submitted['consent'])) {
                $knownErrors['consent.granted'] = new ValidationError('required', 'consent.granted');
            } else {
                $knownErrors['consent.granted'] = new ValidationError('unknown_field', 'consent.granted');
            }
        } elseif ($submitted['consent']['granted'] !== true) {
            $knownErrors['consent.granted'] = new ValidationError('invalid_consent_granted', 'consent.granted');
        } else {
            $consent = ['granted' => true];
        }

        $values = [];
        foreach (['full_name', 'first_name', 'last_name', 'email', 'phone', 'company', 'message', 'resource_id', 'source_path'] as $field) {
            [$value, $error] = $this->validateOptionalField($field, $submitted[$field] ?? null, array_key_exists($field, $submitted));
            $values[$field] = $value;
            if ($error !== null) {
                $knownErrors[$field] = $error;
            }
        }

        $campaign = null;
        if (array_key_exists('campaign', $submitted) && $submitted['campaign'] !== null) {
            if (!is_array($submitted['campaign'])) {
                $knownErrors['campaign'] = new ValidationError('invalid_type', 'campaign');
            } else {
                $campaignValues = [];
                foreach (array_keys($submitted['campaign']) as $key) {
                    if (!is_string($key) || !in_array($key, self::CAMPAIGN_KEYS, true)) {
                        $field = 'campaign.' . $this->normalizeUnknownField((string) $key);
                        $unknownErrors[$field] = new ValidationError('unknown_field', $field);
                    }
                }
                foreach (self::CAMPAIGN_KEYS as $key) {
                    $field = 'campaign.' . $key;
                    [$value, $error] = $this->validateOptionalField(
                        $field,
                        $submitted['campaign'][$key] ?? null,
                        array_key_exists($key, $submitted['campaign'])
                    );
                    $campaignValues[$key] = $value;
                    if ($error !== null) {
                        $knownErrors[$field] = $error;
                    }
                }
                if (array_filter($campaignValues, static fn (?string $value): bool => $value !== null) !== []) {
                    $campaign = $campaignValues;
                }
            }
        }

        $trustedErrors = $this->validateTrustedValues($trusted);
        foreach ($trustedErrors as $field => $error) {
            $knownErrors[$field] = $error;
        }

        $orderedFields = [
            'consent', 'consent.granted', 'full_name', 'first_name', 'last_name',
            'email', 'phone', 'company', 'message', 'resource_id', 'source_path',
            'campaign', 'campaign.utm_source', 'campaign.utm_medium',
            'campaign.utm_campaign', 'campaign.utm_term', 'campaign.utm_content',
            'source', 'form_name', 'locale', 'consent.version',
        ];
        $errors = [];
        foreach ($orderedFields as $field) {
            if (isset($knownErrors[$field])) {
                $errors[] = $knownErrors[$field];
            }
        }
        ksort($unknownErrors, SORT_STRING);
        array_push($errors, ...array_values($unknownErrors));

        $full = $values['full_name'];
        $first = $values['first_name'];
        $last = $values['last_name'];
        $nameHasError = isset($knownErrors['full_name'])
            || isset($knownErrors['first_name'])
            || isset($knownErrors['last_name']);
        if (!$nameHasError && $full === null && $first === null && $last === null) {
            $errors[] = new ValidationError('name_required', 'full_name');
        } elseif (!$nameHasError && (($first === null) xor ($last === null))) {
            $errors[] = new ValidationError('incomplete_name_pair', 'full_name');
        } elseif (!$nameHasError && $full !== null && $first !== null && $last !== null) {
            $errors[] = new ValidationError('conflicting_name_forms', 'full_name');
        }
        if (!isset($knownErrors['email'])
            && !isset($knownErrors['phone'])
            && $values['email'] === null
            && $values['phone'] === null
        ) {
            $errors[] = new ValidationError('contact_required', 'email');
        }
        if (!isset($knownErrors['message'])
            && !isset($knownErrors['resource_id'])
            && $values['message'] === null
            && $values['resource_id'] === null
        ) {
            $errors[] = new ValidationError('inquiry_required', 'message');
        }
        if ($errors !== []) {
            return ValidationResult::failure($errors);
        }
        if ($full === null) {
            $full = $first . ' ' . $last;
        }

        return ValidationResult::success(CaptureCommand::fromValidated([
            'consent' => $consent,
            'full_name' => $full,
            'email' => $values['email'],
            'phone' => $values['phone'],
            'company' => $values['company'],
            'message' => $values['message'],
            'resource_id' => $values['resource_id'],
            'source_path' => $values['source_path'],
            'campaign' => $campaign,
        ], $trusted));
    }

    private function validateTrustedShape(array $trusted): ?ValidationResult
    {
        if (!array_key_exists('consent_version', $trusted)) {
            $without = array_keys($trusted);
            if ($without === ['source', 'form_name', 'locale']) {
                return self::failure('consent_version_missing', 'consent.version');
            }
            throw new \InvalidArgumentException('Invalid trusted input shape.');
        }
        if (array_keys($trusted) !== self::TRUSTED_KEYS) {
            throw new \InvalidArgumentException('Invalid trusted input shape.');
        }
        if (!is_string($trusted['source']) || !is_string($trusted['form_name'])
            || ($trusted['locale'] !== null && !is_string($trusted['locale']))
        ) {
            throw new \InvalidArgumentException('Invalid trusted input value.');
        }
        if (!is_string($trusted['consent_version'])) {
            return self::failure('invalid_consent_version', 'consent.version');
        }

        return null;
    }

    /** @return array<string,ValidationError> */
    private function validateTrustedValues(array $trusted): array
    {
        $errors = [];
        if (preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $trusted['source']) !== 1) {
            $errors['source'] = new ValidationError('invalid_source', 'source');
        }
        if (preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $trusted['form_name']) !== 1) {
            $errors['form_name'] = new ValidationError('invalid_form_name', 'form_name');
        }
        if ($trusted['locale'] !== null
            && preg_match('/\A[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*\z/D', $trusted['locale']) !== 1
        ) {
            $errors['locale'] = new ValidationError('invalid_locale', 'locale');
        }
        $versionError = $this->genericTextError('consent.version', $trusted['consent_version'], 64, 128, false);
        if ($versionError !== null) {
            $errors['consent.version'] = $versionError;
        } elseif (preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $trusted['consent_version']) !== 1) {
            $errors['consent.version'] = new ValidationError('invalid_consent_version', 'consent.version');
        }

        return $errors;
    }

    /** @return array{0:?string,1:?ValidationError} */
    private function validateOptionalField(string $field, mixed $raw, bool $present): array
    {
        if (!$present || $raw === null) {
            return [null, null];
        }
        if (!is_string($raw)) {
            return [null, new ValidationError('invalid_type', $field)];
        }
        if (preg_match('//u', $raw) !== 1) {
            return [null, new ValidationError('invalid_utf8', $field)];
        }
        $normalized = $field === 'email'
            ? $this->normalizer->normalizeEmail($raw)
            : ($field === 'phone' || $field === 'resource_id' || $field === 'source_path'
                ? trim($raw, " \t")
                : $this->normalizer->normalizeWhitespace($field, $raw));
        if ($field === 'phone') {
            $normalized = str_replace([' ', '-', '(', ')'], '', $normalized);
        }
        $normalized = $this->normalizer->normalizeNfc($normalized);
        if ($normalized === null) {
            return [null, new ValidationError('unicode_normalization_failed', $field)];
        }
        if ($normalized === '') {
            return [null, null];
        }
        [$maxPoints, $maxBytes] = match ($field) {
            'full_name', 'first_name', 'last_name', 'company' => [200, 400],
            'message' => [4000, 8000],
            'email' => [254, 254],
            'phone' => [16, 16],
            'resource_id' => [128, 128],
            'source_path' => [512, 512],
            default => [100, 200],
        };
        $error = $this->genericTextError($field, $normalized, $maxPoints, $maxBytes, $field === 'message');
        if ($error !== null) {
            return [null, $error];
        }

        $code = null;
        if (in_array($field, ['full_name', 'first_name', 'last_name'], true)
            && preg_match("/\A\\p{L}[\\p{L}\\p{M}]*(?:[ '\\x{2019}-]\\p{L}[\\p{L}\\p{M}]*)*\z/uD", $normalized) !== 1
        ) {
            $code = 'invalid_' . $field;
        } elseif ($field === 'company'
            && preg_match("/\A[\\p{L}\\p{Nd}][\\p{L}\\p{M}\\p{Nd}]*(?:[ '&.\\x{2019}-][\\p{L}\\p{Nd}][\\p{L}\\p{M}\\p{Nd}]*)*\z/uD", $normalized) !== 1
        ) {
            $code = 'invalid_company';
        } elseif ($field === 'email' && !$this->validEmail($normalized)) {
            $code = 'invalid_email';
        } elseif ($field === 'phone') {
            if (preg_match('/\A\+[0-9]{8,15}\z/D', $normalized) !== 1) {
                $code = 'invalid_phone';
            }
        } elseif ($field === 'resource_id' && preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,126}[a-z0-9])?\z/D', $normalized) !== 1) {
            $code = 'invalid_resource_id';
        } elseif ($field === 'source_path' && !$this->validSourcePath($normalized)) {
            $code = 'invalid_source_path';
        } elseif (str_starts_with($field, 'campaign.')
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._~-]*\z/D', $normalized) !== 1
        ) {
            $code = match ($field) {
                'campaign.utm_source' => 'invalid_campaign_source',
                'campaign.utm_medium' => 'invalid_campaign_medium',
                'campaign.utm_campaign' => 'invalid_campaign_name',
                'campaign.utm_term' => 'invalid_campaign_term',
                default => 'invalid_campaign_content',
            };
        }

        return $code === null ? [$normalized, null] : [null, new ValidationError($code, $field)];
    }

    private function genericTextError(
        string $field,
        string $value,
        int $maxPoints,
        int $maxBytes,
        bool $allowLf
    ): ?ValidationError {
        if (preg_match('//u', $value) !== 1) {
            return new ValidationError('invalid_utf8', $field);
        }
        if ($value === '') {
            return new ValidationError('empty_required', $field);
        }
        if (!$allowLf && preg_match('/[\r\n]/', $value) === 1) {
            return new ValidationError('multiline_not_allowed', $field);
        }
        $controlPattern = $allowLf
            ? '/[\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}-\x{009F}]/u'
            : '/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u';
        if (preg_match($controlPattern, $value) === 1) {
            return new ValidationError('forbidden_control', $field);
        }
        if (preg_match('/[\x{FDD0}-\x{FDEF}\x{FFFE}\x{FFFF}\x{1FFFE}\x{1FFFF}\x{2FFFE}\x{2FFFF}\x{3FFFE}\x{3FFFF}\x{4FFFE}\x{4FFFF}\x{5FFFE}\x{5FFFF}\x{6FFFE}\x{6FFFF}\x{7FFFE}\x{7FFFF}\x{8FFFE}\x{8FFFF}\x{9FFFE}\x{9FFFF}\x{AFFFE}\x{AFFFF}\x{BFFFE}\x{BFFFF}\x{CFFFE}\x{CFFFF}\x{DFFFE}\x{DFFFF}\x{EFFFE}\x{EFFFF}\x{FFFFE}\x{FFFFF}\x{10FFFE}\x{10FFFF}]/u', $value) === 1) {
            return new ValidationError('forbidden_noncharacter', $field);
        }
        if (preg_match('/<[^>]*>/', $value) === 1) {
            return new ValidationError('html_not_allowed', $field);
        }
        if (strlen($value) > $maxBytes || mb_strlen($value, 'UTF-8') > $maxPoints) {
            return new ValidationError('too_long', $field);
        }

        return null;
    }

    private function validEmail(string $email): bool
    {
        if (preg_match('/\A[\x00-\x7F]+\z/D', $email) !== 1 || substr_count($email, '@') !== 1) {
            return false;
        }
        [$local, $domain] = explode('@', $email, 2);
        if (strlen($local) < 1 || strlen($local) > 64 || strlen($domain) < 1 || strlen($domain) > 253) {
            return false;
        }
        $atom = "[A-Za-z0-9!#$%&'*+\\/=?^_`{|}~-]+";
        if (preg_match('/\A' . $atom . '(?:\.' . $atom . ')*\z/D', $local) !== 1) {
            return false;
        }
        $labels = explode('.', $domain);
        if (count($labels) < 2) {
            return false;
        }
        foreach ($labels as $label) {
            if (strlen($label) < 1 || strlen($label) > 63
                || preg_match('/\A[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\z/D', $label) !== 1
            ) {
                return false;
            }
        }

        return true;
    }

    private function validSourcePath(string $path): bool
    {
        return str_starts_with($path, '/')
            && !str_contains($path, '//')
            && !str_contains($path, '?')
            && !str_contains($path, '#')
            && !str_contains($path, '://')
            && preg_match('~(?:^|/)\.{1,2}(?:/|$)|%2[fF]|%5[cC]~', $path) !== 1;
    }

    private function normalizeUnknownField(string $field): string
    {
        $field = strtolower($field);
        $field = preg_replace('/[^a-z0-9_.-]+/', '_', $field) ?? '';
        $field = trim($field, '._-');

        return $field === '' ? 'unknown' : $field;
    }

    private static function failure(string $code, ?string $field): ValidationResult
    {
        return ValidationResult::failure([new ValidationError($code, $field)]);
    }
}
