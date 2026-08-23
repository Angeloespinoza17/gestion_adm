<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\VersionConflictException;
use App\Http\Requests\LibroDigital\BulkSubjectStatusRequest;
use App\Http\Requests\LibroDigital\StoreSubjectRequest;
use App\Http\Requests\LibroDigital\UpdateExternalSubjectMappingsRequest;
use App\Http\Requests\LibroDigital\UpdateSubjectRequest;
use App\Http\Resources\LibroDigital\SubjectResource;
use App\Models\LibroDigital\SubjectCatalogProfile;
use App\Models\LibroDigital\SubjectExternalAlias;
use App\Models\Schedule\ScheduleSubject;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\SubjectNameNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectController extends LibroDigitalController
{
    /**
     * ScheduleSubject is a legacy, institution-wide catalog. Mutations are
     * deliberately governed by a dedicated global-catalog permission; no
     * school-scoped ownership is claimed where the source table has none.
     */
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly AuditEventWriter $audit,
        private readonly SubjectNameNormalizer $normalizer,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduleSubject::class);
        $school = $this->school($request);
        $subjects = ScheduleSubject::query()
            ->with([
                'catalogProfile',
                'externalAliases' => fn ($query) => $query
                    ->where('school_id', $school->id)
                    ->where('source_system', 'legacy_gradebook')
                    ->where('active', true)
                    ->orderBy('external_name'),
            ])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->toString()).'%';
                $query->where(fn (Builder $search) => $search
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhereHas('catalogProfile', fn (Builder $profile) => $profile->where('display_name', 'like', $term)));
            })
            ->when($request->has('active'), fn (Builder $query) => $query->where('active', $request->boolean('active')))
            ->orderBy('name')->get();

        $gradeCodes = DB::table('lcd_learning_objectives')
            ->whereIn('schedule_subject_id', $subjects->pluck('id'))
            ->where('active', true)
            ->whereNotNull('grade_code')
            ->distinct()
            ->get(['schedule_subject_id', 'grade_code'])
            ->groupBy('schedule_subject_id');
        $subjects->each(function (ScheduleSubject $subject) use ($gradeCodes): void {
            $types = $gradeCodes->get($subject->id, collect())
                ->map(fn ($row): ?string => $this->educationTypeFromGrade((string) $row->grade_code))
                ->filter()->unique()->sort()->values()->all();
            $subject->setAttribute('derived_education_types', $types);
        });
        $subjects = $subjects->sortBy(fn (ScheduleSubject $subject): string => $this->normalizer->normalize($subject->resolvedDisplayName()))->values();

        return $this->collectionResponse(SubjectResource::collection($subjects)->resolve($request), [
            'education_types' => $this->educationTypeOptions(),
            'subject_types' => $this->subjectTypeOptions(),
        ]);
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $this->authorize('create', ScheduleSubject::class);
        $school = $this->school($request);
        $data = $request->safe()->only(['name', 'code', 'area', 'color', 'active']);
        $profileData = $request->safe()->only(['display_name', 'subject_type', 'description', 'education_types']);
        $subject = DB::transaction(function () use ($request, $data, $profileData): ScheduleSubject {
            $subject = ScheduleSubject::query()->create($data + ['active' => $data['active'] ?? true]);
            $this->syncProfile($subject, $profileData, (int) $request->user()->id);

            return $subject;
        }, 3);
        $subject->load('catalogProfile');
        $this->audit->write('lcd.subject.created', 'create', $subject, actor: $request->user(), schoolId: $school->id, after: $this->subjectSnapshot($subject), request: $request);

        return $this->dataResponse((new SubjectResource($subject))->resolve($request), 201, $subject->updated_at?->getTimestamp() ?: 1);
    }

    public function update(UpdateSubjectRequest $request, string $subject): JsonResponse
    {
        $model = ScheduleSubject::query()->findOrFail($subject);
        $this->authorize('update', $model);
        $school = $this->school($request);
        $expected = (int) ($request->header('If-Match') ?: $request->input('lock_version'));
        $actual = $model->updated_at?->getTimestamp() ?: 1;
        if ($expected !== $actual) {
            throw new VersionConflictException($expected, $actual);
        }

        $model->load('catalogProfile');
        $before = $this->subjectSnapshot($model);
        DB::transaction(function () use ($request, $model, $actual): void {
            $model->fill($request->safe()->only(['name', 'code', 'area', 'color', 'active']));
            $this->syncProfile(
                $model,
                $request->safe()->only(['display_name', 'subject_type', 'description', 'education_types']),
                (int) $request->user()->id,
            );
            $model->updated_at = Carbon::createFromTimestamp(max(now()->getTimestamp(), $actual + 1));
            $model->save(['timestamps' => false]);
        }, 3);
        $model->load('catalogProfile');
        $this->audit->write('lcd.subject.updated', 'update', $model, actor: $request->user(), schoolId: $school->id, before: $before, after: $this->subjectSnapshot($model), request: $request);

        return $this->dataResponse((new SubjectResource($model))->resolve($request), version: $model->updated_at->getTimestamp());
    }

    public function bulkStatus(BulkSubjectStatusRequest $request): JsonResponse
    {
        $this->authorize('create', ScheduleSubject::class);
        $school = $this->school($request);
        $ids = collect($request->validated('subject_ids'))->map(fn ($id): int => (int) $id)->unique()->values();
        $active = $request->boolean('active');
        $mutation = DB::transaction(function () use ($ids, $active): array {
            $subjects = ScheduleSubject::query()->whereIn('id', $ids)->lockForUpdate()->get();
            $before = $subjects->mapWithKeys(fn (ScheduleSubject $subject): array => [$subject->id => (bool) $subject->active])->all();
            $changedIds = $subjects->where('active', ! $active)->pluck('id')->values();
            if ($changedIds->isNotEmpty()) {
                ScheduleSubject::query()->whereIn('id', $changedIds)->update([
                    'active' => $active,
                    'updated_at' => now(),
                ]);
            }

            return ['before' => $before, 'changed_ids' => $changedIds];
        }, 3);
        /** @var Collection<int, int> $changedIds */
        $changedIds = $mutation['changed_ids'];

        if ($changedIds->isNotEmpty()) {
            $this->audit->write(
                'lcd.subject.bulk_status_changed',
                $active ? 'bulk_activate' : 'bulk_deactivate',
                $school,
                actor: $request->user(),
                schoolId: $school->id,
                before: ['subjects' => $mutation['before']],
                after: ['subject_ids' => $changedIds->all(), 'active' => $active],
                request: $request,
            );
        }

        $fresh = ScheduleSubject::query()->with('catalogProfile')->whereIn('id', $ids)->orderBy('name')->get();

        return $this->collectionResponse(SubjectResource::collection($fresh)->resolve($request), [
            'requested' => $ids->count(),
            'changed' => $changedIds->count(),
            'active' => $active,
        ]);
    }

    public function externalCatalog(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduleSubject::class);
        $school = $this->school($request);
        $sourceSystem = $request->string('source_system', 'legacy_gradebook')->toString();
        $catalog = $this->configuredExternalCatalog($sourceSystem);
        $subjects = ScheduleSubject::query()->with('catalogProfile')->orderBy('name')->get();
        $subjectCandidates = $this->subjectCandidates($subjects);
        $aliases = SubjectExternalAlias::query()
            ->with('subject.catalogProfile')
            ->where('school_id', $school->id)
            ->where('source_system', $sourceSystem)
            ->get()
            ->keyBy('mapping_key');

        $items = collect($catalog['items'])->map(function (array $entry) use ($aliases, $subjectCandidates): array {
            $mappingKey = $this->normalizer->mappingKey($entry['scope_code'], $entry['external_name']);
            $mapping = $aliases->get($mappingKey);
            $suggestion = $this->suggestSubject($entry['external_name'], $subjectCandidates);

            return [
                ...$entry,
                'mapping_key' => $mappingKey,
                'mapped_subject_id' => $mapping?->active ? $mapping->schedule_subject_id : null,
                'mapped_subject' => $mapping?->active && $mapping->subject ? [
                    'id' => $mapping->subject->id,
                    'name' => $mapping->subject->resolvedDisplayName(),
                    'technical_name' => $mapping->subject->name,
                ] : null,
                'suggested_subject_id' => $mapping?->active ? null : $suggestion?->id,
                'suggested_subject' => $mapping?->active || ! $suggestion ? null : [
                    'id' => $suggestion->id,
                    'name' => $suggestion->resolvedDisplayName(),
                    'technical_name' => $suggestion->name,
                ],
                'status' => $mapping?->active ? 'mapped' : ($suggestion ? 'suggested' : 'pending'),
            ];
        })->values();

        return $this->collectionResponse($items->all(), [
            'source_system' => $sourceSystem,
            'source_label' => $catalog['label'],
            'source_description' => $catalog['description'],
            'groups' => $catalog['groups'],
            'total' => $items->count(),
            'mapped' => $items->where('status', 'mapped')->count(),
            'suggested' => $items->where('status', 'suggested')->count(),
            'pending' => $items->where('status', 'pending')->count(),
        ]);
    }

    public function updateExternalMappings(UpdateExternalSubjectMappingsRequest $request): JsonResponse
    {
        $this->authorize('create', ScheduleSubject::class);
        $school = $this->school($request);
        $data = $request->validated();
        $catalog = $this->configuredExternalCatalog($data['source_system']);
        $configured = collect($catalog['items'])->keyBy(
            fn (array $entry): string => $this->normalizer->mappingKey($entry['scope_code'], $entry['external_name'])
        );
        $requestedMappings = collect($data['mappings']);
        foreach ($requestedMappings as $index => $mapping) {
            $key = $this->normalizer->mappingKey($mapping['scope_code'], $mapping['external_name']);
            if (! $configured->has($key)) {
                throw ValidationException::withMessages([
                    "mappings.{$index}.external_name" => 'La asignatura no pertenece al catálogo externo configurado.',
                ]);
            }
        }

        $before = SubjectExternalAlias::query()
            ->where('school_id', $school->id)
            ->where('source_system', $data['source_system'])
            ->whereIn('mapping_key', $requestedMappings->map(
                fn (array $mapping): string => $this->normalizer->mappingKey($mapping['scope_code'], $mapping['external_name'])
            ))
            ->get(['mapping_key', 'schedule_subject_id', 'active'])
            ->mapWithKeys(fn (SubjectExternalAlias $alias): array => [$alias->mapping_key => [
                'schedule_subject_id' => $alias->schedule_subject_id,
                'active' => (bool) $alias->active,
            ]])->all();

        DB::transaction(function () use ($request, $school, $data, $configured, $requestedMappings): void {
            foreach ($requestedMappings as $mapping) {
                $mappingKey = $this->normalizer->mappingKey($mapping['scope_code'], $mapping['external_name']);
                $entry = $configured->get($mappingKey);
                $alias = SubjectExternalAlias::query()->firstOrNew([
                    'school_id' => $school->id,
                    'source_system' => $data['source_system'],
                    'mapping_key' => $mappingKey,
                ]);
                if (! $alias->exists) {
                    $alias->created_by = $request->user()->id;
                }
                $alias->fill([
                    'schedule_subject_id' => (int) $mapping['schedule_subject_id'],
                    'scope_code' => $entry['scope_code'],
                    'education_type' => $entry['education_type'],
                    'external_name' => $entry['external_name'],
                    'normalized_name' => $this->normalizer->normalize($entry['external_name']),
                    'active' => true,
                    'updated_by' => $request->user()->id,
                    'confirmed_at' => now('UTC'),
                ])->save();
            }
        }, 3);

        $this->audit->write(
            'lcd.subject.external_mappings_confirmed',
            'confirm_external_mappings',
            $school,
            actor: $request->user(),
            schoolId: $school->id,
            before: ['mappings' => $before],
            after: ['mapping_keys' => $requestedMappings->map(
                fn (array $mapping): string => $this->normalizer->mappingKey($mapping['scope_code'], $mapping['external_name'])
            )->all()],
            request: $request,
        );

        return $this->externalCatalog($request);
    }

    /** @param array<string, mixed> $data */
    private function syncProfile(ScheduleSubject $subject, array $data, int $actorId): void
    {
        if ($data === []) {
            return;
        }
        $profile = SubjectCatalogProfile::query()->firstOrNew(['schedule_subject_id' => $subject->id]);
        if (! $profile->exists) {
            $profile->created_by = $actorId;
        }
        $profile->fill($data + ['updated_by' => $actorId])->save();
        $subject->setRelation('catalogProfile', $profile);
    }

    /** @return array<string, mixed> */
    private function subjectSnapshot(ScheduleSubject $subject): array
    {
        return [
            ...$subject->only(['id', 'name', 'code', 'area', 'color', 'active']),
            'profile' => $subject->catalogProfile?->only(['display_name', 'subject_type', 'description', 'education_types']),
        ];
    }

    /** @return array{label:string,description:string,groups:list<array<string,string>>,items:list<array<string,string>>} */
    private function configuredExternalCatalog(string $sourceSystem): array
    {
        $source = config("libro_digital_external_subjects.sources.{$sourceSystem}");
        abort_unless(is_array($source), 404, 'El catálogo externo solicitado no está configurado.');
        $groups = collect($source['groups'] ?? [])->map(fn (array $group): array => [
            'code' => (string) $group['code'],
            'label' => (string) $group['label'],
            'education_type' => (string) $group['education_type'],
        ])->values();
        $items = collect($source['groups'] ?? [])->flatMap(fn (array $group): array => array_map(
            fn (string $name): array => [
                'scope_code' => (string) $group['code'],
                'scope_label' => (string) $group['label'],
                'education_type' => (string) $group['education_type'],
                'external_name' => $name,
            ],
            (array) ($group['subjects'] ?? []),
        ))->values();

        return [
            'label' => (string) ($source['label'] ?? $sourceSystem),
            'description' => (string) ($source['description'] ?? ''),
            'groups' => $groups->all(),
            'items' => $items->all(),
        ];
    }

    /** @param Collection<int, ScheduleSubject> $subjects @return Collection<string, Collection<int, ScheduleSubject>> */
    private function subjectCandidates(Collection $subjects): Collection
    {
        $candidates = collect();
        foreach ($subjects as $subject) {
            foreach (array_unique([$subject->name, $subject->resolvedDisplayName()]) as $name) {
                $key = $this->normalizer->normalize($name);
                if ($key !== '') {
                    $candidates->push(['key' => $key, 'subject' => $subject]);
                }
            }
        }

        return $candidates->groupBy('key')->map(
            fn (Collection $items): Collection => $items->pluck('subject')->unique('id')->values()
        );
    }

    /** @param Collection<string, Collection<int, ScheduleSubject>> $candidates */
    private function suggestSubject(string $externalName, Collection $candidates): ?ScheduleSubject
    {
        foreach ($this->normalizer->candidateKeys($externalName) as $key) {
            $matches = $candidates->get($key, collect());
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    private function educationTypeFromGrade(string $gradeCode): ?string
    {
        $grade = strtoupper(trim($gradeCode));
        if (str_starts_with($grade, 'NT')) {
            return 'parvularia';
        }
        if (preg_match('/^[1-8]B$/', $grade)) {
            return 'basica';
        }
        if (preg_match('/^[1-4]M$/', $grade)) {
            return 'media';
        }

        return null;
    }

    /** @return list<array{value:string,label:string}> */
    private function educationTypeOptions(): array
    {
        return [
            ['value' => 'parvularia', 'label' => 'Educación Parvularia'],
            ['value' => 'basica', 'label' => 'Enseñanza Básica'],
            ['value' => 'media', 'label' => 'Enseñanza Media'],
        ];
    }

    /** @return list<array{value:string,label:string}> */
    private function subjectTypeOptions(): array
    {
        return [
            ['value' => 'official', 'label' => 'Oficial MINEDUC'],
            ['value' => 'common_plan', 'label' => 'Plan común'],
            ['value' => 'differentiated', 'label' => 'Plan diferenciado'],
            ['value' => 'workshop', 'label' => 'Taller o academia'],
            ['value' => 'institutional', 'label' => 'Institucional'],
        ];
    }
}
