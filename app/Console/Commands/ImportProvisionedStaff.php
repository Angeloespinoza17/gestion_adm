<?php

namespace App\Console\Commands;

use App\Models\Cargo;
use App\Models\Commune;
use App\Models\Permission;
use App\Models\Region;
use App\Models\Role;
use App\Models\Staff;
use App\Models\SystemModule;
use App\Models\User;
use App\Support\DateInput;
use App\Support\Rut;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportProvisionedStaff extends Command
{
    protected $signature = 'staff:import-provisioned
        {json : Archivo JSON generado desde la planilla validada}
        {--password= : Clave temporal para todas las cuentas}
        {--role=funcionario_temporal_inicio : Rol aditivo que se asignará a las cuentas}
        {--actor-email= : Cuenta que quedará registrada como responsable}
        {--dry-run : Valida y simula toda la importación sin persistir cambios}';

    protected $description = 'Importa fichas y cuentas de funcionarios sin eliminar registros ni modificar departamentos.';

    /** @var array<string, Region> */
    private array $regions = [];

    /** @var array<string, Commune> */
    private array $communes = [];

    public function handle(): int
    {
        try {
            $path = $this->resolvePath((string) $this->argument('json'));
            $password = (string) ($this->option('password') ?: env('STAFF_IMPORT_PASSWORD', ''));
            $roleSlug = trim((string) $this->option('role'));
            $dryRun = (bool) $this->option('dry-run');

            if (mb_strlen($password) < 8) {
                throw new RuntimeException('La clave temporal debe tener al menos 8 caracteres.');
            }

            if ($roleSlug !== Role::TEMPORARY_HOME_ONLY_SLUG) {
                throw new RuntimeException('Esta importación solo admite el rol temporal de acceso a Inicio.');
            }

            $records = $this->readRecords($path);
            $this->loadLocations();
            $actorId = $this->actorId();
            $summary = $this->emptySummary(count($records));

            DB::beginTransaction();

            try {
                $role = $this->temporaryHomeRole($roleSlug);

                foreach ($records as $record) {
                    $cargo = $this->resolveCargo($record['cargo']);
                    [$staff, $staffAction] = $this->upsertStaff($record, $cargo, $actorId);
                    [$user, $userAction] = $this->upsertUser($record, $staff, $cargo, $password);

                    if (! $user->roles()->whereKey($role->id)->exists()) {
                        $user->roles()->attach($role->id);
                        $summary['role_assignments']++;
                    }

                    $summary["staff_{$staffAction}"]++;
                    $summary["users_{$userAction}"]++;
                    $summary['passwords_reset']++;

                    if ($record['generated_account_email']) {
                        $summary['generated_account_emails']++;
                    }
                }

                if ($dryRun) {
                    DB::rollBack();
                } else {
                    DB::commit();
                }
            } catch (Throwable $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                throw $exception;
            }

            $this->renderSummary($summary, $dryRun);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRecords(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! array_is_list($decoded) || $decoded === []) {
            throw new RuntimeException('El archivo debe contener una lista JSON no vacía.');
        }

        if (count($decoded) > 2000) {
            throw new RuntimeException('La importación supera el máximo de 2.000 funcionarios.');
        }

        $records = [];
        $ruts = [];
        $emails = [];
        $errors = [];

        foreach ($decoded as $index => $row) {
            if (! is_array($row)) {
                $errors[] = 'La entrada '.($index + 1).' no es un objeto válido.';

                continue;
            }

            $record = $this->normalizeRecord($row, $index + 1);
            $validator = Validator::make($record, $this->rules());

            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Fila {$record['source_row']}: {$message}";
            }

            if ($record['rut'] && isset($ruts[$record['rut']])) {
                $errors[] = "Fila {$record['source_row']}: el RUT se repite con la fila {$ruts[$record['rut']]}.";
            }

            if ($record['account_email'] && isset($emails[$record['account_email']])) {
                $errors[] = "Fila {$record['source_row']}: el correo de acceso se repite con la fila {$emails[$record['account_email']]}.";
            }

            if ($record['rut']) {
                $ruts[$record['rut']] = $record['source_row'];
            }

            if ($record['account_email']) {
                $emails[$record['account_email']] = $record['source_row'];
            }

            $records[] = $record;
        }

        if ($errors !== []) {
            throw new RuntimeException(
                "La importación contiene errores:\n- ".implode("\n- ", array_slice($errors, 0, 50))
            );
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRecord(array $row, int $fallbackRow): array
    {
        $fullName = $this->nullableString($row['full_name'] ?? null);
        $rut = Rut::normalize($this->nullableString($row['rut'] ?? null));
        $institutionalEmail = $this->email($row['institutional_email'] ?? null);
        $accountEmail = $this->email($row['account_email'] ?? null);

        return [
            'source_row' => (int) ($row['source_row'] ?? $fallbackRow),
            'full_name' => $fullName ? Str::title(mb_strtolower($fullName)) : null,
            'rut' => $rut,
            'birth_date' => DateInput::normalize($row['birth_date'] ?? null),
            'institutional_email' => $institutionalEmail,
            'personal_email' => $this->email($row['personal_email'] ?? null),
            'account_email' => $accountEmail,
            'generated_account_email' => (bool) ($row['generated_account_email'] ?? false),
            'phone' => $this->nullableString($row['phone'] ?? null),
            'address' => $this->nullableString($row['address'] ?? null),
            'region' => $this->nullableString($row['region'] ?? null),
            'commune' => $this->nullableString($row['commune'] ?? null),
            'cargo' => $this->nullableString($row['cargo'] ?? null),
            'contract_type' => $this->nullableString($row['contract_type'] ?? null),
            'start_date' => DateInput::normalize($row['start_date'] ?? null),
            'end_date' => DateInput::normalize($row['end_date'] ?? null),
            'status' => $this->nullableString($row['status'] ?? null),
            'workday' => $this->nullableString($row['workday'] ?? null),
            'contract_hours' => $this->nullableNumber($row['contract_hours'] ?? null),
            'professional_title' => $this->nullableString($row['professional_title'] ?? null),
            'specialty' => $this->nullableString($row['specialty'] ?? null),
            'professional_registration' => $this->nullableString($row['professional_registration'] ?? null),
            'internal_notes' => $this->nullableString($row['internal_notes'] ?? null),
            'active' => true,
            'can_receive_maintenance_orders' => false,
            'maintenance_role' => null,
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'source_row' => ['required', 'integer', 'min:1'],
            'full_name' => ['required', 'string', 'max:255'],
            'rut' => [
                'required',
                'string',
                'max:20',
                fn (string $attribute, mixed $value, callable $fail) => Rut::isValid((string) $value)
                    ?: $fail('El RUT no es válido.'),
            ],
            'birth_date' => ['nullable', 'date'],
            'institutional_email' => ['nullable', 'email:rfc', 'max:255'],
            'personal_email' => ['nullable', 'email:rfc', 'max:255'],
            'account_email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:191'],
            'commune' => ['nullable', 'string', 'max:191'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'contract_type' => ['nullable', 'in:indefinido,plazo_fijo,honorarios,reemplazo,part_time'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:activo,inactivo,reemplazo,licencia,desvinculado'],
            'workday' => ['nullable', 'in:completa,parcial,por_horas,turnos'],
            'contract_hours' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'professional_title' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'professional_registration' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }

    private function temporaryHomeRole(string $roleSlug): Role
    {
        $permission = Permission::query()
            ->where('slug', 'ver_dashboard')
            ->where('active', true)
            ->first();
        $module = SystemModule::query()
            ->where('slug', 'dashboard')
            ->where('active', true)
            ->first();

        if (! $permission || ! $module) {
            throw new RuntimeException('No existen el permiso y módulo activos requeridos para Inicio.');
        }

        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => 'Funcionario temporal · Solo Inicio',
                'description' => 'Acceso temporal limitado al Inicio del sistema.',
                'active' => true,
            ],
        );

        if (
            $role->permissions()->whereKeyNot($permission->id)->exists()
            || $role->modules()->whereKeyNot($module->id)->exists()
        ) {
            throw new RuntimeException('El rol temporal existente contiene accesos adicionales y no fue modificado.');
        }

        $role->forceFill([
            'name' => 'Funcionario temporal · Solo Inicio',
            'description' => 'Acceso temporal limitado al Inicio del sistema.',
            'active' => true,
        ])->save();
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $role->modules()->syncWithoutDetaching([$module->id]);

        return $role;
    }

    private function resolveCargo(?string $name): ?Cargo
    {
        if (! $name) {
            return null;
        }

        $normalizedName = Str::title(mb_strtolower($name));
        $slug = Str::slug($name, '_');
        $cargo = Cargo::query()
            ->where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
            ->first();

        if ($cargo) {
            if (! $cargo->active) {
                $cargo->update(['active' => true]);
            }

            return $cargo;
        }

        return Cargo::query()->create([
            'name' => $normalizedName,
            'slug' => $slug,
            'description' => 'Creado desde importación validada de funcionarios.',
            'active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0:Staff,1:string}
     */
    private function upsertStaff(array $record, ?Cargo $cargo, ?int $actorId): array
    {
        $byRut = Staff::query()->where('rut', $record['rut'])->first();
        $byEmail = $record['institutional_email']
            ? Staff::query()->whereRaw('LOWER(institutional_email) = ?', [$record['institutional_email']])->first()
            : null;

        if ($byRut && $byEmail && $byRut->id !== $byEmail->id) {
            throw new RuntimeException("Fila {$record['source_row']}: RUT y correo pertenecen a fichas distintas.");
        }

        $staff = $byRut ?: $byEmail;
        $payload = $this->staffPayload($record, $cargo, $actorId);

        if (! $staff) {
            $payload['status'] ??= 'activo';
            $payload['created_by'] = $actorId;

            return [Staff::query()->create($payload), 'created'];
        }

        $changes = [];
        foreach ($payload as $field => $value) {
            if ($value !== null && $staff->{$field} != $value) {
                $changes[$field] = $value;
            }
        }

        if ($changes === []) {
            return [$staff, 'unchanged'];
        }

        $staff->update($changes);

        return [$staff->fresh(), 'updated'];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function staffPayload(array $record, ?Cargo $cargo, ?int $actorId): array
    {
        $region = $record['region'] ? ($this->regions[$this->lookupKey($record['region'])] ?? null) : null;
        $commune = $record['commune'] ? ($this->communes[$this->lookupKey($record['commune'])] ?? null) : null;

        return [
            'full_name' => $record['full_name'],
            'rut' => $record['rut'],
            'birth_date' => $record['birth_date'],
            'institutional_email' => $record['institutional_email'],
            'personal_email' => $record['personal_email'],
            'phone' => $record['phone'],
            'address' => $record['address'],
            'region' => $region?->short_name ?: $record['region'],
            'region_id' => $region?->id,
            'commune' => $commune?->name ?: $record['commune'],
            'commune_id' => $commune?->id,
            'cargo_id' => $cargo?->id,
            'contract_type' => $record['contract_type'],
            'start_date' => $record['start_date'],
            'end_date' => $record['contract_type'] === 'indefinido' ? null : $record['end_date'],
            'status' => $record['status'],
            'workday' => $record['workday'],
            'contract_hours' => $record['contract_hours'],
            'professional_title' => $record['professional_title'],
            'specialty' => $record['specialty'],
            'professional_registration' => $record['professional_registration'],
            'internal_notes' => $record['internal_notes'],
            'active' => true,
            'can_receive_maintenance_orders' => false,
            'maintenance_role' => null,
            'updated_by' => $actorId,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0:User,1:string}
     */
    private function upsertUser(
        array $record,
        Staff $staff,
        ?Cargo $cargo,
        string $password,
    ): array {
        $linkedUser = User::query()->where('staff_id', $staff->id)->first();
        $accountEmail = $record['institutional_email']
            ?: $staff->institutional_email
            ?: $linkedUser?->email
            ?: $record['account_email'];
        $emailUser = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($accountEmail)])->first();

        if ($linkedUser && $emailUser && $linkedUser->id !== $emailUser->id) {
            throw new RuntimeException("Fila {$record['source_row']}: el correo de acceso pertenece a otra cuenta.");
        }

        $user = $linkedUser ?: $emailUser;
        if ($user && $user->staff_id && (int) $user->staff_id !== (int) $staff->id) {
            throw new RuntimeException("Fila {$record['source_row']}: la cuenta ya está asociada a otra ficha.");
        }

        $payload = [
            'name' => $record['full_name'],
            'email' => mb_strtolower($accountEmail),
            'password' => Hash::make($password),
            'user_type' => 'staff',
            'active' => true,
            'staff_id' => $staff->id,
            'cargo_id' => $cargo?->id ?: $staff->cargo_id,
        ];

        if (! $user) {
            $user = new User($payload);
            $user->email_verified_at = now();
            $user->save();

            return [$user, 'created'];
        }

        $user->fill($payload);
        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
        }
        $user->save();

        return [$user->fresh(), 'updated'];
    }

    private function loadLocations(): void
    {
        foreach (Region::query()->get() as $region) {
            foreach (array_filter([$region->name, $region->short_name, $region->code, $region->abbreviation]) as $value) {
                $this->regions[$this->lookupKey((string) $value)] = $region;
            }
        }

        foreach (Commune::query()->get() as $commune) {
            $this->communes[$this->lookupKey($commune->name)] = $commune;
        }
    }

    private function actorId(): ?int
    {
        $email = $this->email($this->option('actor-email'));
        if ($email) {
            $actor = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if (! $actor) {
                throw new RuntimeException('No se encontró la cuenta indicada como responsable.');
            }

            return $actor->id;
        }

        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super_admin'))
            ->orderBy('id')
            ->value('id');
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(int $processed): array
    {
        return [
            'processed' => $processed,
            'staff_created' => 0,
            'staff_updated' => 0,
            'staff_unchanged' => 0,
            'users_created' => 0,
            'users_updated' => 0,
            'users_unchanged' => 0,
            'passwords_reset' => 0,
            'role_assignments' => 0,
            'generated_account_emails' => 0,
        ];
    }

    /** @param array<string, int> $summary */
    private function renderSummary(array $summary, bool $dryRun): void
    {
        $this->newLine();
        $this->info($dryRun ? 'Simulación completada; no se guardó ningún cambio.' : 'Importación completada.');
        $this->table(
            [
                'Procesados',
                'Fichas nuevas',
                'Fichas actualizadas',
                'Fichas sin cambios',
                'Usuarios nuevos',
                'Usuarios actualizados',
                'Claves restablecidas',
                'Roles agregados',
                'Correos internos',
            ],
            [[
                $summary['processed'],
                $summary['staff_created'],
                $summary['staff_updated'],
                $summary['staff_unchanged'],
                $summary['users_created'],
                $summary['users_updated'],
                $summary['passwords_reset'],
                $summary['role_assignments'],
                $summary['generated_account_emails'],
            ]],
        );
        $this->line('Departamentos creados, asociados o eliminados: 0.');
        $this->line('Roles previos eliminados: 0.');
    }

    private function resolvePath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $candidate = base_path($path);
        if (is_file($candidate)) {
            return $candidate;
        }

        throw new RuntimeException("No existe el archivo JSON: {$path}");
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableNumber(mixed $value): int|float|null
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function email(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value ? mb_strtolower($value) : null;
    }

    private function lookupKey(string $value): string
    {
        return trim((string) preg_replace(
            '/[^a-z0-9]+/',
            '_',
            Str::ascii(mb_strtolower(trim($value))),
        ), '_');
    }
}
