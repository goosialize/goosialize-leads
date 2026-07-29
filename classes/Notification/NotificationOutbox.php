<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

interface NotificationOutbox
{
    public function ensure(NotificationEvent $event): NotificationEnqueueResult;
}
