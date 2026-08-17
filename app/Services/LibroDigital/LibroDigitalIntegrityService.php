<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\ReportExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LibroDigitalIntegrityService
{
    public function __construct(
        private readonly AuditIntegrityVerifier $audit,
        private readonly TeacherSignatureService $signatures,
    ) {}

    /** @return array{valid: bool, checked: array<string, int>, issues: array<int, array<string, mixed>>} */
    public function check(?int $schoolId = null): array
    {
        $issues = [];
        $checked = ['audit_events' => 0, 'signed_sessions' => 0, 'rosters' => 0, 'reports' => 0, 'ede_exports' => 0];
        $audit = $this->audit->verify($schoolId);
        $checked['audit_events'] = $audit['checked'];
        foreach ($audit['errors'] as $error) {
            $issues[] = ['area' => 'audit', ...$error];
        }

        ClassSession::query()->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))
            ->where('status', 'signed')->with('signatures')->chunkById(100, function ($sessions) use (&$issues, &$checked): void {
                foreach ($sessions as $session) {
                    $checked['signed_sessions']++;
                    $signature = $session->signatures
                        ->filter(fn ($item) => ($item->status instanceof \BackedEnum ? $item->status->value : $item->status) === 'verified')
                        ->sortByDesc('id')->first();
                    if (! $signature) {
                        $issues[] = ['area' => 'signature', 'code' => 'SIGNED_SESSION_WITHOUT_SIGNATURE', 'session' => $session->public_id];

                        continue;
                    }
                    try {
                        $current = $this->signatures->payloadHash($session);
                        if (! hash_equals((string) $signature->payload_hash, $current)) {
                            $issues[] = ['area' => 'signature', 'code' => 'SIGNED_PAYLOAD_CHANGED', 'session' => $session->public_id];
                        }
                    } catch (Throwable) {
                        $issues[] = ['area' => 'signature', 'code' => 'SIGNED_PAYLOAD_UNREADABLE', 'session' => $session->public_id];
                    }
                }
            });

        DB::table('lcd_roster_snapshots')->when($schoolId, fn ($query) => $query->whereIn('book_id', DB::table('lcd_books')->where('school_id', $schoolId)->select('id')))
            ->orderBy('id')->chunkById(500, function ($rosters) use (&$issues, &$checked): void {
                foreach ($rosters as $roster) {
                    $checked['rosters']++;
                    $actual = DB::table('lcd_roster_snapshot_items')->where('roster_snapshot_id', $roster->id)->count();
                    if ($actual !== (int) $roster->student_count) {
                        $issues[] = ['area' => 'roster', 'code' => 'ROSTER_COUNT_MISMATCH', 'roster_id' => $roster->public_id, 'expected' => $roster->student_count, 'actual' => $actual];
                    }
                }
            }, 'id');

        ReportExport::query()->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))->where('status', 'completed')
            ->chunkById(100, function ($reports) use (&$issues, &$checked): void {
                $disk = Storage::disk((string) config('libro_digital.storage.disk', 'local'));
                foreach ($reports as $report) {
                    $checked['reports']++;
                    if (! $report->private_path || ! $disk->exists($report->private_path)) {
                        $issues[] = ['area' => 'report', 'code' => 'REPORT_FILE_MISSING', 'report' => $report->public_id];

                        continue;
                    }
                    $contents = $disk->get($report->private_path);
                    if (! $report->sha256 || ! hash_equals((string) $report->sha256, hash('sha256', $contents))) {
                        $issues[] = ['area' => 'report', 'code' => 'REPORT_HASH_MISMATCH', 'report' => $report->public_id];
                    }
                }
            });

        $edeRows = DB::table('lcd_ede_exports')->when($schoolId, fn ($query) => $query->where('school_id', $schoolId))->where('status', 'validated')->get();
        foreach ($edeRows as $export) {
            $checked['ede_exports']++;
            $validRun = DB::table('lcd_ede_validation_runs')->where('ede_export_id', $export->id)->where('status', 'completed')->where('exit_code', 0)->whereNotNull('validator_image_digest')->exists();
            $passedResult = DB::table('lcd_ede_validation_results')->where('ede_export_id', $export->id)
                ->where('code', 'EDE_CHECK_PASSED')->where('severity', 'info')->exists();
            if (! $validRun || ! $passedResult || $export->validator_status !== 'passed' || ! $export->validated_at) {
                $issues[] = ['area' => 'ede', 'code' => 'EDE_VALIDATED_WITHOUT_EVIDENCE', 'export' => $export->public_id];
            }
        }

        return ['valid' => $issues === [], 'checked' => $checked, 'issues' => $issues];
    }
}
