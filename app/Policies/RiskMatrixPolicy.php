<?php

namespace App\Policies;

use App\Enums\RiskPrevention\RiskMatrixStatus;
use App\Models\RiskPrevention\RiskMatrix;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Models\User;

class RiskMatrixPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('risk-matrix.view');
    }

    public function view(User $user, RiskMatrix|RiskMatrixVersion $model): bool
    {
        return $user->hasPermission('risk-matrix.view') && $this->inInstitution($model);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('risk-matrix.create');
    }

    public function update(User $user, RiskMatrixVersion $version): bool
    {
        return $user->hasPermission('risk-matrix.update') && $version->isEditable() && $this->inInstitution($version);
    }

    public function delete(User $user, RiskMatrixVersion $version): bool
    {
        return $user->hasPermission('risk-matrix.delete-draft') && $version->status === RiskMatrixStatus::Draft && $this->inInstitution($version);
    }

    public function createVersion(User $user, RiskMatrix $matrix): bool
    {
        return $user->hasPermission('risk-matrix.create-version') && $this->inInstitution($matrix);
    }

    public function submit(User $user, RiskMatrixVersion $version): bool
    {
        return $user->hasPermission('risk-matrix.submit') && $version->isEditable() && $this->inInstitution($version);
    }

    public function review(User $user, RiskMatrixVersion $version): bool
    {
        return $user->hasPermission('risk-matrix.review') && $this->inInstitution($version);
    }

    public function approve(User $user, RiskMatrixVersion $version): bool
    {
        return $user->hasPermission('risk-matrix.approve') && $this->inInstitution($version);
    }

    public function archive(User $user, RiskMatrixVersion $version): bool
    {
        return $user->hasPermission('risk-matrix.archive') && $this->inInstitution($version);
    }

    public function export(User $user, RiskMatrixVersion $version): bool
    {
        return $user->hasPermission('risk-matrix.export') && $this->inInstitution($version);
    }

    private function inInstitution(RiskMatrix|RiskMatrixVersion $model): bool
    {
        $matrix = $model instanceof RiskMatrix ? $model : $model->matrix;

        return $matrix->company_key === config('risk_matrix.company.key', 'institution');
    }
}
