@extends('public.layouts.site')

@section('body_class', 'inner-page news-index-page')
@section('title', 'Noticias | Colegio Nuestra Señora del Carmen')
@section('description', 'Noticias recientes del Colegio Nuestra Señora del Carmen de Valdivia.')

@section('content')
  <div class="page-title page-title--news">
    <div class="container position-relative">
      <div class="page-title__content">
        <span class="page-title__kicker">
          <i class="bi bi-newspaper" aria-hidden="true"></i>
          Actualidad CNSC
        </span>
        <h1>Noticias</h1>
        <p>Conoce las experiencias, aprendizajes y encuentros que dan vida a nuestra comunidad educativa pastoral.</p>
        <nav class="page-title__trail" aria-label="Ruta de navegación">
          <ol>
            <li><a href="{{ route('public.home') }}">Inicio</a></li>
            <li aria-current="page">Noticias</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>

  <section class="news-posts section">
    <div class="container">
      <div class="row gy-4 news-grid">
        @forelse($newsPosts as $post)
          @php
            $publishedAt = $post->published_at?->copy()->locale('es');
            $summary = \Illuminate\Support\Str::limit(strip_tags($post->excerpt ?: $post->body ?: ''), 190);
          @endphp
          <div class="col-lg-4 col-md-6">
            <article class="news-card news-card--editorial">
              <a
                href="{{ route('public.news.show', $post) }}"
                class="news-card__media"
                aria-label="Leer noticia: {{ $post->title }}"
              >
                @if($post->image_url)
                  <img
                    src="{{ $post->image_url }}"
                    alt="{{ $post->image_alt ?: $post->title }}"
                    class="img-fluid"
                    loading="lazy"
                    decoding="async"
                  >
                @else
                  <span class="news-card__placeholder" aria-hidden="true">
                    <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="" width="300" height="322">
                    <span>Actualidad CNSC</span>
                  </span>
                @endif
                @if($post->category)
                  <span class="news-card__category">{{ $post->category }}</span>
                @endif
              </a>
              <div class="news-card__body">
                <div class="news-card__meta">
                  <i class="bi bi-calendar3" aria-hidden="true"></i>
                  @if($publishedAt)
                    <time datetime="{{ $post->published_at->toDateString() }}">{{ $publishedAt->translatedFormat('j F Y') }}</time>
                  @else
                    <span>Fecha por confirmar</span>
                  @endif
                </div>
                <h2 class="news-card__title"><a href="{{ route('public.news.show', $post) }}">{{ $post->title }}</a></h2>
                <p class="news-card__summary">{{ $summary ?: 'Conoce todos los detalles de esta noticia institucional.' }}</p>
                <a
                  href="{{ route('public.news.show', $post) }}"
                  class="read-more news-card__action"
                  aria-label="Leer noticia completa: {{ $post->title }}"
                >
                  <span>Leer noticia</span>
                  <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
              </div>
            </article>
          </div>
        @empty
          <div class="col-12" data-aos="fade-up" data-aos-delay="100">
            <div class="page-card empty-state text-center" role="status">
              <span class="empty-state__icon" aria-hidden="true">
                <i class="bi bi-newspaper"></i>
              </span>
              <span class="empty-state__eyebrow">Actualidad institucional</span>
              <h2>Noticias en preparación</h2>
              <p>Estamos preparando nuevas historias y actividades de nuestra comunidad educativa pastoral.</p>
              <a href="{{ route('public.home') }}" class="empty-state__action">
                <i class="bi bi-house-door" aria-hidden="true"></i>
                Volver al inicio
              </a>
            </div>
          </div>
        @endforelse
      </div>

      @if(method_exists($newsPosts, 'links') && $newsPosts->hasPages())
        <div class="mt-5">
          {{ $newsPosts->links('pagination::bootstrap-5') }}
        </div>
      @endif
    </div>
  </section>
@endsection
