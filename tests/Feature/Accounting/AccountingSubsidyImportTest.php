<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingIncome;
use App\Models\Accounting\AccountingJournalEntry;
use App\Models\Accounting\AccountingManualAccount;
use App\Models\Accounting\AccountingSubsidySettlement;
use App\Models\User;
use Database\Seeders\AccountingModuleSeeder;
use Database\Seeders\EducationLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountingSubsidyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_annexes_without_double_counting_pie_and_posts_one_income(): void
    {
        Storage::fake('local');
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);

        $files = [
            $this->annex('Subvencion_Normal_Anexo_Detalle_Escolaridad_RBD_6830_202607.xls', 'Subvención Base', [10, 20, 30]),
            $this->annex('Subvencion_Normal_Anexo_Detalle_Incremento_Zona_RBD_6830_202607.xls', 'Monto Incremento Zona', [1, 2, 3]),
            $this->annex('Subvencion_Normal_Anexo_Detalle_Aporte_Gratuidad_RBD_6830_202607.xls', 'Subvención Gratuidad', [2, 4, 6]),
            $this->annex('Subvencion_Normal_Anexo_Detalle_Monto_Ley19464_RBD_6830_202607.xls', 'Monto Ley 19464', [3, 6, 9]),
            $this->annex('Subvencion_Normal_Anexo_Detalle_Reforma_Pensiones_RBD_6830_202607.xls', 'Subvención Reforma Pensiones', [4, 8, 12]),
            UploadedFile::fake()->createWithContent(
                'Subvencion_Normal_Anexo_Detalle_Pie_RBD_6830_202607.xls',
                $this->pieAnnexHtml(),
            ),
            UploadedFile::fake()->createWithContent(
                'Subvencion_Normal_Orden_Pago_202607_RBD_6830.xls',
                $this->paymentOrderHtml(),
            ),
        ];

        $response = $this->post('/api/contabilidad/subvenciones/importar', ['files' => $files]);

        $response
            ->assertCreated()
            ->assertJsonCount(7, 'imports')
            ->assertJsonCount(0, 'duplicates')
            ->assertJsonPath('settlements.0.status', 'validado');

        $settlement = AccountingSubsidySettlement::query()->with(['lines', 'allocations.educationLevel'])->firstOrFail();
        $this->assertSame(120.0, (float) $settlement->net_amount);
        $this->assertSame(120.0, (float) $settlement->gross_amount);
        $this->assertCount(6, $settlement->lines);
        $pieLine = $settlement->lines->firstWhere('concept_code', 'pie_breakdown');
        $this->assertTrue($pieLine->informative);
        $this->assertTrue($pieLine->education_allocable);
        $this->assertCount(4, $pieLine->allocations);
        $this->assertSame(99.0, (float) $pieLine->allocations->sum('amount'));
        $this->assertSame(20.0, $this->amountForLevelType($settlement, 'parvularia'));
        $this->assertSame(40.0, $this->amountForLevelType($settlement, 'basica'));
        $this->assertSame(60.0, $this->amountForLevelType($settlement, 'media'));

        $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-07')
            ->assertOk()
            ->assertJsonPath('metrics.net_liquidated', 120)
            ->assertJsonPath('metrics.allocated_total', 120)
            ->assertJsonPath('metrics.unallocated_total', 0)
            ->assertJsonPath('metrics.pie_informative_total', 99)
            ->assertJsonPath('pie.total', 99)
            ->assertJsonPath('pie.allocated_total', 99)
            ->assertJsonPath('pie.unallocated_total', 0)
            ->assertJsonPath('pie.row_count', 4)
            ->assertJsonPath('pie.by_level.0.amount', 33)
            ->assertJsonPath('pie.by_level.0.detail_count', 2)
            ->assertJsonPath('pie.by_level.0.enrollment', 2)
            ->assertJsonCount(3, 'pie.by_course')
            ->assertJsonPath('pie.by_course.0.label', 'NT1 A')
            ->assertJsonPath('pie.by_course.0.detail_count', 2)
            ->assertJsonPath('pie.by_course.0.amount', 33)
            ->assertJsonPath('per_student.enrollment_total', 60)
            ->assertJsonPath('per_student.allocated_amount', 120)
            ->assertJsonPath('per_student.enrollment_source', 'pension_reform')
            ->assertJsonPath('per_student.by_cycle.0.label', 'Educación Parvularia')
            ->assertJsonPath('per_student.by_cycle.0.average_per_student', 2)
            ->assertJsonPath('per_student.by_cycle.1.label', 'Educación Básica')
            ->assertJsonPath('per_student.by_cycle.1.average_per_student', 2)
            ->assertJsonPath('per_student.by_grade.1.label', '1° básico')
            ->assertJsonPath('per_student.by_grade.1.enrollment', 20)
            ->assertJsonPath('per_student.by_grade.1.average_per_student', 2)
            ->assertJsonPath('comparison.period', '2026-06')
            ->assertJsonCount(0, 'comparison.per_student.by_cycle')
            ->assertJsonPath('annual.6.period', '2026-07')
            ->assertJsonPath('annual.6.net_liquidated', 120)
            ->assertJsonPath('annual.6.pie_total', 99);

        $this->postJson("/api/contabilidad/subvenciones/{$settlement->id}/aprobar", [
            'transferred_amount' => 120,
        ])->assertOk()->assertJsonPath('data.status', 'aprobado');

        $incomeAccount = AccountingManualAccount::query()->where('code', '3101')->firstOrFail();
        $bankAccount = AccountingBankAccount::query()->firstOrFail();
        $postResponse = $this->postJson("/api/contabilidad/subvenciones/{$settlement->id}/contabilizar", [
            'received_at' => '2026-07-27',
            'transferred_amount' => 120,
            'manual_account_id' => $incomeAccount->id,
            'bank_account_id' => $bankAccount->id,
        ]);

        $postResponse->assertOk()->assertJsonPath('data.status', 'contabilizado');

        $income = AccountingIncome::query()->where('code', 'ING-'.$settlement->code)->firstOrFail();
        $this->assertSame(120.0, (float) $income->amount);
        $this->assertDatabaseHas('accounting_subsidy_matches', [
            'settlement_id' => $settlement->id,
            'income_id' => $income->id,
            'matched_amount' => 120,
            'status' => 'conciliado',
        ]);
        $this->assertDatabaseHas('accounting_bank_movements', [
            'referenceable_id' => $income->id,
            'amount' => 120,
            'is_reconciled' => true,
        ]);

        $journal = AccountingJournalEntry::query()
            ->where('sourceable_type', $income->getMorphClass())
            ->where('sourceable_id', $income->id)
            ->with('lines')
            ->firstOrFail();
        $this->assertSame(120.0, (float) $journal->lines->sum('debit'));
        $this->assertSame(120.0, (float) $journal->lines->sum('credit'));

        $this->postJson("/api/contabilidad/subvenciones/{$settlement->id}/contabilizar", [
            'received_at' => '2026-07-27',
            'manual_account_id' => $incomeAccount->id,
            'bank_account_id' => $bankAccount->id,
        ])->assertUnprocessable();
        $this->assertSame(1, AccountingIncome::query()->where('code', 'ING-'.$settlement->code)->count());

        $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-07&compare_period=2026-06')
            ->assertOk()
            ->assertJsonPath('metrics.income_total', 120)
            ->assertJsonPath('comparison.metrics.income_total', 0)
            ->assertJsonPath('comparison.deltas.income_total.amount', 120);
    }

    public function test_it_omits_duplicate_files_and_does_not_overwrite_an_imported_settlement_with_manual_gross(): void
    {
        Storage::fake('local');
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        Sanctum::actingAs(User::query()->firstOrFail());

        $filename = 'Subvencion_Normal_Anexo_Detalle_Escolaridad_RBD_6830_202607.xls';
        $content = $this->annexHtml('Subvención Base', [10, 20, 30]);

        $this->post('/api/contabilidad/subvenciones/importar', [
            'files' => [UploadedFile::fake()->createWithContent($filename, $content)],
        ])->assertCreated();

        $this->post('/api/contabilidad/subvenciones/importar', [
            'files' => [UploadedFile::fake()->createWithContent($filename, $content)],
        ])
            ->assertCreated()
            ->assertJsonCount(0, 'imports')
            ->assertJsonCount(1, 'duplicates');

        $this->assertDatabaseCount('accounting_subsidy_imports', 1);
        $this->assertDatabaseCount('accounting_subsidy_settlements', 1);

        $this->postJson('/api/contabilidad/subvenciones/manual', [
            'rbd' => '6830',
            'period' => '2026-07',
            'subsidy_type' => 'normal',
            'gross_amount' => 999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subsidy_type');

        $settlement = AccountingSubsidySettlement::query()->firstOrFail();
        $this->assertSame(60.0, (float) $settlement->net_amount);
        $this->assertFalse($settlement->lines()->where('concept_code', 'manual_gross')->exists());
    }

    public function test_it_rejects_the_whole_batch_before_persisting_when_a_file_is_invalid(): void
    {
        Storage::fake('local');
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->post('/api/contabilidad/subvenciones/importar', [
            'files' => [
                $this->annex(
                    'Subvencion_Normal_Anexo_Detalle_Escolaridad_RBD_6830_202607.xls',
                    'Subvención Base',
                    [10, 20, 30],
                ),
                UploadedFile::fake()->createWithContent('archivo-invalido.xls', 'contenido no reconocido'),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.1');

        $this->assertDatabaseCount('accounting_subsidy_imports', 0);
        $this->assertDatabaseCount('accounting_subsidy_settlements', 0);
    }

    public function test_it_validates_that_uploaded_files_match_the_selected_month_and_year(): void
    {
        Storage::fake('local');
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        Sanctum::actingAs(User::query()->firstOrFail());

        $this->post('/api/contabilidad/subvenciones/importar', [
            'period' => '2026-06',
            'files' => [
                $this->annex(
                    'Subvencion_Normal_Anexo_Detalle_Escolaridad_RBD_6830_202607.xls',
                    'Subvención Base',
                    [10, 20, 30],
                ),
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        $this->assertDatabaseCount('accounting_subsidy_imports', 0);
        $this->assertDatabaseCount('accounting_subsidy_settlements', 0);
    }

    public function test_it_imports_pro_retention_and_school_bonus_without_treating_the_payroll_as_unassigned_education(): void
    {
        Storage::fake('local');
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        Sanctum::actingAs(User::query()->firstOrFail());

        $response = $this->post('/api/contabilidad/subvenciones/importar', [
            'period' => '2026-06',
            'files' => [
                UploadedFile::fake()->createWithContent(
                    'Listado_Alumnos_Establecimiento_ProRetencion_RBD_6830_Sostenedor_65031932_202606.xls',
                    $this->proRetentionHtml(),
                ),
                UploadedFile::fake()->createWithContent(
                    'Lista_Trabajadores_Bono_Escolar_RBD_6830_202606.xls',
                    $this->schoolBonusHtml(),
                ),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'imports')
            ->assertJsonCount(2, 'settlements');

        $proRetention = AccountingSubsidySettlement::query()
            ->where('subsidy_type', 'pro_retention')
            ->with('lines.allocations.educationLevel')
            ->firstOrFail();
        $schoolBonus = AccountingSubsidySettlement::query()
            ->where('subsidy_type', 'school_bonus')
            ->with('lines.allocations')
            ->firstOrFail();

        $this->assertSame(300.0, (float) $proRetention->net_amount);
        $this->assertSame(2, $proRetention->lines->first()->allocations->count());
        $this->assertSame(120.0, (float) $schoolBonus->net_amount);
        $this->assertFalse($schoolBonus->lines->first()->education_allocable);
        $this->assertSame(2, $schoolBonus->lines->first()->allocations->count());
        $this->assertSame(100.0, (float) $schoolBonus->lines->first()->metadata['bonus_components']['bonus_amount']);
        $this->assertSame(20.0, (float) $schoolBonus->lines->first()->metadata['bonus_components']['additional_amount']);

        $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-06&compare_period=2026-05')
            ->assertOk()
            ->assertJsonPath('metrics.net_liquidated', 420)
            ->assertJsonPath('metrics.allocated_total', 300)
            ->assertJsonPath('metrics.unallocated_total', 0)
            ->assertJsonPath('by_level.0.amount', 100)
            ->assertJsonPath('by_level.1.amount', 200)
            ->assertJsonCount(2, 'by_family');
    }

    public function test_it_accepts_cd_brp_and_assignment_by_tranche_as_predefined_simple_amounts(): void
    {
        Storage::fake('local');
        $this->seed([EducationLevelSeeder::class, AccountingModuleSeeder::class]);
        Sanctum::actingAs(User::query()->firstOrFail());

        foreach ([
            'cd_brp' => 1000,
            'cd_asignacion_tramo' => 2000,
        ] as $type => $amount) {
            $this->postJson('/api/contabilidad/subvenciones/manual', [
                'rbd' => '6830',
                'period' => '2026-06',
                'subsidy_type' => $type,
                'gross_amount' => $amount,
            ])
                ->assertCreated()
                ->assertJsonPath('data.subsidy_type', $type)
                ->assertJsonPath('data.net_amount', number_format($amount, 2, '.', ''));
        }

        $this->assertDatabaseHas('accounting_subsidy_settlements', [
            'subsidy_type' => 'cd_brp',
            'net_amount' => 1000,
        ]);
        $this->assertDatabaseHas('accounting_subsidy_settlements', [
            'subsidy_type' => 'cd_asignacion_tramo',
            'net_amount' => 2000,
        ]);

        $this->postJson('/api/contabilidad/subvenciones/manual', [
            'rbd' => '6830',
            'period' => '2026-06',
            'subsidy_type' => 'school_bonus',
            'gross_amount' => 500,
        ])->assertCreated();

        $this->getJson('/api/contabilidad/subvenciones/dashboard?period=2026-06&compare_period=2026-05')
            ->assertOk()
            ->assertJsonPath('metrics.net_liquidated', 3500)
            ->assertJsonPath('metrics.allocated_total', 0)
            ->assertJsonPath('metrics.unallocated_total', 3000);
    }

    private function annex(string $filename, string $amountHeader, array $amounts): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $this->annexHtml($amountHeader, $amounts));
    }

    private function annexHtml(string $amountHeader, array $amounts): string
    {
        $rows = [
            ['10', '4', 'A', 'NT1', '10', $amounts[0]],
            ['110', '1', 'A', '1° básico', '20', $amounts[1]],
            ['310', '1', 'A', '1° medio', '30', $amounts[2]],
        ];
        $body = collect($rows)
            ->map(fn (array $row): string => '<tr><td>'.implode('</td><td>', $row).'</td></tr>')
            ->implode('');

        return $this->documentHeader()
            .'<table><tr><th>Código Enseñanza</th><th>Grado</th><th>Letra</th><th>Glosa</th>'
            .'<th>Matrícula</th><th>'.$amountHeader.'</th></tr>'.$body.'</table></body></html>';
    }

    private function documentHeader(): string
    {
        return '<html><head><meta charset="UTF-8"></head><body><div>Establecimiento: 6830 COLEGIO NUESTRA SEÑORA DEL CARMEN</div>'
            .'<div>MES PAGO: JULIO 2026</div>';
    }

    private function paymentOrderHtml(): string
    {
        return $this->documentHeader()
            .'<div>Resumen Conceptos Calculados</div>'
            .'<div>Subtotal Subvención Base $ 60</div>'
            .'<div>Incremento Zona $ 6</div>'
            .'<div>Aporte por Gratuidad $ 12</div>'
            .'<div>Monto Ley 19.464 $ 18</div>'
            .'<div>Reforma de Pensiones $ 24</div>'
            .'<div>Líquido Pago Final $ 120</div></body></html>';
    }

    private function pieAnnexHtml(): string
    {
        $headers = [
            'Cod. Ens.', 'Grado', 'Jec', 'Letra', 'Ens.', 'Nivel', 'Glosa Subvención',
            'Matrícula', 'Promedio', 'Factor', 'Subvención', 'Monto Ruralidad',
            'Total Monto (Adic) Ley 19.410', 'Total Monto (No doc) Ley 19.464',
            'Monto Ley 19.933 (referencial)',
        ];
        $rows = [
            ['10', '4', 'NO', 'A', '260', '1', 'PIE Parvularia permanente', '1', '0,5', '4,5', '$ 15', '$ 0', '$ 0', '$ 1', '$ 2'],
            ['10', '4', 'NO', 'A', '260', '1', 'PIE Parvularia transitorio', '1', '0,5', '4,5', '$ 15', '$ 0', '$ 0', '$ 1', '$ 2'],
            ['110', '1', 'SI', 'A', '610', '1', 'PIE Básica', '1', '1', '4,5', '$ 30', '$ 0', '$ 0', '$ 1', '$ 4'],
            ['310', '1', 'SI', 'A', '810', '1', 'PIE Media', '1', '1', '4,5', '$ 30', '$ 0', '$ 0', '$ 1', '$ 4'],
        ];
        $body = collect($rows)
            ->map(fn (array $row): string => '<tr><td>'.implode('</td><td>', $row).'</td></tr>')
            ->implode('');

        return $this->documentHeader()
            .'<table><tr><td>Subvención</td><td>$ 90</td><td>Incremento Zona (10%)</td><td>$ 9</td></tr>'
            .'<tr><td>Total montos pagados PIE</td><td>$ 99</td><td>Total No Docente</td><td>$ 3</td></tr></table>'
            .'<table><tr><th>'.implode('</th><th>', $headers).'</th></tr>'.$body.'</table></body></html>';
    }

    private function proRetentionHtml(): string
    {
        return '<html><body><div>MES PAGO: JUNIO 2026</div><table>'
            .'<tr><td>Periodo</td><td>Rbd</td><td>Codigo Ensenanza</td><td>Glosa Tipo Ensenanza</td>'
            .'<td>Curso</td><td>Glosa Curso</td><td>Rut Alumno</td><td>Nombre Alumno</td><td>Tramo</td><td>Valor Tramo</td></tr>'
            .'<tr><td>202605</td><td>6830</td><td>110</td><td>ENSEÑANZA BÁSICA</td><td>7</td><td>7° BÁSICO</td><td>11.111.111-1</td><td>Alumno Uno</td><td>1</td><td>$ 100</td></tr>'
            .'<tr><td>202605</td><td>6830</td><td>310</td><td>ENSEÑANZA MEDIA</td><td>1</td><td>1° MEDIO H/C</td><td>22.222.222-2</td><td>Alumno Dos</td><td>2</td><td>$ 200</td></tr>'
            .'<tr><td colspan="9">Total</td><td>$ 300</td></tr></table></body></html>';
    }

    private function schoolBonusHtml(): string
    {
        $header = '<tr><th>Periodo</th><th>Rbd</th><th>Rut Trabajador</th><th>Nombre</th><th>Tipo Persona</th>'
            .'<th>N° Horas</th><th>Tramo</th><th>Tramo Mayor</th><th>RutCarga</th><th>NombreCarga</th>'
            .'<th>Monto por BEscolar</th><th>Monto por Adicional</th></tr>';
        $rows = [
            ['202606', '6830', '11111111-1', 'Trabajador Uno', 'DOCENTE', '44', '1', '1', '22.222.222-2', 'Carga Uno', '$ 50', '$ 10'],
            ['202606', '6830', '33333333-3', 'Trabajador Dos', 'ASISTENTE', '40', '2', '2', '44.444.444-4', 'Carga Dos', '$ 50', '$ 10'],
        ];
        $body = collect($rows)
            ->map(fn (array $row): string => '<tr><td>'.implode('</td><td>', $row).'</td></tr>')
            ->implode('');

        return '<html><body><div>Lista Trabajadores - Bono Escolar Cuota 2</div>'
            .'<div>Establecimiento: 6830 - Colegio</div><div>MES PAGO: JUNIO 2026</div>'
            .'<table>'.$header.$body.'<tr><td colspan="10">Total</td><td>$ 100</td><td>$ 20</td></tr></table></body></html>';
    }

    private function amountForLevelType(AccountingSubsidySettlement $settlement, string $type): float
    {
        $effectiveLineIds = $settlement->lines
            ->where('informative', false)
            ->pluck('id');

        return (float) $settlement->allocations
            ->filter(fn ($allocation): bool => $effectiveLineIds->contains($allocation->line_id)
                && $allocation->educationLevel?->type === $type)
            ->sum('amount');
    }
}
