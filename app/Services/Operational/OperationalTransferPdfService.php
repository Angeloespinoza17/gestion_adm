<?php

namespace App\Services\Operational;

use App\Models\Operational\OperationalTransferDocument;
use App\Models\Operational\OperationalTransferRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class OperationalTransferPdfService
{
    public function build(OperationalTransferRequest $transfer): string
    {
        $transfer->loadMissing([
            'requestedBy:id,name,email,staff_id', 'visorUser:id,name,email,staff_id',
            'administrationUser:id,name,email,staff_id', 'approvals.actorUser:id,name,email',
            'operation.provider', 'operation.selectedQuote.provider',
        ]);

        return (new OperationalTransferPdfBuilder)->build($transfer);
    }

    public function storeSnapshot(OperationalTransferRequest $transfer, string $stage, User $actor): OperationalTransferDocument
    {
        $bytes = $this->build($transfer);
        $fileName = sprintf('Solicitud_Traslado_%s_%s.pdf', $transfer->folio, $stage);
        $path = sprintf('operational-transfers/%d/official/%s_%s', $transfer->id, now()->format('Ymd_His'), $fileName);
        Storage::disk('local')->put($path, $bytes);

        return $transfer->documents()->create([
            'uploaded_by_user_id' => $actor->id,
            'document_type' => 'pdf_solicitud',
            'file_path' => $path,
            'file_name' => $fileName,
            'file_type' => 'application/pdf',
            'file_size' => strlen($bytes),
            'official_snapshot' => true,
            'snapshot_stage' => $stage,
            'comments' => 'Copia oficial inalterable generada automáticamente.',
        ]);
    }
}

class OperationalTransferPdfBuilder
{
    private const WIDTH = 595.0;

    private const HEIGHT = 842.0;

    private const MARGIN = 42.0;

    private const CONTENT_WIDTH = 511.0;

    private const BOTTOM = 54.0;

    private array $pages = [];

    private int $pageIndex = -1;

    private float $cursorY = 0;

    private OperationalTransferRequest $transfer;

    public function build(OperationalTransferRequest $transfer): string
    {
        $this->transfer = $transfer;
        $this->pages = [];
        $this->startPage(true);
        $this->statusBand();
        $this->section('IDENTIFICACIÓN DE LA SOLICITUD');
        $this->keyValueRows([
            ['Solicitante', $transfer->requester_name_snapshot ?: $transfer->requestedBy?->name],
            ['Cargo / función', $transfer->requester_role_snapshot],
            ['Unidad', $transfer->requester_unit_snapshot],
            ['Subdirector/a visador/a', $transfer->visor_name_snapshot ?: $transfer->visorUser?->name],
        ]);

        $this->section('ACTIVIDAD Y TRASLADO');
        $this->keyValueRows([
            ['Actividad', $transfer->activity_name],
            ['Tipo', $this->label(OperationalTransferRequest::ACTIVITY_TYPE_OPTIONS, $transfer->activity_type)],
            ['Curso / asignatura', $transfer->course_subject],
            ['Propósito', $transfer->purpose],
            ['Fecha', $transfer->transport_date?->format('d/m/Y')],
            ['Horario', $this->time($transfer->departure_time).' - '.$this->time($transfer->return_time)],
            ['Modalidad', $this->label(OperationalTransferRequest::TRANSPORT_MODE_OPTIONS, $transfer->transport_mode)],
            ['Origen', $transfer->origin],
            ['Destino', $transfer->destination],
        ]);

        $this->section('PASAJEROS Y ACCESIBILIDAD');
        $this->metricCards([
            ['Estudiantes', $transfer->student_count],
            ['Adultos', $transfer->adult_count],
            ['Total', $transfer->passenger_count],
        ]);
        $this->keyValueRows([
            ['Movilidad reducida', $transfer->reduced_mobility ? 'Sí' : 'No'],
            ['Requerimientos', $transfer->mobility_requirements],
            ['Observaciones', $transfer->visible_observations],
        ]);

        $this->section('VISACIÓN Y APROBACIÓN');
        $approvalRows = $transfer->approvals->map(fn ($approval) => [
            $this->stepLabel($approval->step).' · '.$this->decisionLabel($approval->decision),
            ($approval->actorUser?->name ?? 'Usuario no disponible').' · '.$approval->acted_at?->format('d/m/Y H:i').($approval->comments ? ' · '.$approval->comments : ''),
        ])->all();
        $this->keyValueRows($approvalRows ?: [['Estado', 'Aún no existen decisiones registradas.']]);

        if ($transfer->operation || in_array($transfer->approval_status, ['aprobado', 'importado_historico'], true)) {
            $this->section('GESTIÓN ADMINISTRATIVA');
            $this->keyValueRows([
                ['Proveedor', $transfer->operation?->provider?->name],
                ['Costo final', $transfer->operation?->final_cost !== null ? '$'.number_format($transfer->operation->final_cost, 0, ',', '.') : null],
                ['Confirmación', $transfer->operation?->confirmation_reference],
                ['Estado del servicio', $this->label(OperationalTransferRequest::SERVICE_STATUS_OPTIONS, $transfer->service_status)],
                ['DTE', $this->label(OperationalTransferRequest::DTE_STATUS_OPTIONS, $transfer->dte_status).($transfer->operation?->dte_number ? ' · '.$transfer->operation->dte_number : '')],
                ['Pago', $this->label(OperationalTransferRequest::PAYMENT_STATUS_OPTIONS, $transfer->payment_status).($transfer->operation?->payment_reference ? ' · '.$transfer->operation->payment_reference : '')],
            ]);
        }

        $this->appendFooters();

        return $this->document();
    }

