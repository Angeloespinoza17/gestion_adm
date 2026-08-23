<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\AcademicYear;
use App\Models\EducationLevel;
use App\Models\LibroDigital\CurriculumAttitude;
use App\Models\LibroDigital\CurriculumAxis;
use App\Models\LibroDigital\CurriculumDocument;
use App\Models\LibroDigital\CurriculumElement;
use App\Models\LibroDigital\CurriculumElementRelation;
use App\Models\LibroDigital\CurriculumImportBatch;
use App\Models\LibroDigital\CurriculumImportFile;
use App\Models\LibroDigital\CurriculumImportLog;
use App\Models\LibroDigital\CurriculumKeyword;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\CurriculumSkill;
use App\Models\LibroDigital\CurriculumUnit;
use App\Models\LibroDigital\CurriculumVersion;
use App\Models\LibroDigital\LearningObjective;
use App\Models\LibroDigital\School;
use App\Models\Schedule\ScheduleSubject;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use App\Services\LibroDigital\CurriculumGradeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CurriculumManualProgramService
{
    public function __construct(
        private readonly CurriculumPdfImportService $pdfs,
        private readonly CurriculumGradeResolver $grades,
        private readonly PdfTextNormalizer $normalizer,
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(School $school, AcademicYear $year, User $actor, Request $request, array $data): CurriculumProgram
    {
        $subject = ScheduleSubject::query()->whereKey($data['schedule_subject_id'])->where('active', true)->first();
        $level = EducationLevel::query()->find($data['education_level_id']);
        $gradeCode = $this->grades->fromEducationLevel($level);
        if (! $subject || ! $level || ! $gradeCode) {
            throw new LibroDigitalException('La asignatura o el nivel no están disponibles para crear el programa.', 'LCD_CURRICULUM_MANUAL_SCOPE_INVALID', 422);
        }

        $objectives = LearningObjective::query()
            ->whereIn('id', collect($data['objective_ids'])->map(fn ($id): int => (int) $id))
            ->where('schedule_subject_id', $subject->id)
            ->where('grade_code', $gradeCode)
            ->where('active', true)
            ->get();
        if ($objectives->count() !== count(array_unique(array_map('intval', $data['objective_ids'])))) {
            throw new LibroDigitalException('Todos los OA deben pertenecer a la asignatura y nivel seleccionados.', 'LCD_CURRICULUM_MANUAL_OBJECTIVES_SCOPE_INVALID', 422);
        }

        $publishNow = (bool) ($data['publish_now'] ?? false);
        $source = $request->file('source_file');
        if ($publishNow && ! $source instanceof UploadedFile) {
            throw new LibroDigitalException('Adjunta el PDF de respaldo antes de publicar.', 'LCD_CURRICULUM_MANUAL_SOURCE_REQUIRED', 422);
        }
        $archive = $source instanceof UploadedFile ? $this->pdfs->archiveManualSource($source, $school, $year) : null;

        $program = DB::transaction(function () use ($school, $year, $actor, $data, $subject, $level, $gradeCode, $objectives, $publishNow, $source, $archive): CurriculumProgram {
            $version = $this->version($data, $publishNow);
            $identity = $this->canonical->hash([
                'subject' => $subject->id,
                'education_level' => $level->id,
                'version' => $version->id,
                'grade' => $gradeCode,
                'modality' => $data['modality_code'] ?? null,
                'formation' => $data['formation_type_code'] ?? null,
                'track' => $data['curriculum_track'] ?? null,
            ]);
            if (CurriculumProgram::query()->where('identity_hash', $identity)->exists()) {
                throw new LibroDigitalException('Ya existe un programa para esta asignatura, nivel y versión.', 'LCD_CURRICULUM_MANUAL_PROGRAM_DUPLICATE', 409);
            }

            $program = CurriculumProgram::query()->create([
                'schedule_subject_id' => $subject->id,
                'education_level_id' => $level->id,
                'curriculum_version_id' => $version->id,
                'level_code' => $this->grades->levelForGrade($gradeCode),
                'grade_code' => $gradeCode,
                'modality_code' => $data['modality_code'] ?? null,
                'formation_type_code' => $data['formation_type_code'] ?? null,
                'curriculum_track' => $data['curriculum_track'] ?? 'GENERAL',
                'official_name' => trim((string) $data['official_name']),
                'official_code' => $data['official_code'] ?? ($subject->code.'-'.$gradeCode),
                'description' => $data['description'] ?? null,
                'estimated_weeks' => $data['estimated_weeks'] ?? null,
                'estimated_pedagogical_hours' => $data['estimated_pedagogical_hours'] ?? null,
                'status' => $publishNow ? 'published' : 'draft',
                'identity_hash' => $identity,
                'revision' => 1,
                'published_at' => $publishNow ? now('UTC') : null,
                'created_by' => $actor->id,
                'reviewed_by' => $publishNow ? $actor->id : null,
                'published_by' => $publishNow ? $actor->id : null,
                'metadata' => ['entry_method' => 'manual', 'school_id' => $school->id, 'academic_year_id' => $year->id],
            ]);

            $document = $archive && $source instanceof UploadedFile
                ? $this->document($archive, $source, $program, $version, $subject, $level, $actor, $data, $publishNow)
                : null;
            if ($document) {
                $program->documents()->syncWithoutDetaching([$document->id => [
                    'role' => 'primary', 'official_order' => 1, 'page_start' => 1,
                    'page_end' => $data['source_page_count'] ?? null,
                ]]);
            }

            $this->attachObjectives($program, $objectives, $document);
            $this->attachAxes($program, $objectives, $document, (array) ($data['axes'] ?? []));
            $this->attachUnits($program, $objectives, $document, (array) $data['units']);
            if ($archive) {
                $this->completeExistingImport($school, $year, $archive['sha256'], $document, $program, $publishNow, $actor);
            }

            return $program;
        }, 3);

        $this->audit->write(
            'curriculum.program.manual_created',
            'create',
            $program,
            actor: $actor,
            schoolId: $school->id,
            academicYearId: $year->id,
            after: ['program_id' => $program->id, 'status' => $program->status, 'entry_method' => 'manual'],
            reason: 'Creación manual de programa curricular.',
            request: $request,
            entityRevision: 1,
        );

        return $program->fresh([
            'subject.catalogProfile', 'educationLevel', 'version', 'documents', 'axes',
            'learningObjectives', 'units.learningObjectives', 'units.skills', 'units.attitudes', 'units.keywords',
        ]);
    }

    /** @param array<string,mixed> $data */
    private function version(array $data, bool $published): CurriculumVersion
    {
        $identity = $this->canonical->hash([
            'authority' => $data['issuing_authority'], 'decree' => $data['decree'] ?? null,
            'edition' => $data['edition'] ?? null, 'year' => $data['publication_year'] ?? null,
        ]);

        $version = CurriculumVersion::query()->firstOrNew(['identity_hash' => $identity]);
        if (! $version->exists) {
            $version->fill([
                'name' => trim(collect([$data['decree'] ?? null, $data['edition'] ?? null])->filter()->implode(' · ')) ?: 'Versión manual '.($data['publication_year'] ?? now()->year),
                'decree' => $data['decree'] ?? null,
                'issuing_authority' => $data['issuing_authority'],
                'publication_year' => $data['publication_year'] ?? null,
                'status' => $published ? 'published' : 'draft',
                'official_url' => $data['official_url'] ?? null,
                'verified_at' => $published ? now('UTC') : null,
            ]);
        } elseif ($published && $version->status === 'draft') {
            $version->forceFill(['status' => 'published', 'verified_at' => now('UTC')]);
        }
        $version->save();

        return $version;
    }

    /** @param array{sha256:string,size_bytes:int,mime_type:string,private_path:string,disk:string} $archive @param array<string,mixed> $data */
    private function document(array $archive, UploadedFile $source, CurriculumProgram $program, CurriculumVersion $version, ScheduleSubject $subject, EducationLevel $level, User $actor, array $data, bool $published): CurriculumDocument
    {
        return CurriculumDocument::query()->firstOrCreate(['sha256' => $archive['sha256']], [
            'curriculum_version_id' => $version->id,
            'document_type' => 'program_study',
            'title' => $program->official_name,
            'original_filename' => mb_substr($source->getClientOriginalName(), 0, 190),
            'disk' => $archive['disk'],
            'storage_path' => $archive['private_path'],
            'mime_type' => $archive['mime_type'],
            'file_size' => $archive['size_bytes'],
            'official_url' => $data['official_url'] ?? null,
            'issuing_authority' => $data['issuing_authority'],
            'decree' => $data['decree'] ?? null,
            'edition' => $data['edition'] ?? null,
            'publication_year' => $data['publication_year'] ?? null,
            'page_count' => $data['source_page_count'] ?? 0,
            'primary_subject_id' => $subject->id,
            'primary_education_level_id' => $level->id,
            'extraction_status' => 'manual',
            'review_status' => $published ? 'published' : 'pending',
            'classification' => ['entry_method' => 'manual', 'grade_code' => $program->grade_code],
            'metadata' => ['manual_entry' => true, 'text_extraction_skipped' => true],
            'uploaded_by' => $actor->id,
            'reviewed_by' => $published ? $actor->id : null,
            'published_by' => $published ? $actor->id : null,
            'published_at' => $published ? now('UTC') : null,
        ]);
    }

    /** @param Collection<int,LearningObjective> $objectives */
    private function attachObjectives(CurriculumProgram $program, Collection $objectives, ?CurriculumDocument $document): void
    {
        foreach ($objectives->values() as $index => $objective) {
            $program->learningObjectives()->attach($objective->id, [
                'role' => $objective->objective_type === 'OAH' ? 'skill' : 'main',
                'official_order' => $index + 1,
                'source_document_id' => $document?->id,
                'source_page' => $this->objectiveSourcePage($objective),
                'original_text' => $objective->description,
            ]);
        }
    }

    /** @param Collection<int,LearningObjective> $objectives @param list<array<string,mixed>> $axes */
    private function attachAxes(CurriculumProgram $program, Collection $objectives, ?CurriculumDocument $document, array $axes): void
    {
        foreach (array_values($axes) as $index => $data) {
            $name = trim((string) $data['name']);
            $normalized = $this->normalizer->key($name);
            $axis = CurriculumAxis::query()->updateOrCreate(
                ['identity_hash' => $this->canonical->hash(['subject' => $program->schedule_subject_id, 'name' => $normalized])],
                ['schedule_subject_id' => $program->schedule_subject_id, 'canonical_name' => $name, 'normalized_name' => $normalized, 'description' => $data['description'] ?? null],
            );
            $program->axes()->attach($axis->id, ['official_order' => $index + 1, 'source_document_id' => $document?->id]);
            $axisObjectiveIds = collect($data['objective_ids'] ?? [])->map(fn ($id): int => (int) $id);
            foreach ($objectives->whereIn('id', $axisObjectiveIds) as $objective) {
                DB::table('lcd_curriculum_axis_objectives')->insert([
                    'curriculum_axis_id' => $axis->id, 'learning_objective_id' => $objective->id,
                    'curriculum_program_id' => $program->id, 'source_document_id' => $document?->id,
                    'source_page' => $this->objectiveSourcePage($objective), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /** @param Collection<int,LearningObjective> $objectives @param list<array<string,mixed>> $units */
    private function attachUnits(CurriculumProgram $program, Collection $objectives, ?CurriculumDocument $document, array $units): void
    {
        foreach (array_values($units) as $index => $data) {
            $unitObjectiveIds = collect($data['objective_ids'])->map(fn ($id): int => (int) $id);
            if ($unitObjectiveIds->diff($objectives->pluck('id'))->isNotEmpty()) {
                throw new LibroDigitalException('Una unidad contiene OA fuera del programa.', 'LCD_CURRICULUM_MANUAL_UNIT_OBJECTIVES_INVALID', 422);
            }
            $unit = CurriculumUnit::query()->create([
                'curriculum_program_id' => $program->id,
                'unit_code' => trim((string) $data['unit_code']),
                'official_title' => trim((string) $data['official_title']),
                'friendly_focus' => mb_substr(trim((string) ($data['purpose'] ?? '')), 0, 190) ?: null,
                'purpose' => $data['purpose'] ?? null,
                'semester' => $data['semester'] ?? null,
                'official_order' => $index + 1,
                'estimated_pedagogical_hours' => $data['estimated_pedagogical_hours'] ?? null,
                'page_start' => $data['page_start'] ?? null,
                'page_end' => $data['page_end'] ?? null,
                'source_document_id' => $document?->id,
                'metadata' => ['entry_method' => 'manual'],
            ]);
            foreach ($objectives->whereIn('id', $unitObjectiveIds)->values() as $objectiveIndex => $objective) {
                $unit->learningObjectives()->attach($objective->id, [
                    'role' => 'main', 'official_order' => $objectiveIndex + 1,
                    'source_document_id' => $document?->id, 'source_page' => $this->objectiveSourcePage($objective),
                    'original_text' => $objective->description,
                ]);
            }
            $this->attachUnitText($unit, $document, 'skills', (array) ($data['skills'] ?? []));
            $this->attachUnitText($unit, $document, 'attitudes', (array) ($data['attitudes'] ?? []));
            $this->attachUnitText($unit, $document, 'knowledge', (array) ($data['knowledge'] ?? []));
            $this->attachUnitText($unit, $document, 'keywords', (array) ($data['keywords'] ?? []));
        }
    }

    /** @param list<string> $values */
    private function attachUnitText(CurriculumUnit $unit, ?CurriculumDocument $document, string $type, array $values): void
    {
        foreach (array_values(array_filter(array_map(fn ($value): string => trim((string) $value), $values))) as $index => $value) {
            if ($type === 'skills') {
                $normalized = $this->normalizer->key($value);
                $model = CurriculumSkill::query()->firstOrCreate(
                    ['identity_hash' => $this->canonical->hash(['name' => $normalized])],
                    ['name' => mb_substr($value, 0, 190), 'normalized_name' => $normalized, 'description' => $value],
                );
                $unit->skills()->attach($model->id, ['official_order' => $index + 1, 'source_document_id' => $document?->id, 'original_text' => $value]);
            } elseif ($type === 'attitudes') {
                $model = CurriculumAttitude::query()->firstOrCreate(
                    ['identity_hash' => $this->canonical->hash(['text' => $this->normalizer->key($value)])],
                    ['text' => $value],
                );
                $unit->attitudes()->attach($model->id, ['official_order' => $index + 1, 'source_document_id' => $document?->id, 'original_text' => $value]);
            } elseif ($type === 'keywords') {
                $normalized = $this->normalizer->key($value);
                $model = CurriculumKeyword::query()->firstOrCreate(['normalized_text' => $normalized], ['original_text' => mb_substr($value, 0, 190)]);
                $unit->keywords()->attach($model->id, ['official_order' => $index + 1, 'source_document_id' => $document?->id, 'original_text' => mb_substr($value, 0, 190)]);
            } else {
                $element = CurriculumElement::query()->firstOrCreate(
                    ['identity_hash' => $this->canonical->hash(['type' => 'knowledge', 'content' => $this->normalizer->key($value)])],
                    ['element_type' => 'knowledge', 'title' => 'Conocimiento', 'content' => $value, 'normalized_content' => $this->normalizer->key($value)],
                );
                CurriculumElementRelation::query()->create([
                    'curriculum_element_id' => $element->id, 'related_type' => CurriculumUnit::class,
                    'related_id' => $unit->id, 'role' => 'primary', 'official_order' => $index + 1,
                    'source_document_id' => $document?->id, 'original_text' => $value,
                ]);
            }
        }
    }

    private function objectiveSourcePage(LearningObjective $objective): ?int
    {
        return is_numeric($objective->source_page) ? (int) $objective->source_page : null;
    }

    private function completeExistingImport(School $school, AcademicYear $year, string $hash, ?CurriculumDocument $document, CurriculumProgram $program, bool $published, User $actor): void
    {
        $file = CurriculumImportFile::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)->where('sha256', $hash)->first();
        if (! $file) {
            return;
        }
        $metadata = (array) $file->detected_metadata;
        $metadata['classification'] = [
            'entry_method' => 'manual', 'schedule_subject_id' => $program->schedule_subject_id,
            'education_level_id' => $program->education_level_id, 'grade_code' => $program->grade_code,
            'title' => $program->official_name,
        ];
        $file->forceFill([
            'curriculum_document_id' => $document?->id,
            'status' => $published ? 'published' : 'validated',
            'current_stage' => $published ? 'published' : 'validated',
            'progress' => 100,
            'processing_completed_at' => now('UTC'),
            'detected_metadata' => $metadata,
            'warnings' => [],
            'error_summary' => null,
        ])->save();
        CurriculumImportLog::query()->create([
            'import_file_id' => $file->id, 'stage' => 'manual_entry', 'level' => 'info',
            'message' => 'Programa creado manualmente; se omitió la extracción automática.',
            'context' => ['program_id' => $program->id, 'actor_id' => $actor->id],
            'progress' => 100, 'occurred_at' => now('UTC'),
        ]);
        $files = $file->batch->files()->get(['status']);
        $allPublished = $files->every(fn (CurriculumImportFile $item): bool => in_array($item->status, ['published', 'published_with_warnings'], true));
        $file->batch->forceFill([
            'status' => $allPublished ? CurriculumImportBatch::STATUS_ACTIVATED : CurriculumImportBatch::STATUS_PENDING_REVIEW,
            'imported_row_count' => $files->whereIn('status', ['published', 'published_with_warnings'])->count(),
            'warning_count' => 0,
            'error_count' => $files->where('status', 'failed')->count(),
            'completed_at' => now('UTC'),
        ])->save();
    }
}
