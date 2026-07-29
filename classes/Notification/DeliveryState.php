<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class DeliveryState
{
    private const KEYS=['schema_version','event_id','revision','state','attempt_count','first_attempt_at','last_attempt_at','next_eligible_at','last_result_code','terminal_at','created_at','updated_at'];
    private const STATES=['eligible','retry_wait','attempting','uncertain','delivered','dead_lettered'];
    private const CODES=['already_archived','archive_conflict','archive_failed','attempt_interrupted','dead_letter_conflict','dead_letter_failed','delivered','delivery_uncertain','duplicate_risk_retry_authorized','lead_invalid','lead_missing','message_invalid','state_unavailable','transport_exception','transport_failed','transport_uncertain'];
    private function __construct(
        private readonly int $schemaVersion, private readonly string $eventId, private readonly int $revision,
        private readonly string $state, private readonly int $attemptCount, private readonly ?\DateTimeImmutable $firstAttemptAt,
        private readonly ?\DateTimeImmutable $lastAttemptAt, private readonly ?\DateTimeImmutable $nextEligibleAt,
        private readonly ?string $lastResultCode, private readonly ?\DateTimeImmutable $terminalAt,
        private readonly \DateTimeImmutable $createdAt, private readonly \DateTimeImmutable $updatedAt
    ) { $this->validate(); }
    public static function initial(string $eventId,\DateTimeImmutable $now):self
    {
        self::id($eventId);$now=self::time($now);
        return new self(1,$eventId,1,'eligible',0,null,null,null,null,null,$now,$now);
    }
    public static function fromArray(array $data):self
    {
        if(array_keys($data)!==self::KEYS)throw new \InvalidArgumentException('state_invalid');
        foreach(['schema_version','revision','attempt_count']as$k)if(!is_int($data[$k]))throw new \InvalidArgumentException('state_invalid');
        foreach(['event_id','state']as$k)if(!is_string($data[$k]))throw new \InvalidArgumentException('state_invalid');
        if($data['last_result_code']!==null&&!is_string($data['last_result_code']))throw new \InvalidArgumentException('state_invalid');
        return new self($data['schema_version'],$data['event_id'],$data['revision'],$data['state'],$data['attempt_count'],
            self::nullable($data['first_attempt_at']),self::nullable($data['last_attempt_at']),self::nullable($data['next_eligible_at']),
            $data['last_result_code'],self::nullable($data['terminal_at']),self::required($data['created_at']),self::required($data['updated_at']));
    }
    public function schemaVersion():int{return$this->schemaVersion;}
    public function eventId():string{return$this->eventId;}
    public function revision():int{return$this->revision;}
    public function state():string{return$this->state;}
    public function attemptCount():int{return$this->attemptCount;}
    public function firstAttemptAt():?\DateTimeImmutable{return$this->firstAttemptAt;}
    public function lastAttemptAt():?\DateTimeImmutable{return$this->lastAttemptAt;}
    public function nextEligibleAt():?\DateTimeImmutable{return$this->nextEligibleAt;}
    public function lastResultCode():?string{return$this->lastResultCode;}
    public function terminalAt():?\DateTimeImmutable{return$this->terminalAt;}
    public function createdAt():\DateTimeImmutable{return$this->createdAt;}
    public function updatedAt():\DateTimeImmutable{return$this->updatedAt;}
    public function beginAttempt(\DateTimeImmutable $now):self
    {
        if(!in_array($this->state,['eligible','retry_wait'],true)||$this->attemptCount>=5||!$this->eligibleAt($now))$this->badTransition();
        $now=$this->nextTime($now);
        return $this->copy('attempting',$this->attemptCount+1,$this->firstAttemptAt??$now,$now,null,null,null,$now);
    }
    public function retryWait(string $code,\DateTimeImmutable $nextEligibleAt,\DateTimeImmutable $now):self
    {
        if($this->state!=='attempting'||$this->attemptCount>=5||$code!=='transport_failed')$this->badTransition();
        $now=$this->nextTime($now);$nextEligibleAt=self::time($nextEligibleAt);
        if($nextEligibleAt<=$now)throw new \InvalidArgumentException('state_clock_invalid');
        return $this->copy('retry_wait',$this->attemptCount,$this->firstAttemptAt,$this->lastAttemptAt,$nextEligibleAt,$code,null,$now);
    }
    public function uncertain(string $code,\DateTimeImmutable $now):self
    {
        if($this->state!=='attempting'||!in_array($code,['attempt_interrupted','transport_exception','transport_uncertain','delivery_uncertain'],true))$this->badTransition();
        $now=$this->nextTime($now);
        return $this->copy('uncertain',$this->attemptCount,$this->firstAttemptAt,$this->lastAttemptAt,null,$code,null,$now);
    }
    public function authorizeDuplicateRiskRetry(\DateTimeImmutable $now):self
    {
        if($this->state!=='uncertain'||$this->attemptCount<1||$this->attemptCount>4)$this->badTransition();
        $now=$this->nextTime($now);
        return $this->copy('eligible',$this->attemptCount,$this->firstAttemptAt,$this->lastAttemptAt,null,'duplicate_risk_retry_authorized',null,$now);
    }
    public function delivered(\DateTimeImmutable $now):self
    {
        if(in_array($this->state,['delivered','dead_lettered'],true))$this->badTransition();
        $now=$this->nextTime($now);
        return $this->copy('delivered',$this->attemptCount,$this->firstAttemptAt,$this->lastAttemptAt,null,'delivered',$now,$now);
    }
    public function deadLettered(string $code,\DateTimeImmutable $now):self
    {
        $permanent=in_array($code,['lead_missing','lead_invalid','message_invalid','archive_conflict'],true);
        $allowed=($permanent&&($this->state==='eligible'||($this->state==='retry_wait'&&$this->eligibleAt($now))))
            ||($this->state==='attempting'&&$this->attemptCount===5&&$code==='transport_failed')
            ||($this->state==='uncertain'&&$code===$this->lastResultCode);
        if(!$allowed)$this->badTransition();
        $now=$this->nextTime($now);
        return $this->copy('dead_lettered',$this->attemptCount,$this->firstAttemptAt,$this->lastAttemptAt,null,$code,$now,$now);
    }
    public function eligibleAt(\DateTimeImmutable $now):bool
    {
        $now=self::time($now);
        return $this->state==='eligible'||($this->state==='retry_wait'&&$this->nextEligibleAt!==null&&$now>=$this->nextEligibleAt);
    }
    public function toArray():array
    {
        return['schema_version'=>$this->schemaVersion,'event_id'=>$this->eventId,'revision'=>$this->revision,'state'=>$this->state,
            'attempt_count'=>$this->attemptCount,'first_attempt_at'=>self::format($this->firstAttemptAt),'last_attempt_at'=>self::format($this->lastAttemptAt),
            'next_eligible_at'=>self::format($this->nextEligibleAt),'last_result_code'=>$this->lastResultCode,'terminal_at'=>self::format($this->terminalAt),
            'created_at'=>self::format($this->createdAt),'updated_at'=>self::format($this->updatedAt)];
    }
    private function copy(string $state,int $attempts,?\DateTimeImmutable $first,?\DateTimeImmutable $last,?\DateTimeImmutable $next,?string $code,?\DateTimeImmutable $terminal,\DateTimeImmutable $updated):self
    {if($this->revision===2147483647)throw new \InvalidArgumentException('state_transition_invalid');return new self(1,$this->eventId,$this->revision+1,$state,$attempts,$first,$last,$next,$code,$terminal,$this->createdAt,$updated);}
    private function validate():void
    {
        self::id($this->eventId);
        if($this->schemaVersion!==1||$this->revision<1||$this->revision>2147483647||!in_array($this->state,self::STATES,true)
            ||$this->attemptCount<0||$this->attemptCount>5||($this->lastResultCode!==null&&!in_array($this->lastResultCode,self::CODES,true))
            ||$this->updatedAt<$this->createdAt)throw new \InvalidArgumentException('state_invalid');
        $attempted=$this->attemptCount>0;
        if($attempted!==($this->firstAttemptAt!==null&&$this->lastAttemptAt!==null))throw new \InvalidArgumentException('state_invalid');
        if($attempted&&($this->firstAttemptAt>$this->lastAttemptAt||$this->lastAttemptAt>$this->updatedAt))throw new \InvalidArgumentException('state_invalid');
        if($this->state==='eligible'&&($this->nextEligibleAt!==null||$this->terminalAt!==null||!in_array($this->lastResultCode,[null,'duplicate_risk_retry_authorized'],true)))throw new \InvalidArgumentException('state_invalid');
        if($this->state==='eligible'&&$this->attemptCount===5)throw new \InvalidArgumentException('state_invalid');
        if($this->state==='attempting'&&(!$attempted||$this->nextEligibleAt!==null||$this->terminalAt!==null||$this->lastResultCode!==null))throw new \InvalidArgumentException('state_invalid');
        if($this->state==='retry_wait'&&(!$attempted||$this->attemptCount>=5||$this->nextEligibleAt===null||$this->lastResultCode!=='transport_failed'||$this->terminalAt!==null))throw new \InvalidArgumentException('state_invalid');
        if($this->state==='uncertain'&&(!$attempted||$this->nextEligibleAt!==null||!in_array($this->lastResultCode,['attempt_interrupted','transport_exception','transport_uncertain','delivery_uncertain'],true)||$this->terminalAt!==null))throw new \InvalidArgumentException('state_invalid');
        if(in_array($this->state,['delivered','dead_lettered'],true)!==($this->terminalAt!==null))throw new \InvalidArgumentException('state_invalid');
        if($this->state==='delivered'&&$this->lastResultCode!=='delivered')throw new \InvalidArgumentException('state_invalid');
    }
    private function nextTime(\DateTimeImmutable $now):\DateTimeImmutable{$now=self::time($now);if($now<=$this->updatedAt)throw new \InvalidArgumentException('state_clock_invalid');return$now;}
    private function badTransition():never{throw new \InvalidArgumentException('state_transition_invalid');}
    private static function id(string $id):void{if(preg_match('/\A[0-9a-f]{64}\z/D',$id)!==1)throw new \InvalidArgumentException('state_invalid');}
    private static function time(\DateTimeImmutable $value):\DateTimeImmutable{return self::required($value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'));}
    private static function nullable(mixed $value):?\DateTimeImmutable{return$value===null?null:self::required($value);}
    private static function required(mixed $value):\DateTimeImmutable
    {
        if(!is_string($value)||preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D',$value)!==1)throw new \InvalidArgumentException('state_invalid');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z',$value,new \DateTimeZone('UTC'));$errors=\DateTimeImmutable::getLastErrors();
        if(!$date||($errors!==false&&($errors['warning_count']||$errors['error_count']))||$date->format('Y-m-d\TH:i:s.u\Z')!==$value)throw new \InvalidArgumentException('state_invalid');
        return$date;
    }
    private static function format(?\DateTimeImmutable $value):?string{return$value?->format('Y-m-d\TH:i:s.u\Z');}
}
