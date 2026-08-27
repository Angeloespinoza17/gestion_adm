<?php

namespace App\Services\PedagogicalManagement;

use App\Enums\PedagogicalManagement\AiReportStatus;
use App\Models\PedagogicalManagement\PedagogicalInstrumentReview;
use App\Support\PedagogicalManagement\PedagogicalAiReportStatistics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PedagogicalReportProjectionService
{
    private const STATUSES = [
        'meets', 'partially_meets', 'does_not_meet', 'not_evidenced', 'not_applicable',
    ];

    private const MISC_CATEGORIES = [
        'arithmetic', 'internal_consistency', 'wording', 'presentation', 'other',
    ];

    private const SEVERITIES = ['critical', 'important', 'suggestion'];

    public function projectReview(PedagogicalInstrumentReview $review): ?int
    {
        $review->loadMissing(['instrument', 'instrumentFile', 'aiReport']);
        $report = $review->aiReport;
        if (! $report || $report->status !== AiReportStatus::Completed || ! is_array($report->report)) {
            return null;
        }

        $instrument = $review->instrument;
        $file = $review->instrumentFile;
        $statistics = PedagogicalAiReportStatistics::calculate($report->report);
        $criteriaCatalog = collect(config('pedagogical_management.review_criteria', []))->keyBy('code');
        $rubricVersion = (string) config('pedagogical_management.statistics.rubric_version', 'institutional-review-v1.0.0');
        $rubricHash = hash('sha256', json_encode($criteriaCatalog->values()->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
        $now = now();

        $snapshotId = DB::transaction(function () use ($review, $report, $instrument, $file, $statistics, $criteriaCatalog, $rubricVersion, $rubricHash, $now): int {
            $snapshot = DB::table('pedagogical_report_snapshots')->where('review_id', $review->id)->first();
            $payload = [
                'review_id' => $review->id,
                'ai_report_id' => $report->id,
                'school_id' => $instrument->school_id,
                'academic_year_id' => $instrument->academic_year_id,
                'instrument_id' => $instrument->id,
                'instrument_file_id' => $file->id,
                'owner_user_id' => $instrument->owner_user_id,
                'subject_id' => $instrument->subject_id,
                'reviewed_by' => $review->reviewed_by,
                'file_version' => $file->version,
                'decision' => $review->decision->value,
                'prompt_version' => $report->prompt_version,
                'rubric_version' => $rubricVersion,
                'rubric_hash' => $rubricHash,
                'criteria_total' => $statistics['criteria_total'],
                'applicable_count' => max(0, $statistics['criteria_total'] - $statistics['not_applicable']),
                'meets_count' => $statistics['meets'],
                'partially_meets_count' => $statistics['partially_meets'],
                'does_not_meet_count' => $statistics['does_not_meet'],
                'not_evidenced_count' => $statistics['not_evidenced'],
                'not_applicable_count' => $statistics['not_applicable'],
                'miscellaneous_count' => $statistics['miscellaneous_findings'],
                'compliance_percentage' => $statistics['compliance_percentage'],
                'evidence_coverage_percentage' => $statistics['evidence_coverage_percentage'],
                'submitted_at' => $file->created_at,
                'analyzed_at' => $report->finished_at,
                'reviewed_at' => $review->reviewed_at,
                'updated_at' => $now,
            ];

            if ($snapshot) {
                DB::table('pedagogical_report_snapshots')->where('id', $snapshot->id)->update($payload);
                $snapshotId = (int) $snapshot->id;
            } else {
                $snapshotId = (int) DB::table('pedagogical_report_snapshots')->insertGetId([
                    ...$payload,
                    'created_at' => $now,
                ]);
            }

            $criteria = collect($report->report['criteria_assessment'] ?? [])
                ->filter(fn ($item): bool => is_array($item) && trim((string) ($item['code'] ?? '')) !== '')
                ->map(function (array $item) use ($criteriaCatalog, $snapshotId, $now): array {
                    $code = trim((string) $item['code']);
                    $catalog = $criteriaCatalog->get($code, []);
                    $status = in_array($item['status'] ?? null, self::STATUSES, true)
                        ? (string) $item['status']
                        : 'not_evidenced';

                    return [
                        'snapshot_id' => $snapshotId,
                        'code' => $code,
                        'dimension' => trim((string) ($catalog['dimension'] ?? $item['dimension'] ?? 'Sin dimensión')),
                        'applicability' => trim((string) ($catalog['applicability'] ?? $item['applicability'] ?? '')) ?: null,
                        'criterion' => trim((string) ($catalog['criterion'] ?? $item['criterion'] ?? 'Criterio institucional')),
                        'status' => $status,
                        'status_score' => match ($status) {
                            'meets' => 1,
                            'partially_meets' => 0.5,
                            'does_not_meet', 'not_evidenced' => 0,
                            default => null,
                        },
                        'is_evidenced' => ! in_array($status, ['not_evidenced', 'not_applicable'], true),
                        'page_number' => is_numeric($item['page'] ?? null) ? max(1, (int) $item['page']) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->values()->all();

            if ($criteria !== []) {
                DB::table('pedagogical_report_criterion_snapshots')->upsert(
                    $criteria,
                    ['snapshot_id', 'code'],
                    ['dimension', 'applicability', 'criterion', 'status', 'status_score', 'is_evidenced', 'page_number', 'updated_at'],
                );
            }

            $miscellaneous = collect($report->report['miscellaneous_findings'] ?? [])
                ->filter(fn ($item): bool => is_array($item))
                ->values()
                ->map(function (array $item, int $index) use ($snapshotId, $now): array {
                    $category = in_array($item['category'] ?? null, self::MISC_CATEGORIES, true)
                        ? (string) $item['category']
                        : 'other';
                    $severity = in_array($item['severity'] ?? null, self::SEVERITIES, true)
                        ? (string) $item['severity']
                        : 'suggestion';

                    return [
                        'snapshot_id' => $snapshotId,
                        'position' => $index + 1,
                        'category' => $category,
                        'severity' => $severity,
                        'title' => mb_substr(trim((string) ($item['title'] ?? $item['finding'] ?? 'Hallazgo misceláneo')), 0, 191),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

            if ($miscellaneous !== []) {
                DB::table('pedagogical_report_misc_snapshots')->upsert(
                    $miscellaneous,
                    ['snapshot_id', 'position'],
                    ['category', 'severity', 'title', 'updated_at'],
                );
            }

            return $snapshotId;
        }, 3);

        Cache::forever('pedagogical-statistics:version', (string) now()->getTimestampMs());

        return $snapshotId;
    }
}
