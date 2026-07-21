<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\Rut;
use Database\Seeders\Support\PreventsProductionSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use PreventsProductionSeeding;

    /**
     * @var array<string, array<int, string>>
     */
    private array $knownStaffRoles = [
        'patricia.lopez@cnscgestion.local' => ['administrador'],
        'carolina.munoz@cnscgestion.local' => ['direccion'],
        'marcelo.rojas@cnscgestion.local' => ['rrhh'],
        'paula.vargas@cnscgestion.local' => ['coordinador_academico'],
        'sergio.torres@cnscgestion.local' => ['inspectoria'],
        'laura.diaz@cnscgestion.local' => ['porteria'],
        'jose.campos@cnscgestion.local' => ['nochero'],
        'ricardo.fuentes@cnscgestion.local' => ['encargado_mantencion'],
        'nicolas.perez@cnscgestion.local' => ['prevencion_riesgos'],
        'camila.soto@cnscgestion.local' => ['psicologo'],
        'ivonne.reyes@cnscgestion.local' => ['enfermeria'],
        'andrea.medina@cnscgestion.local' => ['docente'],
        'daniela.castillo@cnscgestion.local' => ['docente'],
    ];

    public function run(): void
    {
        $this->preventProductionSeeding();
        $this->seedSuperAdminUser();
        $this->syncStaffUsers();
    }

    private function seedSuperAdminUser(): void
    {
        $email = mb_strtolower(trim((string) env('SUPER_ADMIN_EMAIL', 'superadmin@cnscgestion.cl')));
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'ADMIN');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($password),
                'active' => true,
            ],
        );

        $superAdminRole = Role::query()->firstWhere('slug', 'super_admin');
        if ($superAdminRole) {
            $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }
    }

    private function syncStaffUsers(): void
    {
        Staff::query()
            ->with('cargo:id,slug')
            ->whereNotNull('institutional_email')
            ->whereNotNull('rut')
            ->orderBy('full_name')
            ->get()
            ->each(function (Staff $staff): void {
                $this->syncStaffUser($staff);
            });
    }

    private function syncStaffUser(Staff $staff): void
    {
        $email = mb_strtolower(trim((string) $staff->institutional_email));
        $rut = Rut::normalize((string) $staff->rut);

        if ($email === '' || !$rut) {
            $this->command?->warn(sprintf(
                'Omitido %s: falta correo institucional o RUT valido.',
                $staff->full_name ?: "staff #{$staff->id}",
            ));
            return;
        }

        $user = User::query()->where('staff_id', $staff->id)->first()
            ?: User::query()->where('email', $email)->first()
            ?: new User();

        if ($user->exists && $user->staff_id && (int) $user->staff_id !== (int) $staff->id) {
            $this->command?->warn(sprintf(
                'Omitido %s: el correo %s ya esta asociado a otro funcionario.',
                $staff->full_name,
                $email,
            ));
            return;
        }

        $user->fill([
            'name' => $staff->full_name,
            'email' => $email,
            'password' => Hash::make($rut),
            'user_type' => 'staff',
            'active' => (bool) $staff->active,
            'staff_id' => $staff->id,
            'cargo_id' => $staff->cargo_id,
        ]);
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        $roleIds = Role::query()
            ->whereIn('slug', $this->roleSlugsForStaff($staff, $email))
            ->pluck('id')
            ->all();

        if ($roleIds !== []) {
            $user->roles()->syncWithoutDetaching($roleIds);
        }
    }

    /**
     * @return array<int, string>
     */
    private function roleSlugsForStaff(Staff $staff, string $email): array
    {
        $slugs = $this->knownStaffRoles[$email] ?? [];
        $cargoSlug = $staff->cargo?->slug;

        if ($slugs === [] && $cargoSlug) {
            $slugs[] = $cargoSlug;
        }

        return array_values(array_unique($slugs));
    }
}
