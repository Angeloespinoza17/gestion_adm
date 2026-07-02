<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;
use Throwable;

class ImportActiveWorkspaceUsers extends Command
{
    protected $signature = 'users:import-workspace
        {csv : Ruta absoluta o relativa del CSV}
        {--only-active : Importa solo filas con estado Active}
        {--default-password= : Contrasena temporal para usuarios nuevos}
        {--dry-run : Simula la importacion sin persistir cambios}';

    protected $description = 'Importa usuarios y funcionarios desde un CSV de Google Workspace.';

    public function handle(): int
    {
        $csvPath = $this->resolveCsvPath((string) $this->argument('csv'));
        $onlyActive = (bool) $this->option('only-active');
        $dryRun = (bool) $this->option('dry-run');
        $defaultPassword = $this->option('default-password');

        if (!$defaultPassword) {
            $defaultPassword = Str::password(16);
            $this->warn('No se indico --default-password. Se genero una clave aleatoria para usuarios nuevos.');
            $this->line('Clave temporal generada: ' . $defaultPassword);
        }

        $file = new SplFileObject($csvPath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = $file->fgetcsv();
        if (!is_array($headers)) {
            $this->error('No fue posible leer el encabezado del CSV.');

            return self::FAILURE;
        }

        $headerMap = $this->buildHeaderMap($headers);
        $requiredHeaders = [
            'first name [required]',
            'last name [required]',
            'email address [required]',
            'status [read only]',
        ];

        foreach ($requiredHeaders as $requiredHeader) {
            if (!array_key_exists($requiredHeader, $headerMap)) {
                $this->error("Falta la columna requerida: {$requiredHeader}");

                return self::FAILURE;
            }
        }

        $summary = [
            'processed' => 0,
            'skipped' => 0,
            'users_created' => 0,
            'users_updated' => 0,
            'staff_created' => 0,
            'staff_updated' => 0,
            'errors' => 0,
        ];

        $errors = [];
        $lineNumber = 1;

        while (!$file->eof()) {
            $lineNumber++;
            $row = $file->fgetcsv();

            if (!$this->hasCsvContent($row)) {
                continue;
            }

            $record = $this->mapRow($row, $headerMap);
            $email = $this->normalizeEmail($record['email address [required]'] ?? null);
            $status = $this->normalizeValue($record['status [read only]'] ?? null);
            $firstName = $this->normalizeNamePart($record['first name [required]'] ?? null);
            $lastName = $this->normalizeNamePart($record['last name [required]'] ?? null);

            if ($onlyActive && !in_array(mb_strtolower($status), ['active'], true)) {
                $summary['skipped']++;
                continue;
            }

            if (!$this->looksLikePersonRecord($firstName, $lastName)) {
                $summary['skipped']++;
                continue;
            }

            if ($email === '' || ($firstName === '' && $lastName === '')) {
                $summary['errors']++;
                $errors[] = "Linea {$lineNumber}: faltan datos minimos para importar.";
                continue;
            }

            $summary['processed']++;
            $fullName = trim($firstName . ' ' . $lastName);

            try {
                DB::transaction(function () use (
                    $dryRun,
                    $email,
                    $fullName,
                    $defaultPassword,
                    &$summary
                ) {
                    [$staff, $staffAction] = $this->upsertStaff($email, $fullName);
                    [$user, $userAction] = $this->upsertUser($email, $fullName, $staff, (string) $defaultPassword);

                    if ($staffAction === 'created') {
                        $summary['staff_created']++;
                    } elseif ($staffAction === 'updated') {
                        $summary['staff_updated']++;
                    }

                    if ($userAction === 'created') {
                        $summary['users_created']++;
                    } elseif ($userAction === 'updated') {
                        $summary['users_updated']++;
                    }

                    if ($dryRun) {
                        throw new DryRunRollbackException($user->email);
                    }
                });
            } catch (DryRunRollbackException) {
                continue;
            } catch (Throwable $exception) {
                $summary['errors']++;
                $errors[] = "Linea {$lineNumber} ({$email}): {$exception->getMessage()}";
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Simulacion completada.' : 'Importacion completada.');
        $this->table(
            ['procesadas', 'omitidas', 'usuarios nuevos', 'usuarios actualizados', 'funcionarios nuevos', 'funcionarios actualizados', 'errores'],
            [[
                $summary['processed'],
                $summary['skipped'],
                $summary['users_created'],
                $summary['users_updated'],
                $summary['staff_created'],
                $summary['staff_updated'],
                $summary['errors'],
            ]]
        );

        if (!empty($errors)) {
            $this->newLine();
            $this->warn('Detalle de errores:');
            foreach ($errors as $error) {
                $this->line('- ' . $error);
            }
        }

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: \App\Models\Staff, 1: string}
     */
    private function upsertStaff(string $email, string $fullName): array
    {
        $staff = Staff::query()
            ->where('institutional_email', $email)
            ->first();

        $action = 'unchanged';

        if (!$staff) {
            $staff = Staff::query()->create([
                'full_name' => $fullName,
                'institutional_email' => $email,
                'status' => 'activo',
                'active' => true,
            ]);

            return [$staff, 'created'];
        }

        $changes = [];

        if ($staff->full_name !== $fullName) {
            $changes['full_name'] = $fullName;
        }

        if ($staff->institutional_email !== $email) {
            $changes['institutional_email'] = $email;
        }

        if ($staff->status !== 'activo') {
            $changes['status'] = 'activo';
        }

        if (!$staff->active) {
            $changes['active'] = true;
        }

        if ($changes !== []) {
            $staff->update($changes);
            $action = 'updated';
        }

        return [$staff->fresh(), $action];
    }

    /**
     * @return array{0: \App\Models\User, 1: string}
     */
    private function upsertUser(string $email, string $fullName, Staff $staff, string $defaultPassword): array
    {
        $user = User::query()->where('email', $email)->first();
        $action = 'unchanged';

        if ($user) {
            if ($user->staff_id && (int) $user->staff_id !== (int) $staff->id) {
                throw new RuntimeException('El usuario ya esta vinculado a otro funcionario.');
            }

            $conflictingUser = User::query()
                ->where('staff_id', $staff->id)
                ->where('id', '!=', $user->id)
                ->first();

            if ($conflictingUser) {
                throw new RuntimeException("El funcionario ya esta vinculado al usuario {$conflictingUser->email}.");
            }

            $changes = [];

            if ($user->name !== $fullName) {
                $changes['name'] = $fullName;
            }

            if (!$user->active) {
                $changes['active'] = true;
            }

            if ($user->user_type !== 'staff') {
                $changes['user_type'] = 'staff';
            }

            if ((int) ($user->staff_id ?? 0) !== (int) $staff->id) {
                $changes['staff_id'] = $staff->id;
            }

            if ($changes !== []) {
                $user->update($changes);
                $action = 'updated';
            }

            return [$user->fresh(), $action];
        }

        $conflictingUser = User::query()->where('staff_id', $staff->id)->first();
        if ($conflictingUser) {
            throw new RuntimeException("El funcionario ya esta vinculado al usuario {$conflictingUser->email}.");
        }

        $user = User::query()->create([
            'name' => $fullName,
            'email' => $email,
            'password' => Hash::make($defaultPassword),
            'user_type' => 'staff',
            'active' => true,
            'staff_id' => $staff->id,
            'email_verified_at' => now(),
        ]);

        return [$user, 'created'];
    }

    /**
     * @param array<int, string|null|false> $headers
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headers): array
    {
        $headerMap = [];

        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeValue($header);
            if ($normalizedHeader === '') {
                continue;
            }

            $headerMap[mb_strtolower($normalizedHeader)] = $index;
        }

        return $headerMap;
    }

    /**
     * @param array<int, string|null|false> $row
     * @param array<string, int> $headerMap
     * @return array<string, string>
     */
    private function mapRow(array $row, array $headerMap): array
    {
        $mapped = [];

        foreach ($headerMap as $header => $index) {
            $mapped[$header] = $this->normalizeValue($row[$index] ?? null);
        }

        return $mapped;
    }

    /**
     * @param array<int, string|null|false>|false $row
     */
    private function hasCsvContent(array|false $row): bool
    {
        if ($row === false) {
            return false;
        }

        foreach ($row as $value) {
            if ($this->normalizeValue($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function resolveCsvPath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $candidate = base_path($path);
        if (is_file($candidate)) {
            return $candidate;
        }

        throw new RuntimeException("No existe el archivo CSV: {$path}");
    }

    private function normalizeEmail(mixed $value): string
    {
        return mb_strtolower($this->normalizeValue($value));
    }

    private function normalizeNamePart(mixed $value): string
    {
        $value = $this->normalizeValue($value);

        if ($value === '') {
            return '';
        }

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizeValue(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return $value;
    }

    private function looksLikePersonRecord(string $firstName, string $lastName): bool
    {
        $fullName = mb_strtolower(trim($firstName . ' ' . $lastName));

        if ($fullName === '') {
            return false;
        }

        if (preg_match('/\d/u', $fullName) === 1) {
            return false;
        }

        $genericLabels = [
            'cde',
            'centro de estudiantes',
        ];

        foreach ($genericLabels as $label) {
            if ($fullName === $label || str_contains($fullName, $label)) {
                return false;
            }
        }

        return true;
    }
}

class DryRunRollbackException extends RuntimeException
{
}
