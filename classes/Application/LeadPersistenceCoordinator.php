<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Application;

use Grav\Plugin\GoosializeLeads\Domain\LeadRecord;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\LeadRepository;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceRequest;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
use Grav\Plugin\GoosializeLeads\Storage\StorageException;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;

final class LeadPersistenceCoordinator
{
    public function __construct(
        private readonly LeadInputValidator $validator,
        private readonly LeadRepository $repository,
        private readonly IdempotencyKeyRing $keyRing
    ) {
    }

    /**
     * @param array{source:string,form_name:string,locale:?string,consent_version:string} $trusted
     * @param callable(int):string $entropy
     * @param callable():\DateTimeInterface $clock
     */
    public function persist(
        mixed $submitted,
        array $trusted,
        ?string $idempotencyKey,
        callable $entropy,
        callable $clock
    ): PersistenceResult {
        $validation = $this->validator->validate($submitted, $trusted);
        if (!$validation->isValid()) {
            return PersistenceResult::failure('validation_failed', $validation->errors());
        }
        /** @var CaptureCommand $command */
        $command = $validation->value();
        if ($idempotencyKey !== null && preg_match('/\A[A-Za-z0-9._~-]{16,128}\z/D', $idempotencyKey) !== 1) {
            return PersistenceResult::failure('invalid_idempotency_key');
        }
        try {
            $keyDigest = $this->keyRing->keyDigest($idempotencyKey);
            $payloadBytes = $idempotencyKey === null ? null : $this->keyRing->canonicalPayload($command);
            $payloadDigest = $idempotencyKey === null ? null : $this->keyRing->payloadDigest($command);
            $keyVersion = $idempotencyKey === null ? null : $this->keyRing->activeVersion();

            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $recordResult = LeadRecord::fromCommandWithIdempotency(
                    $command,
                    $entropy,
                    $clock,
                    $keyVersion,
                    $keyDigest,
                    $payloadDigest
                );
                if (!$recordResult->isValid()) {
                    return PersistenceResult::failure('storage_unavailable');
                }
                /** @var LeadRecord $record */
                $record = $recordResult->value();
                $serialized = $record->serialize($record);
                if (!$serialized->isValid() || !is_string($serialized->value())) {
                    return PersistenceResult::failure('storage_unavailable');
                }
                $result = $this->repository->persist(PersistenceRequest::create(
                    $record,
                    $serialized->value(),
                    $keyDigest,
                    $payloadBytes
                ));
                if ($result->status() !== 'id_collision') return $result;
            }
            return PersistenceResult::failure('collision_exhausted');
        } catch (StorageException) {
            return PersistenceResult::failure('storage_unavailable');
        }
    }
}
