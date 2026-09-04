@extends('public.layouts.site')

@section('title', 'Vida estudiantil | Colegio Nuestra Señora del Carmen')
@section('description', 'Experiencias de pastoral, talleres, deporte, convivencia y participación de las estudiantes del Colegio Nuestra Señora del Carmen de Valdivia.')
@section('body_class', 'inner-page students-life-page public-students-life-page')

@section('content')
  <div class="page-title page-title--students-life">
    <div class="container position-relative">
      <div class="page-title__content">
        <div class="page-title__kicker">
          <i class="bi bi-stars" aria-hidden="true"></i>
          <span>Experiencia formativa</span>
        </div>
        <h1>Vida estudiantil</h1>
        <p>Experiencias que permiten descubrir talentos, vivir la fe, participar y construir comunidad más allá del aula.</p>
        <nav class="page-title__trail" aria-label="Ruta de navegación">
          <ol>
            <li><a href="{{ route('public.home') }}">Inicio</a></li>
            <li aria-current="page">Vida estudiantil</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>

  <section class="student-life-intro section" aria-labelledby="student-life-intro-title">
    <div class="container">
      <div class="student-life-intro__grid">
        <div>
          <span class="section-eyebrow">Formación integral</span>
          <h2 id="student-life-intro-title">Cada experiencia también educa</h2>
        </div>
        <div class="student-life-intro__copy">
          <p>La vida escolar se construye en los encuentros, el servicio, la creatividad, la actividad física y la participación de cada estudiante.</p>
          <p>Conoce los espacios que complementan nuestro proyecto educativo y fortalecen una comunidad inspirada en la fe, la alegría y el cuidado mutuo.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="student-life-collection section" aria-labelledby="student-life-collection-title">
    <div class="container">
      @php
        $featuredPost = $studentLifePosts->getCollection()->firstWhere('featured', true)
          ?: $studentLifePosts->getCollection()->first();
        $remainingPosts = $featuredPost
          ? $studentLifePosts->getCollection()->reject(fn ($item) => $item->slug === $featuredPost->slug)->values()
          : collect();
      @endphp

      <div class="student-life-collection__heading">
        <div>
          <span class="section-eyebrow">Vida CNSC</span>
          <h2 id="student-life-collection-title">Experiencias que dejan huella</h2>
        </div>
        <p>{{ $usesEditorialFallback ? 'Una mirada a los principales ámbitos que dan forma a nuestra vida estudiantil.' : 'Actividades y experiencias publicadas por nuestra comunidad educativa.' }}</p>
      </div>

      @if($featuredPost)
        <article class="student-life-featured-card">
          <a href="{{ $featuredPost->public_url }}" class="student-life-featured-card__media" aria-label="Conocer: {{ $featuredPost->title }}">
            @if($featuredPost->cover_image_url)
              <img
                src="{{ $featuredPost->cover_image_url }}"
                alt="{{ $featuredPost->cover_image_alt ?: $featuredPost->title }}"
                loading="eager"
                decoding="async"
              >
            @else
              <span class="student-life-media-placeholder student-life-media-placeholder--large" aria-hidden="true">
                <i class="bi {{ data_get($featuredPost, 'icon', 'bi-stars') }}"></i>
                <span>Vida estudiantil CNSC</span>
              </span>
            @endif
          </a>
          <div class="student-life-featured-card__body">
            <div class="student-life-card__meta">
              <span class="student-life-category">{{ $featuredPost->category ?: 'Vida estudiantil' }}</span>
              @if($featuredPost->event_date)
                <time datetime="{{ $featuredPost->event_date->toDateString() }}">
                  {{ $featuredPost->event_date->copy()->locale('es')->translatedFormat('j F Y') }}
                </time>
              @endif
            </div>
            <h3><a href="{{ $featuredPost->public_url }}">{{ $featuredPost->title }}</a></h3>
            <p>{{ $featuredPost->summary }}</p>
            <a href="{{ $featuredPost->public_url }}" class="student-life-card__action">
              <span>Conocer la experiencia</span>
              <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
          </div>
        </article>
      @endif

      @if($remainingPosts->isNotEmpty())
        <div class="student-life-card-grid">
          @foreach($remainingPosts as $post)
            <article class="student-life-story-card">
              <a href="{{ $post->public_url }}" class="student-life-story-card__media" aria-label="Conocer: {{ $post->title }}">
                @if($post->cover_image_url)
                  <img
                    src="{{ $post->cover_image_url }}"
                    alt="{{ $post->cover_image_alt ?: $post->title }}"
                    loading="lazy"
                    decoding="async"
                  >
                @else
                  <span class="student-life-media-placeholder" aria-hidden="true">
                    <i class="bi {{ data_get($post, 'icon', 'bi-compass') }}"></i>
                    <span>{{ $post->category ?: 'Vida CNSC' }}</span>
                  </span>
                @endif
              </a>
              <div class="student-life-story-card__body">
                <div class="student-life-card__meta">
                  <span class="student-life-category">{{ $post->category ?: 'Comunidad' }}</span>
                  @if($post->event_date)
                    <time datetime="{{ $post->event_date->toDateString() }}">
                      {{ $post->event_date->copy()->locale('es')->translatedFormat('j M Y') }}
                    </time>
                  @endif
                </div>
                <h3><a href="{{ $post->public_url }}">{{ $post->title }}</a></h3>
                <p>{{ $post->summary }}</p>
                <a href="{{ $post->public_url }}" class="student-life-card__action" aria-label="Conocer: {{ $post->title }}">
                  <span>Explorar</span>
                  <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
              </div>
            </article>
          @endforeach
        </div>
      @endif

      @if($studentLifePosts->hasPages())
        <nav class="student-life-pagination" aria-label="Páginas de vida estudiantil">
          {{ $studentLifePosts->links('pagination::bootstrap-5') }}
        </nav>
      @endif
    </div>
  </section>

  <section class="student-life-closing section" aria-labelledby="student-life-closing-title">
    <div class="container">
      <div class="student-life-closing__panel">
        <span class="student-life-closing__icon" aria-hidden="true"><i class="bi bi-heart"></i></span>
        <div>
          <span class="section-eyebrow">Adelante con valor y alegría</span>
          <h2 id="student-life-closing-title">Una comunidad para aprender, participar y servir</h2>
          <p>Cada actividad abre una oportunidad para encontrarnos, crecer y poner nuestros talentos al servicio de los demás.</p>
        </div>
        <a href="{{ route('public.contact') }}" class="btn btn-light">
          Contactar al colegio <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
        </a>
      </div>
    </div>
  </section>
@endsection
