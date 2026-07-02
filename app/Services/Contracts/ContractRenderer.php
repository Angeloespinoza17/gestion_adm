<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractClause;
use App\Models\ContractSigner;
use App\Models\ContractTemplate;
use App\Models\Department;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ContractRenderer
{
    private const ORDINAL_LABELS = [
        1 => 'PRIMERO',
        2 => 'SEGUNDO',
        3 => 'TERCERO',
        4 => 'CUARTO',
        5 => 'QUINTO',
        6 => 'SEXTO',
        7 => 'SÉPTIMO',
        8 => 'OCTAVO',
        9 => 'NOVENO',
        10 => 'DÉCIMO',
        11 => 'UNDÉCIMO',
        12 => 'DUODÉCIMO',
        13 => 'DECIMOTERCERO',
        14 => 'DECIMOCUARTO',
        15 => 'DECIMOQUINTO',
        16 => 'DECIMOSEXTO',
        17 => 'DECIMOSÉPTIMO',
        18 => 'DECIMOCTAVO',
        19 => 'DECIMONOVENO',
        20 => 'VIGÉSIMO',
    ];

    public function availableVariables(): array
    {
        return [
            'funcionario' => [
                'funcionario.nombre_completo',
                'funcionario.rut',
                'funcionario.direccion',
                'funcionario.comuna',
                'funcionario.region',
                'funcionario.correo_institucional',
                'funcionario.correo_personal',
                'funcionario.telefono',
                'funcionario.cargo',
                'funcionario.fecha_ingreso',
                'funcionario.tipo_contrato',
                'funcionario.horas_contrato',
                'funcionario.jornada_laboral',
                'funcionario.departamentos',
                'funcionario.titulo_profesional',
                'funcionario.especialidad',
            ],
            'contrato' => [
                'contrato.fecha_inicio',
                'contrato.fecha_termino',
                'contrato.tipo_contrato',
                'contrato.cargo_contratado',
                'contrato.horas_contratadas',
                'contrato.jornada',
                'contrato.sueldo_base',
                'contrato.asignaciones',
                'contrato.departamentos',
                'contrato.lugar_firma',
                'contrato.fecha_firma',
                'contrato.fecha_generacion',
                'contrato.estado',
            ],
            'representante_legal' => [
                'representante_legal.nombre',
                'representante_legal.rut',
                'representante_legal.cargo',
            ],
            'sistema' => [
                'clausulas',
                'firmas',
            ],
            'extra' => [
                'extra.campo_personalizado',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function previewVariableMap(): array
    {
        return [
            'funcionario.nombre_completo' => 'Juan Pérez González',
            'funcionario.rut' => '12.345.678-9',
            'funcionario.direccion' => 'Av. Los Robles 123',
            'funcionario.comuna' => 'Valdivia',
            'funcionario.region' => 'Los Ríos',
            'funcionario.correo_institucional' => 'juan.perez@colegio.cl',
            'funcionario.correo_personal' => 'juanperez@email.com',
            'funcionario.telefono' => '+56 9 1234 5678',
            'funcionario.cargo' => 'Docente de Matemática',
            'funcionario.fecha_ingreso' => '01/03/2024',
            'funcionario.tipo_contrato' => 'Indefinido',
            'funcionario.horas_contrato' => '44',
            'funcionario.jornada_laboral' => 'Jornada completa',
            'funcionario.departamentos' => 'Docentes, UTP',
            'funcionario.titulo_profesional' => 'Profesor de Matemática',
            'funcionario.especialidad' => 'Didáctica de la matemática',
            'contrato.fecha_inicio' => '01/03/2026',
            'contrato.fecha_termino' => '28/02/2027',
            'contrato.tipo_contrato' => 'Plazo fijo',
            'contrato.cargo_contratado' => 'Profesor jefe',
            'contrato.horas_contratadas' => '40',
            'contrato.jornada' => 'Jornada completa',
            'contrato.sueldo_base' => '$1.200.000',
            'contrato.asignaciones' => 'Bono responsabilidad, movilización',
            'contrato.departamentos' => 'Docentes, Coordinación Académica',
            'contrato.lugar_firma' => 'Valdivia',
            'contrato.fecha_firma' => '26/06/2026',
            'contrato.fecha_generacion' => '26/06/2026 10:30',
            'contrato.estado' => 'Borrador',
            'representante_legal.nombre' => 'María Fernández Soto',
            'representante_legal.rut' => '9.876.543-2',
            'representante_legal.cargo' => 'Representante legal',
            'extra.campo_personalizado' => 'Valor de ejemplo',
        ];
    }

    public function renderClausePreview(string $title, string $content): string
    {
        $values = $this->previewVariableMap();
        $normalizedContent = $this->sanitizeClauseContent($content);
        $heading = 'PRIMERO:';
        $normalizedTitle = trim($title);

        if ($normalizedTitle !== '') {
            $heading .= ' ' . rtrim($normalizedTitle, ". \t\n\r\0\x0B") . '.';
        }

        foreach ($values as $token => $value) {
            $normalizedContent = str_replace('{{' . $token . '}}', $value, $normalizedContent);
        }

        return trim($heading . ($normalizedContent !== '' ? "\n" . trim($normalizedContent) : ''));
    }

    /**
     * @param  iterable<int, ContractClause>  $clauses
     */
    public function renderTemplatePreview(string $body, iterable $clauses): string
    {
        $content = trim($body);
        $clauseText = $this->renderTemplateClauses($clauses);
        $signatureText = $this->renderTemplateSignatureBlock();

        if (str_contains($content, '{{clausulas}}')) {
            $content = str_replace('{{clausulas}}', $clauseText, $content);
        } elseif ($clauseText !== '') {
            $content = trim($content . "\n\n" . $clauseText);
        }

        if (str_contains($content, '{{firmas}}')) {
            $content = str_replace('{{firmas}}', $signatureText, $content);
        } elseif ($signatureText !== '') {
            $content = trim($content . "\n\n" . $signatureText);
        }

        return trim($content);
    }

    /**
     * @param  iterable<int, ContractClause>  $clauses
     * @param  iterable<int, array<string, mixed>>  $signatureBlocks
     * @param  array<string, mixed>  $contractData
     * @param  array<string, mixed>  $customVariables
     * @return array{content:string, missing:array<int, string>, placeholders:array<int, string>}
     */
    public function render(
        ContractTemplate $template,
        Staff $staff,
        iterable $clauses,
        iterable $signatureBlocks,
        array $contractData = [],
        ?ContractSigner $representativeLegal = null,
        array $customVariables = [],
    ): array {
        $content = trim((string) ($template->body ?? ''));
        $clauseText = $this->renderClauses($clauses, $staff, $contractData, $representativeLegal, $customVariables);
        $signatureText = $this->renderSignatures($signatureBlocks);

        if (str_contains($content, '{{clausulas}}')) {
            $content = str_replace('{{clausulas}}', $clauseText, $content);
        } elseif ($clauseText !== '') {
            $content = trim($content . "\n\n" . $clauseText);
        }

        if (str_contains($content, '{{firmas}}')) {
            $content = str_replace('{{firmas}}', $signatureText, $content);
        } elseif ($signatureText !== '') {
            $content = trim($content . "\n\n" . $signatureText);
        }

        $values = $this->buildVariableMap($staff, $contractData, $representativeLegal, $customVariables);
        $placeholders = $this->extractPlaceholders($content);
        $missing = [];

        foreach ($placeholders as $placeholder) {
            $token = $this->normalizePlaceholder($placeholder);
            $value = $values[$token] ?? null;

            if ($value === null || trim((string) $value) === '') {
                $missing[] = $token;
            }
        }

        $rendered = $content;

        foreach ($values as $token => $value) {
            $rendered = str_replace('{{' . $token . '}}', (string) $value, $rendered);
        }

        return [
            'content' => trim($rendered),
            'missing' => array_values(array_unique($missing)),
            'placeholders' => array_map(fn ($item) => $this->normalizePlaceholder($item), $placeholders),
        ];
    }

    /**
     * @param  array<string, mixed>  $contractData
     * @param  array<string, mixed>  $customVariables
     * @return array<string, string>
     */
    public function buildVariableMap(
        Staff $staff,
        array $contractData = [],
        ?ContractSigner $representativeLegal = null,
        array $customVariables = [],
    ): array {
        $departments = collect($contractData['departments'] ?? $staff->departments ?? [])
            ->map(function ($department) {
                if ($department instanceof Department) {
                    return $department->name;
                }

                if (is_array($department)) {
                    return $department['name'] ?? null;
                }

                return null;
            })
            ->filter()
            ->implode(', ');

        $statusLabel = collect(Contract::STATUS_OPTIONS)->firstWhere('value', $contractData['status'] ?? null)['label'] ?? ($contractData['status'] ?? '');

        $values = [
            'funcionario.nombre_completo' => (string) ($staff->full_name ?? ''),
            'funcionario.rut' => (string) ($staff->rut ?? ''),
            'funcionario.direccion' => (string) ($staff->address ?? ''),
            'funcionario.comuna' => (string) ($staff->communeRecord?->name ?? $staff->commune ?? ''),
            'funcionario.region' => (string) ($staff->regionRecord?->short_name ?? $staff->regionRecord?->name ?? $staff->region ?? ''),
            'funcionario.correo_institucional' => (string) ($staff->institutional_email ?? ''),
            'funcionario.correo_personal' => (string) ($staff->personal_email ?? ''),
            'funcionario.telefono' => (string) ($staff->phone ?? ''),
            'funcionario.cargo' => (string) ($staff->cargo?->name ?? ''),
            'funcionario.fecha_ingreso' => $this->formatDate($staff->start_date),
            'funcionario.tipo_contrato' => $this->labelForOption($staff->contract_type, Staff::CONTRACT_TYPE_OPTIONS),
            'funcionario.horas_contrato' => $this->formatNumber($staff->contract_hours),
            'funcionario.jornada_laboral' => $this->labelForOption($staff->workday, Staff::WORKDAY_OPTIONS),
            'funcionario.departamentos' => (string) ($staff->departments->pluck('name')->implode(', ')),
            'funcionario.titulo_profesional' => (string) ($staff->professional_title ?? ''),
            'funcionario.especialidad' => (string) ($staff->specialty ?? ''),
            'contrato.fecha_inicio' => $this->formatDate($contractData['start_date'] ?? null),
            'contrato.fecha_termino' => $this->formatDate($contractData['end_date'] ?? null),
            'contrato.tipo_contrato' => $this->labelForOption(
                $contractData['contract_type'] ?? $staff->contract_type ?? null,
                Staff::CONTRACT_TYPE_OPTIONS
            ),
            'contrato.cargo_contratado' => (string) ($contractData['position_name'] ?? $staff->cargo?->name ?? ''),
            'contrato.horas_contratadas' => $this->formatNumber($contractData['contract_hours'] ?? null),
            'contrato.jornada' => $this->labelForOption($contractData['workday'] ?? null, Staff::WORKDAY_OPTIONS),
            'contrato.sueldo_base' => $this->formatCurrency($contractData['base_salary'] ?? null),
            'contrato.asignaciones' => (string) ($contractData['allowances'] ?? ''),
            'contrato.departamentos' => $departments,
            'contrato.lugar_firma' => (string) ($contractData['place_of_signature'] ?? ''),
            'contrato.fecha_firma' => $this->formatDate($contractData['signature_date'] ?? null),
            'contrato.fecha_generacion' => $this->formatDateTime($contractData['generated_at'] ?? now()),
            'contrato.estado' => (string) $statusLabel,
            'representante_legal.nombre' => (string) ($representativeLegal?->name ?? ''),
            'representante_legal.rut' => (string) ($representativeLegal?->rut ?? ''),
            'representante_legal.cargo' => (string) ($representativeLegal?->position ?? ''),
        ];

        foreach ($customVariables as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $values['extra.' . Str::snake($normalizedKey)] = (string) $value;
        }

        return $values;
    }

    /**
     * @param  iterable<int, ContractClause>  $clauses
     * @param  array<string, mixed>  $contractData
     * @param  array<string, mixed>  $customVariables
     */
    private function renderClauses(
        iterable $clauses,
        Staff $staff,
        array $contractData,
        ?ContractSigner $representativeLegal,
        array $customVariables,
    ): string {
        $values = $this->buildVariableMap($staff, $contractData, $representativeLegal, $customVariables);

        return collect($clauses)
            ->values()
            ->map(function (ContractClause $clause, int $index) use ($values) {
                $content = $this->sanitizeClauseContent((string) $clause->content);

                foreach ($values as $token => $value) {
                    $content = str_replace('{{' . $token . '}}', (string) $value, $content);
                }

                $heading = $this->ordinalLabel($index + 1) . ':';
                $title = trim((string) $clause->title);

                if ($title !== '') {
                    $heading .= ' ' . rtrim($title, ". \t\n\r\0\x0B") . '.';
                }

                $content = trim($content);

                return trim($heading . ($content !== '' ? "\n" . $content : ''));
            })
            ->filter()
            ->implode("\n\n");
    }

    /**
     * @param  iterable<int, ContractClause>  $clauses
     */
    private function renderTemplateClauses(iterable $clauses): string
    {
        return collect($clauses)
            ->values()
            ->map(function (ContractClause $clause, int $index) {
                $content = trim($this->sanitizeClauseContent((string) $clause->content));
                $heading = $this->ordinalLabel($index + 1) . ':';
                $title = trim((string) $clause->title);

                if ($title !== '') {
                    $heading .= ' ' . rtrim($title, ". \t\n\r\0\x0B") . '.';
                }

                return trim($heading . ($content !== '' ? "\n" . $content : ''));
            })
            ->filter()
            ->implode("\n\n");
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $signatureBlocks
     */
    private function renderSignatures(iterable $signatureBlocks): string
    {
        return collect($signatureBlocks)
            ->map(function (array $signature) {
                $line = trim((string) ($signature['name'] ?? ''));
                $rut = trim((string) ($signature['rut'] ?? ''));
                $position = trim((string) ($signature['position'] ?? ''));

                $parts = array_filter([$line, $rut, $position], fn ($value) => $value !== '');
                $text = "______________________________\n";

                if (empty($parts)) {
                    return $text . 'Firma';
                }

                return $text . implode("\n", $parts);
            })
            ->filter()
            ->implode("\n\n");
    }

    private function renderTemplateSignatureBlock(): string
    {
        return implode("\n", [
            '______________________________',
            '[Nombre del firmante]',
            '[RUT del firmante]',
            '[Cargo del firmante]',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function extractPlaceholders(string $content): array
    {
        preg_match_all('/{{\s*([^}]+)\s*}}/', $content, $matches);

        return $matches[1] ?? [];
    }

    private function normalizePlaceholder(string $placeholder): string
    {
        return trim(str_replace(["\n", "\r"], '', $placeholder));
    }

    private function ordinalLabel(int $position): string
    {
        return self::ORDINAL_LABELS[$position] ?? 'CLÁUSULA ' . $position;
    }

    private function sanitizeClauseContent(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        $ordinals = implode('|', array_map(
            static fn ($label) => preg_quote($label, '/'),
            array_values(self::ORDINAL_LABELS)
        ));

        $content = preg_replace('/^\s*(?:' . $ordinals . '|CLÁUSULA\s+\d+)\s*:\s*.*(?:\R+|$)/u', '', $content, 1) ?? $content;

        return trim($content);
    }

    private function labelForOption(?string $value, array $options): string
    {
        if (!$value) {
            return '';
        }

        return collect($options)->firstWhere('value', $value)['label'] ?? $value;
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatDateTime(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    private function formatCurrency(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return '$' . number_format((float) $value, 0, ',', '.');
    }
}
