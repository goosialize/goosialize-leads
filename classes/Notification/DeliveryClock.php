<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
interface DeliveryClock { public function now(): \DateTimeImmutable; }
