<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Admin;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadReadRepository;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadMetadataRepository;
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

    public function filterFormData(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('api_user');

        if (!is_object($user)) {
            return $this->failure(
                401,
                'authentication_required',
                'Authentication required.'
            );
        }

        if (!$this->authorized($user)) {
            return $this->failure(
                403,
                'forbidden',
                'Lead access is forbidden.'
            );
        }

        return $this->response(
            200,
            [
                'data' => [
                    'filters' => [
                        'search' => '',
                        'status' => '',
                        'source' => '',
                        'form_resource' => '',
                        'state' => '',
                        'date_from' => null,
                        'date_to' => null,
                    ],
                    'presentation' => [
                        'sort' => 'newest',
                        'view' => '10',
                    ],
                    'capabilities' => $this->capabilities($user),
                ],
            ]
        );
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
            $body = (new FilesystemLeadReadRepository($root))
                ->latest(LeadIndexQuery::newest())
                ->toResponse();

            $metadata =
                new FilesystemLeadMetadataRepository(
                    $root
                );

            foreach ($body['data'] as &$lead) {
                $state =
                    $metadata->read(
                        $lead['id']
                    );

                $lead['status'] =
                    $state['status'];

                $lead['state'] =
                    $state['state'];

                $lead['metadata_revision'] =
                    $state['revision'];

                $lead['metadata_updated_at'] =
                    $state['updated_at'];
            }

            unset($lead);

            $body['meta']['capabilities'] =
                $this->capabilities($user);

            $body['meta']['source_options'] =
                $this->sourceOptions($body['data']);

            $body['meta']['form_resource_options'] =
                $this->formResourceOptions($body['data']);

            return $this->response(
                200,
                $body
            );
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
        return $this->authorizedFor($user, 'read');
    }

    /**
     * @param list<array<string,mixed>> $leads
     * @return array<string,string>
     */
    private function sourceOptions(array $leads): array
    {
        $options = [];

        foreach ($leads as $lead) {
            $source = $lead['source'] ?? null;

            if (!is_string($source) || $source === '') {
                continue;
            }

            $options[$source] = ucwords(
                str_replace(['_', '-'], ' ', $source)
            );
        }

        uksort(
            $options,
            static function (
                string $leftKey,
                string $rightKey
            ) use ($options): int {
                $labelOrder = strnatcasecmp(
                    $options[$leftKey],
                    $options[$rightKey]
                );

                if ($labelOrder !== 0) {
                    return $labelOrder;
                }

                return strcmp($leftKey, $rightKey);
            }
        );

        return ['' => 'All sources'] + $options;
    }

    /**
     * @param list<array<string,mixed>> $leads
     * @return array<string,string>
     */
    private function formResourceOptions(array $leads): array
    {
        $options = [];

        foreach ($leads as $lead) {
            $value = $lead['form_or_resource'] ?? null;

            if (!is_string($value) || $value === '') {
                continue;
            }

            $options[$value] = ucwords(
                str_replace(['_', '-'], ' ', $value)
            );
        }

        uksort(
            $options,
            static function (
                string $leftKey,
                string $rightKey
            ) use ($options): int {
                $labelOrder = strnatcasecmp(
                    $options[$leftKey],
                    $options[$rightKey]
                );

                return $labelOrder !== 0
                    ? $labelOrder
                    : strcmp($leftKey, $rightKey);
            }
        );

        return ['' => 'All forms / resources'] + $options;
    }

    /** @return array{write:bool,delete:bool,restore:bool} */
    private function capabilities(object $user): array
    {
        $write = $this->authorizedFor($user, 'write');

        return [
            'write' => $write,
            'delete' => $this->authorizedFor($user, 'delete'),
            'restore' => $write,
        ];
    }

    private function authorizedFor(object $user, string $permission): bool
    {
        try {
            if ($this->userAuthorized($user, 'api.super')) return true;
            if (!$this->userAuthorized($user, 'api.access')) return false;

            $name = 'api.goosialize_leads.' . $permission;

            return $this->userAuthorized($user, $name);
        } catch (\Throwable) {
            return false;
        }
    }

    private function userAuthorized(object $user, string $permission): bool
    {
        if (
            method_exists($user, 'get')
            && $user->get('access.' . $permission) === true
        ) {
            return true;
        }

        return method_exists($user, 'authorize')
            && $user->authorize($permission) === true;
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
