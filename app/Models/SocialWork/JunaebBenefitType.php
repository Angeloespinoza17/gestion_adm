<?php
namespace App\Models\SocialWork;
class JunaebBenefitType extends SocialWorkModel { protected $table = 'junaeb_benefit_types'; protected $casts = ['eligible_level_codes' => 'array', 'active' => 'boolean']; }
