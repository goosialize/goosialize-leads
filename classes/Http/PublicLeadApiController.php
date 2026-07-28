<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Http;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;
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
            $parse = $request->getAttribute('goosialize_leads.api_parse_result');
            if (!$parse instanceof ApiParseResult || !$parse->isValid() || $parse->object() === null) {
                return $responses->failure('request_unavailable');
            }
            $public = $this->config->get('plugins.goosialize-leads.public_api');
            $idempotency = $this->config->get('plugins.goosialize-leads.idempotency');
            if (!is_array($public) || !is_array($idempotency)) return $responses->failure('configuration_unavailable');
            $headers = $request->getHeader('Idempotency-Key');
            $key = count($headers) === 1 ? $headers[0] : '';
            if ($headers === []) return $responses->failure('missing_idempotency_key');
            if (count($headers) !== 1 || str_contains($key, ',')) return $responses->failure('invalid_idempotency_key');
            $mapped = (new ApiRequestMapper())->map($parse->object(), $key, [
                'locale' => $public['locale'] ?? null,
                'consent_version' => $public['consent_version'] ?? null,
            ]);
            if (!$mapped->isValid()) return $responses->failure(strtolower((string) $mapped->code()));
            $versions = [];
            foreach (($idempotency['keys'] ?? []) as $version => $encoded) {
                if (!(is_int($version) || (is_string($version) && ctype_digit($version)))) {
                    return $responses->failure('configuration_unavailable');
                }
                $versions[(int) $version] = $encoded;
            }
            $active = $idempotency['active_key_version'] ?? null;
            if (is_string($active) && ctype_digit($active)) $active = (int) $active;
            $root = $this->grav['locator']->findResource('user-data://', true);
            if (!is_string($root) || $root === '') return $responses->failure('configuration_unavailable');
            $ring = new IdempotencyKeyRing($active, $versions);
            $repository = new FilesystemLeadRepository($root, static fn (int $n): string => random_bytes($n), $ring);
            $coordinator = new LeadPersistenceCoordinator(
                new LeadInputValidator(new LeadNormalizer()),
                $repository,
                $ring
            );
            $service = new LeadCaptureService($coordinator, $ring);
            $result = $service->captureApi(
                $mapped->submitted(),
                $mapped->trusted(),
                (string) $mapped->idempotencyKey(),
                static fn (int $n): string => random_bytes($n),
                static fn (): \DateTimeInterface => new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );
            return $responses->success($result);
        } catch (\Throwable) {
            return $responses->failure('internal_error');
        }
    }
}
