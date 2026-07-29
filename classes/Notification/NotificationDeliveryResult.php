<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class NotificationDeliveryResult
{
    /** @param array<string,int> $codes */
    private function __construct(
        private readonly int $discovered,
        private readonly int $delivered,
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
        int $contended,
        int $failed,
        int $uncertain,
        array $codes
    ): self {
        $allowed = [
            'archive_conflict', 'archive_failed', 'delivery_contended',
            'delivery_disabled', 'delivery_uncertain', 'email_unavailable',
            'event_invalid', 'invalid_limit', 'lead_invalid', 'lead_missing',
            'message_invalid', 'routing_invalid', 'transport_failed',
        ];
        $sorted = $codes;
        ksort($sorted, SORT_STRING);
        $valid = $discovered >= 0 && $delivered >= 0 && $contended >= 0
            && $failed >= 0 && $uncertain >= 0
            && $discovered === $delivered + $contended + $failed + $uncertain
            && $codes === $sorted;
        foreach ($codes as $code => $count) {
            $valid = $valid && in_array($code, $allowed, true)
                && is_int($count) && $count > 0;
        }
        if (!$valid) {
            throw new \InvalidArgumentException('Invalid notification delivery result.');
        }
        return new self($discovered, $delivered, $contended, $failed, $uncertain, $codes);
    }

    public function discovered(): int { return $this->discovered; }
    public function delivered(): int { return $this->delivered; }
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
