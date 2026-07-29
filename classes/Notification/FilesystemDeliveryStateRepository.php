<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class FilesystemDeliveryStateRepository implements DeliveryStateRepository
{
    private readonly string $root;
    private readonly string $locks;
    /** @var callable(int):string */ private $entropy;
    /** @var callable(resource):bool */ private $sync;
    /** @var callable(string,string):bool */ private $replace;
    public function __construct(string $userDataRoot,?callable $entropy=null,?callable $sync=null,?callable $replace=null)
    {
        if($userDataRoot===''||$userDataRoot[0]!=='/'||str_contains($userDataRoot,"\0"))throw new \InvalidArgumentException('state_invalid');
        $base=rtrim($userDataRoot,'/').'/goosialize-leads/v1/notification-outbox';
        $this->root=$base.'/delivery-state';$this->locks=$base.'/delivery-state-locks';
        $this->entropy=$entropy??random_bytes(...);$this->sync=$sync??fsync(...);$this->replace=$replace??rename(...);
    }
    public function read(string $eventId):?DeliveryState
    {
        $this->assertId($eventId);$path=$this->statePath($eventId);
        if(!file_exists($path)&&!is_link($path))return null;
        return$this->readPath($path,$eventId);
    }
    public function create(string $eventId,\DateTimeImmutable $now):DeliveryState
    {
        return$this->withLock($eventId,function()use($eventId,$now):DeliveryState{
            $current=$this->read($eventId);if($current!==null)return$current;
            $state=DeliveryState::initial($eventId,$now);$this->publish($state,false);
            return$this->read($eventId)??throw new \RuntimeException('state_unavailable');
        });
    }
    public function save(DeliveryState $state,int $expectedRevision):DeliveryState
    {
        if($expectedRevision<1||$state->revision()!==$expectedRevision+1)throw new \RuntimeException('state_revision_stale');
        return$this->withLock($state->eventId(),function()use($state,$expectedRevision):DeliveryState{
            $current=$this->read($state->eventId());
            if($current===null||$current->revision()!==$expectedRevision)throw new \RuntimeException('state_revision_stale');
            $this->publish($state,true);$saved=$this->read($state->eventId());
            if($saved===null||$saved->toArray()!==$state->toArray())throw new \RuntimeException('state_unavailable');
            return$saved;
        });
    }
    private function withLock(string $eventId,callable $consumer):mixed
    {
        $this->assertId($eventId);$this->ensureDirectory($this->locks);$dir=$this->locks.'/'.substr($eventId,0,2);$this->ensureDirectory($dir);
        $path=$dir.'/'.$eventId.'.lock';$before=@lstat($path);
        if($before!==false&&(is_link($path)||($before['mode']&0170000)!==0100000||($before['mode']&0777)!==0600))throw new \RuntimeException('state_invalid');
        $handle=@fopen($path,'c+b');if(!is_resource($handle))throw new \RuntimeException('state_unavailable');
        try{
            @chmod($path,0600);$opened=@fstat($handle);$after=@lstat($path);
            if(!$opened||!$after||$opened['dev']!==$after['dev']||$opened['ino']!==$after['ino']||($opened['mode']&0777)!==0600)throw new \RuntimeException('state_invalid');
            if(!@flock($handle,LOCK_EX|LOCK_NB))throw new \RuntimeException('state_revision_stale');
            try{return$consumer();}finally{@flock($handle,LOCK_UN);}
        }finally{@fclose($handle);}
    }
    private function publish(DeliveryState $state,bool $replace):void
    {
        $this->ensureDirectory($this->root);$dir=$this->root.'/'.substr($state->eventId(),0,2);$this->ensureDirectory($dir);
        $target=$this->statePath($state->eventId());
        try{$token=bin2hex(($this->entropy)(8));}catch(\Throwable){throw new \RuntimeException('state_unavailable');}
        if(preg_match('/\A[0-9a-f]{16}\z/D',$token)!==1)throw new \RuntimeException('state_unavailable');
        $temporary=$target.'.'.$token.'.tmp';
        try{$bytes=json_encode($state->toArray(),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}catch(\Throwable){throw new \RuntimeException('state_invalid');}
        if(strlen($bytes)<2||strlen($bytes)>1024)throw new \RuntimeException('state_invalid');
        $handle=@fopen($temporary,'x+b');if(!is_resource($handle))throw new \RuntimeException('state_unavailable');
        try{
            if(!@chmod($temporary,0600)||@fwrite($handle,$bytes)!==strlen($bytes)||!@fflush($handle)||!(($this->sync)($handle)))throw new \RuntimeException('state_unavailable');
            if($replace){if(!(($this->replace)($temporary,$target)))throw new \RuntimeException('state_unavailable');}
            elseif(!@link($temporary,$target))throw new \RuntimeException('state_revision_stale');
            if(!$this->syncDirectory($dir))throw new \RuntimeException('state_unavailable');
        }finally{@fclose($handle);$stat=@lstat($temporary);if($stat&& !is_link($temporary)&&($stat['mode']&0170000)===0100000)@unlink($temporary);}
    }
    private function readPath(string $path,string $eventId):DeliveryState
    {
        $before=@lstat($path);$real=@realpath($path);$root=@realpath($this->root);
        if(!$before||!$real||!$root||is_link($path)||($before['mode']&0170000)!==0100000||($before['mode']&0777)!==0600
            ||$before['size']<2||$before['size']>1024||!str_starts_with($real,$root.'/'))throw new \RuntimeException('state_invalid');
        $handle=@fopen($path,'rb');if(!is_resource($handle))throw new \RuntimeException('state_invalid');
        try{
            $after=@lstat($path);$opened=@fstat($handle);
            if(!$after||!$opened||$before['dev']!==$after['dev']||$before['ino']!==$after['ino']||$after['dev']!==$opened['dev']||$after['ino']!==$opened['ino']||$after['size']!==$opened['size']||($opened['mode']&0777)!==0600)throw new \RuntimeException('state_invalid');
            $bytes=@stream_get_contents($handle,1025);
        }finally{@fclose($handle);}
        try{
            if(!is_string($bytes)||strlen($bytes)!==$before['size']||!str_ends_with($bytes,"\n"))throw new \RuntimeException('state_invalid');
            $data=json_decode(substr($bytes,0,-1),true,8,JSON_THROW_ON_ERROR);
            if(!is_array($data)||json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"!==$bytes)throw new \RuntimeException('state_invalid');
            $state=DeliveryState::fromArray($data);if(!hash_equals($eventId,$state->eventId()))throw new \RuntimeException('state_invalid');
            return$state;
        }catch(\RuntimeException $error){throw$error;}catch(\Throwable){throw new \RuntimeException('state_invalid');}
    }
    private function ensureDirectory(string $path):void
    {
        if(!is_dir($path)&&!@mkdir($path,0700,true)&&!is_dir($path))throw new \RuntimeException('state_unavailable');
        @chmod($path,0700);$stat=@lstat($path);
        if(!$stat||is_link($path)||($stat['mode']&0170000)!==0040000||($stat['mode']&0777)!==0700)throw new \RuntimeException('state_invalid');
    }
    private function syncDirectory(string $path):bool{$handle=@fopen($path,'rb');if(!is_resource($handle))return false;try{return(bool)(($this->sync)($handle));}finally{@fclose($handle);}}
    private function statePath(string $eventId):string{return$this->root.'/'.substr($eventId,0,2).'/'.$eventId.'.json';}
    private function assertId(string $eventId):void{if(preg_match('/\A[0-9a-f]{64}\z/D',$eventId)!==1)throw new \RuntimeException('state_invalid');}
}
