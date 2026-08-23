<?php

namespace App\Jobs\Psychology;

use App\Models\Psychology\PsychologyExport;
use App\Models\Psychology\PsychologyReferral;
use App\Notifications\Psychology\PsychologySafeNotification;
use App\Services\Psychology\PsychologyAccessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePsychologyExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly int $exportId) {}

    public function handle(PsychologyAccessService $access): void
    {
        $export = PsychologyExport::query()->with('author.roles.permissions')->findOrFail($this->exportId);
        $export->update(['status' => 'processing']);
        $path = 'psychology/exports/'.$export->created_by.'/'.uniqid('report-', true).'.csv';
        try {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Código', 'Estudiante', 'Estado', 'Prioridad', 'Fecha'], ';');
            $filters = $export->filters ?: [];
            $query = PsychologyReferral::query()->with('student:id,first_name,last_name,registered_name')->filter($filters)
                ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
                ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'));
            $access->applyReferralReportScope($query, $export->author);
            $query->when(! $access->isScopedPsychologist($export->author) && ($filters['professional_id'] ?? null), function ($filtered) use ($filters) {
                $filtered->where(function ($professional) use ($filters) {
                    $professional->where('assigned_user_id', $filters['professional_id'])
                        ->orWhereHas('assignments', fn ($assignment) => $assignment->where('user_id', $filters['professional_id'])->whereNull('ended_at'));
                });
            });
            $query->chunkById(500, function ($rows) use ($stream) {
                foreach ($rows as $row) {
                    fputcsv($stream, [$row->code, $row->student?->registered_name_resolved, $row->status, $row->professional_priority ?: $row->suggested_urgency, $row->created_at?->format('d-m-Y H:i')], ';');
                }
            });
            rewind($stream);
            Storage::disk(config('psychology.disk', 'local'))->put($path, $stream);
            fclose($stream);
            $export->update(['status' => 'completed', 'private_path' => $path, 'completed_at' => now()]);
            $export->author->notify(new PsychologySafeNotification('Exportación disponible', 'Tu exportación autorizada de Psicología está disponible.', '/psychology/reports'));
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'error_message' => 'No fue posible generar el archivo.']);
            throw $exception;
        }
    }
}
