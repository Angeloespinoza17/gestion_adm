<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Http\Requests\LibroDigital\UpdateLessonRecordRequest;
use App\Models\LibroDigital\ClassSession;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CurriculumObjectiveScopeService;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use App\Services\LibroDigital\OptimisticLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonRecordController extends LibroDigitalController
{
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly OptimisticLock $locks,
        private readonly AuditEventWriter $audit,
        private readonly CurriculumObjectiveScopeService $curriculumScope,
    ) {
        parent::__construct($access);
    }

    public function show(Request $request, string $session): JsonResponse
    {
        $model = $this->session($session);
        $this->authorize('view', $model);

        return $this->dataResponse($this->payload($model), version: $model->lock_version);
    }

    public function update(UpdateLessonRecordRequest $request, string $session): JsonResponse
    {
        $model = $this->session($session);
        $this->authorize('lesson', $model);
        $this->locks->assert($model, $request);
        if (in_array($this->statusValue($model->status), ['signing', 'signed', 'closed', 'cancelled'], true)) {
            throw new LibroDigitalException('El leccionario ya no admite edición directa.', 'LCD_SESSION_IMMUTABLE', 409);
        }
        $data = $request->validated();
        $this->curriculumScope->assertAllowed(
            $model->book,
            (int) $model->schedule_subject_id,
            array_values($data['curriculum_objective_ids'] ?? []),
        );

        DB::transaction(function () use ($request, $model, $data): void {
            $locked = ClassSession::query()->lockForUpdate()->findOrFail($model->id);
            $nextRevision = ((int) $locked->revision) + 1;
            DB::table('lcd_session_topics')->insert([
                'class_session_id' => $locked->id,
                'title' => 'Contenido de la clase',
                'description' => $data['contents'],
                'sort_order' => 1,
                'revision' => $nextRevision,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            DB::table('lcd_session_objectives')->insert([
                'class_session_id' => $locked->id,
                'learning_objective_id' => null,
                'objective_code_snapshot' => null,
                'objective_description_snapshot' => $data['objectives'],
                'treatment_level' => $data['treatment_level'] ?? 'introduced',
                'progress_percent' => $data['progress_percentage'] ?? null,
                'sort_order' => 1,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            $objectives = DB::table('lcd_learning_objectives')->whereIn('id', $data['curriculum_objective_ids'] ?? [])->get(['id', 'code', 'description']);
            foreach ($objectives as $index => $objective) {
                DB::table('lcd_session_objectives')->insert([
                    'class_session_id' => $locked->id,
                    'learning_objective_id' => $objective->id,
                    'objective_code_snapshot' => $objective->code,
                    'objective_description_snapshot' => $objective->description,
                    'treatment_level' => $data['treatment_level'] ?? 'introduced',
                    'progress_percent' => $data['progress_percentage'] ?? null,
                    'sort_order' => $index + 2,
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            }
            DB::table('lcd_session_activities')->insert([
                'class_session_id' => $locked->id,
                'activity_type' => 'learning_activity',
                'description' => $data['activities'],
                'sort_order' => 1,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            foreach (['methodology' => 'methodology', 'resources' => 'teaching_resources'] as $field => $type) {
                if (filled($data[$field] ?? null)) {
                    DB::table('lcd_session_resources')->insert([
                        'class_session_id' => $locked->id,
                        'resource_type' => $type,
                        'name' => $field === 'methodology' ? 'Metodología' : 'Recursos',
                        'description' => $data[$field],
                        'sort_order' => $field === 'methodology' ? 1 : 2,
                        'created_at' => now('UTC'),
                        'updated_at' => now('UTC'),
                    ]);
                }
            }
            if (filled($data['observations'] ?? null)) {
                DB::table('lcd_session_observations')->insert([
                    'class_session_id' => $locked->id,
                    'visibility' => 'internal',
                    'observation' => $data['observations'],
                    'created_by' => $request->user()->id,
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            }
            $locked->forceFill([
                'objective_summary' => $data['objectives'],
                'content_summary' => $data['contents'],
                'activity_summary' => $data['activities'],
                'observation' => $data['observations'] ?? $locked->observation,
                'revision' => $nextRevision,
                'lock_version' => ((int) $locked->lock_version) + 1,
                'updated_by' => $request->user()->id,
            ])->save();
        }, 3);

        $fresh = $model->fresh();
        $this->audit->write('lcd.session.lesson_recorded', 'update_lesson', $fresh, actor: $request->user(), schoolId: $fresh->school_id, academicYearId: $fresh->academic_year_id, after: $this->payload($fresh), request: $request, entityRevision: $fresh->revision);

        return $this->dataResponse($this->payload($fresh), version: $fresh->lock_version);
    }

    /** @return array<string, mixed> */
    private function payload(ClassSession $session): array
    {
        $latestObjective = DB::table('lcd_session_objectives')->where('class_session_id', $session->id)->orderByDesc('id')->first();
        $methodology = DB::table('lcd_session_resources')->where('class_session_id', $session->id)->where('resource_type', 'methodology')->orderByDesc('id')->value('description');
        $resources = DB::table('lcd_session_resources')->where('class_session_id', $session->id)->where('resource_type', 'teaching_resources')->orderByDesc('id')->value('description');
        $observations = DB::table('lcd_session_observations')->where('class_session_id', $session->id)->orderByDesc('id')->value('observation');
        $curriculumIds = DB::table('lcd_session_objectives')->where('class_session_id', $session->id)->whereNotNull('learning_objective_id')->pluck('learning_objective_id')->unique()->values();

        return [
            'id' => $session->id,
            'session_id' => $session->id,
            'objectives' => $session->objective_summary ?? '',
            'contents' => $session->content_summary ?? '',
            'activities' => $session->activity_summary ?? '',
            'methodology' => $methodology,
            'resources' => $resources,
            'observations' => $observations ?? $session->observation,
            'curriculum_objective_ids' => $curriculumIds,
            'treatment_level' => $latestObjective?->treatment_level,
            'progress_percentage' => $latestObjective?->progress_percent,
            'revision' => (int) $session->revision,
            'lock_version' => (int) $session->lock_version,
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];
    }
}
