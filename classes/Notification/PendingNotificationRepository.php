<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

interface PendingNotificationRepository
{
    /** @return list<string> */
    public function pending(int $limit): array;

    /** @param callable(NotificationEvent):string $consumer */
    public function process(string $eventId, callable $consumer): string;
}
