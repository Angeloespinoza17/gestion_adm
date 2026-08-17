<?php

namespace App\Policies;

use App\Models\Operational\OperationalTransferRequest;
use App\Models\User;
use App\Services\Operational\OperationalTransferAccessService;

class OperationalTransferRequestPolicy
{
    public function __construct(private readonly OperationalTransferAccessService $accessService) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ver_traslados_operativos')
            || $user->hasPermission('visar_traslados_operativos')
            || $this->accessService->canManage($user);
    }

    public function view(User $user, OperationalTransferRequest $request): bool
    {
        return $this->accessService->canView($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('solicitar_traslados_operativos') || $this->accessService->canManage($user);
    }

    public function update(User $user, OperationalTransferRequest $request): bool
    {
        if ($this->accessService->canManage($user)) {
            return true;
        }

        return $request->isEditable()
            && ((int) $request->requested_by_user_id === (int) $user->id
                || ((int) $request->requester_staff_id === (int) $user->staff_id));
    }

    public function submit(User $user, OperationalTransferRequest $request): bool
    {
        return $this->update($user, $request);
    }

    public function visorAct(User $user, OperationalTransferRequest $request): bool
    {
        return $this->accessService->canVisorAct($user, $request);
    }

    public function manage(User $user): bool
    {
        return $this->accessService->canManage($user);
    }

    public function cancel(User $user, OperationalTransferRequest $request): bool
    {
        if ($this->accessService->canManage($user)) {
            return true;
        }

        return in_array($request->approval_status, ['borrador', 'observado', 'pendiente_visacion', 'pendiente_administracion', 'aprobado'], true)
            && ! in_array($request->service_status, ['confirmado', 'ejecutado', 'cancelado'], true)
            && ((int) $request->requested_by_user_id === (int) $user->id
                || ((int) $request->requester_staff_id === (int) $user->staff_id));
    }
}
