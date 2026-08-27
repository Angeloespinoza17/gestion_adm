<?php

namespace App\Http\Resources\PedagogicalManagement;

use App\Support\PedagogicalManagement\PedagogicalAiReportStatistics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PedagogicalAiReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status?->value ?? $this->status,
            'model' => $this->model,
            'prompt_version' => $this->prompt_version,
            'file_id' => $this->instrumentFile?->uuid,
            'file_version' => $this->instrumentFile?->version,
            'report' => is_array($this->report) ? PedagogicalAiReportStatistics::enrich($this->report) : null,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'requested_by' => $this->whenLoaded('requester', fn () => $this->requester ? ['id' => $this->requester->id, 'name' => $this->requester->name] : null),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
