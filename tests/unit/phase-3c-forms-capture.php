<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Application\CaptureResult;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
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
    LeadCaptureService::class => ['__construct', 'capture', 'captureApi'],
    FormsLeadCaptureAdapter::class => ['__construct', 'process'],
    IdempotencyKeyRing::class => [
        '__construct', 'enabled', 'activeVersion', 'deriveFormsIdempotencyKey', 'deriveApiIdempotencyKey',
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
