<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class NotificationMessageFactory
{
    /** @param array<string,mixed> $record */
    public function create(array $record): NotificationMessage
    {
        $specification = [
            'id' => [32, true],
            'created_at' => [27, true],
            'full_name' => [256, false],
            'email' => [320, false],
            'phone' => [64, false],
            'company' => [256, false],
            'source' => [64, true],
            'form_name' => [64, true],
            'message' => [10000, false],
        ];
        $values = [];
        foreach ($specification as $field => [$maximum, $required]) {
            if (!array_key_exists($field, $record)) {
                throw new \InvalidArgumentException('Invalid notification message.');
            }
            $value = $record[$field];
            if ($value === null || $value === '') {
                if ($required) {
                    throw new \InvalidArgumentException('Invalid notification message.');
                }
                $values[$field] = '-';
                continue;
            }
            if (
                !is_string($value)
                || strlen($value) > $maximum
                || preg_match('//u', $value) !== 1
                || !class_exists(\Normalizer::class)
                || !\Normalizer::isNormalized($value, \Normalizer::FORM_C)
            ) {
                throw new \InvalidArgumentException('Invalid notification message.');
            }
            $values[$field] = $this->normalizeValue($value, $field === 'message');
        }
        if (
            preg_match('/\A[0-9a-f]{32}\z/D', $values['id']) !== 1
            || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D', $values['created_at']) !== 1
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $values['source']) !== 1
            || preg_match('/\A[a-z0-9](?:[a-z0-9_-]{0,62}[a-z0-9])?\z/D', $values['form_name']) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid notification message.');
        }

        $message = str_replace("\n", "\n  ", $values['message']);
        $body = 'Lead ID: ' . $values['id'] . "\n"
            . 'Created (UTC): ' . $values['created_at'] . "\n"
            . 'Name: ' . $values['full_name'] . "\n"
            . 'Email: ' . $values['email'] . "\n"
            . 'Phone: ' . $values['phone'] . "\n"
            . 'Company: ' . $values['company'] . "\n"
            . 'Source: ' . $values['source'] . "\n"
            . 'Form: ' . $values['form_name'] . "\n"
            . 'Message: ' . $message . "\n";

        return NotificationMessage::create('New Lead ' . $values['id'], $body);
    }

    private function normalizeValue(string $value, bool $multiline): string
    {
        $value = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $value);
        if (
            (!$multiline && str_contains($value, "\n"))
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\x9f]|\x{2028}|\x{2029}/u', $value) === 1
        ) {
            throw new \InvalidArgumentException('Invalid notification message.');
        }
        return $value;
    }
}
