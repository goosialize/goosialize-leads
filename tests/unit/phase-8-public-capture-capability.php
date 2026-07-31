<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Application\LeadCaptureRuntimeFactory;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Integration\GoosializeLeadsCaptureCapabilityV1;
use Grav\Plugin\GoosializeLeads\Integration\LeadCaptureCapabilityV1;
use Grav\Plugin\GoosializeLeads\Integration\LeadCaptureContextV1;
use Grav\Plugin\GoosializeLeads\Integration\LeadCaptureRequestV1;
use Grav\Plugin\GoosializeLeads\Integration\LeadCaptureResultV1;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;

function phase8Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase8RemoveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

$publicTypes = [
    GoosializeLeadsCaptureCapabilityV1::class,
    LeadCaptureContextV1::class,
    LeadCaptureRequestV1::class,
    LeadCaptureResultV1::class,
];

foreach ($publicTypes as $class) {
    $reflection = new ReflectionClass($class);

    phase8Check(
        $reflection->isFinal(),
        'Public runtime class is not final: ' . $class
    );

    phase8Check(
        $reflection->getProperties(ReflectionProperty::IS_PUBLIC) === [],
        'Public runtime class exposes a public property: ' . $class
    );
}

phase8Check(
    (new ReflectionClass(LeadCaptureCapabilityV1::class))->isInterface(),
    'Public capability type is not an interface.'
);

$capabilityMethods = [];
$capabilityReflection = new ReflectionClass(
    LeadCaptureCapabilityV1::class
);

foreach (
    $capabilityReflection->getMethods(ReflectionMethod::IS_PUBLIC)
    as $method
) {
    if (
        $method->getDeclaringClass()->getName()
        === LeadCaptureCapabilityV1::class
    ) {
        $capabilityMethods[] = $method->getName();
    }
}

phase8Check(
    $capabilityMethods === [
        'capabilityId',
        'contractVersion',
        'available',
        'capture',
    ],
    'Public capability interface method inventory mismatch.'
);

$serviceMethods = [];
$serviceReflection = new ReflectionClass(
    LeadCaptureService::class
);

foreach (
    $serviceReflection->getMethods(ReflectionMethod::IS_PUBLIC)
    as $method
) {
    if (
        $method->getDeclaringClass()->getName()
        === LeadCaptureService::class
    ) {
        $serviceMethods[] = $method->getName();
    }
}

phase8Check(
    $serviceMethods === [
        '__construct',
        'capture',
        'captureApi',
        'capturePublicCapability',
    ],
    'LeadCaptureService public API mismatch.'
);

$keyRingMethods = [];
$keyRingReflection = new ReflectionClass(
    IdempotencyKeyRing::class
);

foreach (
    $keyRingReflection->getMethods(ReflectionMethod::IS_PUBLIC)
    as $method
) {
    if (
        $method->getDeclaringClass()->getName()
        === IdempotencyKeyRing::class
    ) {
        $keyRingMethods[] = $method->getName();
    }
}

phase8Check(
    $keyRingMethods === [
        '__construct',
        'enabled',
        'activeVersion',
        'deriveFormsIdempotencyKey',
        'deriveApiIdempotencyKey',
        'derivePublicCapabilityIdempotencyKey',
        'keyDigest',
        'canonicalPayload',
        'payloadDigest',
        'verify',
    ],
    'IdempotencyKeyRing public API mismatch.'
);

$explicitName = LeadCaptureRequestV1::fromArray([
    'full_name' => '  Ada Lovelace  ',
    'first_name' => 'Ignored',
    'last_name' => 'Ignored',
    'email' => 'ada@example.test',
    'consent' => ['granted' => true],
]);

phase8Check(
    $explicitName->toArray()['full_name'] === 'Ada Lovelace',
    'Explicit full_name precedence failed.'
);

$composedName = LeadCaptureRequestV1::fromArray([
    'first_name' => '  Ada ',
    'last_name' => ' Lovelace  ',
    'email' => 'ada@example.test',
    'consent' => ['granted' => true],
]);

phase8Check(
    $composedName->toArray()['full_name'] === 'Ada Lovelace',
    'first_name and last_name composition failed.'
);

$unknownRejected = false;

try {
    LeadCaptureRequestV1::fromArray([
        'email' => 'ada@example.test',
        'unknown' => 'forbidden',
        'consent' => ['granted' => true],
    ]);
} catch (InvalidArgumentException) {
    $unknownRejected = true;
}

phase8Check(
    $unknownRejected,
    'Unknown public request field was accepted.'
);

$errorResult = LeadCaptureResultV1::validationFailed([
    'email' => ['invalid_email', 'invalid_email'],
    'consent' => ['consent_required'],
]);

phase8Check(
    $errorResult->toArray() === [
        'outcome' => 'validation_failed',
        'errors' => [
            'consent' => ['consent_required'],
            'email' => ['invalid_email'],
        ],
    ],
    'Public validation error map mismatch.'
);

