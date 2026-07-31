<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Integration;

interface LeadCaptureCapabilityV1
{
    public function capabilityId(): string;

    public function contractVersion(): int;

    public function available(): bool;

    public function capture(
        LeadCaptureRequestV1 $request,
        LeadCaptureContextV1 $context
    ): LeadCaptureResultV1;
}
