<?php

namespace App\Support\PedagogicalManagement;

final class PedagogicalAiReportStatistics
{
    /** @param array<string,mixed> $report @return array<string,mixed> */
    public static function enrich(array $report): array
    {
        $report['summary_statistics'] = self::calculate($report);

        return $report;
    }

    /** @param array<string,mixed> $report @return array<string,int> */
    public static function calculate(array $report): array
    {
        $statuses = collect($report['criteria_assessment'] ?? [])
            ->filter(fn ($criterion): bool => is_array($criterion))
            ->map(fn (array $criterion): string => (string) ($criterion['status'] ?? ''));

        $total = $statuses->count();
        $meets = $statuses->filter(fn (string $status): bool => $status === 'meets')->count();
        $partial = $statuses->filter(fn (string $status): bool => $status === 'partially_meets')->count();
        $doesNotMeet = $statuses->filter(fn (string $status): bool => $status === 'does_not_meet')->count();
        $notEvidenced = $statuses->filter(fn (string $status): bool => $status === 'not_evidenced')->count();
        $notApplicable = $statuses->filter(fn (string $status): bool => $status === 'not_applicable')->count();
        $applicable = max(0, $total - $notApplicable);
        $evidenced = max(0, $applicable - $notEvidenced);

        return [
            'criteria_total' => $total,
            'meets' => $meets,
            'partially_meets' => $partial,
            'does_not_meet' => $doesNotMeet,
            'not_evidenced' => $notEvidenced,
            'not_applicable' => $notApplicable,
            'needs_attention' => $partial + $doesNotMeet + $notEvidenced,
            'compliance_percentage' => $applicable > 0
                ? (int) round((($meets + ($partial * 0.5)) / $applicable) * 100)
                : 0,
            'evidence_coverage_percentage' => $applicable > 0
                ? (int) round(($evidenced / $applicable) * 100)
                : 0,
            'miscellaneous_findings' => collect($report['miscellaneous_findings'] ?? [])
                ->filter(fn ($finding): bool => is_array($finding))
                ->count(),
        ];
    }
}
