<?php

namespace App\Services\Attendance;

use App\Jobs\GenerateAttendanceStatisticsExport;
use App\Models\Attendance\AttendanceExportJob;
use App\Models\User;
use Illuminate\Support\Str;

class AttendanceExportService
{
    public function create(array $data, User $user): AttendanceExportJob
    {
        $export = AttendanceExportJob::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'academic_year_id' => $data['academic_year_id'],
            'report_type' => $data['report_type'],
            'format' => $data['format'],
            'status' => 'pending',
            'filters' => [...($data['filters'] ?? []), 'academic_year_id' => $data['academic_year_id']],
            'progress' => 0,
            'expires_at' => now()->addDays(7),
        ]);
        GenerateAttendanceStatisticsExport::dispatch($export->id);

        return $export->fresh();
    }
}
