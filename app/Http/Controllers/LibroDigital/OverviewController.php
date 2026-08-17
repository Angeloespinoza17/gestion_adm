<?php

namespace App\Http\Controllers\LibroDigital;

use App\Http\Resources\LibroDigital\BookResource;
use App\Http\Resources\LibroDigital\ClassSessionResource;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\ClassSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OverviewController extends LibroDigitalController
{
    public function __invoke(Request $request): JsonResponse
    {
        $school = $this->school($request);
        $date = Carbon::parse($request->input('date', 'today'), $school->timezone)->toDateString();
        $user = $request->user();

        $sessions = ClassSession::query()
            ->where('school_id', $school->id)
            ->whereDate('session_date', $date)
            ->when($request->integer('academic_year_id'), fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
            ->when($request->integer('book_id'), fn (Builder $query, int $id) => $query->where('book_id', $id))
            ->when(! $user->isSuperAdmin() && ! $user->hasPermission('libro_digital.closures.manage'), function (Builder $query) use ($user): void {
                $query->where(function (Builder $teachers) use ($user): void {
                    $teachers->where('actual_teacher_id', $user->staff_id)->orWhere('scheduled_teacher_id', $user->staff_id);
                });
            })
            ->with(['schoolDayBlock', 'actualTeacher', 'rosterSnapshot.items'])
            ->withCount('attendance')
            ->orderBy('scheduled_start_at')->get();

        $books = Book::query()->where('school_id', $school->id)
            ->when($request->integer('academic_year_id'), fn (Builder $query, int $id) => $query->where('academic_year_id', $id))
            ->whereIn('status', ['open', 'pending_preflight', 'temporarily_locked'])
            ->with($this->bookRelations())->withCount('sessions')->limit(12)->get();

        $unsigned = (clone $sessions)->whereNotIn('status', ['signed', 'closed', 'cancelled'])->count();
        $incompleteAttendance = $sessions->filter(function (ClassSession $session): bool {
            $expected = $session->rosterSnapshot?->items?->where('applicability_status', 'applicable')->count() ?? 0;

            return $expected > (int) $session->attendance_count;
        })->count();

        return $this->dataResponse([
            'date' => $date,
            'summary' => [
                'sessions' => $sessions->count(),
                'active_books' => $books->count(),
                'pending_signatures' => $unsigned,
                'incomplete_attendance' => $incompleteAttendance,
            ],
            'sessions' => ClassSessionResource::collection($sessions)->resolve($request),
            'books' => BookResource::collection($books)->resolve($request),
            'alerts' => collect([
                $incompleteAttendance ? ['type' => 'attendance', 'severity' => 'warning', 'message' => "Hay {$incompleteAttendance} sesiones con asistencia incompleta."] : null,
                $unsigned ? ['type' => 'signature', 'severity' => 'info', 'message' => "Hay {$unsigned} sesiones pendientes de firma o cierre."] : null,
            ])->filter()->values(),
            'capabilities' => $this->capabilities($request),
        ]);
    }

    /** @return array<int, string> */
    private function bookRelations(): array
    {
        return ['academicYear', 'courseSection', 'regulatoryProfile', 'teachingGroups.subject', 'teachingGroups.teacherAssignments.staff'];
    }
}
