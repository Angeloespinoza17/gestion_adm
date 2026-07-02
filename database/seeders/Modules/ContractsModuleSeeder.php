<?php

namespace Database\Seeders\Modules;

use App\Models\Contract;
use App\Models\ContractClause;
use App\Models\ContractSigner;
use App\Models\ContractSignature;
use App\Models\ContractTemplate;
use Database\Seeders\Support\ModuleSeeder;

class ContractsModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $templates = $this->seedTemplates();
        $signers = $this->seedSigners();

        $this->seedContracts($templates, $signers);
    }

    /**
     * @return array<string, \App\Models\ContractTemplate>
     */
    private function seedTemplates(): array
    {
        $templates = [
            'contrato-indefinido-docente' => [
                'name' => 'Contrato docente indefinido',
                'contract_type' => 'indefinido',
                'description' => 'Plantilla base para personal docente con contrato indefinido.',
                'body' => $this->teacherTemplateBody(),
            ],
            'contrato-plazo-fijo-asistente' => [
                'name' => 'Contrato plazo fijo asistentes',
                'contract_type' => 'plazo_fijo',
                'description' => 'Plantilla para personal de apoyo y asistentes con vigencia anual.',
                'body' => $this->assistantTemplateBody(),
            ],
        ];

        $clauses = ContractClause::query()->orderBy('sort_order')->get();
        $records = [];

        foreach ($templates as $slug => $definition) {
            $template = ContractTemplate::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'contract_type' => $definition['contract_type'],
                    'description' => $definition['description'],
                    'active' => true,
                    'body' => $definition['body'],
                    'available_variables' => [
                        'funcionario.nombre_completo',
                        'funcionario.rut',
                        'funcionario.correo_institucional',
                        'contrato.fecha_inicio',
                        'contrato.fecha_termino',
                        'contrato.cargo_contratado',
                        'contrato.horas_contratadas',
                        'contrato.jornada',
                        'contrato.sueldo_base',
                        'contrato.asignaciones',
                        'contrato.lugar_firma',
                        'contrato.fecha_firma',
                        'representante_legal.nombre',
                    ],
                    'internal_notes' => 'Plantilla generada desde seeder completo para pruebas funcionales.',
                ],
            );

            $template->clauses()->sync(
                $clauses->mapWithKeys(fn (ContractClause $clause, int $index) => [
                    $clause->id => [
                        'sort_order' => $index + 1,
                        'is_required' => (bool) $clause->is_required,
                    ],
                ])->all(),
            );

            $records[$slug] = $template;
        }

        return $records;
    }

    /**
     * @return array<string, \App\Models\ContractSigner>
     */
    private function seedSigners(): array
    {
        $definitions = [
            'representante_legal' => [
                'name' => 'Fundación Colegio Nuestra Señora del Carmen',
                'position' => 'Representación legal',
                'signer_type' => 'representante_legal',
                'sort_order' => 1,
                'observations' => 'Firma institucional para documentos contractuales.',
            ],
            'director' => [
                'name' => 'Carolina Muñoz Arriagada',
                'rut' => '12222222-2',
                'position' => 'Directora',
                'signer_type' => 'director',
                'sort_order' => 2,
            ],
            'rrhh' => [
                'name' => 'Marcelo Rojas Fuenzalida',
                'rut' => '13333333-3',
                'position' => 'Encargado RRHH',
                'signer_type' => 'rrhh',
                'sort_order' => 3,
            ],
        ];

        $records = [];

        foreach ($definitions as $key => $definition) {
            $records[$key] = ContractSigner::query()->updateOrCreate(
                [
                    'name' => $definition['name'],
                    'signer_type' => $definition['signer_type'],
                ],
                array_merge($definition, ['active' => true]),
            );
        }

        return $records;
    }

    /**
     * @param  array<string, \App\Models\ContractTemplate>  $templates
     * @param  array<string, \App\Models\ContractSigner>  $signers
     */
    private function seedContracts(array $templates, array $signers): void
    {
        $definitions = [
            [
                'staff' => 'andrea.medina@cnscgestion.local',
                'template' => 'contrato-indefinido-docente',
                'contract_type' => 'indefinido',
                'start_date' => '2024-03-01',
                'end_date' => null,
                'position_name' => 'Profesora de Ciencias',
                'contract_hours' => 42,
                'workday' => 'completa',
                'base_salary' => 1450000,
                'allowances' => 'Asignación de responsabilidad pedagógica',
                'place_of_signature' => 'Valdivia',
                'signature_date' => '2026-02-28',
                'status' => 'firmado',
                'generated_at' => '2026-02-20 10:15:00',
                'signed_at' => '2026-02-28 16:45:00',
                'observations' => 'Contrato vigente para módulo docente.',
            ],
            [
                'staff' => 'daniela.castillo@cnscgestion.local',
                'template' => 'contrato-plazo-fijo-asistente',
                'contract_type' => 'plazo_fijo',
                'start_date' => '2026-03-01',
                'end_date' => '2026-12-31',
                'position_name' => 'Profesora de Matemática',
                'contract_hours' => 34,
                'workday' => 'parcial',
                'base_salary' => 980000,
                'allowances' => 'Asignación proporcional por coordinación de nivel',
                'place_of_signature' => 'Valdivia',
                'signature_date' => '2026-03-01',
                'status' => 'generado',
                'generated_at' => '2026-02-26 09:00:00',
            ],
            [
                'staff' => 'laura.diaz@cnscgestion.local',
                'template' => 'contrato-plazo-fijo-asistente',
                'contract_type' => 'plazo_fijo',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'position_name' => 'Encargada de portería',
                'contract_hours' => 45,
                'workday' => 'turnos',
                'base_salary' => 840000,
                'allowances' => 'Turnos nocturnos y control de acceso',
                'place_of_signature' => 'Valdivia',
                'signature_date' => '2026-01-02',
                'status' => 'enviado_firma',
                'generated_at' => '2025-12-28 12:30:00',
            ],
            [
                'staff' => 'ricardo.fuentes@cnscgestion.local',
                'template' => 'contrato-indefinido-docente',
                'contract_type' => 'indefinido',
                'start_date' => '2025-03-01',
                'end_date' => null,
                'position_name' => 'Encargado de Mantención',
                'contract_hours' => 44,
                'workday' => 'completa',
                'base_salary' => 1180000,
                'allowances' => 'Disponibilidad por emergencias y soporte operacional',
                'place_of_signature' => 'Valdivia',
                'signature_date' => '2025-03-01',
                'status' => 'anulado',
                'generated_at' => '2025-02-25 18:00:00',
                'voided_at' => '2026-04-12 14:10:00',
                'observations' => 'Anulado por reemplazo de formato contractual.',
            ],
        ];

        foreach ($definitions as $definition) {
            $staff = $this->staffByEmail($definition['staff']);
            $template = $templates[$definition['template']];

            $contract = Contract::query()->updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'start_date' => $definition['start_date'],
                    'contract_type' => $definition['contract_type'],
                ],
                [
                    'contract_template_id' => $template->id,
                    'end_date' => $definition['end_date'],
                    'position_name' => $definition['position_name'],
                    'contract_hours' => $definition['contract_hours'],
                    'workday' => $definition['workday'],
                    'base_salary' => $definition['base_salary'],
                    'allowances' => $definition['allowances'],
                    'place_of_signature' => $definition['place_of_signature'],
                    'signature_date' => $definition['signature_date'],
                    'status' => $definition['status'],
                    'rendered_content' => $this->renderedContractContent($staff->full_name, $definition['position_name'], $definition['start_date'], $definition['end_date']),
                    'generated_at' => $definition['generated_at'] ?? null,
                    'signed_at' => $definition['signed_at'] ?? null,
                    'voided_at' => $definition['voided_at'] ?? null,
                    'custom_variables' => [
                        'establecimiento' => 'Colegio Nuestra Señora del Carmen',
                        'comuna' => 'Valdivia',
                    ],
                    'observations' => $definition['observations'] ?? null,
                    'created_by' => $this->creator()->id,
                    'updated_by' => $this->creator()->id,
                ],
            );

            $contract->departments()->sync($staff->departments()->pluck('departments.id')->all());

            $signatureDefinitions = [
                ['sort_order' => 1, 'signer' => $signers['representante_legal'], 'use_signature_image' => false],
                ['sort_order' => 2, 'signer' => $signers['director'], 'use_signature_image' => false],
                ['sort_order' => 3, 'signer' => $signers['rrhh'], 'use_signature_image' => false],
                [
                    'sort_order' => 4,
                    'signer' => null,
                    'name' => $staff->full_name,
                    'rut' => $staff->rut,
                    'position' => $definition['position_name'],
                    'signer_type' => 'funcionario',
                    'use_signature_image' => false,
                ],
            ];

            foreach ($signatureDefinitions as $signatureDefinition) {
                $signer = $signatureDefinition['signer'] ?? null;

                ContractSignature::query()->updateOrCreate(
                    [
                        'contract_id' => $contract->id,
                        'sort_order' => $signatureDefinition['sort_order'],
                    ],
                    [
                        'contract_signer_id' => $signer?->id,
                        'name' => $signatureDefinition['name'] ?? $signer?->name,
                        'rut' => $signatureDefinition['rut'] ?? $signer?->rut,
                        'position' => $signatureDefinition['position'] ?? $signer?->position,
                        'signer_type' => $signatureDefinition['signer_type'] ?? $signer?->signer_type,
                        'use_signature_image' => $signatureDefinition['use_signature_image'],
                        'observations' => 'Firma sincronizada desde seeder de contratos.',
                    ],
                );
            }
        }
    }

    private function teacherTemplateBody(): string
    {
        return <<<TXT
CONTRATO DE TRABAJO DOCENTE

Se celebra el presente contrato entre el establecimiento y {{funcionario.nombre_completo}}, RUT {{funcionario.rut}}, para desempeñarse como {{contrato.cargo_contratado}} desde {{contrato.fecha_inicio}}.

La jornada convenida corresponde a {{contrato.horas_contratadas}} horas, bajo modalidad {{contrato.jornada}}, con remuneración base {{contrato.sueldo_base}}.

Las partes fijan como lugar de firma {{contrato.lugar_firma}} con fecha {{contrato.fecha_firma}}.
TXT;
    }

    private function assistantTemplateBody(): string
    {
        return <<<TXT
CONTRATO DE TRABAJO A PLAZO FIJO

El establecimiento contrata a {{funcionario.nombre_completo}}, RUT {{funcionario.rut}}, para funciones de {{contrato.cargo_contratado}} desde {{contrato.fecha_inicio}} hasta {{contrato.fecha_termino}}.

Se establece jornada {{contrato.jornada}} por {{contrato.horas_contratadas}} horas y asignaciones complementarias {{contrato.asignaciones}}.
TXT;
    }

    private function renderedContractContent(string $fullName, string $position, string $startDate, ?string $endDate): string
    {
        $endLabel = $endDate ?: 'vigencia indefinida';

        return sprintf(
            'Contrato generado para %s, cargo %s, con vigencia desde %s hasta %s.',
            $fullName,
            $position,
            $startDate,
            $endLabel,
        );
    }
}
