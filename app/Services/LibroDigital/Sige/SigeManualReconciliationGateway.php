<?php

namespace App\Services\LibroDigital\Sige;

use App\Contracts\LibroDigital\SigeIntegrationGateway;
use App\Models\LibroDigital\AttendanceReconciliation;
use App\Models\LibroDigital\Book;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use Illuminate\Http\Request;

class SigeManualReconciliationGateway implements SigeIntegrationGateway
{
    public function __construct(
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
    ) {}

    public function reconcile(Book $book, array $input, User $actor, ?Request $request = null): array
    {
        $month = (int) ($input['month'] ?? 0);
        $external = collect($input['rows'] ?? [])->keyBy(fn ($row) => (string) ($row['student_reference'] ?? ''));
        $groups = $book->teachingGroups()->with(['enrollmentLinks'])->get();
        $local = $groups->flatMap(fn ($group) => $group->enrollmentLinks ?? [])->keyBy(fn ($link) => (string) $link->public_id);
        $differences = [];
        foreach ($local as $reference => $link) {
            $externalRow = $external->get($reference);
            if (! $externalRow) {
                $differences[] = ['student_reference' => $reference, 'code' => 'MISSING_EXTERNAL_ROW'];

                continue;
            }
        }
        foreach ($groups as $group) {
            $closure = $book->monthlyAttendanceClosures()->where('teaching_group_id', $group->id)->where('month', $month)->latest('revision')->first();
            $externalGroup = collect($input['groups'] ?? [])->firstWhere('teaching_group_public_id', $group->public_id);
            if (! $closure || ! $externalGroup) {
                $differences[] = ['teaching_group_public_id' => $group->public_id, 'code' => ! $closure ? 'MISSING_LOCAL_CLOSURE' : 'MISSING_EXTERNAL_GROUP'];

                continue;
            }
            foreach (['expected_total', 'present_total', 'absent_total'] as $field) {
                if ((int) $closure->{$field} !== (int) ($externalGroup[$field] ?? 0)) {
                    $differences[] = ['teaching_group_public_id' => $group->public_id, 'code' => strtoupper($field).'_MISMATCH', 'local' => (int) $closure->{$field}, 'external' => (int) ($externalGroup[$field] ?? 0)];
                }
            }
        }

        $reconciliation = AttendanceReconciliation::query()->create([
            'school_id' => $book->school_id,
            'book_id' => $book->id,
            'academic_year_id' => $book->academic_year_id,
            'month' => $month,
            'external_source' => 'sige_manual_evidence',
            'status' => $differences === [] ? 'review_required' : 'pending',
            'discrepancies' => $differences,
            'resolution' => [
                'evidence_reference' => $input['evidence_reference'] ?? null,
                'external_snapshot_hash' => $this->canonical->hash(['rows' => $input['rows'] ?? [], 'groups' => $input['groups'] ?? []]),
                'official_confirmation' => false,
            ],
        ]);
        $this->audit->write('lcd.sige.manual_reconciliation_created', 'reconcile', $reconciliation, actor: $actor, schoolId: $book->school_id, academicYearId: $book->academic_year_id, after: ['public_id' => $reconciliation->public_id, 'month' => $month, 'difference_count' => count($differences), 'official_confirmation' => false], request: $request);

        return ['data' => $reconciliation, 'official' => false, 'differences' => $differences];
    }

    public function driver(): string
    {
        return 'manual';
    }
}
