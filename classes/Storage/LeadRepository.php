<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Storage;

interface LeadRepository
{
    public function persist(PersistenceRequest $request): PersistenceResult;
}
