<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use Grav\Plugin\GoosializeLeads\Notification\FilesystemNotificationOutbox;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemPendingNotificationRepository;
use Grav\Plugin\GoosializeLeads\Notification\GravEmailNotificationTransport;
use Grav\Plugin\GoosializeLeads\Notification\LeadDeliveryRecordReader;
use Grav\Plugin\GoosializeLeads\Notification\NotificationDeliveryResult;
use Grav\Plugin\GoosializeLeads\Notification\NotificationDeliveryWorker;
use Grav\Plugin\GoosializeLeads\Notification\NotificationEvent;
use Grav\Plugin\GoosializeLeads\Notification\NotificationMessage;
use Grav\Plugin\GoosializeLeads\Notification\NotificationMessageFactory;
use Grav\Plugin\GoosializeLeads\Notification\NotificationTransport;
use Grav\Plugin\GoosializeLeads\Notification\PendingNotificationRepository;

function phase5bCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase5bRemove(string $path): void
{
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') phase5bRemove($path . '/' . $entry);
    }
    @rmdir($path);
}

function phase5bDirectory(string $path): void
{
    if (!is_dir($path)) mkdir($path, 0700, true);
    chmod($path, 0700);
}

/** @return array<string,mixed> */
function phase5bRecord(string $id = '00000000000000000000000000000000'): array
{
    $created = '2026-07-27T10:20:30.123456Z';
    return [
        'schema_version'=>1, 'id'=>$id, 'created_at'=>$created, 'updated_at'=>$created,
        'status'=>'new', 'revision'=>1, 'source'=>'api', 'form_name'=>'goosialize-leads-capture',
        'locale'=>null, 'consent'=>['granted'=>true,'version'=>'privacy-v1','captured_at'=>$created],
        'idempotency'=>['key_version'=>null,'key_hash'=>null,'payload_fingerprint'=>null],
        'full_name'=>'Synthetic Lead', 'email'=>'lead@example.test', 'phone'=>'+35722000000',
        'company'=>'Synthetic Company', 'message'=>"First line\r\nSecond\tline",
        'resource_id'=>null, 'source_path'=>null, 'campaign'=>null,
    ];
}

function phase5bWriteRecord(string $root, array $record): string
{
    $path = $root . '/goosialize-leads/v1/records/2026/07/' . $record['id'] . '.json';
    phase5bDirectory(dirname($path));
    file_put_contents($path, json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    chmod($path, 0600);
    return $path;
}

if (($argv[1] ?? null) === '--concurrent-worker') {
    [$root, $eventId, $barrier, $resultPath, $calls] = array_slice($argv, 2, 5);
    while (!file_exists($barrier)) usleep(1000);
    $status = (new FilesystemPendingNotificationRepository($root))->process(
        $eventId,
        static function () use ($calls): string {
            file_put_contents($calls, "send\n", FILE_APPEND | LOCK_EX);
            usleep(300000);
            return 'transport_success';
        }
    );
    file_put_contents($resultPath, $status);
    exit(0);
}

$api = [
    NotificationMessage::class => ['create','subject','body'],
    NotificationMessageFactory::class => ['create'],
    NotificationDeliveryResult::class => ['create','discovered','delivered','contended','failed','uncertain','codes','isComplete'],
    FilesystemPendingNotificationRepository::class => ['__construct','pending','process'],
    LeadDeliveryRecordReader::class => ['__construct','read'],
    NotificationDeliveryWorker::class => ['__construct','deliver'],
    GravEmailNotificationTransport::class => ['__construct','send'],
];
foreach ($api as $class => $methods) {
    $reflection = new ReflectionClass($class);
    phase5bCheck($reflection->isFinal(), 'finality ' . $class);
    $actual = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $class) $actual[] = $method->getName();
    }
    phase5bCheck($actual === $methods, 'API ' . $class);
}
foreach ([NotificationTransport::class=>['send'], PendingNotificationRepository::class=>['pending','process']] as $class=>$methods) {
    $reflection = new ReflectionClass($class);
    phase5bCheck($reflection->isInterface(), 'interface ' . $class);
    phase5bCheck(array_map(static fn (ReflectionMethod $m): string => $m->getName(), $reflection->getMethods()) === $methods, 'interface API');
}

