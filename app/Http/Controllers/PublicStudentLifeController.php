<?php

namespace App\Http\Controllers;

use App\Models\StudentLifePost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Fluent;

class PublicStudentLifeController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): View
    {
        $publishedPosts = Schema::hasTable('student_life_posts')
            ? $this->publishedPaginator()
            : null;
        $usesEditorialFallback = $publishedPosts === null || $publishedPosts->total() === 0;

        return view('public.pages.students-life', [
            'studentLifePosts' => $usesEditorialFallback
                ? $this->fallbackPaginator()
                : $publishedPosts,
            'usesEditorialFallback' => $usesEditorialFallback,
        ]);
    }

    public function show(string $slug): View
    {
        if (Schema::hasTable('student_life_posts')) {
            $post = StudentLifePost::query()
                ->published()
                ->where('slug', $slug)
                ->with(['galleryImages' => static fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('id')])
                ->first();

            if (! $post && StudentLifePost::query()->published()->exists()) {
                abort(404);
            }

            if ($post) {
                $relatedPosts = StudentLifePost::query()
                    ->published()
                    ->whereKeyNot($post->id)
                    ->orderedForPublic()
                    ->limit(3)
                    ->get();

                return view('public.pages.student-life-show', [
                    'post' => $post,
                    'relatedPosts' => $relatedPosts,
                    'usesEditorialFallback' => false,
                ]);
            }
        }

        $fallbackPosts = $this->fallbackPosts();
        $post = $fallbackPosts->firstWhere('slug', $slug);

        abort_unless($post, 404);

        return view('public.pages.student-life-show', [
            'post' => $post,
            'relatedPosts' => $fallbackPosts
                ->reject(static fn (Fluent $item) => $item->slug === $slug)
                ->take(3)
                ->values(),
            'usesEditorialFallback' => true,
        ]);
    }

    private function publishedPaginator(): LengthAwarePaginatorContract
    {
        return StudentLifePost::query()
            ->published()
            ->orderedForPublic()
            ->paginate(self::PER_PAGE);
    }

    private function fallbackPaginator(): LengthAwarePaginator
    {
        $items = $this->fallbackPosts();

        return new LengthAwarePaginator(
            $items,
            $items->count(),
            self::PER_PAGE,
            1,
            ['path' => route('public.students-life')]
        );
    }

    private function fallbackPosts(): Collection
    {
        return collect([
            [
                'title' => 'Pastoral y vida de fe',
                'slug' => 'pastoral-y-vida-de-fe',
                'category' => 'Pastoral',
                'summary' => 'Encuentros, celebraciones y experiencias que permiten vivir la fe en comunidad y ponerla al servicio de los demás.',
                'body_html' => '<p>La pastoral acompaña la vida cotidiana del colegio mediante espacios de encuentro, oración y celebración que dialogan con cada etapa formativa.</p><p>Estas experiencias invitan a las estudiantes a descubrir una fe cercana, consciente y comprometida, inspirada en el legado de Madre Paulina y expresada en el servicio alegre a los demás.</p>',
                'cover_image_url' => null,
                'cover_image_alt' => null,
                'event_date' => null,
                'featured' => true,
                'icon' => 'bi-stars',
            ],
            [
                'title' => 'Talleres y ACLES',
                'slug' => 'talleres-y-acles',
                'category' => 'Talentos',
                'summary' => 'Instancias complementarias para explorar intereses, desarrollar talentos y fortalecer la participación escolar.',
                'body_html' => '<p>Los talleres y actividades curriculares de libre elección amplían las oportunidades de aprendizaje más allá del aula.</p><p>A través del arte, la cultura, la ciencia y otras áreas de interés, las estudiantes pueden descubrir sus capacidades, compartir con sus pares y asumir nuevos desafíos.</p>',
                'cover_image_url' => null,
                'cover_image_alt' => null,
                'event_date' => null,
                'featured' => false,
                'icon' => 'bi-palette',
            ],
            [
                'title' => 'Deporte y vida saludable',
                'slug' => 'deporte-y-vida-saludable',
                'category' => 'Deporte',
                'summary' => 'Disciplina, colaboración y bienestar mediante experiencias deportivas que fortalecen la formación integral.',
                'body_html' => '<p>La actividad física y el deporte favorecen hábitos saludables, perseverancia y trabajo en equipo.</p><p>Las experiencias deportivas permiten representar al colegio con entusiasmo, aprender del esfuerzo compartido y valorar el cuidado personal y comunitario.</p>',
                'cover_image_url' => null,
                'cover_image_alt' => null,
                'event_date' => null,
                'featured' => false,
                'icon' => 'bi-trophy',
            ],
            [
                'title' => 'Convivencia y participación',
                'slug' => 'convivencia-y-participacion',
                'category' => 'Convivencia',
                'summary' => 'Buen trato, participación responsable y cuidado mutuo para construir una comunidad acogedora y segura.',
                'body_html' => '<p>La convivencia escolar se construye día a día a través del respeto, el diálogo y la participación responsable.</p><p>El colegio promueve espacios en los que cada estudiante pueda sentirse escuchada, acompañada y parte activa de una comunidad que aprende a cuidarse.</p>',
                'cover_image_url' => null,
                'cover_image_alt' => null,
                'event_date' => null,
                'featured' => false,
                'icon' => 'bi-chat-heart',
            ],
            [
                'title' => 'Comunidad solidaria',
                'slug' => 'comunidad-solidaria',
                'category' => 'Servicio',
                'summary' => 'Acciones solidarias que conectan el aprendizaje con las necesidades de otras personas y de nuestro entorno.',
                'body_html' => '<p>El servicio es una expresión concreta del proyecto educativo y del carisma institucional.</p><p>Las campañas, encuentros y acciones comunitarias ayudan a reconocer las necesidades del entorno y a responder a ellas con responsabilidad, empatía y alegría.</p>',
                'cover_image_url' => null,
                'cover_image_alt' => null,
                'event_date' => null,
                'featured' => false,
                'icon' => 'bi-heart',
            ],
            [
                'title' => 'Familias que acompañan',
                'slug' => 'familias-que-acompanan',
                'category' => 'Comunidad',
                'summary' => 'Participación de madres, padres y apoderados para fortalecer el vínculo entre familia, colegio y formación.',
                'body_html' => '<p>La alianza entre familia y colegio es fundamental para acompañar de manera coherente la trayectoria de cada estudiante.</p><p>Reuniones, actividades y espacios de colaboración favorecen una comunicación cercana y una participación activa en la vida de la comunidad educativa.</p>',
                'cover_image_url' => null,
                'cover_image_alt' => null,
                'event_date' => null,
                'featured' => false,
                'icon' => 'bi-house-heart',
            ],
        ])->map(function (array $post): Fluent {
            $post['published_at'] = null;
            $post['public_url'] = route('public.student-life.show', ['slug' => $post['slug']]);
            $post['gallery_images'] = collect();

            return new Fluent($post);
        });
    }
}