    private function startPage(bool $first = false): void
    {
        $this->pages[] = [];
        $this->pageIndex = count($this->pages) - 1;
        $this->fillRect(0, 0, self::WIDTH, self::HEIGHT, [1, 1, 1]);
        $this->fillRect(0, $first ? 720 : 775, self::WIDTH, $first ? 122 : 67, [0.055, 0.18, 0.34]);
        $this->fillRect(0, $first ? 716 : 771, self::WIDTH, 4, [0.94, 0.68, 0.16]);

        if ($first) {
            $this->fillRect(42, 760, 54, 54, [1, 1, 1], [0.94, 0.68, 0.16]);
            $this->text(51, 780, 'CNSC', 12, 'F2', [0.055, 0.18, 0.34]);
            $this->text(112, 800, 'COLEGIO NUESTRA SEÑORA DEL CARMEN', 8, 'F2', [0.76, 0.84, 0.92]);
            $this->text(112, 770, 'SOLICITUD DE TRASLADO', 22, 'F2', [1, 1, 1]);
            $this->text(112, 750, 'GESTIÓN OPERATIVA', 8, 'F2', [0.94, 0.68, 0.16]);
            $this->text(434, 797, 'FOLIO', 7, 'F2', [0.76, 0.84, 0.92]);
            $this->text(434, 777, $this->transfer->folio, 12, 'F2', [1, 1, 1]);
            $this->cursorY = 696;
            $this->watermark();
        } else {
            $this->text(42, 808, 'CNSC · GESTIÓN OPERATIVA', 8, 'F2', [0.76, 0.84, 0.92]);
            $this->text(390, 808, $this->transfer->folio, 9, 'F2', [1, 1, 1]);
            $this->cursorY = 750;
        }
    }

    private function statusBand(): void
    {
        $label = $this->label(OperationalTransferRequest::APPROVAL_STATUS_OPTIONS, $this->transfer->approval_status);
        $this->fillRect(self::MARGIN, $this->cursorY - 38, self::CONTENT_WIDTH, 38, [0.965, 0.973, 0.985], [0.84, 0.88, 0.92]);
        $this->fillRect(self::MARGIN, $this->cursorY - 38, 6, 38, $this->statusColor($this->transfer->approval_status));
        $this->text(self::MARGIN + 18, $this->cursorY - 15, 'ESTADO DE APROBACIÓN', 6.5, 'F2', [0.42, 0.48, 0.56]);
        $this->text(self::MARGIN + 170, $this->cursorY - 17, mb_strtoupper($label), 10, 'F2', $this->statusColor($this->transfer->approval_status));
        $this->text(410, $this->cursorY - 16, 'Generado '.now()->format('d/m/Y H:i'), 7, 'F1', [0.42, 0.48, 0.56]);
        $this->cursorY -= 52;
    }

