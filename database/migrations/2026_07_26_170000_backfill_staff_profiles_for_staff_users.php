<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('users')
                ->where('user_type', 'staff')
                ->whereNull('staff_id')
                ->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        $email = mb_strtolower(trim((string) $user->email));
                        $staff = $email !== ''
                            ? DB::table('staff')
                                ->whereRaw('LOWER(institutional_email) = ?', [$email])
                                ->first()
                            : null;

                        if ($staff) {
                            $linkedToAnotherUser = DB::table('users')
                                ->where('staff_id', $staff->id)
                                ->where('id', '!=', $user->id)
                                ->exists();

                            if ($linkedToAnotherUser) {
                                continue;
                            }

                            $staffId = $staff->id;
                        } else {
                            $staffId = DB::table('staff')->insertGetId([
                                'full_name' => trim((string) $user->name) ?: $email,
                                'rut' => null,
                                'institutional_email' => $email ?: null,
                                'cargo_id' => $user->cargo_id,
                                'status' => $user->active ? 'activo' : 'inactivo',
                                'active' => (bool) $user->active,
                                'created_by' => null,
                                'updated_by' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('users')
                            ->where('id', $user->id)
                            ->whereNull('staff_id')
                            ->update([
                                'staff_id' => $staffId,
                                'updated_at' => now(),
                            ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Las fichas creadas pueden recibir datos laborales después del despliegue.
        // No se eliminan ni se desvinculan registros al revertir.
    }
};
