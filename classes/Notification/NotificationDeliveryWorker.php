<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Notification;

final class NotificationDeliveryWorker
{
    public function __construct(
        private readonly PendingNotificationRepository $pending,
        private readonly LeadDeliveryRecordReader $reader,
        private readonly NotificationMessageFactory $messages,
        private readonly NotificationTransport $transport
    ) {
    }

    public function deliver(int $limit): NotificationDeliveryResult
    {
        if ($limit < 1 || $limit > 50) {
            throw new \InvalidArgumentException('Invalid delivery limit.');
        }
        try {
            $ids = $this->pending->pending($limit);
        } catch (\Throwable) {
            return NotificationDeliveryResult::create(1, 0, 0, 1, 0, ['event_invalid' => 1]);
        }
        $delivered = $contended = $failed = $uncertain = 0;
        $codes = [];
        foreach ($ids as $id) {
            try {
                $status = $this->pending->process($id, function (NotificationEvent $event): string {
                    try {
                        $record = $this->reader->read($event->leadId());
                    } catch (\RuntimeException $error) {
                        return in_array($error->getMessage(), ['lead_missing', 'lead_invalid'], true)
                            ? $error->getMessage() : 'lead_invalid';
                    } catch (\Throwable) {
                        return 'lead_invalid';
                    }
                    try {
                        $message = $this->messages->create($record);
                    } catch (\Throwable) {
                        return 'message_invalid';
                    }
                    try {
                        return $this->transport->send($message)
                            ? 'transport_success' : 'transport_failed';
                    } catch (\Throwable) {
                        return 'transport_failed';
                    }
                });
            } catch (\Throwable) {
                $status = 'event_invalid';
            }
            if (in_array($status, ['delivered', 'already_archived'], true)) {
                $delivered++;
            } elseif ($status === 'delivery_contended') {
                $contended++;
                $codes[$status] = ($codes[$status] ?? 0) + 1;
            } elseif ($status === 'delivery_uncertain') {
                $uncertain++;
                $codes[$status] = ($codes[$status] ?? 0) + 1;
            } else {
                $failed++;
                $code = in_array($status, [
                    'archive_conflict', 'archive_failed', 'event_invalid',
                    'lead_invalid', 'lead_missing', 'message_invalid', 'transport_failed',
                ], true) ? $status : 'event_invalid';
                $codes[$code] = ($codes[$code] ?? 0) + 1;
            }
        }
        ksort($codes, SORT_STRING);
        return NotificationDeliveryResult::create(count($ids), $delivered, $contended, $failed, $uncertain, $codes);
    }
}
