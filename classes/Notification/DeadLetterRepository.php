<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
interface DeadLetterRepository
{
    public function move(DeliveryEventLease $lease, DeliveryState $state, int $expectedRevision): string;
}
