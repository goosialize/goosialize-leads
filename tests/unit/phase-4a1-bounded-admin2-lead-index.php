<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Admin\LeadIndexCollection;
use Grav\Plugin\GoosializeLeads\Admin\LeadIndexQuery;
use Grav\Plugin\GoosializeLeads\Admin\LeadSummary;
use Grav\Plugin\GoosializeLeads\Admin\LeadsIndexController;
use Grav\Plugin\GoosializeLeads\Application\CaptureCommand;
use Grav\Plugin\GoosializeLeads\Domain\LeadRecord;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadReadRepository;
use Grav\Plugin\GoosializeLeads\Storage\LeadReadRepository;
use Grav\Plugin\GoosializeLeads\Storage\StorageException;

function phase4a1Check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase4a1Remove(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);
        return;
    }
    if (!is_dir($path)) return;
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        phase4a1Remove($path . '/' . $entry);
    }
    rmdir($path);
}

/** @return array<string,mixed> */
function phase4a1Record(string $idByte, string $time, string $email): array
{
    $command = CaptureCommand::fromValidated([
        'consent' => ['granted' => true],
        'full_name' => 'Synthetic Lead',
        'email' => $email,
        'phone' => null,
        'company' => null,
        'message' => 'Synthetic Phase 4A.1 fixture',
        'resource_id' => null,
        'source_path' => null,
        'campaign' => null,
    ], [
        'source' => 'website',
        'form_name' => 'contact',
        'locale' => null,
        'consent_version' => 'privacy-v1',
    ]);
    $record = LeadRecord::fromCommand(
        $command,
        static fn (int $length): string => str_repeat($idByte, $length),
        static fn (): DateTimeInterface => new DateTimeImmutable($time)
    );
    phase4a1Check($record->isValid(), 'record fixture invalid');
    return $record->value()->toArray();
}

function phase4a1Write(string $root, array $record): void
{
    $year = substr($record['created_at'], 0, 4);
    $month = substr($record['created_at'], 5, 2);
    $directory = "{$root}/goosialize-leads/v1/records/{$year}/{$month}";
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
        chmod($root . '/goosialize-leads', 0700);
        chmod($root . '/goosialize-leads/v1', 0700);
        chmod($root . '/goosialize-leads/v1/records', 0700);
        chmod($root . "/goosialize-leads/v1/records/{$year}", 0700);
        chmod($directory, 0700);
    }
    $path = "{$directory}/{$record['id']}.json";
    file_put_contents($path, json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    chmod($path, 0600);
}

/** @return array<string,array{mode:int,size:int,hash:?string}> */
function phase4a1Snapshot(string $root): array
{
    $snapshot = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        $path = $entry->getPathname();
        $stat = lstat($path);
        phase4a1Check(is_array($stat), 'snapshot stat failed');
        $relative = substr($path, strlen($root) + 1);
        $snapshot[$relative] = [
            'mode' => $stat['mode'],
            'size' => $stat['size'],
            'hash' => $entry->isFile() && !$entry->isLink() ? hash_file('sha256', $path) : null,
        ];
    }
    ksort($snapshot, SORT_STRING);
    return $snapshot;
}

eval('namespace Grav\\Plugin\\GoosializeLeads\\Storage {
    function fopen(string $path, string $mode) {
        if (str_contains($path, "/idempotency/")) {
            $GLOBALS["phase4a1_sidecar_open_attempts"]++;
            return false;
        }
        if (($GLOBALS["phase4a1_swap_path"] ?? null) === $path) {
            \\unlink($path);
            \\symlink($GLOBALS["phase4a1_swap_target"], $path);
            $GLOBALS["phase4a1_swap_path"] = null;
        }
        $GLOBALS["phase4a1_primary_open_attempts"]++;
        return \\fopen($path, $mode);
    }
    function scandir(string $directory, int $sortingOrder = SCANDIR_SORT_ASCENDING, $context = null): array|false {
        throw new \\RuntimeException("Unbounded scandir() is forbidden.");
    }
}');
$GLOBALS['phase4a1_sidecar_open_attempts'] = 0;
$GLOBALS['phase4a1_primary_open_attempts'] = 0;

