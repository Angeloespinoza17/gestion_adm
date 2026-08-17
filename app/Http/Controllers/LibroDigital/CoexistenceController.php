<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\StoreOperationalRecordRequest;
use App\Http\Requests\LibroDigital\UpdateOperationalRecordRequest;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\CoexistenceEntry;
use App\Models\LibroDigital\EnrollmentLink;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use App\Services\LibroDigital\RecordRevisionWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoexistenceController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly CanonicalJson $canonical,
        private readonly RecordRevisionWriter $revisions,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request, int $student): JsonResponse
    {
        StudentProfile::query()->findOrFail($student);
        $school = $this->school($request);
        $this->assertPermission($request, $school->id, false);
        $paginator = CoexistenceEntry::query()->where('school_id', $school->id)->where('student_profile_id', $student)
            ->when($request->integer('academic_year_id'), fn ($query, int $yearId) => $query->where('academic_year_id', $yearId))
            ->with(['student', 'responsibleStaff'])->orderByDesc('happened_at')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return $this->collectionResponse(
            $paginator->getCollection()->map(fn (CoexistenceEntry $entry): array => $this->payload($entry))->all(),
            ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        );
    }

    public function store(StoreOperationalRecordRequest $request, int $student): JsonResponse
    {
        $data = $request->validated();
        if (! filled($data['book_id'] ?? null)) {
            throw new LibroDigitalException('Selecciona el libro que respalda la anotación.', 'LCD_COEXISTENCE_BOOK_REQUIRED');
        }
        $book = $this->book($data['book_id']);
        $this->authorize('view', $book);
        $this->assertPermission($request, $book->school_id, true);
        $this->assertBookOpen($book);
        if (isset($data['student_profile_id']) && (int) $data['student_profile_id'] !== $student) {
            throw new LibroDigitalException('El estudiante de la ruta no coincide con el contenido.', 'LCD_COEXISTENCE_STUDENT_MISMATCH');
        }
        $link = $this->rosterLink($book, $student);
        $group = $book->teachingGroups()->firstOrFail();
        $responsible = $this->responsible($request, $data['professional_staff_id'] ?? null);
        $happenedAt = Carbon::createFromFormat('Y-m-d H:i:s', $data['occurred_on'].' 12:00:00', $book->school()->value('timezone') ?: 'America/Santiago')->utc();

        $entry = CoexistenceEntry::query()->create([
            'school_id' => $book->school_id,
            'book_id' => $book->id,
            'academic_year_id' => $book->academic_year_id,
            'teaching_group_id' => $group->id,
            'student_profile_id' => $link->student_profile_id,
            'student_enrollment_id' => $link->student_enrollment_id,
            'course_section_id' => $link->course_section_id,
            'entry_type' => 'annotation',
            'category_code' => $data['category'] ?: null,
            'happened_at' => $happenedAt,
            'place_snapshot' => null,
            'student_name_snapshot' => $link->student_name_snapshot,
            'course_snapshot' => $link->course_snapshot,
            'description_encrypted' => Crypt::encryptString($this->canonical->encode(['title' => $data['title'], 'description' => $data['description']])),
            'immediate_action_encrypted' => filled($data['actions'] ?? null) ? Crypt::encryptString($data['actions']) : null,
            'confidentiality_level' => $data['confidentiality_level'] ?? 'restricted',
            'status' => 'recorded',
            'evidence' => null,
            'revision' => 1,
            'responsible_staff_id' => $responsible?->id,
            'created_by' => $request->user()->id,
        ]);
        $this->revisions->write($entry, $entry->school_id, 1, $request->user(), 'coexistence_entry_created');
        $this->audit->write('lcd.coexistence.created', 'create', $entry, actor: $request->user(), schoolId: $entry->school_id, academicYearId: $entry->academic_year_id, after: $this->auditPayload($entry), request: $request);

        return $this->entryResponse($entry, 201);
    }

    public function show(Request $request, string $coexistence): JsonResponse
    {
        $entry = $this->aggregate(CoexistenceEntry::class, $coexistence);
        $this->assertPermission($request, $entry->school_id, false);

        return $this->entryResponse($entry);
    }

    public function update(UpdateOperationalRecordRequest $request, string $coexistence): JsonResponse
    {
        $model = $this->aggregate(CoexistenceEntry::class, $coexistence);
        $this->assertPermission($request, $model->school_id, true);
        $this->locks->assert($model, $request);
        $this->assertBookOpen($model->book);
        $data = $request->validated();
        $before = $this->auditPayload($model);

        DB::transaction(function () use ($request, $model, $data): void {
            $locked = CoexistenceEntry::query()->lockForUpdate()->findOrFail($model->id);
            $this->locks->assert($locked, $request);
            $responsible = array_key_exists('professional_staff_id', $data)
                ? $this->responsible($request, $data['professional_staff_id'])
                : $locked->responsibleStaff;
            $happenedAt = array_key_exists('occurred_on', $data)
                ? Carbon::createFromFormat('Y-m-d H:i:s', $data['occurred_on'].' 12:00:00', $locked->book->school()->value('timezone') ?: 'America/Santiago')->utc()
                : $locked->happened_at;
            $content = $this->content($locked);
            if (array_key_exists('title', $data)) {
                $content['title'] = $data['title'];
            }
            if (array_key_exists('description', $data)) {
                $content['description'] = $data['description'];
            }
            $locked->forceFill([
                'category_code' => array_key_exists('category', $data) ? ($data['category'] ?: null) : $locked->category_code,
                'happened_at' => $happenedAt,
                'evidence' => null,
                'description_encrypted' => Crypt::encryptString($this->canonical->encode($content)),
                'immediate_action_encrypted' => array_key_exists('actions', $data) ? (filled($data['actions']) ? Crypt::encryptString($data['actions']) : null) : $locked->immediate_action_encrypted,
                'confidentiality_level' => $data['confidentiality_level'] ?? $locked->confidentiality_level,
                'responsible_staff_id' => $responsible?->id,
                'revision' => $locked->revision + 1,
            ])->save();
            $this->revisions->write($locked, $locked->school_id, $locked->revision, $request->user(), 'coexistence_entry_updated');
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.coexistence.updated', 'update', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, before: $before, after: $this->auditPayload($fresh), request: $request, entityRevision: $fresh->revision);

        return $this->entryResponse($fresh);
    }

    private function assertPermission(Request $request, int $schoolId, bool $manage): void
    {
        $allowed = $manage
            ? $request->user()->hasPermission('libro_digital.coexistence.manage')
            : ($request->user()->hasPermission('libro_digital.coexistence.view') || $request->user()->hasPermission('libro_digital.coexistence.manage'));
        if (! $allowed || ! $this->access->canAccessSchool($request->user(), $schoolId)) {
            abort(403);
        }
    }

    private function assertBookOpen(Book $book): void
    {
        if ($this->statusValue($book->status) !== 'open') {
            throw new LibroDigitalException('Las anotaciones solo se modifican mientras el libro está abierto.', 'LCD_BOOK_NOT_OPEN', 409);
        }
    }

    private function rosterLink(Book $book, int $studentId): EnrollmentLink
    {
        $link = EnrollmentLink::query()->where('book_id', $book->id)->where('student_profile_id', $studentId)->where('status', 'active')->first();
        if (! $link) {
            throw new LibroDigitalException('El estudiante no pertenece a la nómina del libro seleccionado.', 'LCD_COEXISTENCE_STUDENT_OUTSIDE_ROSTER');
        }

        return $link;
    }

    private function responsible(Request $request, mixed $staffId): ?Staff
    {
        $staffId = $staffId ?: $request->user()->staff_id;

        return $staffId ? Staff::query()->where('active', true)->findOrFail((int) $staffId) : null;
    }

    private function entryResponse(CoexistenceEntry $entry, int $status = 200): JsonResponse
    {
        $entry->load(['student', 'responsibleStaff', 'book.school']);

        return $this->dataResponse($this->payload($entry), $status, $entry->revision);
    }

    /** @return array<string, mixed> */
    private function payload(CoexistenceEntry $entry): array
    {
        $content = $this->content($entry);

        return [
            'id' => $entry->id,
            'public_id' => $entry->public_id,
            'book_id' => $entry->book_id,
            'student_profile_id' => $entry->student_profile_id,
            'student' => $entry->relationLoaded('student') ? ['id' => $entry->student?->id, 'registered_name' => $entry->student?->registered_name_resolved] : null,
            'occurred_on' => $entry->happened_at?->setTimezone($entry->book?->school?->timezone ?: 'America/Santiago')->format('Y-m-d'),
            'title' => $content['title'] ?? $entry->place_snapshot,
            'category' => $entry->category_code,
            'description' => $content['description'] ?? null,
            'actions' => $this->decrypt($entry->immediate_action_encrypted),
            'confidentiality_level' => $entry->confidentiality_level,
            'status' => $entry->status,
            'responsible_staff_id' => $entry->responsible_staff_id,
            'revision' => (int) $entry->revision,
            'lock_version' => (int) $entry->revision,
            'created_at' => $entry->created_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditPayload(CoexistenceEntry $entry): array
    {
        return [
            'book_id' => $entry->book_id,
            'student_profile_id' => $entry->student_profile_id,
            'entry_type' => $entry->entry_type,
            'category_code' => $entry->category_code,
            'happened_at' => $entry->happened_at?->toIso8601String(),
            'confidentiality_level' => $entry->confidentiality_level,
            'status' => $entry->status,
            'revision' => (int) $entry->revision,
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

    /** @return array{title?: string|null, description?: string|null} */
    private function content(CoexistenceEntry $entry): array
    {
        $plain = $this->decrypt($entry->description_encrypted);
        $decoded = $plain ? json_decode($plain, true) : null;
        if (is_array($decoded)) {
            return $decoded;
        }

        return ['title' => $entry->place_snapshot, 'description' => $plain];
    }
}
