<?php

namespace App\Services\Library;

use Generator;
use RuntimeException;

class AbiesMdbReader
{
    public function __construct(
        private readonly string $databasePath,
    ) {
        if (! is_file($this->databasePath) || ! is_readable($this->databasePath)) {
            throw new RuntimeException('La base Abies no existe o no puede leerse.');
        }

        if (! $this->commandExists('mdb-export')) {
            throw new RuntimeException('Falta mdbtools. Instala mdbtools antes de ejecutar la importación.');
        }
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    public function rows(string $table, string $binaryMode = 'strip'): Generator
    {
        $command = [
            'mdb-export',
            '-b',
            $binaryMode,
            '-D',
            '%Y-%m-%d',
            '-T',
            '%Y-%m-%d %H:%M:%S',
            $this->databasePath,
            $table,
        ];

        $pipes = [];
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException("No se pudo exportar la tabla {$table} de Abies.");
        }

        try {
            $headers = fgetcsv($pipes[1], null, ',', '"', '');
            if (! is_array($headers) || $headers === []) {
                throw new RuntimeException("La tabla {$table} no tiene un encabezado legible.");
            }

            while (($values = fgetcsv($pipes[1], null, ',', '"', '')) !== false) {
                if (count($values) !== count($headers)) {
                    throw new RuntimeException("Una fila de {$table} no coincide con su estructura.");
                }

                /** @var array<string, string|null> $row */
                $row = array_combine($headers, $values);
                yield $row;
            }

            $error = trim((string) stream_get_contents($pipes[2]));
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException($error !== '' ? $error : "Falló la lectura de {$table}.");
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public function table(string $table): array
    {
        return iterator_to_array($this->rows($table), false);
    }

    public function exportableCount(string $table): int
    {
        $count = 0;
        foreach ($this->rows($table) as $_row) {
            $count++;
        }

        return $count;
    }

    private function commandExists(string $command): bool
    {
        $path = getenv('PATH') ?: '';
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$command;
            if (is_file($candidate) && is_executable($candidate)) {
                return true;
            }
        }

        return false;
    }
}
