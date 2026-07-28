<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Application\CaptureCommand;
use Grav\Plugin\GoosializeLeads\Domain\LeadIdGenerator;
use Grav\Plugin\GoosializeLeads\Domain\LeadRecord;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;
use Grav\Plugin\GoosializeLeads\Validation\ValidationError;
use Grav\Plugin\GoosializeLeads\Validation\ValidationResult;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{source:string,form_name:string,locale:?string,consent_version:string} */
function trusted(): array
{
    return [
        'source' => 'website',
        'form_name' => 'contact',
        'locale' => 'en',
        'consent_version' => 'privacy-2026-01',
    ];
}

/** @return array<string,mixed> */
function validInput(?string $email = 'lead@example.test'): array
{
    return [
        'consent' => ['granted' => true],
        'full_name' => 'Example Lead',
        'email' => $email,
        'phone' => '+35700000000',
        'company' => null,
        'message' => 'Please contact me.',
        'resource_id' => 'example-resource',
        'source_path' => '/example',
        'campaign' => [
            'utm_source' => 'example',
            'utm_medium' => 'example',
            'utm_campaign' => 'example',
            'utm_term' => null,
            'utm_content' => null,
        ],
    ];
}

$classes = [
    CaptureCommand::class,
    LeadIdGenerator::class,
    LeadRecord::class,
    LeadNormalizer::class,
    LeadInputValidator::class,
    ValidationError::class,
    ValidationResult::class,
];
foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);
    assertTrue($reflection->isFinal(), $class . ' must be final');
    assertTrue($reflection->getProperties(ReflectionProperty::IS_PUBLIC) === [], $class . ' has public properties');
}
assertTrue((new ReflectionClass(CaptureCommand::class))->getConstructor()?->isPrivate() === true, 'CaptureCommand constructor');
assertTrue((new ReflectionClass(LeadRecord::class))->getConstructor()?->isPrivate() === true, 'LeadRecord constructor');
assertTrue((new ReflectionClass(ValidationResult::class))->getConstructor()?->isPrivate() === true, 'ValidationResult constructor');

