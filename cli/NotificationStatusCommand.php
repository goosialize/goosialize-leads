<?php
declare(strict_types=1);
namespace Grav\Plugin\Console;
use Grav\Common\Grav;use Grav\Console\ConsoleCommand;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemNotificationOperationalInventoryRepository;
use Grav\Plugin\GoosializeLeads\Notification\SystemDeliveryClock;
use Symfony\Component\Console\Input\InputOption;
final class NotificationStatusCommand extends ConsoleCommand
{
    protected function configure():void{$this->setName('notification-status')->setDescription('Show read-only notification operations')->addOption('limit',null,InputOption::VALUE_REQUIRED,'Maximum items','50')->addOption('json',null,InputOption::VALUE_NONE);}
    protected function serve():int
    {
        $this->initializePlugins();$v=$this->getInput()->getOption('limit');
        if(!is_string($v)||preg_match('/\A(?:[1-9]|[1-9][0-9]|100)\z/D',$v)!==1)return 2;
        try{$root=Grav::instance()['locator']->findResource('user-data://',true,true);if(!is_string($root)||$root==='')return 2;
            $inventory=(new FilesystemNotificationOperationalInventoryRepository($root))->inventory((int)$v,new SystemDeliveryClock());
            if($this->getInput()->getOption('json')===true)$this->getIO()->write(json_encode($inventory->toArray(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n");
            else{$data=$inventory->toArray();foreach($inventory->counters()as$n=>$c)$this->getIO()->writeln("STATE name=$n count=$c");
                foreach($inventory->items()as$i){$a=$i->toArray();$this->getIO()->writeln('ITEM event_id='.$a['event_id'].' state='.$a['state'].' attempts='.$a['attempt_count'].' next='.($a['next_eligible_at']??'-').' updated='.$a['updated_at'].' code='.($a['last_result_code']??'-').' revision='.$a['revision']);}
                $m=$data['meta'];$this->getIO()->writeln("RESULT scanned={$m['total_scanned']} returned={$m['returned']} truncated=".(int)$m['truncated']." invalid={$m['invalid']} conflicts={$m['conflicts']}");}
            return$inventory->hasAnomaly()?3:0;
        }catch(\Throwable){return 2;}
    }
}
