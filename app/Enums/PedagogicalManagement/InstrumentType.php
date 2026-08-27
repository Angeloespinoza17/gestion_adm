<?php

namespace App\Enums\PedagogicalManagement;

enum InstrumentType: string
{
    case WrittenTest = 'written_test';
    case Quiz = 'quiz';
    case Rubric = 'rubric';
    case Checklist = 'checklist';
    case AppreciationScale = 'appreciation_scale';
    case AssessedGuide = 'assessed_guide';
    case Project = 'project';
    case WrittenProduction = 'written_production';
    case PracticalAssessment = 'practical_assessment';
    case Presentation = 'presentation';
    case Portfolio = 'portfolio';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WrittenTest => 'Prueba escrita', self::Quiz => 'Control', self::Rubric => 'Rúbrica',
            self::Checklist => 'Lista de cotejo', self::AppreciationScale => 'Escala de apreciación',
            self::AssessedGuide => 'Guía evaluada', self::Project => 'Proyecto',
            self::WrittenProduction => 'Producción escrita', self::PracticalAssessment => 'Evaluación práctica',
            self::Presentation => 'Exposición', self::Portfolio => 'Portafolio', self::Other => 'Otro',
        };
    }
}
