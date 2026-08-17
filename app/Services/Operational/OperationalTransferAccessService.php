<?php

namespace App\Services\Operational;

use App\Models\Operational\OperationalTransferRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OperationalTransferAccessService
{
    public function visibleQuery(User $user): Builder
    {
        if ($this->canManage($user)) {
            return OperationalTransferRequest::query();
        }

        return OperationalTransferRequest::query()->where(function (Builder $query) use ($user): void {
            if ($user->staff_id) {
                $query->orWhere('requester_staff_id', $user->staff_id);
            }
            $query
                ->orWhere('requested_by_user_id', $user->id)
                ->orWhere('visor_user_id', $user->id);
        });
    }

    public function reviewableQuery(User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return OperationalTransferRequest::query()->where('approval_status', 'pendiente_visacion');
        }

        return OperationalTransferRequest::query()
            ->where('approval_status', 'pendiente_visacion')
            ->where('visor_user_id', $user->id);
    }

    public function canView(User $user, OperationalTransferRequest $request): bool
    {
        return $this->visibleQuery($user)->whereKey($request->id)->exists();
    }

    public function canVisorAct(User $user, OperationalTransferRequest $request): bool
    {
        return $request->approval_status === 'pendiente_visacion'
            && ($user->isSuperAdmin() || (
                $user->hasPermission('visar_traslados_operativos')
                && (int) $request->visor_user_id === (int) $user->id
            ));
    }

    public function canManage(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('gestionar_traslados_operativos');
    }
}
