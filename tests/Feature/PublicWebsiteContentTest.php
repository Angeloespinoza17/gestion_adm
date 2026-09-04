<?php

namespace Tests\Feature;

use App\Models\StudentLifePost;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_hides_testimonial_section_when_no_real_testimonials_are_published(): void
    {
        $response = $this->get(route('public.home'));

        $response
            ->assertOk()
            ->assertDontSee('id="testimonios"', false)
            ->assertDontSee('María González')
            ->assertSee('Pastoral')
            ->assertSee(route('public.student-life.show', ['slug' => 'pastoral-y-vida-de-fe'], false), false);
    }

    public function test_home_only_renders_authorized_active_testimonials_published_in_the_past(): void
    {
        Testimonial::query()->create([
            'quote' => 'Una experiencia real y cercana.',
            'author_name' => 'Comunidad publicada',
            'author_role' => 'Apoderada',
            'status' => Testimonial::STATUS_PUBLISHED,
            'active' => true,
            'published_at' => now()->subMinute(),
            'consent_confirmed_at' => now()->subMinutes(2),
        ]);

        Testimonial::query()->create([
            'quote' => 'Este borrador no debe aparecer.',
            'author_name' => 'Borrador privado',
            'status' => Testimonial::STATUS_DRAFT,
            'active' => true,
            'consent_confirmed_at' => now()->subMinutes(2),
        ]);

        Testimonial::query()->create([
            'quote' => 'Este testimonio aún no está vigente.',
            'author_name' => 'Publicación futura',
            'status' => Testimonial::STATUS_PUBLISHED,
            'active' => true,
            'published_at' => now()->addDay(),
            'consent_confirmed_at' => now()->subMinutes(2),
        ]);

        Testimonial::query()->create([
            'quote' => 'No existe autorización para publicar esta voz.',
            'author_name' => 'Sin consentimiento',
            'status' => Testimonial::STATUS_PUBLISHED,
            'active' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('id="testimonios"', false)
            ->assertSee('Una experiencia real y cercana.')
            ->assertSee('Comunidad publicada')
            ->assertDontSee('Borrador privado')
            ->assertDontSee('Publicación futura')
            ->assertDontSee('Sin consentimiento')
            ->assertDontSee('No existe autorización para publicar esta voz.');
    }

    public function test_student_life_index_and_home_use_published_content_instead_of_editorial_fallback(): void
    {
        $published = StudentLifePost::query()->create([
            'title' => 'Encuentro pastoral 2026',
            'slug' => 'encuentro-pastoral-2026',
            'category' => 'Pastoral',
            'summary' => 'Una jornada de encuentro para toda la comunidad.',
            'body' => '<p>Contenido de la experiencia.</p>',
            'status' => StudentLifePost::STATUS_PUBLISHED,
            'active' => true,
            'featured' => true,
            'published_at' => now()->subMinute(),
        ]);

        StudentLifePost::query()->create([
            'title' => 'Encuentro deportivo',
            'slug' => 'encuentro-deportivo',
            'category' => 'Deporte',
            'summary' => 'Otra experiencia pública.',
            'body' => '<p>Actividad complementaria.</p>',
            'status' => StudentLifePost::STATUS_PUBLISHED,
            'active' => true,
            'published_at' => now()->subMinutes(2),
        ]);

        StudentLifePost::query()->create([
            'title' => 'Actividad todavía en borrador',
            'slug' => 'actividad-en-borrador',
            'summary' => 'No debe ser pública.',
            'status' => StudentLifePost::STATUS_DRAFT,
            'active' => true,
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertSee($published->public_url, false)
            ->assertDontSee('Actividad todavía en borrador');

        $this->get(route('public.students-life'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertSee('Actividades y experiencias publicadas por nuestra comunidad educativa.')
            ->assertSee('Una comunidad para aprender, participar y servir')
            ->assertDontSee('Conversemos sobre el camino educativo de tu familia')
            ->assertDontSee('Actividad todavía en borrador')
            ->assertDontSee('Familias que acompañan');
    }

    public function test_student_life_detail_is_premium_sanitized_and_never_exposes_drafts(): void
    {
        $published = StudentLifePost::query()->create([
            'title' => 'Festival de talentos',
            'slug' => 'festival-de-talentos',
            'category' => 'Talentos',
            'summary' => 'Una experiencia para compartir capacidades e intereses.',
            'body' => '<h2>Una jornada especial</h2><p>Contenido visible.</p><script>alert("x")</script>',
            'status' => StudentLifePost::STATUS_PUBLISHED,
            'active' => true,
            'published_at' => now()->subMinute(),
        ]);

        StudentLifePost::query()->create([
            'title' => 'Encuentro deportivo',
            'slug' => 'encuentro-deportivo',
            'category' => 'Deporte',
            'summary' => 'Otra experiencia pública.',
            'body' => '<p>Actividad complementaria.</p>',
            'status' => StudentLifePost::STATUS_PUBLISHED,
            'active' => true,
            'published_at' => now()->subMinutes(2),
        ]);

        StudentLifePost::query()->create([
            'title' => 'Experiencia reservada',
            'slug' => 'experiencia-reservada',
            'summary' => 'No publicada.',
            'body' => '<p>Contenido privado.</p>',
            'status' => StudentLifePost::STATUS_DRAFT,
            'active' => true,
        ]);

        $this->get(route('public.student-life.show', ['slug' => $published->slug]))
            ->assertOk()
            ->assertSee('student-life-detail-hero', false)
            ->assertSee('Una jornada especial')
            ->assertSee('Contenido visible.')
            ->assertDontSee('<script>', false)
            ->assertSee('Conversemos sobre el camino educativo de tu familia')
            ->assertSee('Otras experiencias', false);

        $this->get(route('public.student-life.show', ['slug' => 'experiencia-reservada']))
            ->assertNotFound();
    }

    public function test_editorial_student_life_fallback_has_working_index_and_detail_routes(): void
    {
        $this->get(route('public.students-life'))
            ->assertOk()
            ->assertSee('Pastoral y vida de fe')
            ->assertSee('Una mirada a los principales ámbitos que dan forma a nuestra vida estudiantil.');

        $this->get(route('public.student-life.show', ['slug' => 'pastoral-y-vida-de-fe']))
            ->assertOk()
            ->assertSee('Pastoral y vida de fe')
            ->assertSee('student-life-detail-body', false);

        $this->get(route('public.student-life.show', ['slug' => 'contenido-inexistente']))
            ->assertNotFound();
    }
}
