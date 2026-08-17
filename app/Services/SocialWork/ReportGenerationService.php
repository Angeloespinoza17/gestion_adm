<?php

namespace App\Services\SocialWork;

use App\Models\SocialWork\Report;
use App\Models\SocialWork\SocialCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportGenerationService
{
    public function __construct(private readonly AuditService $audit) {}

    public function createDraft(SocialCase $case, User $user, array $sections = []): Report
    {
        $case->load(['student:id,first_name,last_name,registered_name,rut', 'responsible:id,name', 'statusHistory', 'interventions', 'protocols.protocol:id,name,code', 'alerts', 'referrals', 'reopenings']);
        $allowed = $user->hasPermission('social_work.highly_confidential.view');
        $snapshot = $case->toArray();
        if (! $allowed) {
            foreach ($snapshot['interventions'] ?? [] as &$intervention) unset($intervention['highly_confidential_notes']);
        }
        $content = $this->renderText($case, $snapshot);

        return DB::transaction(function () use ($case, $user, $sections, $snapshot, $content) {
            $report = Report::create(['case_id' => $case->id, 'student_profile_id' => $case->primary_student_id, 'type' => 'informe_maestro', 'title' => 'Ficha maestra '.$case->code, 'status' => 'borrador', 'confidentiality' => $case->confidentiality, 'current_version' => 1, 'created_by' => $user->id]);
            $report->versions()->create(['version' => 1, 'content' => $content, 'source_sections' => $sections ?: ['resumen', 'estados', 'intervenciones', 'protocolos', 'alertas', 'derivaciones', 'cierres'], 'source_snapshot' => $snapshot, 'status' => 'borrador', 'change_reason' => 'Borrador generado desde información estructurada', 'created_by' => $user->id]);
            $this->audit->record('report.draft_generated', $report, $user, [], ['case_id' => $case->id, 'version' => 1]);
            return $report->load('versions');
        });
    }

    public function addVersion(Report $report, string $content, string $reason, User $user): Report
    {
        return DB::transaction(function () use ($report, $content, $reason, $user) {
            $next = $report->versions()->lockForUpdate()->max('version') + 1;
            $report->versions()->create(['version' => $next, 'content' => $content, 'source_sections' => [], 'source_snapshot' => [], 'status' => 'borrador', 'change_reason' => $reason, 'created_by' => $user->id]);
            $report->update(['current_version' => $next, 'status' => 'borrador']);
            return $report->load('versions');
        });
    }

    private function renderText(SocialCase $case, array $snapshot): string
    {
        $student = $case->student?->registered_name_resolved ?: $case->student?->full_name;
        return "BORRADOR – REQUIERE REVISIÓN HUMANA\n\nCaso: {$case->code}\nEstudiante: {$student}\nEstado: {$case->status}\nRiesgo: {$case->risk_level}\nMotivo de apertura: {$case->reason}\n\nDescripción inicial:\n{$case->initial_description}\n\nConclusión de cierre:\n".($case->closure_conclusion ?: 'Caso no cerrado o sin conclusión registrada.')."\n\nFuentes estructuradas: ".count($snapshot).' secciones del caso.';
    }
}
