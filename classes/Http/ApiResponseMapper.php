<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

use Grav\Framework\Psr7\Response;
use Grav\Plugin\GoosializeLeads\Application\CaptureResult;
use Psr\Http\Message\ResponseInterface;

final class ApiResponseMapper
{
    public function __construct()
    {
    }

    public function success(CaptureResult $result): ResponseInterface
    {
        if (!$result->isSuccess() || $result->record() === null) {
            return $this->failure($result->code() ?? 'internal_error', $result->errorsAsArray());
        }
        $replayed = $result->replayed();
        return $this->response($replayed ? 200 : 201, [
            'ok' => true,
            'code' => $replayed ? 'replayed' : 'created',
            'message' => $replayed ? 'Lead already captured.' : 'Lead captured.',
            'lead_id' => $result->record()['id'],
        ]);
    }

    /** @param list<array{field:?string,code:string}> $errors */
    public function failure(string $code, array $errors = [], ?int $retryAfter = null): ResponseInterface
    {
        $map = [
            'unsupported_method' => [405, 'Method not allowed.'],
            'not_acceptable' => [406, 'JSON response required.'],
            'unsupported_media_type' => [415, 'JSON request required.'],
            'empty_body' => [400, 'Invalid request.'],
            'invalid_utf8' => [400, 'Invalid request.'],
            'invalid_json' => [400, 'Invalid request.'],
            'too_deep' => [400, 'Invalid request.'],
            'duplicate_json_key' => [400, 'Invalid request.'],
            'object_required' => [400, 'Invalid request.'],
            'unknown_member' => [400, 'Invalid request.'],
            'request_schema_invalid' => [400, 'Invalid request.'],
            'missing_idempotency_key' => [400, 'Invalid request.'],
            'invalid_idempotency_key' => [400, 'Invalid request.'],
            'payload_too_large' => [413, 'Request body too large.'],
            'origin_missing' => [403, 'Origin not allowed.'],
            'origin_invalid' => [403, 'Origin not allowed.'],
            'origin_forbidden' => [403, 'Origin not allowed.'],
            'validation_failed' => [422, 'Please correct the request and try again.'],
            'idempotency_conflict' => [409, 'Request conflicts with an earlier submission.'],
            'rate_limited' => [429, 'Too many requests.'],
            'request_unavailable' => [503, 'Service unavailable.'],
            'rate_limit_unavailable' => [503, 'Service unavailable.'],
            'storage_unavailable' => [503, 'Service unavailable.'],
            'configuration_unavailable' => [503, 'Service unavailable.'],
            'api_dependency_unavailable' => [503, 'Service unavailable.'],
            'internal_error' => [500, 'Unexpected error.'],
        ];
        [$status, $message] = $map[$code] ?? $map['internal_error'];
        $body = ['ok' => false, 'code' => array_key_exists($code, $map) ? $code : 'internal_error', 'message' => $message];
        if ($code === 'validation_failed') {
            $body['errors'] = array_map(static fn (array $error): array => [
                'field' => $error['field'],
                'code' => $error['code'],
            ], $errors);
        }
        if ($code === 'rate_limited') $body['retry_after'] = max(1, (int) $retryAfter);
        $headers = [];
        if ($status === 405) $headers['Allow'] = 'POST';
        if ($status === 429) $headers['Retry-After'] = (string) $body['retry_after'];
        return $this->response($status, $body, $headers);
    }

    /** @param array<string,mixed> $body @param array<string,string> $headers */
    private function response(int $status, array $body, array $headers = []): ResponseInterface
    {
        return new Response($status, $headers + [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Vary' => 'Origin',
        ], json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