$expectedMethods = [
    CaptureCommand::class => ['fromValidated', 'submitted', 'trusted', 'toArray'],
    LeadIdGenerator::class => ['__construct', 'generate'],
    LeadRecord::class => ['fromCommand', 'id', 'capturedAt', 'status', 'data', 'toArray', 'serialize'],
    LeadNormalizer::class => ['__construct', 'unicodeCapabilityAvailable', 'normalizeNfc', 'normalizeWhitespace', 'normalizeEmail'],
    LeadInputValidator::class => ['__construct', 'validate'],
    ValidationError::class => ['__construct', 'code', 'field', 'toArray'],
    ValidationResult::class => ['success', 'failure', 'isValid', 'value', 'errors', 'errorsAsArray'],
];
foreach ($expectedMethods as $class => $names) {
    $actual = array_map(
        static fn (ReflectionMethod $method): string => $method->getName(),
        array_filter(
            (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $class
        )
    );
    sort($actual);
    sort($names);
    assertTrue($actual === $names, $class . ' public methods mismatch');
}
$signatures = [
    CaptureCommand::class . '::fromValidated' => [true, ['array', 'array'], CaptureCommand::class],
    CaptureCommand::class . '::submitted' => [false, [], 'array'],
    CaptureCommand::class . '::trusted' => [false, [], 'array'],
    CaptureCommand::class . '::toArray' => [false, [], 'array'],
    LeadIdGenerator::class . '::generate' => [false, ['callable'], ValidationResult::class],
    LeadRecord::class . '::fromCommand' => [true, [CaptureCommand::class, 'callable', 'callable'], ValidationResult::class],
    LeadRecord::class . '::id' => [false, [], 'string'],
    LeadRecord::class . '::capturedAt' => [false, [], 'string'],
    LeadRecord::class . '::status' => [false, [], 'string'],
    LeadRecord::class . '::data' => [false, [], 'array'],
    LeadRecord::class . '::toArray' => [false, [], 'array'],
    LeadRecord::class . '::serialize' => [false, [LeadRecord::class], ValidationResult::class],
    LeadNormalizer::class . '::unicodeCapabilityAvailable' => [false, [], 'bool'],
    LeadNormalizer::class . '::normalizeNfc' => [false, ['string'], '?string'],
    LeadNormalizer::class . '::normalizeWhitespace' => [false, ['string', 'string'], 'string'],
    LeadNormalizer::class . '::normalizeEmail' => [false, ['string'], 'string'],
    LeadInputValidator::class . '::validate' => [false, ['mixed', 'array'], ValidationResult::class],
    ValidationError::class . '::code' => [false, [], 'string'],
    ValidationError::class . '::field' => [false, [], '?string'],
    ValidationError::class . '::toArray' => [false, [], 'array'],
    ValidationResult::class . '::success' => [true, ['object|string'], ValidationResult::class],
    ValidationResult::class . '::failure' => [true, ['array'], ValidationResult::class],
    ValidationResult::class . '::isValid' => [false, [], 'bool'],
    ValidationResult::class . '::value' => [false, [], 'object|string|null'],
    ValidationResult::class . '::errors' => [false, [], 'array'],
    ValidationResult::class . '::errorsAsArray' => [false, [], 'array'],
];
foreach ($signatures as $target => [$static, $parameterTypes, $returnType]) {
    [$class, $methodName] = explode('::', $target, 2);
    $method = new ReflectionMethod($class, $methodName);
    assertTrue($method->isPublic() && $method->isStatic() === $static, $target . ' visibility/static');
    $actualTypes = array_map(
        static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
        $method->getParameters()
    );
    assertTrue($actualTypes === $parameterTypes, $target . ' parameter types');
    assertTrue((string) $method->getReturnType() === $returnType, $target . ' return type');
}

$error = new ValidationError('invalid_email', 'email');
assertTrue($error->toArray() === ['code' => 'invalid_email', 'field' => 'email'], 'ValidationError shape');
try {
    new ValidationError('not_approved', null);
    throw new RuntimeException('unknown ValidationError code accepted');
} catch (InvalidArgumentException) {
}
$failure = ValidationResult::failure([$error, $error]);
assertTrue(!$failure->isValid() && $failure->value() === null, 'failure invariant');
assertTrue($failure->errorsAsArray() === [$error->toArray(), $error->toArray()], 'error order');
try {
    ValidationResult::failure([]);
    throw new RuntimeException('empty failure accepted');
} catch (InvalidArgumentException) {
}

$normalizer = new LeadNormalizer();
assertTrue($normalizer->unicodeCapabilityAvailable(), 'Unicode capability');
assertTrue($normalizer->normalizeNfc("e\u{0301}") === 'é', 'NFC');
assertTrue($normalizer->normalizeWhitespace('full_name', "  Example\t Lead  ") === 'Example Lead', 'name whitespace');
assertTrue($normalizer->normalizeWhitespace('message', " A\r\nB\rC ") === "A\nB\nC", 'message newlines');
assertTrue($normalizer->normalizeEmail('Local@EXAMPLE.TEST') === 'Local@example.test', 'email case');

$validator = new LeadInputValidator($normalizer);
$valid = $validator->validate(validInput(), trusted());
assertTrue($valid->isValid() && $valid->value() instanceof CaptureCommand, 'valid command');
$command = $valid->value();
assertTrue(array_keys($command->submitted()) === ['consent', 'full_name', 'email', 'phone', 'company', 'message', 'resource_id', 'source_path', 'campaign'], 'submitted order');
assertTrue(array_keys($command->trusted()) === ['source', 'form_name', 'locale', 'consent_version'], 'trusted order');
assertTrue(array_keys($command->toArray()) === ['source', 'form_name', 'locale', 'consent', 'full_name', 'email', 'phone', 'company', 'message', 'resource_id', 'source_path', 'campaign'], 'command order');

$multi = $validator->validate([
    'zeta' => 'synthetic',
    'resource_id' => null,
    'company' => '<b>Example</b>',
    'phone' => '+35700000000',
    'email' => 7,
    'message' => null,
    'full_name' => 'Example Lead',
    'alpha' => 'synthetic',
    'consent' => ['granted' => true],
], trusted());
$expectedMulti = [
    ['code' => 'invalid_type', 'field' => 'email'],
    ['code' => 'html_not_allowed', 'field' => 'company'],
    ['code' => 'unknown_field', 'field' => 'alpha'],
    ['code' => 'unknown_field', 'field' => 'zeta'],
    ['code' => 'inquiry_required', 'field' => 'message'],
];
assertTrue($multi->errorsAsArray() === $expectedMulti, 'multi-error oracle');
assertTrue($validator->validate([], trusted())->errorsAsArray() === [['code' => 'object_required', 'field' => null]], 'object root');
assertTrue($validator->validate(['full_name' => 'Example Lead'], trusted())->errors()[0] instanceof ValidationError, 'failure object');

$acceptedEmails = [
    'lead@example.test',
    'first.last+tag@example.test',
    str_repeat('a', 64) . '@example.test',
    'lead@' . str_repeat('a', 63) . '.test',
    str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 61),
];
foreach ($acceptedEmails as $emailValue) {
    assertTrue($validator->validate(validInput($emailValue), trusted())->isValid(), 'accepted email boundary');
}
$rejectedEmails = [
    'lead.example.test', 'lead@@example.test', '.lead@example.test', 'lead.@example.test',
    'le..ad@example.test', '"lead"@example.test', 'léad@example.test',
    'lead@exämple.test', 'lead@example_test', 'lead@-example.test',
    'lead@example-.test', 'lead@example..test', str_repeat('a', 65) . '@example.test',
    'lead@' . str_repeat('a', 64) . '.test',
];
foreach ($rejectedEmails as $emailValue) {
    $result = $validator->validate(validInput($emailValue), trusted());
    assertTrue(
        in_array(['code' => 'invalid_email', 'field' => 'email'], $result->errorsAsArray(), true),
        'rejected email boundary'
    );
}
$tooLongEmail = str_repeat('a', 64) . '@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 62);
assertTrue(
    in_array(['code' => 'too_long', 'field' => 'email'], $validator->validate(validInput($tooLongEmail), trusted())->errorsAsArray(), true),
    'email maximum priority'
);

