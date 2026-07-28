<?php

declare(strict_types=1);

namespace Grav\Plugin\GoosializeLeads\Admin;

final class LeadCsvExporter
{
    private const HEADERS = ['Lead ID', 'Created (UTC)', 'Name', 'Email', 'Source', 'Form', 'Status'];
    private const LIMITS = [128, 32, 256, 320, 128, 128, 32];

    public function __construct()
    {
    }

    public function export(LeadIndexCollection $collection, int $maximumBytes): string
    {
        if ($maximumBytes !== 131072) {
            throw new \InvalidArgumentException('Invalid Lead CSV export configuration.');
        }

        $csv = $this->row(self::HEADERS);
        foreach ($collection->summaries() as $summary) {
            $values = [
                $summary->id(),
                $summary->createdAt(),
                $summary->name(),
                $summary->email(),
                $summary->source(),
                $summary->formName(),
                $summary->status(),
            ];
            foreach ($values as $index => $value) {
                if ($value !== null && !$this->validLength($value, self::LIMITS[$index])) {
                    throw new \RuntimeException('Invalid Lead CSV export record.');
                }
            }
            $csv .= $this->row($values);
            if (strlen($csv) > $maximumBytes) {
                throw new \LengthException('Lead CSV export exceeds the response limit.');
            }
        }
        return $csv;
    }

    /** @param list<?string> $values */
    private function row(array $values): string
    {
        return implode(',', array_map(function (?string $value): string {
            $value ??= '';
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            if ($this->dangerous($value)) {
                $value = "'" . $value;
            }
            return '"' . str_replace('"', '""', $value) . '"';
        }, $values)) . "\r\n";
    }

    private function validLength(string $value, int $limit): bool
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $matched = preg_match_all('/./us', $value, $unused);
        return $matched !== false && $matched <= $limit;
    }

    private function dangerous(string $value): bool
    {
        if ($value === '') return false;
        if (in_array($value[0], ["\t", "\r", "\n"], true)) return true;
        $significant = ltrim($value, " \t");
        return $significant !== '' && str_contains('=+-@', $significant[0]);
    }
}
