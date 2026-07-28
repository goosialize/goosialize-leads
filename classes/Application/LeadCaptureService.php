<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Application;

use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\PersistenceResult;
use Grav\Plugin\GoosializeLeads\Storage\StorageException;

final class LeadCaptureService
{
    public function __construct(
        private readonly LeadPersistenceCoordinator $coordinator,
        private readonly IdempotencyKeyRing $keyRing
    ) {
    }

    /**
     * @param array{source:string,form_name:string,locale:?string,consent_version:string} $trusted
     * @param callable(int):string $entropy
     * @param callable():\DateTimeInterface $clock
     */
    public function capture(
        mixed $submitted,
        array $trusted,
        string $formName,
        string $submissionId,
        callable $entropy,
        callable $clock
    ): CaptureResult {
        if (($trusted['form_name'] ?? null) !== $formName) {
            throw new \InvalidArgumentException('Form name mismatch.');
        }
        if (preg_match('/\A[a-z0-9]{20}\z/D', $submissionId) !== 1) {
            return CaptureResult::failure('invalid_submission_id');
        }

        try {
            $idempotencyKey = $this->keyRing->deriveFormsIdempotencyKey($formName, $submissionId);
            return $this->mapPersistenceResult(
                $this->coordinator->persist($submitted, $trusted, $idempotencyKey, $entropy, $clock)
            );
        } catch (StorageException) {
            return CaptureResult::failure('storage_unavailable');
        }
    }

    /**
     * @param array{source:string,form_name:string,locale:?string,consent_version:string} $trusted
     * @param callable(int):string $entropy
     * @param callable():\DateTimeInterface $clock
     */
    public function captureApi(
        mixed $submitted,
        array $trusted,
        string $idempotencyKey,
        callable $entropy,
        callable $clock
    ): CaptureResult {
        try {
            $derived = $this->keyRing->deriveApiIdempotencyKey($idempotencyKey);
            return $this->mapPersistenceResult(
                $this->coordinator->persist($submitted, $trusted, $derived, $entropy, $clock)
            );
        } catch (StorageException) {
            return CaptureResult::failure('storage_unavailable');
        } catch (\InvalidArgumentException) {
            return CaptureResult::failure('invalid_submission_id');
        }
    }

    private function mapPersistenceResult(PersistenceResult $result): CaptureResult
    {
        $record = $result->record();
        if ($result->status() === 'created' || $result->status() === 'replayed') {
            if (
                !is_array($record)
                || !isset($record['id'], $record['status'], $record['created_at'])
                || !is_string($record['id'])
                || !is_string($record['status'])
                || !is_string($record['created_at'])
            ) {
                return CaptureResult::failure('storage_unavailable');
            }
            return CaptureResult::success(
                ['id' => $record['id'], 'status' => $record['status'], 'created_at' => $record['created_at']],
                $result->status() === 'replayed'
            );
        }
        if ($result->status() !== 'failure') {
            return CaptureResult::failure('storage_unavailable');
        }

        return match ($result->code()) {
            'validation_failed' => CaptureResult::failure('validation_failed', $result->errors()),
            'invalid_idempotency_key' => CaptureResult::failure('invalid_submission_id'),
            'idempotency_conflict', 'idempotency_expired' => CaptureResult::failure('idempotency_conflict'),
            default => CaptureResult::failure('storage_unavailable'),
        };
    }
}
