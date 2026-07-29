<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
interface ClassifiedNotificationTransport extends NotificationTransport
{
    public function sendClassified(NotificationMessage $message): NotificationTransportResult;
}
