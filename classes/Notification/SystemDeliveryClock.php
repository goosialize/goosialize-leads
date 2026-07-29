<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class SystemDeliveryClock implements DeliveryClock
{
    public function now(): \DateTimeImmutable { return new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
}
