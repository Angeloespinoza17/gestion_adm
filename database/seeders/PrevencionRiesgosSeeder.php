<?php

namespace Database\Seeders;

use Database\Seeders\Support\PreventsProductionSeeding;

use App\Models\RiskPrevention\RiskPreventionAccident;
use App\Models\RiskPrevention\RiskPreventionDocument;
use App\Models\RiskPrevention\RiskPreventionEppDelivery;
use App\Models\RiskPrevention\RiskPreventionEppItem;
use App\Models\RiskPrevention\RiskPreventionEmergencyDrill;
use App\Models\RiskPrevention\RiskPreventionEmergencyPlan;
use App\Models\RiskPrevention\RiskPreventionFireExtinguisher;
use App\Models\RiskPrevention\RiskPreventionTraining;
use App\Models\RiskPrevention\RiskPreventionTrainingParticipant;
use App\Models\User;
use App\Services\RiskPrevention\RiskPreventionAccessService;
use Database\Seeders\Modules\PrevencionRiesgosModuleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PrevencionRiesgosSeeder extends Seeder
{
    use PreventsProductionSeeding;

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->call(PrevencionRiesgosModuleSeeder::class);
        $accessService = app(RiskPreventionAccessService::class);

        if (!$accessService->isInstalled()) {
            return;
        }

        $actor = User::query()
            ->where('email', 'superadmin@cnscgestion.cl')
            ->first()
            ?: User::query()->firstOrFail();

        $this->seedExtinguishers($actor);
        $this->seedAccidents($actor);
        $this->seedEmergencies($actor);
        $this->seedEpp($actor);
        $this->seedTrainings($actor);
        $this->seedDocuments($actor);

        $accessService->refreshDynamicStatuses();
    }

    private function seedExtinguishers(User $actor): void
    {
        $rows = [
            [
                'code' => 'EXT-PR-001',
                'extinguisher_type' => 'PQS',
                'building' => 'Edificio Central',
                'floor' => '1',
                'dependency_name' => 'Recepción',
                'installed_at' => now()->subYears(1)->toDateString(),
                'expires_at' => now()->addDays(28)->toDateString(),
                'status' => 'vigente',
                'notes' => 'Ubicado junto a acceso principal.',
            ],
            [
                'code' => 'EXT-PR-002',
                'extinguisher_type' => 'CO2',
                'building' => 'Edificio Central',
                'floor' => '2',
                'dependency_name' => 'Laboratorio de computación',
                'installed_at' => now()->subYears(2)->toDateString(),
                'expires_at' => now()->addDays(12)->toDateString(),
                'status' => 'vigente',
                'notes' => 'Revisión prioritaria por cercanía de vencimiento.',
            ],
            [
                'code' => 'EXT-PR-003',
                'extinguisher_type' => 'Agua presurizada',
                'building' => 'Pabellón Sur',
                'floor' => '1',
                'dependency_name' => 'Sala de ciencias',
                'installed_at' => now()->subYears(3)->toDateString(),
                'expires_at' => now()->subDays(3)->toDateString(),
                'status' => 'vigente',
                'notes' => 'Debe gestionarse recarga inmediata.',
            ],
            [
                'code' => 'EXT-PR-004',
                'extinguisher_type' => 'Espuma AFFF',
                'building' => 'Casino',
                'floor' => 'PB',
                'dependency_name' => 'Cocina',
                'installed_at' => now()->subYears(4)->toDateString(),
                'expires_at' => now()->addDays(120)->toDateString(),
                'status' => 'dado_baja',
                'notes' => 'Fuera de servicio por reposición programada.',
            ],
        ];

        foreach ($rows as $row) {
            RiskPreventionFireExtinguisher::query()->updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]),
            );
        }
    }

    private function seedAccidents(User $actor): void
    {
        $accidentOne = RiskPreventionAccident::query()->updateOrCreate(
            ['involved_person_name' => 'Matías Rivera', 'occurred_at' => now()->subDays(9)->setHour(10)->setMinute(15)->toDateTimeString()],
            [
                'accident_type' => 'student',
                'involved_person_identifier' => '7A',
                'location' => 'Patio central',
                'description' => 'Caída durante recreo por superficie húmeda.',
                'injuries' => 'Golpe leve en rodilla derecha.',
                'measures_taken' => 'Primeros auxilios y aviso a inspectoría.',
                'referrals' => 'Observación en enfermería escolar.',
                'case_status' => 'en_seguimiento',
                'responsible_name' => 'Inspectoría general',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $accidentOne->followUps()->updateOrCreate(
            ['followed_at' => now()->subDays(8)->setHour(11)->toDateTimeString()],
            [
                'status' => 'en_seguimiento',
                'notes' => 'Se confirma evolución favorable y retorno a clases.',
                'next_actions' => 'Revisar señalética de piso mojado.',
                'created_by' => $actor->id,
            ],
        );

        $accidentTwo = RiskPreventionAccident::query()->updateOrCreate(
            ['involved_person_name' => 'Claudia Fuentes', 'occurred_at' => now()->subDays(4)->setHour(8)->setMinute(25)->toDateTimeString()],
            [
                'accident_type' => 'staff',
                'involved_person_identifier' => 'Docente',
                'location' => 'Escalera norte',
                'description' => 'Torcedura al bajar escalera con material pedagógico.',
                'injuries' => 'Dolor en tobillo izquierdo.',
                'measures_taken' => 'Se inmoviliza y se deriva a mutual.',
                'referrals' => 'Atención ACHS.',
                'case_status' => 'abierto',
                'responsible_name' => 'Prevención de Riesgos',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $accidentTwo->followUps()->updateOrCreate(
            ['followed_at' => now()->subDays(3)->setHour(9)->setMinute(30)->toDateTimeString()],
            [
                'status' => 'abierto',
                'notes' => 'Se solicita evaluación de baranda y condición de escalones.',
                'next_actions' => 'Levantamiento con mantención.',
                'created_by' => $actor->id,
            ],
        );

        RiskPreventionAccident::query()->updateOrCreate(
            ['involved_person_name' => 'María Paz Soto', 'occurred_at' => now()->subDays(16)->setHour(14)->setMinute(10)->toDateTimeString()],
            [
                'accident_type' => 'visit',
                'involved_person_identifier' => 'Apoderada',
                'location' => 'Hall de acceso',
                'description' => 'Resbalón menor al ingreso.',
                'injuries' => 'Sin lesiones aparentes.',
                'measures_taken' => 'Se acompaña y se registra incidente.',
                'referrals' => 'No aplica.',
                'case_status' => 'cerrado',
                'responsible_name' => 'Portería',
                'closed_at' => now()->subDays(15),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }

    private function seedEmergencies(User $actor): void
    {
        $planPath = $this->storeDemoFile(
            'risk-prevention/demo/plan-evacuacion-general.txt',
            "Plan de evacuacion general del establecimiento\nPunto de encuentro: patio cubierto.\n",
        );

        $plan = RiskPreventionEmergencyPlan::query()->updateOrCreate(
            ['title' => 'Plan general de evacuación'],
            [
                'record_type' => 'plan_evacuacion',
                'emergency_type' => 'Sismo',
                'last_updated_at' => now()->subDays(20)->toDateString(),
                'responsible_name' => 'Comité Paritario',
                'document_path' => $planPath,
                'document_name' => 'plan-evacuacion-general.txt',
                'notes' => 'Incluye rutas por pabellón y zonas seguras.',
                'active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $protocolPath = $this->storeDemoFile(
            'risk-prevention/demo/protocolo-incendio.txt',
            "Protocolo de incendio\nAcciones inmediatas, aislamiento y comunicacion interna.\n",
        );

        $protocol = RiskPreventionEmergencyPlan::query()->updateOrCreate(
            ['title' => 'Protocolo de incendio y humo'],
            [
                'record_type' => 'protocolo',
                'emergency_type' => 'Incendio',
                'last_updated_at' => now()->subDays(12)->toDateString(),
                'responsible_name' => 'Encargado de Seguridad',
                'document_path' => $protocolPath,
                'document_name' => 'protocolo-incendio.txt',
                'notes' => 'Incluye roles por brigada y comunicación a bomberos.',
                'active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $drillPath = $this->storeDemoFile(
            'risk-prevention/demo/simulacro-sismo-abril.txt',
            "Simulacro abril\nEvacuacion en 3 minutos 42 segundos.\nHallazgos: mejorar flujo de salida del segundo piso.\n",
        );

        RiskPreventionEmergencyDrill::query()->updateOrCreate(
            ['emergency_plan_id' => $plan->id, 'title' => 'Simulacro general de sismo abril'],
            [
                'emergency_type' => 'Sismo',
                'drill_date' => now()->subDays(58)->toDateString(),
                'responsible_name' => 'Comité Paritario',
                'participants_count' => 540,
                'findings' => 'Demora en pabellón B por congestión en escalera.',
                'improvements' => 'Reubicar curso y reforzar señalización de salida.',
                'document_path' => $drillPath,
                'document_name' => 'simulacro-sismo-abril.txt',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        RiskPreventionEmergencyDrill::query()->updateOrCreate(
            ['emergency_plan_id' => $protocol->id, 'title' => 'Simulacro conato de incendio casino'],
            [
                'emergency_type' => 'Incendio',
                'drill_date' => now()->subDays(22)->toDateString(),
                'responsible_name' => 'Encargado de Seguridad',
                'participants_count' => 46,
                'findings' => 'Tiempo de aviso correcto, pero faltó control de acceso externo.',
                'improvements' => 'Coordinar cierre de portón lateral con portería.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );
    }

    private function seedEpp(User $actor): void
    {
        $helmet = RiskPreventionEppItem::query()->updateOrCreate(
            ['name' => 'Casco dieléctrico blanco'],
            [
                'epp_type' => 'Casco',
                'stock' => 14,
                'minimum_stock' => 8,
                'unit' => 'unidad',
                'description' => 'Uso para labores eléctricas y mantención general.',
                'active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $gloves = RiskPreventionEppItem::query()->updateOrCreate(
            ['name' => 'Guantes anticorte talla L'],
            [
                'epp_type' => 'Guantes',
                'stock' => 6,
                'minimum_stock' => 10,
                'unit' => 'par',
                'description' => 'Protección para maniobras de taller y mantención.',
                'active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $vest = RiskPreventionEppItem::query()->updateOrCreate(
            ['name' => 'Chaleco reflectante naranja'],
            [
                'epp_type' => 'Chaleco reflectante',
                'stock' => 18,
                'minimum_stock' => 6,
                'unit' => 'unidad',
                'description' => 'Uso para patio, acceso y simulacros.',
                'active' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $deliveries = [
            [$helmet->id, 'José Campos', 1, now()->subDays(120)->toDateString(), now()->addDays(20)->toDateString(), 'vigente', 'Uso en trabajos de mantención.'],
            [$gloves->id, 'Ricardo Fuentes', 2, now()->subDays(200)->toDateString(), now()->addDays(5)->toDateString(), 'vigente', 'Reposición próxima por desgaste.'],
            [$vest->id, 'Patricia López', 1, now()->subDays(35)->toDateString(), now()->addDays(80)->toDateString(), 'vigente', 'Asignado para coordinación de simulacros.'],
            [$gloves->id, 'Sergio Torres', 1, now()->subDays(300)->toDateString(), now()->subDays(10)->toDateString(), 'vigente', 'Debe reemplazarse con prioridad.'],
        ];

        foreach ($deliveries as [$itemId, $employee, $quantity, $deliveredAt, $replacementDueAt, $status, $observations]) {
            RiskPreventionEppDelivery::query()->updateOrCreate(
                [
                    'epp_item_id' => $itemId,
                    'employee_name' => $employee,
                    'delivered_at' => $deliveredAt,
                ],
                [
                    'quantity' => $quantity,
                    'replacement_due_at' => $replacementDueAt,
                    'status' => $status,
                    'observations' => $observations,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ],
            );
        }
    }

    private function seedTrainings(User $actor): void
    {
        $evidencePath = $this->storeDemoFile(
            'risk-prevention/demo/capacitacion-extintores.txt',
            "Capacitacion uso de extintores\nParticipantes, asistencia y recomendaciones.\n",
        );

        $trainingOne = RiskPreventionTraining::query()->updateOrCreate(
            ['name' => 'Uso y manejo de extintores'],
            [
                'training_type' => 'obligatoria',
                'training_date' => now()->subDays(18)->toDateString(),
                'modality' => 'Presencial',
                'evidence_path' => $evidencePath,
                'evidence_name' => 'capacitacion-extintores.txt',
                'observations' => 'Actividad práctica con brigada interna.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $trainingOne->participants()->delete();
        $trainingOne->participants()->createMany([
            ['employee_name' => 'Patricia López', 'compliance_status' => 'cumplido', 'notes' => 'Asistencia completa.'],
            ['employee_name' => 'Ricardo Fuentes', 'compliance_status' => 'cumplido', 'notes' => null],
            ['employee_name' => 'José Campos', 'compliance_status' => 'pendiente', 'notes' => 'Debe repetir inducción práctica.'],
        ]);

        $trainingTwo = RiskPreventionTraining::query()->updateOrCreate(
            ['name' => 'Inducción de trabajo seguro en altura'],
            [
                'training_type' => 'induccion',
                'training_date' => now()->addDays(6)->toDateString(),
                'modality' => 'Mixta',
                'observations' => 'Programada para equipo de mantención y apoyo.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ],
        );

        $trainingTwo->participants()->delete();
        $trainingTwo->participants()->createMany([
            ['employee_name' => 'Sergio Torres', 'compliance_status' => 'pendiente', 'notes' => 'Convocado.'],
            ['employee_name' => 'Claudia Fuentes', 'compliance_status' => 'pendiente', 'notes' => 'Convocada.'],
            ['employee_name' => 'Patricia López', 'compliance_status' => 'cumplido', 'notes' => 'Ya cuenta con certificación vigente.'],
        ]);
    }

    private function seedDocuments(User $actor): void
    {
        $protocolPath = $this->storeDemoFile(
            'risk-prevention/demo/documento-protocolo-caidas.txt',
            "Protocolo de caidas y golpes\nVersion vigente para recreos y traslado.\n",
        );

        $rows = [
            [
                'document_type' => 'protocolo',
                'title' => 'Protocolo de caídas y golpes',
                'document_group' => 'PR-CAIDAS',
                'version_number' => 'v2.1',
                'valid_from' => now()->subMonths(6)->toDateString(),
                'valid_until' => now()->addDays(21)->toDateString(),
                'status' => 'vigente',
                'responsible_name' => 'Prevención de Riesgos',
                'document_path' => $protocolPath,
                'document_name' => 'documento-protocolo-caidas.txt',
                'notes' => 'Aplica a recreos, talleres y actos masivos.',
            ],
            [
                'document_type' => 'reglamento',
                'title' => 'Reglamento interno de seguridad escolar',
                'document_group' => 'PR-RISE',
                'version_number' => 'v1.0',
                'valid_from' => now()->subYear()->toDateString(),
                'valid_until' => now()->addDays(180)->toDateString(),
                'status' => 'vigente',
                'responsible_name' => 'Dirección',
                'document_path' => $this->storeDemoFile('risk-prevention/demo/reglamento-seguridad.txt', "Reglamento de seguridad escolar.\n"),
                'document_name' => 'reglamento-seguridad.txt',
                'notes' => 'Documento base para inducciones.',
            ],
            [
                'document_type' => 'instructivo',
                'title' => 'Instructivo de uso de EPP en taller',
                'document_group' => 'PR-EPP-TALLER',
                'version_number' => 'v3.0',
                'valid_from' => now()->subMonths(4)->toDateString(),
                'valid_until' => now()->subDays(2)->toDateString(),
                'status' => 'vigente',
                'responsible_name' => 'Taller de Tecnología',
                'document_path' => $this->storeDemoFile('risk-prevention/demo/instructivo-epp-taller.txt', "Uso obligatorio de EPP para herramientas manuales y electricas.\n"),
                'document_name' => 'instructivo-epp-taller.txt',
                'notes' => 'Debe actualizarse por nueva matriz de riesgos.',
            ],
            [
                'document_type' => 'informe',
                'title' => 'Informe de autoevaluación preventiva',
                'document_group' => 'PR-INF-AUTO',
                'version_number' => '2026-1',
                'valid_from' => now()->subDays(30)->toDateString(),
                'valid_until' => now()->addDays(45)->toDateString(),
                'status' => 'vigente',
                'responsible_name' => 'Comité Paritario',
                'document_path' => $this->storeDemoFile('risk-prevention/demo/informe-autoevaluacion.txt', "Hallazgos principales y plan de mejoras.\n"),
                'document_name' => 'informe-autoevaluacion.txt',
                'notes' => 'Usado como respaldo en auditoría interna.',
            ],
        ];

        foreach ($rows as $row) {
            RiskPreventionDocument::query()->updateOrCreate(
                ['title' => $row['title'], 'version_number' => $row['version_number']],
                array_merge($row, [
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]),
            );
        }
    }

    private function storeDemoFile(string $path, string $content): string
    {
        Storage::disk('local')->put($path, $content);

        return $path;
    }
}
