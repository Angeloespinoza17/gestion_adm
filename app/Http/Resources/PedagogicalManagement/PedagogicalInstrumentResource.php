<?php

namespace App\Http\Resources\PedagogicalManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedagogicalInstrumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid, 'title' => $this->title, 'grade_label' => $this->grade_label,
            'instrument_type' => $this->instrument_type?->value ?? $this->instrument_type,
            'evaluation_purpose' => $this->evaluation_purpose?->value ?? $this->evaluation_purpose,
            'work_modality' => $this->work_modality?->value ?? $this->work_modality,
            'application_date' => $this->application_date?->format('Y-m-d'),
            'duration_minutes' => $this->duration_minutes, 'declared_total_points' => $this->declared_total_points,
            'passing_percentage' => $this->passing_percentage, 'weighting_percentage' => $this->weighting_percentage,
            'minimum_grade' => $this->minimum_grade, 'maximum_grade' => $this->maximum_grade,
            'accessibility_measures' => $this->accessibility_measures, 'notes' => $this->notes,
            'status' => $this->status?->value ?? $this->status,
            'workflow_status' => $this->workflow_status?->value ?? $this->workflow_status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'school' => $this->whenLoaded('school', fn () => ['id' => $this->school->id, 'name' => $this->school->name, 'rbd' => $this->school->rbd]),
            'academic_year' => $this->whenLoaded('academicYear', fn () => ['id' => $this->academicYear->id, 'name' => $this->academicYear->name, 'year' => $this->academicYear->year]),
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name]),
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'name' => $this->subject->resolvedDisplayName()]),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? ['id' => $this->unit->id, 'code' => $this->unit->unit_code, 'title' => $this->unit->official_title ?: $this->unit->friendly_focus] : null),
            'courses' => $this->whenLoaded('courses', fn () => $this->courses->map(fn ($course): array => ['id' => $course->id, 'name' => $course->display_name, 'education_level_id' => $course->education_level_id])),
            'objectives' => $this->whenLoaded('objectives', fn () => $this->objectives->map(fn ($objective): array => [
                'id' => $objective->id, 'origin' => $objective->origin, 'detected_code' => $objective->detected_code,
                'confirmation_status' => $objective->confirmation_status, 'page_number' => $objective->page_number,
                'source_excerpt' => $objective->source_excerpt,
                'catalog_objective' => $objective->learningObjective ? [
                    'id' => $objective->learningObjective->id, 'code' => $objective->learningObjective->code,
                    'description' => $objective->learningObjective->description,
                ] : null,
            ])),
            'files' => $this->whenLoaded('files', fn () => $this->files->map(fn ($file): array => [
                'id' => $file->uuid, 'version' => $file->version, 'original_filename' => $file->original_filename,
                'mime_type' => $file->mime_type, 'file_size' => $file->file_size, 'sha256' => $file->sha256,
                'page_count' => $file->page_count, 'is_encrypted' => $file->is_encrypted,
                'has_text_layer' => $file->has_text_layer, 'uploaded_at' => $file->created_at?->toIso8601String(),
                'uploaded_by' => $file->relationLoaded('uploader') && $file->uploader ? ['id' => $file->uploader->id, 'name' => $file->uploader->name] : null,
            ])),
            'latest_file' => $this->whenLoaded('latestFile', fn () => $this->latestFile ? [
                'id' => $this->latestFile->uuid, 'version' => $this->latestFile->version,
                'original_filename' => $this->latestFile->original_filename, 'mime_type' => $this->latestFile->mime_type,
                'page_count' => $this->latestFile->page_count,
                'file_size' => $this->latestFile->file_size, 'is_encrypted' => $this->latestFile->is_encrypted,
                'has_text_layer' => $this->latestFile->has_text_layer,
            ] : null),
            'latest_analysis' => $this->whenLoaded('latestAnalysisRun', fn () => $this->latestAnalysisRun ? new AnalysisRunResource($this->latestAnalysisRun) : null),
            'reviews' => $this->whenLoaded('reviews', fn () => PedagogicalReviewResource::collection($this->reviews)),
            'latest_review' => $this->whenLoaded('latestReview', fn () => $this->latestReview ? new PedagogicalReviewResource($this->latestReview) : null),
            'latest_ai_report' => $this->when(
                $request->user()?->hasPermission('pedagogical-instruments.ai-report') === true
                    || $request->user()?->hasPermission('pedagogical-instruments.ai-workspace') === true,
                fn () => $this->relationLoaded('latestAiReport') && $this->latestAiReport ? new PedagogicalAiReportResource($this->latestAiReport) : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
