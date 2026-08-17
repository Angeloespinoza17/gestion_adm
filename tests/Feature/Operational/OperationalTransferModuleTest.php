<?php

namespace Tests\Feature\Operational;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffOrganigramRelation;
use App\Models\User;
use App\Services\Operational\OperationalTransferGenericRequesterService;
use App\Services\Operational\OperationalTransferSpreadsheetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class OperationalTransferModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $visor;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-12 09:00:00');
        Notification::fake();
        Storage::fake('local');

        $requesterStaff = Staff::query()->create(['full_name' => 'Docente Solicitante', 'rut' => '11111111-1', 'status' => 'activo', 'active' => true]);
        $visorStaff = Staff::query()->create(['full_name' => 'Subdirectora Visadora', 'rut' => '22222222-2', 'status' => 'activo', 'active' => true]);
        $administratorStaff = Staff::query()->create(['full_name' => 'Administrador Final', 'rut' => '33333333-3', 'status' => 'activo', 'active' => true]);

        $this->requester = User::factory()->create(['name' => 'Docente Solicitante', 'staff_id' => $requesterStaff->id, 'active' => true]);
        $this->visor = User::factory()->create(['name' => 'Subdirectora Visadora', 'staff_id' => $visorStaff->id, 'active' => true]);
        $this->administrator = User::factory()->create(['name' => 'Administrador Final', 'staff_id' => $administratorStaff->id, 'active' => true]);

        $this->attachRole($this->requester, 'docente', ['ver_traslados_operativos', 'solicitar_traslados_operativos']);
        $this->attachRole($this->visor, 'subdirector', ['ver_traslados_operativos', 'visar_traslados_operativos', 'exportar_traslados_operativos']);
        $this->attachRole($this->administrator, 'administrador', [
            'ver_traslados_operativos', 'gestionar_traslados_operativos', 'administrar_proveedores_traslados',
            'exportar_traslados_operativos', 'importar_traslados_operativos',
        ]);

        StaffOrganigramRelation::query()->create([
            'staff_id' => $requesterStaff->id,
            'related_staff_id' => $visorStaff->id,
            'relationship_type' => 'subdirector',
            'priority' => 1,
            'is_primary' => true,
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_complete_request_visation_quote_approval_confirmation_and_pdf_flow(): void
    {
        Sanctum::actingAs($this->requester);
        $response = $this->postJson('/api/operational/transfers', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.approval_status', 'borrador')
            ->assertJsonPath('data.visor_user_id', $this->visor->id);
        $transferId = $response->json('data.id');

        $this->post('/api/operational/transfers/'.$transferId.'/documents', [
            'document_type' => 'solicitud_pedagogica',
            'document' => UploadedFile::fake()->create('Solicitud Salida.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->postJson('/api/operational/transfers/'.$transferId.'/submit')
            ->assertOk()->assertJsonPath('data.approval_status', 'pendiente_visacion');

        Sanctum::actingAs($this->visor);
        $this->postJson('/api/operational/transfers/'.$transferId.'/visor/approve', [
            'comment' => 'Visado pedagógicamente.',
            'internal_comment' => 'Revisar disponibilidad presupuestaria antes de confirmar.',
        ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'pendiente_administracion')
            ->assertJsonPath('data.service_status', 'cotizando');

        Sanctum::actingAs($this->requester);
        $this->getJson('/api/operational/transfers/'.$transferId)
            ->assertOk()
            ->assertJsonMissingPath('data.approvals.0.internal_comments');

        Sanctum::actingAs($this->administrator);
        $this->getJson('/api/operational/transfers/'.$transferId)
            ->assertOk()
            ->assertJsonPath('data.approvals.0.internal_comments', 'Revisar disponibilidad presupuestaria antes de confirmar.');
        $provider = $this->postJson('/api/operational/transfers/providers', ['name' => 'Transportes del Sur', 'rut' => '76543210-9'])
            ->assertCreated()->json('data');
        $quote = $this->postJson('/api/operational/transfers/'.$transferId.'/quotes', [
            'provider_id' => $provider['id'],
            'amount' => 185000,
            'reference' => 'COT-2026-184',
        ])->assertCreated()->json('data.quotes.0');

        $this->postJson('/api/operational/transfers/'.$transferId.'/administration/approve')
            ->assertUnprocessable()->assertJsonValidationErrors('quote');
        $this->postJson('/api/operational/transfers/'.$transferId.'/quotes/'.$quote['id'].'/select')
            ->assertOk()->assertJsonPath('data.service_status', 'cotizado');
        $this->postJson('/api/operational/transfers/'.$transferId.'/administration/approve', ['comment' => 'Presupuesto autorizado.'])
            ->assertOk()->assertJsonPath('data.approval_status', 'aprobado');

        $this->assertDatabaseHas('operational_transfer_documents', [
            'operational_transfer_request_id' => $transferId,
            'document_type' => 'pdf_solicitud',
            'official_snapshot' => true,
            'snapshot_stage' => 'aprobacion',
        ]);
        $pdf = $this->get('/api/operational/transfers/'.$transferId.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());

        $this->postJson('/api/operational/transfers/'.$transferId.'/confirm', [
            'confirmation_reference' => 'RES-TRANS-2026-88',
            'confirmation_notes' => 'Bus confirmado para las 08:00.',
            'dte_status' => 'pendiente',
            'payment_status' => 'solicitado',
            'payment_reference' => 'SOL-PAGO-450',
        ])->assertOk()->assertJsonPath('data.service_status', 'confirmado');
        $this->postJson('/api/operational/transfers/'.$transferId.'/execute')
            ->assertOk()->assertJsonPath('data.service_status', 'ejecutado');
        $this->postJson('/api/operational/transfers/'.$transferId.'/cancel', ['comment' => 'Intento posterior'])
            ->assertUnprocessable()->assertJsonValidationErrors('service_status');

        $this->assertDatabaseHas('operational_transfer_requests', [
            'id' => $transferId,
            'approval_status' => 'aprobado',
            'service_status' => 'ejecutado',
            'passenger_count' => 27,
        ]);
        $this->assertDatabaseCount('operational_transfer_approvals', 2);
    }

    public function test_request_cannot_be_submitted_without_pedagogical_document(): void
    {
        Sanctum::actingAs($this->requester);
        $transferId = $this->postJson('/api/operational/transfers', $this->validPayload())->assertCreated()->json('data.id');

        $this->postJson('/api/operational/transfers/'.$transferId.'/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents');
    }

    public function test_administrator_can_edit_an_advanced_transfer_without_changing_its_status(): void
    {
        Sanctum::actingAs($this->requester);
        $transferId = $this->postJson('/api/operational/transfers', $this->validPayload())->assertCreated()->json('data.id');
        $this->post('/api/operational/transfers/'.$transferId.'/documents', [
            'document_type' => 'solicitud_pedagogica',
            'document' => UploadedFile::fake()->create('solicitud.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/operational/transfers/'.$transferId.'/submit')->assertOk();

        $updatedPayload = array_replace($this->validPayload(), [
            'activity_name' => 'Visita educativa actualizada',
            'destination' => 'Museo de Sitio actualizado',
        ]);

        $this->putJson('/api/operational/transfers/'.$transferId, $updatedPayload)->assertForbidden();

        Sanctum::actingAs($this->administrator);
        $this->putJson('/api/operational/transfers/'.$transferId, $updatedPayload)
            ->assertOk()
            ->assertJsonPath('data.activity_name', 'Visita educativa actualizada')
            ->assertJsonPath('data.destination', 'Museo de Sitio actualizado')
            ->assertJsonPath('data.approval_status', 'pendiente_visacion');

        $this->assertDatabaseHas('operational_transfer_logs', [
            'operational_transfer_request_id' => $transferId,
            'user_id' => $this->administrator->id,
            'action' => 'actualizada',
        ]);
    }

    public function test_calendar_returns_only_transfers_in_the_visible_range(): void
    {
        Sanctum::actingAs($this->requester);
        $august = $this->postJson('/api/operational/transfers', $this->validPayload())
            ->assertCreated()
            ->json('data');
        $this->postJson('/api/operational/transfers', array_replace($this->validPayload(), [
            'activity_name' => 'Traslado de septiembre',
            'transport_date' => '2026-09-18',
        ]))->assertCreated();

        $this->getJson('/api/operational/transfers/calendar?queue=mine&date_from=2026-08-01&date_to=2026-08-31')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $august['id'])
            ->assertJsonPath('data.0.transport_date', '2026-08-20');
    }

    public function test_unassigned_subdirector_cannot_review_a_request(): void
    {
        Sanctum::actingAs($this->requester);
        $transferId = $this->postJson('/api/operational/transfers', $this->validPayload())->assertCreated()->json('data.id');
        $this->post('/api/operational/transfers/'.$transferId.'/documents', [
            'document_type' => 'solicitud_pedagogica',
            'document' => UploadedFile::fake()->create('solicitud.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/operational/transfers/'.$transferId.'/submit')->assertOk();

        $otherStaff = Staff::query()->create(['full_name' => 'Otro Subdirector', 'rut' => '44444444-4', 'status' => 'activo', 'active' => true]);
        $otherVisor = User::factory()->create(['staff_id' => $otherStaff->id, 'active' => true]);
        $this->attachRole($otherVisor, 'otro_subdirector', ['ver_traslados_operativos', 'visar_traslados_operativos']);
        Sanctum::actingAs($otherVisor);

        $this->postJson('/api/operational/transfers/'.$transferId.'/visor/approve')->assertForbidden();
        $this->getJson('/api/operational/transfers?queue=review')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_historical_import_uses_one_generic_requester_and_keeps_importer_in_audit(): void
    {
        $row = [
            'row' => 2,
            'can_import' => true,
            'warnings' => ['Sin datos de solicitante; se asignará el perfil institucional "Solicitante genérico".'],
            'transport_date' => '2026-07-29',
            'transport_mode' => 'ida_vuelta',
            'passenger_count' => 31,
            'departure_time' => '08:00',
            'return_time' => '13:30',
            'destination' => 'Museo histórico',
            'requester' => '',
            'reduced_mobility' => false,
            'final_cost' => 185000,
            'provider' => 'Transportes Demo',
            'approval_status' => 'importado_historico',
            'service_status' => 'ejecutado',
            'dte_status' => 'recibido',
            'payment_status' => 'pagado',
            'legacy_status' => 'Ejecutado',
        ];
        $service = app(OperationalTransferSpreadsheetImportService::class);

        $firstToken = (string) Str::uuid();
        $this->storeImportPreview($firstToken, $row);
        $result = $service->commit($firstToken, $this->administrator);

        $generic = Staff::query()->where('institutional_email', OperationalTransferGenericRequesterService::EMAIL)->firstOrFail();
        $this->assertSame(['created' => 1, 'skipped' => 0], collect($result)->only(['created', 'skipped'])->all());
        $this->assertDatabaseHas('operational_transfer_requests', [
            'requester_staff_id' => $generic->id,
            'requested_by_user_id' => $this->administrator->id,
            'requester_name_snapshot' => OperationalTransferGenericRequesterService::NAME,
            'requester_role_snapshot' => 'Solicitante no identificado',
            'legacy_imported' => true,
        ]);
        $this->assertDatabaseHas('operational_transfer_logs', [
            'user_id' => $this->administrator->id,
            'action' => 'importada_historica',
        ]);

        $secondToken = (string) Str::uuid();
        $this->storeImportPreview($secondToken, $row);
        $secondResult = $service->commit($secondToken, $this->administrator);

        $this->assertSame(['created' => 0, 'skipped' => 1], collect($secondResult)->only(['created', 'skipped'])->all());
        $this->assertDatabaseCount('operational_transfer_requests', 1);
        $this->assertDatabaseCount('staff', 4);
    }

    public function test_import_preview_preserves_decimal_numbers_and_corrects_known_historical_date_typo(): void
    {
        $preview = app(OperationalTransferSpreadsheetImportService::class)->preview(
            $this->makeImportWorkbook(),
            $this->administrator,
        );

        $this->assertSame(1, $preview['total_rows']);
        $this->assertSame(1, $preview['importable_rows']);
        $this->assertSame(34, $preview['rows'][0]['passenger_count']);
        $this->assertSame(80000, $preview['rows'][0]['final_cost']);
        $this->assertSame('2026-07-29', $preview['rows'][0]['transport_date']);
        $this->assertContains(
            'La fecha "29/07/0206" se corrigió automáticamente a "29/07/2026"; revisar el dato histórico.',
            $preview['rows'][0]['warnings'],
        );
        $this->assertContains(
            'Sin datos de solicitante; se asignará el perfil institucional "Solicitante genérico".',
            $preview['rows'][0]['warnings'],
        );
    }

    private function validPayload(): array
    {
        return [
            'requester_staff_id' => $this->requester->staff_id,
            'activity_type' => 'salida_pedagogica',
            'activity_name' => 'Visita educativa al Museo de Sitio',
            'course_subject' => '5° Básico · Historia',
            'purpose' => 'Reconocer el patrimonio cultural de la región.',
            'transport_date' => '2026-08-20',
            'departure_time' => '08:00',
            'return_time' => '13:30',
            'origin' => 'Colegio Nuestra Señora del Carmen',
            'destination' => 'Museo de Sitio Castillo de Niebla',
            'transport_mode' => 'ida_vuelta',
            'student_count' => 24,
            'adult_count' => 3,
            'reduced_mobility' => true,
            'mobility_requirements' => 'Espacio para silla de ruedas y acceso mediante rampa.',
            'urgent' => false,
        ];
    }

    private function storeImportPreview(string $token, array $row): void
    {
        Storage::disk('local')->put('operational-transfer-imports/'.$token.'.json', json_encode([
            'user_id' => $this->administrator->id,
            'file_name' => 'Gestión traslados.xlsx',
            'created_at' => now()->toIso8601String(),
            'rows' => [$row],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function makeImportWorkbook(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'transfer-import-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="inlineStr"><is><t>DIA</t></is></c>
      <c r="B1" t="inlineStr"><is><t>MODALIDAD</t></is></c>
      <c r="C1" t="inlineStr"><is><t>PASAJEROS</t></is></c>
      <c r="D1" t="inlineStr"><is><t>SALIDA</t></is></c>
      <c r="E1" t="inlineStr"><is><t>RETORNO</t></is></c>
      <c r="F1" t="inlineStr"><is><t>DESTINO</t></is></c>
      <c r="G1" t="inlineStr"><is><t>MOVI REDUCIDAD</t></is></c>
      <c r="H1" t="inlineStr"><is><t>COSTO</t></is></c>
      <c r="I1" t="inlineStr"><is><t>PROVEEDOR</t></is></c>
      <c r="J1" t="inlineStr"><is><t>ESTADO</t></is></c>
      <c r="K1" t="inlineStr"><is><t>DTE</t></is></c>
      <c r="L1" t="inlineStr"><is><t>PAGO</t></is></c>
    </row>
    <row r="2">
      <c r="A2" t="inlineStr"><is><t>29/07/0206</t></is></c>
      <c r="B2" t="inlineStr"><is><t>Ida y vuelta</t></is></c>
      <c r="C2"><v>34.0</v></c>
      <c r="D2"><v>0.3854166666666667</v></c>
      <c r="E2"><v>0.5</v></c>
      <c r="F2" t="inlineStr"><is><t>Universidad Austral de Chile</t></is></c>
      <c r="G2" t="inlineStr"><is><t>no</t></is></c>
      <c r="H2"><v>80000.0</v></c>
      <c r="I2" t="inlineStr"><is><t>Transportes Demo</t></is></c>
      <c r="J2" t="inlineStr"><is><t>EJECUTADO</t></is></c>
      <c r="K2" t="inlineStr"><is><t>SI</t></is></c>
      <c r="L2" t="inlineStr"><is><t>SI</t></is></c>
    </row>
  </sheetData>
</worksheet>
XML);
        $zip->close();

        return new UploadedFile(
            $path,
            'Gestión traslados.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function attachRole(User $user, string $slug, array $permissions): void
    {
        $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => str_replace('_', ' ', $slug), 'active' => true]);
        $permissionIds = collect($permissions)->map(function (string $permission): int {
            return Permission::query()->firstOrCreate(
                ['slug' => $permission],
                ['name' => str_replace('_', ' ', $permission), 'active' => true],
            )->id;
        });
        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->attach($role);
    }
}
