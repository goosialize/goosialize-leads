<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Admin\LeadIndexQuery;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadMetadataRepository;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadReadRepository;

function batch1Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function batch1Remove(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        batch1Remove($path . '/' . $entry);
    }

    rmdir($path);
}

/** @return array<string,mixed> */
function batch1Record(string $id, int $day): array
{
    $created = sprintf('2026-07-%02dT10:20:30.123456Z', $day);

    return [
        'schema_version' => 1,
        'id' => $id,
        'created_at' => $created,
        'updated_at' => $created,
        'status' => 'new',
        'revision' => 1,
        'source' => 'website',
        'form_name' => 'contact',
        'locale' => null,
        'consent' => [
            'granted' => true,
            'version' => 'privacy-v1',
            'captured_at' => $created,
        ],
        'idempotency' => [
            'key_version' => null,
            'key_hash' => null,
            'payload_fingerprint' => null,
        ],
        'full_name' => 'Lead ' . $id,
        'email' => 'lead@example.test',
        'phone' => null,
        'company' => null,
        'message' => null,
        'resource_id' => null,
        'source_path' => null,
        'campaign' => null,
    ];
}

/** @param array<string,mixed> $record */
function batch1Write(string $root, array $record): void
{
    $directory = $root . '/goosialize-leads/v1/records/2026/07';
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
        foreach ([
            $root . '/goosialize-leads',
            $root . '/goosialize-leads/v1',
            $root . '/goosialize-leads/v1/records',
            $root . '/goosialize-leads/v1/records/2026',
            $directory,
        ] as $path) {
            chmod($path, 0700);
        }
    }

    $path = $directory . '/' . $record['id'] . '.json';
    file_put_contents(
        $path,
        json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );
    chmod($path, 0600);
}

$root = sys_get_temp_dir() . '/goosialize-remediation-batch-1-' . bin2hex(random_bytes(8));
mkdir($root, 0700);

try {
    $oldestId = str_pad('1', 32, '0', STR_PAD_LEFT);
    batch1Write($root, batch1Record($oldestId, 1));

    for ($index = 2; $index <= 101; $index++) {
        $id = str_pad(dechex($index), 32, '0', STR_PAD_LEFT);
        batch1Write($root, batch1Record($id, 2 + (($index - 2) % 27)));
    }

    $reader = new FilesystemLeadReadRepository($root);
    $index = $reader->latest(LeadIndexQuery::newest())->toResponse();
    batch1Check(count($index['data']) === 100, 'Admin2 index is not bounded to 100');
    batch1Check(!in_array($oldestId, array_column($index['data'], 'id'), true), 'oldest Lead unexpectedly in latest 100');
    batch1Check($reader->findById($oldestId)?->id() === $oldestId, 'exact-ID lookup missed Lead outside latest 100');
    batch1Check($reader->findById(str_repeat('f', 32)) === null, 'nonexistent valid ID did not return null');

    try {
        $reader->findById('../not-a-lead');
        throw new RuntimeException('malformed ID was accepted');
    } catch (InvalidArgumentException) {
    }

    $metadata = new FilesystemLeadMetadataRepository($root);
    $deleted = $metadata->save($oldestId, 0, 'qualified', 'deleted');
    batch1Check($deleted['revision'] === 1 && $deleted['state'] === 'deleted', 'delete transition failed');

    try {
        $metadata->save($oldestId, 0, 'qualified', 'active');
        throw new RuntimeException('stale optimistic revision was accepted');
    } catch (DomainException $error) {
        batch1Check($error->getMessage() === 'lead_metadata_revision_conflict', 'revision conflict code changed');
    }

    foreach ([
        ['qualified', 'inactive'],
        ['closed', 'active'],
        ['qualified', 'deleted'],
    ] as [$status, $state]) {
        try {
            $metadata->save($oldestId, 1, $status, $state);
            throw new RuntimeException('deleted Lead mutation bypassed Restore');
        } catch (DomainException $error) {
            batch1Check($error->getMessage() === 'lead_metadata_deleted_restore_required', 'deleted-state code changed');
        }
    }

    $restored = $metadata->save($oldestId, 1, 'qualified', 'active');
    batch1Check(
        $restored['revision'] === 2
        && $restored['status'] === 'qualified'
        && $restored['state'] === 'active',
        'Restore did not preserve status and reactivate Lead'
    );

    echo "PASS_REMEDIATION_BATCH_1_EXACT_ID\n";
    echo "PASS_REMEDIATION_BATCH_1_METADATA\n";
} finally {
    batch1Remove($root);
}
