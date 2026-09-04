@extends('public.layouts.site')

@section('body_class', 'index-page')
@section('title', 'Colegio Nuestra Señora del Carmen | Valdivia')
@section('description', 'Sitio institucional del Colegio Nuestra Señora del Carmen de Valdivia, comunidad educativa pastoral inspirada en Madre Paulina.')

@section('content')
  <section id="hero" class="hero section dark-background">
    <div class="hero-container">
      <video autoplay muted loop playsinline preload="metadata" poster="{{ asset('niceschool/assets/img/education/hero-poster.webp') }}" class="video-background" aria-hidden="true" tabindex="-1">
        <source src="{{ asset('niceschool/assets/img/education/video-2-web.mp4') }}" type="video/mp4" media="(min-width: 768px)">
      </video>
      <div class="overlay" aria-hidden="true"></div>
      <div class="container">
        <div class="row align-items-center hero-grid">
          <div class="col-lg-7" data-aos="zoom-out" data-aos-delay="100">
            <div class="hero-content">
              <p class="hero-eyebrow">
                <i class="bi bi-stars" aria-hidden="true"></i>
                <span>Desde 1907 · Valdivia</span>
              </p>
              <h1>"Que el amor sea el móvil de tu actuar"</h1>
              <div class="hero-attribution">
                <p class="hero-author">Madre Paulina von Mallinckrodt</p>
                <p class="hero-context">Una inspiración que orienta nuestra formación académica, humana y pastoral.</p>
              </div>

              <div class="cta-buttons hero-actions" aria-label="Accesos principales">
                <a href="{{ route('public.educational-project') }}" class="btn-primary">
                  <span>Conoce nuestro proyecto educativo</span>
                  <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
                <a href="{{ route('public.admissions') }}" class="btn-secondary">
                  <span>Conoce admisión</span>
                  <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
              </div>

              <ul class="hero-identity-chips list-unstyled" aria-label="Sellos de nuestra identidad">
                <li><i class="bi bi-book" aria-hidden="true"></i> Educación humanista cristiana</li>
                <li><i class="bi bi-heart" aria-hidden="true"></i> Formación integral</li>
                <li><i class="bi bi-people" aria-hidden="true"></i> Comunidad pastoral</li>
              </ul>
            </div>
          </div>
          <div class="col-lg-5" data-aos="zoom-out" data-aos-delay="200">
            <article class="school-crest-card" aria-labelledby="crest-card-title">
              <p class="crest-kicker">Identidad CNSC</p>
              <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="Logo Colegio Nuestra Señora del Carmen" width="300" height="322">
              <h2 id="crest-card-title" class="crest-title">Adelante con Valor y Alegría</h2>
              <p class="crest-description">Identidad, fe, servicio y comunidad al centro del proyecto educativo.</p>
              <ul class="crest-chips list-unstyled" aria-label="Valores institucionales">
                <li>Fe</li>
                <li>Servicio</li>
                <li>Alegría</li>
              </ul>
            </article>
          </div>
        </div>
      </div>
    </div>

    <aside class="event-ticker" aria-labelledby="hero-highlights-title">
      <div class="container">
        <h2 id="hero-highlights-title" class="visually-hidden">Información destacada del colegio</h2>
        <ul class="event-ticker__list list-unstyled mb-0">
          <li class="ticker-item">
            <span class="date">1907</span>
            <span class="title">Más de un siglo de historia</span>
            <a href="{{ route('public.about') }}" class="btn-register" aria-label="Conocer la historia del colegio">
              <span>Nosotros</span>
              <i class="bi bi-arrow-up-right btn-register__icon" aria-hidden="true"></i>
            </a>
          </li>
          <li class="ticker-item">
            <span class="date">CNSC</span>
            <span class="title">Educación humanista y cristiana</span>
            <a href="{{ route('public.admissions') }}" class="btn-register" aria-label="Conocer el proceso de admisión">
              <span>Admisión</span>
              <i class="bi bi-arrow-up-right btn-register__icon" aria-hidden="true"></i>
            </a>
          </li>
          <li class="ticker-item">
            <span class="date">VAL</span>
            <span class="title">Eleuterio Ramírez #1886</span>
            <a href="{{ route('public.contact') }}" class="btn-register" aria-label="Ver información de contacto y ubicación">
              <span>Contacto</span>
              <i class="bi bi-arrow-up-right btn-register__icon" aria-hidden="true"></i>
            </a>
          </li>
        </ul>
      </div>
    </aside>
  </section>

  <section id="colegio" class="about section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row mb-5 align-items-center">
        <div class="col-lg-6 pe-lg-5" data-aos="fade-right" data-aos-delay="200">
          <h2 class="display-6 fw-bold mb-4">Una comunidad educativa <span>con historia y vocación de servicio</span></h2>
          <p class="lead mb-4">Fundado el 7 de noviembre de 1907, el Colegio Nuestra Señora del Carmen mantiene una tradición formativa ligada a la Congregación de la Inmaculada Concepción y al legado de Madre Paulina.</p>
          <p>Su proyecto educativo busca formar estudiantes con preparación académica y valórica, promoviendo el servicio, la responsabilidad, la fe y la participación consciente en la sociedad.</p>
          <div class="d-flex flex-wrap gap-4 mb-4">
            <div class="stat-box">
              <span class="stat-number"><span data-purecounter-start="0" data-purecounter-end="118" data-purecounter-duration="1" class="purecounter"></span>+</span>
              <span class="stat-label">Años</span>
            </div>
            <div class="stat-box">
              <span class="stat-number"><span data-purecounter-start="0" data-purecounter-end="1000" data-purecounter-duration="1" class="purecounter"></span>+</span>
              <span class="stat-label">Estudiantes</span>
            </div>
            <div class="stat-box">
              <span class="stat-number"><span data-purecounter-start="0" data-purecounter-end="60" data-purecounter-duration="1" class="purecounter"></span>+</span>
              <span class="stat-label">Docentes</span>
            </div>
          </div>
          <a href="{{ route('public.about') }}" class="btn btn-primary">Conócenos</a>
        </div>
        <div class="col-lg-6" data-aos="fade-left" data-aos-delay="300">
          <div class="about-single-image">
            <img src="{{ asset('niceschool/assets/img/education/hero-poster.webp') }}" alt="Estudiantes en un entorno educativo" class="img-fluid rounded-4 shadow-lg" loading="lazy" decoding="async">
          </div>
        </div>
      </div>

      <div class="row mission-vision-row g-4">
        <div class="col-md-4">
          <div class="value-card h-100">
            <div class="card-icon">
              <i class="bi bi-rocket-takeoff"></i>
            </div>
            <h3>Nuestra misión</h3>
            <p>Formar niños, niñas y jóvenes con una sólida preparación cristiano-católica, académica y valórica, mediante una educación humanista, inspirada en el legado de Madre Paulina de “servir a los demás” y basada en un proceso de aprendizaje-enseñanza que promueva el desarrollo de competencias, habilidades, valores y actitudes, que les permitan aportar como personas y ciudadanos a la sociedad, al mundo y a la Iglesia de acuerdo a los nuevos desafíos.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="value-card h-100">
            <div class="card-icon">
              <i class="bi bi-eye"></i>
            </div>
            <h3>Nuestra visión</h3>
            <p>Queremos hacer de nuestro Colegio una comunidad de Fe y Cultura, trabajando en la construcción de la civilización del amor, conforme al mensaje de Cristo, con María Inmaculada y el ideario de la Madre Paulina.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="value-card h-100">
            <div class="card-icon">
              <i class="bi bi-star"></i>
            </div>
            <h3>Carisma educacional</h3>
            <p>La Congregación de las Hermanas de la Caridad Cristiana, Hijas de la Bienaventurada Virgen María de la Inmaculada Concepción, a través de la educación, quiere formar a niños, niñas y jóvenes para un servicio alegre y cordial a la Iglesia, al mundo y a la sociedad, fundamentado en un sólido espíritu eucarístico y mariano; es decir, que sus estudiantes desarrollen, para toda su vida, la capacidad de un servicio real y alegre a los demás, fruto de la comunión con Jesús en la Eucaristía, siendo capaces de enfrentar con fortaleza las dificultades y la adversidad.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="sellos" class="seals section soft-band">
    <div class="container section-title" data-aos="fade-up">
      <h2>Nuestros sellos</h2>
      <p>Identidades que orientan nuestra misión educativa y pastoral.</p>
    </div>

    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row g-4">
        <x-public.sellos />
      </div>
    </div>
  </section>

  @if($testimonials->isNotEmpty())
    <section id="testimonios" class="testimonials section" aria-labelledby="testimonials-title">
      <div class="container section-title" data-aos="fade-up">
        <span class="section-eyebrow">Comunidad CNSC</span>
        <h2 id="testimonials-title">Testimonios</h2>
        <p>Voces reales de estudiantes, familias y miembros de nuestra comunidad educativa.</p>
      </div>

      <div class="container">
        <div class="testimonial-masonry">
          @foreach($testimonials as $testimonial)
            @php
              $initials = collect(preg_split('/\s+/', trim($testimonial->author_name)))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
            @endphp
            <article class="testimonial-item {{ $testimonial->featured ? 'highlight' : '' }}">
              <div class="testimonial-content">
                <div class="quote-pattern" aria-hidden="true">
                  <i class="bi bi-quote"></i>
                </div>
                <blockquote>
                  <p>{{ $testimonial->quote }}</p>
                </blockquote>
                <div class="client-info">
                  <div class="client-image">
                    @if($testimonial->image_url)
                      <img
                        src="{{ $testimonial->image_url }}"
                        alt="{{ $testimonial->image_alt ?: 'Retrato de '.$testimonial->author_name }}"
                        width="64"
                        height="64"
                        loading="lazy"
                        decoding="async"
                      >
                    @else
                      <span class="client-image__initials" aria-hidden="true">{{ $initials ?: 'C' }}</span>
                    @endif
                  </div>
                  <div class="client-details">
                    <h3>{{ $testimonial->author_name }}</h3>
                    <span class="position">{{ $testimonial->author_role }}</span>
                  </div>
                </div>
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  <section id="students-life-block" class="students-life-block section">
    <div class="container section-title" data-aos="fade-up">
      <span class="section-eyebrow">Más allá del aula</span>
      <h2>Vida estudiantil</h2>
      <p>Experiencias que complementan la formación académica y fortalecen comunidad, fe y alegría.</p>
    </div>

    <div class="container">
      @if($studentLifeHighlights->isNotEmpty())
        @php
          $featuredStudentLife = $studentLifeHighlights->firstWhere('featured', true) ?: $studentLifeHighlights->first();
          $secondaryStudentLife = $studentLifeHighlights->reject(fn ($item) => $item->id === $featuredStudentLife->id)->take(3);
        @endphp
        <div class="home-student-life-layout">
          <article class="home-student-life-feature">
            <a href="{{ $featuredStudentLife->public_url }}" class="home-student-life-feature__media" aria-label="Conocer: {{ $featuredStudentLife->title }}">
              @if($featuredStudentLife->cover_image_url)
                <img
                  src="{{ $featuredStudentLife->cover_image_url }}"
                  alt="{{ $featuredStudentLife->cover_image_alt ?: $featuredStudentLife->title }}"
                  loading="lazy"
                  decoding="async"
                >
              @else
                <span class="student-life-media-placeholder" aria-hidden="true">
                  <i class="bi bi-stars"></i>
                  <span>Vida CNSC</span>
                </span>
              @endif
            </a>
            <div class="home-student-life-feature__body">
              <span class="student-life-category">{{ $featuredStudentLife->category ?: 'Vida estudiantil' }}</span>
              <h3><a href="{{ $featuredStudentLife->public_url }}">{{ $featuredStudentLife->title }}</a></h3>
              <p>{{ $featuredStudentLife->summary }}</p>
              <a href="{{ $featuredStudentLife->public_url }}" class="student-life-inline-link">
                Conocer esta experiencia <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
              </a>
            </div>
          </article>

          <div class="home-student-life-list" aria-label="Otras experiencias estudiantiles">
            @foreach($secondaryStudentLife as $studentLifePost)
              <article class="home-student-life-list__item">
                <span class="student-life-list-icon" aria-hidden="true"><i class="bi bi-compass"></i></span>
                <div>
                  <span class="student-life-category">{{ $studentLifePost->category ?: 'Comunidad' }}</span>
                  <h3><a href="{{ $studentLifePost->public_url }}">{{ $studentLifePost->title }}</a></h3>
                  <p>{{ Illuminate\Support\Str::limit($studentLifePost->summary, 105) }}</p>
                </div>
                <a href="{{ $studentLifePost->public_url }}" class="student-life-list-arrow" aria-label="Conocer: {{ $studentLifePost->title }}">
                  <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </a>
              </article>
            @endforeach
          </div>
        </div>
      @else
        <div class="home-student-life-fallback" aria-label="Ámbitos de la vida estudiantil">
          @foreach([
            ['Pastoral', 'Encuentros y celebraciones que acompañan la experiencia de fe.', 'bi-stars', 'pastoral-y-vida-de-fe'],
            ['Talleres y ACLES', 'Espacios para descubrir talentos, intereses y nuevas formas de expresión.', 'bi-palette', 'talleres-y-acles'],
            ['Deporte', 'Disciplina, vida saludable y colaboración a través de la actividad física.', 'bi-trophy', 'deporte-y-vida-saludable'],
            ['Convivencia', 'Participación, buen trato y cuidado mutuo en la vida cotidiana.', 'bi-chat-heart', 'convivencia-y-participacion'],
          ] as [$title, $summary, $icon, $slug])
            <article class="home-student-life-fallback__item">
              <span aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
              <h3><a href="{{ route('public.student-life.show', ['slug' => $slug]) }}">{{ $title }}</a></h3>
              <p>{{ $summary }}</p>
              <a href="{{ route('public.student-life.show', ['slug' => $slug]) }}" aria-label="Conocer: {{ $title }}">
                Explorar <i class="bi bi-arrow-right" aria-hidden="true"></i>
              </a>
            </article>
          @endforeach
        </div>
      @endif

      <div class="students-life-cta text-center">
        <a href="{{ route('public.students-life') }}" class="btn btn-primary">Ver toda la vida estudiantil</a>
      </div>
    </div>
  </section>

  <section id="vida-escolar" class="featured-programs section">
    <div class="container section-title" data-aos="fade-up">
      <h2>Áreas de gestión</h2>
      <p>Áreas que articulan el acompañamiento académico, formativo, pastoral, comunitario y administrativo.</p>
    </div>
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-3 col-md-6">
          <div class="school-card">
            <i class="bi bi-building-check"></i>
            <h3>Dirección</h3>
            <p>Equipo directivo y gestión institucional al servicio de la comunidad educativa.</p>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="school-card">
            <i class="bi bi-journal-bookmark"></i>
            <h3>UTP</h3>
            <p>Acompañamiento pedagógico, actividades curriculares y apoyo al aprendizaje.</p>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="school-card">
            <i class="bi bi-people"></i>
            <h3>Formación</h3>
            <p>Convivencia escolar, profesores jefes y desarrollo integral de las estudiantes.</p>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="school-card">
            <i class="bi bi-brightness-high"></i>
            <h3>Pastoral</h3>
            <p>Encuentro, oración, sacramentos y compromiso con la comunidad.</p>
          </div>
        </div>
      </div>
      <div class="row justify-content-center gy-4 mt-1">
        <div class="col-lg-3 col-md-6">
          <div class="school-card">
            <i class="bi bi-clipboard-check"></i>
            <h3>Administración</h3>
            <p>Gestión de recursos, procesos internos y apoyo operativo al proyecto educativo.</p>
          </div>
        </div>
      </div>
      <div class="text-center mt-5" data-aos="fade-up" data-aos-delay="500">
        <a href="{{ route('public.students-life') }}" class="btn btn-primary btn-lg">Ver vida estudiantil</a>
      </div>
    </div>
  </section>

  <section id="recent-news" class="recent-news section">
    <div class="container section-title" data-aos="fade-up">
      <h2>Noticias recientes</h2>
      <p>La actividad escolar se expresa en pastoral, deporte, encuentros familiares y participación de todos los estamentos.</p>
    </div>

    <div class="container" data-aos="fade-up" data-aos-delay="100">
      @if($latestNews->isNotEmpty())
        <div id="recentNewsCarousel" class="carousel slide recent-news-carousel" data-bs-ride="carousel" data-bs-interval="6500">
          <div class="carousel-indicators">
            @foreach($latestNews as $post)
              <button
                type="button"
                data-bs-target="#recentNewsCarousel"
                data-bs-slide-to="{{ $loop->index }}"
                class="{{ $loop->first ? 'active' : '' }}"
                aria-current="{{ $loop->first ? 'true' : 'false' }}"
                aria-label="Noticia {{ $loop->iteration }}"
              ></button>
            @endforeach
          </div>

          <div class="carousel-inner">
            @foreach($latestNews as $post)
              @php
                $publishedAt = $post->published_at?->copy()->locale('es');
                $fallbackImage = asset('niceschool/assets/img/blog/blog-post-' . ((($loop->iteration - 1) % 3) + 1) . '.webp');
                $summary = \Illuminate\Support\Str::limit(strip_tags($post->excerpt ?: $post->body_html ?: $post->body ?: ''), 180);
              @endphp
              <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                <article class="recent-news-slide">
                  <div class="row g-0 align-items-stretch">
                    <div class="col-lg-6">
                      <a href="{{ route('public.news.show', $post) }}" class="recent-news-image-link">
                        <img
                          src="{{ $post->image_url ?: $fallbackImage }}"
                          alt="{{ $post->image_alt ?: $post->title }}"
                          class="recent-news-slide-image"
                        >
                      </a>
                    </div>
                    <div class="col-lg-6">
                      <div class="recent-news-slide-content">
                        <p class="post-category">{{ $post->category ?: 'Institucional' }}</p>
                        <h3>
                          <a href="{{ route('public.news.show', $post) }}">{{ $post->title }}</a>
                        </h3>
                        @if($summary)
                          <p class="recent-news-summary">{{ $summary }}</p>
                        @endif
                        <div class="recent-news-meta">
                          <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="Colegio Nuestra Señora del Carmen" width="300" height="322" loading="lazy" decoding="async">
                          <div>
                            <p>{{ $post->author_name ?: 'Colegio Nuestra Señora del Carmen' }}</p>
                            <time datetime="{{ $post->published_at?->toDateString() }}">
                              {{ $publishedAt ? $publishedAt->translatedFormat('j F Y') : 'Sin fecha' }}
                            </time>
                          </div>
                        </div>
                        <a href="{{ route('public.news.show', $post) }}" class="btn btn-primary mt-4">Leer noticia</a>
                      </div>
                    </div>
                  </div>
                </article>
              </div>
            @endforeach
          </div>

          <button class="carousel-control-prev" type="button" data-bs-target="#recentNewsCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Anterior</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#recentNewsCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Siguiente</span>
          </button>
        </div>

        <div class="text-center mt-4">
          <a href="{{ route('public.news') }}" class="btn btn-outline-primary">Ver todas las noticias</a>
        </div>
      @else
        <div class="page-card text-center">
          <h3>Noticias en preparación</h3>
          <p class="mb-0">Pronto publicaremos nuevas actividades de la comunidad educativa pastoral.</p>
        </div>
      @endif
    </div>
  </section>
@endsection
