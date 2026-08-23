<?php

namespace App\Http\Resources\RiskPrevention;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiskMatrixResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Draft matrices do not have an approved active_version_id yet. The
        // portfolio must still expose their latest editable version.
        $version = $this->activeVersion ?: $this->latestVersion;

        return [
            'id' => $this->id, 'code' => $this->code, 'folio' => $this->folio, 'name' => $this->name, 'description' => $this->description,
            'company_name' => $this->company_name, 'work_center' => $this->workCenter?->only(['id', 'name', 'code']),
            'active_version' => $version ? [
                'id' => $version->id, 'number' => $version->version_number, 'status' => $version->status->value,
                'approved_at' => $version->approved_at, 'next_review_at' => $version->next_review_at,
                'responsible' => $version->programResponsible?->only(['id', 'name']),
            ] : null,
            'risk_count' => (int) ($this->risk_count ?? 0), 'important_count' => (int) ($this->important_count ?? 0),
            'intolerable_count' => (int) ($this->intolerable_count ?? 0), 'overdue_controls_count' => (int) ($this->overdue_controls_count ?? 0),
            'program_progress' => (int) ($this->program_progress ?? 0), 'created_at' => $this->created_at,
        ];
    }
}
