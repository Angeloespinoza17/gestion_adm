<?php

namespace App\Services\RiskPrevention;

use App\Models\RiskPrevention\RiskAssessment;
use App\Models\RiskPrevention\RiskAuditLog;
use App\Models\RiskPrevention\RiskControl;
use App\Models\RiskPrevention\RiskEntry;
use App\Models\RiskPrevention\RiskEvidence;
use App\Models\RiskPrevention\RiskImportBatch;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixParticipation;
use App\Models\RiskPrevention\RiskMatrixProcess;
use App\Models\RiskPrevention\RiskMatrixReview;
use App\Models\RiskPrevention\RiskMatrixTask;
use App\Models\RiskPrevention\RiskMatrixVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RiskMatrixAuditService
{
    public function record(Model $model, string $action, array $old = [], array $new = [], ?string $reason = null, ?Request $request = null): RiskAuditLog
    {
        $matrix = $this->matrixFor($model);
        $request ??= request();

        return RiskAuditLog::query()->create([
            'company_key' => $matrix?->company_key ?? config('risk_matrix.company.key', 'institution'),
            'work_center_id' => $matrix?->work_center_id,
            'user_id' => $request?->user()?->id,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'old_values' => $this->sanitize($old),
            'new_values' => $this->sanitize($new),
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 500),
        ]);
    }

    private function matrixFor(Model $model): ?RiskMatrix
    {
        return match (true) {
            $model instanceof RiskMatrix => $model,
            $model instanceof RiskMatrixVersion => $model->matrix,
            $model instanceof RiskMatrixProcess => $model->version?->matrix,
            $model instanceof RiskMatrixTask => $model->process?->version?->matrix,
            $model instanceof RiskEntry => $model->task?->process?->version?->matrix,
            $model instanceof RiskControl, $model instanceof RiskAssessment => $model->risk?->task?->process?->version?->matrix,
            $model instanceof RiskEvidence, $model instanceof RiskMatrixParticipation, $model instanceof RiskMatrixReview => RiskMatrixVersion::query()->find($model->risk_matrix_version_id)?->matrix,
            $model instanceof RiskImportBatch && $model->target_matrix_id => RiskMatrix::query()->find($model->target_matrix_id),
            default => null,
        };
    }

    private function sanitize(array $values): array
    {
        foreach (['password', 'token', 'file_path', 'stored_path', 'snapshot_payload'] as $sensitive) {
            unset($values[$sensitive]);
        }

        return $values;
    }
}
