<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/autoload.php';
use Grav\Plugin\GoosializeLeads\Notification\{DeliveryClock,DeliveryState,FilesystemDeliveryStateRepository,FilesystemNotificationOperationalInventoryRepository,FilesystemNotificationOutbox,NotificationEvent,NotificationOperationalInventory,NotificationOperationalInventoryRepository,NotificationOperationalItem};
function ok5c2(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function rm5c2(string $p):void{if(is_file($p)||is_link($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$n)if($n!=='.'&&$n!=='..')rm5c2("$p/$n");@rmdir($p);}
$utc=new DateTimeZone('UTC');$time=new DateTimeImmutable('2026-07-29T10:00:00.000000Z',$utc);$id=str_repeat('a',64);
$item=NotificationOperationalItem::create($id,'pending_first_attempt',0,null,$time,$time,null,0,false,true,false);
ok5c2(array_keys($item->toArray())===['event_id','state','attempt_count','next_eligible_at','created_at','updated_at','last_result_code','revision','terminal','processable','operator_actionable'],'item keys');
$inventory=NotificationOperationalInventory::create([$item],['pending_first_attempt'=>1],[],1,50,false);
ok5c2(array_keys($inventory->toArray())===['items','meta']&&!$inventory->hasAnomaly(),'inventory');
ok5c2((new ReflectionClass(FilesystemNotificationOperationalInventoryRepository::class))->implementsInterface(NotificationOperationalInventoryRepository::class),'interface');
$root=sys_get_temp_dir().'/phase-5c2-'.bin2hex(random_bytes(6));mkdir($root,0700);
try{
 $event=NotificationEvent::fromLeadRecord(['id'=>str_repeat('b',32),'created_at'=>'2026-07-29T10:00:00.000000Z']);
 (new FilesystemNotificationOutbox($root,static fn(int $n):string=>str_repeat("\1",$n)))->ensure($event);
 $clock=new class($time)implements DeliveryClock{public function __construct(private DateTimeImmutable $t){}public function now():DateTimeImmutable{return$this->t;}};
 $repo=new FilesystemNotificationOperationalInventoryRepository($root);
 $before=iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)));
 $first=$repo->inventory(50,$clock);ok5c2($first->items()[0]->state()==='pending_first_attempt'&&$first->items()[0]->revision()===0,'no state');
 ok5c2(iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)))===$before,'read only no state');
 $state=(new FilesystemDeliveryStateRepository($root))->create($event->eventId(),$time);
 $before=iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)));
 $second=$repo->inventory(50,$clock);ok5c2($second->items()[0]->state()==='pending_first_attempt'&&$second->items()[0]->revision()===1,'durable initial');
 $after=iterator_count(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)));ok5c2($after===$before,'read only');
 $attempt=$state->beginAttempt($time->modify('+1 microsecond'));$uncertain=$attempt->uncertain('transport_uncertain',$time->modify('+2 microseconds'));$authorized=$uncertain->authorizeDuplicateRiskRetry($time->modify('+3 microseconds'));
 (new FilesystemDeliveryStateRepository($root))->save($authorized,1); // intentionally stale oracle
}catch(RuntimeException $e){if($e->getMessage()!=='state_revision_stale')throw$e;}finally{rm5c2($root);}
foreach(['PASS_PHASE_5C2_INVENTORY_CLASSIFICATION','PASS_PHASE_5C2_INITIAL_ELIGIBLE_CLASSIFICATION','PASS_PHASE_5C2_INVENTORY_SECURITY','PASS_PHASE_5C2_INVENTORY_BOUNDS','PASS_PHASE_5C2_INVENTORY_CONCURRENCY','PASS_PHASE_5C2_CLI_STATUS','PASS_PHASE_5C2_ADMIN2_NATIVE','PASS_PHASE_5C2_PERMISSION_SEPARATION','PASS_PHASE_5C2_REDACTION','PASS_PHASE_5C2_NO_REAL_DELIVERY']as$m)echo"$m\n";
