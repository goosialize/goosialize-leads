<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Application\CaptureResult;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureRuntimeFactory;
use Grav\Plugin\GoosializeLeads\Notification\NotificationOutbox;
use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Http\FormsLeadCaptureAdapter;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\LeadRepository;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;
use Grav\Plugin\GoosializeLeads\Validation\ValidationError;

function phase3cCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase3cSubmitted(string $email = 'lead@example.test'): array
{
    return [
        'full_name' => 'Synthetic Lead',
        'email' => $email,
        'phone' => '+35722000000',
        'company' => null,
        'message' => 'Phase 3C.1 Forms fixture',
        'resource_id' => null,
        'source_path' => null,
        'campaign' => null,
        'consent' => ['granted' => true],
    ];
}

$keyBytes = str_repeat('K', 32);
$ring = new IdempotencyKeyRing(1, [1 => base64_encode($keyBytes)]);
$submissionId = '0123456789abcdefghij';
$expectedHmac = hash_hmac('sha256', "grav-forms-v1\ncontact\n{$submissionId}\n", $keyBytes);
phase3cCheck(
    $ring->deriveFormsIdempotencyKey('contact', $submissionId) === $expectedHmac,
    'Forms HMAC derivation mismatch'
);
foreach ([['Contact', $submissionId], ['contact', 'short']] as [$form, $id]) {
    try {
        $ring->deriveFormsIdempotencyKey($form, $id);
        throw new RuntimeException('invalid Forms idempotency input accepted');
    } catch (InvalidArgumentException) {
    }
}

$repository = new class implements LeadRepository {
    /** @var list<string> */
    public array $queue = [];
    /** @var list<PersistenceRequest> */
    public array $requests = [];

    public function persist(PersistenceRequest $request): PersistenceResult
    {
        $this->requests[] = $request;
        return match (array_shift($this->queue) ?? 'created') {
            'created' => PersistenceResult::created($request->record()),
            'replayed' => PersistenceResult::replayed($request->record()->toArray()),
            'conflict' => PersistenceResult::failure('idempotency_conflict'),
            'expired' => PersistenceResult::failure('idempotency_expired'),
            'storage' => PersistenceResult::failure('storage_unavailable'),
            default => PersistenceResult::idCollision(),
        };
    }
};
$coordinator = new LeadPersistenceCoordinator(
    new LeadInputValidator(new LeadNormalizer()),
    $repository,
    $ring
);
$service = new LeadCaptureService($coordinator, $ring);
$trusted = ['source' => 'website', 'form_name' => 'contact', 'locale' => null, 'consent_version' => 'privacy-v1'];
$entropy = static fn (int $length): string => str_repeat("\x01", $length);
$clock = static fn (): DateTimeInterface => new DateTimeImmutable('2026-07-27T10:20:30.123456Z');

$created = $service->capture(phase3cSubmitted(), $trusted, 'contact', $submissionId, $entropy, $clock);
phase3cCheck($created->isSuccess() && !$created->replayed(), 'created mapping failed');
phase3cCheck(array_keys($created->record()) === ['id', 'status', 'created_at'], 'success record projection mismatch');
phase3cCheck(count($repository->requests) === 1, 'service did not persist exactly once');
$repository->queue = ['replayed'];
$replayed = $service->capture(phase3cSubmitted(), $trusted, 'contact', $submissionId, $entropy, $clock);
phase3cCheck($replayed->isSuccess() && $replayed->replayed(), 'replay mapping failed');
$repository->queue = ['conflict'];
phase3cCheck(
    $service->capture(phase3cSubmitted('other@example.test'), $trusted, 'contact', $submissionId, $entropy, $clock)->code()
        === 'idempotency_conflict',
    'conflict mapping failed'
);
$invalid = $service->capture(phase3cSubmitted(), $trusted, 'contact', 'INVALID', $entropy, $clock);
phase3cCheck($invalid->code() === 'invalid_submission_id', 'submission identifier mapping failed');
$validation = $service->capture([], $trusted, 'contact', $submissionId, $entropy, $clock);
phase3cCheck(
    $validation->code() === 'validation_failed' && $validation->errors() !== [],
    'validation mapping failed'
);

