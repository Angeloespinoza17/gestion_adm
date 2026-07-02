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
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
          <div class="event-card">
            <div class="event-date">
              <span class="month">MAY</span>
              <span class="day">18</span>
              <span class="year">2026</span>
            </div>
            <div class="event-content">
              <div class="event-tag community">Pastoral</div>
              <h3>Sacramento de Confirmación</h3>
              <p>Celebración comunitaria junto a estudiantes, familias y equipo pastoral.</p>
              <div class="event-meta">
                <div class="meta-item"><i class="bi bi-geo-alt"></i><span>Parroquia Sagrado Corazón de Jesús</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
          <div class="event-card">
            <div class="event-date">
              <span class="month">MAY</span>
              <span class="day">08</span>
              <span class="year">2026</span>
            </div>
            <div class="event-content">
              <div class="event-tag academic">Comunidad</div>
              <h3>Desayunos solidarios de M. Paulina</h3>
              <p>Encuentro fraterno con participación de diferentes estamentos del colegio.</p>
              <div class="event-meta">
                <div class="meta-item"><i class="bi bi-geo-alt"></i><span>Dependencias del colegio</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="300">
          <div class="event-card">
            <div class="event-date">
              <span class="month">ABR</span>
              <span class="day">28</span>
              <span class="year">2026</span>
            </div>
            <div class="event-content">
              <div class="event-tag community">Pastoral</div>
              <h3>Reunión de apoderados delegados de pastoral</h3>
              <p>Inicio del trabajo conjunto entre familias y equipo pastoral de la institución.</p>
              <div class="event-meta">
                <div class="meta-item"><i class="bi bi-geo-alt"></i><span>Colegio Nuestra Señora del Carmen</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="400">
          <div class="event-card">
            <div class="event-date">
              <span class="month">ABR</span>
              <span class="day">21</span>
              <span class="year">2026</span>
            </div>
            <div class="event-content">
              <div class="event-tag arts">Fe</div>
              <h3>Primeras Comuniones</h3>
              <p>Celebración sacramental junto a estudiantes, familias y comunidades parroquiales.</p>
              <div class="event-meta">
                <div class="meta-item"><i class="bi bi-geo-alt"></i><span>Comunidad educativa pastoral</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