    private function section(string $title): void
    {
        $this->ensureSpace(70);
        $this->text(self::MARGIN, $this->cursorY, $title, 9, 'F2', [0.055, 0.18, 0.34]);
        $this->line(self::MARGIN, $this->cursorY - 8, self::MARGIN + self::CONTENT_WIDTH, $this->cursorY - 8, [0.94, 0.68, 0.16], 1.5);
        $this->cursorY -= 24;
    }

    private function keyValueRows(array $rows): void
    {
        foreach ($rows as [$label, $value]) {
            $value = trim((string) ($value ?? '')) ?: '-';
            $lines = $this->wrap($value, 68, 4);
            $height = max(25, 13 + (count($lines) * 10));
            $this->ensureSpace($height);
            $bottom = $this->cursorY - $height;
            $this->fillRect(self::MARGIN, $bottom, 137, $height, [0.945, 0.957, 0.973]);
            $this->fillRect(self::MARGIN + 137, $bottom, self::CONTENT_WIDTH - 137, $height, [1, 1, 1]);
            $this->line(self::MARGIN, $bottom, self::MARGIN + self::CONTENT_WIDTH, $bottom, [0.86, 0.89, 0.93], 0.55);
            $this->text(self::MARGIN + 9, $this->cursorY - 16, $label, 7.4, 'F2', [0.30, 0.38, 0.46], 30);
            foreach ($lines as $index => $line) {
                $this->text(self::MARGIN + 147, $this->cursorY - 16 - ($index * 10), $line, 7.8, 'F1', [0.15, 0.20, 0.27]);
            }
            $this->cursorY = $bottom;
        }
        $this->cursorY -= 10;
    }

