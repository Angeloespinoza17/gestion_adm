<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>@yield('title', 'Colegio Nuestra Señora del Carmen | Valdivia')</title>
  <meta name="description" content="@yield('description', 'Sitio institucional del Colegio Nuestra Señora del Carmen de Valdivia, comunidad educativa pastoral inspirada en Madre Paulina.')">
  <meta name="keywords" content="Colegio Nuestra Señora del Carmen, CNSC Valdivia, colegio católico, educación, pastoral">

  <link href="{{ asset('brand/logo-cnsc.png') }}" rel="icon">
  <link href="{{ asset('brand/logo-cnsc.png') }}" rel="apple-touch-icon">

  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;500;600;700;800&family=Raleway:wght@500;600;700&display=swap" rel="stylesheet">

  @php
    $siteCssPath = 'niceschool/assets/css/main.css';
    $bootstrapCssPath = 'niceschool/assets/vendor/bootstrap/css/bootstrap.min.css';
    $bootstrapJsPath = 'niceschool/assets/vendor/bootstrap/js/bootstrap.bundle.min.js';

    $siteAssetVersion = file_exists(public_path($siteCssPath))
      ? filemtime(public_path($siteCssPath))
      : time();
    $bootstrapAssetVersion = file_exists(public_path($bootstrapCssPath))
      ? filemtime(public_path($bootstrapCssPath))
      : $siteAssetVersion;
    $bootstrapJsAssetVersion = file_exists(public_path($bootstrapJsPath))
      ? filemtime(public_path($bootstrapJsPath))
      : $siteAssetVersion;
  @endphp

  @if(file_exists(public_path($bootstrapCssPath)))
    <link href="{{ asset($bootstrapCssPath) }}?v={{ $bootstrapAssetVersion }}" rel="stylesheet">
  @else
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  @endif
  <link href="{{ asset('niceschool/assets/vendor/bootstrap-icons/bootstrap-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('niceschool/assets/vendor/aos/aos.css') }}" rel="stylesheet">
  <link href="{{ asset('niceschool/assets/vendor/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
  <link href="{{ asset('niceschool/assets/vendor/glightbox/css/glightbox.min.css') }}" rel="stylesheet">
  <link href="{{ asset($siteCssPath) }}?v={{ $siteAssetVersion }}" rel="stylesheet">

  <style>
    :root {
      --primary-color: #174866;
      --secondary-color: #4f9bb1;
      --accent-color: #d49a53;
      --default-color: #243746;
      --heading-color: #174866;
      --surface-color: #ffffff;
      --cnsc-olive: #7f8d2c;
      --cnsc-gold: #d49a53;
      --cnsc-soft: #f4efe8;
      --nav-hover-color: var(--accent-color);
      --nav-dropdown-hover-color: var(--accent-color);
    }

    body {
      color: var(--default-color);
    }

    .fixed-top {
      left: 0;
      position: fixed;
      right: 0;
      top: 0;
      z-index: 1030;
    }

    .position-relative {
      position: relative !important;
    }

    .d-flex {
      display: flex !important;
    }

    .align-items-center {
      align-items: center !important;
    }

    .align-items-stretch {
      align-items: stretch !important;
    }

    .justify-content-between {
      justify-content: space-between !important;
    }

    .text-center {
      text-align: center !important;
    }

    .container,
    .container-fluid,
    .container-xl {
      margin-left: auto;
      margin-right: auto;
      padding-left: 12px;
      padding-right: 12px;
      width: 100%;
    }

    @media (min-width: 576px) {
      .container {
        max-width: 540px;
      }
    }

    @media (min-width: 768px) {
      .container {
        max-width: 720px;
      }
    }

    @media (min-width: 992px) {
      .container {
        max-width: 960px;
      }
    }

    @media (min-width: 1200px) {
      .container,
      .container-xl {
        max-width: 1140px;
      }
    }

    @media (min-width: 1400px) {
      .container,
      .container-xl {
        max-width: 1320px;
      }
    }

    .row {
      display: flex;
      flex-wrap: wrap;
      margin-left: -12px;
      margin-right: -12px;
    }

    .row > * {
      box-sizing: border-box;
      flex-shrink: 0;
      max-width: 100%;
      padding-left: 12px;
      padding-right: 12px;
      width: 100%;
    }

    @media (min-width: 768px) {
      .col-md-4 {
        flex: 0 0 auto;
        width: 33.33333333%;
      }

      .col-md-6 {
        flex: 0 0 auto;
        width: 50%;
      }
    }

    @media (min-width: 992px) {
      .col-lg-5 {
        flex: 0 0 auto;
        width: 41.66666667%;
      }

      .col-lg-6 {
        flex: 0 0 auto;
        width: 50%;
      }

      .col-lg-7 {
        flex: 0 0 auto;
        width: 58.33333333%;
      }
    }

    @media (min-width: 1200px) {
      .col-xl-4 {
        flex: 0 0 auto;
        width: 33.33333333%;
      }
    }

    .img-fluid {
      height: auto;
      max-width: 100%;
    }

    body.index-page .header {
      background: transparent;
      position: fixed !important;
    }

    body.index-page.scrolled .header {
      background: rgba(0, 0, 0, 0.8);
    }

    body.index-page .header > .container-fluid {
      display: flex;
    }

    body.index-page .hero {
      margin-top: 0;
    }

    body.index-page .hero .hero-container {
      min-height: 90vh;
      padding-bottom: 80px;
      padding-top: 180px;
    }

    body.index-page .hero .hero-container .container {
      position: relative;
      z-index: 3;
    }

    .header .logo {
      gap: 12px;
    }

    .header .logo img.brand-logo {
      max-height: none;
      height: 78px;
      width: auto;
      object-fit: contain;
    }

    .footer .brand-logo {
      height: 52px;
      width: 52px;
      object-fit: contain;
    }

    .brand-wordmark {
      display: flex;
      flex-direction: column;
      line-height: 1.05;
    }

    .brand-wordmark strong {
      color: #ffffff;
      font-family: var(--heading-font);
      font-size: 1.2rem;
      font-weight: 800;
    }

    .brand-wordmark span {
      color: color-mix(in srgb, #ffffff, transparent 18%);
      font-size: 0.76rem;
      font-weight: 600;
      letter-spacing: 0;
    }

    .scrolled .brand-wordmark strong,
    .scrolled .brand-wordmark span,
    body:not(.index-page) .brand-wordmark strong,
    body:not(.index-page) .brand-wordmark span {
      color: var(--primary-color);
    }

    body:not(.index-page):not(.contact-page) .header {
      background: rgba(255, 255, 255, 0.96);
      box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
    }

    body:not(.index-page):not(.contact-page) .navmenu a,
    body:not(.index-page):not(.contact-page) .navmenu a:focus {
      color: var(--primary-color);
    }

    body:not(.index-page):not(.contact-page) .navmenu a:hover,
    body:not(.index-page):not(.contact-page) .navmenu .active,
    body:not(.index-page):not(.contact-page) .navmenu .active:focus {
      color: var(--accent-color) !important;
    }

    body:not(.index-page):not(.contact-page) .navmenu .dropdown ul a:hover {
      color: var(--accent-color) !important;
      background: color-mix(in srgb, var(--accent-color), transparent 88%);
    }

    body.contact-page .header {
      background: rgba(10, 18, 24, 0.82);
      backdrop-filter: blur(8px);
    }

    body.contact-page .main {
      background: #f4f7f6;
    }

    body.contact-page .contact.section {
      padding-top: 150px;
    }

    body.contact-page.scrolled .header {
      background: rgba(10, 18, 24, 0.86);
    }

    body.contact-page.scrolled .brand-wordmark strong,
    body.contact-page.scrolled .brand-wordmark span {
      color: #ffffff;
    }

    .home-login-link {
      background: var(--accent-color);
      border-radius: 6px;
      color: #ffffff !important;
      font-weight: 800;
      padding: 12px 22px !important;
    }

    .home-login-link:hover {
      background: #bf7f37;
      color: #ffffff !important;
    }

    .hero .overlay {
      background: linear-gradient(90deg, rgba(12, 42, 61, 0.86), rgba(12, 42, 61, 0.66));
    }

    .hero .hero-content h1 {
      max-width: 760px;
    }

    .hero .hero-content p {
      max-width: 720px;
    }

    .hero .btn-primary,
    .btn-primary,
    .btn-view-all {
      background: var(--accent-color);
      border-color: var(--accent-color);
      color: #ffffff !important;
      font-weight: 800;
    }

    .hero .btn-primary:hover,
    .btn-primary:hover,
    .btn-view-all:hover {
      background: #bf7f37;
      border-color: #bf7f37;
      color: #ffffff !important;
    }

    .hero .btn-secondary {
      border-color: rgba(255, 255, 255, 0.64);
      color: #ffffff;
    }

    .school-crest-card {
      background-color: color-mix(in srgb, var(--surface-color), transparent 95%);
      -webkit-backdrop-filter: blur(10px);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
      color: #ffffff;
      padding: 30px;
      text-align: center;
    }

    .school-crest-card img {
      height: 220px;
      max-width: 100%;
      object-fit: contain;
    }

    .school-crest-card h3 {
      color: #ffffff;
      font-size: 1.8rem;
      font-weight: 700;
      margin: 18px 0 6px;
    }

    .hero .school-crest-card p {
      color: rgba(255, 255, 255, 0.8) !important;
      margin: 0;
    }

    .hero .event-ticker {
      background: var(--primary-color);
    }

    .hero .event-ticker .date {
      border-color: var(--accent-color);
      color: #ffffff;
    }

    .hero .event-ticker .title {
      color: #ffffff;
    }

    .hero .event-ticker .btn-register {
      background: color-mix(in srgb, var(--accent-color), transparent 8%);
      color: #ffffff;
      font-weight: 800;
    }

    .cnsc-stat {
      color: var(--primary-color);
      font-family: var(--heading-font);
      font-size: 2rem;
      font-weight: 800;
      line-height: 1;
    }

    .identity-band,
    .soft-band {
      background: color-mix(in srgb, var(--secondary-color), #ffffff 90%);
    }

    .identity-card,
    .school-card,
    .news-card,
    .page-card,
    .contact-info-card {
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 8px;
      height: 100%;
      padding: 28px;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .identity-card:hover,
    .school-card:hover,
    .news-card:hover,
    .page-card:hover {
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
      transform: translateY(-4px);
    }

    .mission-vision-row .value-card p {
      text-align: justify;
      text-justify: inter-word;
    }

    .about-single-image {
      margin-top: 0;
    }

    .about-single-image img {
      width: 100%;
      object-fit: cover;
    }

    .institutional-values {
      background: #eef4f2;
      border-radius: 28px;
      padding: 4.5rem 1.5rem;
    }

    .institutional-values .section-header {
      margin: 0 auto 2.25rem;
      max-width: 840px;
    }

    .institutional-values .section-header h3 {
      color: var(--primary-color);
      font-size: clamp(2rem, 3vw, 2.8rem);
      font-weight: 800;
      letter-spacing: -0.03em;
      margin-bottom: 0.75rem;
    }

    .institutional-values .section-header p {
      color: color-mix(in srgb, var(--default-color), transparent 18%);
      font-size: 1.02rem;
      line-height: 1.7;
      margin: 0;
    }

    .section-kicker {
      align-items: center;
      display: inline-flex;
      gap: 0.9rem;
      margin-bottom: 1rem;
    }

    .section-kicker .line {
      background: linear-gradient(90deg, rgba(217, 154, 69, 0), var(--cnsc-gold));
      border-radius: 999px;
      display: block;
      height: 2px;
      width: 68px;
    }

    .section-kicker .religious-mark {
      color: var(--cnsc-gold);
      font-size: 1.12rem;
      font-weight: 800;
      line-height: 1;
    }

    .value-card-premium {
      align-items: center;
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 22px;
      box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
      display: flex;
      flex-direction: column;
      gap: 0.85rem;
      height: 100%;
      justify-content: center;
      min-height: 142px;
      overflow: hidden;
      padding: 1.2rem 1rem;
      position: relative;
      text-align: center;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease, background-color 0.25s ease;
    }

    .value-card-premium::before {
      background: var(--cnsc-gold);
      content: "";
      height: 4px;
      left: 0;
      position: absolute;
      top: 0;
      width: 100%;
    }

    .value-card-premium:hover {
      background: #fcfdfd;
      border-color: color-mix(in srgb, var(--cnsc-gold), transparent 55%);
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.1);
      transform: translateY(-5px);
    }

    .value-icon-circle {
      align-items: center;
      background: color-mix(in srgb, var(--cnsc-gold), transparent 86%);
      border-radius: 50%;
      color: var(--primary-color);
      display: inline-flex;
      font-size: 1.35rem;
      height: 56px;
      justify-content: center;
      width: 56px;
    }

    .value-card-premium h4 {
      color: var(--primary-color);
      font-size: 1.06rem;
      font-weight: 700;
      line-height: 1.25;
      margin: 0;
    }

    .team-hero {
      background:
        radial-gradient(circle at top right, rgba(217, 154, 69, 0.08), transparent 30%),
        linear-gradient(180deg, #eef6fa 0%, #f9fcfb 100%);
      border-bottom: 1px solid rgba(11, 58, 87, 0.06);
      padding: 5.5rem 0 3.25rem;
    }

    .hero-kicker {
      align-items: center;
      color: var(--cnsc-gold);
      display: inline-flex;
      font-size: 0.78rem;
      font-weight: 800;
      gap: 0.8rem;
      letter-spacing: 0.08em;
      margin-bottom: 1rem;
      text-transform: uppercase;
    }

    .hero-kicker .line {
      background: linear-gradient(90deg, rgba(217, 154, 69, 0), var(--cnsc-gold));
      border-radius: 999px;
      display: block;
      height: 2px;
      width: 64px;
    }

    .team-hero h1 {
      color: var(--primary-color);
      font-size: clamp(2.5rem, 5vw, 4.4rem);
      font-weight: 900;
      letter-spacing: -0.04em;
      margin-bottom: 1rem;
    }

    .team-hero p {
      color: color-mix(in srgb, var(--default-color), transparent 18%);
      font-size: clamp(1.05rem, 1.7vw, 1.2rem);
      line-height: 1.8;
      margin: 0 auto;
      max-width: 760px;
    }

    .team-page {
      background:
        linear-gradient(180deg, rgba(238, 244, 242, 0.28) 0%, rgba(255, 255, 255, 0) 22%),
        #ffffff;
      padding: 3rem 0 5rem;
    }

    .filters-wrap {
      margin-bottom: 2.5rem;
    }

    .filter-pill {
      align-items: center;
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 999px;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
      color: var(--primary-color);
      display: inline-flex;
      font-weight: 700;
      padding: 0.8rem 1.1rem;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease, background-color 0.25s ease;
    }

    .filter-pill:hover {
      border-color: color-mix(in srgb, var(--cnsc-gold), transparent 50%);
      box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
      transform: translateY(-2px);
    }

    .filter-pill.active {
      background: linear-gradient(135deg, var(--primary-color), color-mix(in srgb, var(--primary-color), #ffffff 18%));
      border-color: transparent;
      box-shadow: 0 16px 30px rgba(11, 58, 87, 0.16);
      color: #ffffff;
    }

    .team-group {
      margin-bottom: 3.5rem;
      padding-top: 0.25rem;
    }

    .team-group-header {
      margin-bottom: 1.75rem;
    }

    .team-group-header h2 {
      color: var(--primary-color);
      font-size: clamp(1.5rem, 2vw, 2rem);
      font-weight: 900;
      letter-spacing: -0.03em;
      margin-bottom: 0.45rem;
    }

    .team-group-header p {
      color: color-mix(in srgb, var(--default-color), transparent 18%);
      line-height: 1.75;
      margin-bottom: 0;
      max-width: 880px;
    }

    .team-card {
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 24px;
      box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
      cursor: pointer;
      height: 100%;
      overflow: hidden;
      position: relative;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .team-card:hover {
      border-color: color-mix(in srgb, var(--cnsc-gold), transparent 55%);
      box-shadow: 0 22px 48px rgba(15, 23, 42, 0.14);
      transform: translateY(-4px);
    }

    .team-card.is-hidden {
      display: none !important;
    }

    .photo-shell {
      background: linear-gradient(180deg, #d8ebf7 0%, #c7deed 100%);
      padding: 1rem 1rem 0;
      position: relative;
    }

    .photo-shell img,
    .team-empty-photo {
      aspect-ratio: 4 / 4.9;
      background: #dcecf7;
      border-radius: 20px;
      display: block;
      object-fit: cover;
      width: 100%;
    }

    .team-empty-photo {
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.42), rgba(255, 255, 255, 0.1)),
        #dcecf7;
    }

    .overlay-layer {
      align-items: flex-end;
      background: linear-gradient(180deg, rgba(11, 58, 87, 0.08) 0%, rgba(11, 58, 87, 0.92) 100%);
      border-radius: 20px;
      display: flex;
      inset: 1rem;
      opacity: 0;
      padding: 1rem;
      pointer-events: none;
      position: absolute;
      transform: translateY(12px);
      transition: opacity 0.28s ease, transform 0.28s ease;
    }

    .team-card:hover .overlay-layer,
    .team-card:focus-within .overlay-layer,
    .team-card.is-revealed .overlay-layer {
      opacity: 1;
      transform: translateY(0);
    }

    .overlay-box {
      background: rgba(11, 58, 87, 0.88);
      border: 1px solid rgba(217, 154, 69, 0.25);
      border-radius: 18px;
      backdrop-filter: blur(6px);
      padding: 1rem;
      width: 100%;
    }

    .overlay-kicker {
      color: rgba(255, 255, 255, 0.82);
      font-size: 0.75rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      margin-bottom: 0.45rem;
      text-transform: uppercase;
    }

    .overlay-box h4 {
      color: #ffffff;
      font-size: 1.2rem;
      font-weight: 900;
      line-height: 1.25;
      margin-bottom: 0.2rem;
    }

    .overlay-role {
      color: rgba(255, 255, 255, 0.84);
      font-size: 0.95rem;
      font-weight: 600;
      margin-bottom: 0.85rem;
    }

    .overlay-department {
      color: rgba(255, 255, 255, 0.94);
      font-size: 0.9rem;
      font-weight: 700;
      margin-bottom: 0.9rem;
    }

    .overlay-contact a {
      align-items: center;
      color: rgba(255, 255, 255, 0.96);
      display: inline-flex;
      gap: 0.55rem;
      font-size: 0.93rem;
      line-height: 1.5;
      word-break: break-word;
    }

    .overlay-contact a i {
      color: var(--cnsc-gold);
      flex-shrink: 0;
    }

    .card-body-front {
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
      padding: 1.15rem 1.15rem 1.25rem;
    }

    .team-label {
      align-self: flex-start;
      background: rgba(217, 154, 69, 0.12);
      border-radius: 999px;
      color: var(--cnsc-gold);
      display: inline-flex;
      font-size: 0.77rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      padding: 0.32rem 0.75rem;
      text-transform: uppercase;
    }

    .team-name {
      color: var(--primary-color);
      font-size: 1.15rem;
      font-weight: 900;
      line-height: 1.2;
      margin: 0;
    }

    .team-role {
      color: color-mix(in srgb, var(--default-color), transparent 18%);
      font-weight: 500;
      line-height: 1.55;
      margin: 0;
    }

    .team-department {
      color: color-mix(in srgb, var(--cnsc-gold), #7c8a99 34%);
      font-size: 0.92rem;
      font-weight: 700;
      line-height: 1.35;
      margin: 0;
    }

    .card-footer-hint {
      color: rgba(37, 54, 71, 0.65);
      display: flex;
      font-size: 0.88rem;
      gap: 1rem;
      justify-content: space-between;
      margin-top: auto;
      padding-top: 0.25rem;
    }

    .flip-hint {
      align-items: center;
      color: var(--primary-color);
      display: inline-flex;
      font-weight: 700;
      gap: 0.45rem;
    }

    .flip-hint i {
      color: var(--cnsc-gold);
    }

    @media (max-width: 1199.98px) {
      .hero-kicker .line {
        width: 52px;
      }
    }

    .faculty-staff.section {
      background: linear-gradient(180deg, #eef4f2 0%, #f8faf9 100%);
    }

    .team-intro {
      margin: 0 auto 2.75rem;
      max-width: 840px;
    }

    .team-intro .eyebrow {
      margin-bottom: 0.75rem;
    }

    .team-intro h2 {
      color: var(--primary-color);
      font-size: clamp(2rem, 3vw, 2.9rem);
      font-weight: 800;
      letter-spacing: -0.03em;
      margin-bottom: 0.9rem;
    }

    .team-intro p {
      color: color-mix(in srgb, var(--default-color), transparent 18%);
      font-size: 1.02rem;
      line-height: 1.7;
      margin: 0;
    }

    .team-nav {
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 24px;
      box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
      padding: 1.25rem;
      position: sticky;
      top: 110px;
    }

    .team-nav .departments-title {
      color: var(--primary-color);
      font-size: 1rem;
      font-weight: 800;
      letter-spacing: 0.02em;
      margin-bottom: 1rem;
      text-transform: uppercase;
    }

    .team-nav .nav-tabs {
      border: 0;
      gap: 0.6rem;
    }

    .team-nav .nav-link {
      background: #f7faf9;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 90%);
      border-radius: 16px;
      color: color-mix(in srgb, var(--default-color), transparent 6%);
      font-weight: 700;
      line-height: 1.35;
      padding: 0.9rem 1rem;
      text-align: left;
      transition: transform 0.25s ease, box-shadow 0.25s ease, background-color 0.25s ease, color 0.25s ease, border-color 0.25s ease;
      width: 100%;
    }

    .team-nav .nav-link:hover {
      border-color: color-mix(in srgb, var(--cnsc-gold), transparent 55%);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
      transform: translateX(2px);
    }

    .team-nav .nav-link.active {
      background: linear-gradient(135deg, var(--primary-color), color-mix(in srgb, var(--primary-color), #ffffff 18%));
      border-color: transparent;
      box-shadow: 0 14px 30px rgba(11, 58, 87, 0.18);
      color: #ffffff;
    }

    .team-tab-content .team-pane {
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 28px;
      box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06);
      padding: 1.75rem;
    }

    .team-pane-header {
      margin-bottom: 1.5rem;
    }

    .team-pane-header .eyebrow {
      margin-bottom: 0.5rem;
    }

    .team-pane-header h3 {
      color: var(--primary-color);
      font-size: clamp(1.35rem, 2vw, 1.8rem);
      font-weight: 800;
      margin-bottom: 0.6rem;
    }

    .team-pane-header p {
      color: color-mix(in srgb, var(--default-color), transparent 18%);
      line-height: 1.7;
      margin: 0;
    }

    .team-role-card {
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 90%);
      border-radius: 22px;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
      height: 100%;
      padding: 1.25rem;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .team-role-card:hover {
      border-color: color-mix(in srgb, var(--cnsc-gold), transparent 55%);
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.1);
      transform: translateY(-4px);
    }

    .team-role-icon {
      align-items: center;
      background: color-mix(in srgb, var(--cnsc-gold), transparent 86%);
      border-radius: 50%;
      color: var(--primary-color);
      display: inline-flex;
      font-size: 1.3rem;
      height: 52px;
      justify-content: center;
      margin-bottom: 1rem;
      width: 52px;
    }

    .team-role-card h4 {
      color: var(--primary-color);
      font-size: 1.05rem;
      font-weight: 800;
      line-height: 1.25;
      margin-bottom: 0.5rem;
    }

    .team-role-card p {
      color: color-mix(in srgb, var(--default-color), transparent 14%);
      line-height: 1.7;
      margin: 0;
    }

    .subject-grid {
      display: grid;
      gap: 0.9rem;
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .subject-pill {
      align-items: center;
      background: #f8fbfa;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 18px;
      color: var(--primary-color);
      display: flex;
      gap: 0.75rem;
      padding: 0.95rem 1rem;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease, background-color 0.25s ease;
    }

    .subject-pill:hover {
      background: #ffffff;
      border-color: color-mix(in srgb, var(--cnsc-gold), transparent 55%);
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
      transform: translateY(-3px);
    }

    .subject-pill i {
      color: var(--cnsc-gold);
      flex-shrink: 0;
      font-size: 1.15rem;
    }

    .subject-pill span {
      font-size: 0.98rem;
      font-weight: 700;
      line-height: 1.3;
    }

    @media (max-width: 1199.98px) {
      .subject-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 991.98px) {
      .about-single-image {
        margin-top: 2rem;
      }

      .institutional-values {
        border-radius: 24px;
        padding: 4rem 1.25rem;
      }

      .value-card-premium {
        min-height: 132px;
      }

      .team-nav {
        position: static;
      }

      .team-tab-content .team-pane {
        padding: 1.5rem;
      }

      .team-hero {
        padding: 4.8rem 0 3rem;
      }

      .team-page {
        padding: 2.5rem 0 4rem;
      }

      .team-card {
        min-height: 405px;
      }
    }

    .seal-card {
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 14px;
      height: 100%;
      min-height: 220px;
      overflow: hidden;
      padding: 26px;
      position: relative;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .seal-card:hover,
    .seal-card:focus-within {
      border-color: color-mix(in srgb, var(--accent-color), transparent 35%);
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
      transform: translateY(-4px);
    }

    .seal-card-head {
      display: flex;
      align-items: center;
      gap: 18px;
      min-height: 86px;
    }

    .seal-card-icon {
      width: 54px;
      height: 54px;
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: color-mix(in srgb, var(--accent-color), transparent 86%);
      color: var(--primary-color);
      font-size: 1.45rem;
      flex-shrink: 0;
    }

    .seal-card h3 {
      color: var(--primary-color);
      font-size: 1.12rem;
      font-weight: 700;
      line-height: 1.3;
      margin: 0;
      flex: 1;
    }

    .seal-card-reveal {
      max-height: 0;
      opacity: 0;
      overflow: hidden;
      transform: translateY(8px);
      transition: max-height 0.35s ease, opacity 0.25s ease, transform 0.25s ease;
    }

    .seal-card:hover .seal-card-reveal,
    .seal-card:focus-within .seal-card-reveal {
      max-height: 220px;
      opacity: 1;
      transform: translateY(0);
      margin-top: 12px;
    }

    .seal-card-reveal p {
      color: color-mix(in srgb, var(--default-color), transparent 12%);
      font-size: 0.96rem;
      line-height: 1.7;
      margin: 0;
      text-align: justify;
      text-justify: inter-word;
    }

    @media (min-width: 992px) {
      .seal-card {
        min-height: 220px;
      }

      .seal-card-reveal {
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        transform: translateY(8px);
      }

      .seal-card:hover .seal-card-reveal,
      .seal-card:focus-within .seal-card-reveal {
        max-height: 180px;
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (hover: none) {
      .seal-card-reveal {
        max-height: none;
        opacity: 1;
        overflow: visible;
        transform: none;
        margin-top: 14px;
      }
    }

    .identity-card i,
    .school-card i,
    .news-card i,
    .page-card i,
    .contact-info-card i {
      color: var(--primary-color);
      display: inline-block;
      font-size: 2rem;
      margin-bottom: 16px;
    }

    .identity-card h3,
    .school-card h3,
    .news-card h3,
    .page-card h3 {
      color: var(--primary-color);
      font-size: 1.2rem;
    }

    .news-date,
    .eyebrow {
      color: var(--cnsc-gold);
      display: block;
      font-size: 0.85rem;
      font-weight: 800;
      margin-bottom: 10px;
      text-transform: uppercase;
    }

    .event-card-link {
      color: inherit;
      display: block;
      height: 100%;
    }

    .event-card-link:hover {
      color: inherit;
    }

    .event-card-link .event-card {
      height: 100%;
    }

    .event-card-link .event-content h3 {
      transition: color 0.2s ease;
    }

    .event-card-link:hover .event-content h3 {
      color: var(--cnsc-gold);
    }

    .news-card h3 a {
      color: inherit;
    }

    .news-card .read-more {
      color: var(--primary-color);
      display: inline-flex;
      font-weight: 800;
      margin-top: 8px;
    }

    .recent-news-carousel {
      padding-bottom: 44px;
    }

    .recent-news-slide {
      background: #ffffff;
      border: 1px solid color-mix(in srgb, var(--default-color), transparent 88%);
      border-radius: 8px;
      overflow: hidden;
    }

    .recent-news-image-link {
      display: block;
      height: 100%;
      min-height: 360px;
    }

    .recent-news-slide-image {
      display: block;
      height: 100%;
      min-height: 360px;
      object-fit: cover;
      width: 100%;
    }

    .recent-news-slide-content {
      display: flex;
      flex-direction: column;
      justify-content: center;
      min-height: 360px;
      padding: 42px;
    }

    .recent-news-slide h3 {
      color: var(--primary-color);
      font-size: clamp(1.75rem, 3vw, 2.7rem);
      line-height: 1.14;
      margin-bottom: 18px;
    }

    .recent-news-slide h3 a {
      color: inherit;
    }

    .recent-news-summary {
      color: color-mix(in srgb, var(--default-color), transparent 12%);
      font-size: 1.05rem;
      line-height: 1.65;
      margin-bottom: 24px;
    }

    .recent-news-meta {
      align-items: center;
      display: flex;
      gap: 14px;
    }

    .recent-news-meta img {
      border-radius: 50%;
      height: 48px;
      object-fit: contain;
      width: 48px;
    }

    .recent-news-meta p,
    .recent-news-meta time {
      display: block;
      margin: 0;
    }

    .recent-news-meta p {
      color: var(--primary-color);
      font-weight: 800;
    }

    .recent-news-meta time {
      color: color-mix(in srgb, var(--default-color), transparent 30%);
      font-size: 0.92rem;
    }

    .recent-news-carousel .carousel-control-prev,
    .recent-news-carousel .carousel-control-next {
      background: var(--primary-color);
      border-radius: 50%;
      height: 44px;
      opacity: 1;
      top: calc(50% - 22px);
      width: 44px;
    }

    .recent-news-carousel .carousel-control-prev {
      left: 18px;
    }

    .recent-news-carousel .carousel-control-next {
      right: 18px;
    }

    .recent-news-carousel .carousel-indicators {
      bottom: 0;
      margin-bottom: 0;
    }

    .recent-news-carousel .carousel-indicators [data-bs-target] {
      background-color: var(--primary-color);
      height: 10px;
      width: 10px;
    }

    .news-back-link {
      align-items: center;
      color: rgba(255, 255, 255, 0.82);
      display: inline-flex;
      font-weight: 800;
      gap: 8px;
      margin-bottom: 20px;
    }

    .news-back-link:hover {
      color: #ffffff;
    }

    .news-detail-title .eyebrow {
      color: rgba(255, 255, 255, 0.72);
    }

    .news-detail-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 12px 24px;
      font-weight: 700;
      margin-top: 20px;
      opacity: 0.92;
    }

    .news-detail-image {
      border-radius: 8px;
      display: block;
      max-height: 560px;
      object-fit: cover;
      width: 100%;
    }

    .news-detail-lead {
      color: var(--primary-color);
      font-family: var(--heading-font);
      font-size: clamp(1.25rem, 2vw, 1.65rem);
      font-weight: 700;
      line-height: 1.45;
      margin: 34px 0 24px;
    }

    .news-detail-body {
      color: color-mix(in srgb, var(--default-color), #111827 16%);
      font-size: 1.05rem;
      line-height: 1.8;
      margin-top: 28px;
    }

    .news-detail-body h2,
    .news-detail-body h3,
    .news-detail-body h4 {
      color: var(--primary-color);
      margin: 32px 0 14px;
    }

    .news-detail-body p,
    .news-detail-body ul,
    .news-detail-body ol,
    .news-detail-body blockquote {
      margin-bottom: 20px;
    }

    .news-detail-body blockquote {
      border-left: 4px solid var(--cnsc-gold);
      color: var(--primary-color);
      font-family: var(--heading-font);
      font-size: 1.2rem;
      font-weight: 700;
      padding-left: 20px;
    }

    .news-detail-body a {
      color: var(--primary-color);
      font-weight: 800;
      text-decoration: underline;
    }

    .event-detail-card {
      display: grid;
      gap: 28px;
      grid-template-columns: 140px minmax(0, 1fr);
    }

    .event-detail-date {
      align-items: center;
      background: color-mix(in srgb, var(--primary-color), #ffffff 92%);
      border-radius: 8px;
      color: var(--primary-color);
      display: flex;
      flex-direction: column;
      justify-content: center;
      min-height: 140px;
      padding: 18px;
      text-align: center;
    }

    .event-detail-date .month,
    .event-detail-date .year {
      font-weight: 800;
      text-transform: uppercase;
    }

    .event-detail-date .day {
      font-family: var(--heading-font);
      font-size: 3rem;
      font-weight: 800;
      line-height: 1;
    }

    .event-detail-meta-list {
      display: grid;
      gap: 12px;
      margin: 24px 0;
    }

    .event-detail-meta-list div {
      align-items: center;
      display: flex;
      gap: 10px;
    }

    .event-detail-meta-list i {
      color: var(--cnsc-gold);
      font-size: 1.2rem;
    }

    @media (max-width: 991px) {
      .recent-news-image-link,
      .recent-news-slide-image,
      .recent-news-slide-content {
        min-height: 300px;
      }

      .recent-news-slide-content {
        padding: 28px;
      }
    }

    @media (max-width: 575px) {
      .recent-news-carousel .carousel-control-prev,
      .recent-news-carousel .carousel-control-next {
        height: 38px;
        top: 128px;
        width: 38px;
      }

      .recent-news-carousel .carousel-control-prev {
        left: 10px;
      }

      .recent-news-carousel .carousel-control-next {
        right: 10px;
      }

      .recent-news-slide h3 {
        font-size: 1.55rem;
      }

      .event-detail-card {
        grid-template-columns: 1fr;
      }
    }

    .page-title {
      background: linear-gradient(135deg, rgba(23, 72, 102, 0.95), rgba(79, 155, 177, 0.9)), url("{{ asset('niceschool/assets/img/education/showcase-2.webp') }}") center/cover;
      color: #ffffff;
      padding-top: 150px;
    }

    .page-title h1,
    .page-title p,
    .page-title .breadcrumbs,
    .page-title .breadcrumbs a {
      color: #ffffff;
    }

    .footer a {
      color: color-mix(in srgb, #ffffff, var(--accent-color) 28%);
    }

    @media (max-width: 1199px) {
      .brand-wordmark strong {
        font-size: 1rem;
      }
    }

    @media (max-width: 575px) {
      .header .logo img.brand-logo {
        height: 60px;
        width: auto;
      }

      .brand-wordmark strong {
        font-size: 0.86rem;
      }

      .brand-wordmark span {
        display: none;
      }

      .school-crest-card {
        margin-top: 28px;
        padding: 24px;
      }

      .school-crest-card img {
        height: 160px;
      }

      .institutional-values {
        padding: 3.5rem 1rem;
      }

      .section-kicker .line {
        width: 42px;
      }

      .value-card-premium {
        min-height: 122px;
        padding: 1rem;
      }

      .value-icon-circle {
        font-size: 1.2rem;
        height: 50px;
        width: 50px;
      }

      .value-card-premium h4 {
        font-size: 1rem;
      }

      .team-intro {
        margin-bottom: 2rem;
      }

      .team-nav {
        border-radius: 20px;
        padding: 1rem;
      }

      .team-nav .nav-link {
        border-radius: 14px;
      }

      .team-tab-content .team-pane {
        border-radius: 22px;
        padding: 1.25rem;
      }

      .subject-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr));
      }

      .team-hero {
        padding: 4.6rem 0 2.75rem;
      }

      .team-hero .hero-kicker {
        gap: 0.65rem;
      }

      .team-hero .hero-kicker .line {
        width: 38px;
      }

      .team-page {
        padding: 2.25rem 0 3.5rem;
      }

      .filter-pill {
        padding: 0.72rem 0.95rem;
      }

      .team-group {
        margin-bottom: 2.5rem;
      }

      .team-card {
        min-height: 390px;
      }

      .photo-shell img,
      .team-empty-photo {
        aspect-ratio: 4 / 4.5;
      }

    }
  </style>
</head>

<body class="@yield('body_class', 'inner-page')">

  <header id="header" class="header d-flex align-items-center fixed-top">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
      <a href="{{ route('public.home') }}" class="logo d-flex align-items-center">
        <img src="{{ asset('brand/logo-cnsc.png') }}" alt="Escudo Colegio Nuestra Señora del Carmen" class="brand-logo">
        <span class="brand-wordmark">
          <strong>Colegio Nuestra Señora del Carmen</strong>
          <span>Valdivia</span>
        </span>
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="{{ route('public.home') }}" class="{{ request()->routeIs('public.home') ? 'active' : '' }}">Inicio</a></li>
          <li class="dropdown">
            <a href="#" class="{{ request()->routeIs('public.about', 'public.admissions', 'public.faculty', 'public.campus') ? 'active' : '' }}"><span>Colegio</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
            <ul>
              <li><a href="{{ route('public.about') }}">Nosotros</a></li>
              <li><a href="{{ route('public.admissions') }}">Admisión</a></li>
              <li><a href="{{ route('public.faculty') }}">Equipo</a></li>
              <li><a href="{{ route('public.campus') }}">Instalaciones</a></li>
            </ul>
          </li>
          <li><a href="{{ route('public.students-life') }}" class="{{ request()->routeIs('public.students-life') ? 'active' : '' }}">Vida estudiantil</a></li>
          <li><a href="{{ route('public.news') }}" class="{{ request()->routeIs('public.news*') ? 'active' : '' }}">Noticias</a></li>
          <li><a href="{{ route('public.events') }}" class="{{ request()->routeIs('public.events*') ? 'active' : '' }}">Eventos</a></li>
          <li><a href="{{ route('public.contact') }}" class="{{ request()->routeIs('public.contact') ? 'active' : '' }}">Contacto</a></li>
          <li><a href="{{ url('/login') }}" class="home-login-link">Sistema interno</a></li>
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>
    </div>
  </header>

  <main class="main">
    @yield('content')
  </main>

  <footer id="contacto" class="footer position-relative dark-background">
    <div class="container footer-top">
      <div class="row gy-4">
        <div class="col-lg-5 col-md-6 footer-about">
          <a href="{{ route('public.home') }}" class="logo d-flex align-items-center">
            <img src="{{ asset('brand/logo-cnsc.png') }}" alt="Escudo CNSC" class="brand-logo">
            <span class="sitename">Colegio Nuestra Señora del Carmen</span>
          </a>
          <div class="footer-contact pt-3">
            <p>Eleuterio Ramírez #1886</p>
            <p>Valdivia, Chile</p>
            <p class="mt-3"><strong>Secretaría:</strong> <span>632 244731</span></p>
            <p><strong>Horario:</strong> <span>9:00 a 17:00 hrs</span></p>
          </div>
          <div class="social-links d-flex mt-4">
            <a href="https://www.instagram.com/cnscvaldivia.cl/" target="_blank" rel="noopener noreferrer" aria-label="Instagram del colegio">
              <i class="bi bi-instagram"></i>
            </a>
          </div>
        </div>
        <div class="col-lg-3 col-md-3 footer-links">
          <h4>Colegio</h4>
          <ul>
            <li><a href="{{ route('public.about') }}">Nosotros</a></li>
            <li><a href="{{ route('public.admissions') }}">Admisión</a></li>
            <li><a href="{{ route('public.faculty') }}">Equipo</a></li>
            <li><a href="{{ route('public.campus') }}">Instalaciones</a></li>
          </ul>
        </div>
        <div class="col-lg-4 col-md-3 footer-links">
          <h4>Accesos</h4>
          <ul>
            <li><a href="{{ route('public.students-life') }}">Vida estudiantil</a></li>
            <li><a href="{{ route('public.news') }}">Noticias</a></li>
            <li><a href="{{ route('public.events') }}">Eventos</a></li>
            <li><a href="{{ route('public.contact') }}">Contacto</a></li>
            <li><a href="{{ url('/login') }}">Sistema interno</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="container copyright text-center mt-4">
      <p>© <span>{{ date('Y') }}</span> <strong class="px-1 sitename">Colegio Nuestra Señora del Carmen</strong> <span>Todos los derechos reservados</span></p>
    </div>
  </footer>

  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  @if(file_exists(public_path($bootstrapJsPath)))
    <script src="{{ asset($bootstrapJsPath) }}?v={{ $bootstrapJsAssetVersion }}"></script>
  @else
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
  @endif
  <script src="{{ asset('niceschool/assets/vendor/aos/aos.js') }}"></script>
  <script src="{{ asset('niceschool/assets/vendor/purecounter/purecounter_vanilla.js') }}"></script>
  <script src="{{ asset('niceschool/assets/vendor/imagesloaded/imagesloaded.pkgd.min.js') }}"></script>
  <script src="{{ asset('niceschool/assets/vendor/isotope-layout/isotope.pkgd.min.js') }}"></script>
  <script src="{{ asset('niceschool/assets/vendor/swiper/swiper-bundle.min.js') }}"></script>
  <script src="{{ asset('niceschool/assets/vendor/glightbox/js/glightbox.min.js') }}"></script>
  <script src="{{ asset('niceschool/assets/js/main.js') }}"></script>
</body>

</html>
