<?php

namespace App\Console\Commands;

use App\Models\RiskPrevention\PreventiveProgram;
use App\Models\RiskPrevention\PreventiveProgramAction;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Notifications\RiskPrevention\RiskMatrixNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendRiskMatrixReminders extends Command
{
    protected $signature = 'risk-matrices:send-reminders';
    protected $description = 'Envía alertas idempotentes de medidas, programas y revisiones IPER.';

    public function handle(): int
    {
        $sent = 0;
        PreventiveProgramAction::query()
            ->with('responsible', 'program.version.matrix')
            ->whereHas('program', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))
            ->whereNotIn('status', ['verified', 'cancelled'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->addDays(15))
            ->chunkById(200, function ($actions) use (&$sent) {
                foreach ($actions as $action) {
                    if (! $action->responsible) continue;
                    $days = now()->startOfDay()->diffInDays($action->due_date->copy()->startOfDay(), false);
                    if (! in_array($days, [15, 7, 3, 0], true) && $days >= 0) continue;
                    $milestone = $days < 0 ? 'overdue-'.now()->format('Y-m') : 'd'.$days;
                    $message = $days < 0 ? 'La medida está vencida.' : ($days === 0 ? 'La medida vence hoy.' : "La medida vence en {$days} días.");
                    if ($this->notify($action->responsible, $action->program->version, "action:{$action->id}:{$milestone}", 'Medida preventiva por vencer', $message)) $sent++;
                    if ($days < 0) $action->update(['status' => 'overdue']);
                }
            });

        PreventiveProgram::query()->with('responsible', 'version.matrix')->where('company_key', config('risk_matrix.company.key'))->where('status', '!=', 'approved')->whereDate('due_to_be_prepared_at', '<=', now()->addDays(15))->chunkById(100, function ($programs) use (&$sent) {
            foreach ($programs as $program) {
                if (! $program->responsible) continue;
                $days = now()->startOfDay()->diffInDays($program->due_to_be_prepared_at->copy()->startOfDay(), false);
                if (! in_array($days, [15, 7, 3, 0], true) && $days >= 0) continue;
                $milestone = $days < 0 ? 'overdue-'.now()->format('Y-m') : 'd'.$days;
                if ($this->notify($program->responsible, $program->version, "program:{$program->id}:{$milestone}", 'Programa preventivo por vencer', $days < 0 ? 'El programa preventivo está vencido.' : "El programa vence en {$days} días.")) $sent++;
            }
        });

        RiskMatrixVersion::query()->with('programResponsible', 'matrix')->whereHas('matrix', fn ($query) => $query->where('company_key', config('risk_matrix.company.key')))->where('status', 'approved')->whereDate('next_review_at', '<=', now()->addDays(30))->chunkById(100, function ($versions) use (&$sent) {
            foreach ($versions as $version) {
                if (! $version->programResponsible) continue;
                $period = now()->format('Y-m');
                if ($this->notify($version->programResponsible, $version, "review:{$version->id}:{$period}", 'Revisión IPER próxima', 'La matriz debe revisarse el '.$version->next_review_at->format('d/m/Y').'.')) $sent++;
            }
        });

        $this->info("Alertas enviadas: {$sent}");

        return self::SUCCESS;
    }

    private function notify($user, RiskMatrixVersion $version, string $key, string $title, string $message): bool
    {
        return DB::transaction(function () use ($user, $version, $key, $title, $message) {
            $inserted = DB::table('prevent_risk_alert_logs')->insertOrIgnore([
                'alert_key' => $key.':'.$user->id,
                'alert_type' => str($key)->before(':')->toString(),
                'user_id' => $user->id,
                'alertable_type' => $version->getMorphClass(),
                'alertable_id' => $version->id,
                'sent_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $inserted) return false;
            $user->notify(new RiskMatrixNotification($title, $message, $version));

            return true;
        });
    }
}
