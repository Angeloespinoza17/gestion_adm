<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance\AttendancePatternDetection;
use App\Models\Attendance\AttendanceRiskSnapshot;
use App\Models\Security\SecurityNotification;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Attendance\Patterns\AttendanceDataset;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceAnalysisService
{
    /** @var Collection<int,int>|null */
    private ?Collection $notificationRecipients = null;

    public function __construct(
        private readonly AttendanceAnalyticsService $analytics,
        private readonly AttendanceManagementSettingsService $settings,
        private readonly AttendanceStatisticsCache $cache,
    ) {}

    public function analyzeYear(AcademicYear|int $academicYear, ?string $asOf = null, ?int $courseSectionId = null): array
    {
        $year = $academicYear instanceof AcademicYear ? $academicYear : AcademicYear::query()->findOrFail($academicYear);
        $asOf = CarbonImmutable::parse($asOf ?: now(config('attendance_management.timezone'))->toDateString())
            ->min(CarbonImmutable::parse($year->ends_at))->max(CarbonImmutable::parse($year->starts_at))->toDateString();
        $settings = $this->settings->forYear($year->id);
        $nonSchoolDates = DB::table('school_days')->where('academic_year_id', $year->id)->where('is_school_day', false)->pluck('date');
        $studentQuery = StudentProfile::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $year->id)
                ->when($courseSectionId, fn ($inner) => $inner->where('course_section_id', $courseSectionId)));
        $stats = ['students' => 0, 'snapshots' => 0, 'patterns' => 0, 'alerts' => 0, 'resolved_patterns' => 0];
        $chunkSize = (int) config('attendance_management.analysis_chunk_size', 200);

        $studentQuery->select('id')->orderBy('id')->chunkById($chunkSize, function (Collection $students) use ($year, $asOf, $courseSectionId, $settings, $nonSchoolDates, &$stats): void {
            $studentIds = $students->pluck('id')->map(fn ($id) => (int) $id)->values();
            $records = $this->analytics->validRecordsQuery($year->id, $asOf)
                ->whereIn('ar.student_profile_id', $studentIds)
                ->when($courseSectionId, fn ($query) => $query->where('ar.course_section_id', $courseSectionId))
                ->orderBy('ar.student_profile_id')->orderBy('ar.attendance_date')
                ->get($this->analytics->recordColumns())
                ->groupBy('student_profile_id');
            $enrollments = StudentEnrollment::query()
                ->where('academic_year_id', $year->id)
                ->whereIn('student_profile_id', $studentIds)
                ->when($courseSectionId, fn ($query) => $query->where('course_section_id', $courseSectionId))
                ->latest('id')->get(['id', 'student_profile_id', 'course_section_id'])
                ->unique('student_profile_id')->keyBy('student_profile_id');
            $previousCases = DB::table('attendance_cases')->where('academic_year_id', $year->id)->whereIn('student_profile_id', $studentIds)
                ->select('student_profile_id')->selectRaw('COUNT(*) as aggregate')->groupBy('student_profile_id')->pluck('aggregate', 'student_profile_id');
            $previousSnapshots = AttendanceRiskSnapshot::query()->where('academic_year_id', $year->id)->whereIn('student_profile_id', $studentIds)
                ->whereDate('snapshot_date', '<', $asOf)->orderByDesc('snapshot_date')->get(['student_profile_id', 'risk_level', 'risk_score', 'snapshot_date'])
                ->unique('student_profile_id')->keyBy('student_profile_id');
            $existingPatterns = AttendancePatternDetection::query()->where('academic_year_id', $year->id)->whereIn('student_profile_id', $studentIds)->where('is_active', true)->get()->keyBy(fn ($row) => $row->student_profile_id.'|'.$row->pattern_type);

            $snapshotRows = [];
            $patternRows = [];
            $detectedPatternKeys = [];
            $analysisByStudent = [];
            $now = now();
            foreach ($studentIds as $studentId) {
                $enrollment = $enrollments->get($studentId);
                $dataset = new AttendanceDataset(
                    $studentId,
                    $year->id,
                    $enrollment?->course_section_id ? (int) $enrollment->course_section_id : null,
                    collect($records->get($studentId, [])),
                    $nonSchoolDates,
                    $settings['pattern_settings'],
                );
                $analysis = $this->analytics->analyzeDataset($dataset, (int) ($previousCases[$studentId] ?? 0) > 0);
                $summary = $analysis['summary'];
                $risk = $analysis['risk'];
                $analysisByStudent[$studentId] = ['analysis' => $analysis, 'course_id' => $dataset->courseSectionId, 'previous' => $previousSnapshots->get($studentId)];
                $snapshotRows[] = [
                    'student_profile_id' => $studentId, 'academic_year_id' => $year->id, 'course_section_id' => $dataset->courseSectionId,
                    'snapshot_date' => $asOf, 'school_days_elapsed' => $summary['school_days_elapsed'], 'days_present' => $summary['days_present'],
                    'days_absent' => $summary['days_absent'], 'justified_absences' => $summary['justified_absences'], 'unjustified_absences' => $summary['unjustified_absences'],
                    'late_arrivals' => $summary['late_arrivals'], 'early_departures' => $summary['early_departures'], 'attendance_percentage' => $summary['attendance_percentage'],
                    'attendance_last_30_days' => $summary['attendance_last_30_days'], 'attendance_last_15_days' => $summary['attendance_last_15_days'],
                    'risk_level' => $risk['level'], 'risk_score' => $risk['score'], 'trend' => $summary['trend'], 'trend_points' => $summary['trend_points'],
                    'consecutive_absences' => $summary['consecutive_absences'], 'risk_reasons' => json_encode($risk['reasons'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'metrics' => json_encode(['score_band' => $risk['score_band'], 'critical_triggers' => $risk['critical_triggers']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ];
                foreach ($analysis['patterns_detected'] as $pattern) {
                    $key = $studentId.'|'.$pattern['type'];
                    $detectedPatternKeys[$key] = true;
                    $existing = $existingPatterns->get($key);
                    $patternRows[] = [
                        'student_profile_id' => $studentId, 'academic_year_id' => $year->id, 'course_section_id' => $dataset->courseSectionId,
                        'pattern_type' => $pattern['type'], 'severity' => $pattern['severity'], 'confidence_score' => $pattern['confidence'] * 100,
                        'confidence_label' => $pattern['confidence_label'], 'occurrence_count' => $pattern['occurrences'], 'description' => $pattern['description'],
                        'metrics' => json_encode($pattern['metrics'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'period' => json_encode($pattern['period'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'first_detected_at' => $existing?->first_detected_at ?? $now, 'last_detected_at' => $now,
                        'is_active' => true, 'resolved_at' => null, 'created_at' => $existing?->created_at ?? $now, 'updated_at' => $now,
                    ];
                }
            }

            DB::table('attendance_risk_snapshots')->upsert($snapshotRows, ['student_profile_id', 'academic_year_id', 'snapshot_date'], [
                'course_section_id', 'school_days_elapsed', 'days_present', 'days_absent', 'justified_absences', 'unjustified_absences',
                'late_arrivals', 'early_departures', 'attendance_percentage', 'attendance_last_30_days', 'attendance_last_15_days',
                'risk_level', 'risk_score', 'trend', 'trend_points', 'consecutive_absences', 'risk_reasons', 'metrics', 'updated_at',
            ]);
            foreach (array_chunk($patternRows, 500) as $chunk) {
                DB::table('attendance_pattern_detections')->upsert($chunk, ['student_profile_id', 'academic_year_id', 'pattern_type'], [
                    'course_section_id', 'severity', 'confidence_score', 'confidence_label', 'occurrence_count', 'description', 'metrics', 'period',
                    'last_detected_at', 'is_active', 'resolved_at', 'updated_at',
                ]);
            }
            $resolvedIds = $existingPatterns->reject(fn ($row, string $key) => isset($detectedPatternKeys[$key]))->pluck('id');
            if ($resolvedIds->isNotEmpty()) {
                AttendancePatternDetection::query()->whereIn('id', $resolvedIds)->update(['is_active' => false, 'resolved_at' => $now, 'updated_at' => $now]);
            }
            $newAlerts = $this->createAlerts($year->id, $asOf, $analysisByStudent, $settings);

            $stats['students'] += $studentIds->count();
            $stats['snapshots'] += count($snapshotRows);
            $stats['patterns'] += count($patternRows);
            $stats['resolved_patterns'] += $resolvedIds->count();
            $stats['alerts'] += $newAlerts;
        });

        $this->cache->invalidate();

        return [...$stats, 'academic_year_id' => $year->id, 'snapshot_date' => $asOf];
    }

    private function createAlerts(int $yearId, string $asOf, array $analysisByStudent, array $settings): int
    {
        if ($analysisByStudent === []) {
            return 0;
        }
        $studentIds = array_map('intval', array_keys($analysisByStudent));
        $cooldown = (int) ($settings['alert_settings']['cooldown_days'] ?? 7);
        $recentAlerts = DB::table('attendance_alerts')->where('academic_year_id', $yearId)->whereIn('student_profile_id', $studentIds)
            ->whereDate('detected_on', '>=', CarbonImmutable::parse($asOf)->subDays($cooldown)->toDateString())
            ->get(['student_profile_id', 'type'])->mapWithKeys(fn ($row) => [$row->student_profile_id.'|'.$row->type => true]);
        $names = StudentProfile::query()->whereIn('id', $studentIds)->get(['id', 'first_name', 'last_name', 'registered_name'])->keyBy('id');
        $rows = [];
        $now = now();
        foreach ($analysisByStudent as $studentId => $payload) {
            $risk = $payload['analysis']['risk'];
            $summary = $payload['analysis']['summary'];
            $previous = $payload['previous'];
            $types = [];
            if (in_array($risk['level'], ['red', 'critical'], true)) {
                $types[] = $risk['level'] === 'critical' ? 'risk_critical' : 'risk_high';
            }
            if ($previous && $previous->risk_level !== $risk['level']) {
                $types[] = $risk['level'] === 'green' ? 'attendance_recovery' : 'risk_transition';
            }
            foreach ($types as $type) {
                $key = $studentId.'|'.$type;
                if ($recentAlerts->has($key)) {
                    continue;
                }
                $name = $names->get($studentId)?->registered_name_resolved ?? 'Estudiante';
                $rows[] = [
                    'academic_year_id' => $yearId, 'course_section_id' => $payload['course_id'], 'student_profile_id' => $studentId,
                    'type' => $type, 'severity' => $risk['level'] === 'critical' ? 'critical' : ($risk['level'] === 'green' ? 'info' : 'warning'),
                    'status' => 'open', 'detected_on' => $asOf, 'metric_value' => $risk['score'], 'threshold_value' => null,
                    'title' => $type === 'attendance_recovery' ? "Recuperación de asistencia: {$name}" : "Cambio de riesgo de asistencia: {$name}",
                    'description' => implode(' ', array_slice($risk['reasons'], 0, 3)),
                    'context' => json_encode(['risk_level' => $risk['level'], 'risk_score' => $risk['score'], 'attendance_rate' => $summary['attendance_percentage'], 'reasons' => $risk['reasons']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $recentAlerts->put($key, true);
            }
        }
        if ($rows === []) {
            return 0;
        }
        DB::table('attendance_alerts')->insert($rows);
        $this->notifyAuthorizedUsers($rows);

        return count($rows);
    }

    private function notifyAuthorizedUsers(array $alertRows): void
    {
        $recipientIds = $this->notificationRecipients ??= User::query()
            ->where('active', true)
            ->where(function ($query) {
                $query->whereHas('roles', fn ($roles) => $roles->where('slug', 'super_admin'))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->whereIn('slug', [
                        AttendanceManagementAccessService::VIEW_ALL, 'attendance_statistics.view_global',
                    ])->where('permissions.active', true));
            })->limit(30)->pluck('id');
        if ($recipientIds->isEmpty()) {
            return;
        }
        $now = now();
        $notifications = [];
        foreach ($alertRows as $alert) {
            foreach ($recipientIds as $userId) {
                $notifications[] = [
                    'user_id' => $userId, 'title' => $alert['title'], 'message' => $alert['description'],
                    'priority' => $alert['severity'] === 'critical' ? 'alta' : 'media',
                    'action_url' => '/students/attendance-management?section=students&student='.$alert['student_profile_id'],
                    'read_at' => null, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($notifications, 500) as $chunk) {
            SecurityNotification::query()->insert($chunk);
        }
    }
}