$record = phase5bRecord();
$factory = new NotificationMessageFactory();
$message = $factory->create($record);
phase5bCheck($message->subject() === 'New Lead 00000000000000000000000000000000', 'subject');
$expectedBody = "Lead ID: 00000000000000000000000000000000\n"
    . "Created (UTC): 2026-07-27T10:20:30.123456Z\nName: Synthetic Lead\nEmail: lead@example.test\n"
    . "Phone: +35722000000\nCompany: Synthetic Company\nSource: api\nForm: goosialize-leads-capture\n"
    . "Message: First line\n  Second line\n";
phase5bCheck($message->body() === $expectedBody, 'body');
phase5bCheck(!str_contains($message->body(), "\r") && str_ends_with($message->body(), "\n"), 'line endings');
phase5bCheck(NotificationMessage::create(str_repeat('s', 41), str_repeat('b', 12287) . "\n")->body() !== '', 'exact size boundaries');
foreach ([[str_repeat('s', 42), "x\n"], ["Bad\nSubject", "x\n"], ['x', str_repeat('x', 12289) . "\n"]] as [$subject, $body]) {
    try { NotificationMessage::create($subject, $body); throw new RuntimeException('invalid message accepted'); }
    catch (InvalidArgumentException) {}
}
$unicode = $record;
$unicode['full_name'] = 'Δοκιμή';
phase5bCheck(str_contains($factory->create($unicode)->body(), 'Name: Δοκιμή'), 'Unicode body');
$control = $record;
$control['message'] = "bad\x01";
try { $factory->create($control); throw new RuntimeException('control accepted'); }
catch (InvalidArgumentException) {}
foreach (["folded\nname", "folded\r\nname", "folded\u{2028}name", "folded\u{2029}name"] as $injection) {
    $headerLike = $record;
    $headerLike['full_name'] = $injection;
    try { $factory->create($headerLike); throw new RuntimeException('scalar line injection accepted'); }
    catch (InvalidArgumentException) {}
}

