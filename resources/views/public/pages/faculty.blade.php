@extends('public.layouts.site')

@section('title', 'Equipo | Colegio Nuestra Señora del Carmen')
@section('description', 'Equipo directivo, docentes, asistentes y estamentos de apoyo del Colegio Nuestra Señora del Carmen.')

@php
  $teamFilters = $teamFilters ?? [
      ['key' => 'all', 'label' => 'Todos'],
      ['key' => 'directivo', 'label' => 'Equipo Directivo'],
      ['key' => 'administrativo', 'label' => 'Equipo Administrativo'],
      ['key' => 'pastoral', 'label' => 'Equipo Pastoral'],
      ['key' => 'unidad-academica', 'label' => 'Unidad Académica'],
      ['key' => 'pie', 'label' => 'Equipo PIE'],
      ['key' => 'mantencion-aseo', 'label' => 'Mantención y Aseo'],
      ['key' => 'formacion-convivencia', 'label' => 'Formación y Convivencia Escolar'],
      ['key' => 'docentes', 'label' => 'Equipo Docente'],
  ];

  $teamGroups = $teamGroups ?? [
      [
          'key' => 'directivo',
          'label' => 'Equipo Directivo',
          'eyebrow' => 'Conducción institucional',
          'title' => 'Liderazgo y proyección',
          'description' => 'Define el rumbo del colegio, cuida su identidad y articula la vida académica, formativa y comunitaria.',
          'cards' => [
              [
                  'name' => 'Ana María Campos',
                  'role' => 'Directora',
                  'email' => 'directora@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-5.webp'),
                  'icon' => 'bi-building',
                  'phrase' => 'Acompaña la comunidad con visión pastoral, cercanía y compromiso con la misión educativa.',
              ],
              [
                  'name' => 'Rodrigo Herrera',
                  'role' => 'Administrador',
                  'email' => 'administrador@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-3.webp'),
                  'icon' => 'bi-shield-check',
                  'phrase' => 'Coordina la gestión institucional con orden, servicio y mirada estratégica.',
              ],
              [
                  'name' => 'Patricia Leiva',
                  'role' => 'Subdirectora Curricular',
                  'email' => 'subdireccion.curricular@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-9.webp'),
                  'icon' => 'bi-diagram-3',
                  'phrase' => 'Impulsa el trabajo pedagógico y la coherencia curricular del colegio.',
              ],
              [
                  'name' => 'Valeria Gómez',
                  'role' => 'Subdirectora de Formación y Convivencia Escolar',
                  'email' => 'subdireccion.formacion@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-7.webp'),
                  'icon' => 'bi-chat-heart',
                  'phrase' => 'Promueve el buen trato, el acompañamiento y la formación integral.',
              ],
              [
                  'name' => 'Hna. Teresa Fuentes',
                  'role' => 'Subdirectora de Pastoral',
                  'email' => 'pastoral@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-3.webp'),
                  'icon' => 'bi-stars',
                  'phrase' => 'Anima la fe, la oración y el servicio como parte de la vida educativa.',
              ],
          ],
      ],
      [
          'key' => 'administrativo',
          'label' => 'Equipo Administrativo',
          'eyebrow' => 'Gestión y atención',
          'title' => 'Orden y servicio',
          'description' => 'Sostiene los procesos administrativos, la comunicación interna y el contacto con familias y estudiantes.',
          'cards' => [
              [
                  'name' => 'Verónica Muñoz',
                  'role' => 'Secretaria',
                  'email' => 'secretaria@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-7.webp'),
                  'icon' => 'bi-envelope-paper',
                  'phrase' => 'Organiza gestiones con amabilidad, claridad y eficiencia.',
              ],
              [
                  'name' => 'Camila Ríos',
                  'role' => 'Prevencionista de Riesgos',
                  'email' => 'prevencion.riesgos@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-8.webp'),
                  'icon' => 'bi-shield-plus',
                  'phrase' => 'Promueve ambientes seguros y el cuidado responsable de toda la comunidad.',
              ],
              [
                  'name' => 'Paola Fuentes',
                  'role' => 'TENS',
                  'email' => 'tens@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-10.webp'),
                  'icon' => 'bi-heart-pulse',
                  'phrase' => 'Acompaña la atención diaria con sensibilidad, orden y apoyo técnico.',
              ],
              [
                  'name' => 'Andrea Pérez',
                  'role' => 'Encargada Centro de Apuntes',
                  'email' => 'centro.apuntes@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-12.webp'),
                  'icon' => 'bi-printer',
                  'phrase' => 'Gestiona materiales y recursos con atención al detalle y servicio oportuno.',
              ],
              [
                  'name' => 'Felipe Araya',
                  'role' => 'Encargado de Informática',
                  'email' => 'informatica@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-7.webp'),
                  'icon' => 'bi-laptop',
                  'phrase' => 'Da soporte a la operación digital y a las herramientas tecnológicas del colegio.',
              ],
              [
                  'name' => 'Patricia Leiva',
                  'role' => 'Encargada de Remuneraciones',
                  'email' => 'remuneraciones@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-9.webp'),
                  'icon' => 'bi-cash-coin',
                  'phrase' => 'Resguarda procesos de pago y gestión administrativa con precisión y discreción.',
              ],
              [
                  'name' => 'María Campos',
                  'role' => 'Contadora',
                  'email' => 'contabilidad@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-5.webp'),
                  'icon' => 'bi-calculator',
                  'phrase' => 'Ordena la información financiera con rigurosidad y responsabilidad.',
              ],
          ],
      ],
      [
          'key' => 'pastoral',
          'label' => 'Equipo Pastoral',
          'eyebrow' => 'Vida de fe',
          'title' => 'Espiritualidad y comunidad',
          'description' => 'Acompaña la dimensión espiritual del colegio y anima las celebraciones, encuentros y experiencias de comunidad.',
          'cards' => [
              [
                  'name' => 'Hna. Teresa Fuentes',
                  'role' => 'Coordinadora Pastoral',
                  'email' => 'teresa.fuentes@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-3.webp'),
                  'icon' => 'bi-stars',
                  'phrase' => 'Anima la fe, la oración y el servicio como parte de la vida educativa.',
              ],
              [
                  'name' => 'Camila Lagos',
                  'role' => 'Coordinadora de Formación Pastoral',
                  'email' => 'camila.lagos@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-13.webp'),
                  'icon' => 'bi-book-heart',
                  'phrase' => 'Coordina espacios de formación, oración y servicio con identidad carismática.',
              ],
              [
                  'name' => 'Felipe Araya',
                  'role' => 'Coordinador de Liturgia',
                  'email' => 'liturgia@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-7.webp'),
                  'icon' => 'bi-cross',
                  'phrase' => 'Prepara celebraciones y momentos de encuentro con sentido comunitario.',
              ],
          ],
      ],
      [
          'key' => 'unidad-academica',
          'label' => 'Unidad Académica',
          'eyebrow' => 'Gestión pedagógica',
          'title' => 'Coordinación de ciclos',
          'description' => 'Acompaña la planificación, evaluación y articulación pedagógica de los distintos ciclos del colegio.',
          'cards' => [
              [
                  'name' => 'Paula Mardones',
                  'role' => 'Coordinadora Primer Ciclo',
                  'email' => 'primer.ciclo@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-2.webp'),
                  'icon' => 'bi-1-circle',
                  'phrase' => 'Acompaña la trayectoria inicial con foco en aprendizajes y vínculos.',
              ],
              [
                  'name' => 'Valentina Soto',
                  'role' => 'Coordinadora Segundo Ciclo',
                  'email' => 'segundo.ciclo@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-8.webp'),
                  'icon' => 'bi-2-circle',
                  'phrase' => 'Orienta la articulación entre asignaturas, evaluación y seguimiento pedagógico.',
              ],
              [
                  'name' => 'Patricia Leiva',
                  'role' => 'Coordinadora Tercer Ciclo',
                  'email' => 'tercer.ciclo@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-9.webp'),
                  'icon' => 'bi-3-circle',
                  'phrase' => 'Favorece la continuidad formativa y el acompañamiento académico de las estudiantes.',
              ],
          ],
      ],
      [
          'key' => 'pie',
          'label' => 'Equipo PIE',
          'eyebrow' => 'Apoyo a la inclusión',
          'title' => 'Atención a la diversidad',
          'description' => 'Articula apoyos especializados para responder a las necesidades educativas de las estudiantes.',
          'cards' => [
              [
                  'name' => 'Andrea Pérez',
                  'role' => 'Coordinadora PIE',
                  'email' => 'pie@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-10.webp'),
                  'icon' => 'bi-heart-pulse',
                  'phrase' => 'Acompaña procesos de inclusión con coordinación, cercanía y foco pedagógico.',
              ],
          ],
      ],
      [
          'key' => 'mantencion-aseo',
          'label' => 'Mantención y Aseo',
          'eyebrow' => 'Cuidado de espacios',
          'title' => 'Ambientes seguros y dignos',
          'description' => 'Resguarda la limpieza, el orden y la infraestructura para favorecer un ambiente escolar seguro y digno.',
          'cards' => [
              [
                  'name' => 'Juan Vera',
                  'role' => 'Encargado Gestión de Mantención',
                  'email' => 'mantencion@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-8.webp'),
                  'icon' => 'bi-tools',
                  'phrase' => 'Coordina reparaciones y conservación de los espacios con dedicación cotidiana.',
              ],
              [
                  'name' => 'Luis Morales',
                  'role' => 'Encargado Gestión de Aseo',
                  'email' => 'aseo@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-11.webp'),
                  'icon' => 'bi-broom',
                  'phrase' => 'Mantiene el cuidado diario de los espacios con responsabilidad y calma.',
              ],
              [
                  'name' => 'Claudia Muñoz',
                  'role' => 'Auxiliar de Aseo',
                  'email' => 'auxiliar.aseo@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-10.webp'),
                  'icon' => 'bi-stars',
                  'phrase' => 'Apoya la limpieza y el orden para un ambiente escolar acogedor.',
              ],
              [
                  'name' => 'Marcos Pérez',
                  'role' => 'Equipo de Mantención',
                  'email' => 'equipo.mantencion@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-10.webp'),
                  'icon' => 'bi-hammer',
                  'phrase' => 'Apoya el funcionamiento y conservación de las instalaciones del colegio.',
              ],
              [
                  'name' => 'Ricardo Silva',
                  'role' => 'Calderero',
                  'email' => 'caldero@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-9.webp'),
                  'icon' => 'bi-gear',
                  'phrase' => 'Controla y mantiene sistemas de servicio con atención técnica y seguridad.',
              ],
              [
                  'name' => 'Jorge Contreras',
                  'role' => 'Portería',
                  'email' => 'porteria@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-13.webp'),
                  'icon' => 'bi-door-open',
                  'phrase' => 'Recibe y orienta con amabilidad, resguardando accesos y movimiento diario.',
              ],
          ],
      ],
      [
          'key' => 'formacion-convivencia',
          'label' => 'Formación y Convivencia Escolar',
          'eyebrow' => 'Acompañamiento formativo',
          'title' => 'Buen trato y desarrollo integral',
          'description' => 'Promueve el respeto, la convivencia y el acompañamiento socioemocional de las estudiantes.',
          'cards' => [
              [
                  'name' => 'Daniela Rojas',
                  'role' => 'Coordinadora de Convivencia Escolar',
                  'email' => 'convivencia@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-13.webp'),
                  'icon' => 'bi-chat-heart',
                  'phrase' => 'Escucha, orienta y construye vínculos sanos en la comunidad escolar.',
              ],
              [
                  'name' => 'Camila Lagos',
                  'role' => 'Trabajadora Social',
                  'email' => 'trabajo.social@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-12.webp'),
                  'icon' => 'bi-people',
                  'phrase' => 'Acompaña trayectorias escolares con mirada social, respeto y cercanía.',
              ],
              [
                  'name' => 'Teresa Fuentes',
                  'role' => 'Psicóloga I Ciclo',
                  'email' => 'psicologia1@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-5.webp'),
                  'icon' => 'bi-person-hearts',
                  'phrase' => 'Apoya el desarrollo emocional y la adaptación escolar en los primeros niveles.',
              ],
              [
                  'name' => 'Patricia Leiva',
                  'role' => 'Psicóloga II Ciclo',
                  'email' => 'psicologia2@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-9.webp'),
                  'icon' => 'bi-person-hearts',
                  'phrase' => 'Acompaña procesos socioemocionales y estrategias de apoyo oportunas.',
              ],
              [
                  'name' => 'Valeria Gómez',
                  'role' => 'Psicóloga III Ciclo',
                  'email' => 'psicologia3@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-7.webp'),
                  'icon' => 'bi-person-hearts',
                  'phrase' => 'Favorece el bienestar, la escucha y el acompañamiento en la adolescencia.',
              ],
              [
                  'name' => 'Felipe Araya',
                  'role' => 'Orientador',
                  'email' => 'orientacion@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-7.webp'),
                  'icon' => 'bi-compass',
                  'phrase' => 'Orienta decisiones y procesos de crecimiento académico y personal.',
              ],
          ],
      ],
      [
          'key' => 'docentes',
          'label' => 'Equipo Docente',
          'eyebrow' => 'Cuerpo pedagógico',
          'title' => 'Departamentos académicos',
          'description' => 'Docentes organizados por departamentos para acompañar el aprendizaje con cercanía, exigencia académica y una mirada humana.',
          'cards' => [
              [
                  'name' => 'Valentina Soto',
                  'role' => 'Profesora de Inglés',
                  'department' => 'Inglés',
                  'email' => 'ingles@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-8.webp'),
                  'icon' => 'bi-translate',
                  'phrase' => 'Promueve la comunicación y la confianza a través de experiencias activas.',
              ],
              [
                  'name' => 'Felipe Araya',
                  'role' => 'Profesor de Matemática',
                  'department' => 'Matemática',
                  'email' => 'matematica@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-7.webp'),
                  'icon' => 'bi-calculator',
                  'phrase' => 'Guía el razonamiento lógico con paciencia, rigor y respeto por cada ritmo.',
              ],
              [
                  'name' => 'Paula Mardones',
                  'role' => 'Profesora de Lenguaje',
                  'department' => 'Lenguaje',
                  'email' => 'lenguaje@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-2.webp'),
                  'icon' => 'bi-journal-text',
                  'phrase' => 'Acompaña la lectura, la escritura y el pensamiento crítico con cercanía.',
              ],
              [
                  'name' => 'Camila Lagos',
                  'role' => 'Profesora de Historia',
                  'department' => 'Historia',
                  'email' => 'historia@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-9.webp'),
                  'icon' => 'bi-bank2',
                  'phrase' => 'Fomenta la memoria, el pensamiento crítico y la identidad colectiva.',
              ],
              [
                  'name' => 'Hna. Teresa Fuentes',
                  'role' => 'Profesora de Religión',
                  'department' => 'Religión',
                  'email' => 'religion@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-3.webp'),
                  'icon' => 'bi-stars',
                  'phrase' => 'Acompaña la fe y la formación espiritual de la comunidad escolar.',
              ],
              [
                  'name' => 'Patricia Leiva',
                  'role' => 'Profesora de Artes',
                  'department' => 'Artes',
                  'email' => 'artes@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-f-13.webp'),
                  'icon' => 'bi-palette',
                  'phrase' => 'Cultiva la creatividad, la sensibilidad y la expresión personal.',
              ],
              [
                  'name' => 'Sofía Contreras',
                  'role' => 'Profesora de Ciencias',
                  'department' => 'Ciencias',
                  'email' => 'ciencias@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-8.webp'),
                  'icon' => 'bi-flask',
                  'phrase' => 'Explora el conocimiento con curiosidad, método y asombro.',
              ],
              [
                  'name' => 'Ricardo Silva',
                  'role' => 'Profesor de Educación Física',
                  'department' => 'Educación Física',
                  'email' => 'educacionfisica@cnscvaldivia.cl',
                  'image' => asset('niceschool/assets/img/person/person-m-9.webp'),
                  'icon' => 'bi-basketball',
                  'phrase' => 'Promueve el movimiento, la disciplina, el cuidado y el trabajo en equipo.',
              ],
          ],
      ],
  ];
