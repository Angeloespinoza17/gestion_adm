<?php

namespace App\Jobs\Psychology;

use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyTask;
use App\Notifications\Psychology\PsychologySafeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyPsychologyDeadlines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        PsychologyTask::query()->with('responsibleUser')->whereIn('status', ['pending', 'in_progress'])->whereBetween('due_at', [now(), now()->addDay()])->get()->each(fn ($task) => $task->responsibleUser?->notify(new PsychologySafeNotification('Tarea próxima a vencer', 'Tienes una tarea de Psicología próxima a vencer.', '/psychology/tasks')));
        PsychologyTask::query()->with('responsibleUser')->whereIn('status', ['pending', 'in_progress'])->where('due_at', '<', now())->get()->each(function ($task) {
            $task->update(['status' => 'overdue']);
            $task->responsibleUser?->notify(new PsychologySafeNotification('Tarea vencida', 'Tienes una tarea de Psicología vencida.', '/psychology/tasks'));
        });
        PsychologyCase::query()->with('responsibleUser')->where('status', '!=', 'closed')->where('last_activity_at', '<', now()->subDays((int) config('psychology.inactive_days', 14)))->get()->each(fn ($case) => $case->responsibleUser?->notify(new PsychologySafeNotification('Caso sin actividad', 'Tienes un caso de Psicología que requiere revisión de seguimiento.', '/psychology/cases')));
    }
}
