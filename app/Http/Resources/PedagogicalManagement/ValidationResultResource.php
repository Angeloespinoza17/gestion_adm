<?php

namespace App\Http\Resources\PedagogicalManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValidationResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid, 'code' => $this->code, 'category' => $this->category,
            'severity' => $this->severity, 'outcome' => $this->outcome, 'reliability' => $this->reliability,
            'field_path' => $this->field_path, 'title' => $this->title, 'message' => $this->message,
            'detected_value' => $this->detected_value, 'expected_value' => $this->expected_value,
            'source_excerpt' => $this->source_excerpt, 'page_number' => $this->page_number,
            'is_blocking' => $this->is_blocking, 'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_status' => $this->resolution_status, 'resolution_notes' => $this->resolution_notes,
            'resolver' => $this->whenLoaded('resolver', fn () => $this->resolver ? ['id' => $this->resolver->id, 'name' => $this->resolver->name] : null),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn ($event): array => [
                'id' => $event->uuid, 'action' => $event->action, 'notes' => $event->notes,
                'performed_at' => $event->performed_at?->toIso8601String(),
                'performed_by' => $event->performer ? ['id' => $event->performer->id, 'name' => $event->performer->name] : null,
            ])),
        ];
    }
}
