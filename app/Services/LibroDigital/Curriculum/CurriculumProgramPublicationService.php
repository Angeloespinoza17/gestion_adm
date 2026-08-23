<?php

namespace App\Services\LibroDigital\Curriculum;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\CurriculumAttitude;
use App\Models\LibroDigital\CurriculumAxis;
use App\Models\LibroDigital\CurriculumDocument;
use App\Models\LibroDigital\CurriculumElement;
use App\Models\LibroDigital\CurriculumElementRelation;
use App\Models\LibroDigital\CurriculumImportCandidate;
use App\Models\LibroDigital\CurriculumImportConflict;
use App\Models\LibroDigital\CurriculumImportFile;
use App\Models\LibroDigital\CurriculumImportLog;
use App\Models\LibroDigital\CurriculumKeyword;
use App\Models\LibroDigital\CurriculumProgram;
use App\Models\LibroDigital\CurriculumSkill;
use App\Models\LibroDigital\CurriculumSkillFormulation;
use App\Models\LibroDigital\CurriculumUnit;
use App\Models\LibroDigital\CurriculumVersion;
use App\Models\LibroDigital\LearningObjective;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CanonicalJson;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CurriculumProgramPublicationService
{
    public function __construct(
        private readonly PdfTextNormalizer $normalizer,
        private readonly CanonicalJson $canonical,
        private readonly AuditEventWriter $audit,
    ) {}

    /** @param array<string,mixed> $changes */
    public function reviewCandidate(CurriculumImportCandidate $candidate, User $actor, array $changes, Request $request): CurriculumImportCandidate
    {
        $file = $candidate->importFile()->firstOrFail();
        $this->assertReviewable($file);
        $before = $candidate->only(['detected_value', 'structured_payload', 'suggested_existing_type', 'suggested_existing_id', 'suggested_action', 'review_status']);
        $payload = array_key_exists('structured_payload', $changes)
            ? [...(array) $candidate->structured_payload, ...(array) $changes['structured_payload']]
            : $candidate->structured_payload;
        $action = (string) ($changes['suggested_action'] ?? $candidate->suggested_action);
        $existingId = array_key_exists('suggested_existing_id', $changes) ? (int) $changes['suggested_existing_id'] : $candidate->suggested_existing_id;
        $existingType = $candidate->suggested_existing_type;
        if (in_array($candidate->entity_type, ['learning_objective', 'skill_objective'], true) && $existingId) {
            $exists = LearningObjective::query()->whereKey($existingId)->exists();
            if (! $exists) {
                throw new LibroDigitalException('El OA seleccionado ya no existe.', 'LCD_CURRICULUM_OBJECTIVE_MATCH_INVALID', 422);
            }
            $existingType = LearningObjective::class;
            $action = 'reuse';
        }
        $candidate->forceFill([
            'detected_value' => array_key_exists('detected_value', $changes) ? trim((string) $changes['detected_value']) : $candidate->detected_value,
            'normalized_value' => array_key_exists('detected_value', $changes) ? $this->normalizer->key((string) $changes['detected_value']) : $candidate->normalized_value,
            'structured_payload' => $payload,
            'suggested_existing_type' => $existingType,
            'suggested_existing_id' => $existingId,
            'suggested_action' => $action,
            'review_status' => (string) ($changes['review_status'] ?? 'accepted'),
            'reviewed_by' => $actor->id,
            'reviewed_at' => now('UTC'),
            'review_notes' => $changes['review_notes'] ?? $candidate->review_notes,
        ])->save();
        $file->forceFill(['status' => 'pending_review', 'current_stage' => 'pending_review'])->save();
        $file->document?->forceFill(['review_status' => 'pending'])->save();

        $this->audit->write(
            'curriculum.program_import.candidate_reviewed',
            'review',
            $candidate,
            actor: $actor,
            schoolId: (int) $file->school_id,
            academicYearId: (int) $file->academic_year_id,
            before: $before,
            after: $candidate->only(array_keys($before)),
            request: $request,
        );

        return $candidate->fresh(['children']);
    }

    public function resolveConflict(CurriculumImportConflict $conflict, User $actor, string $resolution, Request $request): CurriculumImportConflict
    {
        $file = $conflict->importFile()->firstOrFail();
        $this->assertReviewable($file);
        $conflict->forceFill([
            'status' => 'resolved',
            'resolution' => trim($resolution),
            'resolved_by' => $actor->id,
            'resolved_at' => now('UTC'),
        ])->save();
        $this->audit->write(
            'curriculum.program_import.conflict_resolved',
            'resolve',
            $conflict,
            actor: $actor,
            schoolId: (int) $file->school_id,
            academicYearId: (int) $file->academic_year_id,
            after: $conflict->only(['status', 'resolution', 'resolved_by', 'resolved_at']),
            request: $request,
        );

        return $conflict->fresh();
    }

    public function validate(CurriculumImportFile $file, User $actor, ?string $note, Request $request): CurriculumImportFile
    {
        $this->assertReviewable($file);
        if (! $file->curriculum_document_id) {
            throw new LibroDigitalException('La extracción del documento aún no está disponible.', 'LCD_CURRICULUM_DOCUMENT_NOT_EXTRACTED', 409);
        }
        $critical = $file->conflicts()->where('severity', 'critical')->where('status', 'open')->count();
        if ($critical > 0) {
            throw new LibroDigitalException('Resuelve todos los conflictos críticos antes de validar.', 'LCD_CURRICULUM_CRITICAL_CONFLICTS_OPEN', 409, [['critical_conflicts' => $critical]]);
        }
        $unmatchedObjectives = $file->candidates()
            ->whereIn('entity_type', ['learning_objective', 'skill_objective'])
            ->whereNotIn('review_status', ['rejected', 'skipped'])
            ->where(function ($query): void {
                $query->whereNull('suggested_existing_id')->where('suggested_action', '!=', 'create');
            })->count();
        if ($unmatchedObjectives > 0) {
            throw new LibroDigitalException('Cada OA debe reutilizar uno existente o quedar aprobado explícitamente como nuevo.', 'LCD_CURRICULUM_OBJECTIVES_UNRESOLVED', 409, [['unresolved_objectives' => $unmatchedObjectives]]);
        }

        DB::transaction(function () use ($file, $actor, $note): void {
            $file->candidates()->where('review_status', 'pending')->get()->each(function (CurriculumImportCandidate $candidate) use ($actor, $note): void {
                $candidate->forceFill([
                    // Los extractos marcados para revisión nunca se publican
                    // por omisión. Solo llegan al catálogo si un revisor los
                    // aceptó explícitamente desde staging.
                    'review_status' => in_array($candidate->suggested_action, ['skip', 'review'], true) ? 'skipped' : 'accepted',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now('UTC'),
                    'review_notes' => $note,
                ])->save();
            });
            $file->forceFill(['status' => 'validated', 'current_stage' => 'validated'])->save();
            $file->document?->forceFill(['review_status' => 'validated', 'reviewed_by' => $actor->id])->save();
            CurriculumImportLog::query()->create([
                'import_file_id' => $file->id,
                'stage' => 'validated',
                'level' => 'info',
                'message' => 'Candidatos validados mediante revisión humana.',
                'context' => ['note' => $note],
                'progress' => 100,
                'occurred_at' => now('UTC'),
            ]);
        }, 3);
        $this->audit->write(
            'curriculum.program_import.validated',
            'validate',
            $file,
            actor: $actor,
            schoolId: (int) $file->school_id,
            academicYearId: (int) $file->academic_year_id,
            after: ['status' => 'validated', 'candidate_count' => $file->candidates()->count()],
            reason: $note,
            request: $request,
        );

        return $file->fresh(['document', 'candidates', 'conflicts']);
    }

    public function publish(CurriculumImportFile $file, User $actor, ?string $note, Request $request): CurriculumProgram
    {
        $file->refresh();
        if ($file->status !== 'validated' && ! in_array($file->status, ['published', 'published_with_warnings'], true)) {
            throw new LibroDigitalException('El archivo debe estar validado antes de publicar.', 'LCD_CURRICULUM_IMPORT_NOT_VALIDATED', 409);
        }
        if ($file->conflicts()->where('severity', 'critical')->where('status', 'open')->exists()) {
            throw new LibroDigitalException('No se puede publicar con conflictos críticos abiertos.', 'LCD_CURRICULUM_CRITICAL_CONFLICTS_OPEN', 409);
        }

        $program = DB::transaction(function () use ($file, $actor): CurriculumProgram {
            /** @var CurriculumImportFile $locked */
            $locked = CurriculumImportFile::query()->lockForUpdate()->with(['document', 'candidates.children'])->findOrFail($file->id);
            $document = $locked->document;
            if (! $document) {
                throw new LibroDigitalException('El documento extraído no existe.', 'LCD_CURRICULUM_DOCUMENT_NOT_EXTRACTED', 409);
            }
            $classification = (array) data_get($locked->detected_metadata, 'classification', []);
            foreach (['schedule_subject_id', 'education_level_id', 'grade_code'] as $required) {
                if (! filled($classification[$required] ?? null)) {
                    throw new LibroDigitalException('La clasificación validada está incompleta.', 'LCD_CURRICULUM_CLASSIFICATION_INCOMPLETE', 409, [['field' => $required]]);
                }
            }
            $version = $this->version($classification, $document);
            $summaryCandidate = $locked->candidates->firstWhere('candidate_key', 'program:summary');
            $summary = (array) ($summaryCandidate?->structured_payload ?? []);
            $identity = $this->canonical->hash([
                'subject' => (int) $classification['schedule_subject_id'],
                'education_level' => (int) $classification['education_level_id'],
                'version' => $version->id,
                'grade' => (string) $classification['grade_code'],
                'modality' => $classification['modality_code'] ?? null,
                'formation' => $classification['formation_type_code'] ?? null,
                'track' => $classification['curriculum_track'] ?? null,
            ]);
            $program = CurriculumProgram::query()->firstOrNew(['identity_hash' => $identity]);
            $program->forceFill([
                'schedule_subject_id' => (int) $classification['schedule_subject_id'],
                'education_level_id' => (int) $classification['education_level_id'],
                'curriculum_version_id' => $version->id,
                'level_code' => $classification['level_code'] ?? null,
                'grade_code' => (string) $classification['grade_code'],
                'cycle_code' => $classification['cycle_code'] ?? null,
                'modality_code' => $classification['modality_code'] ?? null,
                'formation_type_code' => $classification['formation_type_code'] ?? null,
                'curriculum_track' => $classification['curriculum_track'] ?? null,
                'official_name' => (string) ($classification['title'] ?? $document->title),
                'official_code' => (string) ($classification['subject_code'] ?? '').'-'.(string) $classification['grade_code'],
                'estimated_weeks' => $summary['estimated_weeks'] ?? null,
                'estimated_pedagogical_hours' => $summary['estimated_pedagogical_hours'] ?? null,
                'status' => 'published',
                'revision' => max(1, (int) $program->revision),
                'published_at' => $program->published_at ?: now('UTC'),
                'created_by' => $program->created_by ?: $actor->id,
                'reviewed_by' => $actor->id,
                'published_by' => $actor->id,
                'metadata' => ['source_import_file_id' => $locked->id, 'parser_summary' => $summary],
            ])->save();
            $document->forceFill([
                'curriculum_version_id' => $version->id,
                'extraction_status' => 'published',
                'review_status' => 'published',
                'reviewed_by' => $actor->id,
                'published_by' => $actor->id,
                'published_at' => $document->published_at ?: now('UTC'),
            ])->save();
            $program->documents()->syncWithoutDetaching([$document->id => [
                'role' => 'primary',
                'official_order' => 1,
                'page_start' => 1,
                'page_end' => $document->page_count,
            ]]);

            $objectives = $this->publishObjectives($program, $document, $locked->candidates);
            $axes = $this->publishAxes($program, $document, $locked->candidates, $objectives);
            $this->publishUnits($program, $document, $locked->candidates, $objectives);
            $this->publishSkillFormulations($program, $document, $locked->candidates);

            $warnings = $locked->conflicts()->where('severity', 'warning')->where('status', 'open')->count()
                + count((array) $locked->warnings);
            $locked->forceFill([
                'status' => $warnings > 0 ? 'published_with_warnings' : 'published',
                'current_stage' => 'published',
                'processing_completed_at' => now('UTC'),
            ])->save();
            CurriculumImportLog::query()->create([
                'import_file_id' => $locked->id,
                'stage' => 'published',
                'level' => $warnings > 0 ? 'warning' : 'info',
                'message' => $warnings > 0 ? 'Programa publicado con advertencias no críticas.' : 'Programa publicado y trazado al documento ministerial.',
                'context' => ['program_id' => $program->id, 'warnings' => $warnings, 'axis_count' => count($axes)],
                'progress' => 100,
                'occurred_at' => now('UTC'),
            ]);
            $this->refreshBatch($locked);

            return $program;
        }, 3);

        $this->audit->write(
            'curriculum.program.published',
            'publish',
            $program,
            actor: $actor,
            schoolId: (int) $file->school_id,
            academicYearId: (int) $file->academic_year_id,
            after: ['program_id' => $program->id, 'status' => $program->status, 'document_id' => $file->curriculum_document_id],
            reason: $note,
            request: $request,
            entityRevision: (int) $program->revision,
        );

        return $program->fresh($this->programRelations());
    }

    private function version(array $classification, CurriculumDocument $document): CurriculumVersion
    {
        $identity = $this->canonical->hash([
            'authority' => $classification['issuing_authority'] ?? null,
            'decree' => $classification['decree'] ?? null,
            'edition' => $classification['edition'] ?? null,
            'year' => $classification['publication_year'] ?? null,
        ]);

        return CurriculumVersion::query()->updateOrCreate(
            ['identity_hash' => $identity],
            [
                'name' => trim(collect([$classification['decree'] ?? null, $classification['edition'] ?? null])->filter()->implode(' · ')) ?: 'Versión ministerial '.$document->sha256,
                'decree' => $classification['decree'] ?? null,
                'issuing_authority' => $classification['issuing_authority'] ?? null,
                'publication_year' => $classification['publication_year'] ?? null,
                'status' => 'published',
                'official_url' => $document->official_url,
                'verified_at' => now('UTC'),
            ],
        );
    }

    /** @param Collection<int,CurriculumImportCandidate> $candidates @return Collection<int,LearningObjective> */
    private function publishObjectives(CurriculumProgram $program, CurriculumDocument $document, Collection $candidates): Collection
    {
        $published = collect();
        $order = 0;
        foreach ($candidates->whereIn('entity_type', ['learning_objective', 'skill_objective'])->where('review_status', 'accepted') as $candidate) {
            $objective = $candidate->suggested_existing_type === LearningObjective::class && $candidate->suggested_existing_id
                ? LearningObjective::query()->find($candidate->suggested_existing_id)
                : null;
            if (! $objective) {
                throw new LibroDigitalException('La publicación no crea OA implícitos: falta una conciliación aprobada.', 'LCD_CURRICULUM_OBJECTIVE_MATCH_REQUIRED', 409, [['candidate' => $candidate->public_id]]);
            }
            $program->learningObjectives()->syncWithoutDetaching([$objective->id => [
                'role' => $candidate->entity_type === 'skill_objective' ? 'skill' : 'main',
                'official_order' => ++$order,
                'source_document_id' => $document->id,
                'source_page' => $candidate->physical_page,
                'original_text' => data_get($candidate->structured_payload, 'official_text', $candidate->source_excerpt),
            ]]);
            $published->put($this->objectiveKey((string) data_get($candidate->structured_payload, 'official_code', $candidate->detected_value)), $objective);
        }

        return $published;
    }

    /** @param Collection<int,CurriculumImportCandidate> $candidates @param Collection<string,LearningObjective> $objectives @return array<int,CurriculumAxis> */
    private function publishAxes(CurriculumProgram $program, CurriculumDocument $document, Collection $candidates, Collection $objectives): array
    {
        $published = [];
        foreach ($candidates->where('entity_type', 'axis')->where('review_status', 'accepted')->values() as $index => $candidate) {
            $normalized = $this->normalizer->key((string) $candidate->detected_value);
            $axis = CurriculumAxis::query()->updateOrCreate(
                ['identity_hash' => $this->canonical->hash(['subject' => $program->schedule_subject_id, 'name' => $normalized])],
                [
                    'schedule_subject_id' => $program->schedule_subject_id,
                    'canonical_name' => (string) $candidate->detected_value,
                    'normalized_name' => $normalized,
                    'description' => data_get($candidate->structured_payload, 'description'),
                ],
            );
            $program->axes()->syncWithoutDetaching([$axis->id => [
                'official_order' => $index + 1,
                'source_document_id' => $document->id,
                'source_page' => $candidate->physical_page,
                'original_text' => $candidate->source_excerpt,
            ]]);
            foreach ($objectives as $objective) {
                if ($this->axisMatchesObjective($normalized, (string) $objective->axis_code)) {
                    DB::table('lcd_curriculum_axis_objectives')->updateOrInsert(
                        ['curriculum_axis_id' => $axis->id, 'learning_objective_id' => $objective->id, 'curriculum_program_id' => $program->id],
                        ['source_document_id' => $document->id, 'source_page' => $candidate->physical_page, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            }
            $published[] = $axis;
        }

        return $published;
    }

    /** @param Collection<int,CurriculumImportCandidate> $candidates @param Collection<string,LearningObjective> $objectives */
    private function publishUnits(CurriculumProgram $program, CurriculumDocument $document, Collection $candidates, Collection $objectives): void
    {
        foreach ($candidates->where('entity_type', 'unit')->where('review_status', 'accepted') as $candidate) {
            $payload = (array) $candidate->structured_payload;
            $unit = CurriculumUnit::query()->updateOrCreate(
                ['curriculum_program_id' => $program->id, 'unit_code' => (string) $payload['unit_code']],
                [
                    'official_title' => (string) $candidate->detected_value,
                    'friendly_focus' => mb_substr((string) ($payload['purpose'] ?? ''), 0, 190),
                    'purpose' => $payload['purpose'] ?? null,
                    'semester' => $payload['semester'] ?? null,
                    'official_order' => $payload['official_order'] ?? 1,
                    'estimated_pedagogical_hours' => $payload['estimated_pedagogical_hours'] ?? null,
                    'page_start' => $payload['page_start'] ?? $candidate->physical_page,
                    'page_end' => $payload['page_end'] ?? $candidate->physical_page,
                    'source_document_id' => $document->id,
                    'metadata' => ['prior_knowledge' => $payload['prior_knowledge'] ?? null, 'knowledge' => $payload['knowledge'] ?? null],
                ],
            );
            foreach ((array) ($payload['objective_codes'] ?? []) as $index => $code) {
                $objective = $objectives->get($this->objectiveKey((string) $code));
                if ($objective) {
                    $unit->learningObjectives()->syncWithoutDetaching([$objective->id => [
                        'role' => 'main',
                        'official_order' => $index + 1,
                        'source_document_id' => $document->id,
                        'source_page' => $candidate->physical_page,
                        'original_text' => (string) $code,
                    ]]);
                }
            }
            foreach ($candidate->children->where('review_status', 'accepted') as $child) {
                $this->publishUnitChild($unit, $document, $child);
            }
        }
    }

    private function publishUnitChild(CurriculumUnit $unit, CurriculumDocument $document, CurriculumImportCandidate $candidate): void
    {
        $order = (int) data_get($candidate->structured_payload, 'official_order', 1);
        $source = (string) ($candidate->source_excerpt ?: $candidate->detected_value);
        if ($candidate->entity_type === 'keyword') {
            $normalized = $this->normalizer->key((string) $candidate->detected_value);
            $keyword = CurriculumKeyword::query()->firstOrCreate(['normalized_text' => $normalized], ['original_text' => trim((string) $candidate->detected_value)]);
            $unit->keywords()->syncWithoutDetaching([$keyword->id => ['official_order' => $order, 'source_document_id' => $document->id, 'source_page' => $candidate->physical_page, 'original_text' => $candidate->detected_value]]);

            return;
        }
        if ($candidate->entity_type === 'skills') {
            $normalized = $this->normalizer->key((string) $candidate->detected_value);
            $skill = CurriculumSkill::query()->updateOrCreate(
                ['identity_hash' => $this->canonical->hash(['name' => $normalized])],
                ['name' => trim((string) $candidate->detected_value), 'normalized_name' => $normalized, 'description' => $source],
            );
            $unit->skills()->syncWithoutDetaching([$skill->id => ['official_order' => $order, 'source_document_id' => $document->id, 'source_page' => $candidate->physical_page, 'original_text' => $source]]);

            return;
        }
        if ($candidate->entity_type === 'attitudes') {
            $attitude = CurriculumAttitude::query()->updateOrCreate(
                ['identity_hash' => $this->canonical->hash(['text' => $this->normalizer->key((string) $candidate->detected_value)])],
                ['text' => trim((string) $candidate->detected_value)],
            );
            $unit->attitudes()->syncWithoutDetaching([$attitude->id => ['official_order' => $order, 'source_document_id' => $document->id, 'source_page' => $candidate->physical_page, 'original_text' => $source]]);

            return;
        }
        $elementType = match ($candidate->entity_type) {
            'knowledge' => 'knowledge',
            'prior_knowledge' => 'prior_knowledge',
            'indicator_block' => 'evaluation_indicator',
            'activity' => 'suggested_activity',
            'suggested_assessment' => 'suggested_assessment',
            'teacher_note' => 'teacher_note',
            default => null,
        };
        if (! $elementType) {
            return;
        }
        $content = (string) data_get($candidate->structured_payload, 'text', $source);
        $element = CurriculumElement::query()->updateOrCreate(
            ['identity_hash' => $this->canonical->hash(['type' => $elementType, 'content' => $this->normalizer->key($content)])],
            ['element_type' => $elementType, 'title' => $candidate->detected_value, 'content' => $content, 'normalized_content' => $this->normalizer->key($content), 'structured_data' => $candidate->structured_payload],
        );
        CurriculumElementRelation::query()->updateOrCreate(
            ['curriculum_element_id' => $element->id, 'related_type' => CurriculumUnit::class, 'related_id' => $unit->id, 'role' => 'primary'],
            ['official_order' => $order, 'source_document_id' => $document->id, 'source_page' => $candidate->physical_page, 'original_text' => $source],
        );
    }

    /** @param Collection<int,CurriculumImportCandidate> $candidates */
    private function publishSkillFormulations(CurriculumProgram $program, CurriculumDocument $document, Collection $candidates): void
    {
        foreach ($candidates->where('entity_type', 'skill_objective')->where('review_status', 'accepted') as $candidate) {
            $wording = (string) data_get($candidate->structured_payload, 'official_text', $candidate->source_excerpt);
            $code = (string) data_get($candidate->structured_payload, 'official_code', $candidate->detected_value);
            $skill = CurriculumSkill::query()->updateOrCreate(
                ['identity_hash' => $this->canonical->hash(['code' => $this->normalizer->key($code), 'wording' => $this->normalizer->key($wording)])],
                ['code' => $code, 'name' => mb_substr($wording, 0, 190), 'normalized_name' => $this->normalizer->key($wording), 'description' => $wording],
            );
            CurriculumSkillFormulation::query()->updateOrCreate(
                ['identity_hash' => $this->canonical->hash(['skill' => $skill->id, 'program' => $program->id, 'wording' => $this->normalizer->key($wording)])],
                ['curriculum_skill_id' => $skill->id, 'curriculum_program_id' => $program->id, 'official_wording' => $wording, 'official_code' => $code, 'source_document_id' => $document->id, 'source_page' => $candidate->physical_page],
            );
        }
    }

    private function objectiveKey(string $code): string
    {
        preg_match('/(?:OA|OAH)?\s*0*([0-9]+|[a-z])$/iu', trim($code), $match);

        return preg_match('/[a-z]/iu', (string) ($match[1] ?? '')) ? 'OAH:'.mb_strtolower((string) $match[1]) : 'OA:'.(int) ($match[1] ?? 0);
    }

    private function axisMatchesObjective(string $axisName, string $axisCode): bool
    {
        $axisCode = $this->normalizer->key($axisCode);

        return match (true) {
            str_contains($axisName, 'vida') => str_contains($axisCode, 'vida'),
            str_contains($axisName, 'fisica') || str_contains($axisName, 'quimica') => str_contains($axisCode, 'fis') || str_contains($axisCode, 'quim') || str_contains($axisCode, 'materia'),
            str_contains($axisName, 'tierra') || str_contains($axisName, 'universo') => str_contains($axisCode, 'tierra') || str_contains($axisCode, 'universo'),
            default => false,
        };
    }

    private function assertReviewable(CurriculumImportFile $file): void
    {
        if (! in_array($file->status, ['pending_review', 'validated'], true)) {
            throw new LibroDigitalException('El archivo no está disponible para revisión.', 'LCD_CURRICULUM_IMPORT_NOT_REVIEWABLE', 409);
        }
    }

    private function refreshBatch(CurriculumImportFile $file): void
    {
        $batch = $file->batch()->firstOrFail();
        $files = $batch->files()->get(['status', 'warnings']);
        $allPublished = $files->every(fn (CurriculumImportFile $item): bool => in_array($item->status, ['published', 'published_with_warnings'], true));
        $batch->forceFill([
            'status' => $allPublished ? 'activated' : 'pending_review',
            'warning_count' => $files->filter(fn (CurriculumImportFile $item): bool => filled($item->warnings))->count(),
            'error_count' => $files->where('status', 'failed')->count(),
            'imported_row_count' => $files->whereIn('status', ['published', 'published_with_warnings'])->count(),
            'completed_at' => $allPublished ? now('UTC') : $batch->completed_at,
        ])->save();
    }

    /** @return list<string> */
    private function programRelations(): array
    {
        return [
            'subject.catalogProfile', 'educationLevel', 'version', 'documents',
            'axes', 'learningObjectives', 'units.learningObjectives', 'units.skills',
            'units.attitudes', 'units.keywords',
        ];
    }
}
