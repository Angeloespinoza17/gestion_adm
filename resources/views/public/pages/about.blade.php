@extends('public.layouts.site')

@section('title', 'Nosotros | Colegio Nuestra Señora del Carmen')
@section('description', 'Historia, carisma, misión, visión y lineamientos formativos del Colegio Nuestra Señora del Carmen de Valdivia.')
@section('body_class', 'inner-page about-page public-about-page')

@section('content')
  <div class="page-title page-title--about">
    <div class="container">
      <div class="page-title__content">
        <div class="page-title__kicker">
          <i class="bi bi-building" aria-hidden="true"></i>
          <span>Identidad y trayectoria</span>
        </div>
        <h1>Nosotros</h1>
        <p>Colegio Nuestra Señora del Carmen de Valdivia, una comunidad educativa pastoral con historia, fe y servicio.</p>
        <nav class="page-title__trail" aria-label="Ruta de navegación">
          <a href="{{ route('public.home') }}">Inicio</a>
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
          <span aria-current="page">Nosotros</span>
        </nav>
      </div>
    </div>
  </div>

  <section id="history" class="history section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row align-items-center g-5">
        <div class="col-lg-6">
          <div class="about-content about-intro" data-aos="fade-up" data-aos-delay="200">
            <span class="about-eyebrow">Nuestra historia</span>
            <h2>Más de un siglo educando con identidad y vocación de servicio</h2>
            <p>El Colegio Nuestra Señora del Carmen fue fundado el 7 de noviembre de 1907 por Madre Lorenza Koehler, religiosa de la Congregación de la Inmaculada Concepción.</p>
            <p>Desde sus orígenes, la institución ha estado vinculada al cuidado, la educación y la formación cristiana. Su proyecto educativo mantiene el legado de Madre Paulina y promueve una vida escolar centrada en fe, responsabilidad, servicio y alegría.</p>

            <div class="timeline">
              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <h3>7 de noviembre de 1907</h3>
                  <p>Fue fundada por Madre Lorenza Koehler, religiosa de la Congregación de la Inmaculada Concepción, con el apoyo del Intendente Sr. Enrique Cuevas y de su esposa, la Sra. Carmela Mackena de Cuevas.</p>
                </div>
              </div>

              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <h3>1908</h3>
                  <p>El hogar para las huerfanitas creció rápidamente: comenzó con tres niñas y, al inicio del año 1908, ya contaba con 30 pequeñas que recibían cuidados y enseñanza primaria.</p>
                </div>
              </div>

              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <h3>1909</h3>
                  <p>La Escuela fue reconocida como cooperadora del Estado, consolidando su labor educativa y social en la comunidad de Valdivia.</p>
                </div>
              </div>

              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <h3>6 de julio de 1969</h3>
                  <p>Casa de Huérfanas obtuvo Personalidad Jurídica, fortaleciendo su identidad institucional y su continuidad de servicio.</p>
                </div>
              </div>

              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <h3>10 de abril de 1987</h3>
                  <p>Un incendio destruyó completamente la antigua casa de madera; solo se salvó el gimnasio, marcando un quiebre doloroso en la historia del colegio.</p>
                </div>
              </div>

              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <h3>Marzo de 1989</h3>
                  <p>Se iniciaron las clases en la nueva Escuela, dando comienzo a una etapa de reconstrucción y esperanza para toda la comunidad.</p>
                </div>
              </div>

              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <h3>1989 - 1993</h3>
                  <p>Se bendijo la capilla, se inauguró la comunidad para las religiosas, cocina y comedor del internado, y se finalizó la última etapa del internado, que pudo habilitarse en marzo de 1993.</p>
                </div>
              </div>

              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <h3>Actualidad</h3>
                  <p>El colegio cuenta con una matrícula de 1078 alumnas, desde Pre Kínder a 4° año Medio, y continúa formando jóvenes con fe, libertad, responsabilidad y espíritu de servicio.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="about-image about-visual" data-aos="zoom-in" data-aos-delay="300">
            <img src="{{ asset('niceschool/assets/img/education/chapel-hero.jpg') }}" alt="Comunidad reunida en la capilla del colegio" class="img-fluid rounded-4" loading="lazy" decoding="async">

            <div class="mission-vision" data-aos="fade-up" data-aos-delay="400">
              <div class="mission">
                <h3>Misión</h3>
                <p>Formar niños, niñas y jóvenes con una sólida preparación cristiano-católica, académica y valórica, mediante una educación humanista, inspirada en el legado de Madre Paulina de “servir a los demás”.</p>
              </div>

              <div class="vision">
                <h3>Visión</h3>
                <p>Queremos hacer de nuestro Colegio una comunidad de Fe y Cultura, trabajando en la construcción de la civilización del amor, conforme al mensaje de Cristo, con María Inmaculada y el ideario de la Madre Paulina.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-5">
        <div class="col-lg-12">
          <section class="institutional-values">
            <div class="section-header text-center">
              <div class="section-kicker" aria-hidden="true">
                <span class="line"></span>
                <span class="religious-mark">✝</span>
                <span class="line"></span>
              </div>
              <h2>Nuestros valores</h2>
              <p>Valores que sostienen la identidad del colegio y orientan su formación humana, cristiana y comunitaria.</p>
            </div>

            <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3">
              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-heart-fill"></i>
                  </div>
                  <h3>Caridad</h3>
                </article>
              </div>

              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-book"></i>
                  </div>
                  <h3>Fe</h3>
                </article>
              </div>

              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-check2-circle"></i>
                  </div>
                  <h3>Verdad</h3>
                </article>
              </div>

              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-emoji-smile"></i>
                  </div>
                  <h3>Alegría</h3>
                </article>
              </div>

              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-hand-thumbs-up"></i>
                  </div>
                  <h3>Servicio</h3>
                </article>
              </div>

              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-unlock"></i>
                  </div>
                  <h3>Libertad</h3>
                </article>
              </div>

              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-person"></i>
                  </div>
                  <h3>Humildad</h3>
                </article>
              </div>

              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-clipboard-check"></i>
                  </div>
                  <h3>Responsabilidad</h3>
                </article>
              </div>

              <div class="col">
                <article class="value-card-premium">
                  <div class="value-icon-circle" aria-hidden="true">
                    <i class="bi bi-people"></i>
                  </div>
                  <h3>Respeto</h3>
                </article>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </section>

  <section id="leadership" class="leadership identity-summary section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row mb-5">
        <div class="col-lg-6" data-aos="fade-right" data-aos-delay="200">
          <span class="section-subtitle">Identidad institucional</span>
          <h2 class="section-heading">Una comunidad educativa que sostiene su trabajo en la fe, la familia y el servicio</h2>
          <p class="section-description">La vida del colegio se organiza en torno a la dirección, la formación, la pastoral y la administración, con una mirada común centrada en el crecimiento académico, humano y espiritual de sus estudiantes.</p>
          <div class="stats-container mt-4">
            <div class="row">
              <div class="col-md-4 col-6">
                <div class="stat-item">
                  <strong>1907</strong>
                  <p>Fundación</p>
                </div>
              </div>
              <div class="col-md-4 col-6">
                <div class="stat-item">
                  <strong>1078</strong>
                  <p>Estudiantes</p>
                </div>
              </div>
              <div class="col-md-4 col-6">
                <div class="stat-item">
                  <strong>60+</strong>
                  <p>Docentes</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6" data-aos="fade-left" data-aos-delay="300">
          <div class="about-image">
            <img src="{{ asset('niceschool/assets/img/education/chapel-hero.jpg') }}" alt="Comunidad educativa reunida en la capilla del colegio" class="img-fluid rounded-4 shadow-lg" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
