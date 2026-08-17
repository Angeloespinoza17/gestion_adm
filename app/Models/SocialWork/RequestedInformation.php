<?php
namespace App\Models\SocialWork;
class RequestedInformation extends SocialWorkModel { protected $table = 'social_work_requested_information'; protected $casts = ['requested_at' => 'datetime', 'due_at' => 'datetime']; }
