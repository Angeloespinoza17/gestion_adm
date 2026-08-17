<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\VersionConflictException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OptimisticLock
{
    public function expectedVersion(Request $request): int
    {
        $header = trim((string) $request->header('If-Match', ''));
        $header = trim($header, '"W/ ');

        return (int) ($header !== '' ? $header : $request->input('lock_version', 0));
    }

    public function assert(Model $model, Request $request): int
    {
        $expected = $this->expectedVersion($request);
        $actual = (int) ($model->getAttribute('lock_version') ?? $model->getAttribute('revision') ?? 0);

        if ($expected !== $actual) {
            throw new VersionConflictException($expected, $actual);
        }

        return $expected;
    }
}
