<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\User;
use App\Services\Convivencia\ConvivenciaPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PreloadConvivenciaAnnualPlan2026 extends Command
{
    protected $signature = 'convivencia:preload-plan-2026
                            {--source= : Ruta al documento Word original para almacenarlo de forma privada}';

    protected $description = 'Precarga de forma segura el Plan de Gestión de la Convivencia Escolar 2026.';

    public function handle(ConvivenciaPlanService $service): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('La precarga solo está permitida en entornos local o testing.');

            return self::FAILURE;
        }

        if (app()->environment('local') && config('database.default') !== 'sqlite'
            && (string) config('database.connections.'.config('database.default').'.database') !== 'gestion_adm') {
            $this->error('La base local autorizada para esta precarga es gestion_adm.');

            return self::FAILURE;
        }

        $definition = config('convivencia_plan_2026');
        $sourcePath = trim((string) $this->option('source'));
        if ($sourcePath !== '' && (! is_file($sourcePath) || ! is_readable($sourcePath))) {
            $this->error('El documento fuente indicado no existe o no puede leerse.');

            return self::FAILURE;
        }
        if ($sourcePath !== '' && ! hash_equals((string) $definition['source_document_sha256'], (string) hash_file('sha256', $sourcePath))) {
            $this->error('El documento fuente no coincide con la huella aprobada para esta precarga.');

            return self::FAILURE;
        }

        $existing = ConvivenciaPlan::withTrashed()->where('calendar_year', 2026)->first();
        if ($existing?->trashed()) {
            $this->error('Existe un plan 2026 archivado. Revísalo manualmente; no se restauró ni sobrescribió.');

            return self::FAILURE;
        }
        if ($existing) {
            if (hash_equals((string) $existing->source_document_sha256, (string) $definition['source_document_sha256'])) {
                if ($sourcePath === '') {
                    $this->info('El Plan de Convivencia 2026 ya está precargado; no se realizaron cambios.');

                    return self::SUCCESS;
                }

                $actor = $this->resolveActor();
                if (! $actor) {
                    $this->error('No existe un usuario activo para registrar la autoría del documento fuente.');

                    return self::FAILURE;
                }
                $created = $this->attachSource($existing, $sourcePath, $actor);
                $this->info($created
                    ? 'Documento fuente almacenado de forma privada en el plan 2026.'
                    : 'El plan y su documento fuente ya estaban precargados; no se realizaron cambios.');

                return self::SUCCESS;
            }

            $this->error('Ya existe un plan 2026 distinto. La precarga se detuvo sin sobrescribirlo.');

            return self::FAILURE;
        }

        $year = AcademicYear::query()->where('year', 2026)->first();
        if (! $year) {
            $this->error('No existe el año académico 2026.');

            return self::FAILURE;
        }
        $actor = $this->resolveActor();
        if (! $actor) {
            $this->error('No existe un usuario activo para registrar la autoría de la precarga.');

            return self::FAILURE;
        }

        $actions = collect($definition['actions'])->map(fn (array $action, int $index) => [
            'action_type' => $action['type'],
            'title' => $action['title'],
            'target_audience' => $action['audience'],
            'planned_month' => $action['month'],
            'date_precision' => 'month',
            'sort_order' => $index + 1,
            'weight_percent' => $action['weight'] ?? 0,
            'responsible_label' => 'Por definir',
            'status' => 'planificada',
            'advance_percentage' => 0,
            'verification_means' => 'Registro de ejecución, participantes y evidencias asociadas.',
        ])->all();

        $plan = $service->store([
            ...collect($definition)->except('actions')->all(),
            'academic_year_id' => $year->id,
            'responsible_user_id' => $actor->id,
            'responsible_staff_id' => $actor->staff_id,
            'actions' => $actions,
            'change_summary' => 'Precarga inicial desde el documento institucional 2026.',
        ], $actor);

        if ($sourcePath !== '') {
            $this->attachSource($plan, $sourcePath, $actor);
        }

        $sourceMessage = $sourcePath !== '' ? ' El documento Word quedó resguardado en almacenamiento privado.' : '';
        $this->info("Plan {$plan->calendar_year} precargado con {$plan->actions->count()} acciones y versión {$plan->version_number}.{$sourceMessage}");

        return self::SUCCESS;
    }

    private function resolveActor(): ?User
    {
        return User::query()
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->orderBy('id')
            ->first() ?: User::query()->where('active', true)->orderBy('id')->first();
    }

    private function attachSource(ConvivenciaPlan $plan, string $sourcePath, User $actor): bool
    {
        $definition = config('convivencia_plan_2026');
        $hash = (string) $definition['source_document_sha256'];
        $notes = "Documento fuente SHA-256: {$hash}";
        $existing = $plan->attachments()
            ->where('original_name', $definition['source_document_name'])
            ->where('notes', $notes)
            ->first();
        $storagePath = "convivencia-private/ConvivenciaPlan/{$plan->id}/source-{$hash}.docx";

        if (! Storage::disk('local')->exists($storagePath)) {
            $stored = Storage::disk('local')->put($storagePath, file_get_contents($sourcePath));
            if (! $stored) {
                throw new \RuntimeException('No fue posible resguardar el documento fuente.');
            }
        }

        if ($existing) {
            return false;
        }

        $plan->attachments()->create([
            'category' => 'informe',
            'confidentiality_level' => 'interna',
            'is_sensitive' => false,
            'file_path' => $storagePath,
            'original_name' => Str::limit((string) $definition['source_document_name'], 191, ''),
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => filesize($sourcePath) ?: null,
            'notes' => $notes,
            'uploaded_by' => $actor->id,
        ]);

        return true;
    }
}
