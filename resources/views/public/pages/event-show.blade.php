@extends('public.layouts.site')

@php
  use Illuminate\Support\Str;

  $startsAt = $event->starts_at?->copy()->locale('es');
  $endsAt = $event->ends_at?->copy()->locale('es');
  $description = Str::limit(strip_tags($event->summary ?: $event->body_html ?: ''), 155);
  $bodyHtml = $event->body_html;

  $assetUrl = function (?string $path, ?string $fallback = null): ?string {
      $path = trim((string) $path);

      if ($path === '') {
          return $fallback ? asset($fallback) : null;
      }

      $lower = strtolower($path);

      if (Str::startsWith($lower, ['javascript:', 'data:'])) {
          return $fallback ? asset($fallback) : null;
      }

      if (Str::startsWith($path, ['http://', 'https://'])) {
          return $path;
      }

      if (Str::startsWith($path, '//')) {
          return $fallback ? asset($fallback) : null;
      }

      if (Str::startsWith($path, '/')) {
          return url($path);
      }

      return asset($path);
  };

  $safeHref = function (?string $url, ?string $fallback = null): ?string {
      $url = trim((string) $url);

      if ($url === '') {
          return $fallback;
      }

      $lower = strtolower($url);

      if (Str::startsWith($lower, ['javascript:', 'data:'])) {
          return $fallback;
      }

      if (Str::startsWith($url, ['http://', 'https://'])) {
          return $url;
      }

      if (Str::startsWith($url, '/') && ! Str::startsWith($url, '//')) {
          return url($url);
      }

      return $fallback;
  };

  $headerImage = $assetUrl($event->header_image_url);
  $heroImage = $assetUrl($event->hero_image_url);
  $organizerImage = $assetUrl($event->organizer_image_url);
  $highlights = collect($event->highlights ?: [])->map(fn ($item) => trim((string) $item))->filter()->values();
  $scheduleItems = collect($event->schedule_items ?: [])
      ->map(fn ($item) => is_array($item) ? $item : [])
      ->filter(fn ($item) => filled($item['time'] ?? null) || filled($item['title'] ?? null) || filled($item['description'] ?? null))
      ->values();
  $galleryImages = collect($event->gallery_images ?: [])
      ->map(fn ($item) => is_array($item) ? $item : [])
      ->filter(fn ($item) => filled($item['url'] ?? null))
      ->values();
  $hasOrganizer = collect([
      $event->organizer_name,
      $event->organizer_position,
      $event->organizer_description,
      $event->organizer_email,
      $event->organizer_phone,
      $event->organizer_image_url,
  ])->contains(fn ($value) => filled($value));
  $registrationAction = $safeHref($event->registration_url, route('public.contact'));
  $registrationHost = parse_url((string) $registrationAction, PHP_URL_HOST);
  $registrationIsExternal = filled($registrationHost) && strtolower((string) $registrationHost) !== strtolower(request()->getHost());
  $externalUrl = $safeHref($event->external_url);
  $eventState = match (true) {
      $startsAt?->isFuture() => ['class' => 'is-upcoming', 'label' => 'Próximo evento', 'icon' => 'bi-stars'],
      $endsAt?->isFuture() => ['class' => 'is-live', 'label' => 'En curso', 'icon' => 'bi-broadcast'],
      $startsAt?->isToday() => ['class' => 'is-live', 'label' => 'Actividad de hoy', 'icon' => 'bi-sun'],
      default => ['class' => 'is-past', 'label' => 'Evento realizado', 'icon' => 'bi-check2-circle'],
  };
  $hasEventContent = filled($bodyHtml)
      || $highlights->isNotEmpty()
      || $scheduleItems->isNotEmpty()
      || $galleryImages->isNotEmpty()
      || filled($externalUrl);
@endphp

@section('body_class', 'inner-page public-detail-page event-details-page')
@section('title', $event->title . ' | Eventos')
@section('description', $description)

