<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestimonialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote' => $this->quote,
            'author_name' => $this->author_name,
            'author_role' => $this->author_role,
            'external_image_url' => $this->external_image_url,
            'image_url' => $this->image_url,
            'preview_image_url' => $this->preview_image_url,
            'image_alt' => $this->image_alt,
            'status' => $this->status,
            'active' => (bool) $this->active,
            'featured' => (bool) $this->featured,
            'sort_order' => (int) $this->sort_order,
            'published_at' => $this->published_at?->toISOString(),
            'authorization_confirmed' => $this->consent_confirmed_at !== null,
            'consent_confirmed_at' => $this->consent_confirmed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
                'email' => $this->createdBy?->email,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
                'email' => $this->updatedBy?->email,
            ]),
            'consent_confirmed_by' => $this->whenLoaded('consentConfirmedBy', fn () => [
                'id' => $this->consentConfirmedBy?->id,
                'name' => $this->consentConfirmedBy?->name,
            ]),
        ];
    }
}
