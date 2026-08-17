<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FormTemplateVersion extends SocialWorkModel {
    protected $table = 'social_work_form_template_versions';
    protected $casts = ['schema' => 'array', 'role_visibility' => 'array', 'signature_blocks' => 'array', 'effective_from' => 'date:Y-m-d'];
    public function template(): BelongsTo { return $this->belongsTo(FormTemplate::class); }
}
