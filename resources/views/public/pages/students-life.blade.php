@extends('public.layouts.site')

@section('title', 'Vida estudiantil | Colegio Nuestra Señora del Carmen')
@section('description', 'Vida estudiantil, pastoral, talleres, deporte y convivencia del Colegio Nuestra Señora del Carmen.')

@section('content')
  <div class="page-title">
    <div class="container">
      <h1>Vida estudiantil</h1>
      <p>Experiencias que complementan la formación académica y fortalecen comunidad, fe y alegría.</p>
    </div>
  </div>

  <section class="students-life section">
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
          <div class="page-card">
            <i class="bi bi-stars"></i>
            <h3>Pastoral</h3>
            <p>Catequesis, encuentros, celebraciones y actividades que acompañan la experiencia de fe.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
          <div class="page-card">
            <i class="bi bi-palette"></i>
            <h3>Talleres y ACLES</h3>
            <p>Instancias complementarias para desarrollar talentos, expresión y participación escolar.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
          <div class="page-card">
            <i class="bi bi-trophy"></i>
            <h3>Deportes</h3>
            <p>Participación deportiva, disciplina, trabajo en equipo y representación institucional.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
          <div class="page-card">
            <i class="bi bi-chat-heart"></i>
            <h3>Convivencia escolar</h3>
            <p>Promoción del buen trato, cuidado mutuo y participación activa de la comunidad.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
          <div class="page-card">
            <i class="bi bi-cup-hot"></i>
            <h3>Comunidad solidaria</h3>
            <p>Actividades como desayunos solidarios y acciones de servicio inspiradas en Madre Paulina.</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
          <div class="page-card">
            <i class="bi bi-house-heart"></i>
            <h3>Familias</h3>
            <p>Vínculo con apoderados y delegados para fortalecer la vida escolar y pastoral.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
