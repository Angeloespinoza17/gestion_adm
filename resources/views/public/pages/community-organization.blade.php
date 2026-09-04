@extends('public.layouts.site')

@section('title', $presentation['title'].' | Colegio Nuestra Señora del Carmen')
@section('description', $presentation['meta_description'])
@section('body_class', 'inner-page public-community-organization-page public-community-organization-page--'.$presentation['type'])

@section('content')
  <div class="page-title community-organization-hero community-organization-hero--{{ $presentation['type'] }}">
    <div class="container position-relative">
      <div class="community-organization-hero__grid">
        <div class="page-title__content">
          <div class="page-title__kicker">
            <i class="bi {{ $presentation['icon'] }}" aria-hidden="true"></i>
            <span>{{ $presentation['eyebrow'] }}</span>
          </div>
          <h1>{{ $presentation['title'] }}</h1>
          <p>{{ $presentation['lead'] }}</p>
          <nav class="page-title__trail" aria-label="Ruta de navegación">
            <ol>
              <li><a href="{{ route('public.home') }}">Inicio</a></li>
              <li><a href="{{ route('public.about') }}">Colegio</a></li>
              <li aria-current="page">{{ $presentation['acronym'] }}</li>
            </ol>
          </nav>
        </div>

        <div class="community-organization-hero__identity" aria-hidden="true">
          <span>{{ $presentation['acronym'] }}</span>
          <small>{{ $presentation['identity_label'] }}</small>
        </div>
      </div>
    </div>
  </div>

  <section class="community-organization-intro section" aria-labelledby="community-organization-intro-title">
    <div class="container">
      <div class="community-organization-intro__grid">
        <div>
          <span class="section-eyebrow">Participación institucional</span>
          <h2 id="community-organization-intro-title">{{ $presentation['intro_title'] }}</h2>
        </div>
        <div class="community-organization-intro__copy">
          <p>{{ $presentation['intro'] }}</p>
        </div>
      </div>
    </div>
  </section>

  <section class="community-organization-roster section" aria-labelledby="community-organization-roster-title">
    <div class="container">
      @if($period)
        <div class="community-organization-period">
          <div class="community-organization-period__copy">
            <span class="section-eyebrow">{{ $presentation['period_eyebrow'] }}</span>
            <h2 id="community-organization-roster-title">{{ $period['display_name'] }}</h2>
            @if($period['description'])
              <p>{{ $period['description'] }}</p>
            @else
              <p>{{ $presentation['period_description'] }}</p>
            @endif
          </div>

          <dl class="community-organization-period__facts">
            <div>
              <dt>{{ $presentation['period_label'] }}</dt>
              <dd>{{ $period['period_value'] }}</dd>
            </div>
            @if($period['date_range'])
              <div>
                <dt>Vigencia</dt>
                <dd>{{ $period['date_range'] }}</dd>
              </div>
            @endif
            <div>
              <dt>Integrantes publicados</dt>
              <dd>{{ $period['public_members_count'] }}</dd>
            </div>
          </dl>
        </div>

        @if($period['groups']->isNotEmpty())
          <div class="community-organization-groups">
            @foreach($period['groups'] as $group)
              <section class="community-organization-group" aria-labelledby="community-organization-group-{{ $loop->iteration }}">
                <div class="community-organization-group__heading">
                  <span class="community-organization-group__icon" aria-hidden="true">
                    <i class="bi {{ $group['icon'] }}"></i>
                  </span>
                  <div>
                    <span>{{ $group['eyebrow'] }}</span>
                    <h3 id="community-organization-group-{{ $loop->iteration }}">{{ $group['label'] }}</h3>
                  </div>
                  <span class="community-organization-group__count">
                    {{ $group['members']->count() }} {{ $group['members']->count() === 1 ? 'integrante' : 'integrantes' }}
                  </span>
                </div>

                <div class="community-organization-members">
                  @foreach($group['members'] as $member)
                    <article class="community-organization-member">
                      <span class="community-organization-member__avatar" aria-hidden="true">{{ $member['initials'] }}</span>
                      <div>
                        <h4>{{ $member['name'] }}</h4>
                        <p>{{ $member['position'] }}</p>
                        @if($member['course'])
                          <span class="community-organization-member__course">
                            <i class="bi bi-mortarboard" aria-hidden="true"></i>
                            {{ $member['course'] }}
                          </span>
                        @endif
                      </div>
                    </article>
                  @endforeach
                </div>
              </section>
            @endforeach
          </div>
        @else
          <div class="community-organization-empty" role="status">
            <span aria-hidden="true"><i class="bi bi-person-check"></i></span>
            <div>
              <h3>Nómina pública en preparación</h3>
              <p>El período está publicado, pero todavía no cuenta con integrantes autorizados para mostrarse en el sitio web.</p>
            </div>
          </div>
        @endif
      @else
        <div class="community-organization-empty community-organization-empty--period" role="status">
          <span aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
          <div>
            <span class="section-eyebrow">Información institucional</span>
            <h2 id="community-organization-roster-title">Próxima actualización</h2>
            <p>{{ $presentation['empty_message'] }}</p>
          </div>
        </div>
      @endif
    </div>
  </section>
@endsection
