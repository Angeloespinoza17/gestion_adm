<?php

namespace Tests\Feature\Remuneration;

use App\Jobs\Remuneration\AnalyzePayslipBatch;
use App\Models\Accounting\AccountingFundingSource;
use App\Models\LibroDigital\School;
use App\Models\Remuneration\RemunerationBookImport;
use App\Models\Remuneration\RemunerationPayslip;
use App\Models\Remuneration\RemunerationPayslipBatch;
use App\Models\Remuneration\RemunerationPayslipFile;
use App\Models\Remuneration\RemunerationPayslipPage;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\Remuneration\Payslips\PayslipExportService;
use App\Services\Remuneration\Payslips\PayslipImportService;
use App\Services\Remuneration\Payslips\PayslipPaymentProposalService;
use App\Services\Remuneration\Payslips\PayslipReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayslipModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_pdf_staging_requires_permission_queues_analysis_and_rejects_duplicate_hash(): void
    {
        Storage::fake('local');
        Queue::fake();
        $school = $this->school();

        Sanctum::actingAs(User::factory()->create(['active' => true]));
        $this->getJson('/api/remuneraciones/liquidaciones-sueldo/catalogs')->assertForbidden();

        Sanctum::actingAs($this->superAdmin());
        $first = $this->post('/api/remuneraciones/liquidaciones-sueldo/batches', [
            'school_id' => $school->id,
            'duplicate_action' => 'reject',
            'files' => [UploadedFile::fake()->create('liquidaciones.pdf', 8, 'application/pdf')],
        ]);

        $first->assertAccepted()
            ->assertJsonPath('data.school_id', $school->id)
            ->assertJsonMissingPath('data.files.0.private_path');
        Queue::assertPushed(AnalyzePayslipBatch::class);
        $stored = RemunerationPayslipFile::query()->firstOrFail();
        Storage::disk('local')->assertExists($stored->getRawOriginal('private_path'));

        $this->post('/api/remuneraciones/liquidaciones-sueldo/batches', [
            'school_id' => $school->id,
            'duplicate_action' => 'reject',
            'files' => [UploadedFile::fake()->create('mismo-contenido.pdf', 8, 'application/pdf')],
        ])->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->assertSame(1, RemunerationPayslipBatch::query()->count());
        $this->assertSame(1, RemunerationPayslipFile::query()->count());
    }

    public function test_private_api_masks_rut_and_never_exposes_storage_path(): void
    {
        $context = $this->confirmedPayslip();
        Sanctum::actingAs($context['user']);

        $this->getJson('/api/remuneraciones/liquidaciones-sueldo/history')
            ->assertOk()
            ->assertJsonPath('data.0.rut', '*****6785')
            ->assertJsonMissingPath('data.0.rut_encrypted')
            ->assertJsonMissingPath('data.0.private_path');

        $link = $this->getJson('/api/remuneraciones/liquidaciones-sueldo/files/'.$context['file']->id.'/link')
            ->assertOk()
            ->json('url');
        $this->assertStringContainsString('signature=', $link);
        $this->assertStringNotContainsString('private/remuneration', $link);
    }

    public function test_payment_proposal_preserves_one_worker_payment_and_never_posts_budget_movements(): void
    {
        $context = $this->confirmedPayslip();
        $service = app(PayslipPaymentProposalService::class);
        $movementCount = \DB::table('remuneration_movements')->count();

        $proposal = $service->generate($context['school'], $context['period'], $context['user'], 'Revisión mensual');

        $this->assertSame('borrador', $proposal->status);
        $this->assertSame(300, $proposal->net_total);
        $this->assertSame(300, $proposal->distributed_total);
        $this->assertCount(1, $proposal->items);
        $this->assertSame(300, $proposal->items->first()->payment_amount);
        $this->assertSame(2, $proposal->items->first()->allocations->count());
        $this->assertSame(300, (int) $proposal->items->first()->allocations->sum('amount'));
        $this->assertSame($movementCount, \DB::table('remuneration_movements')->count());

        $confirmed = $service->confirm($proposal, $context['user']);
        $this->assertSame('confirmada', $confirmed->status);
        $this->assertDatabaseCount('remuneration_payment_proposals', 1);

        $this->expectException(ValidationException::class);
        $service->generate($context['school'], $context['period'], $context['user']);
    }

    public function test_excel_export_contains_required_audit_matrices_and_csv_is_available(): void
    {
        $context = $this->confirmedPayslip();
        $workbook = app(PayslipExportService::class)->workbook([
            'school_id' => $context['school']->id,
            'year' => 2026,
            'month' => 7,
        ]);

        $this->assertSame([
            'Resumen',
            'Matriz Funcionarios',
            'Matriz Haberes',
            'Descuentos',
            'Aportes Empleador',
            'Controles',
            'Metodología',
        ], $workbook->getSheetNames());
        $this->assertSame(300, (int) $workbook->getSheetByName('Resumen')->getCell('B7')->getValue());
        $workbook->disconnectWorksheets();

        Sanctum::actingAs($context['user']);
        $this->get('/api/remuneraciones/liquidaciones-sueldo/export/csv/earnings')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_confirmation_matches_only_exact_rut_and_creates_an_immutable_version_chain(): void
    {
        $context = $this->confirmedPayslip();
        $service = app(PayslipImportService::class);
        $replacement = $this->draftCopy($context);

        $service->confirm($replacement['batch']->id, $context['user']->id, [], 'new_version');

        $this->assertDatabaseHas('remuneration_payslips', [
            'id' => $context['payslip']->id,
            'status' => 'reemplazada',
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('remuneration_payslips', [
            'id' => $replacement['payslip']->id,
            'previous_version_id' => $context['payslip']->id,
            'version' => 2,
            'status' => 'importada',
            'is_current' => true,
        ]);

        $differentStaff = Staff::query()->create([
            'full_name' => 'Otra Persona',
            'rut' => '11.111.111-1',
            'status' => 'activo',
            'active' => true,
        ]);
        $invalid = $this->draftCopy($context);

        try {
            $service->confirm($invalid['batch']->id, $context['user']->id, [[
                'payslip_id' => $invalid['payslip']->public_id,
                'staff_id' => $differentStaff->id,
                'year' => 2026,
                'month' => 7,
            ]], 'new_version');
            $this->fail('Una asociación de RUT distinto no debe confirmarse.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('coincidencia exacta de RUT', $exception->getMessage());
        }

        $this->assertDatabaseHas('remuneration_payslips', [
            'id' => $invalid['payslip']->id,
            'status' => 'borrador',
            'is_current' => false,
        ]);
    }

    public function test_reconciliation_compares_book_and_payslip_without_overwriting_either_source(): void
    {
        $context = $this->confirmedPayslip();
        $book = RemunerationBookImport::query()->create([
            'period_id' => $context['period']->id,
            'original_filename' => 'libro-julio.xlsx',
            'file_hash' => hash('sha256', 'book-fixture'),
            'status' => 'imported',
            'book_period' => '2026-07-01',
            'year' => 2026,
            'month' => 7,
            'row_count' => 1,
            'matched_count' => 1,
            'gross_total' => 350,
            'net_total' => 300,
            'total_deductions' => 50,
            'employer_contributions' => 30,
            'metadata' => ['rbd' => $context['school']->rbd],
            'imported_at' => now(),
            'imported_by' => $context['user']->id,
        ]);
        $book->rows()->create([
            'staff_id' => $context['staff']->id,
            'row_number' => 1,
            'rut' => $context['staff']->rut,
            'employee_name' => $context['staff']->full_name,
            'gross_taxable_amount' => 300,
            'gross_non_taxable_amount' => 50,
            'gross_total' => 350,
            'taxable_amount' => 300,
            'legal_deductions' => 30,
            'other_deductions' => 20,
            'total_deductions' => 50,
            'employer_contributions' => 30,
            'net_amount' => 300,
        ]);

        $result = app(PayslipReportingService::class)->reconciliation($context['school'], 2026, 7);

        $this->assertSame('correcto', $result['status']);
        $this->assertSame(1, $result['summary']['both']);
        $this->assertSame(0, $result['summary']['only_book']);
        $this->assertSame(0, $result['summary']['only_payslips']);
        $this->assertSame('correcto', $result['rows'][0]['status']);
        $this->assertSame(300, $context['payslip']->fresh()->net_amount);
        $this->assertSame(300, $book->fresh()->net_total);
    }

    /** @return array{user:User,school:School,period:RemunerationPeriod,staff:Staff,file:RemunerationPayslipFile,payslip:RemunerationPayslip} */
    private function confirmedPayslip(): array
    {
        $user = $this->superAdmin();
        $school = $this->school();
        $period = RemunerationPeriod::query()->create([
            'year' => 2026,
            'month' => 7,
            'name' => 'Julio 2026',
            'status' => 'abierto',
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $staff = Staff::query()->create([
            'full_name' => 'Persona de Prueba',
            'rut' => '12.345.678-5',
            'status' => 'activo',
            'active' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $batch = RemunerationPayslipBatch::query()->create([
            'school_id' => $school->id,
            'status' => 'confirmado',
            'stage' => 'resultado',
            'file_count' => 1,
            'page_count' => 1,
            'processed_pages' => 1,
            'payslip_count' => 1,
            'gross_total' => 350,
            'taxable_total' => 300,
            'non_taxable_total' => 50,
            'deduction_total' => 50,
            'net_total' => 300,
            'employer_contribution_total' => 30,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $file = RemunerationPayslipFile::query()->create([
            'batch_id' => $batch->id,
            'school_id' => $school->id,
            'sha256' => hash('sha256', 'private-fixture'),
            'original_filename' => 'liquidaciones.pdf',
            'private_path' => 'private/remuneration/payslips/fixture.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'provider' => 'numerus',
            'parser_version' => 'numerus-v1.0.0',
            'page_count' => 1,
            'detected_year' => 2026,
            'detected_month' => 7,
            'status' => 'confirmado',
            'uploaded_by' => $user->id,
        ]);
        $page = RemunerationPayslipPage::query()->create([
            'file_id' => $file->id,
            'page_number' => 1,
            'provider' => 'numerus',
            'parser_version' => 'numerus-v1.0.0',
            'period_year' => 2026,
            'period_month' => 7,
            'confidence' => 1,
            'status' => 'analizada',
        ]);
        $payslip = RemunerationPayslip::query()->create([
            'batch_id' => $batch->id,
            'file_id' => $file->id,
            'page_id' => $page->id,
            'school_id' => $school->id,
            'period_id' => $period->id,
            'staff_id' => $staff->id,
            'rut_hash' => hash_hmac('sha256', '123456785', (string) config('app.key')),
            'rut_encrypted' => '123456785',
            'employee_name_encrypted' => 'Persona de Prueba',
            'business_key_hash' => hash_hmac('sha256', $school->id.'|123456785|2026|7', (string) config('app.key')),
            'year' => 2026,
            'month' => 7,
            'version' => 1,
            'is_current' => true,
            'status' => 'importada',
            'reconciliation_status' => 'pendiente',
            'confidence' => 1,
            'gross_taxable_amount' => 300,
            'gross_non_taxable_amount' => 50,
            'gross_total' => 350,
            'legal_deductions' => 30,
            'other_deductions' => 20,
            'total_deductions' => 50,
            'net_amount' => 300,
            'employer_contributions' => 30,
            'total_cost' => 380,
            'confirmed_at' => now(),
            'confirmed_by' => $user->id,
            'metadata' => ['provider' => 'numerus', 'page_number' => 1],
        ]);
        $page->update(['payslip_id' => $payslip->id]);

        $general = AccountingFundingSource::query()->where('code', 'GENERAL')->firstOrFail();
        $sep = AccountingFundingSource::query()->where('code', 'SEP')->firstOrFail();
        $payslip->fundingSummaries()->create([
            'funding_source_id' => $general->id,
            'taxable_earnings' => 200,
            'non_taxable_earnings' => 50,
            'gross_earnings' => 250,
            'legal_deductions' => 20,
            'other_deductions' => 14,
            'net_amount' => 216,
            'employer_contributions' => 20,
            'total_cost' => 270,
        ]);
        $payslip->fundingSummaries()->create([
            'funding_source_id' => $sep->id,
            'taxable_earnings' => 100,
            'gross_earnings' => 100,
            'legal_deductions' => 10,
            'other_deductions' => 6,
            'net_amount' => 84,
            'employer_contributions' => 10,
            'total_cost' => 110,
        ]);
        $payslip->controls()->create([
            'batch_id' => $batch->id,
            'file_id' => $file->id,
            'scope' => 'liquidacion',
            'code' => 'net_equation',
            'label' => 'Haberes - descuentos = líquido',
            'status' => 'correcto',
            'expected_amount' => 300,
            'actual_amount' => 300,
            'difference' => 0,
        ]);
        $payslip->earnings()->create([
            'funding_source_id' => $general->id,
            'code' => '1000',
            'description' => 'Sueldo base',
            'line_number' => 1,
            'is_imponible' => true,
            'amount' => 200,
            'source_funding_label' => 'GENERAL',
            'original_label' => '(1000) SUELDO BASE',
            'page_number' => 1,
        ]);

        return compact('user', 'school', 'period', 'staff', 'file', 'payslip');
    }

    /** @param array{user:User,school:School,period:RemunerationPeriod,staff:Staff,file:RemunerationPayslipFile,payslip:RemunerationPayslip} $context
     * @return array{batch:RemunerationPayslipBatch,payslip:RemunerationPayslip}
     */
    private function draftCopy(array $context): array
    {
        $batch = RemunerationPayslipBatch::query()->create([
            'school_id' => $context['school']->id,
            'status' => 'listo_revision',
            'stage' => 'vista_previa',
            'file_count' => 1,
            'page_count' => 1,
            'processed_pages' => 1,
            'payslip_count' => 1,
            'created_by' => $context['user']->id,
            'updated_by' => $context['user']->id,
        ]);
        $file = RemunerationPayslipFile::query()->create([
            'batch_id' => $batch->id,
            'school_id' => $context['school']->id,
            'replaces_file_id' => $context['file']->id,
            'sha256' => hash('sha256', 'replacement-'.$batch->id),
            'original_filename' => 'version-'.$batch->id.'.pdf',
            'private_path' => 'private/remuneration/payslips/version-'.$batch->id.'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'provider' => 'numerus',
            'parser_version' => 'numerus-v1.0.0',
            'page_count' => 1,
            'detected_year' => 2026,
            'detected_month' => 7,
            'status' => 'analizado',
            'version' => 2,
            'uploaded_by' => $context['user']->id,
        ]);
        $page = RemunerationPayslipPage::query()->create([
            'file_id' => $file->id,
            'page_number' => 1,
            'provider' => 'numerus',
            'parser_version' => 'numerus-v1.0.0',
            'period_year' => 2026,
            'period_month' => 7,
            'confidence' => 1,
            'status' => 'analizada',
        ]);
        $payslip = RemunerationPayslip::query()->create([
            'batch_id' => $batch->id,
            'file_id' => $file->id,
            'page_id' => $page->id,
            'school_id' => $context['school']->id,
            'staff_id' => $context['staff']->id,
            'rut_hash' => hash_hmac('sha256', '123456785', (string) config('app.key')),
            'rut_encrypted' => '123456785',
            'employee_name_encrypted' => 'Persona de Prueba',
            'business_key_hash' => hash_hmac('sha256', $context['school']->id.'|123456785|2026|7', (string) config('app.key')),
            'year' => 2026,
            'month' => 7,
            'version' => 1,
            'is_current' => false,
            'status' => 'borrador',
            'confidence' => 1,
            'gross_taxable_amount' => 300,
            'gross_non_taxable_amount' => 50,
            'gross_total' => 350,
            'legal_deductions' => 30,
            'other_deductions' => 20,
            'total_deductions' => 50,
            'net_amount' => 300,
            'employer_contributions' => 30,
            'total_cost' => 380,
        ]);
        $page->update(['payslip_id' => $payslip->id]);

        return compact('batch', 'payslip');
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin', 'active' => true]);
        $user = User::factory()->create(['active' => true]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function school(): School
    {
        return School::query()->create([
            'rbd' => '1-9',
            'name' => 'Colegio de Prueba',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);
    }
}
