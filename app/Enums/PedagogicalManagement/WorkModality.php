<?php

namespace App\Enums\PedagogicalManagement;

enum WorkModality: string
{
    case Individual = 'individual';
    case Pairs = 'pairs';
    case Group = 'group';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual', self::Pairs => 'Parejas', self::Group => 'Grupal', self::Mixed => 'Mixta',
        };
    }
}
