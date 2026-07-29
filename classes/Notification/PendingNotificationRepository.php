<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

interface PendingNotificationRepository
{
    /** @return list<string> */
    public function pending(int $limit): array;

    /** @param callable(NotificationEvent):string $consumer */
    public function process(string $eventId, callable $consumer): string;

    /** @return array{eligible:list<string>,recovery:list<string>,deferred:int,codes:array<string,int>} */
    public function eligible(int $limit, DeliveryStateRepository $states, DeliveryClock $clock, bool $retriesOnly): array;

    /** @param callable(DeliveryEventLease):string $consumer */
    public function withLockedEvent(string $eventId, callable $consumer): string;
}
