<?php
namespace App\Models\Psychology;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PsychologyExternalReferral extends Model
{
    use SoftDeletes;
    protected $table = 'psychology_external_referrals';
    protected $guarded = ['id'];
    protected $casts = ['referred_on' => 'date:Y-m-d', 'response_on' => 'date:Y-m-d', 'next_contact_on' => 'date:Y-m-d', 'guardian_informed' => 'boolean', 'follow_up_pending' => 'boolean'];
}
