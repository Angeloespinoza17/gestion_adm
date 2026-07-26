<?php

namespace App\Services\Staff;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffProfileLinker
{
    public function ensureLinked(User $user, ?int $actorId = null): Staff
    {
        return DB::transaction(function () use ($user, $actorId): Staff {
            $user->refresh();

            if ($user->staff_id) {
                $linkedStaff = Staff::query()->find($user->staff_id);

                if ($linkedStaff) {
                    return $linkedStaff;
                }
            }

            $email = mb_strtolower(trim((string) $user->email));
            $staff = $email !== ''
                ? Staff::query()
                    ->whereRaw('LOWER(institutional_email) = ?', [$email])
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($staff) {
                $linkedToAnotherUser = User::query()
                    ->where('staff_id', $staff->id)
                    ->where('id', '!=', $user->id)
                    ->exists();

                if ($linkedToAnotherUser) {
                    throw ValidationException::withMessages([
                        'user_type' => 'La ficha laboral encontrada para este correo ya está vinculada a otra cuenta.',
                    ]);
                }
            } else {
                $staff = Staff::query()->create([
                    'full_name' => trim((string) $user->name) ?: $email,
                    'institutional_email' => $email ?: null,
                    'cargo_id' => $user->cargo_id,
                    'status' => $user->active ? 'activo' : 'inactivo',
                    'active' => (bool) $user->active,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            }

            $user->forceFill([
                'user_type' => 'staff',
                'staff_id' => $staff->id,
            ])->save();

            return $staff;
        });
    }
}
