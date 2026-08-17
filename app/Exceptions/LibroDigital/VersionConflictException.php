<?php

namespace App\Exceptions\LibroDigital;

class VersionConflictException extends LibroDigitalException
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct(
            'El registro fue modificado por otra persona. Recarga antes de continuar.',
            'LCD_VERSION_CONFLICT',
            412,
            [[
                'field' => 'lock_version',
                'reason' => "Version esperada {$expected}; version actual {$actual}.",
            ]],
        );
    }
}
