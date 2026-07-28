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

final class LeadsCsvExportController
{
    public function __construct(
        private readonly Grav $grav,
        private readonly Config $config
    ) {
    }

    public function export(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('api_user');
        if (!is_object($user)) return $this->failure(401, 'authentication_required', 'Authentication required.');
        if (!$this->authorized($user)) return $this->failure(403, 'forbidden', 'Lead export is forbidden.');
        $settings = $this->config->get('plugins.goosialize-leads.admin2_csv_export');
        if (!is_array($settings) || ($settings['enabled'] ?? null) !== true
            || ($settings['max_response_bytes'] ?? null) !== 131072
        ) return $this->failure(503, 'csv_export_unavailable', 'Lead export is unavailable.');

        try {
            $root = $this->grav['locator']->findResource('user-data://', true);
            if (!is_string($root) || $root === '') {
                return $this->failure(503, 'csv_export_unavailable', 'Lead export is unavailable.');
            }
            $collection = (new FilesystemLeadReadRepository($root))->latest(LeadIndexQuery::newest());
            $body = (new LeadCsvExporter())->export($collection, 131072);
            return new Response(200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="goosialize-leads-latest-100.csv"',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ], $body);
        } catch (StorageException $error) {
            return match ($error->stableCode()) {
                'lead_index_capacity_exceeded' => $this->failure(503, 'lead_index_capacity_exceeded', 'Lead export capacity was exceeded.'),
                'lead_index_record_invalid' => $this->failure(503, 'export_record_invalid', 'Lead export data is invalid.'),
                'lead_index_storage_invalid' => $this->failure(503, 'lead_index_storage_invalid', 'Lead export storage is unavailable.'),
                default => $this->failure(500, 'internal_error', 'Lead export could not be created.'),
            };
        } catch (\LengthException) {
            return $this->failure(503, 'export_response_too_large', 'Lead export is too large.');
        } catch (\RuntimeException) {
            return $this->failure(503, 'export_record_invalid', 'Lead export data is invalid.');
        } catch (\Throwable) {
            return $this->failure(500, 'internal_error', 'Lead export could not be created.');
        }
    }

    private function authorized(object $user): bool
    {
        try {
            if (method_exists($user, 'get') && (bool) $user->get('access.api.super')) return true;
            if (method_exists($user, 'get') && !(bool) $user->get('access.api.access')) return false;
            $read = method_exists($user, 'get') && (bool) $user->get('access.api.goosialize_leads.read');
            $export = method_exists($user, 'get') && (bool) $user->get('access.api.goosialize_leads.export');
            if ($read && $export) return true;
            return method_exists($user, 'authorize')
                && (bool) $user->authorize('api.goosialize_leads.read')
                && (bool) $user->authorize('api.goosialize_leads.export');
        } catch (\Throwable) {
            return false;
        }
    }

    private function failure(int $status, string $code, string $message): ResponseInterface
    {
        return new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], json_encode(['ok' => false, 'code' => $code, 'message' => $message],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
