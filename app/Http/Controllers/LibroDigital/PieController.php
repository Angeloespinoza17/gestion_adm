<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\StoreOperationalRecordRequest;
use App\Http\Requests\LibroDigital\UpdateOperationalRecordRequest;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EnrollmentLink;
use App\Models\LibroDigital\PieSupportRecord;
use App\Models\Staff;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use App\Services\LibroDigital\RecordRevisionWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class PieController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly RecordRevisionWriter $revisions,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->authorize('view', $bookModel);
        $this->assertPermission($request, $bookModel->school_id, false);
        $paginator = PieSupportRecord::query()->where('book_id', $bookModel->id)
            ->with(['student', 'professional', 'book.school'])->orderByDesc('recorded_at')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return $this->collectionResponse(
            $paginator->getCollection()->map(fn (PieSupportRecord $record): array => $this->payload($record))->all(),
            ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        );
    }

    public function store(StoreOperationalRecordRequest $request, string $book): JsonResponse
    {
        $bookModel = $this->book($book);
        $this->authorize('view', $bookModel);
        $this->assertPermission($request, $bookModel->school_id, true);
        $this->assertBookOpen($bookModel);
        $data = $request->validated();
        if (empty($data['student_profile_id'])) {
            throw new LibroDigitalException('El esquema normativo PIE exige asociar cada registro a un estudiante de la nómina.', 'LCD_PIE_STUDENT_REQUIRED');
        }
        $link = $this->rosterLink($bookModel, (int) $data['student_profile_id']);
        $professional = $this->professional($request, $data['professional_staff_id'] ?? null);
        $group = $bookModel->teachingGroups()->firstOrFail();
        $recordedAt = Carbon::createFromFormat('Y-m-d H:i:s', $data['occurred_on'].' 12:00:00', $bookModel->school()->value('timezone') ?: 'America/Santiago')->utc();

        $record = PieSupportRecord::query()->create([
            'school_id' => $bookModel->school_id,
            'book_id' => $bookModel->id,
            'academic_year_id' => $bookModel->academic_year_id,
            'teaching_group_id' => $group->id,
            'student_profile_id' => $link->student_profile_id,
            'student_enrollment_id' => $link->student_enrollment_id,
            'professional_staff_id' => $professional->id,
            'record_type' => $data['category'] ?: 'support_record',
            'recorded_at' => $recordedAt,
            'student_name_snapshot' => $link->student_name_snapshot,
            'professional_name_snapshot' => $professional->full_name,
            'objective_encrypted' => Crypt::encryptString($data['title']),
            'details_encrypted' => Crypt::encryptString($data['description']),
            'agreements_encrypted' => filled($data['actions'] ?? null) ? Crypt::encryptString($data['actions']) : null,
            'confidentiality_level' => $data['confidentiality_level'] ?? 'reserved',
            'status' => 'recorded',
            'revision' => 1,
            'created_by' => $request->user()->id,
        ]);
        $this->revisions->write($record, $record->school_id, 1, $request->user(), 'pie_record_created');
        $this->audit->write('lcd.pie.created', 'create', $record, actor: $request->user(), schoolId: $record->school_id, academicYearId: $record->academic_year_id, after: $this->auditPayload($record), request: $request);

        return $this->recordResponse($record, 201);
    }

    public function show(Request $request, string $pie): JsonResponse
    {
        $record = $this->aggregate(PieSupportRecord::class, $pie);
        $this->assertPermission($request, $record->school_id, false);

        return $this->recordResponse($record);
    }

    public function update(UpdateOperationalRecordRequest $request, string $pie): JsonResponse
    {
        $model = $this->aggregate(PieSupportRecord::class, $pie);
        $this->assertPermission($request, $model->school_id, true);
        $this->locks->assert($model, $request);
        $this->assertBookOpen($model->book);
        $data = $request->validated();
        $before = $this->auditPayload($model);

        DB::transaction(function () use ($request, $model, $data): void {
            $locked = PieSupportRecord::query()->lockForUpdate()->findOrFail($model->id);
            $this->locks->assert($locked, $request);
            $professional = array_key_exists('professional_staff_id', $data)
                ? $this->professional($request, $data['professional_staff_id'])
                : $locked->professional;
            $recordedAt = array_key_exists('occurred_on', $data)
                ? Carbon::createFromFormat('Y-m-d H:i:s', $data['occurred_on'].' 12:00:00', $locked->school()->value('timezone') ?: 'America/Santiago')->utc()
                : $locked->recorded_at;
            $locked->forceFill([
                'record_type' => array_key_exists('category', $data) ? ($data['category'] ?: 'support_record') : $locked->record_type,
                'recorded_at' => $recordedAt,
                'professional_staff_id' => $professional->id,
                'professional_name_snapshot' => $professional->full_name,
                'objective_encrypted' => array_key_exists('title', $data) ? Crypt::encryptString($data['title']) : $locked->objective_encrypted,
                'details_encrypted' => array_key_exists('description', $data) ? Crypt::encryptString($data['description']) : $locked->details_encrypted,
                'agreements_encrypted' => array_key_exists('actions', $data) ? (filled($data['actions']) ? Crypt::encryptString($data['actions']) : null) : $locked->agreements_encrypted,
                'confidentiality_level' => $data['confidentiality_level'] ?? $locked->confidentiality_level,
                'revision' => $locked->revision + 1,
            ])->save();
            $this->revisions->write($locked, $locked->school_id, $locked->revision, $request->user(), 'pie_record_updated');
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.pie.updated', 'update', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, before: $before, after: $this->auditPayload($fresh), request: $request, entityRevision: $fresh->revision);

        return $this->recordResponse($fresh);
    }

    private function assertPermission(Request $request, int $schoolId, bool $manage): void
    {
        $allowed = $manage
            ? $request->user()->hasPermission('libro_digital.pie.manage')
            : ($request->user()->hasPermission('libro_digital.pie.view') || $request->user()->hasPermission('libro_digital.pie.manage'));
        if (! $allowed || ! $this->access->canAccessSchool($request->user(), $schoolId)) {
            abort(403);
        }
    }

    private function assertBookOpen(Book $book): void
    {
        if ($this->statusValue($book->status) !== 'open') {
            throw new LibroDigitalException('Los registros PIE solo se modifican mientras el libro está abierto.', 'LCD_BOOK_NOT_OPEN', 409);
        }
    }

    private function rosterLink(Book $book, int $studentId): EnrollmentLink
    {
        $link = EnrollmentLink::query()->where('book_id', $book->id)->where('student_profile_id', $studentId)->where('status', 'active')->first();
        if (! $link) {
            throw new LibroDigitalException('El estudiante no pertenece a la nómina vigente del libro.', 'LCD_PIE_STUDENT_OUTSIDE_ROSTER');
        }

        return $link;
    }

    private function professional(Request $request, mixed $staffId): Staff
    {
        $staffId = $staffId ?: $request->user()->staff_id;
        if (! $staffId) {
            throw new LibroDigitalException('El registro PIE requiere identificar al profesional responsable.', 'LCD_PIE_PROFESSIONAL_REQUIRED');
        }

        return Staff::query()->where('active', true)->findOrFail((int) $staffId);
    }

    private function recordResponse(PieSupportRecord $record, int $status = 200): JsonResponse
    {
        $record->load(['student', 'professional', 'book']);

        return $this->dataResponse($this->payload($record), $status, $record->revision);
    }

    /** @return array<string, mixed> */
    private function payload(PieSupportRecord $record): array
    {
        return [
            'id' => $record->id,
            'public_id' => $record->public_id,
            'book_id' => $record->book_id,
            'student_profile_id' => $record->student_profile_id,
            'student' => $record->relationLoaded('student') ? ['id' => $record->student?->id, 'registered_name' => $record->student?->registered_name_resolved] : null,
            'professional_staff_id' => $record->professional_staff_id,
            'professional_name' => $record->professional_name_snapshot,
            'occurred_on' => $record->recorded_at?->setTimezone($record->book?->school?->timezone ?: 'America/Santiago')->format('Y-m-d'),
            'title' => $this->decrypt($record->objective_encrypted),
            'category' => $record->record_type,
            'description' => $this->decrypt($record->details_encrypted),
            'actions' => $this->decrypt($record->agreements_encrypted),
            'confidentiality_level' => $record->confidentiality_level,
            'status' => $record->status,
            'revision' => (int) $record->revision,
            'lock_version' => (int) $record->revision,
            'created_at' => $record->created_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditPayload(PieSupportRecord $record): array
    {
        return [
            'book_id' => $record->book_id,
            'student_profile_id' => $record->student_profile_id,
            'professional_staff_id' => $record->professional_staff_id,
            'record_type' => $record->record_type,
            'recorded_at' => $record->recorded_at?->toIso8601String(),
            'confidentiality_level' => $record->confidentiality_level,
            'status' => $record->status,
            'revision' => (int) $record->revision,
        ];
    }

    private function decrypt(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }
}
