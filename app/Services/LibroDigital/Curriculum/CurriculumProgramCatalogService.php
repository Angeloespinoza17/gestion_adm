<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Models\EducationLevel;
use App\Models\LibroDigital\CurriculumElement;
use App\Models\LibroDigital\CurriculumImportFile;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\CurriculumUnit;
use App\Models\LibroDigital\LearningObjective;
use App\Models\Schedule\ScheduleSubject;
use App\Services\LibroDigital\CurriculumGradeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CurriculumProgramCatalogService
{
    public function __construct(private readonly CurriculumGradeResolver $grades) {}

    /** @return array<string,mixed> */
    public function matrix(int $schoolId, int $academicYearId, Request $request): array
    {
        $levels = EducationLevel::query()->orderBy('order')->get(['id', 'name', 'type', 'order'])
            ->map(fn (EducationLevel $level): array => [
                'id' => $level->id,
                'name' => $level->name,
                'type' => $level->type,
                'order' => $level->order,
                'grade_code' => $this->grades->fromEducationLevel($level),
            ])->filter(fn (array $level): bool => filled($level['grade_code']))->values();
        $subjects = ScheduleSubject::query()->with('catalogProfile')
            ->where('active', true)
            ->when($request->filled('schedule_subject_id'), fn (Builder $query) => $query->whereKey($request->integer('schedule_subject_id')))
            ->orderBy('area')->orderBy('name')->get();
        $programs = CurriculumProgram::query()
            ->with(['educationLevel:id,name', 'version:id,name,decree,publication_year,status', 'documents:id,review_status,document_type'])
            ->whereIn('schedule_subject_id', $subjects->pluck('id'))
            ->whereIn('education_level_id', $levels->pluck('id'))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('published_at')->get()
            ->unique(fn (CurriculumProgram $program): string => $program->schedule_subject_id.':'.$program->education_level_id);
        $programMap = $programs->keyBy(fn (CurriculumProgram $program): string => $program->schedule_subject_id.':'.$program->education_level_id);
        $imports = CurriculumImportFile::query()->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->latest('id')->get(['id', 'public_id', 'status', 'progress', 'detected_metadata', 'warnings', 'created_at'])
            ->filter(fn (CurriculumImportFile $file): bool => filled(data_get($file->detected_metadata, 'classification.schedule_subject_id')) && filled(data_get($file->detected_metadata, 'classification.education_level_id')))
            ->unique(fn (CurriculumImportFile $file): string => data_get($file->detected_metadata, 'classification.schedule_subject_id').':'.data_get($file->detected_metadata, 'classification.education_level_id'))
            ->keyBy(fn (CurriculumImportFile $file): string => data_get($file->detected_metadata, 'classification.schedule_subject_id').':'.data_get($file->detected_metadata, 'classification.education_level_id'));
        $statusCounts = collect();
        $rows = $subjects->map(function (ScheduleSubject $subject) use ($levels, $programMap, $imports, &$statusCounts): array {
            $cells = $levels->map(function (array $level) use ($subject, $programMap, $imports, &$statusCounts): array {
                $key = $subject->id.':'.$level['id'];
                $program = $programMap->get($key);
                $import = $imports->get($key);
                $status = $program?->status ?: ($import?->status ?: 'missing');
                $statusCounts->put($status, (int) $statusCounts->get($status, 0) + 1);

                return [
                    'education_level_id' => $level['id'],
                    'grade_code' => $level['grade_code'],
                    'status' => $status,
                    'program' => $program ? $this->programSummary($program) : null,
                    'import' => $import ? $this->importSummary($import) : null,
                ];
            })->all();

            return [
                'subject' => [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'name' => $subject->resolvedDisplayName(),
                    'area' => $subject->area,
                    'color' => $subject->color,
                ],
                'cells' => $cells,
            ];
        })->values()->all();

        return [
            'levels' => $levels->all(),
            'rows' => $rows,
            'chart' => [
                'labels' => $statusCounts->keys()->all(),
                'series' => $statusCounts->values()->all(),
            ],
            'legend' => ['missing', 'draft', 'uploaded', 'processing', 'pending_review', 'validated', 'published', 'published_with_warnings', 'failed'],
        ];
    }

    public function query(Request $request): Builder
    {
        return CurriculumProgram::query()
            ->with(['subject.catalogProfile', 'educationLevel', 'version', 'documents'])
            ->when($request->filled('schedule_subject_id'), fn (Builder $query) => $query->where('schedule_subject_id', $request->integer('schedule_subject_id')))
            ->when($request->filled('education_level_id'), fn (Builder $query) => $query->where('education_level_id', $request->integer('education_level_id')))
            ->when($request->filled('grade_code'), fn (Builder $query) => $query->where('grade_code', $request->string('grade_code')->upper()->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('curriculum_version_id'), fn (Builder $query) => $query->where('curriculum_version_id', $request->integer('curriculum_version_id')))
            ->when($request->filled('query'), function (Builder $query) use ($request): void {
                $term = '%'.addcslashes($request->string('query')->toString(), '%_\\').'%';
                $query->where(fn (Builder $nested) => $nested->where('official_name', 'like', $term)->orWhere('official_code', 'like', $term));
            })
            ->orderBy('schedule_subject_id')->orderBy('education_level_id')->orderByDesc('published_at');
    }

    /** @return array<string,mixed> */
    public function detail(CurriculumProgram $program): array
    {
        $program->loadMissing([
            'subject.catalogProfile', 'educationLevel', 'version', 'documents', 'axes.learningObjectives',
            'learningObjectives', 'units.sourceDocument', 'units.learningObjectives', 'units.skills', 'units.attitudes', 'units.keywords',
        ]);
        $elements = CurriculumElement::query()
            ->join('lcd_curriculum_element_relations as relation', 'relation.curriculum_element_id', '=', 'lcd_curriculum_elements.id')
            ->where('relation.related_type', CurriculumUnit::class)
            ->whereIn('relation.related_id', $program->units->pluck('id'))
            ->orderBy('relation.related_id')->orderBy('relation.official_order')
            ->get([
                'lcd_curriculum_elements.*', 'relation.related_id as unit_id', 'relation.source_document_id',
                'relation.source_page', 'relation.original_text', 'relation.official_order',
            ])->groupBy('unit_id');
        $axisCounts = $program->axes->map(fn ($axis): array => [
            'axis' => $axis->canonical_name,
            'count' => $axis->learningObjectives->where('pivot.curriculum_program_id', $program->id)->count(),
        ])->values();
        $keywordCounts = $program->units->flatMap->keywords->countBy(fn ($keyword) => $keyword->original_text)->sortDesc()->take(15);
        $officialObjectiveTexts = $program->learningObjectives->mapWithKeys(fn (LearningObjective $objective): array => [
            $objective->id => filled($objective->pivot->original_text) ? $objective->pivot->original_text : $objective->description,
        ]);

        return [
            ...$this->programSummary($program),
            'description' => $program->description,
            'valid_from' => $program->valid_from?->toDateString(),
            'valid_until' => $program->valid_until?->toDateString(),
            'version' => [
                'id' => $program->version->id,
                'name' => $program->version->name,
                'decree' => $program->version->decree,
                'authority' => $program->version->issuing_authority,
                'publication_year' => $program->version->publication_year,
            ],
            'documents' => $program->documents->map(fn ($document): array => [
                'id' => $document->public_id,
                'title' => $document->title,
                'type' => $document->document_type,
                'edition' => $document->edition,
                'decree' => $document->decree,
                'page_count' => $document->page_count,
                'sha256' => $document->sha256,
                'official_url' => $document->official_url,
            ])->all(),
            'axes' => $program->axes->map(fn ($axis): array => [
                'id' => $axis->public_id,
                'name' => $axis->canonical_name,
                'description' => $axis->description,
                'source_page' => $axis->pivot->source_page,
                'objective_count' => $axis->learningObjectives->where('pivot.curriculum_program_id', $program->id)->count(),
            ])->all(),
            'objectives' => $program->learningObjectives->map(fn (LearningObjective $objective): array => [
                'id' => $objective->public_id,
                'code' => $objective->code,
                'type' => $objective->objective_type,
                'text' => filled($objective->pivot->original_text) ? $objective->pivot->original_text : $objective->description,
                'axis_code' => $objective->axis_code,
                'source_page' => $objective->pivot->source_page,
                'source_text' => $objective->pivot->original_text,
            ])->all(),
            'units' => $program->units->map(function (CurriculumUnit $unit) use ($elements, $officialObjectiveTexts): array {
                $unitElements = $elements->get($unit->id, collect());

                return [
                    'id' => $unit->public_id,
                    'code' => $unit->unit_code,
                    'title' => $unit->official_title,
                    'focus' => $unit->friendly_focus,
                    'purpose' => $unit->purpose,
                    'semester' => $unit->semester,
                    'order' => $unit->official_order,
                    'hours' => $unit->estimated_pedagogical_hours,
                    'page_start' => $unit->page_start,
                    'page_end' => $unit->page_end,
                    'source_document_id' => $unit->sourceDocument?->public_id,
                    'knowledge' => data_get($unit->metadata, 'knowledge'),
                    'prior_knowledge' => data_get($unit->metadata, 'prior_knowledge'),
                    'objectives' => $unit->learningObjectives->map(fn (LearningObjective $objective): array => [
                        'id' => $objective->public_id,
                        'code' => $objective->code,
                        'text' => $officialObjectiveTexts->get($objective->id, $objective->description),
                    ])->all(),
                    'skills' => $unit->skills->map(fn ($skill): array => ['id' => $skill->public_id, 'code' => $skill->code, 'name' => $skill->name])->all(),
                    'attitudes' => $unit->attitudes->map(fn ($attitude): array => ['id' => $attitude->public_id, 'text' => $attitude->text])->all(),
                    'keywords' => $unit->keywords->map(fn ($keyword): array => ['id' => $keyword->public_id, 'text' => $keyword->original_text])->all(),
                    'elements' => $unitElements->map(fn (CurriculumElement $element): array => [
                        'id' => $element->public_id,
                        'type' => $element->element_type,
                        'title' => $element->title,
                        'content' => $element->content,
                        'source_page' => $element->source_page,
                    ])->all(),
                ];
            })->all(),
            'charts' => [
                'hours_by_unit' => ['labels' => $program->units->pluck('official_title')->all(), 'series' => $program->units->pluck('estimated_pedagogical_hours')->map(fn ($hours) => (int) $hours)->all()],
                'objectives_by_unit' => ['labels' => $program->units->pluck('official_title')->all(), 'series' => $program->units->map(fn ($unit) => $unit->learningObjectives->count())->all()],
                'objectives_by_axis' => ['labels' => $axisCounts->pluck('axis')->all(), 'series' => $axisCounts->pluck('count')->all()],
                'skills_units' => [
                    'columns' => $program->units->pluck('official_title')->all(),
                    'rows' => $program->units->flatMap->skills->unique('id')->map(fn ($skill) => [
                        'skill' => $skill->name,
                        'values' => $program->units->map(fn ($unit) => $unit->skills->contains('id', $skill->id) ? 1 : 0)->all(),
                    ])->values()->all(),
                ],
                'keywords' => ['labels' => $keywordCounts->keys()->all(), 'series' => $keywordCounts->values()->all()],
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function search(Request $request): array
    {
        $term = trim($request->string('query')->toString());
        if (mb_strlen($term) < 2) {
            return [];
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $scopeRequest = Request::create('/curriculum/programs/search', 'GET', $request->except(['query', 'limit']));
        $programIds = $this->query($scopeRequest)->reorder()->limit(500)->pluck('id');
        if ($programIds->isEmpty()) {
            return [];
        }
        $limit = min(100, max(10, $request->integer('limit', 50)));
        $results = collect();
        $objectives = DB::table('lcd_curriculum_program_objectives as link')
            ->join('lcd_learning_objectives as item', 'item.id', '=', 'link.learning_objective_id')
            ->join('lcd_curriculum_programs as program', 'program.id', '=', 'link.curriculum_program_id')
            ->join('schedule_subjects as subject', 'subject.id', '=', 'program.schedule_subject_id')
            ->whereIn('program.id', $programIds)
            ->where(fn ($query) => $query->where('item.code', 'like', $like)->orWhere('item.description', 'like', $like)->orWhere('link.original_text', 'like', $like))
            ->limit($limit)->get([
                'program.public_id as program_id', 'program.grade_code', 'subject.name as subject',
                'item.code as title', DB::raw('COALESCE(link.original_text, item.description) as text'), 'link.source_page',
            ]);
        $results->push(...$objectives->map(fn ($item) => $this->searchResult('OA', $item)));
        $units = DB::table('lcd_curriculum_units as item')->join('lcd_curriculum_programs as program', 'program.id', '=', 'item.curriculum_program_id')->join('schedule_subjects as subject', 'subject.id', '=', 'program.schedule_subject_id')
            ->whereIn('program.id', $programIds)->where(fn ($query) => $query->where('item.official_title', 'like', $like)->orWhere('item.purpose', 'like', $like))
            ->limit($limit)->get(['program.public_id as program_id', 'program.grade_code', 'subject.name as subject', 'item.official_title as title', 'item.purpose as text', 'item.page_start as source_page']);
        $results->push(...$units->map(fn ($item) => $this->searchResult('Unidad', $item)));
        $elements = DB::table('lcd_curriculum_elements as item')->join('lcd_curriculum_element_relations as relation', 'relation.curriculum_element_id', '=', 'item.id')->join('lcd_curriculum_units as unit', function ($join): void {
            $join->on('unit.id', '=', 'relation.related_id')->where('relation.related_type', '=', CurriculumUnit::class);
        })->join('lcd_curriculum_programs as program', 'program.id', '=', 'unit.curriculum_program_id')->join('schedule_subjects as subject', 'subject.id', '=', 'program.schedule_subject_id')
            ->whereIn('program.id', $programIds)->where(fn ($query) => $query->where('item.title', 'like', $like)->orWhere('item.content', 'like', $like))
            ->limit($limit)->get(['program.public_id as program_id', 'program.grade_code', 'subject.name as subject', 'item.title', 'item.content as text', 'relation.source_page', 'item.element_type as result_type']);
        $results->push(...$elements->map(fn ($item) => $this->searchResult((string) $item->result_type, $item)));

        $axes = DB::table('lcd_curriculum_program_axes as link')
            ->join('lcd_curriculum_axes as item', 'item.id', '=', 'link.curriculum_axis_id')
            ->join('lcd_curriculum_programs as program', 'program.id', '=', 'link.curriculum_program_id')
            ->join('schedule_subjects as subject', 'subject.id', '=', 'program.schedule_subject_id')
            ->whereIn('program.id', $programIds)
            ->where(fn ($query) => $query->where('item.canonical_name', 'like', $like)->orWhere('item.description', 'like', $like))
            ->limit($limit)->get(['program.public_id as program_id', 'program.grade_code', 'subject.name as subject', 'item.canonical_name as title', 'item.description as text', 'link.source_page']);
        $results->push(...$axes->map(fn ($item) => $this->searchResult('Eje', $item)));

        $skills = DB::table('lcd_curriculum_unit_skills as link')
            ->join('lcd_curriculum_skills as item', 'item.id', '=', 'link.curriculum_skill_id')
            ->join('lcd_curriculum_units as unit', 'unit.id', '=', 'link.curriculum_unit_id')
            ->join('lcd_curriculum_programs as program', 'program.id', '=', 'unit.curriculum_program_id')
            ->join('schedule_subjects as subject', 'subject.id', '=', 'program.schedule_subject_id')
            ->whereIn('program.id', $programIds)
            ->where(fn ($query) => $query->where('item.name', 'like', $like)->orWhere('item.description', 'like', $like))
            ->limit($limit)->get(['program.public_id as program_id', 'program.grade_code', 'subject.name as subject', 'item.name as title', 'item.description as text', 'link.source_page']);
        $results->push(...$skills->map(fn ($item) => $this->searchResult('Habilidad', $item)));

        $attitudes = DB::table('lcd_curriculum_unit_attitudes as link')
            ->join('lcd_curriculum_attitudes as item', 'item.id', '=', 'link.curriculum_attitude_id')
            ->join('lcd_curriculum_units as unit', 'unit.id', '=', 'link.curriculum_unit_id')
            ->join('lcd_curriculum_programs as program', 'program.id', '=', 'unit.curriculum_program_id')
            ->join('schedule_subjects as subject', 'subject.id', '=', 'program.schedule_subject_id')
            ->whereIn('program.id', $programIds)
            ->where(fn ($query) => $query->where('item.text', 'like', $like)->orWhere('item.description', 'like', $like))
            ->limit($limit)->get(['program.public_id as program_id', 'program.grade_code', 'subject.name as subject', 'item.code as title', 'item.text', 'link.source_page']);
        $results->push(...$attitudes->map(function ($item): array {
            $item->title = $item->title ?: 'Actitud transversal';

            return $this->searchResult('Actitud', $item);
        }));

        $keywords = DB::table('lcd_curriculum_unit_keywords as link')
            ->join('lcd_curriculum_keywords as item', 'item.id', '=', 'link.curriculum_keyword_id')
            ->join('lcd_curriculum_units as unit', 'unit.id', '=', 'link.curriculum_unit_id')
            ->join('lcd_curriculum_programs as program', 'program.id', '=', 'unit.curriculum_program_id')
            ->join('schedule_subjects as subject', 'subject.id', '=', 'program.schedule_subject_id')
            ->whereIn('program.id', $programIds)
            ->where('item.original_text', 'like', $like)
            ->limit($limit)->get(['program.public_id as program_id', 'program.grade_code', 'subject.name as subject', 'item.original_text as title', 'item.original_text as text', 'link.source_page']);
        $results->push(...$keywords->map(fn ($item) => $this->searchResult('Palabra clave', $item)));

        return $results->take($limit)->values()->all();
    }

    /** @return array<string,mixed> */
    public function importDetail(CurriculumImportFile $file): array
    {
        $file->loadMissing(['batch.requester:id,name', 'uploader:id,name', 'document.primarySubject.catalogProfile', 'document.primaryEducationLevel', 'candidates.children', 'conflicts.resolver:id,name', 'logs']);

        return [
            ...$this->importSummary($file),
            'batch' => ['id' => $file->batch->public_id, 'status' => $file->batch->status, 'name' => $file->batch->original_name, 'requested_by' => $file->batch->requester?->name],
            'uploaded_by' => $file->uploader?->name,
            'sha256' => $file->sha256,
            'size_bytes' => $file->size_bytes,
            'classification' => data_get($file->detected_metadata, 'classification'),
            'parser_summary' => data_get($file->detected_metadata, 'parser_summary'),
            'warnings' => $file->warnings ?? [],
            'error_summary' => $file->error_summary,
            'document' => $file->document ? [
                'id' => $file->document->public_id,
                'title' => $file->document->title,
                'type' => $file->document->document_type,
                'page_count' => $file->document->page_count,
                'subject' => $file->document->primarySubject?->resolvedDisplayName(),
                'grade' => $file->document->primaryEducationLevel?->name,
            ] : null,
            'candidate_counts' => $file->candidates->countBy('entity_type')->all(),
            'candidates' => $file->candidates->whereNull('parent_candidate_id')->map(fn ($candidate): array => [
                'id' => $candidate->public_id,
                'type' => $candidate->entity_type,
                'key' => $candidate->candidate_key,
                'value' => $candidate->detected_value,
                'payload' => $candidate->structured_payload,
                'confidence' => (float) $candidate->confidence,
                'physical_page' => $candidate->physical_page,
                'printed_page' => $candidate->printed_page,
                'source_excerpt' => $candidate->source_excerpt,
                'suggested_existing_id' => $candidate->suggested_existing_id,
                'suggested_action' => $candidate->suggested_action,
                'review_status' => $candidate->review_status,
                'warnings' => $candidate->warnings ?? [],
                'children' => $candidate->children->map(fn ($child): array => [
                    'id' => $child->public_id,
                    'type' => $child->entity_type,
                    'value' => $child->detected_value,
                    'confidence' => (float) $child->confidence,
                    'physical_page' => $child->physical_page,
                    'source_excerpt' => $child->source_excerpt,
                    'suggested_action' => $child->suggested_action,
                    'review_status' => $child->review_status,
                    'warnings' => $child->warnings ?? [],
                ])->all(),
            ])->values()->all(),
            'conflicts' => $file->conflicts->map(fn ($conflict): array => [
                'id' => $conflict->public_id,
                'type' => $conflict->conflict_type,
                'severity' => $conflict->severity,
                'status' => $conflict->status,
                'title' => $conflict->title,
                'description' => $conflict->description,
                'context' => $conflict->context,
                'resolution' => $conflict->resolution,
                'resolved_by' => $conflict->resolver?->name,
            ])->all(),
            'logs' => $file->logs->map(fn ($log): array => ['stage' => $log->stage, 'level' => $log->level, 'message' => $log->message, 'progress' => $log->progress, 'at' => $log->occurred_at?->toIso8601String()])->all(),
        ];
    }

    /** @return array<string,mixed> */
    public function programSummary(CurriculumProgram $program): array
    {
        return [
            'id' => $program->public_id,
            'name' => $program->official_name,
            'code' => $program->official_code,
            'status' => $program->status,
            'grade_code' => $program->grade_code,
            'subject' => $program->relationLoaded('subject') ? ['id' => $program->subject->id, 'code' => $program->subject->code, 'name' => $program->subject->resolvedDisplayName()] : null,
            'education_level' => $program->relationLoaded('educationLevel') ? ['id' => $program->educationLevel->id, 'name' => $program->educationLevel->name] : null,
            'weeks' => $program->estimated_weeks,
            'hours' => $program->estimated_pedagogical_hours,
            'published_at' => $program->published_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function importSummary(CurriculumImportFile $file): array
    {
        return [
            'id' => $file->public_id,
            'name' => $file->original_name,
            'status' => $file->status,
            'progress' => $file->progress,
            'stage' => $file->current_stage,
            'created_at' => $file->created_at?->toIso8601String(),
            'warnings_count' => count((array) $file->warnings),
        ];
    }

    /** @return array<string,mixed> */
    private function searchResult(string $type, object $item): array
    {
        return [
            'type' => $type,
            'program_id' => $item->program_id,
            'subject' => $item->subject,
            'grade_code' => $item->grade_code,
            'title' => $item->title,
            'text' => $item->text,
            'source_page' => $item->source_page,
            'href' => '/libro-digital/curriculum-programs?program='.$item->program_id,
        ];
    }
}
