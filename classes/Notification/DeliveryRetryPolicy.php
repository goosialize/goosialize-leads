<?php
declare(strict_types=1);
namespace Grav\Plugin\GoosializeLeads\Notification;
final class DeliveryRetryPolicy
{
    private const DELAYS = [300, 1800, 7200, 28800];
    public function __construct(private readonly int $maximumAttempts = 5)
    {
        if ($maximumAttempts !== 5) throw new \InvalidArgumentException('retry_configuration_invalid');
    }
    public function maximumAttempts(): int { return $this->maximumAttempts; }
    public function classify(string $code, bool $transportInvoked, bool $transportThrew): string
    {
        if ($transportThrew) return $transportInvoked ? 'uncertain' : 'none';
        if (in_array($code, ['delivered','already_archived'], true)) return 'success';
        if (in_array($code, ['lead_missing','lead_invalid','message_invalid','archive_conflict'], true)) return 'permanent';
        if ($code === 'transport_failed' && $transportInvoked) return 'retryable';
        if ($transportInvoked && in_array($code, ['transport_uncertain','transport_exception','delivery_uncertain','archive_failed'], true)) return 'uncertain';
        if (in_array($code, ['delivery_contended','event_invalid','state_invalid','state_orphaned','state_clock_invalid','state_revision_stale','state_transition_invalid','state_unavailable'], true)) return 'none';
        throw new \InvalidArgumentException('state_transition_invalid');
    }
    public function nextEligible(int $attempt, \DateTimeImmutable $attemptedAt): ?\DateTimeImmutable
    {
        if ($attempt === 5) return null;
        if ($attempt < 1 || $attempt > 4) throw new \InvalidArgumentException('state_transition_invalid');
        try { $next = $attemptedAt->modify('+' . self::DELAYS[$attempt - 1] . ' seconds'); }
        catch (\Throwable) { throw new \InvalidArgumentException('state_clock_invalid'); }
        if (!$next instanceof \DateTimeImmutable || (int)$next->format('Y') > 9999) throw new \InvalidArgumentException('state_clock_invalid');
        return $next;
    }
}
