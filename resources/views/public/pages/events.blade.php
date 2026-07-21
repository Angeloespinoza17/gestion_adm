@extends('public.layouts.site')

@section('title', 'Eventos | Colegio Nuestra Señora del Carmen')
@section('description', 'Eventos y actividades del Colegio Nuestra Señora del Carmen de Valdivia.')

@section('content')
  <div class="page-title">
    <div class="container">
      <h1>Eventos</h1>
      <p>Celebraciones, encuentros y actividades que reúnen a la comunidad educativa pastoral.</p>
    </div>
  </div>

  <section class="events section">
    <div class="container">
      <div class="row g-4">
        @forelse($siteEvents as $event)
          @php
            $startsAt = $event->starts_at?->copy()->locale('es');
            $delay = min($loop->iteration, 4) * 100;
          @endphp
          <div class="col-lg-6" data-aos="fade-up" data-aos-delay="{{ $delay }}">
            <a href="{{ route('public.events.show', $event) }}" class="event-card-link">
              <div class="event-card">
                <div class="event-date">
                  <span class="month">{{ $startsAt ? strtoupper($startsAt->translatedFormat('M')) : 'S/F' }}</span>
                  <span class="day">{{ $startsAt ? $startsAt->format('d') : '--' }}</span>
                  <span class="year">{{ $startsAt ? $startsAt->format('Y') : '' }}</span>
                </div>
                <div class="event-content">
                  @if($event->category)
                    <div class="event-tag community">{{ $event->category }}</div>
                  @endif
                  <h3>{{ $event->title }}</h3>
                  <p>{{ \Illuminate\Support\Str::limit(strip_tags($event->summary ?: $event->body_html ?: ''), 180) }}</p>
                  <div class="event-meta">
                    @if($event->location)
                      <div class="meta-item"><i class="bi bi-geo-alt"></i><span>{{ $event->location }}</span></div>
                    @endif
                    @if($startsAt)
                      <div class="meta-item"><i class="bi bi-clock"></i><span>{{ $startsAt->translatedFormat('H:i') }} hrs.</span></div>
                    @endif
                  </div>
                </div>
              </div>
            </a>
          </div>
        @empty
          <div class="col-12" data-aos="fade-up" data-aos-delay="100">
            <div class="page-card text-center">
              <h3>Eventos en preparación</h3>
              <p class="mb-0">Pronto publicaremos nuevas actividades de la comunidad educativa pastoral.</p>
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
