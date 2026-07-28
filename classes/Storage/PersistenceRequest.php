<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Storage;

use Grav\Plugin\GoosializeLeads\Domain\LeadRecord;

final class PersistenceRequest
{
    private function __construct(
        private readonly LeadRecord $record,
        private readonly string $recordBytes,
        private readonly ?string $keyDigest,
        private readonly ?string $payloadBytes
    ) {
    }

    public static function create(
        LeadRecord $record,
        string $recordBytes,
        ?string $keyDigest,
        ?string $payloadBytes
    ): self {
        $serialized = $record->serialize($record);
        if (!$serialized->isValid() || $serialized->value() !== $recordBytes) {
            throw new \InvalidArgumentException('Record bytes do not match record.');
        }
        if (($keyDigest === null) !== ($payloadBytes === null)) {
            throw new \InvalidArgumentException('Incomplete idempotency request.');
        }
        if ($keyDigest !== null) {
            $data = $record->toArray()['idempotency'];
            if (preg_match('/\A[0-9a-f]{64}\z/D', $keyDigest) !== 1
                || $payloadBytes === ''
                || !str_ends_with($payloadBytes, "\n")
                || $data['key_hash'] !== $keyDigest
            ) {
                throw new \InvalidArgumentException('Invalid idempotency request.');
            }
        }

        return new self($record, $recordBytes, $keyDigest, $payloadBytes);
    }

    public function record(): LeadRecord { return $this->record; }
    public function recordBytes(): string { return $this->recordBytes; }
    public function keyDigest(): ?string { return $this->keyDigest; }
    public function payloadBytes(): ?string { return $this->payloadBytes; }
    public function hasIdempotency(): bool { return $this->keyDigest !== null; }
}
