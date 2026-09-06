@php
  $pageTitle = data_get($post, 'meta_title') ?: $post->title.' | Vida estudiantil CNSC';
  $pageDescription = data_get($post, 'meta_description') ?: $post->summary;
  $displayDate = $post->event_date ?: data_get($post, 'published_at');
  $galleryImages = ! $usesEditorialFallback && $post->relationLoaded('galleryImages')
    ? $post->galleryImages
    : collect();
@endphp

@extends('public.layouts.site')

@section('title', $pageTitle)
@section('description', $pageDescription)
@section('body_class', 'inner-page student-life-detail-page')
@section('analytics_page_type', 'detail')
@section('analytics_content_type', 'student_life')
@section('analytics_content_id', data_get($post, 'id') ?: $post->slug)
@section('analytics_content_slug', $post->slug)

@section('content')
  <article class="student-life-detail">
    <header class="student-life-detail-hero">
      <div class="container">
        <nav class="student-life-detail-hero__trail" aria-label="Ruta de navegación">
          <ol>
            <li><a href="{{ route('public.home') }}">Inicio</a></li>
            <li><a href="{{ route('public.students-life') }}">Vida estudiantil</a></li>
            <li aria-current="page">{{ $post->title }}</li>
          </ol>
        </nav>

        <div class="student-life-detail-hero__grid">
          <div class="student-life-detail-hero__copy">
            <div class="student-life-detail-hero__meta">
              <span>{{ $post->category ?: 'Vida estudiantil' }}</span>
              @if($displayDate)
                <time datetime="{{ $displayDate->toDateString() }}">
                  {{ $displayDate->copy()->locale('es')->translatedFormat('j F Y') }}
                </time>
              @endif
            </div>
            <h1>{{ $post->title }}</h1>
            <p>{{ $post->summary }}</p>
          </div>

          <div class="student-life-detail-hero__media">
            @if($post->cover_image_url)
              <img
                src="{{ $post->cover_image_url }}"
                alt="{{ $post->cover_image_alt ?: $post->title }}"
                width="960"
                height="720"
                fetchpriority="high"
                decoding="async"
              >
            @else
              <span class="student-life-media-placeholder student-life-media-placeholder--detail" aria-hidden="true">
                <i class="bi {{ data_get($post, 'icon', 'bi-stars') }}"></i>
                <span>Vida estudiantil CNSC</span>
              </span>
            @endif
          </div>
        </div>
      </div>
    </header>

    <section class="student-life-detail-body section" aria-labelledby="student-life-detail-content-title">
      <div class="container">
        <div class="student-life-detail-body__grid">
          <aside class="student-life-detail-aside" aria-label="Información de la experiencia">
            <span class="student-life-detail-aside__icon" aria-hidden="true"><i class="bi bi-compass"></i></span>
            <span class="section-eyebrow">Vida CNSC</span>
            <h2>Una experiencia que forma</h2>
            <dl>
              <div>
                <dt>Ámbito</dt>
                <dd>{{ $post->category ?: 'Comunidad educativa' }}</dd>
              </div>
              @if($displayDate)
                <div>
                  <dt>Fecha</dt>
                  <dd>{{ $displayDate->copy()->locale('es')->translatedFormat('j F Y') }}</dd>
                </div>
              @endif
            </dl>
            <a href="{{ route('public.students-life') }}">
              <i class="bi bi-arrow-left" aria-hidden="true"></i> Ver toda la vida estudiantil
            </a>
          </aside>

          <div class="student-life-detail-content">
            <h2 id="student-life-detail-content-title" class="visually-hidden">Detalle de {{ $post->title }}</h2>
            @if($post->body_html)
              {!! $post->body_html !!}
            @else
              <p>{{ $post->summary }}</p>
            @endif
          </div>
        </div>
      </div>
    </section>

    @if($galleryImages->isNotEmpty())
      <section class="student-life-gallery section" aria-labelledby="student-life-gallery-title">
        <div class="container">
          <div class="student-life-gallery__heading">
            <div>
              <span class="section-eyebrow">Galería</span>
              <h2 id="student-life-gallery-title">Momentos de esta experiencia</h2>
            </div>
            <p>Imágenes compartidas por nuestra comunidad educativa.</p>
          </div>
          <div class="student-life-gallery__grid">
            @foreach($galleryImages as $image)
              <a
                href="{{ data_get($image, 'url', data_get($image, 'image_url')) }}"
                class="student-life-gallery__item glightbox"
                data-gallery="student-life-gallery"
                aria-label="Ampliar imagen: {{ data_get($image, 'alt', data_get($image, 'alt_text')) ?: $post->title }}"
              >
                <img
                  src="{{ data_get($image, 'url', data_get($image, 'image_url')) }}"
                  alt="{{ data_get($image, 'alt', data_get($image, 'alt_text')) ?: $post->title }}"
                  loading="lazy"
                  decoding="async"
                >
              </a>
            @endforeach
          </div>
        </div>
      </section>
    @endif

    @if($relatedPosts->isNotEmpty())
      <section class="student-life-related section" aria-labelledby="student-life-related-title">
        <div class="container">
          <div class="student-life-related__heading">
            <div>
              <span class="section-eyebrow">Sigue explorando</span>
              <h2 id="student-life-related-title">Otras experiencias</h2>
            </div>
            <a href="{{ route('public.students-life') }}">Ver todas <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
          </div>

          <div class="student-life-card-grid">
            @foreach($relatedPosts as $relatedPost)
              <article class="student-life-story-card">
                <a href="{{ $relatedPost->public_url }}" class="student-life-story-card__media" aria-label="Conocer: {{ $relatedPost->title }}">
                  @if($relatedPost->cover_image_url)
                    <img
                      src="{{ $relatedPost->cover_image_url }}"
                      alt="{{ $relatedPost->cover_image_alt ?: $relatedPost->title }}"
                      loading="lazy"
                      decoding="async"
                    >
                  @else
                    <span class="student-life-media-placeholder" aria-hidden="true">
                      <i class="bi {{ data_get($relatedPost, 'icon', 'bi-compass') }}"></i>
                      <span>{{ $relatedPost->category ?: 'Vida CNSC' }}</span>
                    </span>
                  @endif
                </a>
                <div class="student-life-story-card__body">
                  <span class="student-life-category">{{ $relatedPost->category ?: 'Comunidad' }}</span>
                  <h3><a href="{{ $relatedPost->public_url }}">{{ $relatedPost->title }}</a></h3>
                  <p>{{ $relatedPost->summary }}</p>
                  <a href="{{ $relatedPost->public_url }}" class="student-life-card__action" aria-label="Conocer: {{ $relatedPost->title }}">
                    <span>Explorar</span><i class="bi bi-arrow-right" aria-hidden="true"></i>
                  </a>
                </div>
              </article>
            @endforeach
          </div>
        </div>
      </section>
    @endif
  </article>
@endsection
