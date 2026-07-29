<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

interface NotificationTransport
{
    public function send(NotificationMessage $message): bool;
}
