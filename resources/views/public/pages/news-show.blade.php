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

  $featuredImage = $assetUrl($post->image_url);
  $headerImage = $assetUrl($post->header_image_url);
  $authorImage = $assetUrl($post->author_image_url);
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

@section('body_class', 'inner-page public-detail-page news-details-page')
@section('title', $post->title . ' | Noticias')
@section('description', $description)

@section('content')
  <header
    class="detail-hero detail-hero--news {{ $headerImage ? 'detail-hero--with-media' : 'detail-hero--institutional' }}"
    @if($headerImage) style="--detail-hero-image: url('{{ $headerImage }}');" @endif
  >
    <div class="container position-relative">
      <div class="detail-hero__layout">
        <div class="detail-hero__content">
          <span class="detail-hero__kicker">
            <i class="bi bi-newspaper" aria-hidden="true"></i>
            Actualidad CNSC
          </span>

          @if($categories->isNotEmpty())
            <div class="detail-hero__categories" aria-label="Categorías de la noticia">
              @foreach($categories as $category)
                <span>{{ $category }}</span>
              @endforeach
            </div>
          @endif

          <h1>{{ $post->title }}</h1>

          @if($post->excerpt)
            <p class="detail-hero__summary">{{ $post->excerpt }}</p>
          @endif

          <div class="detail-hero__meta" aria-label="Información de publicación">
            <div class="detail-author">
              @if($authorImage)
                <img
                  src="{{ $authorImage }}"
                  alt="{{ $post->author_image_alt ?: $post->author_name ?: 'Autor de la noticia' }}"
                  class="detail-author__avatar"
                  width="48"
                  height="48"
                >
              @else
                <span class="detail-author__avatar detail-author__avatar--institutional" aria-hidden="true">
                  <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="" width="300" height="322">
                </span>
              @endif
              <span class="detail-author__copy">
                <strong>{{ $post->author_name ?: 'Colegio Nuestra Señora del Carmen' }}</strong>
                @if($post->author_role)
                  <small>{{ $post->author_role }}</small>
                @endif
              </span>
            </div>

            <ul class="detail-meta-list">
              <li>
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                @if($publishedAt)
                  <time datetime="{{ $post->published_at->toDateString() }}">{{ $publishedAt->translatedFormat('j F Y') }}</time>
                @else
                  <span>Fecha por confirmar</span>
                @endif
              </li>
              <li><i class="bi bi-clock" aria-hidden="true"></i><span>{{ $readingMinutes }} min de lectura</span></li>
              @if($post->comments_label)
                <li><i class="bi bi-chat-square-text" aria-hidden="true"></i><span>{{ $post->comments_label }}</span></li>
              @endif
            </ul>
          </div>

          <nav class="page-title__trail detail-hero__trail" aria-label="Ruta de navegación">
            <ol>
              <li><a href="{{ route('public.home') }}">Inicio</a></li>
              <li><a href="{{ route('public.news') }}">Noticias</a></li>
              <li aria-current="page">Detalle</li>
            </ol>
          </nav>
        </div>

        @unless($headerImage)
          <div class="detail-hero__fallback" aria-hidden="true">
            <span class="detail-hero__crest">
              <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="" width="300" height="322">
            </span>
            <span>Identidad · Fe · Servicio</span>
          </div>
        @endunless
      </div>
    </div>
  </header>

  <section id="blog-details" class="blog-details detail-article-section section">
    <div class="container" data-aos="fade-up">
      <article class="article detail-article">
        <div class="article-featured-image detail-featured-media" data-aos="fade-up">
          @if($featuredImage)
            <img
              src="{{ $featuredImage }}"
              alt="{{ $post->image_alt ?: $post->title }}"
              class="img-fluid"
              decoding="async"
            >
          @else
            <div class="detail-media-placeholder" role="img" aria-label="Identidad institucional del Colegio Nuestra Señora del Carmen">
              <span class="detail-media-placeholder__mark" aria-hidden="true">
                <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="" width="300" height="322">
              </span>
              <span class="detail-media-placeholder__copy">
                <strong>Noticia institucional</strong>
                <small>Comunidad educativa pastoral CNSC</small>
              </span>
            </div>
          @endif
        </div>

        <div class="article-wrapper detail-shell {{ $tocItems->isEmpty() ? 'detail-shell--single' : '' }}">
          @if($tocItems->isNotEmpty())
            <aside class="table-of-contents detail-toc" data-aos="fade-right">
              <span class="detail-toc__eyebrow">Navegación</span>
              <h2>En esta noticia</h2>
              <nav aria-label="Índice de la noticia">
                <ul>
                  @foreach($tocItems as $item)
                    <li><a href="#{{ $item['anchor'] }}" class="{{ $loop->first ? 'active' : '' }}">{{ $item['label'] }}</a></li>
                  @endforeach
                </ul>
              </nav>
            </aside>
          @endif

          <div class="article-content detail-prose">
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
                        <i class="{{ $iconClass($point['icon'] ?? null) }}" aria-hidden="true"></i>
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
                          <div class="icon"><i class="{{ $iconClass($card['icon'] ?? null, 'bi bi-check-circle') }}" aria-hidden="true"></i></div>
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
                    <i class="{{ $iconClass($post->info_box_icon, 'bi bi-info-circle') }}" aria-hidden="true"></i>
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
                      <i class="{{ $iconClass($trend['icon'] ?? null, 'bi bi-arrow-right-circle') }}" aria-hidden="true"></i>
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
                <span class="article-footer__eyebrow">Difunde la información</span>
                <h2>Compartir esta noticia</h2>
                <div class="share-buttons">
                  <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareTitle }}" class="share-button twitter" target="_blank" rel="noopener noreferrer" aria-label="Compartir en X, se abre en una pestaña nueva">
                    <i class="bi bi-twitter-x" aria-hidden="true"></i>
                    <span>Compartir en X</span>
                  </a>
                  <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" class="share-button facebook" target="_blank" rel="noopener noreferrer" aria-label="Compartir en Facebook, se abre en una pestaña nueva">
                    <i class="bi bi-facebook" aria-hidden="true"></i>
                    <span>Compartir en Facebook</span>
                  </a>
                  <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" class="share-button linkedin" target="_blank" rel="noopener noreferrer" aria-label="Compartir en LinkedIn, se abre en una pestaña nueva">
                    <i class="bi bi-linkedin" aria-hidden="true"></i>
                    <span>Compartir en LinkedIn</span>
                  </a>
                </div>
              </div>
            @endif

            @if($tags->isNotEmpty())
              <div class="article-tags">
                <span class="article-footer__eyebrow">Explora</span>
                <h2>Temas relacionados</h2>
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

      @if($relatedNews->isNotEmpty())
        <section class="detail-related" aria-labelledby="related-news-title" data-aos="fade-up">
          <div class="detail-related__heading">
            <div>
              <span class="detail-related__eyebrow">Sigue explorando</span>
              <h2 id="related-news-title">Más noticias de nuestra comunidad</h2>
            </div>
            <a href="{{ route('public.news') }}" class="detail-related__all">
              Ver todas las noticias
              <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
          </div>

          <div class="detail-related__grid">
            @foreach($relatedNews as $related)
              @php
                $relatedImage = $assetUrl($related->image_url);
                $relatedDate = $related->published_at?->copy()->locale('es');
                $relatedSummary = Str::limit(strip_tags($related->excerpt ?: $related->body_html ?: ''), 120);
              @endphp
              <article class="detail-related-card">
                <a href="{{ route('public.news.show', $related) }}" class="detail-related-card__media" aria-label="Leer noticia: {{ $related->title }}">
                  @if($relatedImage)
                    <img src="{{ $relatedImage }}" alt="{{ $related->image_alt ?: $related->title }}" loading="lazy" decoding="async">
                  @else
                    <span class="detail-related-card__placeholder" aria-hidden="true">
                      <img src="{{ asset('brand/logo-cnsc-web.webp') }}" alt="" width="300" height="322" loading="lazy">
                    </span>
                  @endif
                </a>
                <div class="detail-related-card__body">
                  @if($relatedDate)
                    <time datetime="{{ $related->published_at->toDateString() }}">{{ $relatedDate->translatedFormat('j F Y') }}</time>
                  @endif
                  <h3><a href="{{ route('public.news.show', $related) }}">{{ $related->title }}</a></h3>
                  <p>{{ $relatedSummary ?: 'Conoce los detalles de esta noticia institucional.' }}</p>
                  <a href="{{ route('public.news.show', $related) }}" class="detail-related-card__action" aria-label="Leer noticia completa: {{ $related->title }}">
                    Leer noticia
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                  </a>
                </div>
              </article>
            @endforeach
          </div>
        </section>
      @endif
    </div>
  </section>
@endsection
