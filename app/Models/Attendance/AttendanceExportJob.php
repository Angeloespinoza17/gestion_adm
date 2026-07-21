<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'user_id', 'academic_year_id', 'report_type', 'format', 'status', 'filters',
        'file_path', 'file_size', 'progress', 'failure_message', 'completed_at', 'expires_at',
    ];

    protected $casts = [
        'filters' => 'array', 'file_size' => 'integer', 'progress' => 'integer',
        'completed_at' => 'datetime', 'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
