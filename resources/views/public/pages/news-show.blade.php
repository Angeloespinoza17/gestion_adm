@extends('public.layouts.site')

@php
  use Illuminate\Support\Str;

  $publishedAt = $post->published_at?->copy()->locale('es');
  $bodyHtml = $post->body_html;
  $description = Str::limit(strip_tags($post->excerpt ?: $bodyHtml ?: ''), 155);

  $assetUrl = function (?string $path, ?string $fallback = null): ?string {
      $path = trim((string) $path);

      if ($path === '') {
          return $fallback ? asset($fallback) : null;
      }

      $lower = strtolower($path);

      if (Str::startsWith($lower, ['javascript:', 'data:'])) {
          return $fallback ? asset($fallback) : null;
      }

      if (Str::startsWith($path, ['http://', 'https://'])) {
          return $path;
      }

      if (Str::startsWith($path, '//')) {
          return $fallback ? asset($fallback) : null;
      }

      if (Str::startsWith($path, '/')) {
          return url($path);
      }

      return asset($path);
  };

  $iconClass = function (?string $icon, string $fallback = 'bi bi-lightbulb'): string {
      $icon = trim((string) $icon);

      if ($icon === '' || ! preg_match('/^[a-z0-9\-\s]+$/i', $icon) || ! Str::contains($icon, 'bi-')) {
          return $fallback;
      }

      return Str::contains($icon, 'bi ') ? $icon : 'bi ' . $icon;
  };

  $slugAnchor = fn (?string $value, string $fallback) => Str::slug(trim((string) $value) ?: $fallback) ?: $fallback;

  $featuredImage = $assetUrl($post->image_url, 'niceschool/assets/img/blog/blog-hero-1.webp');
  $headerImage = $assetUrl($post->header_image_url ?: $post->image_url, 'niceschool/assets/img/education/showcase-1.webp');
  $authorImage = $assetUrl($post->author_image_url, 'niceschool/assets/img/person/person-m-6.webp');
  $secondaryImage = $assetUrl($post->secondary_image_url);
  $secondaryImagePosition = in_array($post->secondary_image_position, ['left', 'right', 'full'], true) ? $post->secondary_image_position : 'right';
  $readingMinutes = $post->reading_minutes ?: max(1, (int) ceil(str_word_count(strip_tags(($post->excerpt ?? '') . ' ' . ($bodyHtml ?? ''))) / 200));
  $categories = collect([$post->category])
      ->merge($post->detail_categories ?: [])
      ->map(fn ($item) => trim((string) $item))
      ->filter()
      ->unique()
      ->values();
  $featurePoints = collect($post->feature_points ?: [])
      ->map(fn ($item) => is_array($item) ? $item : [])
      ->filter(fn ($item) => filled($item['icon'] ?? null) || filled($item['title'] ?? null) || filled($item['description'] ?? null))
      ->values();
  $comparisonCards = collect($post->comparison_cards ?: [])
      ->map(fn ($item) => is_array($item) ? $item : [])
      ->filter(fn ($item) => filled($item['icon'] ?? null) || filled($item['title'] ?? null) || ! empty($item['items'] ?? []))
      ->values();
  $keyPrinciples = collect($post->key_principles ?: [])
      ->map(fn ($item) => is_array($item) ? $item : [])
      ->filter(fn ($item) => filled($item['number'] ?? null) || filled($item['title'] ?? null) || filled($item['description'] ?? null))
      ->values();
  $futureTrends = collect($post->future_trends ?: [])
      ->map(fn ($item) => is_array($item) ? $item : [])
      ->filter(fn ($item) => filled($item['icon'] ?? null) || filled($item['title'] ?? null) || filled($item['description'] ?? null))
      ->values();
  $tags = collect($post->tags ?: [])
      ->map(fn ($item) => trim((string) $item))
      ->filter()
      ->unique()
      ->values();
  $hasIntro = filled($post->excerpt) || filled($bodyHtml) || filled($post->quote_text);
  $hasSecondary = filled($post->secondary_section_title) || $secondaryImage || $featurePoints->isNotEmpty();
  $hasInfoBox = filled($post->info_box_title) || filled($post->info_box_text);
  $tocItems = collect($post->toc_items ?: [])
      ->map(fn ($item) => is_array($item) ? [
          'label' => trim((string) ($item['label'] ?? '')),
          'anchor' => $slugAnchor($item['anchor'] ?? $item['label'] ?? '', 'introduccion'),
      ] : null)
      ->filter(fn ($item) => $item && $item['label'] !== '')
      ->values();

  if ($tocItems->isEmpty()) {
      $tocItems = collect([
          $hasIntro ? ['label' => 'Introducción', 'anchor' => 'introduccion'] : null,
          $hasSecondary ? ['label' => $post->secondary_section_title ?: 'Detalle de la noticia', 'anchor' => 'detalle'] : null,
          $comparisonCards->isNotEmpty() ? ['label' => 'Comparativa', 'anchor' => 'comparativa'] : null,
          $keyPrinciples->isNotEmpty() ? ['label' => 'Claves', 'anchor' => 'claves'] : null,
          $hasInfoBox ? ['label' => 'Información', 'anchor' => 'informacion'] : null,
          $futureTrends->isNotEmpty() ? ['label' => 'Próximos pasos', 'anchor' => 'proximos-pasos'] : null,
      ])->filter()->values();
  }

  $shareUrl = urlencode(request()->fullUrl());
  $shareTitle = urlencode($post->title);