$root = sys_get_temp_dir() . '/phase-5b-unit-' . bin2hex(random_bytes(8));
phase5bDirectory($root);
try {
    $recordPath = phase5bWriteRecord($root, $record);
    $event = NotificationEvent::fromLeadRecord($record);
    $outbox = new FilesystemNotificationOutbox($root, static fn (int $length): string => str_repeat("\0", $length));
    phase5bCheck($outbox->ensure($event)->isSuccess(), 'event creation');
    $repository = new FilesystemPendingNotificationRepository($root);
    phase5bCheck($repository->pending(10) === [$event->eventId()], 'discovery');
    $read = (new LeadDeliveryRecordReader($root))->read($record['id']);
    phase5bCheck($read === $record, 'Lead read');

    $fake = new class implements NotificationTransport {
        public int $calls = 0;
        public string $mode = 'success';
        public function send(NotificationMessage $message): bool
        {
            $this->calls++;
            if ($this->mode === 'throw') throw new RuntimeException('synthetic');
            return $this->mode === 'success';
        }
    };
    $worker = new NotificationDeliveryWorker($repository, new LeadDeliveryRecordReader($root), $factory, $fake);

    $lockPath = $root . '/goosialize-leads/v1/notification-outbox/delivery-locks/'
        . substr($event->eventId(), 0, 2) . '/' . $event->eventId() . '.lock';
    phase5bDirectory(dirname($lockPath));
    $lock = fopen($lockPath, 'c+b');
    chmod($lockPath, 0600);
    flock($lock, LOCK_EX | LOCK_NB);
    $contended = $worker->deliver(10);
    phase5bCheck($contended->contended() === 1 && $fake->calls === 0, 'lock contention');
    flock($lock, LOCK_UN);
    fclose($lock);

    $pendingPath = $root . '/goosialize-leads/v1/notification-outbox/events/'
        . substr($event->eventId(), 0, 2) . '/' . $event->eventId() . '.json';
    $eventBytes = file_get_contents($pendingPath);
    $success = $worker->deliver(10);
    $archivePath = $root . '/goosialize-leads/v1/notification-outbox/sent/'
        . substr($event->eventId(), 0, 2) . '/' . $event->eventId() . '.json';
    phase5bCheck($success->isComplete() && $success->delivered() === 1 && $fake->calls === 1, 'success');
    phase5bCheck(!file_exists($pendingPath) && file_get_contents($archivePath) === $eventBytes, 'archive');

    link($archivePath, $pendingPath);
    phase5bCheck($worker->deliver(10)->delivered() === 1 && $fake->calls === 1 && !file_exists($pendingPath), 'matching recovery');
    unlink($archivePath);
    phase5bCheck($outbox->ensure($event)->isSuccess(), 'failure event');
    $fake->mode = 'negative';
    phase5bCheck($worker->deliver(10)->codes() === ['transport_failed'=>1], 'negative transport');
    phase5bCheck(file_exists($pendingPath), 'negative preserved');
    $fake->mode = 'throw';
    phase5bCheck($worker->deliver(10)->codes() === ['transport_failed'=>1], 'throw transport');
    phase5bCheck(file_exists($pendingPath), 'throw preserved');

    chmod($recordPath, 0644);
    phase5bCheck($worker->deliver(10)->codes() === ['lead_invalid'=>1], 'invalid Lead mode');
    chmod($recordPath, 0600);
    $outside = $root . '/outside';
    file_put_contents($outside, file_get_contents($recordPath));
    chmod($outside, 0600);
    unlink($recordPath);
    symlink($outside, $recordPath);
    phase5bCheck($worker->deliver(10)->codes() === ['lead_invalid'=>1], 'Lead symlink');
    unlink($recordPath);
    rename($outside, $recordPath);
    chmod($recordPath, 0600);

    $sentRoot = $root . '/goosialize-leads/v1/notification-outbox/sent';
    phase5bRemove($sentRoot);
    file_put_contents($sentRoot, 'synthetic archive fault');
    chmod($sentRoot, 0600);
    $fake->mode = 'success';
    $uncertain = $worker->deliver(10);
    phase5bCheck($uncertain->uncertain() === 1 && $uncertain->codes() === ['delivery_uncertain'=>1], 'archive uncertainty');
    phase5bCheck(file_exists($pendingPath), 'uncertain pending preserved');
    unlink($sentRoot);

    $outsideEvent = $root . '/outside-event';
    $originalEventBytes = file_get_contents($pendingPath);
    file_put_contents($outsideEvent, $originalEventBytes);
    chmod($outsideEvent, 0600);
    unlink($pendingPath);
    symlink($outsideEvent, $pendingPath);
    phase5bCheck($worker->deliver(10)->codes() === ['event_invalid'=>1], 'event symlink');
    unlink($pendingPath);
    rename($outsideEvent, $pendingPath);
    chmod($pendingPath, 0600);

    $replacement = $root . '/replacement-event';
    file_put_contents($replacement, $originalEventBytes);
    chmod($replacement, 0600);
    $replacementStatus = $repository->process($event->eventId(), static function () use ($pendingPath, $replacement): string {
        unlink($pendingPath);
        rename($replacement, $pendingPath);
        chmod($pendingPath, 0600);
        return 'transport_success';
    });
    phase5bCheck($replacementStatus === 'delivery_uncertain' && file_exists($pendingPath), 'event replacement');

    $barrier = $root . '/concurrent-go';
    $calls = $root . '/concurrent-calls';
    $results = [$root . '/concurrent-result-1', $root . '/concurrent-result-2'];
    $children = [];
    foreach ($results as $resultPath) {
        $process = proc_open(
            [PHP_BINARY, __FILE__, '--concurrent-worker', $root, $event->eventId(), $barrier, $resultPath, $calls],
            [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],
            $pipes
        );
        phase5bCheck(is_resource($process), 'concurrent process');
        $children[] = $process;
    }
    touch($barrier);
    foreach ($children as $process) {
        phase5bCheck(proc_close($process) === 0, 'concurrent child');
    }
    $statuses = [file_get_contents($results[0]), file_get_contents($results[1])];
    sort($statuses, SORT_STRING);
    phase5bCheck($statuses === ['delivered','delivery_contended'], 'concurrent statuses');
    phase5bCheck(file_get_contents($calls) === "send\n", 'single concurrent send');
} finally {
    phase5bRemove($root);
}

echo "PASS_PHASE_5B_COMMAND\n";
echo "PASS_PHASE_5B_DISCOVERY\n";
echo "PASS_PHASE_5B_LOCKING\n";
echo "PASS_PHASE_5B_LEAD_READ\n";
echo "PASS_PHASE_5B_MESSAGE\n";
echo "PASS_PHASE_5B_FAKE_TRANSPORT\n";
echo "PASS_PHASE_5B_ARCHIVE\n";
echo "PASS_PHASE_5B_AT_LEAST_ONCE\n";
echo "PASS_PHASE_5B_NO_REAL_DELIVERY\n";
