<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class NotificationEvent
{
    private const TYPE = 'lead.accepted';
    private const MAX_BYTES = 512;

    /** @param array{schema_version:1,event_id:string,event_type:string,lead_id:string,created_at:string} $data */
    private function __construct(
        private readonly array $data,
        private readonly string $bytes
    ) {
    }

    /** @param array<string,mixed> $record */
    public static function fromLeadRecord(array $record): self
    {
        $leadId = $record['id'] ?? null;
        $createdAt = $record['created_at'] ?? null;
        if (
            !is_string($leadId)
            || preg_match('/\A[0-9a-f]{32}\z/D', $leadId) !== 1
            || !is_string($createdAt)
            || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D', $createdAt) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid notification event.');
        }

        $eventId = hash('sha256', "goosialize-leads\0" . self::TYPE . "\0" . $leadId);
        $data = [
            'schema_version' => 1,
            'event_id' => $eventId,
            'event_type' => self::TYPE,
            'lead_id' => $leadId,
            'created_at' => $createdAt,
        ];
        try {
            $bytes = json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) . "\n";
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Invalid notification event.', 0, $exception);
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Invalid notification event.');
        }

        return new self($data, $bytes);
    }

    public function eventId(): string
    {
        return $this->data['event_id'];
    }

    public function leadId(): string
    {
        return $this->data['lead_id'];
    }

    public function createdAt(): string
    {
        return $this->data['created_at'];
    }

    public function canonicalBytes(): string
    {
        return $this->bytes;
    }

    /** @return array{schema_version:1,event_id:string,event_type:string,lead_id:string,created_at:string} */
    public function toArray(): array
    {
        return $this->data;
    }
}
