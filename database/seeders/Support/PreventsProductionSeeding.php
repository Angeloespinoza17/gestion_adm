<?php

namespace Database\Seeders\Support;

use RuntimeException;

trait PreventsProductionSeeding
{
    protected function preventProductionSeeding(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            throw new RuntimeException(sprintf(
                '%s contiene datos demostrativos o reinicios de datos y solo puede ejecutarse en local, development o testing.',
                static::class
            ));
        }
    }
}
