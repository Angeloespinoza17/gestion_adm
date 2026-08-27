<?php

namespace Tests\Unit\PedagogicalManagement;

use App\Exceptions\PedagogicalManagement\PedagogicalInstrumentException;
use App\Services\PedagogicalManagement\PedagogicalInstrumentFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PedagogicalInstrumentFileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_word_doc_is_rejected_even_when_its_signature_is_valid(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pedagogical-doc-');
        file_put_contents($path, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 2048));

        try {
            app(PedagogicalInstrumentFileService::class)->inspect(
                new UploadedFile($path, 'instrumento.doc', 'application/msword', null, true),
                999,
            );
            $this->fail('Los documentos Word no deben ingresar al flujo PDF determinístico.');
        } catch (PedagogicalInstrumentException $exception) {
            $this->assertSame('INSTRUMENT_FILE_INVALID_EXTENSION', $exception->errorCode);
        } finally {
            @unlink($path);
        }
    }

    public function test_plain_text_disguised_as_legacy_word_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pedagogical-fake-doc-');
        file_put_contents($path, 'contenido de texto que no es un documento binario Word');

        try {
            app(PedagogicalInstrumentFileService::class)->inspect(
                new UploadedFile($path, 'instrumento.doc', 'application/msword', null, true),
                999,
            );
            $this->fail('El archivo disfrazado debió ser rechazado.');
        } catch (PedagogicalInstrumentException $exception) {
            $this->assertSame('INSTRUMENT_FILE_INVALID_EXTENSION', $exception->errorCode);
        } finally {
            @unlink($path);
        }
    }
}
