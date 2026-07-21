<?php

namespace App\Services\Attendance;

use App\Services\Attendance\Contracts\AttendanceImportParser;
use RuntimeException;

class AttendanceParserRegistry
{
    /** @param iterable<AttendanceImportParser> $parsers */
    public function __construct(private readonly iterable $parsers) {}

    public function parse(string $path): array
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($path)) {
                return $parser->parse($path);
            }
        }

        throw new RuntimeException('El archivo no corresponde a un formato de asistencia compatible.');
    }
}
