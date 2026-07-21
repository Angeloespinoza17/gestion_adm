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

  $headerImage = $assetUrl($event->header_image_url ?: $event->hero_image_url, 'niceschool/assets/img/education/showcase-1.webp');
  $heroImage = $assetUrl($event->hero_image_url, 'niceschool/assets/img/education/events-9.webp');
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
  $externalUrl = $safeHref($event->external_url);
@endphp

@section('body_class', 'event-details-page')
@section('title', $event->title . ' | Eventos')
@section('description', $description)

@section('content')
  <div class="page-title dark-background" style="background-image: url('{{ $headerImage }}');">
    <div class="container position-relative">
      <h1>{{ $event->title }}</h1>
      @if($event->summary)
        <p>{{ $event->summary }}</p>
      @endif
      <nav class="breadcrumbs">
        <ol>
          <li><a href="{{ route('public.home') }}">Inicio</a></li>
          <li><a href="{{ route('public.events') }}">Eventos</a></li>
          <li class="current">{{ $event->title }}</li>
        </ol>
      </nav>
    </div>
  </div>

  <section id="event" class="event section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row">
        <div class="col-lg-8">
          <div class="event-image mb-4" data-aos="fade-up">
            <img src="{{ $heroImage }}" alt="{{ $event->hero_image_alt ?: $event->title }}" class="img-fluid rounded">
          </div>

          <div class="event-meta mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="meta-item">
                  <i class="bi bi-calendar-date"></i>
                  <span>{{ $startsAt ? $startsAt->translatedFormat('d/m/Y') : 'Por confirmar' }}</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="meta-item">
                  <i class="bi bi-clock"></i>
                  <span>
                    @if($startsAt && $endsAt)
                      {{ $startsAt->format('H:i') }} - {{ $endsAt->format('H:i') }} hrs.
                    @elseif($startsAt)
                      {{ $startsAt->format('H:i') }} hrs.
                    @else
                      Horario por confirmar
                    @endif
                  </span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="meta-item">
                  <i class="bi bi-geo-alt"></i>
                  <span>{{ $event->location ?: 'Lugar por confirmar' }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="event-content" data-aos="fade-up" data-aos-delay="200">
            <h2>{{ $event->title }}</h2>

            @if($event->summary)
              <p class="lead">{{ $event->summary }}</p>
            @endif

            @if($bodyHtml)
              {!! $bodyHtml !!}
            @endif

            @if($highlights->isNotEmpty())
              <h3 class="mt-4">Puntos destacados</h3>
              <ul class="event-highlights">
                @foreach($highlights as $highlight)
                  <li>
                    <i class="bi bi-check-circle"></i>
                    <span>{{ $highlight }}</span>
                  </li>
                @endforeach
              </ul>
            @endif

            @if($scheduleItems->isNotEmpty())
              <h3 class="mt-4">Programa del evento</h3>
              <div class="schedule-table">
                @foreach($scheduleItems as $item)
                  <div class="schedule-row">
                    <div class="schedule-time">{{ $item['time'] ?? '' }}</div>
                    <div class="schedule-activity">
                      @if(filled($item['title'] ?? null))
                        <h4>{{ $item['title'] }}</h4>
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
                <h3>Galería del evento</h3>
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
                        <a href="{{ $galleryUrl }}" class="glightbox">
                          <img src="{{ $galleryUrl }}" alt="{{ $image['alt'] ?? $event->title }}" class="img-fluid rounded">
                        </a>
                      </div>
                    @endif
                  @endforeach
                </div>
              </div>
            @endif

            @if($externalUrl)
              <a href="{{ $externalUrl }}" class="btn btn-primary mt-4" target="_blank" rel="noopener noreferrer">Ver enlace del evento</a>
            @endif
          </div>
        </div>

        <div class="col-lg-4">
          <div class="event-sidebar">
            @if($event->registration_enabled)
              <div class="sidebar-widget registration-form" data-aos="fade-left" data-aos-delay="200">
                <h3>{{ $event->registration_title ?: 'Inscripción al evento' }}</h3>
                <form action="{{ $registrationAction }}" method="get">
                  <input type="hidden" name="evento" value="{{ $event->title }}">
                  <div class="mb-3">
                    <label for="event-register-name" class="form-label">Nombre completo</label>
                    <input type="text" class="form-control" id="event-register-name" name="nombre" required>
                  </div>
                  <div class="mb-3">
                    <label for="event-register-email" class="form-label">Correo</label>
                    <input type="email" class="form-control" id="event-register-email" name="correo" required>
                  </div>
                  <div class="mb-3">
                    <label for="event-register-phone" class="form-label">Teléfono</label>
                    <input type="tel" class="form-control" id="event-register-phone" name="telefono">
                  </div>
                  <div class="mb-3">
                    <label for="event-register-type" class="form-label">Participante</label>
                    <select class="form-select" id="event-register-type" name="tipo">
                      <option value="">Seleccione una opción</option>
                      <option value="estudiante">Estudiante</option>
                      <option value="apoderado">Apoderado</option>
                      <option value="docente">Docente</option>
                      <option value="otro">Otro</option>
                    </select>
                  </div>
                  <div class="d-grid">
                    <button type="submit" class="btn btn-register">{{ $event->registration_button_label ?: 'Inscribirme' }}</button>
                  </div>
                </form>
              </div>
            @endif

            @if($hasOrganizer)
              <div class="sidebar-widget organizer-info" data-aos="fade-left" data-aos-delay="300">
                <h3>Organizador</h3>
                <div class="organizer-details">
                  @if($organizerImage)
                    <div class="organizer-image">
                      <img src="{{ $organizerImage }}" class="img-fluid rounded" alt="{{ $event->organizer_image_alt ?: $event->organizer_name ?: 'Organizador' }}">
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
                          <p><i class="bi bi-envelope"></i> {{ $event->organizer_email }}</p>
                        @endif
                        @if($event->organizer_phone)
                          <p><i class="bi bi-telephone"></i> {{ $event->organizer_phone }}</p>
                        @endif
                      </div>
                    @endif
                  </div>
                </div>
              </div>
            @endif

            @if($relatedEvents->isNotEmpty())
              <div class="sidebar-widget related-events" data-aos="fade-left" data-aos-delay="400">
                <h3>Eventos relacionados</h3>
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
                      <h4>{{ $related->title }}</h4>
                      @if($related->location)
                        <p><i class="bi bi-geo-alt"></i> {{ $related->location }}</p>
                      @endif
                    </div>
                  </a>
                @endforeach
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
