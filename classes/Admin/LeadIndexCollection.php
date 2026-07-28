<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Admin;

final class LeadIndexCollection
{
    /** @param list<LeadSummary> $summaries */
    private function __construct(
        private readonly array $summaries,
        private readonly bool $truncated,
        private readonly int $totalScanned
    ) {
    }

    /** @param list<LeadSummary> $summaries */
    public static function create(array $summaries, bool $truncated, int $totalScanned): self
    {
        if (!array_is_list($summaries) || count($summaries) > 100
            || $totalScanned > 10000 || $truncated !== ($totalScanned > 100)
            || $totalScanned < 0 || count($summaries) !== min($totalScanned, 100)
        ) throw new \InvalidArgumentException('Invalid Lead index collection.');
        foreach ($summaries as $summary) {
            if (!$summary instanceof LeadSummary) throw new \InvalidArgumentException('Invalid Lead index collection.');
        }
        return new self($summaries, $truncated, $totalScanned);
    }

    /** @return list<LeadSummary> */
    public function summaries(): array { return $this->summaries; }
    public function truncated(): bool { return $this->truncated; }
    public function totalScanned(): int { return $this->totalScanned; }

    /** @return array{data:list<array<string,mixed>>,meta:array{read_only:true,count:int,limit:100,truncated:bool,total_scanned:int,allowed_statuses:list<string>}} */
    public function toResponse(): array
    {
        return [
            'data' => array_map(static fn (LeadSummary $summary): array => $summary->toArray(), $this->summaries),
            'meta' => [
                'read_only' => true,
                'count' => count($this->summaries),
                'limit' => 100,
                'truncated' => $this->truncated,
                'total_scanned' => $this->totalScanned,
                'allowed_statuses' => ['new', 'contacted', 'qualified', 'closed'],
            ],
        ];
    }
}
