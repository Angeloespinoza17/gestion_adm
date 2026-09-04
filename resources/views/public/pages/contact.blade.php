@extends('public.layouts.site')

@section('body_class', 'contact-page')
@section('title', 'Contacto | Colegio Nuestra Señora del Carmen')
@section('description', 'Contacto oficial del Colegio Nuestra Señora del Carmen de Valdivia.')

@section('content')
  <div class="page-title page-title--contact">
    <div class="container position-relative">
      <div class="page-title__content">
        <span class="page-title__kicker">
          <i class="bi bi-chat-heart" aria-hidden="true"></i>
          Estamos para ayudarte
        </span>
        <h1>Contacto</h1>
        <p>Conversemos. Nuestro equipo está disponible para orientar tus consultas y acercarte a la comunidad CNSC.</p>
        <nav class="page-title__trail" aria-label="Ruta de navegación">
          <ol>
            <li><a href="{{ route('public.home') }}">Inicio</a></li>
            <li aria-current="page">Contacto</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>

  <section id="contact" class="contact section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <h2 class="visually-hidden">Información de contacto</h2>
      <div class="contact-summary-grid mb-5">
        <div>
          <div class="contact-info-box">
            <div class="icon-box">
              <i class="bi bi-geo-alt" aria-hidden="true"></i>
            </div>
            <div class="info-content">
              <h3>Dirección</h3>
              <p><a href="https://maps.google.com/?q=Eleuterio+Ram%C3%ADrez+1886,+Valdivia,+Chile" target="_blank" rel="noopener noreferrer" aria-label="Abrir Eleuterio Ramírez 1886 en Google Maps, en una pestaña nueva">Eleuterio Ramírez #1886</a></p>
              <p>Valdivia, Chile</p>
            </div>
          </div>
        </div>

        <div>
          <div class="contact-info-box">
            <div class="icon-box">
              <i class="bi bi-telephone" aria-hidden="true"></i>
            </div>
            <div class="info-content">
              <h3>Secretaría CNSC</h3>
              <p><a href="tel:+56632244731">632 244731</a></p>
              <p>Atención telefónica institucional</p>
            </div>
          </div>
        </div>

        <div>
          <div class="contact-info-box">
            <div class="icon-box">
              <i class="bi bi-headset" aria-hidden="true"></i>
            </div>
            <div class="info-content">
              <h3>Horario de atención</h3>
              <p>Lunes a viernes</p>
              <p>9:00 a 17:00 hrs</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="map-section contact-map contact-map--blue" data-aos="fade-up" data-aos-delay="200">
      <iframe
        class="contact-map__frame"
        src="https://maps.google.com/maps?q=Eleuterio%20Ram%C3%ADrez%201886%2C%20Valdivia%2C%20Chile&output=embed"
        width="100%"
        height="500"
        style="border:0;"
        allowfullscreen=""
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        title="Ubicación Colegio Nuestra Señora del Carmen, Valdivia"></iframe>
    </div>

    <div class="container form-container-overlap">
      <div class="row justify-content-center" data-aos="fade-up" data-aos-delay="300">
        <div class="col-lg-10">
          <div class="contact-form-wrapper">
            <div class="contact-form__heading text-center">
              <span class="contact-form__eyebrow">Canal de atención</span>
              <h2>Escríbenos</h2>
              <p>Completa el formulario y responderemos tu consulta a la brevedad.</p>
            </div>

            <form action="{{ route('public.contact.store') }}" method="post" class="php-email-form" aria-describedby="contact-form-help">
              @csrf
              <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="d-none">

              <p id="contact-form-help" class="contact-form__required-note">
                Los campos marcados con <span aria-hidden="true">*</span><span class="visually-hidden">asterisco</span> son obligatorios.
              </p>

              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="contact-name" class="form-label">Nombre completo <span aria-hidden="true">*</span></label>
                    <div class="input-with-icon">
                      <i class="bi bi-person" aria-hidden="true"></i>
                      <input
                        type="text"
                        class="form-control @error('nombre') is-invalid @enderror"
                        id="contact-name"
                        name="nombre"
                        value="{{ old('nombre') }}"
                        placeholder="Ej. María González"
                        autocomplete="name"
                        required
                        aria-invalid="{{ $errors->has('nombre') ? 'true' : 'false' }}"
                        @error('nombre') aria-describedby="contact-name-error" @enderror
                      >
                    </div>
                    @error('nombre')
                      <div id="contact-name-error" class="invalid-feedback d-block" role="alert">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <label for="contact-email" class="form-label">Correo electrónico <span aria-hidden="true">*</span></label>
                    <div class="input-with-icon">
                      <i class="bi bi-envelope" aria-hidden="true"></i>
                      <input
                        type="email"
                        class="form-control @error('correo') is-invalid @enderror"
                        id="contact-email"
                        name="correo"
                        value="{{ old('correo') }}"
                        placeholder="nombre@correo.cl"
                        autocomplete="email"
                        inputmode="email"
                        required
                        aria-invalid="{{ $errors->has('correo') ? 'true' : 'false' }}"
                        @error('correo') aria-describedby="contact-email-error" @enderror
                      >
                    </div>
                    @error('correo')
                      <div id="contact-email-error" class="invalid-feedback d-block" role="alert">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <label for="contact-phone" class="form-label">Teléfono <span class="contact-form__optional">Opcional</span></label>
                    <div class="input-with-icon">
                      <i class="bi bi-telephone" aria-hidden="true"></i>
                      <input
                        type="tel"
                        class="form-control @error('telefono') is-invalid @enderror"
                        id="contact-phone"
                        name="telefono"
                        value="{{ old('telefono') }}"
                        placeholder="Ej. +56 9 1234 5678"
                        autocomplete="tel"
                        inputmode="tel"
                        aria-invalid="{{ $errors->has('telefono') ? 'true' : 'false' }}"
                        @error('telefono') aria-describedby="contact-phone-error" @enderror
                      >
                    </div>
                    @error('telefono')
                      <div id="contact-phone-error" class="invalid-feedback d-block" role="alert">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <label for="contact-subject" class="form-label">Asunto <span aria-hidden="true">*</span></label>
                    <div class="input-with-icon">
                      <i class="bi bi-text-left" aria-hidden="true"></i>
                      <input
                        type="text"
                        class="form-control @error('asunto') is-invalid @enderror"
                        id="contact-subject"
                        name="asunto"
                        value="{{ old('asunto') }}"
                        placeholder="¿En qué podemos ayudarte?"
                        required
                        aria-invalid="{{ $errors->has('asunto') ? 'true' : 'false' }}"
                        @error('asunto') aria-describedby="contact-subject-error" @enderror
                      >
                    </div>
                    @error('asunto')
                      <div id="contact-subject-error" class="invalid-feedback d-block" role="alert">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-12">
                  <div class="form-group">
                    <label for="contact-message" class="form-label">Mensaje <span aria-hidden="true">*</span></label>
                    <div class="input-with-icon">
                      <i class="bi bi-chat-dots message-icon" aria-hidden="true"></i>
                      <textarea
                        class="form-control @error('mensaje') is-invalid @enderror"
                        id="contact-message"
                        name="mensaje"
                        placeholder="Cuéntanos brevemente tu consulta"
                        rows="7"
                        required
                        aria-invalid="{{ $errors->has('mensaje') ? 'true' : 'false' }}"
                        @error('mensaje') aria-describedby="contact-message-error" @enderror
                      >{{ old('mensaje') }}</textarea>
                    </div>
                    @error('mensaje')
                      <div id="contact-message-error" class="invalid-feedback d-block" role="alert">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-12">
                  <div class="loading" role="status" aria-live="polite">Enviando mensaje…</div>
                  <div class="error-message" role="alert" aria-live="assertive" style="{{ $errors->any() ? 'display: block;' : '' }}">
                    @if($errors->any())
                      Revisa los campos marcados e intenta nuevamente.
                    @endif
                  </div>
                  <div class="sent-message" role="status" aria-live="polite" style="{{ session('contact_success') ? 'display: block;' : '' }}">
                    {{ session('contact_success') ?: 'Tu mensaje ha sido enviado. Gracias.' }}
                  </div>
                </div>

                <div class="col-12 text-center">
                  <button type="submit" class="btn btn-primary btn-submit">
                    <span>Enviar mensaje</span>
                    <i class="bi bi-send" aria-hidden="true"></i>
                  </button>
                </div>
              </div>
            </form>

            <p class="contact-form__alternative text-center mt-4 mb-0">
              ¿Prefieres llamar? Comunícate con secretaría al <a href="tel:+56632244731"><strong>632 244731</strong></a>, de 9:00 a 17:00 hrs.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
