<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoleSuperAdminUserSeeder extends Seeder
{
    use PreventsProductionSeeding;

    private const PASSWORD = 'ADMIN';
    private const DOMAIN = 'cnscgestion.cl';

    public function run(): void
    {
        $this->preventProductionSeeding();
        Role::query()
            ->where('active', true)
            ->orderBy('slug')
            ->get()
            ->each(function (Role $role): void {
                $user = User::query()->updateOrCreate(
                    ['email' => $this->emailForRole($role)],
                    [
                        'name' => "Superadmin {$role->name}",
                        'password' => Hash::make(self::PASSWORD),
                        'active' => true,
                        'user_type' => 'role_preview',
                    ],
                );

                if (!$user->email_verified_at) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $user->roles()->sync([$role->id]);
            });
    }

    private function emailForRole(Role $role): string
    {
        $slug = Str::lower(Str::ascii((string) $role->slug));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?: "rol_{$role->id}";
        $slug = trim($slug, '_') ?: "rol_{$role->id}";

        return "superadmin_{$slug}@" . self::DOMAIN;
    }
}
