<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Application;

use Grav\Plugin\GoosializeLeads\Notification\FilesystemNotificationOutbox;
use Grav\Plugin\GoosializeLeads\Notification\NotificationOutbox;
use Grav\Plugin\GoosializeLeads\Security\IdempotencyKeyRing;
use Grav\Plugin\GoosializeLeads\Storage\FilesystemLeadRepository;
use Grav\Plugin\GoosializeLeads\Validation\LeadInputValidator;
use Grav\Plugin\GoosializeLeads\Validation\LeadNormalizer;

final class LeadCaptureRuntimeFactory
{
    /**
     * @param array{
     *   active_key_version:int|string|null,
     *   keys:array<int|string,mixed>
     * } $idempotencyConfiguration
     * @param array<string,mixed>|null $outboxConfiguration
     * @param callable(string):mixed|null $logger
     */
    public static function create(
        string $userDataRoot,
        array $idempotencyConfiguration,
        ?array $outboxConfiguration,
        ?callable $logger = null
    ): LeadCaptureService {
        $versions = self::keyVersions($idempotencyConfiguration);

        $activeVersion = $idempotencyConfiguration['active_key_version'] ?? null;
        if (is_string($activeVersion) && ctype_digit($activeVersion)) {
            $activeVersion = (int) $activeVersion;
        }

        if (!is_int($activeVersion) || $activeVersion < 1) {
            throw new \InvalidArgumentException('Invalid idempotency configuration.');
        }

        $entropy = static fn (int $length): string => random_bytes($length);

        $keyRing = new IdempotencyKeyRing($activeVersion, $versions);

        $repository = new FilesystemLeadRepository(
            $userDataRoot,
            $entropy,
            $keyRing
        );

        $coordinator = new LeadPersistenceCoordinator(
            new LeadInputValidator(new LeadNormalizer()),
            $repository,
            $keyRing
        );

        return new LeadCaptureService(
            $coordinator,
            $keyRing,
            self::outbox(
                $userDataRoot,
                $outboxConfiguration,
                $entropy,
                $logger
            ),
            $logger
        );
    }

    /**
     * @param array{
     *   active_key_version:int|string|null,
     *   keys:array<int|string,mixed>
     * } $configuration
     * @return array<int,string>
     */
    private static function keyVersions(array $configuration): array
    {
        if (
            !array_key_exists('active_key_version', $configuration)
            || !array_key_exists('keys', $configuration)
            || !is_array($configuration['keys'])
        ) {
            throw new \InvalidArgumentException('Invalid idempotency configuration.');
        }

        $versions = [];

        foreach ($configuration['keys'] as $version => $encoded) {
            if (
                !(is_int($version) || (is_string($version) && ctype_digit($version)))
                || !is_string($encoded)
            ) {
                throw new \InvalidArgumentException('Invalid idempotency configuration.');
            }

            $integerVersion = (int) $version;
            if ($integerVersion < 1 || array_key_exists($integerVersion, $versions)) {
                throw new \InvalidArgumentException('Invalid idempotency configuration.');
            }

            $versions[$integerVersion] = $encoded;
        }

        if ($versions === []) {
            throw new \InvalidArgumentException('Invalid idempotency configuration.');
        }

        ksort($versions, SORT_NUMERIC);

        return $versions;
    }

    /**
     * @param array<string,mixed>|null $configuration
     * @param callable(int):string $entropy
     * @param callable(string):mixed|null $logger
     */
    private static function outbox(
        string $userDataRoot,
        ?array $configuration,
        callable $entropy,
        ?callable $logger
    ): ?NotificationOutbox {
        if ($configuration === null || ($configuration['enabled'] ?? null) !== true) {
            return null;
        }

        if (($configuration['max_event_bytes'] ?? null) !== 512) {
            throw new \InvalidArgumentException('Invalid outbox configuration.');
        }

        try {
            return new FilesystemNotificationOutbox(
                $userDataRoot,
                $entropy
            );
        } catch (\Throwable) {
            if ($logger !== null) {
                $logger('outbox_unavailable');
            }

            return null;
        }
    }
}
