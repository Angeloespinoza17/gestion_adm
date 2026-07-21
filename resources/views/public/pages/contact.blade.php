@extends('public.layouts.site')

@section('body_class', 'contact-page')
@section('title', 'Contacto | Colegio Nuestra Señora del Carmen')
@section('description', 'Contacto oficial del Colegio Nuestra Señora del Carmen de Valdivia.')

@section('content')
  <section id="contact" class="contact section">
    <div class="container" data-aos="fade-up" data-aos-delay="100">
      <div class="row gy-4 mb-5">
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
          <div class="contact-info-box">
            <div class="icon-box">
              <i class="bi bi-geo-alt"></i>
            </div>
            <div class="info-content">
              <h4>Dirección</h4>
              <p>Eleuterio Ramírez #1886</p>
              <p>Valdivia, Chile</p>
            </div>
          </div>
        </div>

        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
          <div class="contact-info-box">
            <div class="icon-box">
              <i class="bi bi-telephone"></i>
            </div>
            <div class="info-content">
              <h4>Secretaría CNSC</h4>
              <p>632 244731</p>
              <p>Atención telefónica institucional</p>
            </div>
          </div>
        </div>

        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
          <div class="contact-info-box">
            <div class="icon-box">
              <i class="bi bi-headset"></i>
            </div>
            <div class="info-content">
              <h4>Horario de atención</h4>
              <p>Lunes a viernes</p>
              <p>9:00 a 17:00 hrs</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="map-section" data-aos="fade-up" data-aos-delay="200">
      <iframe
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
            <h2 class="text-center mb-4">Escríbenos</h2>

            <form action="{{ route('public.contact.store') }}" method="post" class="php-email-form">
              @csrf
              <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="d-none">

              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-group">
                    <div class="input-with-icon">
                      <i class="bi bi-person"></i>
                      <input type="text" class="form-control @error('nombre') is-invalid @enderror" name="nombre" value="{{ old('nombre') }}" placeholder="Nombre" required>
                    </div>
                    @error('nombre')
                      <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <div class="input-with-icon">
                      <i class="bi bi-envelope"></i>
                      <input type="email" class="form-control @error('correo') is-invalid @enderror" name="correo" value="{{ old('correo') }}" placeholder="Correo electrónico" required>
                    </div>
                    @error('correo')
                      <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <div class="input-with-icon">
                      <i class="bi bi-telephone"></i>
                      <input type="text" class="form-control @error('telefono') is-invalid @enderror" name="telefono" value="{{ old('telefono') }}" placeholder="Teléfono (opcional)">
                    </div>
                    @error('telefono')
                      <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <div class="form-group">
                    <div class="input-with-icon">
                      <i class="bi bi-text-left"></i>
                      <input type="text" class="form-control @error('asunto') is-invalid @enderror" name="asunto" value="{{ old('asunto') }}" placeholder="Asunto" required>
                    </div>
                    @error('asunto')
                      <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-12">
                  <div class="form-group">
                    <div class="input-with-icon">
                      <i class="bi bi-chat-dots message-icon"></i>
                      <textarea class="form-control @error('mensaje') is-invalid @enderror" name="mensaje" placeholder="Mensaje" style="height: 180px" required>{{ old('mensaje') }}</textarea>
                    </div>
                    @error('mensaje')
                      <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                  </div>
                </div>

                <div class="col-12">
                  <div class="loading">Cargando</div>
                  <div class="error-message" style="{{ $errors->any() ? 'display: block;' : '' }}">
                    @if($errors->any())
                      Revisa los campos marcados e intenta nuevamente.
                    @endif
                  </div>
                  <div class="sent-message" style="{{ session('contact_success') ? 'display: block;' : '' }}">
                    {{ session('contact_success') ?: 'Tu mensaje ha sido enviado. Gracias.' }}
                  </div>
                </div>

                <div class="col-12 text-center">
                  <button type="submit" class="btn btn-primary btn-submit">Enviar mensaje</button>
                </div>
              </div>
            </form>

            <p class="text-center mt-4 mb-0">
              También puedes comunicarte directamente con secretaría al <strong>632 244731</strong>, de 9:00 a 17:00 hrs.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
