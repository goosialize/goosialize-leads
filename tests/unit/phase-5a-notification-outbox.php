<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;
use Grav\Plugin\GoosializeLeads\Application\LeadPersistenceCoordinator;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemNotificationOutbox;
use Grav\Plugin\GoosializeLeads\Notification\NotificationEnqueueResult;
use Grav\Plugin\GoosializeLeads\Notification\NotificationEvent;
use Grav\Plugin\GoosializeLeads\Notification\NotificationOutbox;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\LeadRepository;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;

function phase5aCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase5aRemove(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') phase5aRemove($path . '/' . $entry);
    }
    @rmdir($path);
}

$record = [
    'id' => '00000000000000000000000000000000',
    'created_at' => '2026-07-27T10:20:30.123456Z',
];
$event = NotificationEvent::fromLeadRecord($record);
if (($argv[1] ?? null) === '--concurrent') {
    $concurrentRoot = $argv[2] ?? '';
    $outbox = new FilesystemNotificationOutbox(
        $concurrentRoot,
        static fn (int $length): string => random_bytes($length)
    );
    echo $outbox->ensure($event)->status(), "\n";
    exit;
}
$expectedId = hash('sha256', "goosialize-leads\0lead.accepted\0" . $record['id']);
$expected = '{"schema_version":1,"event_id":"' . $expectedId
    . '","event_type":"lead.accepted","lead_id":"00000000000000000000000000000000"'
    . ',"created_at":"2026-07-27T10:20:30.123456Z"}' . "\n";
phase5aCheck($event->eventId() === $expectedId, 'event ID');
phase5aCheck($event->canonicalBytes() === $expected, 'canonical event bytes');
phase5aCheck(array_keys($event->toArray()) === ['schema_version', 'event_id', 'event_type', 'lead_id', 'created_at'], 'event order');
phase5aCheck(str_ends_with($event->canonicalBytes(), "\n") && !str_ends_with($event->canonicalBytes(), "\n\n"), 'final newline');
phase5aCheck(strlen($event->canonicalBytes()) <= 512, 'event limit');
foreach ([[], ['id'=>'x','created_at'=>$record['created_at']], ['id'=>$record['id'],'created_at'=>'bad']] as $invalid) {
    try {
        NotificationEvent::fromLeadRecord($invalid);
        throw new RuntimeException('invalid event accepted');
    } catch (InvalidArgumentException) {
    }
}

$apis = [
    NotificationEvent::class => ['fromLeadRecord', 'eventId', 'leadId', 'createdAt', 'canonicalBytes', 'toArray'],
    NotificationEnqueueResult::class => ['created', 'existing', 'failure', 'status', 'code', 'isSuccess'],
    FilesystemNotificationOutbox::class => ['__construct', 'ensure'],
];
foreach ($apis as $class => $expectedMethods) {
    $reflection = new ReflectionClass($class);
    phase5aCheck($reflection->isFinal(), 'class finality');
    $methods = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $class) $methods[] = $method->getName();
    }
    phase5aCheck($methods === $expectedMethods, 'public API ' . $class);
}
$interface = new ReflectionClass(NotificationOutbox::class);
phase5aCheck($interface->isInterface(), 'outbox interface');
phase5aCheck(array_map(fn (ReflectionMethod $m): string => $m->getName(), $interface->getMethods()) === ['ensure'], 'interface API');
$outboxSource = file_get_contents(dirname(__DIR__, 2) . '/classes/Notification/FilesystemNotificationOutbox.php');
phase5aCheck(is_string($outboxSource), 'outbox source');
$compareStart = strpos($outboxSource, 'private function compareExisting');
$preOpenLstat = strpos($outboxSource, '$beforeStat = @lstat($path)', $compareStart);
$open = strpos($outboxSource, "\$handle = @fopen(\$path, 'rb')", $compareStart);
$postOpenFstat = strpos($outboxSource, '$handleStat = @fstat($handle)', $compareStart);
phase5aCheck(
    is_int($compareStart)
    && is_int($preOpenLstat)
    && is_int($open)
    && is_int($postOpenFstat)
    && $compareStart < $preOpenLstat
    && $preOpenLstat < $open
    && $open < $postOpenFstat,
    'pre-open lstat/post-open fstat ordering'
);
phase5aCheck(
    str_contains($outboxSource, "\$beforeStat['dev'] !== \$pathStat['dev']")
    && str_contains($outboxSource, "\$beforeStat['ino'] !== \$pathStat['ino']")
    && str_contains($outboxSource, "\$pathStat['dev'] !== \$handleStat['dev']")
    && str_contains($outboxSource, "\$pathStat['ino'] !== \$handleStat['ino']"),
    'post-open inode identity oracle'
);
foreach (['outbox_unavailable','outbox_security_invalid','outbox_capacity_exceeded','outbox_event_conflict'] as $code) {
    $result = NotificationEnqueueResult::failure($code);
    phase5aCheck(!$result->isSuccess() && $result->status() === 'failure' && $result->code() === $code, 'result code');
}

