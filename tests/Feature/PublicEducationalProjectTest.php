<?php

namespace Tests\Feature;

use App\Services\PublicEducationalProjectDocumentResolver;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class PublicEducationalProjectTest extends TestCase
{
    public function test_public_page_presents_the_simplified_project_and_document_metadata(): void
    {
        $this->mock(PublicEducationalProjectDocumentResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('metadata')->once()->andReturn([
                'title' => 'Proyecto Educativo Institucional CNSC',
                'year' => 2026,
                'version' => '2.1',
                'description' => 'Versión pública vigente.',
                'file_name' => 'pei-cnsc-2026.pdf',
                'file_size' => 1572864,
                'is_managed' => true,
                'download_available' => true,
            ]);
        });

        $this->get(route('public.educational-project'))
            ->assertOk()
            ->assertViewIs('public.pages.educational-project')
            ->assertSee('Proyecto Educativo Institucional CNSC')
            ->assertSee('Año 2026')
            ->assertSee('Versión 2.1')
            ->assertSee('1,5 MB')
            ->assertSee('Síntesis PEI 2023')
            ->assertSee('Esta síntesis corresponde al PEI base 2023. La descarga contiene la versión pública vigente registrada para 2026.')
            ->assertSee('Visión, misión y carisma')
            ->assertSee('Caridad, Fe y Verdad.')
            ->assertSee('La estudiante que buscamos formar')
            ->assertSee('cada una de nuestras estudiantes')
            ->assertDontSee('El estudiante que buscamos formar')
            ->assertSee('«El amor sea el móvil de tu actuar»')
            ->assertSee('Descargar proyecto')
            ->assertSee(route('public.educational-project.download'), false)
            ->assertDontSee('site-invitation', false);
    }

    public function test_main_navigation_and_invitation_link_to_the_educational_project(): void
    {
        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('Proyecto educativo')
            ->assertSee('Conoce nuestro proyecto educativo')
            ->assertSee(route('public.educational-project'), false);
    }

    public function test_download_route_delegates_to_the_safe_document_resolver(): void
    {
        $this->mock(PublicEducationalProjectDocumentResolver::class, function (MockInterface $mock): void {
            $mock->shouldReceive('download')->once()->andReturn(new StreamedResponse(
                static function (): void {
                    echo '%PDF-1.7';
                },
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="proyecto-educativo.pdf"',
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'private, no-store',
                ],
            ));
        });

        $this->get(route('public.educational-project.download'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="proyecto-educativo.pdf"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_packaged_pdf_is_available_as_a_download_fallback_without_database_records(): void
    {
        $this->get(route('public.educational-project.download'))
            ->assertOk()
            ->assertDownload('proyecto-educativo-cnsc-2023.pdf')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