$entropyCalls = 0;
$clockCalls = 0;
$recordResult = LeadRecord::fromCommand(
    $command,
    static function (int $length) use (&$entropyCalls): string {
        ++$entropyCalls;
        assertTrue($length === 16, 'entropy length');
        return str_repeat("\x01", 16);
    },
    static function () use (&$clockCalls): DateTimeInterface {
        ++$clockCalls;
        return new DateTime('2026-07-27 13:20:30.123456+03:00');
    }
);
assertTrue($recordResult->isValid() && $recordResult->value() instanceof LeadRecord, 'record result');
assertTrue($entropyCalls === 1 && $clockCalls === 1, 'callable counts');
$record = $recordResult->value();
assertTrue($record->id() === '01010101010101010101010101010101', 'generated ID');
assertTrue($record->capturedAt() === '2026-07-27T10:20:30.123456Z', 'UTC timestamp');
assertTrue($record->status() === 'new', 'record status');
assertTrue(array_keys($record->toArray()) === [
    'schema_version', 'id', 'created_at', 'updated_at', 'status', 'revision',
    'source', 'form_name', 'locale', 'consent', 'idempotency', 'full_name',
    'email', 'phone', 'company', 'message', 'resource_id', 'source_path', 'campaign',
], 'record order');
$serialization = $record->serialize($record);
assertTrue($serialization->isValid() && is_string($serialization->value()), 'serialization result');
assertTrue(str_ends_with($serialization->value(), "\n") && !str_ends_with($serialization->value(), "\n\n"), 'serialization LF');
assertTrue(json_decode($serialization->value(), true, 512, JSON_THROW_ON_ERROR) === $record->toArray(), 'serialization bytes');

$badEntropy = (new LeadIdGenerator())->generate(static fn (int $length): string => 'short');
assertTrue($badEntropy->errorsAsArray() === [['code' => 'invalid_generated_id', 'field' => 'id']], 'entropy failure');
$badClock = LeadRecord::fromCommand($command, static fn (int $length): string => str_repeat('x', 16), static fn (): string => 'bad');
assertTrue($badClock->errorsAsArray() === [['code' => 'invalid_timestamp', 'field' => 'created_at']], 'clock failure');

