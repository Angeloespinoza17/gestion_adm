@extends('public.layouts.site')

@section('title', 'Instalaciones | Colegio Nuestra Señora del Carmen')
@section('description', 'Conoce los espacios educativos e instalaciones del Colegio Nuestra Señora del Carmen de Valdivia.')
@section('body_class', 'inner-page campus-page public-campus-page')

@section('content')
  <div class="page-title page-title--campus">
    <div class="container position-relative">
      <div class="page-title__content">
        <div class="page-title__kicker">
          <i class="bi bi-buildings" aria-hidden="true"></i>
          <span>Espacios educativos</span>
        </div>
        <h1>Instalaciones</h1>
        <p>Ambientes pensados para acompañar el aprendizaje, la convivencia, la vida pastoral y la formación integral.</p>
        <nav class="page-title__trail" aria-label="Ruta de navegación">
          <ol>
            <li><a href="{{ route('public.home') }}">Inicio</a></li>
            <li aria-current="page">Instalaciones</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>

  <section class="campus-intro section" aria-labelledby="campus-intro-title">
    <div class="container">
      <div class="campus-intro__grid">
        <div>
          <span class="section-eyebrow">Nuestro colegio</span>
          <h2 id="campus-intro-title">Espacios que forman comunidad</h2>
        </div>
        <div class="campus-intro__copy">
          <p>Cada ambiente del colegio cumple un propósito dentro de la experiencia educativa y favorece el encuentro, la participación y el cuidado mutuo.</p>
          <p>Esta selección reúne exclusivamente los espacios que el colegio ha revisado y publicado para su presentación institucional.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="campus-collection section" aria-labelledby="campus-collection-title">
    <div class="container">
      <div class="campus-collection__heading">
        <div>
          <span class="section-eyebrow">Recorre nuestros espacios</span>
          <h2 id="campus-collection-title">Ambientes para aprender y crecer</h2>
        </div>
        @if($installations->isNotEmpty())
          <p>{{ $installations->total() === 1 ? 'Un espacio institucional publicado.' : $installations->total().' espacios institucionales publicados.' }}</p>
        @endif
      </div>

      @forelse($installations as $installation)
        @php
          $galleryImages = $installation->relationLoaded('galleryImages')
            ? $installation->galleryImages
            : collect();
          $features = collect($installation->features ?? [])
            ->filter(fn ($feature) => is_string($feature) && trim($feature) !== '')
            ->values();
          $isFeatured = $loop->first && $installation->featured;
        @endphp

        <article
          id="{{ $installation->slug }}"
          class="campus-place {{ $isFeatured ? 'campus-place--featured' : '' }}"
          aria-labelledby="campus-place-title-{{ $installation->id }}"
        >
          <div class="campus-place__media">
            @if($installation->cover_image_url)
              <a
                href="{{ $installation->cover_image_url }}"
                class="glightbox campus-place__cover-link"
                data-gallery="installation-{{ $installation->id }}"
                aria-label="Ampliar imagen: {{ $installation->cover_image_alt ?: $installation->title }}"
              >
                <img
                  src="{{ $installation->cover_image_url }}"
                  alt="{{ $installation->cover_image_alt ?: $installation->title }}"
                  width="960"
                  height="720"
                  loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                  decoding="async"
                >
                <span class="campus-place__zoom" aria-hidden="true"><i class="bi bi-arrows-fullscreen"></i></span>
              </a>
            @else
              <div class="campus-place__placeholder" aria-hidden="true">
                <span><i class="bi {{ $installation->icon_class ?: 'bi-buildings' }}"></i></span>
                <small>Espacio CNSC</small>
              </div>
            @endif

            @if($galleryImages->isNotEmpty())
              <div class="campus-place__gallery" aria-label="Galería de {{ $installation->title }}">
                @foreach($galleryImages as $image)
                  <a
                    href="{{ $image->url }}"
                    class="glightbox"
                    data-gallery="installation-{{ $installation->id }}"
                    aria-label="Ampliar imagen: {{ $image->alt_text ?: $installation->title }}"
                  >
                    <img
                      src="{{ $image->url }}"
                      alt="{{ $image->alt_text ?: $installation->title }}"
                      width="320"
                      height="220"
                      loading="lazy"
                      decoding="async"
                    >
                  </a>
                @endforeach
              </div>
            @endif
          </div>

          <div class="campus-place__body">
            <div class="campus-place__topline">
              <span class="campus-place__icon" aria-hidden="true"><i class="bi {{ $installation->icon_class ?: 'bi-buildings' }}"></i></span>
              <span class="campus-place__category">{{ $installation->category ?: 'Espacio educativo' }}</span>
              @if($installation->featured)
                <span class="campus-place__featured"><i class="bi bi-star-fill" aria-hidden="true"></i> Destacado</span>
              @endif
            </div>

            <h3 id="campus-place-title-{{ $installation->id }}">{{ $installation->title }}</h3>
            @if($installation->summary)
              <p class="campus-place__summary">{{ $installation->summary }}</p>
            @endif

            @if($installation->location_label || $installation->capacity_label)
              <dl class="campus-place__facts">
                @if($installation->location_label)
                  <div>
                    <dt><i class="bi bi-geo-alt" aria-hidden="true"></i> Ubicación</dt>
                    <dd>{{ $installation->location_label }}</dd>
                  </div>
                @endif
                @if($installation->capacity_label)
                  <div>
                    <dt><i class="bi bi-people" aria-hidden="true"></i> Capacidad</dt>
                    <dd>{{ $installation->capacity_label }}</dd>
                  </div>
                @endif
              </dl>
            @endif

            @if($features->isNotEmpty())
              <ul class="campus-place__features" aria-label="Características">
                @foreach($features as $feature)
                  <li><i class="bi bi-check2" aria-hidden="true"></i>{{ $feature }}</li>
                @endforeach
              </ul>
            @endif

            @if($installation->body_html || $installation->accessibility_notes)
              <details class="campus-place__details">
                <summary>
                  <span>Conocer este espacio</span>
                  <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="campus-place__details-content">
                  @if($installation->body_html)
                    <div class="campus-place__prose">{!! $installation->body_html !!}</div>
                  @endif
                  @if($installation->accessibility_notes)
                    <div class="campus-place__accessibility">
                      <i class="bi bi-universal-access" aria-hidden="true"></i>
                      <div>
                        <strong>Accesibilidad</strong>
                        <p>{{ $installation->accessibility_notes }}</p>
                      </div>
                    </div>
                  @endif
                </div>
              </details>
            @endif
          </div>
        </article>
      @empty
        <div class="campus-empty-state" role="status">
          <span class="campus-empty-state__icon" aria-hidden="true"><i class="bi bi-building"></i></span>
          <div>
            <span class="section-eyebrow">Próxima actualización</span>
            <h3>Estamos preparando el recorrido por nuestras instalaciones</h3>
            <p>El colegio está organizando la información e imágenes institucionales que se publicarán en esta sección.</p>
          </div>
          <a href="{{ route('public.contact') }}" class="btn btn-primary">
            Consultar al colegio <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
          </a>
        </div>
      @endforelse

      @if($installations->hasPages())
        <nav class="campus-pagination" aria-label="Páginas de instalaciones">
          {{ $installations->links('pagination::bootstrap-5') }}
        </nav>
      @endif
    </div>
  </section>

  @if($installations->isNotEmpty())
    <section class="campus-closing section" aria-labelledby="campus-closing-title">
      <div class="container">
        <div class="campus-closing__panel">
          <span class="campus-closing__icon" aria-hidden="true"><i class="bi bi-compass"></i></span>
          <div>
            <span class="section-eyebrow">Comunidad CNSC</span>
            <h2 id="campus-closing-title">¿Necesitas conocer más?</h2>
            <p>Comunícate con el colegio para solicitar información institucional sobre nuestros espacios.</p>
          </div>
          <a href="{{ route('public.contact') }}" class="btn btn-light">
            Contactar al colegio <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </section>
  @endif
@endsection