$root = sys_get_temp_dir() . '/phase-5a-unit-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
try {
    $outbox = new FilesystemNotificationOutbox($root, static fn (int $length): string => substr(str_repeat("\x00", 8), 0, $length));
    phase5aCheck($outbox->ensure($event)->status() === 'created', 'created event');
    $path = $root . '/goosialize-leads/v1/notification-outbox/events/' . substr($expectedId, 0, 2) . '/' . $expectedId . '.json';
    phase5aCheck(file_get_contents($path) === $expected, 'persisted bytes');
    phase5aCheck((fileperms($path) & 0777) === 0600, 'event mode');
    phase5aCheck($outbox->ensure($event)->status() === 'existing', 'matching event');
    phase5aCheck(glob(dirname($path) . '/*.lock') === [], 'lock cleanup');
    phase5aCheck(glob(dirname($path) . '/.event-*.tmp') === [], 'temporary cleanup');

    file_put_contents($path, "{}\n");
    chmod($path, 0600);
    phase5aCheck($outbox->ensure($event)->code() === 'outbox_event_conflict', 'conflicting event');
    phase5aCheck(file_get_contents($path) === "{}\n", 'no overwrite');
    unlink($path);

    $failed = new FilesystemNotificationOutbox(
        $root,
        static fn (int $length): string => substr(str_repeat("\x01", 8), 0, $length),
        static fn ($handle): bool => false
    );
    phase5aCheck($failed->ensure($event)->code() === 'outbox_unavailable', 'sync failure');
    phase5aCheck(!file_exists($path), 'failed publication absent');
    phase5aCheck(glob(dirname($path) . '/.event-*.tmp') === [], 'failure cleanup');

    $publication = new FilesystemNotificationOutbox(
        $root,
        static fn (int $length): string => substr(str_repeat("\x02", 8), 0, $length),
        null,
        static fn (string $temporary, string $final): bool => false
    );
    phase5aCheck($publication->ensure($event)->code() === 'outbox_unavailable', 'publication failure');
    phase5aCheck(!file_exists($path), 'publication failure absent');

    $temporaryCollision = dirname($path) . '/.event-' . $expectedId . '-' . str_repeat('03', 8) . '.tmp';
    file_put_contents($temporaryCollision, 'sentinel');
    chmod($temporaryCollision, 0600);
    $temporaryFailure = new FilesystemNotificationOutbox(
        $root,
        static fn (int $length): string => substr(str_repeat("\x03", 8), 0, $length)
    );
    phase5aCheck($temporaryFailure->ensure($event)->code() === 'outbox_unavailable', 'temporary collision');
    phase5aCheck(file_get_contents($temporaryCollision) === 'sentinel', 'foreign temporary preserved');
    unlink($temporaryCollision);

    $outside = $root . '/outside-event';
    file_put_contents($outside, $expected);
    chmod($outside, 0600);
    symlink($outside, $path);
    phase5aCheck($outbox->ensure($event)->code() === 'outbox_security_invalid', 'event symlink');
    phase5aCheck(file_get_contents($outside) === $expected, 'symlink target unchanged');
    unlink($path);

    $created = $outbox->ensure($event);
    phase5aCheck($created->status() === 'created', 'recovery after failure');
    file_put_contents($path, str_repeat('x', 513));
    chmod($path, 0600);
    phase5aCheck($outbox->ensure($event)->code() === 'outbox_event_conflict', '513-byte overflow');
} finally {
    phase5aRemove($root);
}