$consentMissing = $validator->validate(array_diff_key(validInput(), ['consent' => true]), trusted());
assertTrue($consentMissing->errorsAsArray()[0] === ['code' => 'consent_missing', 'field' => 'consent'], 'consent omitted');
$consentRequired = validInput();
$consentRequired['consent'] = [];
assertTrue($validator->validate($consentRequired, trusted())->errorsAsArray()[0] === ['code' => 'required', 'field' => 'consent.granted'], 'generic required');
$badVersion = trusted();
$badVersion['consent_version'] = 'privacy version';
assertTrue($validator->validate(validInput(), $badVersion)->errorsAsArray() === [['code' => 'invalid_consent_version', 'field' => 'consent.version']], 'consent version grammar');
$multilineVersion = trusted();
$multilineVersion['consent_version'] = "privacy\nversion";
assertTrue($validator->validate(validInput(), $multilineVersion)->errorsAsArray() === [['code' => 'multiline_not_allowed', 'field' => 'consent.version']], 'consent version line policy');
$invalidOnly = validInput();
$invalidOnly['phone'] = null;
$invalidOnly['email'] = 'invalid';
$invalidOnlyErrors = $validator->validate($invalidOnly, trusted())->errorsAsArray();
assertTrue($invalidOnlyErrors === [['code' => 'invalid_email', 'field' => 'email']], 'field error suppresses duplicate contact cross-error');
$formattedPhone = validInput();
$formattedPhone['phone'] = '+357 (000) 000-00';
assertTrue($validator->validate($formattedPhone, trusted())->isValid(), 'phone measured after normalization');
$controlMessage = validInput();
$controlMessage['message'] = "Example\fmessage";
assertTrue(
    in_array(['code' => 'forbidden_control', 'field' => 'message'], $validator->validate($controlMessage, trusted())->errorsAsArray(), true),
    'form feed is rejected, not trimmed'
);
$noncharacterMessage = validInput();
$noncharacterMessage['message'] = "Example\u{2FFFE}";
assertTrue(
    in_array(['code' => 'forbidden_noncharacter', 'field' => 'message'], $validator->validate($noncharacterMessage, trusted())->errorsAsArray(), true),
    'supplementary-plane noncharacter'
);
try {
    $badCampaign = $command->submitted();
    $badCampaign['campaign'] = 'invalid';
    CaptureCommand::fromValidated($badCampaign, trusted());
    throw new RuntimeException('invalid direct campaign factory use accepted');
} catch (InvalidArgumentException) {
}

$recordReflection = new ReflectionClass(LeadRecord::class);
$recordDataProperty = $recordReflection->getProperty('data');
$syntheticError = static function (array $data) use ($recordReflection, $recordDataProperty): array {
    $synthetic = $recordReflection->newInstanceWithoutConstructor();
    $recordDataProperty->setValue($synthetic, $data);
    return $synthetic->serialize($synthetic)->errorsAsArray()[0];
};
$base = $record->toArray();
$mutations = [
    'invalid_schema_version' => static function (array $data): array { $data['schema_version'] = 2; return $data; },
    'invalid_generated_id' => static function (array $data): array { $data['id'] = 'bad'; return $data; },
    'invalid_timestamp' => static function (array $data): array { $data['created_at'] = 'bad'; return $data; },
    'invalid_updated_timestamp' => static function (array $data): array { $data['updated_at'] = 'bad'; return $data; },
    'capture_timestamps_mismatch' => static function (array $data): array { $data['updated_at'] = '2026-07-27T10:20:31.123456Z'; return $data; },
    'invalid_initial_status' => static function (array $data): array { $data['status'] = 'open'; return $data; },
    'invalid_initial_revision' => static function (array $data): array { $data['revision'] = 0; return $data; },
    'invalid_source' => static function (array $data): array { $data['source'] = 'Website!'; return $data; },
    'invalid_form_name' => static function (array $data): array { $data['form_name'] = 'Contact Form'; return $data; },
    'invalid_locale' => static function (array $data): array { $data['locale'] = '!'; return $data; },
    'consent_version_missing' => static function (array $data): array { unset($data['consent']['version']); return $data; },
    'invalid_consent_version' => static function (array $data): array { $data['consent']['version'] = 'privacy version'; return $data; },
    'consent_timestamp_missing' => static function (array $data): array { unset($data['consent']['captured_at']); return $data; },
    'invalid_consent_timestamp' => static function (array $data): array { $data['consent']['captured_at'] = 'bad'; return $data; },
    'invalid_idempotency' => static function (array $data): array { $data['idempotency']['extra'] = null; return $data; },
    'invalid_idempotency_key_version' => static function (array $data): array { $data['idempotency']['key_version'] = 0; return $data; },
    'invalid_idempotency_key_hash' => static function (array $data): array { $data['idempotency']['key_hash'] = 'bad'; return $data; },
    'invalid_payload_fingerprint' => static function (array $data): array { $data['idempotency']['payload_fingerprint'] = 'bad'; return $data; },
    'canonical_serialization_failed' => static function (array $data): array { $data['message'] = "\xC3\x28"; return $data; },
    'canonical_record_too_large' => static function (array $data): array { $data['message'] = str_repeat('x', 33000); return $data; },
];
foreach ($mutations as $expectedCode => $mutation) {
    assertTrue($syntheticError($mutation($base))['code'] === $expectedCode, 'synthetic oracle ' . $expectedCode);
}

printf("PASS_PHASE_3A_AUTOLOAD\n");
printf("PASS_PHASE_3A_SCHEMA\n");
printf("PASS_PHASE_3A_VALIDATION\n");
printf("PASS_PHASE_3A_UNICODE\n");
