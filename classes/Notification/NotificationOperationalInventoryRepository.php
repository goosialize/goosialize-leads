<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
interface NotificationOperationalInventoryRepository
{
    public function inventory(int $limit, DeliveryClock $clock): NotificationOperationalInventory;
}
