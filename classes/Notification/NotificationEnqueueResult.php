<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class NotificationEnqueueResult
{
    private const FAILURE_CODES = [
        'outbox_unavailable',
        'outbox_security_invalid',
        'outbox_capacity_exceeded',
        'outbox_event_conflict',
    ];

    private function __construct(
        private readonly string $status,
        private readonly ?string $code
    ) {
    }

    public static function created(): self
    {
        return new self('created', null);
    }

    public static function existing(): self
    {
        return new self('existing', null);
    }

    public static function failure(string $code): self
    {
        if (!in_array($code, self::FAILURE_CODES, true)) {
            throw new \InvalidArgumentException('Invalid notification enqueue result.');
        }
        return new self('failure', $code);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function isSuccess(): bool
    {
        return $this->status !== 'failure';
    }
}