$keyRing = new IdempotencyKeyRing(1, [1 => base64_encode(str_repeat('K', 32))]);
$repository = new class implements LeadRepository {
    public string $mode = 'created';
    public function persist(PersistenceRequest $request): PersistenceResult
    {
        return match ($this->mode) {
            'replayed' => PersistenceResult::replayed($request->record()->toArray()),
            'conflict' => PersistenceResult::failure('idempotency_conflict'),
            'failure' => PersistenceResult::failure('storage_unavailable'),
            default => PersistenceResult::created($request->record()),
        };
    }
};
$spy = new class implements NotificationOutbox {
    public int $calls = 0;
    public bool $fail = false;
    public function ensure(NotificationEvent $event): NotificationEnqueueResult
    {
        $this->calls++;
        return $this->fail
            ? NotificationEnqueueResult::failure('outbox_unavailable')
            : NotificationEnqueueResult::created();
    }
};
$codes = [];
$service = new LeadCaptureService(
    new LeadPersistenceCoordinator(new LeadInputValidator(new LeadNormalizer()), $repository, $keyRing),
    $keyRing,
    $spy,
    static function (string $code) use (&$codes): void { $codes[] = $code; }
);
$submitted = [
    'consent'=>['granted'=>true], 'full_name'=>'Synthetic Lead', 'email'=>'lead@example.test',
    'phone'=>'+35722000000', 'company'=>null, 'message'=>'Phase 5A fixture',
    'resource_id'=>null, 'source_path'=>null, 'campaign'=>null,
];
$trusted = ['source'=>'api','form_name'=>'goosialize-leads-capture','locale'=>null,'consent_version'=>'privacy-v1'];
$entropy = static fn (int $length): string => str_repeat("\x00", $length);
$clock = static fn (): DateTimeInterface => new DateTimeImmutable('2026-07-27T10:20:30.123456Z');
phase5aCheck($service->captureApi($submitted, $trusted, 'synthetic-key-01', $entropy, $clock)->isSuccess(), 'created capture');
phase5aCheck($spy->calls === 1, 'created ensure count');
$repository->mode = 'replayed';
phase5aCheck($service->captureApi($submitted, $trusted, 'synthetic-key-01', $entropy, $clock)->replayed(), 'replayed capture');
phase5aCheck($spy->calls === 2, 'replayed ensure count');
$repository->mode = 'conflict';
phase5aCheck($service->captureApi($submitted, $trusted, 'synthetic-key-01', $entropy, $clock)->code() === 'idempotency_conflict', 'conflict capture');
phase5aCheck($spy->calls === 2, 'conflict did not ensure');
$repository->mode = 'created';
$spy->fail = true;
$result = $service->captureApi($submitted, $trusted, 'synthetic-key-02', $entropy, $clock);
phase5aCheck($result->isSuccess() && $codes === ['outbox_unavailable'], 'failure policy');

echo "PASS_PHASE_5A_EVENT_SCHEMA\n";
echo "PASS_PHASE_5A_OUTBOX_ATOMIC\n";
echo "PASS_PHASE_5A_OUTBOX_IDEMPOTENCY\n";
echo "PASS_PHASE_5A_REPLAY_REPAIR\n";
echo "PASS_PHASE_5A_FAILURE_POLICY\n";
echo "PASS_PHASE_5A_NO_DELIVERY\n";
echo "PASS_PHASE_5A_CAPTURE_PARITY\n";
