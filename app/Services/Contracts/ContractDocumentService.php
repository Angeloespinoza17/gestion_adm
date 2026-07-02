<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractSignature;
use Illuminate\Support\Facades\Storage;

class ContractDocumentService
{
    public function saveWordDocument(Contract $contract): string
    {
        $directory = sprintf('contracts/%d', $contract->id);
        $filename = 'contrato_' . now()->format('Ymd_His') . '.doc';
        $path = $directory . '/' . $filename;

        Storage::disk('public')->put($path, $this->buildWordHtml($contract));

        return $path;
    }

    public function buildWordHtml(Contract $contract): string
    {
        $paragraphs = collect(preg_split("/\n{2,}/", (string) $contract->rendered_content))
            ->map(fn ($paragraph) => trim((string) $paragraph))
            ->filter()
            ->map(function (string $paragraph) {
                return '<p style="margin:0 0 14px 0; line-height:1.55;">' .
                    nl2br(e($paragraph)) .
                    '</p>';
            })
            ->implode("\n");

        $signatures = $contract->signatures
            ->sortBy('sort_order')
            ->map(fn (ContractSignature $signature) => $this->renderSignatureHtml($signature))
            ->implode("\n");

        return '<html xmlns:o="urn:schemas-microsoft-com:office:office"
            xmlns:w="urn:schemas-microsoft-com:office:word"
            xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <title>Contrato</title>
            </head>
            <body style="font-family:Arial, Helvetica, sans-serif; font-size:12pt; color:#212529;">
                <h2 style="text-align:center; margin:0 0 18px 0;">' . e($contract->template?->name ?: 'Contrato de trabajo') . '</h2>
                ' . $paragraphs . '
                <div style="margin-top:40px;">' . $signatures . '</div>
            </body>
        </html>';
    }

    private function renderSignatureHtml(ContractSignature $signature): string
    {
        $image = '';

        if ($signature->use_signature_image && $signature->signature_image_path && Storage::disk('public')->exists($signature->signature_image_path)) {
            $contents = Storage::disk('public')->get($signature->signature_image_path);
            $mime = Storage::disk('public')->mimeType($signature->signature_image_path) ?: 'image/png';
            $image = '<div style="height:72px; margin-bottom:8px;">
                <img src="data:' . e($mime) . ';base64,' . base64_encode($contents) . '" style="max-height:72px; max-width:180px;" />
            </div>';
        }

        return '<div style="display:inline-block; width:45%; vertical-align:top; text-align:center; margin:16px 2.5% 0 2.5%;">
            ' . $image . '
            <div style="border-top:1px solid #000; padding-top:8px;">' .
            '<strong>' . e((string) $signature->name) . '</strong><br>' .
            e((string) ($signature->rut ?: '')) . '<br>' .
            e((string) ($signature->position ?: '')) .
            '</div>
        </div>';
    }
}
