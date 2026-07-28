<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Storage;

final class StorageException extends \RuntimeException
{
    private const CODES = [
        'root_invalid', 'unsafe_path', 'symlink_detected', 'directory_creation_failed',
        'permission_failed', 'lock_failed', 'temporary_creation_failed', 'write_failed',
        'short_write', 'flush_failed', 'file_fsync_failed', 'close_failed',
        'publication_unsupported', 'publication_failed', 'sidecar_publication_failed',
        'cleanup_failed', 'idempotency_index_invalid', 'key_configuration_invalid',
        'unexpected_storage_failure', 'lead_index_storage_invalid',
        'lead_index_capacity_exceeded', 'lead_index_record_invalid',
    ];

    public function __construct(private readonly string $stableCode, ?\Throwable $previous = null)
    {
        if (!in_array($stableCode, self::CODES, true)) {
            throw new \InvalidArgumentException('Unknown storage error code.');
        }
        parent::__construct($stableCode, 0, $previous);
    }

    public function stableCode(): string
    {
        return $this->stableCode;
    }
}