$root = sys_get_temp_dir() . '/goosialize-phase4a1-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
try {
    $query = LeadIndexQuery::newest();
    phase4a1Check($query->limit() === 100, 'query limit mismatch');
    try {
        LeadIndexCollection::create([], false, 1);
        throw new RuntimeException('inconsistent collection accepted');
    } catch (InvalidArgumentException) {
    }
    $repository = new FilesystemLeadReadRepository($root);
    phase4a1Check($repository->latest($query)->toResponse() === [
        'data' => [],
        'meta' => [
            'read_only' => true,
            'count' => 0,
            'limit' => 100,
            'truncated' => false,
            'total_scanned' => 0,
            'allowed_statuses' => ['new', 'contacted', 'qualified', 'closed'],
        ],
    ], 'empty response mismatch');

    $older = phase4a1Record("\x01", '2026-07-27T10:20:30.123456Z', 'older@example.test');
    $newerB = phase4a1Record("\x03", '2026-07-28T10:20:30.123456Z', 'b@example.test');
    $newerA = phase4a1Record("\x02", '2026-07-28T10:20:30.123456Z', 'a@example.test');
    phase4a1Write($root, $older);
    phase4a1Write($root, $newerB);
    phase4a1Write($root, $newerA);
    $response = $repository->latest($query)->toResponse();
    phase4a1Check(array_column($response['data'], 'id') === [$newerA['id'], $newerB['id'], $older['id']], 'fixed ordering mismatch');
    phase4a1Check(
        array_keys($response['data'][0]) === [
            'id',
            'created_at',
            'name',
            'email',
            'phone',
            'source',
            'form_name',
            'resource_id',
            'status',
            'form_or_resource',
        ],
        'projection mismatch'
    );
    phase4a1Check($response['meta']['count'] === 3 && $response['meta']['total_scanned'] === 3, 'collection metadata mismatch');

    $sidecarDirectory = $root . '/goosialize-leads/v1/idempotency/aa';
    mkdir($sidecarDirectory, 0700, true);
    file_put_contents($sidecarDirectory . '/' . str_repeat('a', 64) . '.json', '{malformed');
    file_put_contents($sidecarDirectory . '/' . str_repeat('b', 64) . '.json', str_repeat('x', 65536));
    $withSidecars = $repository->latest($query)->toResponse();
    phase4a1Check($withSidecars === $response, 'sidecar state changed collection');
    phase4a1Check($GLOBALS['phase4a1_sidecar_open_attempts'] === 0, 'sidecar content open attempted');
    phase4a1Check($GLOBALS['phase4a1_primary_open_attempts'] > 0, 'primary-open shim was not active');
    echo "PASS_PHASE_4A1_NO_SIDECAR_READ\n";

    $template = $older;
    for ($index = 4; $index <= 101; $index++) {
        $template['id'] = str_pad(dechex($index), 32, '0', STR_PAD_LEFT);
        phase4a1Write($root, $template);
    }
    $bounded = $repository->latest($query)->toResponse();
    phase4a1Check(
        count($bounded['data']) === 100
            && $bounded['meta']['limit'] === 100
            && $bounded['meta']['truncated'] === true
            && $bounded['meta']['total_scanned'] === 101,
        '100/101 collection boundary mismatch'
    );
    $beforeRead = phase4a1Snapshot($root);
    $repository->latest($query);
    phase4a1Check(phase4a1Snapshot($root) === $beforeRead, 'read changed filesystem state');

    $badPath = $root . '/goosialize-leads/v1/records/2026/07/' . $older['id'] . '.json';
    $originalBadBytes = file_get_contents($badPath);
    chmod($badPath, 0644);
    try {
        $repository->latest($query);
        throw new RuntimeException('wrong record mode accepted');
    } catch (StorageException $error) {
        phase4a1Check($error->stableCode() === 'lead_index_record_invalid', 'wrong record fault code');
    }
    chmod($badPath, 0600);
    file_put_contents($badPath, '{malformed');
    chmod($badPath, 0600);
    try {
        $repository->latest($query);
        throw new RuntimeException('malformed primary record accepted');
    } catch (StorageException $error) {
        phase4a1Check($error->stableCode() === 'lead_index_record_invalid', 'wrong malformed-record code');
    }
    file_put_contents($badPath, str_repeat('x', 32769));
    chmod($badPath, 0600);
    try {
        $repository->latest($query);
        throw new RuntimeException('oversized primary record accepted');
    } catch (StorageException $error) {
        phase4a1Check($error->stableCode() === 'lead_index_record_invalid', 'wrong oversized-record code');
    }
    file_put_contents($badPath, $originalBadBytes);
    chmod($badPath, 0600);
    $schemaRecord = json_decode($originalBadBytes, true, 8, JSON_THROW_ON_ERROR);
    $schemaRecord['consent']['version'] = 'INVALID';
    file_put_contents($badPath, json_encode($schemaRecord, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    chmod($badPath, 0600);
    try {
        $repository->latest($query);
        throw new RuntimeException('invalid unprojected record field accepted');
    } catch (StorageException $error) {
        phase4a1Check($error->stableCode() === 'lead_index_record_invalid', 'wrong full-schema code');
    }
    file_put_contents($badPath, $originalBadBytes);
    chmod($badPath, 0600);
    $GLOBALS['phase4a1_swap_path'] = $badPath;
    $GLOBALS['phase4a1_swap_target'] = dirname($badPath) . '/' . $newerA['id'] . '.json';
    try {
        $repository->latest($query);
        throw new RuntimeException('record swap was not rejected');
    } catch (StorageException $error) {
        phase4a1Check($error->stableCode() === 'lead_index_record_invalid', 'wrong record-swap code');
    }
    unlink($badPath);
    file_put_contents($badPath, $originalBadBytes);
    chmod($badPath, 0600);
    $linkPath = dirname($badPath) . '/' . str_repeat('f', 32) . '.json';
    symlink($badPath, $linkPath);
    try {
        $repository->latest($query);
        throw new RuntimeException('primary-record symlink accepted');
    } catch (StorageException $error) {
        phase4a1Check($error->stableCode() === 'lead_index_record_invalid', 'wrong symlink code');
    }
    unlink($linkPath);
    $rootLink = $root . '-link';
    symlink($root, $rootLink);
    try {
        new FilesystemLeadReadRepository($rootLink);
        throw new RuntimeException('storage-root symlink accepted');
    } catch (StorageException $error) {
        phase4a1Check($error->stableCode() === 'lead_index_storage_invalid', 'wrong root containment code');
    } finally {
        unlink($rootLink);
    }

    $capacityRoot = $root . '-capacity';
    mkdir($capacityRoot, 0700);
    try {
        for ($year = 2000; $year < 2020; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $directory = sprintf('%s/goosialize-leads/v1/records/%04d/%02d', $capacityRoot, $year, $month);
                mkdir($directory, 0700, true);
            }
        }
        foreach ([$capacityRoot . '/goosialize-leads', $capacityRoot . '/goosialize-leads/v1', $capacityRoot . '/goosialize-leads/v1/records'] as $directory) {
            chmod($directory, 0700);
        }
        try {
            (new FilesystemLeadReadRepository($capacityRoot))->latest($query);
            throw new RuntimeException('directory capacity overflow accepted');
        } catch (StorageException $error) {
            phase4a1Check($error->stableCode() === 'lead_index_capacity_exceeded', 'wrong capacity code');
        }
    } finally {
        phase4a1Remove($capacityRoot);
    }

    $recordCapacityRoot = $root . '-record-capacity';
    mkdir($recordCapacityRoot, 0700);
    try {
        $capacityRecord = $older;
        for ($index = 1; $index <= 10000; $index++) {
            $capacityRecord['id'] = str_pad(dechex($index), 32, '0', STR_PAD_LEFT);
            phase4a1Write($recordCapacityRoot, $capacityRecord);
        }
        $atCapacity = (new FilesystemLeadReadRepository($recordCapacityRoot))->latest($query);
        phase4a1Check(
            $atCapacity->totalScanned() === 10000 && count($atCapacity->summaries()) === 100 && $atCapacity->truncated(),
            '10,000 record boundary mismatch'
        );
        $capacityRecord['id'] = str_pad(dechex(10001), 32, '0', STR_PAD_LEFT);
        phase4a1Write($recordCapacityRoot, $capacityRecord);
        try {
            (new FilesystemLeadReadRepository($recordCapacityRoot))->latest($query);
            throw new RuntimeException('10,001st record candidate accepted');
        } catch (StorageException $error) {
            phase4a1Check($error->stableCode() === 'lead_index_capacity_exceeded', 'wrong record capacity code');
        }
    } finally {
        phase4a1Remove($recordCapacityRoot);
    }

    $classes = [LeadSummary::class, LeadIndexQuery::class, LeadIndexCollection::class, LeadsIndexController::class, FilesystemLeadReadRepository::class];
    foreach ($classes as $class) phase4a1Check((new ReflectionClass($class))->isFinal(), 'class is not final');
    phase4a1Check((new ReflectionClass(LeadReadRepository::class))->isInterface(), 'read repository is not interface');
    $methods = [
        LeadSummary::class => ['fromRecord', 'id', 'createdAt', 'name', 'email', 'phone', 'source', 'formName', 'resourceId', 'status', 'toArray'],
        LeadIndexQuery::class => ['newest', 'limit'],
        LeadIndexCollection::class => ['create', 'summaries', 'truncated', 'totalScanned', 'toResponse'],
        LeadsIndexController::class => ['__construct', 'filterFormData', 'index'],
        FilesystemLeadReadRepository::class => ['__construct', 'latest', 'findById'],
        LeadReadRepository::class => ['latest', 'findById'],
    ];
    foreach ($methods as $class => $expected) {
        $actual = [];
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === $class) $actual[] = $method->getName();
        }
        phase4a1Check($actual === $expected, 'public API mismatch: ' . $class);
    }
    echo "PASS_PHASE_4A1_BOUNDED_INDEX\n";
} finally {
    phase4a1Remove($root);
}
