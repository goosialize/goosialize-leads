<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Admin;

final class LeadIndexQuery
{
    private function __construct()
    {
    }

    public static function newest(): self { return new self(); }
    public function limit(): int { return 100; }
}
