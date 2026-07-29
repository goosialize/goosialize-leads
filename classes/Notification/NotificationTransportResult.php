<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class NotificationTransportResult
{
    private function __construct(private readonly string $classification) {}
    public static function success(): self { return new self('success'); }
    public static function retryableNegative(): self { return new self('retryable_negative'); }
    public static function uncertain(): self { return new self('uncertain'); }
    public function classification(): string { return $this->classification; }
    public function isSuccess(): bool { return $this->classification === 'success'; }
}
