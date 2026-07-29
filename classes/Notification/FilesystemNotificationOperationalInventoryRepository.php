<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class FilesystemNotificationOperationalInventoryRepository implements NotificationOperationalInventoryRepository
{
    private readonly string $userRoot;
    private readonly string $base;
    public function __construct(string $userDataRoot)
    {
        if($userDataRoot===''||$userDataRoot[0]!=='/'||str_contains($userDataRoot,"\0"))throw new \InvalidArgumentException('operational_inventory_invalid');
        $this->userRoot=rtrim($userDataRoot,'/');$this->base=$this->userRoot.'/goosialize-leads/v1/notification-outbox';
    }
    public function inventory(int $limit,DeliveryClock $clock):NotificationOperationalInventory
    {
        if($limit<1||$limit>100)throw new \RuntimeException('inventory_capacity_exceeded');
        $user=@lstat($this->userRoot);if(!$user||is_link($this->userRoot)||($user['mode']&0170000)!==0040000)throw new \RuntimeException('inventory_changed');
        if(file_exists($this->base)||is_link($this->base)){
            if(!$this->directory($this->base))throw new \RuntimeException('inventory_changed');
            $real=@realpath($this->base);$root=@realpath($this->userRoot);
            if(!is_string($real)||!is_string($root)||!str_starts_with($real,$root.'/'))throw new \RuntimeException('inventory_changed');
        }
        $now=$clock->now();$records=[];$codes=[];$scanned=0;$shards=0;
        foreach(['pending'=>'events','state'=>'delivery-state','sent'=>'sent','dead'=>'dead-letter']as$kind=>$name){
            $root=$this->base.'/'.$name;if(!file_exists($root)&&!is_link($root))continue;
            if(!$this->directory($root)){self::inc($codes,$kind==='state'?'state_invalid':'event_invalid');continue;}
            $names=@scandir($root);if(!is_array($names))throw new \RuntimeException('inventory_changed');
            foreach($names as$shard){if($shard==='.'||$shard==='..')continue;if(++$scanned>10000)throw new \RuntimeException('inventory_capacity_exceeded');
                if(++$shards>256)throw new \RuntimeException('inventory_capacity_exceeded');
                $dir=$root.'/'.$shard;if(preg_match('/\A[0-9a-f]{2}\z/D',$shard)!==1||!$this->directory($dir)){self::inc($codes,$kind==='state'?'state_invalid':'event_invalid');continue;}
                $files=@scandir($dir);if(!is_array($files))throw new \RuntimeException('inventory_changed');
                foreach($files as$file){if($file==='.'||$file==='..')continue;if(++$scanned>10000)throw new \RuntimeException('inventory_capacity_exceeded');
                    if(preg_match('/\A([0-9a-f]{64})\.json\z/D',$file,$m)!==1||substr($m[1],0,2)!==$shard){self::inc($codes,$kind==='state'?'state_invalid':'event_invalid');continue;}
                    $id=$m[1];try{$value=$kind==='state'?$this->readState($dir.'/'.$file,$id):$this->readEvent($dir.'/'.$file,$id);
                        if(isset($records[$id][$kind])){self::inc($codes,'inventory_conflict');$records[$id]['duplicate']=true;}
                        $records[$id][$kind]=$value;
                    }catch(\RuntimeException $e){self::inc($codes,in_array($e->getMessage(),['inventory_changed'],true)?$e->getMessage():($kind==='state'?'state_invalid':'event_invalid'));}
                }
            }
        }
        $items=[];$counters=[];
        foreach($records as$id=>$r){$item=$this->classify($id,$r,$now);if($item===null){self::inc($codes,'inventory_conflict');continue;}self::inc($counters,$item->state());$items[]=$item;}
        usort($items,static fn($a,$b)=>$b->updatedAt()<=>$a->updatedAt()?:strcmp($a->eventId(),$b->eventId()));
        $truncated=count($items)>$limit;$items=array_slice($items,0,$limit);ksort($counters,SORT_STRING);ksort($codes,SORT_STRING);
        return NotificationOperationalInventory::create($items,$counters,$codes,$scanned,$limit,$truncated);
    }
    private function classify(string $id,array $r,\DateTimeImmutable $now):?NotificationOperationalItem
    {
        $event=$r['pending']??$r['sent']??$r['dead']??null;$state=$r['state']??null;
        if(!$event instanceof NotificationEvent)return null;
        $pending=isset($r['pending']);$sent=isset($r['sent']);$dead=isset($r['dead']);$conflict=isset($r['duplicate'])||($sent&&$dead);
        foreach(['sent','dead']as$kind)if(isset($r[$kind])&&$r[$kind]->canonicalBytes()!==$event->canonicalBytes())$conflict=true;
        $name=null;
        if($conflict)$name='conflict';
        elseif($pending&&($sent||$dead||($state&&in_array($state->state(),['delivered','dead_lettered'],true))))$name='recovery_required';
        elseif(!$pending&&$dead&&$state?->state()==='dead_lettered')$name='dead_lettered';
        elseif(!$pending&&$sent&&($state===null||$state->state()==='delivered'))$name='sent';
        elseif($pending&&!$sent&&!$dead){
            if($state===null)$name='pending_first_attempt';
            elseif($state->state()==='uncertain')$name='uncertain';
            elseif($state->state()==='attempting')$name='attempting';
            elseif($state->state()==='retry_wait')$name=$state->eligibleAt($now)?'eligible_retry':'retry_wait';
            elseif($state->state()==='eligible'&&$state->attemptCount()===0&&$state->revision()===1&&$state->firstAttemptAt()===null&&$state->lastAttemptAt()===null&&$state->nextEligibleAt()===null&&$state->lastResultCode()===null&&$state->terminalAt()===null&&$state->createdAt()==$state->updatedAt())$name='pending_first_attempt';
            elseif($state->state()==='eligible'&&$state->attemptCount()>=1&&$state->attemptCount()<=4&&$state->firstAttemptAt()!==null&&$state->lastAttemptAt()!==null&&$state->lastResultCode()==='duplicate_risk_retry_authorized')$name='eligible_retry';
        } elseif(!$pending&&$sent&&$state&&!in_array($state->state(),['delivered','dead_lettered'],true))$name='recovery_required';
        if($name===null)$name='conflict';
        $created=$state?->createdAt()??new \DateTimeImmutable($event->createdAt(),new \DateTimeZone('UTC'));
        $updated=$state?->updatedAt()??$created;$flags=['conflict'=>[false,false,true],'recovery_required'=>[false,true,true],'dead_lettered'=>[true,false,false],'sent'=>[true,false,false],'uncertain'=>[false,false,true],'attempting'=>[false,true,false],'eligible_retry'=>[false,true,false],'retry_wait'=>[false,false,false],'pending_first_attempt'=>[false,true,false]][$name];
        return NotificationOperationalItem::create($id,$name,$state?->attemptCount()??0,$state?->nextEligibleAt(),$created,$updated,$state?->lastResultCode(),$state?->revision()??0,...$flags);
    }
    private function readEvent(string $path,string $id):NotificationEvent
    {
        $bytes=$this->read($path,512);try{$data=json_decode(substr($bytes,0,-1),true,8,JSON_THROW_ON_ERROR);if(!is_array($data))throw new \Exception();
            $event=NotificationEvent::fromLeadRecord(['id'=>$data['lead_id']??null,'created_at'=>$data['created_at']??null]);
            if($event->eventId()!==$id||$event->canonicalBytes()!==$bytes)throw new \Exception();return$event;
        }catch(\Throwable){throw new \RuntimeException('event_invalid');}
    }
    private function readState(string $path,string $id):DeliveryState
    {
        $bytes=$this->read($path,1024);try{$data=json_decode(substr($bytes,0,-1),true,8,JSON_THROW_ON_ERROR);if(!is_array($data)||json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"!==$bytes)throw new \Exception();
            $state=DeliveryState::fromArray($data);if($state->eventId()!==$id)throw new \Exception();return$state;
        }catch(\Throwable){throw new \RuntimeException('state_invalid');}
    }
    private function read(string $path,int $max):string
    {
        $before=@lstat($path);if(!$before||is_link($path)||($before['mode']&0170000)!==0100000||($before['mode']&0777)!==0600||$before['size']<2||$before['size']>$max)throw new \RuntimeException('inventory_changed');
        $h=@fopen($path,'rb');if(!$h)throw new \RuntimeException('inventory_changed');try{$after=@lstat($path);$open=@fstat($h);$bytes=@stream_get_contents($h,$max+1);}finally{@fclose($h);}
        if(!$after||!$open||$before['dev']!==$after['dev']||$before['ino']!==$after['ino']||$after['dev']!==$open['dev']||$after['ino']!==$open['ino']||$after['size']!==$open['size']||!is_string($bytes)||strlen($bytes)!==$before['size']||!str_ends_with($bytes,"\n"))throw new \RuntimeException('inventory_changed');
        return$bytes;
    }
    private function directory(string $path):bool{$s=@lstat($path);return(bool)$s&&!is_link($path)&&($s['mode']&0170000)===0040000&&($s['mode']&0777)===0700;}
    private static function inc(array &$map,string $key):void{$map[$key]=($map[$key]??0)+1;}
}
