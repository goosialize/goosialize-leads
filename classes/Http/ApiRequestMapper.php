<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

final class ApiRequestMapper
{
    public function __construct()
    {
    }

    /**
     * @param array<string,mixed> $object
     * @param array{locale:?string,consent_version:string} $config
     */
    public function map(array $object, string $idempotencyKey, array $config): ApiRequestResult
    {
        if ($idempotencyKey === '') {
            return ApiRequestResult::failure('MISSING_IDEMPOTENCY_KEY');
        }
        if (preg_match('/\A[A-Za-z0-9._~-]{16,128}\z/D', $idempotencyKey) !== 1) {
            return ApiRequestResult::failure('INVALID_IDEMPOTENCY_KEY');
        }
        $allowed = [
            'full_name', 'first_name', 'last_name', 'email', 'phone', 'company',
            'message', 'resource_id', 'source_path', 'campaign', 'consent',
        ];
        foreach (array_keys($object) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                return ApiRequestResult::failure('UNKNOWN_MEMBER');
            }
        }
        foreach (['full_name', 'first_name', 'last_name', 'email', 'phone', 'company', 'message', 'resource_id', 'source_path'] as $key) {
            if (array_key_exists($key, $object) && $object[$key] !== null && !is_string($object[$key])) {
                return ApiRequestResult::failure('REQUEST_SCHEMA_INVALID');
            }
        }
        $campaign = $object['campaign'] ?? null;
        if ($campaign !== null) {
            if (!is_array($campaign) || array_is_list($campaign)) {
                return ApiRequestResult::failure('REQUEST_SCHEMA_INVALID');
            }
            $campaignKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
            foreach ($campaign as $key => $value) {
                if (!is_string($key) || !in_array($key, $campaignKeys, true) || ($value !== null && !is_string($value))) {
                    return ApiRequestResult::failure('REQUEST_SCHEMA_INVALID');
                }
            }
        }
        if (($object['consent'] ?? null) !== ['granted' => true]) {
            return ApiRequestResult::failure('REQUEST_SCHEMA_INVALID');
        }
        if (!array_key_exists('locale', $config) || !array_key_exists('consent_version', $config)
            || ($config['locale'] !== null && !is_string($config['locale']))
            || !is_string($config['consent_version'])
        ) {
            return ApiRequestResult::failure('REQUEST_SCHEMA_INVALID');
        }
        $submitted = [];
        foreach ($allowed as $key) {
            $submitted[$key] = $object[$key] ?? null;
        }
        return ApiRequestResult::valid($submitted, [
            'source' => 'public_api',
            'form_name' => 'public_api',
            'locale' => $config['locale'],
            'consent_version' => $config['consent_version'],
        ], $idempotencyKey);
    }
}
