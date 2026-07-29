<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class NotificationOperationalInventory
{
    /** @param list<NotificationOperationalItem> $items @param array<string,int> $counters @param array<string,int> $codes */
    private function __construct(private readonly array $items,private readonly array $counters,private readonly array $codes,private readonly int $totalScanned,private readonly int $limit,private readonly bool $truncated){}
    /** @param list<NotificationOperationalItem> $items @param array<string,int> $counters @param array<string,int> $codes */
    public static function create(array $items,array $counters,array $codes,int $totalScanned,int $limit,bool $truncated):self
    {
        if($limit<1||$limit>100||$totalScanned<0||$totalScanned>10000||count($items)>$limit)self::bad();
        foreach($items as$item)if(!$item instanceof NotificationOperationalItem)self::bad();
        foreach([$counters,$codes]as$map){$copy=$map;ksort($copy,SORT_STRING);if($copy!==$map)self::bad();foreach($map as$k=>$v)if(!is_string($k)||!is_int($v)||$v<1)self::bad();}
        return new self($items,$counters,$codes,$totalScanned,$limit,$truncated);
    }
    public function items():array{return$this->items;} public function counters():array{return$this->counters;}
    public function codes():array{return$this->codes;} public function totalScanned():int{return$this->totalScanned;}
    public function limit():int{return$this->limit;} public function truncated():bool{return$this->truncated;}
    public function hasAnomaly():bool{return$this->codes!==[]||isset($this->counters['conflict'])||isset($this->counters['recovery_required']);}
    public function toArray():array
    {
        $invalid=0;foreach($this->codes as$code=>$count)if($code!=='inventory_conflict')$invalid+=$count;
        return['items'=>array_map(static fn(NotificationOperationalItem $i):array=>$i->toArray(),$this->items),
            'meta'=>['read_only'=>true,'total_scanned'=>$this->totalScanned,'returned'=>count($this->items),'limit'=>$this->limit,
                'truncated'=>$this->truncated,'invalid'=>$invalid,'conflicts'=>($this->counters['conflict']??0),
                'counters'=>$this->counters,'codes'=>$this->codes]];
    }
    private static function bad():never{throw new \InvalidArgumentException('operational_inventory_invalid');}
}
