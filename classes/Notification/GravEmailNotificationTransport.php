<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

use Grav\Plugin\Email\Email;
use Symfony\Component\Mime\Address;

final class GravEmailNotificationTransport implements NotificationTransport
{
    /** @param list<string> $recipients */
    public function __construct(
        private readonly Email $email,
        private readonly array $recipients,
        private readonly string $senderAddress,
        private readonly ?string $senderName
    ) {
    }

    public function send(NotificationMessage $message): bool
    {
        try {
            $mail = $this->email->message($message->subject(), $message->body(), 'text/plain');
            $mail->from($this->senderName === null
                ? $this->senderAddress
                : new Address($this->senderAddress, $this->senderName));
            $mail->getEmail()->to(...$this->recipients);
            return $this->email->send($mail) === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
