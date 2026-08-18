<?php
namespace App\Models\Psychology;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PsychologyConsent extends Model
{
    use SoftDeletes;
    protected $table = 'psychology_consents';
    protected $guarded = ['id'];
    protected $casts = ['guardian_informed' => 'boolean', 'informed_at' => 'datetime', 'consent_required' => 'boolean', 'institutional_exception' => 'boolean'];
}
