<?php

namespace App\Http\Resources\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ClassPresentationFileType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassPresentationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ownsCanvaConnection = (int) $request->user()?->id === (int) $this->user_id;
        $provider = (string) ($this->presentation_provider ?: data_get($this->configuration, 'presentation_provider', 'powerpoint'));
        $files = $this->whenLoaded('files', fn () => $this->files->map(fn ($file): array => [
            'id' => $file->uuid, 'type' => $file->type?->value ?? $file->type, 'version' => $file->version,
            'filename' => $file->filename, 'mime_type' => $file->mime_type, 'size' => $file->size,
            'checksum' => $file->checksum, 'metadata' => $file->metadata,
            'download_url' => route('api.class-presentations.files.download', [$this->uuid, $file->uuid]),
        ]));

        return [
            'id' => $this->uuid, 'series_id' => $this->series_uuid, 'version' => $this->version,
            'title' => $this->title, 'status' => $this->status?->value ?? $this->status,
            'progress' => $this->progress, 'presentation_provider' => $provider,
            'configuration' => $this->configuration,
            'curricular_snapshot' => $this->curricular_snapshot,
            'deck' => $this->when($this->resource->getAttribute('include_deck') === true, $this->deck_json),
            'model' => $this->model, 'prompt_name' => $this->prompt_name, 'prompt_version' => $this->prompt_version,
            'openai_response_id' => $this->when($request->user()?->hasPermission('class-presentations.view-all') === true, $this->openai_response_id),
            'failure_code' => $this->failure_code, 'failure_message' => $this->failure_message,
            'generated_at' => $this->generated_at?->toIso8601String(), 'archived_at' => $this->archived_at?->toIso8601String(),
            'school' => $this->whenLoaded('school', fn () => ['id' => $this->school->id, 'name' => $this->school->name, 'rbd' => $this->school->rbd]),
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name, 'year' => $this->academicYear->year]),
            'course' => $this->whenLoaded('course', fn () => ['id' => $this->course->id, 'name' => $this->course->display_name]),
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'name' => $this->subject->resolvedDisplayName(), 'code' => $this->subject->code]),
            'unit' => $this->whenLoaded('unit', fn () => ['id' => $this->unit->id, 'code' => $this->unit->unit_code, 'title' => $this->unit->official_title ?: $this->unit->friendly_focus]),
            'author' => $this->whenLoaded('author', fn () => ['id' => $this->author->id, 'name' => $this->author->name]),
            'objectives' => $this->whenLoaded('learningObjectives', fn () => $this->learningObjectives->map(fn ($objective): array => ['id' => $objective->id, 'code' => $objective->code, 'description' => $objective->description])),
            'files' => $files,
            'preview_files' => $this->when($this->relationLoaded('files'), fn () => $this->files->filter(fn ($file) => $file->type === ClassPresentationFileType::Preview)->values()->map(fn ($file): array => [
                'id' => $file->uuid, 'slide_number' => (int) data_get($file->metadata, 'slide_number'),
                'url' => route('api.class-presentations.files.download', [$this->uuid, $file->uuid, 'inline' => 1]),
            ])),
            'runs' => $this->whenLoaded('generationRuns', fn () => $this->generationRuns->map(fn ($run): array => [
                'id' => $run->uuid, 'status' => $run->status?->value ?? $run->status, 'model' => $run->model,
                'input_tokens' => $run->input_tokens, 'output_tokens' => $run->output_tokens,
                'duration_ms' => $run->duration_ms, 'failure_code' => $run->failure_code,
                'failure_message' => $run->failure_message, 'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ])),
            'canva' => [
                'status' => $this->canva_status?->value ?? $this->canva_status,
                'brand_template_id' => $this->canva_brand_template_id,
                'brand_template_title' => $this->canva_brand_template_title,
                'autofill_job_id' => $this->when($ownsCanvaConnection, $this->canva_autofill_job_id),
                'design_id' => $this->canva_design_id,
                'design_url' => $this->when($ownsCanvaConnection, $this->canva_design_url),
                'edit_url' => $this->when($ownsCanvaConnection, $this->canva_edit_url),
                'view_url' => $this->when($ownsCanvaConnection, $this->canva_view_url),
                'thumbnail_url' => $this->when(
                    $ownsCanvaConnection && $this->canva_thumbnail_expires_at?->isFuture(),
                    $this->canva_thumbnail_url,
                ),
                'failure_code' => $this->canva_failure_code,
                'failure_message' => $this->canva_failure_message,
                'submitted_at' => $this->canva_submitted_at?->toIso8601String(),
                'completed_at' => $this->canva_completed_at?->toIso8601String(),
                'urls_expires_at' => $this->when(
                    $ownsCanvaConnection && $this->canva_urls_refreshed_at,
                    $this->canva_urls_refreshed_at?->copy()->addDays(30)->toIso8601String(),
                ),
            ],
            'can' => [
                'download' => $request->user()?->can('download', $this->resource) ?? false,
                'regenerate' => $request->user()?->can('regenerate', $this->resource) ?? false,
                'archive' => $request->user()?->can('archive', $this->resource) ?? false,
                'edit_canva' => $ownsCanvaConnection
                    && $provider === 'canva'
                    && ($this->canva_status?->value ?? $this->canva_status) === 'success'
                    && (bool) $this->canva_design_id,
                'sync_canva' => $ownsCanvaConnection
                    && $provider === 'canva'
                    && ($this->status?->value ?? $this->status) === 'ready'
                    && in_array(
                        $this->canva_status?->value ?? $this->canva_status,
                        [null, 'pending', 'failed'],
                        true,
                    )
                    && $this->canva_failure_code !== 'CANVA_SUBMISSION_UNCONFIRMED',
            ],
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
