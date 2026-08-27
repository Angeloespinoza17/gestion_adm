<?php

namespace App\Http\Resources\PedagogicalManagement;

use App\Enums\PedagogicalManagement\ReviewDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedagogicalReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = (int) $request->user()?->id === (int) $this->instrument?->owner_user_id;
        // El controlador/policy ya autorizó el instrumento; evita repetir una consulta
        // de alcance por cada elemento del historial serializado.
        $canReview = $request->user()?->hasPermission('pedagogical-instruments.decide') === true;
        $teacherCanReceiveAi = $isOwner
            && $this->share_ai_report
            && $this->decision === ReviewDecision::RectificationRequested;
        $showAi = $canReview || $teacherCanReceiveAi;

        return [
            'id' => $this->uuid,
            'decision' => $this->decision?->value ?? $this->decision,
            'decision_label' => match ($this->decision) {
                ReviewDecision::Approved => 'Aprobado',
                ReviewDecision::ApprovedWithObservations => 'Aprobado con observaciones',
                ReviewDecision::RectificationRequested => 'Rectificación solicitada',
                default => 'Revisión registrada',
            },
            'coordinator_notes' => $this->coordinator_notes,
            'share_ai_report' => (bool) ($canReview ? $this->share_ai_report : $teacherCanReceiveAi),
            'can_download_ai_report' => (bool) ($showAi && $this->ai_report_id),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name] : null),
            'file' => $this->whenLoaded('instrumentFile', fn () => $this->instrumentFile ? [
                'id' => $this->instrumentFile->uuid,
                'version' => $this->instrumentFile->version,
                'original_filename' => $this->instrumentFile->original_filename,
            ] : null),
            'guidance_documents' => $this->whenLoaded('guidanceDocuments', fn () => $this->guidanceDocuments->map(fn ($document): array => [
                'id' => $document->uuid,
                'document_type' => $document->document_type,
                'title' => $document->title,
                'description' => $document->description,
                'content' => $document->content,
            ])->values()),
            'ai_report' => $showAi && $this->relationLoaded('aiReport') && $this->aiReport
                ? new PedagogicalAiReportResource($this->aiReport)
                : null,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
