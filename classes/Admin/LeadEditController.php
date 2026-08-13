<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Admin;

use Grav\Plugin\Api\Controllers\AbstractApiController;
use Grav\Plugin\Api\Exceptions\ConflictException;
use Grav\Plugin\Api\Exceptions\NotFoundException;
use Grav\Plugin\Api\Exceptions\ValidationException;
use Grav\Plugin\Api\Response\ApiResponse;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadMetadataRepository;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadReadRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LeadEditController extends AbstractApiController
{
    private const STATUSES = [
        'new',
        'contacted',
        'qualified',
        'closed',
    ];

    public function formData(
        ServerRequestInterface $request
    ): ResponseInterface {
        $this->requirePermission(
            $request,
            'api.goosialize_leads.write'
        );

        $leadId =
            $this->leadId(
                $request
            );

        $root =
            $this->userDataRoot();

        $lead =
            $this->findLead(
                $root,
                $leadId
            );

        $metadata =
            (
                new FilesystemLeadMetadataRepository(
                    $root
                )
            )->read(
                $leadId
            );

        $this->assertMetadataEditable(
            $metadata
        );

        return ApiResponse::create([
            'lead_name' =>
                $this->textValue(
                    $lead,
                    ['name']
                ),

            'lead_email' =>
                $this->textValue(
                    $lead,
                    ['email']
                ),

            'lead_phone' =>
                $this->textValue(
                    $lead,
                    ['phone']
                ),

            'lead_source' =>
                $this->sourceLabel(
                    $this->textValue(
                        $lead,
                        ['source'],
                        ''
                    )
                ),

            'lead_resource' =>
                $this->textValue(
                    $lead,
                    [
                        'form_resource',
                        'resource',
                        'form',
                        'resource_id',
                    ]
                ),

            'lead_created' =>
                $this->textValue(
                    $lead,
                    [
                        'created_time',
                        'created',
                        'created_at',
                    ]
                ),

            'status' =>
                $metadata['status'],

            'active' =>
                $metadata['state']
                === 'active'
                    ? 1
                    : 0,

            'revision' =>
                $metadata['revision'],
        ]);
    }

    public function save(
        ServerRequestInterface $request
    ): ResponseInterface {
        $this->requirePermission(
            $request,
            'api.goosialize_leads.write'
        );

        $leadId =
            $this->leadId(
                $request
            );

        $root =
            $this->userDataRoot();

        $lead =
            $this->findLead(
                $root,
                $leadId
            );

        $this->assertEditable(
            $root,
            $leadId
        );

        $body =
            $this->getRequestBody(
                $request
            );

        $status =
            $body['status']
            ?? null;

        $active =
            $body['active']
            ?? null;

        $revision =
            $body['revision']
            ?? null;

        if (
            !is_string($status)
            || !in_array(
                $status,
                self::STATUSES,
                true
            )
        ) {
            throw new ValidationException(
                'Invalid Lead status.'
            );
        }

        $activeValue =
            $this->normalizeToggle(
                $active
            );

        if (
            $activeValue === null
        ) {
            throw new ValidationException(
                'Invalid Lead state.'
            );
        }

        if (
            is_string($revision)
            && preg_match(
                '/\A(?:0|[1-9][0-9]*)\z/D',
                $revision
            ) === 1
        ) {
            $revision =
                (int) $revision;
        }

        if (
            !is_int($revision)
            || $revision < 0
        ) {
            throw new ValidationException(
                'Invalid Lead revision.'
            );
        }

        try {
            $saved =
                (
                    new FilesystemLeadMetadataRepository(
                        $root
                    )
                )->save(
                    $leadId,
                    $revision,
                    $status,
                    $activeValue
                        ? 'active'
                        : 'inactive'
                );
        } catch (\DomainException $error) {
            if (
                $error->getMessage()
                === 'lead_metadata_revision_conflict'
            ) {
                throw new ConflictException(
                    'This Lead changed elsewhere. Reload and try again.'
                );
            }

            throw $error;
        }

        return ApiResponse::create([
            'lead_name' =>
                $this->textValue(
                    $lead,
                    ['name']
                ),

            'lead_email' =>
                $this->textValue(
                    $lead,
                    ['email']
                ),

            'lead_phone' =>
                $this->textValue(
                    $lead,
                    ['phone']
                ),

            'lead_source' =>
                $this->sourceLabel(
                    $this->textValue(
                        $lead,
                        ['source'],
                        ''
                    )
                ),

            'lead_resource' =>
                $this->textValue(
                    $lead,
                    [
                        'form_resource',
                        'resource',
                        'form',
                        'resource_id',
                    ]
                ),

            'lead_created' =>
                $this->textValue(
                    $lead,
                    [
                        'created_time',
                        'created',
                        'created_at',
                    ]
                ),

            'status' =>
                $saved['status'],

            'active' =>
                $saved['state']
                === 'active'
                    ? 1
                    : 0,

            'revision' =>
                $saved['revision'],
        ]);
    }

    private function leadId(
        ServerRequestInterface $request
    ): string {
        $leadId =
            $this->getRouteParam(
                $request,
                'id'
            );

        if (
            !is_string($leadId)
            || preg_match(
                '/\A[0-9a-f]{32}\z/D',
                $leadId
            ) !== 1
        ) {
            throw new NotFoundException(
                'Lead not found.'
            );
        }

        return $leadId;
    }

    private function userDataRoot(): string
    {
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
            throw new \RuntimeException(
                'Lead storage unavailable.'
            );
        }

        return $root;
    }

    /**
     * @return array<string,mixed>
     */
    private function findLead(
        string $root,
        string $leadId
    ): array {
        $lead = (new FilesystemLeadReadRepository($root))
            ->findById($leadId);

        if ($lead !== null) {
            return $lead->toArray();
        }

        throw new NotFoundException(
            'Lead not found.'
        );
    }

    private function assertEditable(
        string $root,
        string $leadId
    ): void {
        $this->assertMetadataEditable(
            (new FilesystemLeadMetadataRepository($root))->read($leadId)
        );
    }

    /** @param array{state:string} $metadata */
    private function assertMetadataEditable(array $metadata): void
    {
        if ($metadata['state'] === 'deleted') {
            throw new ConflictException(
                'Deleted Leads must be restored before editing.'
            );
        }
    }

    /**
     * @param array<string,mixed> $lead
     * @param list<string> $keys
     */
    private function textValue(
        array $lead,
        array $keys,
        string $fallback = '—'
    ): string {
        foreach (
            $keys
            as $key
        ) {
            $value =
                $lead[$key]
                ?? null;

            if (
                is_string($value)
                && trim($value) !== ''
            ) {
                return trim(
                    $value
                );
            }

            if (
                is_int($value)
                || is_float($value)
            ) {
                return (string) $value;
            }
        }

        return $fallback;
    }

    private function sourceLabel(
        string $source
    ): string {
        return match ($source) {
            'contact' =>
                'Contact',

            'download' =>
                'Download',

            'public_api' =>
                'Public API',

            'quote' =>
                'Quote',

            'newsletter' =>
                'Newsletter',

            '' =>
                '—',

            default =>
                ucwords(
                    str_replace(
                        '_',
                        ' ',
                        $source
                    )
                ),
        };
    }

    private function normalizeToggle(
        mixed $value
    ): ?bool {
        if (
            $value === true
            || $value === 1
            || $value === '1'
            || $value === 'true'
        ) {
            return true;
        }

        if (
            $value === false
            || $value === 0
            || $value === '0'
            || $value === 'false'
        ) {
            return false;
        }

        return null;
    }
}
