<?php

namespace Tests\Feature;

use App\Services\PublicSiteOrganizationResolver;
use Mockery\MockInterface;
use Tests\TestCase;

class PublicCommunityOrganizationTest extends TestCase
{
    public function test_school_menu_links_the_three_public_organization_pages(): void
    {
        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('CGPA · Centro General de Padres y Apoderados')
            ->assertSee('CDE · Centro de Estudiantes')
            ->assertSee('Comité Paritario')
            ->assertSee(route('public.cgpa'), false)
            ->assertSee(route('public.cde'), false)
            ->assertSee(route('public.joint-committee'), false);
    }

    public function test_cgpa_page_only_projects_public_safe_member_fields(): void
    {
        $this->mockLatest('cgpa', [
            'type' => 'cgpa',
            'id' => 41,
            'name' => 'Directiva CGPA 2026',
            'year' => 2026,
            'starts_on' => null,
            'ends_on' => null,
            'summary' => 'Representación de las familias durante el año escolar.',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'rut' => '11.111.111-1',
            'members' => [[
                'display_name' => 'Nombre autorizado CGPA',
                'position_name' => 'Presidenta',
                'section' => 'Directiva',
                'sort_order' => 1,
                'email' => 'privado@example.test',
                'phone' => '+56911111111',
            ]],
        ]);

        $this->get(route('public.cgpa'))
            ->assertOk()
            ->assertViewIs('public.pages.community-organization')
            ->assertSee('Centro General de Padres y Apoderados')
            ->assertSee('Directiva CGPA 2026')
            ->assertSee('Nombre autorizado CGPA')
            ->assertSee('Presidenta')
            ->assertDontSee('11.111.111-1')
            ->assertDontSee('privado@example.test')
            ->assertDontSee('+56911111111');
    }

    public function test_cde_page_presents_authorized_students_and_advisers_with_safe_course_context(): void
    {
        $this->mockLatest('cde', [
            'type' => 'cde',
            'id' => 42,
            'name' => 'Directiva CDE 2026',
            'year' => 2026,
            'starts_on' => null,
            'ends_on' => null,
            'summary' => null,
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'members' => [
                [
                    'display_name' => 'Estudiante autorizada',
                    'position_name' => 'Presidenta',
                    'section' => 'Directiva estudiantil',
                    'sort_order' => 1,
                    'course_label' => '4° Medio A',
                ],
                [
                    'display_name' => 'Asesora autorizada',
                    'position_name' => 'Docente asesora',
                    'section' => 'Asesoría',
                    'sort_order' => 2,
                ],
            ],
        ]);

        $this->get(route('public.cde'))
            ->assertOk()
            ->assertSee('Centro de Estudiantes')
            ->assertSee('Estudiante autorizada')
            ->assertSee('4° Medio A')
            ->assertSee('Asesora autorizada')
            ->assertSee('Docente asesora');
    }

    public function test_joint_committee_page_presents_the_published_version_and_representation(): void
    {
        $this->mockLatest('joint_committee', [
            'type' => 'joint_committee',
            'id' => 9,
            'name' => 'Comité Paritario 2025–2027',
            'year' => null,
            'starts_on' => '2025-03-01',
            'ends_on' => '2027-02-28',
            'summary' => 'Versión pública del comité vigente.',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'members' => [[
                'display_name' => 'Representante autorizada',
                'position_name' => 'Titular',
                'section' => 'Representantes de trabajadores',
                'representation' => 'trabajadores',
                'member_role' => 'titular',
                'sort_order' => 1,
            ]],
        ]);

        $this->get(route('public.joint-committee'))
            ->assertOk()
            ->assertSee('Comité Paritario de Higiene y Seguridad')
            ->assertSee('Comité Paritario 2025–2027')
            ->assertSee('1 marzo 2025 — 28 febrero 2027')
            ->assertSee('Representantes de trabajadores')
            ->assertSee('Representante autorizada');
    }

    public function test_pages_show_an_institutional_empty_state_without_placeholder_people(): void
    {
        $this->mockLatest('cgpa', null);

        $this->get(route('public.cgpa'))
            ->assertOk()
            ->assertSee('Próxima actualización')
            ->assertSee('La directiva pública del CGPA aún no ha sido publicada')
            ->assertDontSee('Nombre de ejemplo')
            ->assertDontSee('Presidenta por definir');
    }

    /** @param array<string, mixed>|null $payload */
    private function mockLatest(string $type, ?array $payload): void
    {
        $this->mock(PublicSiteOrganizationResolver::class, function (MockInterface $mock) use ($type, $payload): void {
            $mock->shouldReceive('latest')
                ->once()
                ->with($type)
                ->andReturn($payload);
        });
    }
}
