<?php

namespace Tests\Feature;

use App\Models\ManagedDocument;
use App\Services\PublicEducationalProjectDocumentResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicEducationalProjectDocumentResolverTest extends TestCase
{
    public function test_it_selects_the_latest_active_public_pdf_without_exposing_its_private_path(): void
    {
        Storage::fake('local');
        Schema::create('managed_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('category');
            $table->unsignedSmallInteger('year');
            $table->string('version')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->boolean('is_public');
            $table->boolean('is_active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        try {
            $older = $this->createDocument('PEI 2024', 2024, 'documentation/proyecto-educativo/2024/pei.pdf');
            $current = $this->createDocument('PEI 2026 vigente', 2026, 'documentation/proyecto-educativo/2026/pei.pdf');
            $this->createDocument('PEI 2027 sin archivo', 2027, 'documentation/proyecto-educativo/2027/extraviado.pdf');
            $this->createDocument('PEI privado 2028', 2028, 'documentation/proyecto-educativo/2028/privado.pdf', isPublic: false);
            $this->createDocument('PEI inactivo 2029', 2029, 'documentation/proyecto-educativo/2029/inactivo.pdf', isActive: false);
            $this->createDocument('Anexo Word 2030', 2030, 'documentation/proyecto-educativo/2030/anexo.docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

            Storage::disk('local')->put($older->file_path, '%PDF-1.7 older');
            Storage::disk('local')->put($current->file_path, '%PDF-1.7 current');

            $metadata = app(PublicEducationalProjectDocumentResolver::class)->metadata();

            $this->assertSame('PEI 2026 vigente', $metadata['title']);
            $this->assertSame(2026, $metadata['year']);
            $this->assertTrue($metadata['is_managed']);
            $this->assertTrue($metadata['download_available']);
            $this->assertArrayNotHasKey('path', $metadata);
            $this->assertArrayNotHasKey('id', $metadata);
        } finally {
            Schema::dropIfExists('managed_documents');
        }
    }

    private function createDocument(
        string $title,
        int $year,
        string $path,
        bool $isPublic = true,
        bool $isActive = true,
        string $mimeType = 'application/pdf',
    ): ManagedDocument {
        return ManagedDocument::query()->create([
            'title' => $title,
            'category' => ManagedDocument::CATEGORY_EDUCATIONAL_PROJECT,
            'year' => $year,
            'version' => '1.0',
            'description' => 'Documento de prueba.',
            'file_path' => $path,
            'original_name' => basename($path),
            'mime_type' => $mimeType,
            'file_size' => 2048,
            'is_public' => $isPublic,
            'is_active' => $isActive,
        ]);
    }
}
