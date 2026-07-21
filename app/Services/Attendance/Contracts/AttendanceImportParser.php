<?php

namespace App\Services\Attendance\Contracts;

interface AttendanceImportParser
{
    public function supports(string $path): bool;

    public function parse(string $path): array;
}
