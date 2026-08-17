<?php
namespace App\Models\SocialWork;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class FormTemplate extends SocialWorkModel {
    use SoftDeletes;
    protected $table = 'social_work_form_templates';
    protected $casts = ['active' => 'boolean'];
    public function versions(): HasMany { return $this->hasMany(FormTemplateVersion::class, 'template_id')->latest('version'); }
}
