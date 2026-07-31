<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\LeadRepository;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

/** @return array<string,mixed> */
function submitted(): array
{
    return [
        'consent' => ['granted' => true],
        'full_name' => 'Synthetic Lead',
        'email' => 'lead@example.test',
        'phone' => '+35722000000',
        'company' => null,
        'message' => 'Phase 3B persistence fixture',
        'resource_id' => null,
        'source_path' => null,
        'campaign' => null,
    ];
}

$trusted = ['source' => 'api', 'form_name' => 'goosialize-leads-capture', 'locale' => null, 'consent_version' => 'privacy-v1'];
$key1 = base64_encode(str_repeat('K', 32));
$key2 = base64_encode(str_repeat('R', 32));
$ring = new IdempotencyKeyRing(1, [1 => $key1, 2 => $key2]);
$validator = new LeadInputValidator(new LeadNormalizer());
$commandResult = $validator->validate(submitted(), $trusted);
check($commandResult->isValid(), 'fixture validation failed');
$command = $commandResult->value();
check($ring->keyDigest('synthetic-key-01') === '3a1190b048a946c5d50e77efb7361ab6768acbfa4e3a54fa8e0bf0b8ff927f59', 'key digest mismatch');
check($ring->payloadDigest($command) === '838eb9525abf7512b0e1872249d52b5af67d662341185bed54dd14c93437896b', 'payload digest mismatch');
check($ring->verify(1, $ring->canonicalPayload($command), $ring->payloadDigest($command)), 'active verification failed');
$rotated = new IdempotencyKeyRing(2, [1 => $key1, 2 => $key2]);
check($rotated->verify(1, $ring->canonicalPayload($command), $ring->payloadDigest($command)), 'historical verification failed');
check($rotated->payloadDigest($command) !== $ring->payloadDigest($command), 'rotation did not change digest');
$formsBytes = "grav-forms-v1\ncontact\n0123456789abcdefghij\n";
check(
    $ring->deriveFormsIdempotencyKey('contact', '0123456789abcdefghij')
        === hash_hmac('sha256', $formsBytes, str_repeat('K', 32)),
    'Forms idempotency active-key derivation mismatch'
);
check(
    $ring->deriveApiIdempotencyKey('synthetic-key-01')
        === hash_hmac('sha256', "public-api-v1\nsynthetic-key-01\n", str_repeat('K', 32)),
    'API idempotency active-key derivation mismatch'
);
check(
    $rotated->deriveFormsIdempotencyKey('contact', '0123456789abcdefghij')
        === hash_hmac('sha256', $formsBytes, str_repeat('R', 32)),
    'Forms idempotency key-version selection mismatch'
);
foreach ([['Contact', '0123456789abcdefghij'], ['contact', 'invalid']] as [$formName, $submissionId]) {
    try {
        $ring->deriveFormsIdempotencyKey($formName, $submissionId);
        throw new RuntimeException('invalid Forms idempotency argument accepted');
    } catch (\InvalidArgumentException) {
    }
}
try {
    (new IdempotencyKeyRing(null, []))->deriveFormsIdempotencyKey('contact', '0123456789abcdefghij');
    throw new RuntimeException('disabled Forms idempotency key ring accepted');
} catch (\Grav\Plugin\GoosializeLeads\Storage\StorageException $e) {
    check($e->stableCode() === 'key_configuration_invalid', 'wrong disabled Forms key code');
}
foreach ([[null, [1 => $key1]], [1, []], [1, [1 => 'bad']]] as [$active, $keys]) {
    try {
        new IdempotencyKeyRing($active, $keys);
        throw new RuntimeException('invalid key configuration accepted');
    } catch (\Grav\Plugin\GoosializeLeads\Storage\StorageException $e) {
        check($e->stableCode() === 'key_configuration_invalid', 'wrong key configuration code');
    }
}
echo "PASS_PHASE_3B_IDEMPOTENCY\n";

$storageCodes = [
    'root_invalid', 'unsafe_path', 'symlink_detected', 'directory_creation_failed',
    'permission_failed', 'lock_failed', 'temporary_creation_failed', 'write_failed',
    'short_write', 'flush_failed', 'file_fsync_failed', 'close_failed',
    'publication_unsupported', 'publication_failed', 'sidecar_publication_failed',
    'cleanup_failed', 'idempotency_index_invalid', 'key_configuration_invalid',
    'unexpected_storage_failure', 'lead_index_storage_invalid',
    'lead_index_capacity_exceeded', 'lead_index_record_invalid',
];
foreach ($storageCodes as $storageCode) {
    $exception = new \Grav\Plugin\GoosializeLeads\Storage\StorageException($storageCode);
    check($exception->stableCode() === $storageCode && $exception->getMessage() === $storageCode, 'storage code mismatch');
}
foreach (['lead_index_', 'lead_index_*', '*', 'arbitrary'] as $unknownCode) {
    try {
        new \Grav\Plugin\GoosializeLeads\Storage\StorageException($unknownCode);
        throw new RuntimeException('unknown storage code accepted');
    } catch (\InvalidArgumentException) {
    }
}
echo "PASS_PHASE_4A1_STORAGE_EXCEPTION_ALLOWLIST\n";

