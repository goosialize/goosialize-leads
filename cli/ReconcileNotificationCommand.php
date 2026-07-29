<?php
declare(strict_types=1);
namespace Grav\Plugin\Console;
use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Grav\Plugin\GoosializeLeads\Notification\DeliveryReconciliationService;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemDeadLetterRepository;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemDeliveryStateRepository;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemPendingNotificationRepository;
use Grav\Plugin\GoosializeLeads\Notification\SystemDeliveryClock;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
final class ReconcileNotificationCommand extends ConsoleCommand
{
    protected function configure():void
    {
        $this->setName('reconcile-notification')->setDescription('Reconcile one uncertain Lead notification')
            ->addArgument('event-id',InputArgument::REQUIRED,'Canonical event ID')
            ->addArgument('revision',InputArgument::REQUIRED,'Expected state revision')
            ->addOption('confirm-delivered',null,InputOption::VALUE_NONE)
            ->addOption('retry-duplicate-risk',null,InputOption::VALUE_NONE)
            ->addOption('dead-letter',null,InputOption::VALUE_NONE)
            ->addOption('yes',null,InputOption::VALUE_NONE);
    }
    protected function serve():int
    {
        $this->initializePlugins();$input=$this->getInput();
        $eventId=$input->getArgument('event-id');$revision=$input->getArgument('revision');$actions=[];
        foreach(['confirm-delivered','retry-duplicate-risk','dead-letter']as$action)if($input->getOption($action)===true)$actions[]=$action;
        $shown=$actions[0]??'confirm-delivered';
        if(!is_string($eventId)||preg_match('/\A[0-9a-f]{64}\z/D',$eventId)!==1||!is_string($revision)
            ||preg_match('/\A[1-9][0-9]*\z/D',$revision)!==1||count($actions)!==1||$input->getOption('yes')!==true)
            return$this->output($shown,'rejected',0,2);
        $grav=Grav::instance();$retry=$grav['config']->get('plugins.goosialize-leads.notifications.delivery_retry',[]);
        if(!$this->validRetryConfiguration($retry)||($retry['enabled']??false)!==true)return$this->output($shown,'rejected',0,2);
        $root=$grav['locator']->findResource('user-data://',true,true);
        if(!is_string($root)||$root==='')return$this->output($shown,'failed',0,3);
        try{
            $states=new FilesystemDeliveryStateRepository($root);
            $service=new DeliveryReconciliationService(new FilesystemPendingNotificationRepository($root),$states,new FilesystemDeadLetterRepository($states),new SystemDeliveryClock());
            $result=$service->reconcile($eventId,(int)$revision,$shown);
            return$this->output($shown,$result['status'],$result['revision'],$result['status']==='completed'?0:3);
        }catch(\Throwable){return$this->output($shown,'failed',0,3);}
    }
    private function output(string $action,string $status,int $revision,int $exit):int
    {$this->getIO()->writeln("RECONCILE action=$action status=$status revision=$revision");return$exit;}
    private function validRetryConfiguration(mixed $value):bool
    {
        if(!is_array($value))return false;
        $defaults=['enabled'=>false,'maximum_attempts'=>5,'delays_seconds'=>[300,1800,7200,28800],'processing_limit'=>10,'state_max_bytes'=>1024];
        foreach($value as$key=>$_)if(!array_key_exists($key,$defaults))return false;
        $value=array_replace($defaults,$value);
        return is_bool($value['enabled'])&&$value['maximum_attempts']===5&&$value['delays_seconds']===[300,1800,7200,28800]
            &&is_int($value['processing_limit'])&&$value['processing_limit']>=1&&$value['processing_limit']<=50&&$value['state_max_bytes']===1024;
    }
}
