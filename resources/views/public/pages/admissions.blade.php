@extends('public.layouts.site')

@section('title', 'Admisión | Colegio Nuestra Señora del Carmen')
@section('description', 'Información de admisión del Colegio Nuestra Señora del Carmen de Valdivia.')

@section('content')
  <div class="page-title">
    <div class="container">
      <h1>Admisión</h1>
      <p>Información para familias interesadas en integrarse a la comunidad educativa pastoral CNSC.</p>
    </div>
  </div>

  <section class="admissions section">
    <div class="container">
      <div class="row gy-5 align-items-center">
        <div class="col-lg-6" data-aos="fade-right">
          <span class="eyebrow">Proceso</span>
          <h2>Una comunidad que acompaña a sus familias</h2>
          <p class="lead">El proceso de admisión debe revisarse siempre por los canales oficiales del colegio y por los documentos vigentes informados cada año.</p>
          <p>La orientación institucional está centrada en una educación humanista y cristiana, con acompañamiento académico, formativo y pastoral desde los primeros niveles hasta enseñanza media.</p>
          <a href="{{ route('public.contact') }}" class="btn btn-primary mt-3">Consultar admisión</a>
        </div>
        <div class="col-lg-6" data-aos="fade-left">
          <img src="{{ asset('niceschool/assets/img/education/education-2.webp') }}" alt="Admisión Colegio Nuestra Señora del Carmen" class="img-fluid rounded-4 shadow-lg">
        </div>
      </div>
    </div>
  </section>

  <section class="section soft-band">
    <div class="container">
      <div class="row gy-4">
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
          <div class="page-card">
            <i class="bi bi-1-circle"></i>
            <h3>Informarse</h3>
            <p>Revisar fechas, requisitos, cupos y documentos oficiales publicados por el colegio.</p>
          </div>
        </div>
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
          <div class="page-card">
            <i class="bi bi-2-circle"></i>
            <h3>Contactar</h3>
            <p>Resolver dudas con secretaría o con el área correspondiente antes de iniciar la postulación.</p>
          </div>
        </div>
        <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
          <div class="page-card">
            <i class="bi bi-3-circle"></i>
            <h3>Postular</h3>
            <p>Completar el proceso según los plazos y lineamientos definidos para el año escolar.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
