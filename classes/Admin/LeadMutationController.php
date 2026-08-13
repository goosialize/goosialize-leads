<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Admin;

use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadMetadataRepository;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadReadRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LeadMutationController
{
    public function __construct(
        private readonly Grav $grav
    ) {
    }

    public function apply(
        ServerRequestInterface $request
    ): ResponseInterface {
        $user =
            $request->getAttribute('api_user');

        if (!is_object($user)) {
            return $this->failure(
                401,
                'authentication_required',
                'Authentication required.'
            );
        }

        if (
            trim(
                $request->getHeaderLine(
                    'X-API-Token'
                )
            ) === ''
        ) {
            return $this->failure(
                403,
                'write_token_required',
                'Write authorization is required.'
            );
        }

        if (!$this->canWrite($user)) {
            return $this->failure(
                403,
                'forbidden',
                'Lead mutation is forbidden.'
            );
        }

        $contentType =
            strtolower(
                $request->getHeaderLine(
                    'Content-Type'
                )
            );

        if (
            !str_starts_with(
                $contentType,
                'application/json'
            )
        ) {
            return $this->failure(
                415,
                'content_type_invalid',
                'JSON content is required.'
            );
        }

        $raw = (string) $request->getBody();

        if (
            $raw === ''
            || strlen($raw) > 2048
        ) {
            return $this->failure(
                400,
                'request_invalid',
                'Request is invalid.'
            );
        }

        try {
            $body = json_decode(
                $raw,
                true,
                8,
                JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            return $this->failure(
                400,
                'request_invalid',
                'Request is invalid.'
            );
        }

        if (
            !is_array($body)
            || array_is_list($body)
            || array_keys($body) !== [
                'lead_id',
                'expected_revision',
                'status',
                'action',
            ]
        ) {
            return $this->failure(
                400,
                'request_invalid',
                'Request is invalid.'
            );
        }

        $leadId = $body['lead_id'];
        $revision = $body['expected_revision'];
        $status = $body['status'];
        $action = $body['action'];

        if (
            !is_string($leadId)
            || preg_match(
                '/\A[0-9a-f]{32}\z/D',
                $leadId
            ) !== 1
            || !is_int($revision)
            || $revision < 0
            || !is_string($status)
            || !in_array(
                $status,
                [
                    'new',
                    'contacted',
                    'qualified',
                    'closed',
                ],
                true
            )
            || !is_string($action)
            || !in_array(
                $action,
                [
                    'active',
                    'inactive',
                    'delete',
                ],
                true
            )
        ) {
            return $this->failure(
                400,
                'request_invalid',
                'Request is invalid.'
            );
        }

        if (
            $action === 'delete'
            && !$this->canDelete($user)
        ) {
            return $this->failure(
                403,
                'forbidden',
                'Lead deletion is forbidden.'
            );
        }

        try {
            $root =
                $this->grav['locator']
                    ->findResource(
                        'user-data://',
                        true
                    );

            if (
                !is_string($root)
                || $root === ''
            ) {
                return $this->failure(
                    503,
                    'lead_metadata_unavailable',
                    'Lead metadata is unavailable.'
                );
            }

            $lead = (new FilesystemLeadReadRepository($root))
                ->findById($leadId);

            if ($lead === null) {
                return $this->failure(
                    404,
                    'lead_not_found',
                    'Lead was not found.'
                );
            }

            $state = match ($action) {
                'active' => 'active',
                'inactive' => 'inactive',
                'delete' => 'deleted',
            };

            $saved =
                (
                    new FilesystemLeadMetadataRepository(
                        $root
                    )
                )
                ->save(
                    $leadId,
                    $revision,
                    $status,
                    $state
                );

            return $this->response(
                200,
                [
                    'data' => [
                        'id' => $leadId,
                        'revision' =>
                            $saved['revision'],
                        'status' =>
                            $saved['status'],
                        'state' =>
                            $saved['state'],
                        'updated_at' =>
                            $saved['updated_at'],
                    ],
                ]
            );
        } catch (\DomainException $error) {
            if (
                $error->getMessage()
                === 'lead_metadata_revision_conflict'
            ) {
                return $this->failure(
                    409,
                    'revision_conflict',
                    'Lead state has changed. Reload and try again.'
                );
            }

            if (
                $error->getMessage()
                === 'lead_metadata_deleted_restore_required'
            ) {
                return $this->failure(
                    409,
                    'lead_deleted_restore_required',
                    'Deleted Leads can only be restored.'
                );
            }

            return $this->failure(
                500,
                'internal_error',
                'Lead mutation failed.'
            );
        } catch (\Throwable) {
            return $this->failure(
                503,
                'lead_metadata_unavailable',
                'Lead metadata is unavailable.'
            );
        }
    }

    private function canWrite(
        object $user
    ): bool {
        return $this->authorized(
            $user,
            'write'
        );
    }

    private function canDelete(
        object $user
    ): bool {
        return $this->authorized(
            $user,
            'delete'
        );
    }

    private function authorized(
        object $user,
        string $permission
    ): bool {
        try {
            if (
                method_exists($user, 'get')
                && (bool) $user->get(
                    'access.api.super'
                )
            ) {
                return true;
            }

            if (
                method_exists($user, 'get')
                && !(bool) $user->get(
                    'access.api.access'
                )
            ) {
                return false;
            }

            $path =
                'access.api.goosialize_leads.'
                . $permission;

            if (
                method_exists($user, 'get')
                && (bool) $user->get($path)
            ) {
                return true;
            }

            return method_exists(
                $user,
                'authorize'
            )
                && (bool) $user->authorize(
                    'api.goosialize_leads.'
                    . $permission
                );
        } catch (\Throwable) {
            return false;
        }
    }

    private function failure(
        int $status,
        string $code,
        string $message
    ): ResponseInterface {
        return $this->response(
            $status,
            [
                'ok' => false,
                'code' => $code,
                'message' => $message,
            ]
        );
    }

    /**
     * @param array<string,mixed> $body
     */
    private function response(
        int $status,
        array $body
    ): ResponseInterface {
        return new Response(
            $status,
            [
                'Content-Type' =>
                    'application/json; charset=utf-8',
                'Cache-Control' =>
                    'no-store',
                'X-Content-Type-Options' =>
                    'nosniff',
            ],
            json_encode(
                $body,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
        );
    }
}
