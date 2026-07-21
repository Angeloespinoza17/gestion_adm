<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceAbsenceReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'category', 'is_sensitive', 'active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_sensitive' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer'];
}
