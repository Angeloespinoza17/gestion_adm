<?php

namespace Database\Seeders;

use App\Models\ContractClause;
use Illuminate\Database\Seeder;

class ContractClauseSeeder extends Seeder
{
    public function run(): void
    {
        $clauses = [
            [
                'title' => 'Antecedentes y actualización',
                'clause_type' => 'general',
                'content' => "PRIMERO: Antecedentes.\nLas partes acuerdan actualizar y complementar la información contractual del funcionario {{funcionario.nombre_completo}}, RUT {{funcionario.rut}}, para efectos administrativos internos del establecimiento.\n\nSEGUNDO: Vigencia.\nEl presente instrumento comenzará a regir desde {{contrato.fecha_inicio}} y tendrá duración {{contrato.fecha_termino}}.",
                'is_required' => true,
            ],
            [
                'title' => 'Correos electrónicos y comunicaciones',
                'clause_type' => 'comunicaciones',
                'content' => "Las partes señalan como correos válidos para comunicaciones: empleador {{representante_legal.nombre}} y funcionario {{funcionario.correo_institucional}} / {{funcionario.correo_personal}}.",
                'is_required' => false,
            ],
            [
                'title' => 'Funciones del cargo',
                'clause_type' => 'funciones',
                'content' => "El funcionario prestará servicios en el cargo de {{contrato.cargo_contratado}}, desarrollando las funciones propias del puesto, además de aquellas tareas complementarias compatibles con su rol y con las necesidades del establecimiento.",
                'is_required' => true,
            ],
            [
                'title' => 'Jornada laboral',
                'clause_type' => 'jornada',
                'content' => "La jornada pactada corresponde a {{contrato.horas_contratadas}} horas, bajo modalidad {{contrato.jornada}}.",
                'is_required' => true,
            ],
            [
                'title' => 'Remuneraciones',
                'clause_type' => 'remuneraciones',
                'content' => "La remuneración base acordada es de {{contrato.sueldo_base}}. Asignaciones u otros conceptos: {{contrato.asignaciones}}.",
                'is_required' => true,
            ],
            [
                'title' => 'Firmas y ratificación',
                'clause_type' => 'cierre',
                'content' => "Leído el presente instrumento, las partes firman en señal de aceptación en {{contrato.lugar_firma}}, con fecha {{contrato.fecha_firma}}.",
                'is_required' => true,
            ],
        ];

        foreach ($clauses as $index => $clause) {
            ContractClause::query()->updateOrCreate(
                ['title' => $clause['title']],
                [
                    'clause_type' => $clause['clause_type'],
                    'content' => $clause['content'],
                    'active' => true,
                    'sort_order' => $index + 1,
                    'is_required' => $clause['is_required'],
                    'observations' => 'Cláusula base de ejemplo. Debe revisarse y adaptarse antes de uso definitivo.',
                ]
            );
        }
    }
}
