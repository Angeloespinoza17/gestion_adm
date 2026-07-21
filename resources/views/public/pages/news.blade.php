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
        @forelse($newsPosts as $post)
          @php
            $publishedAt = $post->published_at?->copy()->locale('es');
            $fallbackImage = asset('niceschool/assets/img/blog/blog-post-' . ((($loop->iteration - 1) % 3) + 1) . '.webp');
          @endphp
          <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="{{ min($loop->iteration, 3) * 100 }}">
            <article class="news-card">
              <a href="{{ route('public.news.show', $post) }}" class="d-block">
                <img
                  src="{{ $post->image_url ?: $fallbackImage }}"
                  alt="{{ $post->image_alt ?: $post->title }}"
                  class="img-fluid rounded-3 mb-4"
                >
              </a>
              <span class="news-date">{{ $publishedAt ? $publishedAt->translatedFormat('j F Y') : 'Sin fecha' }}</span>
              @if($post->category)
                <span class="eyebrow">{{ $post->category }}</span>
              @endif
              <h3><a href="{{ route('public.news.show', $post) }}">{{ $post->title }}</a></h3>
              <p>{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt ?: $post->body ?: ''), 190) }}</p>
              <a href="{{ route('public.news.show', $post) }}" class="read-more">Leer noticia</a>
            </article>
          </div>
        @empty
          <div class="col-12" data-aos="fade-up" data-aos-delay="100">
            <div class="page-card text-center">
              <h3>Noticias en preparación</h3>
              <p class="mb-0">Pronto publicaremos nuevas actividades de la comunidad educativa pastoral.</p>
            </div>
          </div>
        @endforelse
      </div>

      @if(method_exists($newsPosts, 'links') && $newsPosts->hasPages())
        <div class="mt-5">
          {{ $newsPosts->links('pagination::bootstrap-5') }}
        </div>
      @endif
    </div>
  </section>
@endsection
