<?php

namespace App\Services\RiskPrevention;

use App\Enums\RiskPrevention\RiskMatrixStatus;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Models\RiskPrevention\RiskMethodology;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RiskMatrixService
{
    public function __construct(private readonly RiskMatrixAuditService $audit) {}

    /** @return array{matrix: RiskMatrix, version: RiskMatrixVersion} */
    public function create(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $methodology = isset($data['methodology_id'])
                ? RiskMethodology::query()->where('active', true)->findOrFail($data['methodology_id'])
                : RiskMethodology::query()->where('code', config('risk_matrix.methodology_code'))->where('active', true)->latest('version_number')->firstOrFail();

            $matrix = RiskMatrix::query()->create([
                'company_key' => $data['company_key'] ?? config('risk_matrix.company.key'),
                'company_name' => $data['company_name'] ?? config('risk_matrix.company.name'),
                'company_tax_id' => $data['company_tax_id'] ?? config('risk_matrix.company.tax_id'),
                'company_address' => $data['company_address'] ?? config('risk_matrix.company.address'),
                'work_center_id' => $data['work_center_id'] ?? null,
                'work_center_name_snapshot' => $data['work_center_name'] ?? null,
                'code' => $data['code'],
                'folio' => $data['folio'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $actor->id,
            ]);
            $matrix->loadMissing('workCenter');

            $version = RiskMatrixVersion::query()->create([
                'risk_matrix_id' => $matrix->id,
                'methodology_id' => $methodology->id,
                'version_number' => 1,
                'status' => RiskMatrixStatus::Draft,
                'company_name_snapshot' => $matrix->company_name,
                'company_tax_id_snapshot' => $matrix->company_tax_id,
                'company_address_snapshot' => $matrix->company_address,
                'economic_activity_code_snapshot' => $data['economic_activity_code'] ?? config('risk_matrix.company.economic_activity_code'),
                'work_center_name_snapshot' => $data['work_center_name'] ?? $matrix->workCenter?->name,
                'department_id' => $data['department_id'] ?? null,
                'prepared_on' => $data['prepared_on'] ?? now()->toDateString(),
                'updated_on' => $data['updated_on'] ?? now()->toDateString(),
                'total_workers' => $data['total_workers'] ?? null,
                'program_responsible_id' => $data['program_responsible_id'] ?? $actor->id,
                'program_responsible_name_snapshot' => $data['program_responsible_name'] ?? $actor->name,
                'program_responsible_position_snapshot' => $data['program_responsible_position'] ?? null,
                'legal_representative_id' => $data['legal_representative_id'] ?? null,
                'legal_representative_name_snapshot' => $data['legal_representative_name'] ?? null,
                'legal_representative_position_snapshot' => $data['legal_representative_position'] ?? null,
                'prepared_by' => $actor->id,
                'lock_version' => 1,
            ]);
            $this->audit->record($matrix, 'created', [], $matrix->only(['code', 'name', 'work_center_id']));

            return compact('matrix', 'version');
        });
    }

    public function update(RiskMatrixVersion $version, array $data, int $expectedLockVersion): RiskMatrixVersion
    {
        abort_unless($version->isEditable(), 423, 'La versión está bloqueada. Cree una nueva versión para modificarla.');
        if ($version->lock_version !== $expectedLockVersion) {
            abort(409, 'La matriz fue modificada por otra persona. Recargue antes de guardar.');
        }

        return DB::transaction(function () use ($version, $data) {
            $old = $version->getAttributes();
            $version->matrix->update(array_filter([
                'work_center_id' => $data['work_center_id'] ?? null,
                'work_center_name_snapshot' => $data['work_center_name'] ?? null,
                'folio' => $data['folio'] ?? null,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
            ], fn ($value) => $value !== null));
            $version->fill(collect($data)->only([
                'company_name_snapshot', 'company_tax_id_snapshot', 'company_address_snapshot', 'economic_activity_code_snapshot',
                'work_center_name_snapshot', 'department_id', 'prepared_on', 'total_workers', 'program_responsible_id',
                'program_responsible_name_snapshot', 'program_responsible_position_snapshot', 'review_reason', 'review_notes',
                'effective_from', 'next_review_at', 'legal_representative_id', 'legal_representative_name_snapshot',
                'legal_representative_position_snapshot',
            ])->all());
            $version->updated_on = now()->toDateString();
            $version->lock_version++;
            $version->save();
            $this->audit->record($version, 'updated', $old, $version->getAttributes(), $data['change_reason'] ?? null);

            return $version->fresh(['matrix.workCenter', 'methodology']);
        });
    }
}