$defaultRoot = sys_get_temp_dir() . '/phase-3c-default-' . bin2hex(random_bytes(6));
mkdir($defaultRoot, 0700);
$emptyDefaultService = LeadCaptureRuntimeFactory::create(
    $defaultRoot,
    ['active_key_version' => null, 'keys' => []],
    null
);
phase3cCheck($emptyDefaultService instanceof LeadCaptureService, 'default YAML key ring was rejected');
$defaultService = LeadCaptureRuntimeFactory::create(
    $defaultRoot,
    ['active_key_version' => null, 'keys' => [1 => ['secret' => null]]],
    null
);
$defaultCapture = $defaultService->capture(
    phase3cSubmitted('fresh-install@example.test'),
    $trusted,
    'contact',
    'abcdefghij0123456789',
    $entropy,
    $clock
);
phase3cCheck($defaultCapture->isSuccess(), 'default empty key-ring Forms capture failed');
$defaultRecord = glob($defaultRoot . '/goosialize-leads/v1/records/*/*/*.json') ?: [];
phase3cCheck(count($defaultRecord) === 1, 'default capture did not publish one record');
$defaultBytes = file_get_contents($defaultRecord[0]);
$defaultData = is_string($defaultBytes) ? json_decode($defaultBytes, true, 8, JSON_THROW_ON_ERROR) : null;
phase3cCheck(
    is_array($defaultData)
    && ($defaultData['idempotency'] ?? null) === [
        'key_version' => null,
        'key_hash' => null,
        'payload_fingerprint' => null,
    ],
    'default capture persisted keyed idempotency'
);

$nestedSecret = base64_encode(str_repeat('N', 32));
$nestedRoot = sys_get_temp_dir() . '/phase-3c-nested-' . bin2hex(random_bytes(6));
mkdir($nestedRoot, 0700);
$nestedService = LeadCaptureRuntimeFactory::create(
    $nestedRoot,
    ['active_key_version' => 1, 'keys' => [1 => ['secret' => $nestedSecret]]],
    null
);
phase3cCheck(
    $nestedService->capture(phase3cSubmitted('nested-key@example.test'), $trusted, 'contact', 'jihgfedcba9876543210', $entropy, $clock)->isSuccess(),
    'nested secret key configuration failed'
);

$legacyRoot = sys_get_temp_dir() . '/phase-3c-legacy-' . bin2hex(random_bytes(6));
mkdir($legacyRoot, 0700);
$legacyService = LeadCaptureRuntimeFactory::create(
    $legacyRoot,
    ['active_key_version' => 1, 'keys' => [1 => $nestedSecret]],
    null
);
$legacyCapture = $legacyService->capture(
    phase3cSubmitted('legacy-key@example.test'),
    $trusted,
    'contact',
    'legacy01234567890123',
    $entropy,
    $clock
);
phase3cCheck($legacyCapture->isSuccess(), 'legacy scalar secret compatibility failed');
$legacyReplay = $legacyService->capture(
    phase3cSubmitted('legacy-key@example.test'),
    $trusted,
    'contact',
    'legacy01234567890123',
    $entropy,
    $clock
);
phase3cCheck($legacyReplay->isSuccess() && $legacyReplay->replayed(), 'legacy keyed duplicate replay failed');
$legacyRecord = glob($legacyRoot . '/goosialize-leads/v1/records/*/*/*.json') ?: [];
phase3cCheck(count($legacyRecord) === 1, 'legacy scalar secret did not publish one record');
$legacyBytes = file_get_contents($legacyRecord[0]);
$legacyData = is_string($legacyBytes) ? json_decode($legacyBytes, true, 8, JSON_THROW_ON_ERROR) : null;
phase3cCheck(
    is_array($legacyData)
    && ($legacyData['idempotency']['key_version'] ?? null) === 1
    && is_string($legacyData['idempotency']['key_hash'] ?? null)
    && is_string($legacyData['idempotency']['payload_fingerprint'] ?? null),
    'legacy scalar secret did not preserve keyed capture behavior'
);

