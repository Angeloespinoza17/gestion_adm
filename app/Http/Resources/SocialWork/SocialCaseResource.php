<?php
namespace App\Http\Resources\SocialWork;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class SocialCaseResource extends JsonResource {
    public function toArray(Request $request): array {
        $data = parent::toArray($request);
        if (! $request->user()?->hasPermission('social_work.confidential.view')) unset($data['initial_description'], $data['initial_safeguards'], $data['closure_conclusion']);
        return $data;
    }
}
