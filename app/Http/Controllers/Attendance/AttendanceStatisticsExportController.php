<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CreateAttendanceExportRequest;
use App\Models\Attendance\AttendanceExportJob;
use App\Services\Attendance\AttendanceExportService;
use App\Services\Attendance\AttendanceStatisticsAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceStatisticsExportController extends Controller
{
    public function store(
        CreateAttendanceExportRequest $request,
        AttendanceExportService $exports,
        AttendanceStatisticsAuditService $audit,
    ): JsonResponse {
        $export = $exports->create($request->validated(), $request->user());
        $audit->log('export_requested', $export, $request->user(), newValues: $request->validated(), request: $request);

        return response()->json($this->payload($export), 202);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => AttendanceExportJob::query()->where('user_id', $request->user()->id)->latest('id')->limit(30)->get()->map(fn ($export) => $this->payload($export)),
        ]);
    }

    public function show(Request $request, AttendanceExportJob $attendanceExportJob): JsonResponse
    {
        abort_unless($attendanceExportJob->user_id === $request->user()->id || $request->user()->hasPermission('attendance_statistics.manage_reports'), 403);

        return response()->json($this->payload($attendanceExportJob));
    }

    public function download(Request $request, AttendanceExportJob $attendanceExportJob): StreamedResponse
    {
        abort_unless($attendanceExportJob->user_id === $request->user()->id || $request->user()->hasPermission('attendance_statistics.manage_reports'), 403);
        abort_unless($attendanceExportJob->status === 'completed' && $attendanceExportJob->file_path && Storage::disk('local')->exists($attendanceExportJob->file_path), 404);
        $extension = pathinfo($attendanceExportJob->file_path, PATHINFO_EXTENSION);
        $mime = match ($extension) {
            'pdf' => 'application/pdf', 'xls' => 'application/vnd.ms-excel', default => 'text/csv; charset=UTF-8'
        };

        return Storage::disk('local')->download(
            $attendanceExportJob->file_path,
            'asistencia_'.$attendanceExportJob->report_type.'_'.$attendanceExportJob->created_at->format('Ymd_His').'.'.$extension,
            ['Content-Type' => $mime],
        );
    }

    private function payload(AttendanceExportJob $export): array
    {
        return [
            'id' => $export->id, 'uuid' => $export->uuid, 'report_type' => $export->report_type,
            'format' => $export->format, 'status' => $export->status, 'progress' => $export->progress,
            'file_size' => $export->file_size, 'failure_message' => $export->failure_message,
            'completed_at' => $export->completed_at?->toIso8601String(), 'expires_at' => $export->expires_at?->toIso8601String(),
            'download_url' => $export->status === 'completed' ? '/api/attendance-statistics/exports/'.$export->id.'/download' : null,
        ];
    }
}