@endphp

@section('content')
  <section class="team-hero">
    <div class="container text-center">
      <div class="hero-kicker">
        <span class="line"></span>
        <span>Comunidad educativa</span>
        <span class="line"></span>
      </div>
      <h1>Nuestro Equipo</h1>
      <p>
        @if (!empty($staffCount) && !empty($departmentCount))
          {{ $staffCount }} funcionarios organizados en {{ $departmentCount }} departamentos al servicio de la comunidad educativa.
        @else
          Personas comprometidas con la formación integral de nuestros estudiantes.
        @endif
      </p>
    </div>
  </section>

  <div class="team-page">
    <div class="container-fluid px-4 px-xxl-5">
      <div class="filters-wrap text-center">
        <div class="d-flex flex-wrap justify-content-center gap-2" id="teamFilters" data-aos="fade-up" data-aos-delay="100">
          @foreach ($teamFilters as $filter)
            <button
              type="button"
              class="filter-pill {{ $filter['key'] === 'all' ? 'active' : '' }}"
              data-filter="{{ $filter['key'] }}"
              aria-pressed="{{ $filter['key'] === 'all' ? 'true' : 'false' }}"
            >
              {{ $filter['label'] }}
            </button>
          @endforeach
        </div>
      </div>

      @forelse ($teamGroups as $group)
        <section class="team-group" data-group="{{ $group['key'] }}">
          <div class="team-group-header">
            <span class="eyebrow">{{ $group['eyebrow'] }}</span>
            <h2>{{ $group['title'] }}</h2>
            <p>{{ $group['description'] }}</p>
          </div>

          <div class="row g-4">
            @foreach ($group['cards'] as $person)
              <div class="col-12 col-md-6 col-lg-4">
                <article class="team-card" data-estamento="{{ $group['key'] }}" tabindex="0" role="button" aria-label="{{ $person['name'] }}, {{ $person['role'] }}">
                  <div class="photo-shell">
                    @if (!empty($person['image']))
                      <img src="{{ $person['image'] }}" alt="{{ $person['name'] }}">
                    @else
                      <div class="team-empty-photo" aria-hidden="true"></div>
                    @endif
                    <div class="overlay-layer" aria-hidden="true">
                      <div class="overlay-box">
                        <div class="overlay-kicker">{{ $group['label'] }}</div>
                        <h4>{{ $person['name'] }}</h4>
                        <p class="overlay-role">{{ $person['role'] }}</p>
                        @if (!empty($person['department']))
                          <p class="overlay-department">{{ $person['department'] }}</p>
                        @endif
                        @if (!empty($person['email']))
                          <div class="overlay-contact">
                            <a href="mailto:{{ $person['email'] }}">
                              <i class="bi bi-envelope"></i>
                              <span>{{ $person['email'] }}</span>
                            </a>
                          </div>
                        @endif
                      </div>
                    </div>
                  </div>

                  <div class="card-body-front">
                    <span class="team-label">{{ $group['label'] }}</span>
                    <h3 class="team-name">{{ $person['name'] }}</h3>
                    <p class="team-role">{{ $person['role'] }}</p>
                    @if (!empty($person['department']))
                      <p class="team-department">{{ $person['department'] }}</p>
                    @endif
                    <div class="card-footer-hint">
                      <span>Pasa el cursor o haz clic</span>
                      <span class="flip-hint"><i class="bi bi-info-circle"></i> Contacto</span>
                    </div>
                  </div>
                </article>
              </div>
            @endforeach
          </div>
        </section>
      @empty
        <section class="team-group">
          <div class="team-group-header">
            <span class="eyebrow">Equipo</span>
            <h2>Sin funcionarios publicados</h2>
            <p>Cuando existan funcionarios activos asociados a departamentos, se mostrarán en esta sección.</p>
          </div>
        </section>
      @endforelse
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const page = document.querySelector('.team-page');
      const filterButtons = Array.from(document.querySelectorAll('[data-filter]'));
      const cards = Array.from(document.querySelectorAll('.team-card'));
      const groups = Array.from(document.querySelectorAll('.team-group'));

      const applyFilter = (filter) => {
        cards.forEach((card) => {
          const visible = filter === 'all' || card.dataset.estamento === filter;
          card.classList.toggle('is-hidden', !visible);
          card.classList.remove('is-revealed');
        });

        groups.forEach((group) => {
          const visibleCards = group.querySelectorAll('.team-card:not(.is-hidden)');
          group.classList.toggle('d-none', visibleCards.length === 0);
        });
      };

      filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
          filterButtons.forEach((current) => {
            current.classList.remove('active');
            current.setAttribute('aria-pressed', 'false');
          });

          button.classList.add('active');
          button.setAttribute('aria-pressed', 'true');
          applyFilter(button.dataset.filter);
        });
      });

      cards.forEach((card) => {
        card.addEventListener('click', (event) => {
          if (event.target.closest('a, button')) return;
          card.classList.toggle('is-revealed');
        });

        card.addEventListener('keydown', (event) => {
          if (event.key !== 'Enter' && event.key !== ' ') return;

          event.preventDefault();
          card.classList.toggle('is-revealed');
        });
      });

      applyFilter('all');
    });
  </script>
@endsection
