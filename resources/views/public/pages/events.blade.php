@extends('public.layouts.site')

@section('body_class', 'inner-page events-index-page')
@section('title', 'Eventos | Colegio Nuestra Señora del Carmen')
@section('description', 'Eventos y actividades del Colegio Nuestra Señora del Carmen de Valdivia.')

@section('content')
  <div class="page-title page-title--events">
    <div class="container position-relative">
      <div class="page-title__content">
        <span class="page-title__kicker">
          <i class="bi bi-calendar-event" aria-hidden="true"></i>
          Agenda institucional
        </span>
        <h1>Eventos</h1>
        <p>Celebraciones, encuentros y actividades que nos permiten aprender, compartir y crecer como comunidad.</p>
        <nav class="page-title__trail" aria-label="Ruta de navegación">
          <ol>
            <li><a href="{{ route('public.home') }}">Inicio</a></li>
            <li aria-current="page">Eventos</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>

  <section class="events section">
    <div class="container">
      @php
        $visibleEvents = collect(method_exists($siteEvents, 'items') ? $siteEvents->items() : $siteEvents);
        $hasUpcomingEvents = $visibleEvents->contains(fn ($item) => $item->starts_at?->isFuture() ?? false);
      @endphp

      @if($visibleEvents->isNotEmpty() && ! $hasUpcomingEvents)
        <div class="agenda-state" role="status">
          <span class="agenda-state__icon" aria-hidden="true"><i class="bi bi-calendar2-plus"></i></span>
          <div>
            <strong>Próximas actividades en preparación</strong>
            <p>La agenda se está actualizando. A continuación puedes revisar encuentros ya realizados por la comunidad.</p>
          </div>
        </div>
      @endif

      <div class="row g-4 events-grid">
        @forelse($siteEvents as $event)
          @php
            $startsAt = $event->starts_at?->copy()->locale('es');
            $isUpcoming = $event->starts_at?->isFuture() ?? false;
            $summary = \Illuminate\Support\Str::limit(strip_tags($event->summary ?: $event->body_html ?: ''), 180);
          @endphp
          <div class="col-lg-6">
            <a
              href="{{ route('public.events.show', $event) }}"
              class="event-card-link"
              aria-label="Ver evento: {{ $event->title }}"
            >
              <article class="event-card event-card--institutional">
                <div class="event-date">
                  <span class="month">{{ $startsAt ? strtoupper($startsAt->translatedFormat('M')) : 'S/F' }}</span>
                  <span class="day">{{ $startsAt ? $startsAt->format('d') : '--' }}</span>
                  <span class="year">{{ $startsAt ? $startsAt->format('Y') : '' }}</span>
                </div>
                <div class="event-content">
                  <div class="event-card__labels">
                    @if($event->category)
                      <span class="event-tag community">{{ $event->category }}</span>
                    @endif
                    <span class="event-card__status {{ $isUpcoming ? 'is-upcoming' : 'is-past' }}">
                      <i class="bi {{ $isUpcoming ? 'bi-stars' : 'bi-check2-circle' }}" aria-hidden="true"></i>
                      {{ $isUpcoming ? 'Próximo' : 'Realizado' }}
                    </span>
                  </div>
                  <h2 class="event-card__title">{{ $event->title }}</h2>
                  <p class="event-card__summary">{{ $summary ?: 'Revisa la información y los detalles de esta actividad institucional.' }}</p>
                  <div class="event-meta">
                    @if($event->location)
                      <div class="meta-item"><i class="bi bi-geo-alt" aria-hidden="true"></i><span>{{ $event->location }}</span></div>
                    @endif
                    @if($startsAt)
                      <div class="meta-item">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $startsAt->translatedFormat('H:i') }} hrs.</time>
                      </div>
                    @endif
                  </div>
                  <span class="event-card__action">
                    Ver detalles
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                  </span>
                </div>
              </article>
            </a>
          </div>
        @empty
          <div class="col-12" data-aos="fade-up" data-aos-delay="100">
            <div class="page-card empty-state text-center" role="status">
              <span class="empty-state__icon" aria-hidden="true">
                <i class="bi bi-calendar2-heart"></i>
              </span>
              <span class="empty-state__eyebrow">Agenda institucional</span>
              <h2>Próximos eventos en preparación</h2>
              <p>Muy pronto encontrarás aquí nuevos encuentros y actividades de nuestra comunidad.</p>
              <a href="{{ route('public.home') }}" class="empty-state__action">
                <i class="bi bi-house-door" aria-hidden="true"></i>
                Volver al inicio
              </a>
            </div>
          </div>
        @endforelse
      </div>

      @if(method_exists($siteEvents, 'links') && $siteEvents->hasPages())
        <div class="mt-5">
          {{ $siteEvents->links('pagination::bootstrap-5') }}
        </div>
      @endif
    </div>
  </section>
@endsection