@section('content')
  <header
    class="detail-hero detail-hero--event {{ $headerImage ? 'detail-hero--with-media' : 'detail-hero--institutional' }}"
    @if($headerImage) style="--detail-hero-image: url('{{ $headerImage }}');" @endif
  >
    <div class="container position-relative">
      <div class="detail-hero__layout">
        <div class="detail-hero__content">
          <div class="detail-hero__topline">
            <span class="detail-hero__kicker">
              <i class="bi bi-calendar-event" aria-hidden="true"></i>
              Agenda CNSC
            </span>
            <span class="detail-status {{ $eventState['class'] }}">
              <i class="bi {{ $eventState['icon'] }}" aria-hidden="true"></i>
              {{ $eventState['label'] }}
            </span>
          </div>

          @if($event->category)
            <div class="detail-hero__categories" aria-label="Categoría del evento">
              <span>{{ $event->category }}</span>
            </div>
          @endif

          <h1>{{ $event->title }}</h1>
          @if($event->summary)
            <p class="detail-hero__summary">{{ $event->summary }}</p>
          @endif

          <ul class="detail-meta-list detail-meta-list--event" aria-label="Información del evento">
            <li>
              <i class="bi bi-calendar3" aria-hidden="true"></i>
              @if($startsAt)
                <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $startsAt->translatedFormat('j F Y') }}</time>
              @else
                <span>Fecha por confirmar</span>
              @endif
            </li>
            <li>
              <i class="bi bi-clock" aria-hidden="true"></i>
              <span>
                @if($startsAt && $endsAt)
                  {{ $startsAt->format('H:i') }} – {{ $endsAt->format('H:i') }} hrs.
                @elseif($startsAt)
                  {{ $startsAt->format('H:i') }} hrs.
                @else
                  Horario por confirmar
                @endif
              </span>
            </li>
            <li><i class="bi bi-geo-alt" aria-hidden="true"></i><span>{{ $event->location ?: 'Lugar por confirmar' }}</span></li>
          </ul>

          <nav class="page-title__trail detail-hero__trail" aria-label="Ruta de navegación">
            <ol>
              <li><a href="{{ route('public.home') }}">Inicio</a></li>
              <li><a href="{{ route('public.events') }}">Eventos</a></li>
              <li aria-current="page">Detalle</li>
            </ol>
          </nav>
        </div>

        @unless($headerImage)
          <div class="detail-hero__fallback" aria-hidden="true">
            <span class="detail-hero__crest">
              <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="" width="300" height="322">
            </span>
            <span>Encuentro · Comunidad · Alegría</span>
          </div>
        @endunless
      </div>
    </div>
  </header>

  <section id="event" class="event event-detail-section section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row g-5 event-detail-layout">
        <div class="col-lg-8">
          <div class="event-image detail-featured-media" data-aos="fade-up">
            @if($heroImage)
              <img src="{{ $heroImage }}" alt="{{ $event->hero_image_alt ?: $event->title }}" class="img-fluid" decoding="async">
            @else
              <div class="detail-media-placeholder detail-media-placeholder--event" role="img" aria-label="Identidad institucional del Colegio Nuestra Señora del Carmen">
                <span class="detail-media-placeholder__mark" aria-hidden="true">
                  <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="" width="300" height="322">
                </span>
                <span class="detail-media-placeholder__copy">
                  <strong>Actividad institucional</strong>
                  <small>Encuentro de la comunidad CNSC</small>
                </span>
              </div>
            @endif
          </div>

          <div class="event-content event-detail-main" data-aos="fade-up" data-aos-delay="100">
            @if($bodyHtml)
              <div class="event-detail-copy">
                {!! $bodyHtml !!}
              </div>
            @endif

            @unless($hasEventContent)
              <div class="event-detail-empty" role="status">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <div>
                  <h2>Información en preparación</h2>
                  <p>Pronto publicaremos más detalles de esta actividad institucional.</p>
                </div>
              </div>
            @endunless

            @if($highlights->isNotEmpty())
              <div class="event-detail-section-heading">
                <span>Lo esencial</span>
                <h2>Puntos destacados</h2>
              </div>
              <ul class="event-highlights">
                @foreach($highlights as $highlight)
                  <li>
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    <span>{{ $highlight }}</span>
                  </li>
                @endforeach
              </ul>
            @endif

            @if($scheduleItems->isNotEmpty())
              <div class="event-detail-section-heading">
                <span>Planifica tu jornada</span>
                <h2>Programa del evento</h2>
              </div>
              <div class="schedule-table">
                @foreach($scheduleItems as $item)
                  <div class="schedule-row">
                    <div class="schedule-time"><i class="bi bi-clock" aria-hidden="true"></i>{{ $item['time'] ?? '' }}</div>
                    <div class="schedule-activity">
                      @if(filled($item['title'] ?? null))
                        <h3>{{ $item['title'] }}</h3>
                      @endif
                      @if(filled($item['description'] ?? null))
                        <p>{{ $item['description'] }}</p>
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            @endif

            @if($galleryImages->isNotEmpty())
              <div class="event-gallery mt-5" data-aos="fade-up" data-aos-delay="300">
                <div class="event-detail-section-heading">
                  <span>Momentos compartidos</span>
                  <h2>Galería del evento</h2>
                </div>
                @if($event->gallery_intro)
                  <p>{{ $event->gallery_intro }}</p>
                @endif
                <div class="row g-4 mt-2">
                  @foreach($galleryImages as $image)
                    @php
                      $galleryUrl = $assetUrl($image['url'] ?? null);
                    @endphp
                    @if($galleryUrl)
                      <div class="col-md-4">
                        <a href="{{ $galleryUrl }}" class="glightbox event-gallery__item" aria-label="Ampliar imagen: {{ $image['alt'] ?? $event->title }}">
                          <img src="{{ $galleryUrl }}" alt="{{ $image['alt'] ?? $event->title }}" class="img-fluid" loading="lazy" decoding="async">
                          <span aria-hidden="true"><i class="bi bi-arrows-fullscreen"></i></span>
                        </a>
                      </div>
                    @endif
                  @endforeach
                </div>
              </div>
            @endif

            @if($externalUrl)
              <a href="{{ $externalUrl }}" class="btn btn-primary event-detail-external" target="_blank" rel="noopener noreferrer" aria-label="Ver enlace del evento, se abre en una pestaña nueva">
                Ver enlace del evento
                <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
              </a>
            @endif
          </div>
        </div>

        <aside class="col-lg-4" aria-label="Información complementaria del evento">
          <div class="event-sidebar event-detail-sidebar">
            @if($event->registration_enabled)
              <div class="sidebar-widget registration-form event-registration" data-aos="fade-left" data-aos-delay="200">
                <span class="sidebar-widget__icon" aria-hidden="true"><i class="bi bi-ticket-perforated"></i></span>
                <span class="sidebar-widget__eyebrow">Participa</span>
                <h2>{{ $event->registration_title ?: 'Inscripción al evento' }}</h2>
                <p class="event-registration__intro">Accede al canal habilitado para conocer requisitos y completar tu inscripción de forma segura.</p>
                <a
                  href="{{ $registrationAction }}"
                  class="btn btn-register event-registration__cta"
                  @if($registrationIsExternal) target="_blank" rel="noopener noreferrer" @endif
                  @if($registrationIsExternal) aria-label="{{ $event->registration_button_label ?: 'Inscribirme' }}, se abre en una pestaña nueva" @endif
                >
                  <span>{{ $event->registration_button_label ?: 'Inscribirme' }}</span>
                  <i class="bi {{ $registrationIsExternal ? 'bi-arrow-up-right' : 'bi-arrow-right' }}" aria-hidden="true"></i>
                </a>
                <p class="event-registration__note">
                  <i class="bi bi-shield-check" aria-hidden="true"></i>
                  {{ $registrationIsExternal ? 'Se abrirá el canal oficial configurado para este evento.' : 'Te dirigiremos al canal de contacto del colegio.' }}
                </p>
              </div>
            @endif

            @if($hasOrganizer)
              <div class="sidebar-widget organizer-info event-organizer" data-aos="fade-left" data-aos-delay="300">
                <span class="sidebar-widget__eyebrow">Contacto responsable</span>
                <h2>Organiza</h2>
                <div class="organizer-details">
                  @if($organizerImage)
                    <div class="organizer-image">
                      <img src="{{ $organizerImage }}" class="img-fluid" alt="{{ $event->organizer_image_alt ?: $event->organizer_name ?: 'Organizador' }}" loading="lazy" decoding="async">
                    </div>
                  @endif
                  <div class="organizer-content">
                    @if($event->organizer_name)
                      <h4>{{ $event->organizer_name }}</h4>
                    @endif
                    @if($event->organizer_position)
                      <p class="organizer-position">{{ $event->organizer_position }}</p>
                    @endif
                    @if($event->organizer_description)
                      <p>{{ $event->organizer_description }}</p>
                    @endif
                    @if($event->organizer_email || $event->organizer_phone)
                      <div class="organizer-contact">
                        @if($event->organizer_email)
                          <p><i class="bi bi-envelope" aria-hidden="true"></i> <a href="mailto:{{ $event->organizer_email }}">{{ $event->organizer_email }}</a></p>
                        @endif
                        @if($event->organizer_phone)
                          <p><i class="bi bi-telephone" aria-hidden="true"></i> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $event->organizer_phone) }}">{{ $event->organizer_phone }}</a></p>
                        @endif
                      </div>
                    @endif
                  </div>
                </div>
              </div>
            @endif

            @if($relatedEvents->isNotEmpty())
              <div class="sidebar-widget related-events event-related" data-aos="fade-left" data-aos-delay="400">
                <span class="sidebar-widget__eyebrow">También te puede interesar</span>
                <h2>Otros eventos</h2>
                @foreach($relatedEvents as $related)
                  @php
                    $relatedDate = $related->starts_at?->copy()->locale('es');
                  @endphp
                  <a href="{{ route('public.events.show', $related) }}" class="related-event-item text-decoration-none text-reset">
                    <div class="related-event-date">
                      <span class="day">{{ $relatedDate ? $relatedDate->format('d') : '--' }}</span>
                      <span class="month">{{ $relatedDate ? $relatedDate->translatedFormat('M') : 'S/F' }}</span>
                    </div>
                    <div class="related-event-info">
                      <h3>{{ $related->title }}</h3>
                      @if($related->location)
                        <p><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $related->location }}</p>
                      @endif
                    </div>
                  </a>
                @endforeach
              </div>
            @endif
          </div>
        </aside>
      </div>
    </div>
  </section>
@endsection
