<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\ApplyAmendmentRequest;
use App\Http\Requests\LibroDigital\ReviewAmendmentRequest;
use App\Http\Requests\LibroDigital\StoreAmendmentRequest;
use App\Models\LibroDigital\AbsenceCase;
use App\Models\LibroDigital\AmendmentRequest;
use App\Models\LibroDigital\Assessment;
use App\Models\LibroDigital\ClassSession;
use App\Models\LibroDigital\CoexistenceEntry;
use App\Models\LibroDigital\ParvulariaEvaluation;
use App\Models\LibroDigital\ParvulariaPlan;
use App\Models\LibroDigital\PieSupportRecord;
use App\Models\LibroDigital\SessionAttendance;
use App\Models\LibroDigital\StudentResult;
use App\Services\LibroDigital\AmendmentService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;

class AmendmentController extends LibroDigitalController
{
    /** @var array<string, class-string<Model>> */
    private const ENTITY_TYPES = [
        'session' => ClassSession::class,
        'session_attendance' => SessionAttendance::class,
        'assessment' => Assessment::class,
        'student_result' => StudentResult::class,
        'coexistence_entry' => CoexistenceEntry::class,
        'pie_support_record' => PieSupportRecord::class,
        'absence_case' => AbsenceCase::class,
        'parvularia_plan' => ParvulariaPlan::class,
        'parvularia_evaluation' => ParvulariaEvaluation::class,
    ];

    /** @var array<string, list<string>> */
    private const AMENDABLE_FIELDS = [
        'session' => ['objective_summary', 'content_summary', 'activity_summary', 'observation'],
        'session_attendance' => ['status', 'arrival_at', 'departure_at', 'justification_status', 'notes'],
        'assessment' => ['name', 'description', 'assessment_type', 'assessment_date', 'weight', 'maximum_score'],
        'student_result' => ['status', 'raw_score', 'numeric_value', 'qualitative_value', 'absent', 'exempt', 'observation'],
        'coexistence_entry' => ['category_code', 'happened_at', 'description_encrypted', 'immediate_action_encrypted', 'confidentiality_level'],
        'pie_support_record' => ['record_type', 'recorded_at', 'objective_encrypted', 'details_encrypted', 'agreements_encrypted', 'confidentiality_level'],
        'absence_case' => ['risk_level', 'next_deadline_on', 'normative_basis'],
        'parvularia_plan' => ['plan_type', 'horizon', 'title', 'learning_experience', 'pedagogical_strategies', 'starts_on', 'ends_on'],
        'parvularia_evaluation' => ['evaluation_type', 'observed_at', 'indicator_snapshot', 'achievement_level', 'observation_encrypted', 'analysis_encrypted', 'feedback_encrypted', 'pedagogical_decision_encrypted'],
    ];

