<?php

namespace App\Http\Controllers\LibroDigital;

use App\Contracts\LibroDigital\SigeIntegrationGateway;
use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\CloseDailyAttendanceRequest;
use App\Http\Requests\LibroDigital\CloseMonthlyAttendanceRequest;
use App\Http\Requests\LibroDigital\ReconcileAttendanceRequest;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\DailyAttendanceClosure;
use App\Models\LibroDigital\MonthlyAttendanceClosure;
use App\Models\LibroDigital\TeachingGroup;
use App\Services\LibroDigital\AttendanceClosureService;
use App\Services\LibroDigital\FeatureFlagService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceClosureController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly AttendanceClosureService $closures,
        private readonly SigeIntegrationGateway $sige,
        private readonly FeatureFlagService $features,
    ) {
        parent::__construct($access);
    }

    public function closeDay(CloseDailyAttendanceRequest $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->assertMayClose($request, $bookModel);
        $this->locks->assert($bookModel, $request);
        $group = $this->group($bookModel, $request->integer('teaching_group_id'));
        $closure = $this->closures->closeDay($bookModel, $group, $request->validated('date'), $request->user(), $request);

        return $this->dataResponse($this->dailyPayload($closure), 201, $closure->revision);
    }

    public function closeMonth(CloseMonthlyAttendanceRequest $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->assertMayClose($request, $bookModel);
        $this->locks->assert($bookModel, $request);
        $group = $this->group($bookModel, $request->integer('teaching_group_id'));
        $month = (int) $request->validated('month');
        $closure = $this->closures->closeMonth($bookModel, $group, $month, $request->user(), $request);

        return $this->dataResponse($this->monthlyPayload($closure), 201, $closure->revision);
    }

    public function reconcile(ReconcileAttendanceRequest $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->assertMayClose($request, $bookModel);
        $this->locks->assert($bookModel, $request);
        if (! $this->features->enabled('lcd_sige_reconciliation_enabled', $bookModel->school_id)) {
            throw new LibroDigitalException('La conciliación SIGE no está habilitada para este establecimiento.', 'LCD_SIGE_RECONCILIATION_FEATURE_DISABLED', 503);
        }
        if ($this->sige->driver() !== 'manual') {
            throw new LibroDigitalException('No existe un driver de conciliación manual aprobado.', 'COMPLIANCE_BLOCKER_SIGE_DISABLED', 409);
        }
        $month = (int) $request->validated('month');
        if (! MonthlyAttendanceClosure::query()->where('book_id', $bookModel->id)->where('month', $month)->whereIn('status', ['closed', 'reclosed'])->exists()) {
            throw new LibroDigitalException('Cierra la asistencia mensual antes de conciliar evidencia externa.', 'LCD_RECONCILIATION_MONTHLY_CLOSURE_REQUIRED', 409);
        }
        $result = $this->sige->reconcile($bookModel, $request->safe()->except('lock_version'), $request->user(), $request);
        $record = $result['data'];

        return $this->dataResponse([
            'id' => $record->id,
            'public_id' => $record->public_id,
            'book_id' => $record->book_id,
            'month' => (int) $record->month,
            'external_source' => $record->external_source,
            'status' => $record->status,
            'official' => (bool) ($result['official'] ?? false),
            'official_submission_performed' => false,
            'differences' => $result['differences'] ?? [],
            'created_at' => $record->created_at?->toIso8601String(),
        ], 201);
    }

    private function assertMayClose(Request $request, Book $book): void
    {
        if (! $request->user()->hasPermission('libro_digital.closures.manage') || ! $this->access->canAccessSchool($request->user(), (int) $book->school_id)) {
            abort(403);
        }
    }

    private function group(Book $book, int $groupId = 0): TeachingGroup
    {
        $query = $book->teachingGroups();
        if ($groupId) {
            $query->whereKey($groupId);
        }
        $groups = $query->limit(2)->get();
        if ($groups->count() !== 1) {
            throw new LibroDigitalException(
                $groups->isEmpty() ? 'El grupo no pertenece al libro.' : 'Selecciona el grupo docente que deseas cerrar.',
                $groups->isEmpty() ? 'LCD_GROUP_BOOK_MISMATCH' : 'LCD_TEACHING_GROUP_REQUIRED',
                $groups->isEmpty() ? 403 : 422,
            );
        }

        return $groups->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function dailyPayload(DailyAttendanceClosure $closure): array
    {
        return [
            'id' => $closure->id,
            'public_id' => $closure->public_id,
            'book_id' => $closure->book_id,
            'teaching_group_id' => $closure->teaching_group_id,
            'date' => $closure->closure_date?->format('Y-m-d'),
            'status' => $this->statusValue($closure->status),
            'expected_count' => (int) $closure->expected_count,
            'present_count' => (int) $closure->present_count,
            'absent_count' => (int) $closure->absent_count,
            'late_count' => (int) $closure->late_count,
            'not_applicable_count' => (int) $closure->not_applicable_count,
            'snapshot_hash' => $closure->snapshot_hash,
            'revision' => (int) $closure->revision,
            'lock_version' => (int) $closure->revision,
            'closed_at' => $closure->closed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function monthlyPayload(MonthlyAttendanceClosure $closure): array
    {
        return [
            'id' => $closure->id,
            'public_id' => $closure->public_id,
            'book_id' => $closure->book_id,
            'teaching_group_id' => $closure->teaching_group_id,
            'academic_year_id' => $closure->academic_year_id,
            'month' => (int) $closure->month,
            'status' => $this->statusValue($closure->status),
            'school_days_count' => (int) $closure->school_days_count,
            'expected_total' => (int) $closure->expected_total,
            'present_total' => (int) $closure->present_total,
            'absent_total' => (int) $closure->absent_total,
            'attendance_percentage' => $closure->attendance_percentage !== null ? (float) $closure->attendance_percentage : null,
            'snapshot_hash' => $closure->snapshot_hash,
            'revision' => (int) $closure->revision,
            'lock_version' => (int) $closure->revision,
            'closed_at' => $closure->closed_at?->toIso8601String(),
        ];
    }
}
