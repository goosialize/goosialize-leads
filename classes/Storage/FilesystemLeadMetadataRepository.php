<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Storage;

final class FilesystemLeadMetadataRepository
{
    private const STATUSES = [
        'new',
        'contacted',
        'qualified',
        'closed',
    ];

    private const STATES = [
        'active',
        'inactive',
        'deleted',
    ];

    private const MAX_BYTES = 2048;

    private readonly string $root;

    public function __construct(string $userDataRoot)
    {
        $base = @realpath($userDataRoot);

        if (
            $base === false
            || !@is_dir($base)
            || @is_link($userDataRoot)
        ) {
            throw new \RuntimeException(
                'lead_metadata_storage_invalid'
            );
        }

        $this->root =
            $base . '/goosialize-leads/v1/metadata';
    }

    /**
     * @return array{
     *   revision:int,
     *   status:string,
     *   state:string,
     *   updated_at:?string
     * }
     */
    public function read(string $leadId): array
    {
        $this->assertLeadId($leadId);

        $path = $this->path($leadId);

        if (
            !file_exists($path)
            && !is_link($path)
        ) {
            return $this->defaults();
        }

        return $this->readStored(
            $path,
            $leadId
        );
    }

    /**
     * @return array{
     *   revision:int,
     *   status:string,
     *   state:string,
     *   updated_at:?string
     * }
     */
    public function save(
        string $leadId,
        int $expectedRevision,
        string $status,
        string $state
    ): array {
        $this->assertLeadId($leadId);

        if (
            $expectedRevision < 0
            || !in_array(
                $status,
                self::STATUSES,
                true
            )
            || !in_array(
                $state,
                self::STATES,
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'lead_metadata_input_invalid'
            );
        }

        $this->ensureRoot();

        $lockPath =
            $this->root . '/.metadata.lock';

        $lock = @fopen(
            $lockPath,
            'c+b'
        );

        if (!is_resource($lock)) {
            throw new \RuntimeException(
                'lead_metadata_lock_failed'
            );
        }

        try {
            if (
                !@chmod($lockPath, 0600)
                || !@flock($lock, LOCK_EX)
            ) {
                throw new \RuntimeException(
                    'lead_metadata_lock_failed'
                );
            }

            $current =
                $this->read($leadId);

            if (
                $current['revision']
                !== $expectedRevision
            ) {
                throw new \DomainException(
                    'lead_metadata_revision_conflict'
                );
            }

            if (
                $current['state'] === 'deleted'
                && (
                    $state !== 'active'
                    || $status !== $current['status']
                )
            ) {
                throw new \DomainException(
                    'lead_metadata_deleted_restore_required'
                );
            }

            if (
                $current['status'] === $status
                && $current['state'] === $state
            ) {
                return $current;
            }

            $nextRevision =
                $current['revision'] + 1;

            $updatedAt =
                (new \DateTimeImmutable(
                    'now',
                    new \DateTimeZone('UTC')
                ))->format(
                    'Y-m-d\TH:i:s.u\Z'
                );

            $record = [
                'schema_version' => 1,
                'lead_id' => $leadId,
                'revision' => $nextRevision,
                'status' => $status,
                'state' => $state,
                'updated_at' => $updatedAt,
            ];

            $bytes = json_encode(
                $record,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ) . "\n";

            if (
                strlen($bytes)
                > self::MAX_BYTES
            ) {
                throw new \RuntimeException(
                    'lead_metadata_storage_invalid'
                );
            }

            $shard =
                $this->ensureShard($leadId);

            $path =
                $shard
                . '/'
                . $leadId
                . '.json';

            $temporary =
                $shard
                . '/.'
                . $leadId
                . '.'
                . bin2hex(random_bytes(8))
                . '.tmp';

            $handle = @fopen(
                $temporary,
                'xb'
            );

            if (!is_resource($handle)) {
                throw new \RuntimeException(
                    'lead_metadata_write_failed'
                );
            }

            try {
                if (!@chmod($temporary, 0600)) {
                    throw new \RuntimeException(
                        'lead_metadata_write_failed'
                    );
                }

                $written = 0;
                $length = strlen($bytes);

                while ($written < $length) {
                    $count = @fwrite(
                        $handle,
                        substr(
                            $bytes,
                            $written
                        )
                    );

                    if (
                        !is_int($count)
                        || $count <= 0
                    ) {
                        throw new \RuntimeException(
                            'lead_metadata_write_failed'
                        );
                    }

                    $written += $count;
                }

                if (
                    !@fflush($handle)
                    || (
                        function_exists('fsync')
                        && !@fsync($handle)
                    )
                ) {
                    throw new \RuntimeException(
                        'lead_metadata_write_failed'
                    );
                }
            } finally {
                @fclose($handle);
            }

            if (!@rename($temporary, $path)) {
                @unlink($temporary);

                throw new \RuntimeException(
                    'lead_metadata_write_failed'
                );
            }

            if (
                !@chmod($path, 0600)
            ) {
                throw new \RuntimeException(
                    'lead_metadata_write_failed'
                );
            }

            return [
                'revision' => $nextRevision,
                'status' => $status,
                'state' => $state,
                'updated_at' => $updatedAt,
            ];
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /**
     * @return array{
     *   revision:int,
     *   status:string,
     *   state:string,
     *   updated_at:null
     * }
     */
    private function defaults(): array
    {
        return [
            'revision' => 0,
            'status' => 'new',
            'state' => 'active',
            'updated_at' => null,
        ];
    }

    private function ensureRoot(): void
    {
        $parts = [
            dirname($this->root, 2),
            dirname($this->root),
            $this->root,
        ];

        foreach ($parts as $path) {
            if (!is_dir($path)) {
                $oldUmask = umask(0077);

                try {
                    $made = @mkdir(
                        $path,
                        0700
                    );
                } finally {
                    umask($oldUmask);
                }

                if (
                    !$made
                    && !is_dir($path)
                ) {
                    throw new \RuntimeException(
                        'lead_metadata_storage_invalid'
                    );
                }
            }

            if (
                @is_link($path)
                || !@chmod($path, 0700)
            ) {
                throw new \RuntimeException(
                    'lead_metadata_storage_invalid'
                );
            }
        }
    }

    private function ensureShard(
        string $leadId
    ): string {
        $path =
            $this->root
            . '/'
            . substr($leadId, 0, 2);

        if (!is_dir($path)) {
            $oldUmask = umask(0077);

            try {
                $made = @mkdir(
                    $path,
                    0700
                );
            } finally {
                umask($oldUmask);
            }

            if (
                !$made
                && !is_dir($path)
            ) {
                throw new \RuntimeException(
                    'lead_metadata_storage_invalid'
                );
            }
        }

        if (
            @is_link($path)
            || !@chmod($path, 0700)
        ) {
            throw new \RuntimeException(
                'lead_metadata_storage_invalid'
            );
        }

        return $path;
    }

    private function path(
        string $leadId
    ): string {
        return $this->root
            . '/'
            . substr($leadId, 0, 2)
            . '/'
            . $leadId
            . '.json';
    }

    /**
     * @return array{
     *   revision:int,
     *   status:string,
     *   state:string,
     *   updated_at:string
     * }
     */
    private function readStored(
        string $path,
        string $leadId
    ): array {
        $stat = @lstat($path);

        if (
            $stat === false
            || @is_link($path)
            || (
                $stat['mode']
                & 0170000
            ) !== 0100000
            || (
                $stat['mode']
                & 0777
            ) !== 0600
            || !is_int($stat['size'])
            || $stat['size'] < 2
            || $stat['size']
                > self::MAX_BYTES
        ) {
            throw new \RuntimeException(
                'lead_metadata_storage_invalid'
            );
        }

        $bytes =
            @file_get_contents($path);

        if (
            !is_string($bytes)
            || strlen($bytes)
                !== $stat['size']
            || !str_ends_with(
                $bytes,
                "\n"
            )
        ) {
            throw new \RuntimeException(
                'lead_metadata_storage_invalid'
            );
        }

        try {
            $record = json_decode(
                $bytes,
                true,
                8,
                JSON_THROW_ON_ERROR
            );

            $canonical = json_encode(
                $record,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ) . "\n";
        } catch (\Throwable) {
            throw new \RuntimeException(
                'lead_metadata_storage_invalid'
            );
        }

        if (
            !is_array($record)
            || array_is_list($record)
            || !hash_equals(
                $canonical,
                $bytes
            )
            || array_keys($record)
                !== [
                    'schema_version',
                    'lead_id',
                    'revision',
                    'status',
                    'state',
                    'updated_at',
                ]
            || $record['schema_version'] !== 1
            || $record['lead_id'] !== $leadId
            || !is_int($record['revision'])
            || $record['revision'] < 1
            || !in_array(
                $record['status'],
                self::STATUSES,
                true
            )
            || !in_array(
                $record['state'],
                self::STATES,
                true
            )
            || !is_string(
                $record['updated_at']
            )
            || preg_match(
                '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D',
                $record['updated_at']
            ) !== 1
        ) {
            throw new \RuntimeException(
                'lead_metadata_storage_invalid'
            );
        }

        return [
            'revision' => $record['revision'],
            'status' => $record['status'],
            'state' => $record['state'],
            'updated_at' => $record['updated_at'],
        ];
    }

    private function assertLeadId(
        string $leadId
    ): void {
        if (
            preg_match(
                '/\A[0-9a-f]{32}\z/D',
                $leadId
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'lead_id_invalid'
            );
        }
    }
}