    /** @var list<string> */
    private const ENCRYPTED_FIELDS = [
        'description_encrypted', 'immediate_action_encrypted', 'objective_encrypted', 'details_encrypted',
        'agreements_encrypted', 'observation_encrypted', 'analysis_encrypted', 'feedback_encrypted',
        'pedagogical_decision_encrypted',
    ];

    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly AmendmentService $amendments,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $school = $this->school($request);
        $this->assertAnyPermission($request, ['libro_digital.amendments.request', 'libro_digital.amendments.review', 'libro_digital.amendments.apply']);
        $query = AmendmentRequest::query()->where('school_id', $school->id)
            ->when($request->integer('book_id'), fn (Builder $builder, int $bookId) => $builder->where('book_id', $bookId))
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->input('status')))
            ->with(['requestedBy:id,name', 'approvals.decider:id,name']);
        if (! $request->user()->hasPermission('libro_digital.amendments.review') && ! $request->user()->hasPermission('libro_digital.amendments.apply')) {
            $query->where('requested_by', $request->user()->id);
        }
        $paginator = $query->orderByDesc('requested_at')->orderByDesc('id')
            ->paginate(min(200, max(1, $request->integer('per_page', 50))));

        return $this->collectionResponse(
            $paginator->getCollection()->map(fn (AmendmentRequest $amendment): array => $this->payload($amendment))->all(),
            ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        );
    }

    public function store(StoreAmendmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $book = $this->book($data['book_id']);
        $this->authorize('view', $book);
        $type = $data['entity_type'];
        $record = $this->aggregate(self::ENTITY_TYPES[$type], $data['entity_id']);
        if ($this->schoolId($record) !== (int) $book->school_id || $this->bookId($record) !== (int) $book->id) {
            throw new LibroDigitalException('La entidad no pertenece al libro seleccionado.', 'LCD_AMENDMENT_ENTITY_SCOPE_INVALID', 403);
        }
        $revision = (int) ($record->getAttribute('revision') ?? 1);
        if ((int) $data['original_revision'] !== $revision) {
            throw new LibroDigitalException('La entidad cambió desde que preparaste la corrección.', 'LCD_AMENDMENT_REVISION_CONFLICT', 409);
        }
        $proposed = $this->validatedProposal($type, $record, $data['proposed']);
        $amendment = $this->amendments->request(
            $record,
            (int) $book->school_id,
            $book,
            $request->user(),
            $proposed,
            $data['reason'],
            $data['section'] ?? null,
            $data['field'] ?? null,
            $request,
        );

        return $this->dataResponse($this->payload($amendment->load(['requestedBy:id,name', 'approvals'])), 201);
    }

    public function approve(ReviewAmendmentRequest $request, string $amendment): JsonResponse
    {
        return $this->review($request, $amendment, true);
    }

    public function reject(ReviewAmendmentRequest $request, string $amendment): JsonResponse
    {
        return $this->review($request, $amendment, false);
    }

    public function apply(ApplyAmendmentRequest $request, string $amendment): JsonResponse
    {
        $model = $this->amendment($amendment);
        $this->assertScope($request, $model);
        if ((int) $model->requested_by === (int) $request->user()->id) {
            throw new LibroDigitalException('La persona solicitante no puede aplicar su propia corrección.', 'LCD_AMENDMENT_SEPARATION_OF_DUTIES', 403);
        }
        if ((int) $model->reviewed_by === (int) $request->user()->id) {
            throw new LibroDigitalException('La persona revisora no puede aplicar la misma corrección.', 'LCD_AMENDMENT_SEPARATION_OF_DUTIES', 403);
        }
        $revision = $this->amendments->apply($model, $request->user(), $request);

        return $this->dataResponse([
            'amendment' => $this->payload($model->fresh()->load(['requestedBy:id,name', 'approvals.decider:id,name'])),
            'revision' => [
                'id' => $revision->id,
                'public_id' => $revision->public_id,
                'revision' => (int) $revision->revision,
                'payload_hash' => $revision->payload_hash,
                'created_at' => $revision->created_at?->toIso8601String(),
            ],
        ]);
    }

    private function review(ReviewAmendmentRequest $request, string $identifier, bool $approve): JsonResponse
    {
        $model = $this->amendment($identifier);
        $this->assertScope($request, $model);
        if ((int) $model->requested_by === (int) $request->user()->id) {
            throw new LibroDigitalException('La persona solicitante no puede revisar su propia corrección.', 'LCD_AMENDMENT_SEPARATION_OF_DUTIES', 403);
        }
        $reviewed = $this->amendments->review($model, $request->user(), $approve, $request->validated('note'), $request);

        return $this->dataResponse($this->payload($reviewed->load(['requestedBy:id,name', 'approvals.decider:id,name'])));
    }

    private function assertScope(Request $request, AmendmentRequest $amendment): void
    {
        if (! $this->access->canAccessSchool($request->user(), (int) $amendment->school_id)) {
            abort(403);
        }
    }

    private function assertAnyPermission(Request $request, array $permissions): void
    {
        if (! collect($permissions)->contains(fn (string $permission): bool => $request->user()->hasPermission($permission))) {
            abort(403);
        }
    }

    /** @param array<string, mixed> $proposed @return array<string, mixed> */
    private function validatedProposal(string $type, Model $record, array $proposed): array
    {
        $allowed = self::AMENDABLE_FIELDS[$type];
        $unknown = array_diff(array_keys($proposed), $allowed);
        if ($unknown !== []) {
            throw new LibroDigitalException('La corrección contiene campos no autorizados.', 'LCD_AMENDMENT_FIELDS_INVALID', 422, collect($unknown)->map(fn (string $field): array => ['field' => 'proposed.'.$field, 'reason' => 'Campo no enmendable por esta API.'])->values()->all());
        }
        $this->validateProposalValues($type, $record, $proposed);
        $normalized = [];
        foreach ($proposed as $field => $value) {
            if (in_array($field, self::ENCRYPTED_FIELDS, true)) {
                if (! is_string($value) && $value !== null) {
                    throw new LibroDigitalException('El contenido sensible debe ser texto o nulo.', 'LCD_AMENDMENT_ENCRYPTED_VALUE_INVALID');
                }
                $normalized[$field] = $value === null ? null : Crypt::encryptString($value);
            } else {
                $normalized[$field] = $value;
            }
        }
        if ($normalized === [] || collect($normalized)->every(fn (mixed $value, string $field): bool => $record->getAttribute($field) === $value)) {
            throw new LibroDigitalException('La propuesta no contiene cambios efectivos.', 'LCD_AMENDMENT_NO_CHANGES');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $proposed */
    private function validateProposalValues(string $type, Model $record, array $proposed): void
    {
        $rules = match ($type) {
            'session' => [
                'objective_summary' => ['nullable', 'string', 'max:5000'],
                'content_summary' => ['nullable', 'string', 'max:20000'],
                'activity_summary' => ['nullable', 'string', 'max:20000'],
                'observation' => ['nullable', 'string', 'max:20000'],
            ],
            'session_attendance' => [
                'status' => ['sometimes', 'string', 'in:present,absent,late,left_early,not_applicable'],
                'arrival_at' => ['sometimes', 'nullable', 'date'],
                'departure_at' => ['sometimes', 'nullable', 'date'],
                'justification_status' => ['sometimes', 'string', 'in:not_required,pending,accepted,rejected'],
                'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ],
            'assessment' => [
                'name' => ['sometimes', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'assessment_type' => ['sometimes', 'string', 'max:60'],
                'assessment_date' => ['sometimes', 'date'],
                'weight' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
                'maximum_score' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            ],
            'student_result' => [
                'status' => ['sometimes', 'string', 'in:pending,recorded,absent,exempt'],
                'raw_score' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'numeric_value' => ['sometimes', 'nullable', 'numeric'],
                'qualitative_value' => ['sometimes', 'nullable', 'string', 'max:120'],
                'absent' => ['sometimes', 'boolean'],
                'exempt' => ['sometimes', 'boolean'],
                'observation' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ],
            'coexistence_entry' => [
                'category_code' => ['sometimes', 'string', 'max:80'],
                'happened_at' => ['sometimes', 'date'],
                'description_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'immediate_action_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'confidentiality_level' => ['sometimes', 'string', 'in:general,reserved,restricted,high_confidentiality'],
            ],
            'pie_support_record' => [
                'record_type' => ['sometimes', 'string', 'max:80'],
                'recorded_at' => ['sometimes', 'date'],
                'objective_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'details_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'agreements_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'confidentiality_level' => ['sometimes', 'string', 'in:general,reserved,restricted,high_confidentiality'],
            ],
            'absence_case' => [
                'risk_level' => ['sometimes', 'string', 'max:40'],
                'next_deadline_on' => ['sometimes', 'nullable', 'date'],
                'normative_basis' => ['sometimes', 'nullable', 'string', 'max:3000'],
            ],
            'parvularia_plan' => [
                'plan_type' => ['sometimes', 'string', 'max:80'],
                'horizon' => ['sometimes', 'string', 'max:80'],
                'title' => ['sometimes', 'string', 'max:255'],
                'learning_experience' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'pedagogical_strategies' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'starts_on' => ['sometimes', 'date'],
                'ends_on' => ['sometimes', 'nullable', 'date'],
            ],
            'parvularia_evaluation' => [
                'evaluation_type' => ['sometimes', 'string', 'max:80'],
                'observed_at' => ['sometimes', 'date'],
                'indicator_snapshot' => ['sometimes', 'nullable', 'string', 'max:255'],
                'achievement_level' => ['sometimes', 'nullable', 'string', 'max:120'],
                'observation_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'analysis_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'feedback_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
                'pedagogical_decision_encrypted' => ['sometimes', 'nullable', 'string', 'max:20000'],
            ],
        };
        Validator::make($proposed, $rules)->validate();

        if ($type === 'session_attendance') {
            $status = (string) ($proposed['status'] ?? $this->statusValue($record->getAttribute('status')));
            if ($status === 'late' && blank($proposed['arrival_at'] ?? $record->getAttribute('arrival_at'))) {
                throw new LibroDigitalException('Un atraso requiere hora de llegada.', 'LCD_ATTENDANCE_ARRIVAL_REQUIRED', 422);
            }
            if ($status === 'left_early' && blank($proposed['departure_at'] ?? $record->getAttribute('departure_at'))) {
                throw new LibroDigitalException('Un retiro anticipado requiere hora de salida.', 'LCD_ATTENDANCE_DEPARTURE_REQUIRED', 422);
            }
        }
        if ($type === 'student_result') {
            $absent = (bool) ($proposed['absent'] ?? $record->getAttribute('absent'));
            $exempt = (bool) ($proposed['exempt'] ?? $record->getAttribute('exempt'));
            if ($absent && $exempt) {
                throw new LibroDigitalException('Un resultado no puede estar ausente y eximido a la vez.', 'LCD_RESULT_STATUS_CONFLICT', 422);
            }
        }
    }

    private function schoolId(Model $record): int
    {
        $schoolId = $record->getAttribute('school_id');
        if ($schoolId !== null) {
            return (int) $schoolId;
        }
        if ($record instanceof SessionAttendance) {
            return (int) $record->classSession()->value('school_id');
        }
        if ($record instanceof StudentResult) {
            return (int) $record->assessment()->value('school_id');
        }

        return 0;
    }

    private function bookId(Model $record): int
    {
        $bookId = $record->getAttribute('book_id');
        if ($bookId !== null) {
            return (int) $bookId;
        }
        if ($record instanceof SessionAttendance) {
            return (int) $record->classSession()->value('book_id');
        }
        if ($record instanceof StudentResult) {
            return (int) $record->assessment()->value('book_id');
        }

        return 0;
    }

    private function amendment(string|int $identifier): AmendmentRequest
    {
        return $this->aggregate(AmendmentRequest::class, $identifier);
    }

    /** @return array<string, mixed> */
    private function payload(AmendmentRequest $amendment): array
    {
        $entityType = array_search($amendment->amendable_type, self::ENTITY_TYPES, true) ?: $amendment->amendable_type;

        return [
            'id' => $amendment->id,
            'public_id' => $amendment->public_id,
            'school_id' => $amendment->school_id,
            'book_id' => $amendment->book_id,
            'entity_type' => $entityType,
            'entity_id' => $amendment->amendable_id,
            'original_revision' => (int) $amendment->original_revision,
            'section' => $amendment->section,
            'field' => $amendment->field,
            'reason' => $amendment->reason,
            'original' => $this->originalValues($amendment),
            'proposed' => $this->readableProposal($amendment->proposed_snapshot ?? []),
            'status' => $this->statusValue($amendment->status),
            'requires_signature' => $amendment->requires_signature,
            'requested_by' => $amendment->requested_by,
            'requester_name' => $amendment->requestedBy?->name,
            'requested_at' => $amendment->requested_at?->toIso8601String(),
            'review_note' => $amendment->review_note,
            'reviewed_at' => $amendment->reviewed_at?->toIso8601String(),
            'applied_revision' => $amendment->applied_revision,
            'applied_at' => $amendment->applied_at?->toIso8601String(),
            'approvals' => $amendment->relationLoaded('approvals') ? $amendment->approvals->map(fn ($approval): array => [
                'id' => $approval->id,
                'required_role' => $approval->required_role,
                'decision' => $approval->decision,
                'decided_by' => $approval->decided_by,
                'decider_name' => $approval->decider?->name,
                'decided_at' => $approval->decided_at?->toIso8601String(),
                'comment' => $approval->comment,
            ])->values()->all() : [],
        ];
    }

    /** @param array<string, mixed> $proposal @return array<string, mixed> */
    private function readableProposal(array $proposal): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (! array_key_exists($field, $proposal)) {
                continue;
            }
            $proposal[$field] = $proposal[$field] === null ? null : '[contenido sensible cifrado]';
        }

        return $proposal;
    }

    /** @return array<string, mixed> */
    private function originalValues(AmendmentRequest $amendment): array
    {
        $before = $amendment->before_snapshot ?? [];
        $keys = array_keys($amendment->proposed_snapshot ?? []);

        return $this->readableProposal(collect($keys)->mapWithKeys(fn (string $key): array => [$key => $before[$key] ?? null])->all());
    }
}
