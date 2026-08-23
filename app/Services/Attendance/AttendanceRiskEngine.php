<?php

namespace App\Services\Attendance;

class AttendanceRiskEngine
{
    /** @param array<string,mixed> $metrics */
    public function calculate(array $metrics, array $settings): array
    {
        $rate = $metrics['attendance_percentage'] ?? null;
        if ($rate === null) {
            return [
                'level' => 'no_data', 'label' => 'Sin datos suficientes', 'score' => 0,
                'score_band' => 'low', 'reasons' => ['No existen registros lectivos válidos en el periodo analizado.'],
                'explanations' => ['No existen registros lectivos válidos en el periodo analizado.'],
                'critical_triggers' => [],
            ];
        }

        $thresholds = $settings['risk_thresholds'];
        $weights = $settings['risk_weights'];
        $score = 0.0;
        $score += $this->rateContribution((float) $rate, (float) ($thresholds['green'] ?? 95), (float) ($weights['accumulated_attendance'] ?? 35));
        $score += $this->rateContribution((float) ($metrics['attendance_last_30_days'] ?? $rate), (float) ($thresholds['green'] ?? 95), (float) ($weights['last_30_days'] ?? 20));
        $score += $this->rateContribution((float) ($metrics['attendance_last_15_days'] ?? $rate), (float) ($thresholds['green'] ?? 95), (float) ($weights['last_15_days'] ?? 15));
        $score += min(1, ((int) ($metrics['consecutive_absences'] ?? 0)) / max(1, (int) ($thresholds['critical_absence_streak'] ?? 5))) * (float) ($weights['absence_streak'] ?? 15);
        $score += min(1, ((float) ($metrics['unjustified_absence_rate'] ?? 0)) / 100) * (float) ($weights['unjustified_absences'] ?? 8);
        $score += min(1, ((int) ($metrics['late_arrivals'] ?? 0)) / 10) * (float) ($weights['late_arrivals'] ?? 4);
        $score += ! empty($metrics['has_previous_case']) ? (float) ($weights['previous_case'] ?? 3) : 0;
        $score = (int) round(max(0, min(100, $score)));

        $criticalTriggers = [];
        if ((int) ($metrics['consecutive_absences'] ?? 0) >= (int) ($thresholds['critical_absence_streak'] ?? 5)) {
            $criticalTriggers[] = 'Racha crítica de ausencias consecutivas.';
        }
        if ((float) ($metrics['trend_drop_points'] ?? 0) >= (float) ($thresholds['critical_drop_points'] ?? 8)) {
            $criticalTriggers[] = 'Caída abrupta de asistencia en el periodo reciente.';
        }
        if ((int) ($metrics['recent_absences'] ?? 0) >= (int) ($thresholds['critical_recent_absences'] ?? 5)) {
            $criticalTriggers[] = 'Concentración crítica de ausencias en los días lectivos recientes.';
        }

        $level = (float) $rate >= (float) ($thresholds['green'] ?? 95) ? 'green'
            : ((float) $rate >= (float) ($thresholds['yellow'] ?? 90) ? 'yellow'
                : ((float) $rate >= (float) ($thresholds['orange'] ?? 85) ? 'orange' : 'red'));
        if ($criticalTriggers !== [] || $score >= 81) {
            $level = 'critical';
        }
        $labels = [
            'green' => 'Asistencia adecuada', 'yellow' => 'Atención preventiva',
            'orange' => 'Riesgo de asistencia', 'red' => 'Requiere apoyo prioritario',
            'critical' => 'Atención crítica',
        ];
        $reasons = [sprintf('Asistencia acumulada %.2f%%.', $rate)];
        if (($metrics['attendance_last_30_days'] ?? null) !== null) {
            $reasons[] = sprintf('Asistencia en últimos 30 días lectivos %.2f%%.', $metrics['attendance_last_30_days']);
        }
        if ((int) ($metrics['consecutive_absences'] ?? 0) > 0) {
            $reasons[] = (int) $metrics['consecutive_absences'].' días lectivos consecutivos ausente.';
        }
        if ((float) ($metrics['unjustified_absence_rate'] ?? 0) > 0) {
            $reasons[] = sprintf('%.1f%% de las ausencias sin justificación.', $metrics['unjustified_absence_rate']);
        }
        $reasons = array_values(array_unique([...$criticalTriggers, ...$reasons]));

        return [
            'level' => $level,
            'label' => $labels[$level],
            'score' => $score,
            'score_band' => $score <= 20 ? 'low' : ($score <= 40 ? 'preventive' : ($score <= 60 ? 'medium' : ($score <= 80 ? 'high' : 'critical'))),
            'reasons' => $reasons,
            'explanations' => $reasons,
            'critical_triggers' => $criticalTriggers,
        ];
    }

    private function rateContribution(float $rate, float $target, float $weight): float
    {
        return $rate >= $target ? 0 : min(1, ($target - $rate) / max(1, $target - 60)) * $weight;
    }
}
