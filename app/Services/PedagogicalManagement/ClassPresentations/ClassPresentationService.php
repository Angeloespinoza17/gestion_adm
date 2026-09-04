<?php

namespace App\Services\PedagogicalManagement\ClassPresentations;

use App\Enums\PedagogicalManagement\CanvaPublicationStatus;
use App\Enums\PedagogicalManagement\ClassPresentationStatus;
use App\Jobs\PedagogicalManagement\GenerateClassPresentationJob;
use App\Models\PedagogicalManagement\CanvaConnection;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaAutofillAccessPolicy;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaConnectionLocator;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTemplateFieldMapper;
use App\Services\PedagogicalManagement\ClassPresentations\Canva\CanvaTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClassPresentationService
{
    public function __construct(
        private readonly ClassPresentationCatalogService $catalogs,
        private readonly PresentationTitleSuggester $titles,
        private readonly ReferenceMaterialService $references,
        private readonly PresentationFileStorage $generatedFiles,
        private readonly AuditEventWriter $audit,
        private readonly PresentationStyleContract $styleContract,
        private readonly CanvaConnectionLocator $canvaConnections,
        private readonly CanvaAutofillAccessPolicy $canvaAutofillAccess,
        private readonly CanvaTemplateService $canvaTemplates,
        private readonly CanvaTemplateFieldMapper $canvaMapper,
    ) {}

    /** @param array<string,mixed> $data @param list<UploadedFile> $referenceFiles */
    public function create(array $data, array $referenceFiles, User $actor, Request $request): ClassPresentation
    {
        $quota = (int) config('class_presentations.generation.per_user_per_day', 20);
        if ($quota > 0 && ClassPresentation::query()->where('user_id', $actor->id)->whereDate('created_at', today())->count() >= $quota) {
            throw ValidationException::withMessages(['quota' => "Alcanzaste la cuota diaria de {$quota} presentaciones."]);
        }
        $school = $this->catalogs->school($actor, (int) $data['school_id']);
        $provider = (string) ($data['presentation_provider'] ?? 'powerpoint');
        $canvaConnection = $provider === 'canva'
            ? $this->canvaConnection($actor, (int) $school->id)
            : null;
        if ($canvaConnection) {
            $this->assertCanvaTemplate(
                $canvaConnection,
                (string) ($data['canva_template_id'] ?? ''),
                (int) ($data['slide_count'] ?? 0),
            );
        }
        $years = $this->catalogs->academicYears($school);
        abort_unless($years->contains('id', (int) $data['academic_year_id']), 422, 'El año académico no pertenece al establecimiento.');
        $course = $this->catalogs->course((int) $data['course_id'], (int) $data['academic_year_id']);
        $subject = $this->catalogs->subjectForCourse($course, (int) $data['subject_id']);
        $unit = $this->catalogs->unitForCourseSubject($course, $subject, (int) $data['unit_id']);
        $objectives = $this->catalogs->selectedObjectives($unit, array_map('intval', $data['learning_objective_ids']));
        $suggestions = $this->titles->suggest(
            $unit->official_title ?: $unit->friendly_focus ?: $unit->unit_code,
            $objectives->map->only(['code', 'description'])->all(),
            (string) $data['class_type'],
        );
        if (! in_array($data['title'], $suggestions, true)) {
            throw ValidationException::withMessages(['title' => 'Selecciona uno de los títulos sugeridos por el sistema.']);
        }

        $configuration = Arr::only($data, [
            'presentation_provider', 'canva_template_id', 'canva_template_title',
            'class_type', 'duration_minutes', 'slide_count', 'prior_knowledge', 'depth', 'methodology', 'tone',
            'opening', 'activity', 'assessment', 'aspect_ratio', 'visual_style', 'palette', 'visual_resources',
            'speaker_notes', 'bibliography', 'web_research', 'generate_pdf', 'generate_teacher_guide', 'generate_activity', 'generate_assessment',
            'include_cover', 'include_objectives', 'include_synthesis', 'include_closure',
        ]);
        $configuration = $this->styleContract->ensure($configuration, [
            'name' => $course->display_name,
            'level' => $course->educationLevel?->name,
        ]);
        $configuration['labels'] = $this->configurationLabels($configuration);
        $snapshot = [
            'school' => ['id' => $school->id, 'uuid' => $school->public_id, 'name' => $school->name, 'rbd' => $school->rbd],
            'academic_year' => $years->firstWhere('id', (int) $data['academic_year_id'])->only(['id', 'name', 'year']),
            'course' => ['id' => $course->id, 'name' => $course->display_name, 'education_level_id' => $course->education_level_id, 'level' => $course->educationLevel?->name],
            'subject' => ['id' => $subject->id, 'name' => $subject->resolvedDisplayName(), 'code' => $subject->code],
            'unit' => ['id' => $unit->id, 'code' => $unit->unit_code, 'title' => $unit->official_title ?: $unit->friendly_focus ?: $unit->unit_code, 'focus' => $unit->friendly_focus, 'purpose' => $unit->purpose],
            'objectives' => $objectives->map(fn ($objective): array => ['id' => $objective->id, 'code' => $objective->code, 'description' => $objective->description])->values()->all(),
            'author' => ['id' => $actor->id, 'name' => $actor->name],
            'captured_at' => now()->toIso8601String(),
        ];

        $presentation = DB::transaction(function () use ($school, $actor, $data, $configuration, $snapshot, $objectives, $provider, $canvaConnection): ClassPresentation {
            $presentation = ClassPresentation::query()->create([
                'school_id' => $school->id, 'user_id' => $actor->id, 'academic_year_id' => $data['academic_year_id'],
                'course_id' => $data['course_id'], 'subject_id' => $data['subject_id'], 'unit_id' => $data['unit_id'],
                'title' => $data['title'], 'status' => ClassPresentationStatus::Queued, 'progress' => 0,
                'presentation_provider' => $provider,
                'canva_connection_id' => $canvaConnection?->id,
                'canva_brand_template_id' => $provider === 'canva' ? $data['canva_template_id'] : null,
                'canva_brand_template_title' => $provider === 'canva' ? $data['canva_template_title'] : null,
                'canva_status' => $provider === 'canva' ? CanvaPublicationStatus::Pending : null,
                'configuration' => $configuration, 'curricular_snapshot' => $snapshot,
                'model' => (string) config('class_presentations.openai.model'),
                'prompt_name' => (string) config('class_presentations.openai.prompt_name'),
                'prompt_version' => (string) config('class_presentations.openai.prompt_version'),
            ]);
            $presentation->learningObjectives()->sync($objectives->pluck('id'));

            return $presentation;
        }, 3);

        try {
            foreach ($referenceFiles as $file) {
                $this->references->store($presentation, $file, $actor);
            }
        } catch (Throwable $exception) {
            foreach ($presentation->referenceFiles()->get() as $stored) {
                $this->references->deleteStored($stored);
                $stored->delete();
            }
            $presentation->learningObjectives()->detach();
            $presentation->delete();
            throw $exception;
        }

        GenerateClassPresentationJob::dispatch($presentation->id)
            ->onQueue((string) config('class_presentations.generation.queue', 'class-presentations'))
            ->afterCommit();
        $this->audit->write(
            'pedagogical.class_presentation.requested', 'generate', $presentation,
            actor: $actor, schoolId: $school->id, academicYearId: $presentation->academic_year_id,
            after: ['uuid' => $presentation->uuid, 'version' => 1, 'slide_count' => $configuration['slide_count']], request: $request,
        );

        return $presentation;
    }

    public function regenerate(ClassPresentation $source, User $actor, Request $request): ClassPresentation
    {
        $configuration = $this->styleContract->ensure((array) $source->configuration, (array) data_get($source->curricular_snapshot, 'course', []));
        unset($configuration['canva_trial']);
        $configuration['labels'] = $this->configurationLabels($configuration);
        $provider = (string) ($source->presentation_provider ?: data_get($configuration, 'presentation_provider', 'powerpoint'));
        $canvaConnection = $provider === 'canva'
            ? $this->canvaConnection($actor, (int) $source->school_id)
            : null;
        if ($canvaConnection) {
            $this->assertCanvaTemplate(
                $canvaConnection,
                (string) data_get($configuration, 'canva_template_id'),
                (int) data_get($configuration, 'slide_count'),
            );
        }
        $presentation = DB::transaction(function () use ($source, $actor, $configuration, $provider, $canvaConnection): ClassPresentation {
            $latest = ClassPresentation::query()->where('series_uuid', $source->series_uuid)->lockForUpdate()->orderByDesc('version')->firstOrFail();
            $version = (int) $latest->version + 1;
            $copy = ClassPresentation::query()->create([
                'series_uuid' => $source->series_uuid, 'version' => $version,
                'school_id' => $source->school_id, 'user_id' => $actor->id,
                'academic_year_id' => $source->academic_year_id, 'course_id' => $source->course_id,
                'subject_id' => $source->subject_id, 'unit_id' => $source->unit_id, 'title' => $source->title,
                'status' => ClassPresentationStatus::Queued, 'progress' => 0,
                'presentation_provider' => $provider,
                'canva_connection_id' => $canvaConnection?->id,
                'canva_brand_template_id' => $provider === 'canva' ? data_get($configuration, 'canva_template_id') : null,
                'canva_brand_template_title' => $provider === 'canva' ? data_get($configuration, 'canva_template_title') : null,
                'canva_status' => $provider === 'canva' ? CanvaPublicationStatus::Pending : null,
                'configuration' => $configuration,
                'curricular_snapshot' => [...$source->curricular_snapshot, 'author' => ['id' => $actor->id, 'name' => $actor->name], 'captured_at' => now()->toIso8601String()],
                'model' => (string) config('class_presentations.openai.model'),
                'prompt_name' => (string) config('class_presentations.openai.prompt_name'),
                'prompt_version' => (string) config('class_presentations.openai.prompt_version'),
            ]);
            $copy->learningObjectives()->sync($source->learningObjectives()->pluck('lcd_learning_objectives.id'));
            foreach ($source->referenceFiles as $reference) {
                $copy->referenceFiles()->create($reference->only(['disk', 'path', 'original_filename', 'mime_type', 'size', 'checksum', 'uploaded_by']));
            }

            return $copy;
        }, 3);
        GenerateClassPresentationJob::dispatch($presentation->id)->onQueue((string) config('class_presentations.generation.queue'))->afterCommit();
        $this->audit->write(
            'pedagogical.class_presentation.regenerated', 'regenerate', $presentation,
            actor: $actor, schoolId: $presentation->school_id, academicYearId: $presentation->academic_year_id,
            before: ['source_uuid' => $source->uuid, 'version' => $source->version],
            after: ['uuid' => $presentation->uuid, 'version' => $presentation->version], request: $request,
        );

        return $presentation;
    }

    public function retry(ClassPresentation $presentation, User $actor, Request $request): ClassPresentation
    {
        if ($presentation->status !== ClassPresentationStatus::Failed) {
            throw ValidationException::withMessages(['status' => 'Solo se pueden reintentar presentaciones fallidas.']);
        }
        $presentation->load('files');
        $this->generatedFiles->purgeGeneratedFiles($presentation);
        $presentation->forceFill(['status' => ClassPresentationStatus::Queued, 'progress' => 0, 'failure_code' => null, 'failure_message' => null])->save();
        GenerateClassPresentationJob::dispatch($presentation->id)->onQueue((string) config('class_presentations.generation.queue'))->afterCommit();
        $this->audit->write(
            'pedagogical.class_presentation.retried', 'retry', $presentation,
            actor: $actor, schoolId: $presentation->school_id, academicYearId: $presentation->academic_year_id,
            after: ['uuid' => $presentation->uuid, 'version' => $presentation->version], request: $request,
        );

        return $presentation;
    }

    public function archive(ClassPresentation $presentation, User $actor, Request $request): void
    {
        $before = ['status' => $presentation->status->value];
        $presentation->forceFill(['status' => ClassPresentationStatus::Archived, 'archived_at' => now()])->save();
        $this->audit->write(
            'pedagogical.class_presentation.archived', 'archive', $presentation,
            actor: $actor, schoolId: $presentation->school_id, academicYearId: $presentation->academic_year_id,
            before: $before, after: ['status' => ClassPresentationStatus::Archived->value], request: $request,
        );
    }

    /** @param array<string,mixed> $configuration @return array<string,mixed> */
    private function configurationLabels(array $configuration): array
    {
        $labels = [
            'presentation_provider' => ($configuration['presentation_provider'] ?? 'powerpoint') === 'canva'
                ? 'Canva · Brand Template Autofill'
                : 'PowerPoint editable',
        ];
        foreach (['class_type', 'prior_knowledge', 'depth', 'methodology', 'tone', 'opening', 'activity', 'assessment', 'aspect_ratio', 'visual_style', 'palette', 'visual_resources'] as $key) {
            $value = $configuration[$key] ?? null;
            if (is_array($value)) {
                $labels[$key] = array_values(array_map(
                    fn (mixed $item): string => (string) config("class_presentations.options.{$key}.{$item}", $item),
                    $value,
                ));
            } else {
                $labels[$key] = config("class_presentations.options.{$key}.{$value}", $value);
            }
        }

        return $labels;
    }

    private function canvaConnection(User $actor, int $schoolId): CanvaConnection
    {
        $connection = $this->canvaConnections->active($actor, $schoolId);
        if (! $this->canvaAutofillAccess->allows($connection)) {
            throw ValidationException::withMessages([
                'presentation_provider' => 'La cuenta Canva conectada no tiene Autofill habilitado y el trial de desarrollo no está disponible.',
            ]);
        }

        return $connection;
    }

    private function assertCanvaTemplate(CanvaConnection $connection, string $templateId, int $slideCount): void
    {
        if (trim($templateId) === '' || $slideCount <= 0) {
            throw ValidationException::withMessages([
                'canva_template_id' => 'Selecciona una plantilla Canva compatible con la cantidad de diapositivas.',
            ]);
        }

        $validation = $this->canvaMapper->validate(
            $this->canvaTemplates->dataset($connection, $templateId),
            $slideCount,
        );
        if (! $validation['compatible']) {
            $missing = array_values(array_merge(
                (array) $validation['missing_fields'],
                (array) $validation['invalid_type_fields'],
            ));
            $detail = $missing === [] ? '' : ' Campos pendientes: '.implode(', ', array_slice($missing, 0, 8)).'.';

            throw ValidationException::withMessages([
                'canva_template_id' => 'La plantilla Canva no cumple el contrato Autofill requerido.'.$detail,
            ]);
        }
    }
}