    private function metricCards(array $cards): void
    {
        $this->ensureSpace(60);
        $gap = 8;
        $width = (self::CONTENT_WIDTH - ($gap * 2)) / 3;
        foreach ($cards as $index => [$label, $value]) {
            $x = self::MARGIN + (($width + $gap) * $index);
            $this->fillRect($x, $this->cursorY - 48, $width, 48, [0.965, 0.973, 0.985], [0.84, 0.88, 0.92]);
            $this->text($x + 12, $this->cursorY - 15, $label, 7, 'F2', [0.42, 0.48, 0.56]);
            $this->text($x + 12, $this->cursorY - 37, (string) $value, 15, 'F2', [0.055, 0.18, 0.34]);
        }
        $this->cursorY -= 62;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->cursorY - $height < self::BOTTOM) {
            $this->startPage();
        }
    }

    private function watermark(): void
    {
        $mark = match ($this->transfer->approval_status) {
            'borrador' => 'BORRADOR',
            'pendiente_visacion', 'pendiente_administracion', 'observado' => 'EN TRAMITACIÓN',
            'rechazado' => 'RECHAZADO',
            'cancelado' => 'CANCELADO',
            default => '',
        };
        if ($mark !== '') {
            $encoded = $this->escape($mark);
            $this->add('q 0.92 g BT /F2 42 Tf 0.707 0.707 -0.707 0.707 150 310 Tm ('.$encoded.') Tj ET Q');
        }
    }

    private function appendFooters(): void
    {
        $total = count($this->pages);
        foreach ($this->pages as $index => &$commands) {
            $commands[] = $this->lineCommand(self::MARGIN, 40, self::MARGIN + self::CONTENT_WIDTH, 40, [0.84, 0.87, 0.91], 0.6);
            $commands[] = $this->textCommand(self::MARGIN, 23, 'Colegio Nuestra Señora del Carmen · Valdivia · Documento generado por Gestión Operativa', 6.4, 'F1', [0.43, 0.48, 0.56]);
            $commands[] = $this->textCommand(500, 23, 'Página '.($index + 1).' de '.$total, 6.4, 'F2', [0.43, 0.48, 0.56]);
        }
        unset($commands);
    }

    private function decisionLabel(string $decision): string
    {
        return match ($decision) {
            'visado' => 'Visado', 'aprobado' => 'Aprobado', 'observado' => 'Observado', 'rechazado' => 'Rechazado', default => ucfirst($decision)
        };
    }

    private function stepLabel(string $step): string
    {
        return match ($step) {
            'visacion' => 'Visación',
            'administracion' => 'Administración',
            default => ucfirst($step),
        };
    }

    private function label(array $options, ?string $value): string
    {
        return collect($options)->firstWhere('value', $value)['label'] ?? ($value ?: '-');
    }

    private function time(?string $value): string
    {
        return $value ? substr($value, 0, 5) : '-';
    }

    private function statusColor(string $status): array
    {
        return match ($status) {
            'aprobado' => [0.08, 0.52, 0.36],
            'rechazado', 'cancelado' => [0.72, 0.16, 0.20],
            'observado', 'pendiente_visacion', 'pendiente_administracion' => [0.78, 0.48, 0.08],
            default => [0.25, 0.36, 0.54],
        };
    }

    private function wrap(string $value, int $maxChars, int $maxLines): array
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return ['-'];
        }
        $words = preg_split('/\s+/u', $value) ?: [$value];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;

                continue;
            }
            $lines[] = $current;
            $current = $word;
            if (count($lines) >= $maxLines - 1) {
                break;
            }
        }
        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }
        if (implode(' ', $lines) !== $value) {
            $last = count($lines) - 1;
            $lines[$last] = mb_strimwidth($lines[$last], 0, $maxChars - 1, '…');
        }

        return $lines ?: ['-'];
    }

    private function document(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($this->pages as $index => $commands) {
            $pageObject = 5 + ($index * 2);
            $contentObject = $pageObject + 1;
            $kids[] = $pageObject.' 0 R';
            $stream = implode("\n", $commands);
            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /ProcSet [/PDF /Text] /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = '<< /Length '.strlen($stream).">>\nstream\n{$stream}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($this->pages).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $maximum = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maximum + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maximum; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0)."\n";
        }

        return $pdf."trailer\n<< /Size ".($maximum + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function text(float $x, float $y, mixed $value, float $size, string $font = 'F1', array $color = [0, 0, 0], ?int $maxChars = null): void
    {
        $this->add($this->textCommand($x, $y, $value, $size, $font, $color, $maxChars));
    }

    private function textCommand(float $x, float $y, mixed $value, float $size, string $font, array $color, ?int $maxChars = null): string
    {
        $text = trim((string) ($value ?? ''));
        if ($maxChars !== null) {
            $text = mb_strimwidth($text, 0, $maxChars, '…');
        }

        return $this->fillColor($color).' BT /'.$font.' '.$this->number($size).' Tf '.$this->number($x).' '.$this->number($y).' Td ('.$this->escape($text).') Tj ET';
    }

    private function fillRect(float $x, float $y, float $width, float $height, array $fill, ?array $stroke = null): void
    {
        $command = $this->fillColor($fill).' '.$this->number($x).' '.$this->number($y).' '.$this->number($width).' '.$this->number($height).' re f';
        if ($stroke) {
            $command .= ' '.$this->strokeColor($stroke).' 0.6 w '.$this->number($x).' '.$this->number($y).' '.$this->number($width).' '.$this->number($height).' re S';
        }
        $this->add($command);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $color, float $width = 1): void
    {
        $this->add($this->lineCommand($x1, $y1, $x2, $y2, $color, $width));
    }

    private function lineCommand(float $x1, float $y1, float $x2, float $y2, array $color, float $width = 1): string
    {
        return $this->strokeColor($color).' '.$this->number($width).' w '.$this->number($x1).' '.$this->number($y1).' m '.$this->number($x2).' '.$this->number($y2).' l S';
    }

    private function add(string $command): void
    {
        $this->pages[$this->pageIndex][] = $command;
    }

    private function fillColor(array $color): string
    {
        return implode(' ', array_map([$this, 'number'], $color)).' rg';
    }

    private function strokeColor(array $color): string
    {
        return implode(' ', array_map([$this, 'number'], $color)).' RG';
    }

    private function number(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function escape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
    }
}
