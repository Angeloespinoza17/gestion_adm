<?php
namespace App\Models\SocialWork;
class ProgramType extends SocialWorkModel { protected $table = 'social_work_program_types'; protected $casts = ['configuration' => 'array', 'active' => 'boolean']; }
