@extends('public.layouts.site')

@section('title', 'Instalaciones | Colegio Nuestra Señora del Carmen')
@section('description', 'Instalaciones y espacios educativos del Colegio Nuestra Señora del Carmen de Valdivia.')

@section('content')
  <div class="page-title">
    <div class="container">
      <h1>Instalaciones</h1>
      <p>Espacios para aprender, compartir, celebrar la fe y desarrollar la vida escolar.</p>
    </div>
  </div>

  <section class="campus-facilities section">
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
          <img src="{{ asset('niceschool/assets/img/education/campus-1.webp') }}" alt="Instalaciones del colegio" class="img-fluid rounded-4 shadow-lg">
        </div>
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
          <div class="row gy-4">
            <div class="col-md-6">
              <div class="page-card">
                <i class="bi bi-book"></i>
                <h3>Salas y aprendizaje</h3>
                <p>Espacios para el trabajo académico y el desarrollo de habilidades.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="page-card">
                <i class="bi bi-flower1"></i>
                <h3>Capilla y pastoral</h3>
                <p>Lugares de encuentro, oración y celebración comunitaria.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="page-card">
                <i class="bi bi-trophy"></i>
                <h3>Deporte</h3>
                <p>Áreas para actividad física, talleres y encuentros deportivos.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="page-card">
                <i class="bi bi-people"></i>
                <h3>Patios y comunidad</h3>
                <p>Espacios para convivencia, recreación y vida escolar diaria.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