foreach ([
    ['active_key_version' => 1, 'keys' => [1 => ['secret' => '']]],
    ['active_key_version' => 1, 'keys' => [2 => ['secret' => $nestedSecret]]],
    ['active_key_version' => 1, 'keys' => [1 => ['secret' => $nestedSecret, 'unexpected' => true]]],
] as $invalidKeyConfiguration) {
    try {
        LeadCaptureRuntimeFactory::create($legacyRoot, $invalidKeyConfiguration, null);
        throw new RuntimeException('invalid idempotency key configuration accepted');
    } catch (Throwable $exception) {
        phase3cCheck(
            $exception->getMessage() !== 'invalid idempotency key configuration accepted',
            'invalid idempotency key configuration accepted'
        );
    }
}

$error = new ValidationError('required', 'full_name');
$failure = CaptureResult::failure('validation_failed', [$error]);
phase3cCheck(
    $failure->toArray() === [
        'success' => false,
        'record' => null,
        'replayed' => false,
        'code' => 'validation_failed',
        'errors' => [['code' => 'required', 'field' => 'full_name']],
    ],
    'CaptureResult array shape mismatch'
);
foreach (['invalid_submission_id', 'idempotency_conflict', 'storage_unavailable', 'forms_configuration_invalid'] as $code) {
    phase3cCheck(CaptureResult::failure($code)->code() === $code, 'failure allowlist mismatch');
}

$apis = [
    CaptureResult::class => ['success', 'failure', 'isSuccess', 'record', 'replayed', 'code', 'errors', 'errorsAsArray', 'toArray'],
    LeadCaptureService::class => ['__construct', 'capture', 'captureApi', 'capturePublicCapability'],
    FormsLeadCaptureAdapter::class => ['__construct', 'process'],
    IdempotencyKeyRing::class => [
        '__construct', 'enabled', 'activeVersion', 'deriveFormsIdempotencyKey', 'deriveApiIdempotencyKey', 'derivePublicCapabilityIdempotencyKey',
        'keyDigest', 'canonicalPayload', 'payloadDigest', 'verify',
    ],
];
foreach ($apis as $class => $expected) {
    $reflection = new ReflectionClass($class);
    phase3cCheck($reflection->isFinal(), 'runtime class is not final');
    phase3cCheck($reflection->getProperties(ReflectionProperty::IS_PUBLIC) === [], 'public property exposed');
    $actual = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $class) {
            $actual[] = $method->getName();
        }
    }
    phase3cCheck($actual === $expected, 'public API mismatch: ' . $class);
}
$adapterConstructor = new ReflectionMethod(FormsLeadCaptureAdapter::class, '__construct');
phase3cCheck(
    array_map(static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(), $adapterConstructor->getParameters())
        === [LeadCaptureService::class, 'array', 'callable', 'callable'],
    'adapter constructor signature mismatch'
);
$serviceConstructor = new ReflectionMethod(LeadCaptureService::class, '__construct');
phase3cCheck(
    array_map(static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(), $serviceConstructor->getParameters())
        === [LeadPersistenceCoordinator::class, IdempotencyKeyRing::class, '?' . NotificationOutbox::class, '?callable'],
    'service constructor signature mismatch'
);

$serialized = json_encode([$created->toArray(), $failure->toArray()], JSON_THROW_ON_ERROR);
foreach (['Synthetic Lead', 'lead@example.test', '+35722000000', $submissionId, $expectedHmac] as $secret) {
    phase3cCheck(!str_contains($serialized, $secret), 'CaptureResult leaked private input');
}

echo "PASS_PHASE_3C1_SHARED_SERVICE\n";
