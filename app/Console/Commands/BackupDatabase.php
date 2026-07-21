<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--no-prune : No eliminar respaldos vencidos}';

    protected $description = 'Crea un respaldo comprimido de la base de datos configurada';

    public function handle(): int
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            $this->error('La conexión de base de datos no está configurada.');

            return self::FAILURE;
        }

        $driver = $connection['driver'] ?? null;
        $extension = $driver === 'sqlite' ? 'sqlite' : 'sql.gz';
        $filename = sprintf('%s-%s.%s', $connectionName, now()->format('Y-m-d_His'), $extension);
        $directory = trim((string) config('backup.path'), '/');
        $relativePath = ($directory === '' ? '' : $directory.'/').$filename;
        $temporaryPath = tempnam(sys_get_temp_dir(), 'db-backup-');

        if ($temporaryPath === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del respaldo.');
        }

        try {
            $driver === 'sqlite'
                ? $this->backupSqlite($connection, $temporaryPath)
                : $this->backupServerDatabase($driver, $connection, $temporaryPath);

            $stream = fopen($temporaryPath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('No se pudo almacenar el respaldo en el disco configurado.');
            }

            try {
                $stored = Storage::disk(config('backup.disk'))->put($relativePath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw new RuntimeException('No se pudo almacenar el respaldo en el disco configurado.');
            }

            if (! $this->option('no-prune')) {
                $this->pruneExpiredBackups($directory);
            }

            $this->info("Respaldo creado: {$relativePath}");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Falló el respaldo: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function backupSqlite(array $connection, string $target): void
    {
        $database = $connection['database'] ?? null;
        if (! is_string($database) || ! is_file($database) || ! copy($database, $target)) {
            throw new RuntimeException('No se pudo copiar la base SQLite.');
        }
    }

    private function backupServerDatabase(?string $driver, array $connection, string $target): void
    {
        [$command, $environment] = match ($driver) {
            'mysql', 'mariadb' => [$this->mysqlCommand($connection), ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]],
            'pgsql' => [$this->postgresCommand($connection), ['PGPASSWORD' => (string) ($connection['password'] ?? '')]],
            default => throw new RuntimeException("El motor {$driver} no está soportado para respaldos."),
        };

        $gzip = gzopen($target, 'wb9');
        if ($gzip === false) {
            throw new RuntimeException('No se pudo abrir el archivo comprimido.');
        }

        $process = new Process($command, null, $environment, null, (float) config('backup.timeout'));
        try {
            $process->run(function (string $type, string $buffer) use ($gzip): void {
                if ($type === Process::OUT) {
                    gzwrite($gzip, $buffer);
                }
            });
        } finally {
            gzclose($gzip);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'La herramienta de respaldo terminó con error.');
        }
    }

    private function mysqlCommand(array $connection): array
    {
        return array_values(array_filter([
            'mysqldump', '--single-transaction', '--quick', '--routines', '--triggers',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? 3306),
            '--user='.($connection['username'] ?? ''),
            isset($connection['unix_socket']) && $connection['unix_socket'] !== '' ? '--socket='.$connection['unix_socket'] : null,
            '--databases', $connection['database'] ?? '',
        ], fn ($value) => $value !== null));
    }

    private function postgresCommand(array $connection): array
    {
        return [
            'pg_dump', '--clean', '--if-exists', '--no-owner', '--no-privileges',
            '--host='.($connection['host'] ?? '127.0.0.1'),
            '--port='.($connection['port'] ?? 5432),
            '--username='.($connection['username'] ?? ''),
            '--dbname='.($connection['database'] ?? ''),
        ];
    }

    private function pruneExpiredBackups(string $directory): void
    {
        $disk = Storage::disk(config('backup.disk'));
        $cutoff = now()->subDays((int) config('backup.retention_days'))->getTimestamp();

        foreach ($disk->files($directory) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
