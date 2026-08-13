<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Storage;

use Grav\Plugin\GoosializeLeads\Admin\LeadIndexCollection;
use Grav\Plugin\GoosializeLeads\Admin\LeadIndexQuery;

interface LeadReadRepository
{
    public function latest(LeadIndexQuery $query): LeadIndexCollection;

    public function findById(string $leadId): ?\Grav\Plugin\GoosializeLeads\Admin\LeadSummary;
}
