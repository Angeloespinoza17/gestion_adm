@extends('public.layouts.site')

@section('body_class', 'index-page')
@section('title', 'Colegio Nuestra Señora del Carmen | Valdivia')
@section('description', 'Sitio institucional del Colegio Nuestra Señora del Carmen de Valdivia, comunidad educativa pastoral inspirada en Madre Paulina.')

@section('content')
  <section id="hero" class="hero section dark-background">
    <div class="hero-container">
      <video autoplay muted loop playsinline class="video-background">
        <source src="{{ asset('niceschool/assets/img/education/video-2.mp4') }}" type="video/mp4">
      </video>
      <div class="overlay"></div>
      <div class="container">
        <div class="row align-items-center">
          <div class="col-lg-7" data-aos="zoom-out" data-aos-delay="100">
            <div class="hero-content">
              <h1>"Que el amor sea el móvil de tu actuar"</h1>
              <p>Madre Paulina von Mallinckrodt</p>
              <div class="cta-buttons">
                <a href="{{ route('public.about') }}" class="btn-primary">Conoce nuestro proyecto educativo</a>
                {{-- <a href="{{ url('/login') }}" class="btn-secondary">Ingresar al sistema</a> --}}
              </div>
            </div>
          </div>
          <div class="col-lg-5" data-aos="zoom-out" data-aos-delay="200">
            <div class="school-crest-card">
              <img src="{{ asset('brand/logo-cnsc.png') }}" alt="Logo Colegio Nuestra Señora del Carmen">
              <h3>Adelante con Valor y Alegría</h3>
              <p>Identidad, fe, servicio y comunidad al centro del proyecto educativo.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="event-ticker">
      <div class="container">
        <div class="row gy-4">
          <div class="col-md-6 col-xl-4 col-12 ticker-item">
            <span class="date">1907</span>
            <span class="title">Más de un siglo de historia</span>
            <a href="{{ route('public.about') }}" class="btn-register">Nosotros</a>
          </div>
          <div class="col-md-6 col-12 col-xl-4 ticker-item">
            <span class="date">CNSC</span>
            <span class="title">Educación humanista y cristiana</span>
            <a href="{{ route('public.admissions') }}" class="btn-register">Admisión</a>
          </div>
          <div class="col-md-6 col-12 col-xl-4 ticker-item">
            <span class="date">VAL</span>
            <span class="title">Eleuterio Ramírez #1886</span>
            <a href="{{ route('public.contact') }}" class="btn-register">Contacto</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="colegio" class="about section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row mb-5 align-items-center">
        <div class="col-lg-6 pe-lg-5" data-aos="fade-right" data-aos-delay="200">
          <h2 class="display-6 fw-bold mb-4">Una comunidad educativa <span>con historia y vocación de servicio</span></h2>
          <p class="lead mb-4">Fundado el 7 de noviembre de 1907, el Colegio Nuestra Señora del Carmen mantiene una tradición formativa ligada a la Congregación de la Inmaculada Concepción y al legado de Madre Paulina.</p>
          <p>Su proyecto educativo busca formar estudiantes con preparación académica y valórica, promoviendo el servicio, la responsabilidad, la fe y la participación consciente en la sociedad.</p>
          <div class="d-flex flex-wrap gap-4 mb-4">
            <div class="stat-box" data-aos="zoom-in" data-aos-delay="100">
              <span class="stat-number"><span data-purecounter-start="0" data-purecounter-end="118" data-purecounter-duration="1" class="purecounter"></span>+</span>
              <span class="stat-label">Años</span>
            </div>
            <div class="stat-box" data-aos="zoom-in" data-aos-delay="200">
              <span class="stat-number"><span data-purecounter-start="0" data-purecounter-end="1000" data-purecounter-duration="1" class="purecounter"></span>+</span>
              <span class="stat-label">Estudiantes</span>
            </div>
            <div class="stat-box" data-aos="zoom-in" data-aos-delay="300">
              <span class="stat-number"><span data-purecounter-start="0" data-purecounter-end="60" data-purecounter-duration="1" class="purecounter"></span>+</span>
              <span class="stat-label">Docentes</span>
            </div>
          </div>
          <a href="{{ route('public.about') }}" class="btn btn-primary">Conócenos</a>
        </div>
        <div class="col-lg-6" data-aos="fade-left" data-aos-delay="300">
          <div class="about-single-image">
            <img src="{{ asset('niceschool/assets/img/education/hero-people.jpg') }}" alt="Estudiantes del colegio" class="img-fluid rounded-4 shadow-lg">
          </div>
        </div>

        <div class="row mission-vision-row g-4">
          <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
            <div class="value-card h-100">
              <div class="card-icon">
                <i class="bi bi-rocket-takeoff"></i>
              </div>
              <h3>Nuestra misión</h3>
              <p>Queremos hacer de nuestro Colegio una comunidad de Fe y Cultura, trabajando en la construcción de la civilización del amor, conforme al mensaje de Cristo, con María Inmaculada y el ideario de la Madre Paulina.</p>
            </div>
          </div>
          <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
            <div class="value-card h-100">
              <div class="card-icon">
                <i class="bi bi-eye"></i>
              </div>
              <h3>Nuestra visión</h3>
              <p>Formar niños, niñas y jóvenes con una sólida preparación cristiano-católica, académica y valórica, mediante una educación humanista, inspirada en el legado de Madre Paulina de “servir a los demás” y basada en un proceso de aprendizaje-enseñanza que promueva el desarrollo de competencias, habilidades, valores y actitudes, que les permitan aportar como personas y ciudadanos a la sociedad, al mundo y a la Iglesia de acuerdo a los nuevos desafíos.</p>
            </div>
          </div>
          <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
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

  <section id="testimonios" class="testimonials section">
    <div class="container section-title" data-aos="fade-up">
      <h2>Testimonios</h2>
      <p>Voces de estudiantes, familias y miembros de la comunidad educativa.</p>
    </div>

    <div class="container">
      <div class="testimonial-masonry">
        <div class="testimonial-item" data-aos="fade-up">
          <div class="testimonial-content">
            <div class="quote-pattern">
              <i class="bi bi-quote"></i>
            </div>
            <p>El colegio acompaña de manera cercana el desarrollo académico y valórico de nuestras hijas, con una comunidad muy comprometida.</p>
            <div class="client-info">
              <div class="client-image">
                <img src="{{ asset('niceschool/assets/img/person/person-f-7.webp') }}" alt="Apoderada">
              </div>
              <div class="client-details">
                <h3>María González</h3>
                <span class="position">Apoderada</span>
              </div>
            </div>
          </div>
        </div>

        <div class="testimonial-item highlight" data-aos="fade-up" data-aos-delay="100">
          <div class="testimonial-content">
            <div class="quote-pattern">
              <i class="bi bi-quote"></i>
            </div>
            <p>La formación integral nos ayuda a crecer en responsabilidad, respeto y fe, dentro de un ambiente que nos hace sentir en casa.</p>
            <div class="client-info">
              <div class="client-image">
                <img src="{{ asset('niceschool/assets/img/person/person-f-8.webp') }}" alt="Estudiante">
              </div>
              <div class="client-details">
                <h3>Camila Pérez</h3>
                <span class="position">Estudiante</span>
              </div>
            </div>
          </div>
        </div>

        <div class="testimonial-item" data-aos="fade-up" data-aos-delay="200">
          <div class="testimonial-content">
            <div class="quote-pattern">
              <i class="bi bi-quote"></i>
            </div>
            <p>El trabajo en equipo entre docentes y familias permite sostener una experiencia educativa coherente y humana.</p>
            <div class="client-info">
              <div class="client-image">
                <img src="{{ asset('niceschool/assets/img/person/person-m-7.webp') }}" alt="Docente">
              </div>
              <div class="client-details">
                <h3>Rodrigo Muñoz</h3>
                <span class="position">Docente</span>
              </div>
            </div>
          </div>
        </div>

        <div class="testimonial-item" data-aos="fade-up" data-aos-delay="300">
          <div class="testimonial-content">
            <div class="quote-pattern">
              <i class="bi bi-quote"></i>
            </div>
            <p>La identidad del colegio se nota en la cercanía, en la exigencia académica y en el cuidado de la convivencia escolar.</p>
            <div class="client-info">
              <div class="client-image">
                <img src="{{ asset('niceschool/assets/img/person/person-m-8.webp') }}" alt="Exalumno">
              </div>
              <div class="client-details">
                <h3>Felipe Torres</h3>
                <span class="position">Exalumno</span>
              </div>
            </div>
          </div>
        </div>

        <div class="testimonial-item highlight" data-aos="fade-up" data-aos-delay="400">
          <div class="testimonial-content">
            <div class="quote-pattern">
              <i class="bi bi-quote"></i>
            </div>
            <p>La pastoral y la vida comunitaria reflejan un sello propio: servicio, alegría y compromiso con los demás.</p>
            <div class="client-info">
              <div class="client-image">
                <img src="{{ asset('niceschool/assets/img/person/person-f-9.webp') }}" alt="Encargada de pastoral">
              </div>
              <div class="client-details">
                <h3>Ana Rojas</h3>
                <span class="position">Encargada de pastoral</span>
              </div>
            </div>
          </div>
        </div>

        <div class="testimonial-item" data-aos="fade-up" data-aos-delay="500">
          <div class="testimonial-content">
            <div class="quote-pattern">
              <i class="bi bi-quote"></i>
            </div>
            <p>El proyecto educativo del colegio mantiene una línea clara de formación, con foco en el aprendizaje y en la dimensión humana.</p>
            <div class="client-info">
              <div class="client-image">
                <img src="{{ asset('niceschool/assets/img/person/person-m-13.webp') }}" alt="Equipo directivo">
              </div>
              <div class="client-details">
                <h3>Carlos Vega</h3>
                <span class="position">Equipo directivo</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="students-life-block" class="students-life-block section">
    <div class="container section-title" data-aos="fade-up">
      <h2>Vida estudiantil</h2>
      <p>Experiencias que complementan la formación académica y fortalecen comunidad, fe y alegría.</p>
    </div>

    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row align-items-center gy-4">
        <div class="col-lg-6" data-aos="fade-right" data-aos-delay="200">
          <div class="students-life-img position-relative">
            <img src="{{ asset('niceschool/assets/img/education/education-square-11.webp') }}" class="img-fluid rounded-4 shadow-sm" alt="Vida estudiantil">
            <div class="img-overlay">
              <h3>Descubre nuestra vida estudiantil</h3>
              <a href="{{ route('public.students-life') }}" class="explore-btn">Conocer más <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>

        <div class="col-lg-6" data-aos="fade-left" data-aos-delay="300">
          <div class="students-life-content">
            <div class="row g-4 mb-4">
              <div class="col-md-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="student-activity-item">
                  <div class="icon-box">
                    <i class="bi bi-stars"></i>
                  </div>
                  <h4>Pastoral</h4>
                  <p>Catequesis, celebraciones y espacios de encuentro que acompañan la experiencia de fe.</p>
                </div>
              </div>

              <div class="col-md-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="student-activity-item">
                  <div class="icon-box">
                    <i class="bi bi-palette"></i>
                  </div>
                  <h4>Talleres y ACLES</h4>
                  <p>Instancias complementarias para desarrollar talentos, expresión y participación escolar.</p>
                </div>
              </div>

              <div class="col-md-6" data-aos="zoom-in" data-aos-delay="400">
                <div class="student-activity-item">
                  <div class="icon-box">
                    <i class="bi bi-trophy"></i>
                  </div>
                  <h4>Deportes</h4>
                  <p>Participación deportiva, disciplina, trabajo en equipo y representación institucional.</p>
                </div>
              </div>

              <div class="col-md-6" data-aos="zoom-in" data-aos-delay="500">
                <div class="student-activity-item">
                  <div class="icon-box">
                    <i class="bi bi-chat-heart"></i>
                  </div>
                  <h4>Convivencia escolar</h4>
                  <p>Promoción del buen trato, cuidado mutuo y participación activa de la comunidad.</p>
                </div>
              </div>
            </div>

            <div class="students-life-cta" data-aos="fade-up" data-aos-delay="600">
              <a href="{{ route('public.students-life') }}" class="btn btn-primary">Ver toda la vida estudiantil</a>
            </div>
          </div>
        </div>
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
        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
          <div class="school-card">
            <i class="bi bi-building-check"></i>
            <h3>Dirección</h3>
            <p>Equipo directivo y gestión institucional al servicio de la comunidad educativa.</p>
          </div>
        </div>
        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
          <div class="school-card">
            <i class="bi bi-journal-bookmark"></i>
            <h3>UTP</h3>
            <p>Acompañamiento pedagógico, actividades curriculares y apoyo al aprendizaje.</p>
          </div>
        </div>
        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
          <div class="school-card">
            <i class="bi bi-people"></i>
            <h3>Formación</h3>
            <p>Convivencia escolar, profesores jefes y desarrollo integral de las estudiantes.</p>
          </div>
        </div>
        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
          <div class="school-card">
            <i class="bi bi-brightness-high"></i>
            <h3>Pastoral</h3>
            <p>Encuentro, oración, sacramentos y compromiso con la comunidad.</p>
          </div>
        </div>
      </div>
      <div class="row justify-content-center gy-4 mt-1">
        <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="500">
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
                          <img src="{{ asset('brand/logo-cnsc.png') }}" alt="Colegio Nuestra Señora del Carmen">
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
