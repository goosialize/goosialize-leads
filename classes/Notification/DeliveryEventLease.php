<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
interface DeliveryEventLease
{
    public function event(): NotificationEvent;
    public function archiveStatus(): string;
    public function deadLetterStatus(): string;
    public function archive(): string;
    public function publishDeadLetter(): string;
    public function removePending(): bool;
}
