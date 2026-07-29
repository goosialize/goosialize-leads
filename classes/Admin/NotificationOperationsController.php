<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Admin;
use Grav\Common\Config\Config;use Grav\Common\Grav;use Grav\Framework\Psr7\Response;
use Grav\Plugin\GoosializeLeads\Notification\FilesystemNotificationOperationalInventoryRepository;
use Grav\Plugin\GoosializeLeads\Notification\SystemDeliveryClock;
use Psr\Http\Message\ResponseInterface;use Psr\Http\Message\ServerRequestInterface;
final class NotificationOperationsController
{
    public function __construct(private readonly Grav $grav,private readonly Config $config){}
    public function index(ServerRequestInterface $request):ResponseInterface
    {
        $user=$request->getAttribute('api_user');if(!is_object($user))return$this->error(401,'authentication_required');
        if(!$this->allowed($user))return$this->error(403,'forbidden');
        try{$root=$this->grav['locator']->findResource('user-data://',true);if(!is_string($root)||$root==='')return$this->error(503,'inventory_unavailable');
            $body=(new FilesystemNotificationOperationalInventoryRepository($root))->inventory(50,new SystemDeliveryClock())->toArray();
            $body['meta']['core_available']=isset($this->grav['scheduler']);
            $body['meta']['job_registered']=$body['meta']['core_available']&&$this->config->get('plugins.goosialize-leads.notifications.scheduling.enabled')===true;
            $body['meta']['job_enabled']=$body['meta']['job_registered']&&$this->config->get('scheduler.status.goosialize-leads-notification-delivery')!=='disabled';
            return$this->response(200,$body);
        }catch(\RuntimeException $e){return$this->error(in_array($e->getMessage(),['inventory_capacity_exceeded','inventory_changed'],true)?503:500,'inventory_unavailable');}
        catch(\Throwable){return$this->error(500,'internal_error');}
    }
    private function allowed(object $u):bool{try{if(method_exists($u,'get')&&(bool)$u->get('access.api.super'))return true;if(method_exists($u,'get')&&!(bool)$u->get('access.api.access'))return false;if(method_exists($u,'get')&&(bool)$u->get('access.api.goosialize_leads.operations'))return true;return method_exists($u,'authorize')&&(bool)$u->authorize('api.goosialize_leads.operations');}catch(\Throwable){return false;}}
    private function error(int $status,string $code):ResponseInterface{return$this->response($status,['ok'=>false,'code'=>$code]);}
    private function response(int $status,array $body):ResponseInterface{return new Response($status,['Content-Type'=>'application/json; charset=utf-8','Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff'],json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));}
}
