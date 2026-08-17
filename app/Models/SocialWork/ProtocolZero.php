<?php
namespace App\Models\SocialWork;
class ProtocolZero extends SocialWorkModel { protected $table = 'social_work_protocol_zero'; protected $casts = ['received_at' => 'datetime', 'urgent_attention' => 'boolean', 'notified_people' => 'array', 'evaluation_due_at' => 'datetime', 'decided_at' => 'datetime']; }
