<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class NotificationDeliveryResult
{
    /** @param array<string,int> $codes */
    private function __construct(
        private readonly int $discovered,
        private readonly int $delivered,
        private readonly int $deadLettered,
        private readonly int $deferred,
        private readonly int $contended,
        private readonly int $failed,
        private readonly int $uncertain,
        private readonly array $codes
    ) {
    }

    /** @param array<string,int> $codes */
    public static function create(
        int $discovered,
        int $delivered,
        int $deadLettered,
        int $deferred,
        int $contended,
        int $failed,
        int $uncertain,
        array $codes
    ): self {
        $allowed = [
            'archive_conflict', 'archive_failed', 'dead_letter_conflict', 'dead_letter_failed', 'delivery_contended', 'delivery_lease_invalid',
            'delivery_disabled', 'delivery_uncertain', 'email_unavailable',
            'event_invalid', 'invalid_limit', 'lead_invalid', 'lead_missing',
            'message_invalid', 'retry_configuration_invalid', 'routing_invalid',
            'state_clock_invalid', 'state_invalid', 'state_orphaned', 'state_revision_stale',
            'state_transition_invalid', 'state_unavailable', 'transport_exception',
            'transport_failed', 'transport_uncertain',
        ];
        $sorted = $codes;
        ksort($sorted, SORT_STRING);
        $valid = $discovered >= 0 && $delivered >= 0 && $deadLettered >= 0 && $deferred >= 0 && $contended >= 0
            && $failed >= 0 && $uncertain >= 0
            && $discovered === $delivered + $deadLettered + $deferred + $contended + $failed + $uncertain
            && $codes === $sorted;
        foreach ($codes as $code => $count) {
            $valid = $valid && in_array($code, $allowed, true)
                && is_int($count) && $count > 0;
        }
        if (!$valid) {
            throw new \InvalidArgumentException('Invalid notification delivery result.');
        }
        return new self($discovered, $delivered, $deadLettered, $deferred, $contended, $failed, $uncertain, $codes);
    }

    public function discovered(): int { return $this->discovered; }
    public function delivered(): int { return $this->delivered; }
    public function deadLettered(): int { return $this->deadLettered; }
    public function deferred(): int { return $this->deferred; }
    public function contended(): int { return $this->contended; }
    public function failed(): int { return $this->failed; }
    public function uncertain(): int { return $this->uncertain; }

    /** @return array<string,int> */
    public function codes(): array { return $this->codes; }

    public function isComplete(): bool
    {
        return $this->contended === 0 && $this->failed === 0 && $this->uncertain === 0;
    }
}
