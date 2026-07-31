<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureRuntimeFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicLeadApiController
{
    public function __construct(
        private readonly Grav $grav,
        private readonly Config $config
    ) {
    }

    public function capture(ServerRequestInterface $request): ResponseInterface
    {
        $responses = new ApiResponseMapper();

        try {
            $parse = $request->getAttribute(
                'goosialize_leads.api_parse_result'
            );

            if (
                !$parse instanceof ApiParseResult
                || !$parse->isValid()
                || $parse->object() === null
            ) {
                return $responses->failure('request_unavailable');
            }

            $public = $this->config->get(
                'plugins.goosialize-leads.public_api'
            );

            $idempotency = $this->config->get(
                'plugins.goosialize-leads.idempotency'
            );

            $outbox = $this->config->get(
                'plugins.goosialize-leads.notifications.outbox'
            );

            if (
                !is_array($public)
                || !is_array($idempotency)
                || ($outbox !== null && !is_array($outbox))
            ) {
                return $responses->failure(
                    'configuration_unavailable'
                );
            }

            $headers = $request->getHeader('Idempotency-Key');
            $key = count($headers) === 1 ? $headers[0] : '';

            if ($headers === []) {
                return $responses->failure(
                    'missing_idempotency_key'
                );
            }

            if (count($headers) !== 1 || str_contains($key, ',')) {
                return $responses->failure(
                    'invalid_idempotency_key'
                );
            }

            $mapped = (new ApiRequestMapper())->map(
                $parse->object(),
                $key,
                [
                    'locale' => $public['locale'] ?? null,
                    'consent_version' =>
                        $public['consent_version'] ?? null,
                ]
            );

            if (!$mapped->isValid()) {
                return $responses->failure(
                    strtolower((string) $mapped->code())
                );
            }

            $root = $this->grav['locator']->findResource(
                'user-data://',
                true
            );

            if (!is_string($root) || $root === '') {
                return $responses->failure(
                    'configuration_unavailable'
                );
            }

            $logger = isset($this->grav['log'])
                ? fn (string $code): mixed =>
                    $this->grav['log']->warning($code)
                : null;

            $service = LeadCaptureRuntimeFactory::create(
                $root,
                $idempotency,
                $outbox,
                $logger
            );

            $result = $service->captureApi(
                $mapped->submitted(),
                $mapped->trusted(),
                (string) $mapped->idempotencyKey(),
                static fn (int $length): string =>
                    random_bytes($length),
                static fn (): \DateTimeInterface =>
                    new \DateTimeImmutable(
                        'now',
                        new \DateTimeZone('UTC')
                    )
            );

            return $responses->success($result);
        } catch (\InvalidArgumentException) {
            return $responses->failure(
                'configuration_unavailable'
            );
        } catch (\Throwable) {
            return $responses->failure('internal_error');
        }
    }
}
