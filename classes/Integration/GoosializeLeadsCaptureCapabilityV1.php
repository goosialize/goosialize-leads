<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Integration;

use Grav\Plugin\GoosializeLeads\Application\CaptureResult;
use Grav\Plugin\GoosializeLeads\Application\LeadCaptureService;

final class GoosializeLeadsCaptureCapabilityV1 implements LeadCaptureCapabilityV1
{
    public const CAPABILITY_ID = 'goosialize-leads.capture';
    public const CONTRACT_VERSION = 1;

    /** @var \Closure(int):string */
    private readonly \Closure $entropy;

    /** @var \Closure():\DateTimeInterface */
    private readonly \Closure $clock;

    public function __construct(
        private readonly ?LeadCaptureService $service,
        ?callable $entropy = null,
        ?callable $clock = null
    ) {
        $this->entropy = $entropy === null
            ? static fn (int $length): string => random_bytes($length)
            : \Closure::fromCallable($entropy);

        $this->clock = $clock === null
            ? static fn (): \DateTimeInterface => new \DateTimeImmutable(
                'now',
                new \DateTimeZone('UTC')
            )
            : \Closure::fromCallable($clock);
    }

    public function capabilityId(): string
    {
        return self::CAPABILITY_ID;
    }

    public function contractVersion(): int
    {
        return self::CONTRACT_VERSION;
    }

    public function available(): bool
    {
        return $this->service !== null;
    }

    public function capture(
        LeadCaptureRequestV1 $request,
        LeadCaptureContextV1 $context
    ): LeadCaptureResultV1 {
        if ($this->service === null) {
            return LeadCaptureResultV1::unavailable();
        }

        try {
            $submitted = $request->toArray();

            unset(
                $submitted['first_name'],
                $submitted['last_name']
            );

            $submitted = [
                'consent' => $submitted['consent'],
                'full_name' => $submitted['full_name'],
                'email' => $submitted['email'],
                'phone' => $submitted['phone'],
                'company' => $submitted['company'],
                'message' => $submitted['message'],
                'resource_id' => $submitted['resource_id'],
                'source_path' => $submitted['source_path'],
                'campaign' => $submitted['campaign'],
            ];

            $result = $this->service->capturePublicCapability(
                $submitted,
                $context->trusted(),
                $context->idempotencyKey(),
                $this->entropy,
                $this->clock
            );

            return $this->mapResult($result);
        } catch (\Throwable) {
            return LeadCaptureResultV1::unavailable();
        }
    }

    private function mapResult(CaptureResult $result): LeadCaptureResultV1
    {
        if ($result->isSuccess()) {
            return $result->replayed()
                ? LeadCaptureResultV1::replayed()
                : LeadCaptureResultV1::created();
        }

        return match ($result->code()) {
            'validation_failed' => LeadCaptureResultV1::validationFailed(
                $this->validationErrors($result)
            ),
            'idempotency_conflict' => LeadCaptureResultV1::idempotencyConflict(),
            default => LeadCaptureResultV1::unavailable(),
        };
    }

    /**
     * @return array<string,list<string>>
     */
    private function validationErrors(CaptureResult $result): array
    {
        $mapped = [];

        foreach ($result->errorsAsArray() as $error) {
            $field = $error['field'] ?? null;
            $field = is_string($field) && $field !== ''
                ? $field
                : '_request';

            $code = $error['code'];

            if (!isset($mapped[$field])) {
                $mapped[$field] = [];
            }

            if (!in_array($code, $mapped[$field], true)) {
                $mapped[$field][] = $code;
            }
        }

        if ($mapped === []) {
            return [
                '_request' => ['validation_failed'],
            ];
        }

        ksort($mapped, SORT_STRING);

        return $mapped;
    }
}
