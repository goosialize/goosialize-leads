<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class NotificationMessage
{
    private function __construct(
        private readonly string $subject,
        private readonly string $body
    ) {
    }

    public static function create(string $subject, string $body): self
    {
        if (
            strlen($subject) < 1
            || strlen($subject) > 41
            || preg_match('//u', $subject) !== 1
            || preg_match('/[\x00-\x1f\x7f-\x9f]/u', $subject) === 1
            || !class_exists(\Normalizer::class)
            || !\Normalizer::isNormalized($subject, \Normalizer::FORM_C)
            || strlen($body) < 1
            || strlen($body) > 12288
            || !str_ends_with($body, "\n")
            || str_ends_with($body, "\n\n")
            || str_contains($body, "\r")
            || preg_match('//u', $body) !== 1
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\x9f]/u', $body) === 1
            || !\Normalizer::isNormalized($body, \Normalizer::FORM_C)
        ) {
            throw new \InvalidArgumentException('Invalid notification message.');
        }

        return new self($subject, $body);
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }
}