foreach ([
    LeadCaptureResultV1::created(),
    LeadCaptureResultV1::replayed(),
    LeadCaptureResultV1::idempotencyConflict(),
    LeadCaptureResultV1::unavailable(),
] as $result) {
    phase8Check(
        $result->errors() === [],
        'Non-validation public result exposed errors.'
    );
}

$root = sys_get_temp_dir()
    . '/goosialize-leads-phase-8-'
    . bin2hex(random_bytes(8));

phase8Check(
    mkdir($root, 0700, true),
    'Unable to create Phase 8 temporary root.'
);

try {
    $service = LeadCaptureRuntimeFactory::create(
        $root,
        [
            'active_key_version' => 1,
            'keys' => [
                1 => base64_encode(str_repeat('k', 32)),
            ],
        ],
        null
    );

    $clock = static fn (): DateTimeInterface =>
        new DateTimeImmutable(
            '2026-07-31T18:00:00+00:00'
        );

    $entropy = static fn (int $length): string =>
        str_repeat('a', $length);

    $capability = new GoosializeLeadsCaptureCapabilityV1(
        $service,
        $entropy,
        $clock
    );

    phase8Check(
        $capability instanceof LeadCaptureCapabilityV1,
        'Concrete capability does not implement public interface.'
    );

    phase8Check(
        $capability->capabilityId()
        === 'goosialize-leads.capture',
        'Capability identifier mismatch.'
    );

    phase8Check(
        $capability->contractVersion() === 1,
        'Contract version mismatch.'
    );

    phase8Check(
        $capability->available(),
        'Configured capability reported unavailable.'
    );

    $context = LeadCaptureContextV1::create(
        'goosialize_links',
        'goosialize_links_contact',
        'en',
        'privacy_v1',
        'phase-8-logical-submission-0001'
    );

    $request = LeadCaptureRequestV1::fromArray([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'message' => 'Phase 8 public capability test',
        'consent' => ['granted' => true],
    ]);

    $created = $capability->capture(
        $request,
        $context
    );

    phase8Check(
        $created->outcome() === 'created',
        'Created public outcome mismatch.'
    );

    $replayed = $capability->capture(
        $request,
        $context
    );

    phase8Check(
        $replayed->outcome() === 'replayed',
        'Replayed public outcome mismatch.'
    );

    $conflict = $capability->capture(
        LeadCaptureRequestV1::fromArray([
            'full_name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'message' => 'Different logical content',
            'consent' => ['granted' => true],
        ]),
        $context
    );

    phase8Check(
        $conflict->outcome() === 'idempotency_conflict',
        'Idempotency-conflict public outcome mismatch.'
    );

    $validation = $capability->capture(
        LeadCaptureRequestV1::fromArray([
            'email' => 'not-an-email',
            'consent' => ['granted' => true],
        ]),
        LeadCaptureContextV1::create(
            'goosialize_links',
            'goosialize_links_contact',
            'en',
            'privacy_v1',
            'phase-8-logical-submission-0002'
        )
    );

    phase8Check(
        $validation->outcome() === 'validation_failed',
        'Validation public outcome mismatch.'
    );

    phase8Check(
        $validation->errors() !== [],
        'Validation result omitted public errors.'
    );

    $unavailable = new GoosializeLeadsCaptureCapabilityV1(
        null
    );

    phase8Check(
        !$unavailable->available(),
        'Unconfigured capability reported available.'
    );

    phase8Check(
        $unavailable->capture(
            $request,
            $context
        )->outcome() === 'unavailable',
        'Unavailable public outcome mismatch.'
    );

    foreach ([
        $created,
        $replayed,
        $conflict,
        $validation,
        $unavailable->capture($request, $context),
    ] as $publicResult) {
        $encoded = json_encode(
            $publicResult->toArray(),
            JSON_THROW_ON_ERROR
        );

        foreach ([
            '"record"',
            '"id"',
            'created_at',
            'storage',
            'repository',
            'phase-8-logical-submission-0001',
        ] as $forbidden) {
            phase8Check(
                !str_contains($encoded, $forbidden),
                'Public result exposed internal data.'
            );
        }
    }
} finally {
    phase8RemoveTree($root);
}

echo "PASS_PHASE_8_PUBLIC_TYPES\n";
echo "PASS_PHASE_8_REQUEST_CONTEXT\n";
echo "PASS_PHASE_8_RESULT_SHAPE\n";
echo "PASS_PHASE_8_CREATED\n";
echo "PASS_PHASE_8_REPLAYED\n";
echo "PASS_PHASE_8_VALIDATION\n";
echo "PASS_PHASE_8_IDEMPOTENCY_CONFLICT\n";
echo "PASS_PHASE_8_UNAVAILABLE\n";
echo "PASS_PHASE_8_NO_INTERNAL_DATA\n";
