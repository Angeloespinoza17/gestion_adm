<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteInstallationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
            'summary' => $this->summary,
            'body' => $this->body,
            'body_html' => $this->body_html,
            'location_label' => $this->location_label,
            'capacity' => $this->capacity,
            'capacity_label' => $this->capacity_label,
            'accessibility_notes' => $this->accessibility_notes,
            'features' => array_values($this->features ?? []),
            'icon' => $this->icon,
            'icon_class' => $this->icon_class,
            'cover_image_url' => $this->cover_image_url,
            'preview_cover_image_url' => $this->preview_cover_image_url,
            'cover_image_alt' => $this->cover_image_alt,
            'gallery_images' => SiteInstallationImageResource::collection(
                $this->whenLoaded('galleryImages'),
            ),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'status' => $this->status,
            'active' => (bool) $this->active,
            'featured' => (bool) $this->featured,
            'sort_order' => (int) $this->sort_order,
            'published_at' => $this->published_at?->toISOString(),
            'public_url' => $this->public_url,
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
        ];
    }
}
