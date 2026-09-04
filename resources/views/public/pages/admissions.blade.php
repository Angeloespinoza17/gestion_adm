@extends('public.layouts.site')

@section('title', 'Admisión | Colegio Nuestra Señora del Carmen')
@section('description', 'Información de admisión del Colegio Nuestra Señora del Carmen de Valdivia.')
@section('body_class', 'inner-page admissions-page public-admissions-page')

@section('content')
  <div class="page-title page-title--admissions">
    <div class="container">
      <div class="page-title__content">
        <div class="page-title__kicker">
          <i class="bi bi-person-plus" aria-hidden="true"></i>
          <span>Acompañamiento a familias</span>
        </div>
        <h1>Admisión</h1>
        <p>Información para familias interesadas en integrarse a la comunidad educativa pastoral CNSC.</p>
        <nav class="page-title__trail" aria-label="Ruta de navegación">
          <a href="{{ route('public.home') }}">Inicio</a>
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
          <span aria-current="page">Admisión</span>
        </nav>
      </div>
    </div>
  </div>

  <section class="admissions section">
    <div class="container">
      <div class="row gy-5 align-items-center">
        <div class="col-lg-6" data-aos="fade-right">
          <div class="admissions-intro">
            <span class="eyebrow">Proceso</span>
            <h2>Una comunidad que acompaña a sus familias</h2>
            <p class="lead">El proceso de admisión debe revisarse siempre por los canales oficiales del colegio y por los documentos vigentes informados cada año.</p>
            <p>La orientación institucional está centrada en una educación humanista y cristiana, con acompañamiento académico, formativo y pastoral desde los primeros niveles hasta enseñanza media.</p>
            <div class="admissions-cta">
              <a href="{{ route('public.contact') }}" class="btn btn-primary">Consultar admisión</a>
            </div>
          </div>
        </div>
        <div class="col-lg-6" data-aos="fade-left">
          <div class="admissions-visual">
            <img src="{{ asset('niceschool/assets/img/education/chapel-hero.jpg') }}" alt="Familias y comunidad educativa reunidas en la capilla del colegio" class="img-fluid rounded-4 shadow-lg" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="admissions-process section soft-band">
    <div class="container">
      <div class="row gy-4">
        <div class="col-md-4">
          <article class="page-card admissions-step">
            <i class="bi bi-1-circle" aria-hidden="true"></i>
            <h3>Informarse</h3>
            <p>Revisar fechas, requisitos, cupos y documentos oficiales publicados por el colegio.</p>
          </article>
        </div>
        <div class="col-md-4">
          <article class="page-card admissions-step">
            <i class="bi bi-2-circle" aria-hidden="true"></i>
            <h3>Contactar</h3>
            <p>Resolver dudas con secretaría o con el área correspondiente antes de iniciar la postulación.</p>
          </article>
        </div>
        <div class="col-md-4">
          <article class="page-card admissions-step">
            <i class="bi bi-3-circle" aria-hidden="true"></i>
            <h3>Postular</h3>
            <p>Completar el proceso según los plazos y lineamientos definidos para el año escolar.</p>
          </article>
        </div>
      </div>
    </div>
  </section>
@endsection
