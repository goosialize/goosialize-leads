<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Admin;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadReadRepository;
use Grav\Plugin\GoosializeLeads\Storage\StorageException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LeadsIndexController
{
    public function __construct(
        private readonly Grav $grav,
        private readonly Config $config
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('api_user');
        if (!is_object($user)) return $this->failure(401, 'authentication_required', 'Authentication required.');
        if (!$this->authorized($user)) return $this->failure(403, 'forbidden', 'Lead access is forbidden.');
        $settings = $this->config->get('plugins.goosialize-leads.admin2_index');
        if (!is_array($settings) || ($settings['enabled'] ?? null) !== true || ($settings['timezone'] ?? null) !== 'UTC') {
            return $this->failure(503, 'admin2_index_unavailable', 'Lead index is unavailable.');
        }
        try {
            $root = $this->grav['locator']->findResource('user-data://', true);
            if (!is_string($root) || $root === '') return $this->failure(503, 'admin2_index_unavailable', 'Lead index is unavailable.');
            return $this->response(200, (new FilesystemLeadReadRepository($root))->latest(LeadIndexQuery::newest())->toResponse());
        } catch (StorageException $error) {
            return match ($error->stableCode()) {
                'lead_index_record_invalid' => $this->failure(503, 'lead_index_record_invalid', 'Lead index data is invalid.'),
                'lead_index_capacity_exceeded' => $this->failure(503, 'lead_index_capacity_exceeded', 'Lead index capacity was exceeded.'),
                'lead_index_storage_invalid' => $this->failure(503, 'lead_index_storage_invalid', 'Lead index storage is unavailable.'),
                default => $this->failure(500, 'internal_error', 'Lead index could not be loaded.'),
            };
        } catch (\Throwable) {
            return $this->failure(500, 'internal_error', 'Lead index could not be loaded.');
        }
    }

    private function authorized(object $user): bool
    {
        try {
            if (method_exists($user, 'get') && (bool) $user->get('access.api.super')) return true;
            if (method_exists($user, 'get') && !(bool) $user->get('access.api.access')) return false;
            if (method_exists($user, 'get') && (bool) $user->get('access.api.goosialize_leads.read')) return true;
            return method_exists($user, 'authorize') && (bool) $user->authorize('api.goosialize_leads.read');
        } catch (\Throwable) {
            return false;
        }
    }

    private function failure(int $status, string $code, string $message): ResponseInterface
    {
        return $this->response($status, ['ok' => false, 'code' => $code, 'message' => $message]);
    }

    /** @param array<string,mixed> $body */
    private function response(int $status, array $body): ResponseInterface
    {
        return new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
