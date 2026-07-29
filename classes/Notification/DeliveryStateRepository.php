<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
interface DeliveryStateRepository
{
    public function read(string $eventId): ?DeliveryState;
    public function create(string $eventId, \DateTimeImmutable $now): DeliveryState;
    public function save(DeliveryState $state, int $expectedRevision): DeliveryState;
}
