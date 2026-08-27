<?php

namespace App\Services\Remuneration\Payslips;

use App\Models\LibroDigital\School;
use App\Models\Remuneration\RemunerationPaymentProposal;
use App\Models\Remuneration\RemunerationPayslip;
use App\Models\Remuneration\RemunerationPayslipControl;
use App\Models\Remuneration\RemunerationPayslipIssue;
use App\Models\Remuneration\RemunerationPeriod;
use App\Models\User;
use App\Services\Remuneration\RemunerationAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayslipPaymentProposalService
{
    public function __construct(private readonly RemunerationAuditService $auditService) {}

    public function generate(School $school, RemunerationPeriod $period, User $actor, ?string $notes = null): RemunerationPaymentProposal
    {
        $payslips = RemunerationPayslip::query()
            ->with(['fundingSummaries.fundingSource:id,code,name', 'discounts.allocations.fundingSource:id,code,name'])
            ->where('school_id', $school->id)
            ->where('period_id', $period->id)
            ->where('is_current', true)
            ->where('status', 'importada')
            ->whereNotNull('staff_id')
            ->orderBy('id')
            ->get();

        if ($payslips->isEmpty()) {
            throw ValidationException::withMessages(['period_id' => 'No existen liquidaciones confirmadas para generar la propuesta.']);
        }

        $payslipIds = $payslips->pluck('id');
        if (RemunerationPayslipIssue::query()->whereIn('payslip_id', $payslipIds)->where('status', 'abierta')->where('severity', 'error')->exists()
            || RemunerationPayslipControl::query()->whereIn('payslip_id', $payslipIds)->where('status', 'error')->exists()) {
            throw ValidationException::withMessages(['period_id' => 'La propuesta no puede generarse mientras existan errores de importación o conciliación.']);
        }

        $sourceHash = hash('sha256', $payslips->map(fn (RemunerationPayslip $payslip): string => implode(':', [$payslip->id, $payslip->version, $payslip->net_amount]))->implode('|'));
        $existing = RemunerationPaymentProposal::query()
            ->where('school_id', $school->id)
            ->where('period_id', $period->id)
            ->whereIn('status', ['borrador', 'confirmada'])
            ->first();
        if ($existing) {
            throw ValidationException::withMessages(['period_id' => 'Ya existe una propuesta de pago activa para este establecimiento y período.']);
        }

        $proposal = DB::transaction(function () use ($school, $period, $actor, $notes, $payslips, $sourceHash) {
            $fundingTotals = [];
            $proposal = RemunerationPaymentProposal::query()->create([
                'school_id' => $school->id,
                'period_id' => $period->id,
                'status' => 'borrador',
                'version' => 1,
                'net_total' => $payslips->sum('net_amount'),
                'distributed_total' => $payslips->sum(fn (RemunerationPayslip $payslip): int => (int) $payslip->fundingSummaries->sum('net_amount')),
                'employer_contribution_total' => $payslips->sum('employer_contributions'),
                'total_cost' => $payslips->sum('total_cost'),
                'source_hash' => $sourceHash,
                'notes' => $notes,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach ($payslips as $payslip) {
                $item = $proposal->items()->create([
                    'payslip_id' => $payslip->id,
                    'staff_id' => $payslip->staff_id,
                    'payment_amount' => $payslip->net_amount,
                    'status' => 'incluido',
                    'metadata' => ['payslip_version' => $payslip->version],
                ]);
                foreach ($payslip->fundingSummaries as $summary) {
                    $item->allocations()->create([
                        'funding_source_id' => $summary->funding_source_id,
                        'allocation_type' => 'sueldo_liquido',
                        'amount' => $summary->net_amount,
                        'source_detail' => [
                            'gross_earnings' => $summary->gross_earnings,
                            'legal_deductions' => $summary->legal_deductions,
                            'other_deductions' => $summary->other_deductions,
                        ],
                    ]);
                    $code = $summary->fundingSource?->code ?: (string) $summary->funding_source_id;
                    $fundingTotals[$code] ??= [
                        'funding_source_id' => $summary->funding_source_id,
                        'name' => $summary->fundingSource?->name,
                        'net_salaries' => 0,
                        'previred' => 0,
                        'form_29' => 0,
                        'other_withholdings' => 0,
                        'employer_contributions' => 0,
                        'total_cost' => 0,
                    ];
                    $fundingTotals[$code]['net_salaries'] += $summary->net_amount;
                    $fundingTotals[$code]['employer_contributions'] += $summary->employer_contributions;
                    $fundingTotals[$code]['total_cost'] += $summary->total_cost;
                }

                foreach ($payslip->discounts as $discount) {
                    foreach ($discount->allocations as $allocation) {
                        $code = $allocation->fundingSource?->code ?: (string) $allocation->funding_source_id;
                        $destination = match ($discount->payment_destination) {
                            'Previred' => 'previred',
                            'Formulario 29' => 'form_29',
                            default => 'other_withholdings',
                        };
                        if (isset($fundingTotals[$code])) {
                            $fundingTotals[$code][$destination] += $allocation->assigned_amount;
                        }
                    }
                }
            }

            $proposal->update(['summary' => ['funding_sources' => $fundingTotals]]);

            return $proposal;
        });

        $this->auditService->log('generar_propuesta_pago_remuneraciones', $proposal, $actor, [], ['status' => 'borrador', 'net_total' => $proposal->net_total], 'Propuesta revisable; no genera movimientos presupuestarios ni pagos.', null, ['period_id' => $period->id, 'school_id' => $school->id]);

        return $proposal->fresh(['school:id,name,rbd', 'period:id,name,year,month', 'items.allocations']);
    }

    public function confirm(RemunerationPaymentProposal $proposal, User $actor): RemunerationPaymentProposal
    {
        if ($proposal->status !== 'borrador') {
            throw ValidationException::withMessages(['proposal' => 'Solo una propuesta en borrador puede confirmarse.']);
        }
        if ($proposal->net_total !== $proposal->distributed_total) {
            throw ValidationException::withMessages(['proposal' => 'La distribución por subvención no coincide con el líquido a pagar.']);
        }

        $proposal->update([
            'status' => 'confirmada',
            'confirmed_at' => now(),
            'confirmed_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $this->auditService->log('confirmar_propuesta_pago_remuneraciones', $proposal, $actor, ['status' => 'borrador'], ['status' => 'confirmada'], 'Confirmación explícita sin contabilización automática.');

        return $proposal->fresh(['items.allocations']);
    }
}