@endphp

@section('body_class', 'news-details-page')
@section('title', $post->title . ' | Noticias')
@section('description', $description)

@section('content')
  <div class="page-title dark-background" style="background-image: url('{{ $headerImage }}');">
    <div class="container position-relative">
      <h1>{{ $post->title }}</h1>
      @if($post->excerpt)
        <p>{{ $post->excerpt }}</p>
      @endif
      <nav class="breadcrumbs">
        <ol>
          <li><a href="{{ route('public.home') }}">Inicio</a></li>
          <li><a href="{{ route('public.news') }}">Noticias</a></li>
          <li class="current">{{ $post->title }}</li>
        </ol>
      </nav>
    </div>
  </div>

  <section id="blog-details" class="blog-details section">
    <div class="container" data-aos="fade-up">
      <article class="article">
        <div class="article-header">
          @if($categories->isNotEmpty())
            <div class="meta-categories" data-aos="fade-up">
              @foreach($categories as $category)
                <a href="{{ route('public.news') }}" class="category">{{ $category }}</a>
              @endforeach
            </div>
          @endif

          <h1 class="title" data-aos="fade-up" data-aos-delay="100">{{ $post->title }}</h1>

          <div class="article-meta" data-aos="fade-up" data-aos-delay="200">
            <div class="author">
              <img src="{{ $authorImage }}" alt="{{ $post->author_image_alt ?: $post->author_name ?: 'Autor' }}" class="author-img">
              <div class="author-info">
                <h4>{{ $post->author_name ?: 'Colegio Nuestra Señora del Carmen' }}</h4>
                @if($post->author_role)
                  <span>{{ $post->author_role }}</span>
                @endif
              </div>
            </div>
            <div class="post-info">
              <span>
                <i class="bi bi-calendar4-week"></i>
                <time datetime="{{ $post->published_at?->toDateString() }}">
                  {{ $publishedAt ? $publishedAt->translatedFormat('j F Y') : 'Sin fecha' }}
                </time>
              </span>
              <span><i class="bi bi-clock"></i> {{ $readingMinutes }} min lectura</span>
              @if($post->comments_label)
                <span><i class="bi bi-chat-square-text"></i> {{ $post->comments_label }}</span>
              @endif
            </div>
          </div>
        </div>

        <div class="article-featured-image" data-aos="zoom-in">
          <img src="{{ $featuredImage }}" alt="{{ $post->image_alt ?: $post->title }}" class="img-fluid">
        </div>

        <div class="article-wrapper">
          @if($tocItems->isNotEmpty())
            <aside class="table-of-contents" data-aos="fade-left">
              <h3>Índice</h3>
              <nav>
                <ul>
                  @foreach($tocItems as $item)
                    <li><a href="#{{ $item['anchor'] }}" class="{{ $loop->first ? 'active' : '' }}">{{ $item['label'] }}</a></li>
                  @endforeach
                </ul>
              </nav>
            </aside>
          @endif

          <div class="article-content">
            @if($hasIntro)
              <div class="content-section" id="introduccion" data-aos="fade-up">
                @if($post->excerpt)
                  <p class="lead">{{ $post->excerpt }}</p>
                @endif

                @if($bodyHtml)
                  {!! $bodyHtml !!}
                @endif

                @if($post->quote_text)
                  <div class="highlight-quote">
                    <blockquote>
                      <p>{{ $post->quote_text }}</p>
                      @if($post->quote_author)
                        <cite>{{ $post->quote_author }}</cite>
                      @endif
                    </blockquote>
                  </div>
                @endif
              </div>
            @endif

            @if($hasSecondary)
              <div class="content-section" id="detalle" data-aos="fade-up">
                <h2>{{ $post->secondary_section_title ?: 'Detalle de la noticia' }}</h2>

                @if($secondaryImage)
                  <figure class="image-with-caption {{ $secondaryImagePosition === 'right' ? 'right' : '' }}">
                    <img src="{{ $secondaryImage }}" alt="{{ $post->secondary_image_alt ?: $post->title }}" class="img-fluid" loading="lazy">
                    @if($post->secondary_image_caption)
                      <figcaption>{{ $post->secondary_image_caption }}</figcaption>
                    @endif
                  </figure>
                @endif

                @if($featurePoints->isNotEmpty())
                  <div class="feature-points">
                    @foreach($featurePoints as $point)
                      <div class="point">
                        <i class="{{ $iconClass($point['icon'] ?? null) }}"></i>
                        <div>
                          @if(filled($point['title'] ?? null))
                            <h4>{{ $point['title'] }}</h4>
                          @endif
                          @if(filled($point['description'] ?? null))
                            <p>{{ $point['description'] }}</p>
                          @endif
                        </div>
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>
            @endif

            @if($comparisonCards->isNotEmpty())
              <div class="content-section" id="comparativa" data-aos="fade-up">
                <h2>Comparativa</h2>
                <div class="comparison-grid">
                  <div class="row g-4">
                    @foreach($comparisonCards as $card)
                      <div class="col-md-6">
                        <div class="comparison-card">
                          <div class="icon"><i class="{{ $iconClass($card['icon'] ?? null, 'bi bi-check-circle') }}"></i></div>
                          @if(filled($card['title'] ?? null))
                            <h4>{{ $card['title'] }}</h4>
                          @endif
                          @if(! empty($card['items'] ?? []))
                            <ul>
                              @foreach($card['items'] as $item)
                                <li>{{ $item }}</li>
                              @endforeach
                            </ul>
                          @endif
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
              </div>
            @endif

            @if($keyPrinciples->isNotEmpty())
              <div class="content-section" id="claves" data-aos="fade-up">
                <h2>Claves de la noticia</h2>
                <div class="key-principles">
                  @foreach($keyPrinciples as $principle)
                    <div class="principle">
                      <span class="number">{{ $principle['number'] ?: str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                      @if(filled($principle['title'] ?? null))
                        <h4>{{ $principle['title'] }}</h4>
                      @endif
                      @if(filled($principle['description'] ?? null))
                        <p>{{ $principle['description'] }}</p>
                      @endif
                    </div>
                  @endforeach
                </div>
              </div>
            @endif

            @if($hasInfoBox)
              <div class="content-section" id="informacion" data-aos="fade-up">
                <div class="info-box">
                  <div class="icon">
                    <i class="{{ $iconClass($post->info_box_icon, 'bi bi-info-circle') }}"></i>
                  </div>
                  <div class="content">
                    @if($post->info_box_title)
                      <h4>{{ $post->info_box_title }}</h4>
                    @endif
                    @if($post->info_box_text)
                      <p>{{ $post->info_box_text }}</p>
                    @endif
                  </div>
                </div>
              </div>
            @endif

            @if($futureTrends->isNotEmpty())
              <div class="content-section" id="proximos-pasos" data-aos="fade-up">
                <h2>Próximos pasos</h2>
                <div class="future-trends">
                  @foreach($futureTrends as $trend)
                    <div class="trend">
                      <i class="{{ $iconClass($trend['icon'] ?? null, 'bi bi-arrow-right-circle') }}"></i>
                      @if(filled($trend['title'] ?? null))
                        <h4>{{ $trend['title'] }}</h4>
                      @endif
                      @if(filled($trend['description'] ?? null))
                        <p>{{ $trend['description'] }}</p>
                      @endif
                    </div>
                  @endforeach
                </div>
              </div>
            @endif
          </div>
        </div>

        @if($post->share_enabled || $tags->isNotEmpty())
          <div class="article-footer" data-aos="fade-up">
            @if($post->share_enabled)
              <div class="share-article">
                <h4>Compartir esta noticia</h4>
                <div class="share-buttons">
                  <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareTitle }}" class="share-button twitter" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-twitter-x"></i>
                    <span>Compartir en X</span>
                  </a>
                  <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" class="share-button facebook" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-facebook"></i>
                    <span>Compartir en Facebook</span>
                  </a>
                  <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" class="share-button linkedin" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-linkedin"></i>
                    <span>Compartir en LinkedIn</span>
                  </a>
                </div>
              </div>
            @endif

            @if($tags->isNotEmpty())
              <div class="article-tags">
                <h4>Temas relacionados</h4>
                <div class="tags">
                  @foreach($tags as $tag)
                    <a href="{{ route('public.news') }}" class="tag">{{ $tag }}</a>
                  @endforeach
                </div>
              </div>
            @endif
          </div>
        @endif
      </article>
    </div>
  </section>
@endsection