$fake = new class implements LeadRepository {
    /** @var list<string> */
    public array $queue = [];
    /** @var list<PersistenceRequest> */
    public array $requests = [];
    public function persist(PersistenceRequest $request): PersistenceResult
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? 'created';
        return $next === 'id_collision' ? PersistenceResult::idCollision() : PersistenceResult::created($request->record());
    }
};
$coordinator = new LeadPersistenceCoordinator($validator, $fake, $ring);
$entropyCalls = 0;
$entropy = static function (int $length) use (&$entropyCalls): string {
    $entropyCalls++;
    return str_repeat(chr($entropyCalls - 1), $length);
};
$clockCalls = 0;
$clock = static function () use (&$clockCalls): DateTimeImmutable {
    $clockCalls++;
    return new DateTimeImmutable('2026-07-27T10:20:30.123456Z');
};
$fake->queue = ['id_collision', 'id_collision', 'created'];
$result = $coordinator->persist(submitted(), $trusted, 'synthetic-key-01', $entropy, $clock);
check($result->status() === 'created' && $entropyCalls === 3 && $clockCalls === 3 && count($fake->requests) === 3, 'early collision retry mismatch');
foreach ($fake->requests as $request) {
    check($request->record()->toArray()['idempotency']['key_version'] === 1, 'idempotency version missing');
}
$fake->queue = array_fill(0, 5, 'id_collision');
$fake->requests = [];
$entropyCalls = 0;
$clockCalls = 0;
$result = $coordinator->persist(submitted(), $trusted, null, $entropy, $clock);
check($result->code() === 'collision_exhausted' && $entropyCalls === 5 && $clockCalls === 5 && count($fake->requests) === 5, 'collision exhaustion mismatch');
echo "PASS_PHASE_3B_COLLISION_POLICY\n";

$classes = [
    LeadPersistenceCoordinator::class,
    IdempotencyKeyRing::class,
    \Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository::class,
    PersistenceRequest::class,
    PersistenceResult::class,
    \Grav\Plugin\GoosializeLeads\Storage\StorageException::class,
];
foreach ($classes as $class) check((new ReflectionClass($class))->isFinal(), 'class is not final');
check((new ReflectionClass(LeadRepository::class))->isInterface(), 'repository is not interface');
$methods = [
    LeadPersistenceCoordinator::class => ['__construct', 'persist'],
    IdempotencyKeyRing::class => ['__construct', 'enabled', 'activeVersion', 'deriveFormsIdempotencyKey', 'deriveApiIdempotencyKey', 'derivePublicCapabilityIdempotencyKey', 'keyDigest', 'canonicalPayload', 'payloadDigest', 'verify'],
    \Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository::class => ['__construct', 'persist'],
    PersistenceRequest::class => ['create', 'record', 'recordBytes', 'keyDigest', 'payloadBytes', 'hasIdempotency'],
    PersistenceResult::class => ['created', 'replayed', 'idCollision', 'failure', 'status', 'record', 'code', 'errors', 'errorsAsArray', 'isSuccess', 'toArray'],
    \Grav\Plugin\GoosializeLeads\Storage\StorageException::class => ['__construct', 'stableCode'],
    LeadRepository::class => ['persist'],
];
foreach ($methods as $class => $expected) {
    $reflection = new ReflectionClass($class);
    $actual = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $class) $actual[] = $method->getName();
    }
    check($actual === $expected, 'public API mismatch: ' . $class);
}
$formsMethod = new ReflectionMethod(IdempotencyKeyRing::class, 'deriveFormsIdempotencyKey');
check($formsMethod->isPublic() && !$formsMethod->isStatic(), 'Forms idempotency method visibility mismatch');
check((string) $formsMethod->getReturnType() === 'string', 'Forms idempotency return type mismatch');
$formsParameters = $formsMethod->getParameters();
check(count($formsParameters) === 2, 'Forms idempotency parameter count mismatch');
check(
    $formsParameters[0]->getName() === 'formName'
        && (string) $formsParameters[0]->getType() === 'string'
        && $formsParameters[1]->getName() === 'submissionId'
        && (string) $formsParameters[1]->getType() === 'string',
    'Forms idempotency parameter contract mismatch'
);
