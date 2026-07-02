@extends('public.layouts.site')

@section('title', 'Noticias | Colegio Nuestra Señora del Carmen')
@section('description', 'Noticias recientes del Colegio Nuestra Señora del Carmen de Valdivia.')

@section('content')
  <div class="page-title">
    <div class="container">
      <h1>Noticias</h1>
      <p>Actividades recientes de la comunidad educativa pastoral.</p>
    </div>
  </div>

  <section class="news-posts section">
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
          <article class="news-card">
            <img src="{{ asset('niceschool/assets/img/blog/blog-post-1.webp') }}" alt="Sacramento de Confirmación" class="img-fluid rounded-3 mb-4">
            <span class="news-date">18 mayo 2026</span>
            <h3>Sacramento de Confirmación</h3>
            <p>Ceremonia celebrada junto a estudiantes, familias, pastoral y autoridades religiosas.</p>
          </article>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
          <article class="news-card">
            <img src="{{ asset('niceschool/assets/img/blog/blog-post-2.webp') }}" alt="Gimnasia rítmica" class="img-fluid rounded-3 mb-4">
            <span class="news-date">12 mayo 2026</span>
            <h3>Destacada participación deportiva</h3>
            <p>Estudiantes representan al colegio con entusiasmo, disciplina y dedicación.</p>
          </article>
        </div>
        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
          <article class="news-card">
            <img src="{{ asset('niceschool/assets/img/blog/blog-post-3.webp') }}" alt="Desayunos solidarios" class="img-fluid rounded-3 mb-4">
            <span class="news-date">8 mayo 2026</span>
            <h3>Desayunos solidarios de M. Paulina</h3>
            <p>Actividad tradicional orientada al cuidado, fraternidad y participación de los distintos estamentos.</p>
          </article>
        </div>
      </div>
    </div>
  </section>
@endsection
