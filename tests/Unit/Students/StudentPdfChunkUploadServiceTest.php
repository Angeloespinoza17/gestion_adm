<?php

namespace Tests\Unit\Students;

use App\Services\Students\StudentPdfChunkUploadService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentPdfChunkUploadServiceTest extends TestCase
{
    public function test_it_assembles_and_cleans_a_pdf_uploaded_in_chunks(): void
    {
        Storage::fake('local');

        $service = new StudentPdfChunkUploadService;
        $contents = '%PDF-'.str_repeat('a', StudentPdfChunkUploadService::MAX_CHUNK_BYTES + 100);
        $metadata = [
            'upload_id' => '12345678-1234-1234-1234-123456789012',
            'chunk_total' => 2,
            'file_name' => 'segundo-libro.pdf',
            'file_size' => strlen($contents),
            'course_section_id' => null,
        ];

        $first = $service->receive(42, array_merge($metadata, ['chunk_index' => 0]), substr(
            $contents,
            0,
            StudentPdfChunkUploadService::MAX_CHUNK_BYTES,
        ));
        $second = $service->receive(42, array_merge($metadata, ['chunk_index' => 1]), substr(
            $contents,
            StudentPdfChunkUploadService::MAX_CHUNK_BYTES,
        ));

        $this->assertFalse($first['completed']);
        $this->assertTrue($second['completed']);
        $this->assertSame($contents, file_get_contents($second['path']));

        $service->cleanup($second['directory']);

        Storage::disk('local')->assertMissing($second['directory']);
    }
}
