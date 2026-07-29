<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class NotificationOperationalItem
{
    private const MATRIX=[
        'conflict'=>[false,false,true],'recovery_required'=>[false,true,true],
        'dead_lettered'=>[true,false,false],'sent'=>[true,false,false],
        'uncertain'=>[false,false,true],'attempting'=>[false,true,false],
        'eligible_retry'=>[false,true,false],'retry_wait'=>[false,false,false],
        'pending_first_attempt'=>[false,true,false],
    ];
    private const CODES=['already_archived','archive_conflict','archive_failed','attempt_interrupted','dead_letter_conflict','dead_letter_failed','delivered','delivery_uncertain','duplicate_risk_retry_authorized','lead_invalid','lead_missing','message_invalid','state_unavailable','transport_exception','transport_failed','transport_uncertain'];
    private function __construct(
        private readonly string $eventId,private readonly string $state,private readonly int $attemptCount,
        private readonly ?\DateTimeImmutable $nextEligibleAt,private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,private readonly ?string $lastResultCode,
        private readonly int $revision,private readonly bool $terminal,private readonly bool $processable,
        private readonly bool $operatorActionable
    ){}
    public static function create(string $eventId,string $state,int $attemptCount,?\DateTimeImmutable $nextEligibleAt,\DateTimeImmutable $createdAt,\DateTimeImmutable $updatedAt,?string $lastResultCode,int $revision,bool $terminal,bool $processable,bool $operatorActionable):self
    {
        $matrix=self::MATRIX[$state]??null;
        if(preg_match('/\A[0-9a-f]{64}\z/D',$eventId)!==1||$matrix!==[$terminal,$processable,$operatorActionable]
            ||$attemptCount<0||$attemptCount>5||$revision<0||$revision>2147483647
            ||($lastResultCode!==null&&!in_array($lastResultCode,self::CODES,true))
            ||$updatedAt<$createdAt)self::bad();
        $utc=new \DateTimeZone('UTC');$createdAt=$createdAt->setTimezone($utc);$updatedAt=$updatedAt->setTimezone($utc);$nextEligibleAt=$nextEligibleAt?->setTimezone($utc);
        return new self($eventId,$state,$attemptCount,$nextEligibleAt,$createdAt,$updatedAt,$lastResultCode,$revision,$terminal,$processable,$operatorActionable);
    }
    public function eventId():string{return$this->eventId;} public function state():string{return$this->state;}
    public function attemptCount():int{return$this->attemptCount;} public function nextEligibleAt():?\DateTimeImmutable{return$this->nextEligibleAt;}
    public function createdAt():\DateTimeImmutable{return$this->createdAt;} public function updatedAt():\DateTimeImmutable{return$this->updatedAt;}
    public function lastResultCode():?string{return$this->lastResultCode;} public function revision():int{return$this->revision;}
    public function terminal():bool{return$this->terminal;} public function processable():bool{return$this->processable;}
    public function operatorActionable():bool{return$this->operatorActionable;}
    public function toArray():array{return['event_id'=>$this->eventId,'state'=>$this->state,'attempt_count'=>$this->attemptCount,
        'next_eligible_at'=>self::time($this->nextEligibleAt),'created_at'=>self::time($this->createdAt),'updated_at'=>self::time($this->updatedAt),
        'last_result_code'=>$this->lastResultCode,'revision'=>$this->revision,'terminal'=>$this->terminal,
        'processable'=>$this->processable,'operator_actionable'=>$this->operatorActionable];}
    private static function time(?\DateTimeImmutable $v):?string{return$v?->format('Y-m-d\TH:i:s.u\Z');}
    private static function bad():never{throw new \InvalidArgumentException('operational_inventory_invalid');}
}
