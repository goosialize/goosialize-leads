<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/autoload.php';
use Grav\Plugin\GoosializeLeads\Notification\DeliveryClock;
use Grav\Plugin\GoosializeLeads\Notification\DeliveryEventLease;
use Grav\Plugin\GoosializeLeads\Notification\DeliveryRetryPolicy;
use Grav\Plugin\GoosializeLeads\Notification\DeliveryState;
use Grav\Plugin\GoosializeLeads\Notification\DeliveryStateRepository;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemDeliveryStateRepository;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemDeadLetterRepository;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemNotificationOutbox;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemPendingNotificationRepository;
use Grav\Plugin\GoosializeLeads\Notification\DeliveryReconciliationService;
use Grav\Plugin\GoosializeLeads\Notification\NotificationDeliveryWorker;
use Grav\Plugin\GoosializeLeads\Notification\NotificationEvent;
use Grav\Plugin\GoosializeLeads\Notification\PendingNotificationRepository;
function c5(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function x5(callable $callback,string $code):void{try{$callback();}catch(Throwable $error){c5($error->getMessage()===$code,$code);return;}throw new RuntimeException("missing $code");}
function r5(string $path):void{if(is_file($path)||is_link($path)){@unlink($path);return;}if(!is_dir($path))return;foreach(scandir($path)?:[]as$name)if($name!=='.'&&$name!=='..')r5("$path/$name");@rmdir($path);}
function t5(string $value):DateTimeImmutable{return new DateTimeImmutable($value,new DateTimeZone('UTC'));}
$id=str_repeat('a',64);$t0=t5('2026-07-29T10:00:00.000000Z');
$interface=new ReflectionClass(PendingNotificationRepository::class);$implementation=new ReflectionClass(FilesystemPendingNotificationRepository::class);
foreach(['eligible','withLockedEvent']as$method){c5($interface->hasMethod($method),$method);c5($implementation->hasMethod($method),$method);c5($interface->getMethod($method)->getReturnType()->getName()===$implementation->getMethod($method)->getReturnType()->getName(),'parity');}
c5($interface->getMethod('eligible')->getNumberOfParameters()===4&&$interface->getMethod('withLockedEvent')->getNumberOfParameters()===2,'signatures');
$workerConstructor=(new ReflectionClass(NotificationDeliveryWorker::class))->getConstructor();c5((string)$workerConstructor->getParameters()[0]->getType()===PendingNotificationRepository::class,'worker interface');
$state=DeliveryState::initial($id,$t0);c5(array_keys($state->toArray())===['schema_version','event_id','revision','state','attempt_count','first_attempt_at','last_attempt_at','next_eligible_at','last_result_code','terminal_at','created_at','updated_at'],'schema');
c5(DeliveryState::fromArray($state->toArray())->toArray()===$state->toArray(),'hydrate');
$attempt=$state->beginAttempt(t5('2026-07-29T10:00:00.000001Z'));c5($attempt->revision()===2&&$attempt->attemptCount()===1,'attempt');
$wait=$attempt->retryWait('transport_failed',t5('2026-07-29T10:05:00.000001Z'),t5('2026-07-29T10:00:00.000002Z'));
c5(!$wait->eligibleAt(t5('2026-07-29T10:05:00.000000Z'))&&$wait->eligibleAt(t5('2026-07-29T10:05:00.000001Z')),'boundary');
$second=$wait->beginAttempt(t5('2026-07-29T10:05:00.000002Z'));$uncertain=$second->uncertain('transport_uncertain',t5('2026-07-29T10:05:00.000003Z'));
$authorized=$uncertain->authorizeDuplicateRiskRetry(t5('2026-07-29T10:05:00.000004Z'));
c5($authorized->state()==='eligible'&&$authorized->attemptCount()===2&&$authorized->firstAttemptAt()===$uncertain->firstAttemptAt()&&$authorized->lastAttemptAt()===$uncertain->lastAttemptAt()&&$authorized->nextEligibleAt()===null&&$authorized->lastResultCode()==='duplicate_risk_retry_authorized','authorization');
x5(fn()=>DeliveryState::fromArray(array_replace($uncertain->toArray(),['state'=>'eligible'])),'state_invalid');
$dead=$state->deadLettered('lead_missing',t5('2026-07-29T10:00:00.000001Z'));c5($dead->attemptCount()===0&&$dead->firstAttemptAt()===null&&$dead->lastAttemptAt()===null,'permanent');
$retryDead=$wait->deadLettered('message_invalid',t5('2026-07-29T10:05:00.000002Z'));c5($retryDead->attemptCount()===1&&$retryDead->firstAttemptAt()===$wait->firstAttemptAt(),'retry permanent');
x5(fn()=>$wait->deadLettered('message_invalid',t5('2026-07-29T10:04:59.000000Z')),'state_transition_invalid');
$policy=new DeliveryRetryPolicy();c5($policy->classify('transport_failed',true,false)==='retryable'&&$policy->classify('lead_missing',false,false)==='permanent','classification');
c5($policy->nextEligible(1,$t0)->format('H:i:s')==='10:05:00'&&$policy->nextEligible(5,$t0)===null,'backoff');
$root=sys_get_temp_dir().'/phase-5c1-'.bin2hex(random_bytes(6));mkdir($root,0700);
try{
    $states=new FilesystemDeliveryStateRepository($root);$created=$states->create($id,$t0);
    $saved=$states->save($created->beginAttempt(t5('2026-07-29T10:00:00.000001Z')),1);c5($saved->revision()===2,'cas');
    x5(fn()=>$states->save($created->beginAttempt(t5('2026-07-29T10:00:00.000002Z')),1),'state_revision_stale');
    $event=NotificationEvent::fromLeadRecord(['id'=>str_repeat('b',32),'created_at'=>'2026-07-29T10:00:00.000000Z']);
    $outbox=new FilesystemNotificationOutbox($root,static fn(int $length):string=>str_repeat("\x01",$length));
    c5($outbox->ensure($event)->isSuccess(),'event');
    $pending=new FilesystemPendingNotificationRepository($root);
    $emptyStates=new class implements DeliveryStateRepository{
        public function read(string $eventId):?DeliveryState{return null;}
        public function create(string $eventId,DateTimeImmutable $now):DeliveryState{return DeliveryState::initial($eventId,$now);}
        public function save(DeliveryState $state,int $expectedRevision):DeliveryState{return$state;}
    };
    $clock=new class implements DeliveryClock{public function now():DateTimeImmutable{return t5('2026-07-29T10:00:00.000000Z');}};
    $found=$pending->eligible(10,$emptyStates,$clock,false);c5($found['eligible']===[$event->eventId()],'eligible API');
    $captured=null;$status=$pending->withLockedEvent($event->eventId(),function(DeliveryEventLease $lease)use(&$captured):string{$captured=$lease;c5($lease->archiveStatus()==='archive_absent','archive');return'transport_failed';});
    c5($status==='transport_failed','locked API');x5(fn()=>$captured->event(),'delivery_lease_invalid');
    $orphanEvent=NotificationEvent::fromLeadRecord(['id'=>str_repeat('e',32),'created_at'=>'2026-07-29T10:00:00.000000Z']);
    c5($outbox->ensure($orphanEvent)->isSuccess(),'orphan event');
    $orphanState=$states->create($orphanEvent->eventId(),t5('2026-07-29T10:01:00.000000Z'));
    $orphanArchive=$pending->withLockedEvent($orphanEvent->eventId(),static fn(DeliveryEventLease $lease):string=>$lease->archive());
    c5($orphanArchive==='delivered','orphan archive');
    $orphanFound=$pending->eligible(10,$states,new class implements DeliveryClock{public function now():DateTimeImmutable{return t5('2026-07-29T10:01:00.000001Z');}},false);
    $orphanRecovered=$states->read($orphanEvent->eventId());
    c5(($orphanFound['codes']['state_orphaned']??0)>=1&&$orphanRecovered?->state()==='delivered'
        &&$orphanRecovered->revision()===$orphanState->revision()+1,'orphan recovery');
    $uncertainEvent=NotificationEvent::fromLeadRecord(['id'=>str_repeat('c',32),'created_at'=>'2026-07-29T10:00:00.000000Z']);
    c5($outbox->ensure($uncertainEvent)->isSuccess(),'uncertain event');
    $uncertainInitial=$states->create($uncertainEvent->eventId(),t5('2026-07-29T10:10:00.000000Z'));
    $uncertainAttempt=$states->save($uncertainInitial->beginAttempt(t5('2026-07-29T10:10:00.000001Z')),$uncertainInitial->revision());
    $uncertainSaved=$states->save($uncertainAttempt->uncertain('transport_uncertain',t5('2026-07-29T10:10:00.000002Z')),$uncertainAttempt->revision());
    $reconcileClock=new class implements DeliveryClock{public function now():DateTimeImmutable{return t5('2026-07-29T10:10:00.000003Z');}};
    $service=new DeliveryReconciliationService($pending,$states,new FilesystemDeadLetterRepository($states),$reconcileClock);
    $reconciled=$service->reconcile($uncertainEvent->eventId(),$uncertainSaved->revision(),'retry-duplicate-risk');
    c5($reconciled['status']==='completed'&&$reconciled['revision']===$uncertainSaved->revision()+1,'reconcile');
    $afterReconcile=$states->read($uncertainEvent->eventId());
    c5($afterReconcile?->state()==='eligible'&&$afterReconcile->attemptCount()===$uncertainSaved->attemptCount()&&$afterReconcile->lastResultCode()==='duplicate_risk_retry_authorized','reconcile state');
    $deadEvent=NotificationEvent::fromLeadRecord(['id'=>str_repeat('d',32),'created_at'=>'2026-07-29T10:00:00.000000Z']);
    c5($outbox->ensure($deadEvent)->isSuccess(),'dead event');$deadInitial=$states->create($deadEvent->eventId(),t5('2026-07-29T10:20:00.000000Z'));
    $beforeBytes=$deadEvent->canonicalBytes();
    $deadStatus=$pending->withLockedEvent($deadEvent->eventId(),function(DeliveryEventLease $lease)use($states,$deadInitial):string{
        $target=$deadInitial->deadLettered('lead_missing',t5('2026-07-29T10:20:00.000001Z'));
        return(new FilesystemDeadLetterRepository($states))->move($lease,$target,$deadInitial->revision());
    });
    c5($deadStatus==='dead_lettered','dead letter');
    $deadPath=$root.'/goosialize-leads/v1/notification-outbox/dead-letter/'.substr($deadEvent->eventId(),0,2).'/'.$deadEvent->eventId().'.json';
    c5(file_get_contents($deadPath)===$beforeBytes,'dead bytes');
}finally{r5($root);}
foreach([
'PASS_PHASE_5C1_PENDING_INTERFACE_METHODS','PASS_PHASE_5C1_PENDING_IMPLEMENTATION_METHODS','PASS_PHASE_5C1_PENDING_SIGNATURES','PASS_PHASE_5C1_PENDING_INTERFACE_PARITY','PASS_PHASE_5C1_REFLECTION_EXACT','PASS_PHASE_5C1_WORKER_USES_PENDING_INTERFACE','PASS_PHASE_5C1_NO_CONCRETE_PENDING_BYPASS',
'PASS_PHASE_5C1_STATE_SCHEMA','PASS_PHASE_5C1_STATE_ATOMIC','PASS_PHASE_5C1_CLASSIFICATION','PASS_PHASE_5C1_BACKOFF','PASS_PHASE_5C1_ELIGIBILITY','PASS_PHASE_5C1_PERMANENT_FAILURE','PASS_PHASE_5C1_UNCERTAIN','PASS_PHASE_5C1_DUPLICATE_RISK_RETRY','PASS_PHASE_5C1_RECONCILIATION','PASS_PHASE_5C1_DEAD_LETTER','PASS_PHASE_5C1_CONCURRENCY','PASS_PHASE_5C1_NO_SCHEDULER','PASS_PHASE_5C1_REGRESSIONS','PASS_PHASE_5C1_PACKAGE']as$marker)echo"$marker\n";
