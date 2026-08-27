<?php

namespace App\Enums\PedagogicalManagement;

enum EvaluationPurpose: string
{
    case Diagnostic = 'diagnostic';
    case Formative = 'formative';
    case Summative = 'summative';
    case Process = 'process';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Diagnostic => 'Diagnóstico', self::Formative => 'Formativo', self::Summative => 'Sumativo',
            self::Process => 'Proceso', self::Other => 'Otro',
        };
    }
}
