@extends('public.layouts.site')

@section('title', 'Proyecto Educativo | Colegio Nuestra Señora del Carmen')
@section('description', 'Síntesis y descarga del Proyecto Educativo Institucional del Colegio Nuestra Señora del Carmen de Valdivia.')
@section('body_class', 'inner-page educational-project-page public-educational-project-page')

@php
  $projectSections = collect($projectSections ?? [])->filter(fn ($section) => filled($section['title'] ?? null));
  $summaryYear = (int) ($summaryYear ?? 2023);
  $projectYear = $projectDocument['year'] ?? null;
  $projectVersion = $projectDocument['version'] ?? null;
  $projectFileSize = $projectDocument['file_size'] ?? null;
  $projectFileSizeLabel = $projectFileSize
    ? ($projectFileSize >= 1048576
      ? number_format($projectFileSize / 1048576, 1, ',', '.').' MB'
      : number_format($projectFileSize / 1024, 0, ',', '.').' KB')
    : null;
@endphp

@section('content')
  <div class="page-title page-title--educational-project">
    <div class="container">
      <div class="page-title__content">
        <div class="page-title__kicker">
          <i class="bi bi-journal-bookmark" aria-hidden="true"></i>
          <span>Identidad y propósito</span>
        </div>
        <h1>Proyecto Educativo</h1>
        <p>Una presentación clara del Proyecto Educativo Institucional que orienta la vida y la formación de nuestra comunidad.</p>
        <nav class="page-title__trail" aria-label="Ruta de navegación">
          <a href="{{ route('public.home') }}">Inicio</a>
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
          <span aria-current="page">Proyecto Educativo</span>
        </nav>
      </div>
    </div>
  </div>

  <section class="educational-project-intro section" aria-labelledby="educational-project-intro-title">
    <div class="container">
      <div class="educational-project-intro__grid">
        <div class="educational-project-intro__copy">
          <span class="educational-project-eyebrow">Síntesis PEI {{ $summaryYear }}</span>
          <h2 id="educational-project-intro-title">Una mirada simple a nuestro horizonte educativo</h2>
          @if(filled($projectLead))
            <p>{{ $projectLead }}</p>
          @endif

          @if(($projectDocument['is_managed'] ?? false) && $projectYear && (int) $projectYear !== $summaryYear)
            <p class="educational-project-version-note" role="note">
              <i class="bi bi-info-circle" aria-hidden="true"></i>
              <span>Esta síntesis corresponde al PEI base {{ $summaryYear }}. La descarga contiene la versión pública vigente registrada para {{ $projectYear }}.</span>
            </p>
          @endif

          @if($projectSections->isNotEmpty())
            <nav class="educational-project-index" aria-label="Contenidos del Proyecto Educativo">
              <span>Explora esta síntesis</span>
              <div>
                @foreach($projectSections as $section)
                  <a href="#{{ $section['id'] }}">{{ $section['short_title'] ?? $section['title'] }}</a>
                @endforeach
              </div>
            </nav>
          @endif
        </div>

        <aside class="educational-project-document" aria-label="Documento disponible">
          <div class="educational-project-document__icon" aria-hidden="true">
            <i class="bi bi-file-earmark-pdf"></i>
          </div>
          <span class="educational-project-document__label">Documento disponible</span>
          <h2>{{ $projectDocument['title'] }}</h2>
          <div class="educational-project-document__meta">
            @if($projectYear)
              <span><i class="bi bi-calendar3" aria-hidden="true"></i> Año {{ $projectYear }}</span>
            @endif
            @if($projectVersion)
              <span><i class="bi bi-layers" aria-hidden="true"></i> Versión {{ $projectVersion }}</span>
            @endif
            @if($projectFileSizeLabel)
              <span><i class="bi bi-file-earmark" aria-hidden="true"></i> PDF · {{ $projectFileSizeLabel }}</span>
            @else
              <span><i class="bi bi-file-earmark" aria-hidden="true"></i> Formato PDF</span>
            @endif
          </div>
          @if(filled($projectDocument['description'] ?? null))
            <p>{{ $projectDocument['description'] }}</p>
          @endif
        </aside>
      </div>
    </div>
  </section>

  @if($projectSections->isNotEmpty())
    <section class="educational-project-summary section" aria-labelledby="educational-project-summary-title">
      <div class="container">
        <header class="educational-project-section-heading">
          <span>Lectura esencial</span>
          <h2 id="educational-project-summary-title">El proyecto, en sus ideas principales</h2>
          <p>Esta síntesis facilita la lectura. El documento descargable conserva el contenido institucional completo.</p>
        </header>

        <div class="educational-project-sections">
          @foreach($projectSections as $index => $section)
            <article id="{{ $section['id'] }}" class="educational-project-section-card">
              <div class="educational-project-section-card__aside" aria-hidden="true">
                <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                <i class="bi {{ $section['icon'] ?? 'bi-compass' }}"></i>
              </div>
              <div class="educational-project-section-card__content">
                @if(filled($section['eyebrow'] ?? null))
                  <span class="educational-project-eyebrow">{{ $section['eyebrow'] }}</span>
                @endif
                <h3>{{ $section['title'] }}</h3>
                @if(filled($section['summary'] ?? null))
                  <p>{{ $section['summary'] }}</p>
                @endif
                @if(! empty($section['items']))
                  <ul>
                    @foreach($section['items'] as $item)
                      <li><i class="bi bi-check2" aria-hidden="true"></i><span>{{ $item }}</span></li>
                    @endforeach
                  </ul>
                @endif
                @if(filled($section['quote'] ?? null))
                  <blockquote>{{ $section['quote'] }}</blockquote>
                @endif
              </div>
            </article>
          @endforeach
        </div>
      </div>
    </section>
  @endif

  <section class="educational-project-download" aria-labelledby="educational-project-download-title">
    <div class="container">
      <div class="educational-project-download__panel">
        <div class="educational-project-download__mark" aria-hidden="true">
          <i class="bi bi-cloud-arrow-down"></i>
        </div>
        <div class="educational-project-download__copy">
          <span>Documento oficial</span>
          <h2 id="educational-project-download-title">Consulta el proyecto educativo completo</h2>
          <p>Descarga la versión pública disponible en formato PDF.</p>
        </div>
        @if($projectDocument['download_available'])
          <a href="{{ route('public.educational-project.download') }}" class="btn btn-primary educational-project-download__button">
            <span>Descargar proyecto</span>
            <i class="bi bi-download" aria-hidden="true"></i>
          </a>
        @else
          <span class="educational-project-download__unavailable" role="status">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            Documento en actualización
          </span>
        @endif
      </div>
    </div>
  </section>
@endsection
