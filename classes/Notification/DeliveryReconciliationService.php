<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class DeliveryReconciliationService
{
    public function __construct(
        private readonly PendingNotificationRepository $pending,
        private readonly DeliveryStateRepository $states,
        private readonly DeadLetterRepository $deadLetters,
        private readonly DeliveryClock $clock
    ) {}
    /** @return array{status:string,revision:int,code:?string} */
    public function reconcile(string $eventId,int $revision,string $action):array
    {
        if(preg_match('/\A[0-9a-f]{64}\z/D',$eventId)!==1||$revision<1||!in_array($action,['confirm-delivered','retry-duplicate-risk','dead-letter'],true))
            return['status'=>'rejected','revision'=>0,'code'=>'state_transition_invalid'];
        $result=['status'=>'failed','revision'=>0,'code'=>'state_unavailable'];
        $status=$this->pending->withLockedEvent($eventId,function(DeliveryEventLease $lease)use($eventId,$revision,$action,&$result):string{
            try{
                $state=$this->states->read($eventId);
                if($state===null||$state->state()!=='uncertain'||$state->revision()!==$revision){
                    $result=['status'=>'rejected','revision'=>0,'code'=>'state_revision_stale'];return'reconcile_rejected';
                }
                $archiveStatus=$lease->archiveStatus();
                if($archiveStatus==='archive_conflict'){
                    $result=['status'=>'rejected','revision'=>0,'code'=>'archive_conflict'];return'reconcile_rejected';
                }
                if($action==='retry-duplicate-risk'){
                    if($archiveStatus!=='archive_absent'){
                        $result=['status'=>'rejected','revision'=>0,'code'=>'archive_conflict'];return'reconcile_rejected';
                    }
                    if($lease->deadLetterStatus()!=='dead_letter_absent'){
                        $result=['status'=>'rejected','revision'=>0,'code'=>'dead_letter_conflict'];return'reconcile_rejected';
                    }
                    $saved=$this->states->save($state->authorizeDuplicateRiskRetry($this->clock->now()),$revision);
                    $result=['status'=>'completed','revision'=>$saved->revision(),'code'=>null];return'reconcile_completed';
                }
                if($action==='confirm-delivered'){
                    if($lease->deadLetterStatus()!=='dead_letter_absent'){
                        $result=['status'=>'rejected','revision'=>0,'code'=>'dead_letter_conflict'];return'reconcile_rejected';
                    }
                    $archive=$lease->archive();
                    if(!in_array($archive,['delivered','already_archived'],true)){
                        $result=['status'=>'failed','revision'=>0,'code'=>$archive];return'reconcile_failed';
                    }
                    $saved=$this->states->save($state->delivered($this->clock->now()),$revision);
                    $result=['status'=>'completed','revision'=>$saved->revision(),'code'=>null];return'reconcile_completed';
                }
                if($archiveStatus!=='archive_absent'){
                    $result=['status'=>'rejected','revision'=>0,'code'=>'archive_conflict'];return'reconcile_rejected';
                }
                if($lease->deadLetterStatus()!=='dead_letter_absent'){
                    $result=['status'=>'rejected','revision'=>0,'code'=>'dead_letter_conflict'];return'reconcile_rejected';
                }
                $target=$state->deadLettered((string)$state->lastResultCode(),$this->clock->now());
                $moved=$this->deadLetters->move($lease,$target,$revision);
                if(!in_array($moved,['dead_lettered','dead_letter_existing'],true)){
                    $result=['status'=>'failed','revision'=>0,'code'=>$moved];return'reconcile_failed';
                }
                $result=['status'=>'completed','revision'=>$target->revision(),'code'=>null];return'reconcile_completed';
            }catch(\InvalidArgumentException){
                $result=['status'=>'rejected','revision'=>0,'code'=>'state_transition_invalid'];return'reconcile_rejected';
            }catch(\RuntimeException $error){
                $code=in_array($error->getMessage(),['state_revision_stale','state_transition_invalid'],true)?$error->getMessage():'state_unavailable';
                $result=['status'=>$code==='state_revision_stale'?'rejected':'failed','revision'=>0,'code'=>$code];
                return$result['status']==='rejected'?'reconcile_rejected':'reconcile_failed';
            }
        });
        if($status==='delivery_contended')return['status'=>'contended','revision'=>0,'code'=>'delivery_contended'];
        if($status==='event_invalid')return['status'=>'rejected','revision'=>0,'code'=>'event_invalid'];
        return$result;
    }
}
