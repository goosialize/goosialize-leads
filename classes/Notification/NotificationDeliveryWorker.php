<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class NotificationDeliveryWorker
{
    public function __construct(
        private readonly PendingNotificationRepository $pending,
        private readonly LeadDeliveryRecordReader $records,
        private readonly NotificationMessageFactory $messages,
        private readonly NotificationTransport $transport,
        private readonly ?DeliveryStateRepository $states = null,
        private readonly ?DeliveryClock $clock = null,
        private readonly ?DeliveryRetryPolicy $retryPolicy = null,
        private readonly ?DeadLetterRepository $deadLetters = null
    ) {
    }

    public function deliver(int $limit, bool $retriesOnly = false): NotificationDeliveryResult
    {
        if ($limit < 1 || $limit > 50) {
            throw new \InvalidArgumentException('Invalid delivery limit.');
        }
        $dependencies = array_filter([$this->states,$this->clock,$this->retryPolicy,$this->deadLetters],static fn(mixed $value):bool=>$value!==null);
        if(count($dependencies)!==0&&count($dependencies)!==4)throw new \InvalidArgumentException('retry_configuration_invalid');
        if(count($dependencies)===4)return$this->deliverStateful($limit,$retriesOnly);
        if($retriesOnly)throw new \InvalidArgumentException('retry_configuration_invalid');
        try {
            $ids = $this->pending->pending($limit);
        } catch (\Throwable) {
            return NotificationDeliveryResult::create(1, 0, 0, 0, 0, 1, 0, ['event_invalid' => 1]);
        }
        $delivered = $contended = $failed = $uncertain = 0;
        $codes = [];
        foreach ($ids as $id) {
            try {
                $status = $this->pending->process($id, function (NotificationEvent $event): string {
                    try {
                        $record = $this->records->read($event->leadId());
                    } catch (\RuntimeException $error) {
                        return in_array($error->getMessage(), ['lead_missing', 'lead_invalid'], true)
                            ? $error->getMessage() : 'lead_invalid';
                    } catch (\Throwable) {
                        return 'lead_invalid';
                    }
                    try {
                        $message = $this->messages->create($record);
                    } catch (\Throwable) {
                        return 'message_invalid';
                    }
                    try {
                        return $this->transport->send($message)
                            ? 'transport_success' : 'transport_failed';
                    } catch (\Throwable) {
                        return 'transport_failed';
                    }
                });
            } catch (\Throwable) {
                $status = 'event_invalid';
            }
            if (in_array($status, ['delivered', 'already_archived'], true)) {
                $delivered++;
            } elseif ($status === 'delivery_contended') {
                $contended++;
                $codes[$status] = ($codes[$status] ?? 0) + 1;
            } elseif ($status === 'delivery_uncertain') {
                $uncertain++;
                $codes[$status] = ($codes[$status] ?? 0) + 1;
            } else {
                $failed++;
                $code = in_array($status, [
                    'archive_conflict', 'archive_failed', 'event_invalid',
                    'lead_invalid', 'lead_missing', 'message_invalid', 'transport_failed',
                ], true) ? $status : 'event_invalid';
                $codes[$code] = ($codes[$code] ?? 0) + 1;
            }
        }
        ksort($codes, SORT_STRING);
        return NotificationDeliveryResult::create(count($ids), $delivered, 0, 0, $contended, $failed, $uncertain, $codes);
    }

    private function deliverStateful(int $limit,bool $retriesOnly):NotificationDeliveryResult
    {
        if(!$this->transport instanceof ClassifiedNotificationTransport)throw new \InvalidArgumentException('retry_configuration_invalid');
        try{$found=$this->pending->eligible($limit,$this->states,$this->clock,$retriesOnly);}
        catch(\Throwable){return NotificationDeliveryResult::create(1,0,0,0,0,1,0,['state_invalid'=>1]);}
        $delivered=$deadLettered=$contended=$uncertain=0;$codes=$found['codes'];$failed=array_sum($codes);
        foreach($found['recovery']as$eventId){
            $status=$this->pending->withLockedEvent($eventId,function(DeliveryEventLease $lease)use($eventId):string{
                try{
                    $state=$this->states->read($eventId);if($state===null)return'state_invalid';
                    if($state->state()==='attempting'){
                        $this->states->save($state->uncertain('attempt_interrupted',$this->clock->now()),$state->revision());
                        return'transport_uncertain';
                    }
                    if($state->state()!=='dead_lettered')return'state_invalid';
                    return$this->deadLetters->move($lease,$state,$state->revision());
                }catch(\Throwable $error){return$this->safeCode($error);}
            });
            $this->aggregate($status,$delivered,$deadLettered,$contended,$failed,$uncertain,$codes);
        }
        foreach($found['eligible']as$eventId){
            $status=$this->pending->withLockedEvent($eventId,function(DeliveryEventLease $lease)use($eventId):string{
                try{
                    $state=$this->states->read($eventId)??$this->states->create($eventId,$this->clock->now());
                    if(!$state->eligibleAt($this->clock->now()))return'state_transition_invalid';
                    $archive=$lease->archiveStatus();
                    if($archive==='archive_matching'){
                        $archived=$lease->archive();if(!in_array($archived,['delivered','already_archived'],true))return'delivery_uncertain';
                        $this->states->save($state->delivered($this->clock->now()),$state->revision());return$archived;
                    }
                    $permanent=$archive==='archive_conflict'?'archive_conflict':null;$message=null;
                    if($permanent===null){
                        try{$record=$this->records->read($lease->event()->leadId());}
                        catch(\RuntimeException $error){$permanent=in_array($error->getMessage(),['lead_missing','lead_invalid'],true)?$error->getMessage():'lead_invalid';}
                        catch(\Throwable){$permanent='lead_invalid';}
                    }
                    if($permanent===null){try{$message=$this->messages->create($record);}catch(\Throwable){$permanent='message_invalid';}}
                    if($permanent!==null){
                        $target=$state->deadLettered($permanent,$this->clock->now());
                        return$this->deadLetters->move($lease,$target,$state->revision());
                    }
                    $attempt=$this->states->save($state->beginAttempt($this->clock->now()),$state->revision());
                    try{$transportResult=$this->transport->sendClassified($message);}
                    catch(\Throwable){
                        try{$this->states->save($attempt->uncertain('transport_exception',$this->clock->now()),$attempt->revision());}catch(\Throwable){}
                        return'transport_exception';
                    }
                    if($transportResult->classification()==='uncertain'){
                        try{$this->states->save($attempt->uncertain('transport_uncertain',$this->clock->now()),$attempt->revision());}catch(\Throwable){}
                        return'transport_uncertain';
                    }
                    if($transportResult->classification()==='retryable_negative'){
                        $next=$this->retryPolicy->nextEligible($attempt->attemptCount(),$attempt->lastAttemptAt());
                        if($next!==null){
                            try{$this->states->save($attempt->retryWait('transport_failed',$next,$this->clock->now()),$attempt->revision());}
                            catch(\Throwable){return'delivery_uncertain';}
                            return'transport_failed';
                        }
                        return$this->deadLetters->move($lease,$attempt->deadLettered('transport_failed',$this->clock->now()),$attempt->revision());
                    }
                    $archived=$lease->archive();
                    if(!in_array($archived,['delivered','already_archived'],true)){
                        try{$this->states->save($attempt->uncertain('delivery_uncertain',$this->clock->now()),$attempt->revision());}catch(\Throwable){}
                        return'delivery_uncertain';
                    }
                    try{$this->states->save($attempt->delivered($this->clock->now()),$attempt->revision());}
                    catch(\Throwable){return'delivery_uncertain';}
                    return$archived;
                }catch(\Throwable $error){return$this->safeCode($error);}
            });
            $this->aggregate($status,$delivered,$deadLettered,$contended,$failed,$uncertain,$codes);
        }
        ksort($codes,SORT_STRING);
        $discovered=count($found['eligible'])+count($found['recovery'])+$found['deferred']+array_sum($found['codes']);
        return NotificationDeliveryResult::create($discovered,$delivered,$deadLettered,$found['deferred'],$contended,$failed,$uncertain,$codes);
    }
    /** @param array<string,int> $codes */
    private function aggregate(string $status,int &$delivered,int &$deadLettered,int &$contended,int &$failed,int &$uncertain,array &$codes):void
    {
        if(in_array($status,['delivered','already_archived'],true)){$delivered++;return;}
        if(in_array($status,['dead_lettered','dead_letter_existing'],true)){$deadLettered++;return;}
        if($status==='delivery_contended')$contended++;
        elseif(in_array($status,['transport_uncertain','transport_exception','delivery_uncertain'],true))$uncertain++;
        else$failed++;
        $codes[$status]=($codes[$status]??0)+1;
    }
    private function safeCode(\Throwable $error):string
    {
        $allowed=['state_invalid','state_clock_invalid','state_revision_stale','state_transition_invalid','state_unavailable','dead_letter_conflict','dead_letter_failed'];
        return in_array($error->getMessage(),$allowed,true)?$error->getMessage():'state_unavailable';
    }
}
