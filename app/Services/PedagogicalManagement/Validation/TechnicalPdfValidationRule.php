<?php

namespace App\Services\PedagogicalManagement\Validation;

use App\Contracts\PedagogicalManagement\InstrumentValidationRule;
use App\DTO\PedagogicalManagement\InstrumentAnalysisContext;

class TechnicalPdfValidationRule implements InstrumentValidationRule
{
    use ValidationRuleSupport;

    public function validate(InstrumentAnalysisContext $context): array
    {
        $file = $context->file;
        $meta = (array) $file->technical_metadata;
        $maxBytes = (int) config('pedagogical_management.storage.max_file_kb', 20480) * 1024;
        $characters = (int) data_get($context->extractedData, 'text_character_count', 0);

        return [
            ($file->mime_type === 'application/pdf')
                ? $this->pass('PDF_INVALID_MIME', 'technical', 'Tipo MIME válido', 'El contenido fue identificado como application/pdf mediante finfo.')
                : $this->finding('PDF_INVALID_MIME', 'technical', 'critical', 'fail', 'exact', 'Tipo MIME inválido', 'El contenido real no corresponde a un PDF.', ['mime' => $file->mime_type], ['mime' => 'application/pdf'], blocking: true),
            (bool) ($meta['signature_valid'] ?? false)
                ? $this->pass('PDF_INVALID_SIGNATURE', 'technical', 'Firma PDF válida', 'El archivo comienza con la firma %PDF-.')
                : $this->finding('PDF_INVALID_SIGNATURE', 'technical', 'critical', 'fail', 'exact', 'Firma PDF inválida', 'No se encontró la firma interna de un PDF.', blocking: true),
            (bool) ($meta['integrity_checked'] ?? false) || $file->is_encrypted
                ? $this->pass('PDF_CORRUPTED', 'technical', 'Integridad comprobada', $file->is_encrypted ? 'El cifrado impide completar la comprobación; se informa por separado.' : 'El extractor interpretó correctamente la estructura del PDF.')
                : $this->finding('PDF_CORRUPTED', 'technical', 'critical', 'fail', 'exact', 'PDF corrupto', 'El extractor no pudo interpretar la estructura del documento.', blocking: true),
            $file->is_encrypted
                ? $this->finding('PDF_ENCRYPTED', 'technical', 'critical', 'fail', 'exact', 'PDF cifrado o protegido', 'El documento se conservó, pero no puede analizarse automáticamente mientras esté cifrado.', blocking: true)
                : $this->pass('PDF_ENCRYPTED', 'technical', 'PDF sin cifrado', 'No se detectó un diccionario /Encrypt.'),
            $file->file_size <= 0
                ? $this->finding('PDF_EMPTY', 'technical', 'critical', 'fail', 'exact', 'Archivo vacío', 'El PDF no contiene bytes.', blocking: true)
                : $this->pass('PDF_EMPTY', 'technical', 'Archivo no vacío', 'El archivo contiene datos.'),
            $file->file_size > $maxBytes
                ? $this->finding('PDF_TOO_LARGE', 'technical', 'critical', 'fail', 'exact', 'PDF demasiado grande', 'El archivo supera el límite configurado.', ['bytes' => $file->file_size], ['max_bytes' => $maxBytes], blocking: true)
                : $this->pass('PDF_TOO_LARGE', 'technical', 'Tamaño permitido', 'El archivo respeta el límite configurado.'),
            $file->has_text_layer === false
                ? $this->finding('PDF_NO_TEXT_LAYER', 'technical', 'critical', 'fail', 'exact', 'PDF sin capa de texto', 'El documento no es evaluable automáticamente porque no contiene texto seleccionable. Debe reemplazarse por un PDF con capa de texto.', blocking: true)
                : ($file->has_text_layer === null
                    ? $this->notEvaluable('PDF_NO_TEXT_LAYER', 'technical', 'Capa de texto no evaluable', 'El cifrado o un error previo impidió verificar la capa de texto.')
                    : $this->pass('PDF_NO_TEXT_LAYER', 'technical', 'Capa de texto disponible', 'El PDF contiene texto extraíble.')),
            $file->page_count === null
                ? $this->finding('PDF_PAGE_COUNT_UNAVAILABLE', 'technical', 'warning', 'not_evaluable', 'not_evaluable', 'Número de páginas no disponible', 'No fue posible determinar el número de páginas de forma confiable.')
                : $this->pass('PDF_PAGE_COUNT_UNAVAILABLE', 'technical', 'Número de páginas disponible', 'Se contabilizaron las páginas del documento.'),
            $this->pass('DUPLICATE_FILE_HASH', 'technical', 'Hash no duplicado', 'La restricción SHA-256 por establecimiento no detectó otra copia.'),
            $this->pass('TEXT_EXTRACTION_FAILED', 'technical', 'Extracción ejecutada', 'El extractor terminó sin una excepción técnica.'),
            $characters === 0
                ? $this->finding('TEXT_EXTRACTION_EMPTY', 'technical', 'critical', 'fail', 'exact', 'Extracción de texto vacía', 'No se obtuvo texto utilizable del PDF.', blocking: true)
                : $this->pass('TEXT_EXTRACTION_EMPTY', 'technical', 'Texto extraído', 'La extracción produjo contenido textual.'),
        ];
    }
}
