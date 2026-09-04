<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentLifePostImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'preview_url' => $this->preview_url,
            'alt' => $this->alt_text,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
