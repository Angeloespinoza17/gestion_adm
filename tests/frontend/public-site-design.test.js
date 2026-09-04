import { readFileSync } from "node:fs";
import { describe, expect, it } from "vitest";

const read = (path) => readFileSync(path, "utf8");

const layout = read("resources/views/public/layouts/site.blade.php");
const home = read("resources/views/public/home.blade.php");
const about = read("resources/views/public/pages/about.blade.php");
const faculty = read("resources/views/public/pages/faculty.blade.php");
const news = read("resources/views/public/pages/news.blade.php");
const events = read("resources/views/public/pages/events.blade.php");
const newsShow = read("resources/views/public/pages/news-show.blade.php");
const eventShow = read("resources/views/public/pages/event-show.blade.php");
const contact = read("resources/views/public/pages/contact.blade.php");
const admissions = read("resources/views/public/pages/admissions.blade.php");
const studentsLife = read("resources/views/public/pages/students-life.blade.php");
const educationalProject = read("resources/views/public/pages/educational-project.blade.php");
const communityOrganization = read("resources/views/public/pages/community-organization.blade.php");
const publicRoutes = read("routes/web.php");
const seals = read("resources/views/components/public/sellos.blade.php");
const premiumCss = read("public/niceschool/assets/css/cnsc-premium.css");
const publicJs = read("public/niceschool/assets/js/main.js");

describe("Sistema visual del sitio público CNSC", () => {
  it("aplica una capa visual compartida y conserva una navegación accesible", () => {
    expect(layout).toContain("niceschool/assets/css/cnsc-premium.css");
    expect(layout).toContain('<body class="public-site');
    expect(layout).toContain('class="site-skip-link"');
    expect(layout).toContain('<main id="main-content"');
    expect(layout).toContain('<button type="button" class="mobile-nav-toggle');
    expect(layout).toContain('aria-controls="navmenu"');
    expect(layout).toContain('aria-expanded="false"');
    expect(layout).toContain(".row-cols-lg-3 > *");
    expect(layout).toContain(".col-lg-10");
    expect(layout).toContain('class="site-invitation"');
  });

  it("presenta una portada editorial con medios web optimizados", () => {
    expect(home).toContain("hero-eyebrow");
    expect(home).toContain("hero-actions");
    expect(home).toContain("hero-identity-chips");
    expect(home).toContain("school-crest-card");
    expect(home).toContain("event-ticker__list");
    expect(home).toContain("video-2-web.mp4");
    expect(home).toContain("hero-poster.webp");
    expect(home).toContain('media="(min-width: 768px)"');
    expect(home).not.toContain("video-2.mp4");
    expect(home).toContain('href="{{ route(\'public.educational-project\') }}" class="btn-primary"');
    expect(home).toContain("Conoce nuestro proyecto educativo");
  });

  it("mantiene consistentes la misión, visión y portada del proyecto educativo", () => {
    expect(about).toMatch(/<h3>Misión<\/h3>\s*<p>Formar niños, niñas y jóvenes con una sólida preparación cristiano-católica/);
    expect(about).toMatch(/<h3>Visión<\/h3>\s*<p>Queremos hacer de nuestro Colegio una comunidad de Fe y Cultura/);
    expect(premiumCss).toContain(".public-site.public-educational-project-page .page-title.page-title--educational-project {");
    expect(premiumCss).toContain(".public-site.public-educational-project-page .page-title.page-title--educational-project::after {");
    expect(educationalProject).toContain("Síntesis PEI {{ $summaryYear }}");
    expect(educationalProject).toContain("Esta síntesis corresponde al PEI base {{ $summaryYear }}");
    expect(premiumCss).toContain("scroll-margin-top: 120px;");
  });

  it("convierte Equipo en un directorio compacto y utilizable sin hover", () => {
    expect(faculty).toContain("<details class=\"team-group\"");
    expect(faculty).toContain("team-people-grid");
    expect(faculty).toContain("team-card--placeholder");
    expect(faculty).toContain("team-contact-link");
    expect(faculty).toContain('href="mailto:{{ $person[\'email\'] }}"');
    expect(faculty).not.toContain('role="button" aria-label="{{ $person');
    expect(faculty).not.toContain("overlay-layer");
  });

  it("diferencia noticias y eventos con tarjetas editoriales semánticas", () => {
    expect(news).toContain("news-card--editorial");
    expect(news).toContain("news-card__media");
    expect(news).toContain("news-card__meta");
    expect(news).toContain("<time");
    expect(events).toContain("event-card--institutional");
    expect(events).toContain("event-card__status");
    expect(events).toContain("is-upcoming");
    expect(events).toContain("agenda-state");
    expect(events).toContain("<time");
  });

  it("diseña los detalles editoriales sin exponer datos personales en URLs", () => {
    expect(newsShow).toContain("detail-hero--news");
    expect(newsShow).toContain("detail-shell");
    expect(newsShow).toContain("detail-related");
    expect(eventShow).toContain("detail-hero--event");
    expect(eventShow).toContain("event-detail-sidebar");
    expect(eventShow).toContain("event-registration__cta");
    expect(eventShow).not.toContain('method="get"');
    expect(eventShow).not.toContain('name="correo"');
    expect(eventShow).not.toContain('name="telefono"');
  });

  it("mantiene el formulario de contacto explícito y accesible", () => {
    expect(contact).toContain("contact-summary-grid");
    expect(contact).toContain('class="map-section contact-map contact-map--blue"');
    expect(contact).toContain('class="contact-map__frame"');
    expect(contact).toContain('src="https://maps.google.com/maps?q=Eleuterio%20Ram%C3%ADrez%201886%2C%20Valdivia%2C%20Chile&output=embed"');
    expect(contact).toContain('href="https://maps.google.com/?q=Eleuterio+Ram%C3%ADrez+1886,+Valdivia,+Chile"');
    expect(contact).toContain('loading="lazy"');
    expect(contact).toContain('title="Ubicación Colegio Nuestra Señora del Carmen, Valdivia"');
    expect(contact).toContain('for="contact-name"');
    expect(contact).toContain('autocomplete="name"');
    expect(contact).toContain('type="email"');
    expect(contact).toContain('autocomplete="tel"');
    expect(contact).toContain('aria-invalid="{{ $errors->has');
    expect(contact).toContain('role="alert"');
    expect(contact).toContain("@csrf");
    expect(premiumCss).toContain(".public-site.contact-page .contact-map__frame");
    expect(premiumCss).toMatch(/\.public-site\.contact-page \.contact-map \{[^}]*height: 500px;/s);
    expect(premiumCss).toContain("filter: grayscale(100%) sepia(32%) saturate(440%) hue-rotate(165deg) brightness(94%) contrast(92%);");
    expect(premiumCss).toContain(".public-site.contact-page .contact-map--blue::after");
    expect(premiumCss).toContain("mix-blend-mode: color;");
    expect(premiumCss).toContain("pointer-events: none;");
    expect(premiumCss).toMatch(/@media \(max-width: 767\.98px\)[\s\S]*?\.public-site\.contact-page \.contact-map \{[^}]*height: 400px;/);
  });

  it("sincroniza el menú móvil y respeta movimiento reducido", () => {
    expect(publicJs).toContain("mobileNavToggle(forceOpen = null, restoreFocus = false)");
    expect(publicJs).toContain("aria-expanded");
    expect(publicJs).toContain("event.key === 'Escape'");
    expect(publicJs).toContain("event.key !== 'Tab'");
    expect(publicJs).toContain("focusableItems");
    expect(publicJs).toContain("prefers-reduced-motion: reduce");
    expect(publicJs).toContain("window.innerWidth < 768");
    expect(premiumCss).toContain("@media (prefers-reduced-motion: reduce)");
    expect(premiumCss).toContain(".public-site [data-aos]");
  });

  it("incluye reglas responsive para portada, directorio y contacto", () => {
    expect(premiumCss).toContain(".public-site .hero-grid");
    expect(premiumCss).toContain(".public-site .team-people-grid");
    expect(premiumCss).toContain(".public-site .contact-summary-grid");
    expect(premiumCss).toContain(".public-site .navmenu .dropdown:focus-within > ul");
    expect(premiumCss).toContain("grid-template-columns: repeat(4, minmax(0, 1fr));");
    expect(premiumCss).toContain("@media (max-width: 767.98px)");
    expect(premiumCss).toContain("grid-template-columns: 1fr;");
  });

  it("mantiene legibles las tarjetas y reduce la escala del cierre institucional", () => {
    expect(home).not.toMatch(/testimonial-item[^>]*data-aos/);
    expect(home).not.toMatch(/stat-box[^>]*data-aos/);
    expect(seals).not.toContain("data-aos-delay");
    expect(admissions).not.toMatch(/col-md-4[^>]*data-aos/);
    expect(studentsLife).not.toMatch(/col-lg-4 col-md-6[^>]*data-aos/);
    expect(news).not.toMatch(/col-lg-4 col-md-6[^>]*data-aos/);
    expect(events).not.toMatch(/col-lg-6[^>]*data-aos/);
    expect(contact).not.toMatch(/<div data-aos="fade-up" data-aos-delay="(?:100|200|300)">\s*<div class="contact-info-box">/);
    expect(premiumCss).toContain(".public-site .testimonials .testimonial-item.highlight .testimonial-content p");
    expect(premiumCss).toContain(".public-site .value-card-premium:hover");
    expect(premiumCss).toContain("transform: none;");
    expect(premiumCss).toContain(".public-site .site-footer .copyright .sitename");
    expect(premiumCss).toContain("font-size: 0.55rem;");
    expect(premiumCss).toMatch(/\.public-site \.site-footer \.footer-about \.logo img\.brand-logo \{[^}]*width: auto;[^}]*height: 58px;[^}]*max-height: none;[^}]*aspect-ratio: 300 \/ 322;/s);
  });

  it("integra CGPA, CDE y Comité Paritario al sitio sin exponer datos privados", () => {
    expect(publicRoutes).toContain("PublicCommunityOrganizationController::class, 'cgpa'");
    expect(publicRoutes).toContain("PublicCommunityOrganizationController::class, 'cde'");
    expect(publicRoutes).toContain("PublicCommunityOrganizationController::class, 'jointCommittee'");
    expect(layout).toContain("CGPA · Centro General de Padres y Apoderados");
    expect(layout).toContain("CDE · Centro de Estudiantes");
    expect(layout).toContain("Comité Paritario");
    expect(layout).toContain("school-menu-dropdown");
    expect(communityOrganization).toContain("community-organization-hero");
    expect(communityOrganization).toContain("community-organization-period__facts");
    expect(communityOrganization).toContain("community-organization-members");
    expect(communityOrganization).toContain("Nómina pública en preparación");
    expect(communityOrganization).toContain("public_members_count");
    expect(communityOrganization).not.toContain("rut");
    expect(communityOrganization).not.toContain("email");
    expect(communityOrganization).not.toContain("phone");
    expect(premiumCss).toContain(".public-site .community-organization-hero");
    expect(premiumCss).toContain(".public-site .community-organization-members");
    expect(premiumCss).toContain(".public-site .navmenu .dropdown ul.school-menu-dropdown");
    expect(premiumCss).toMatch(/@media \(max-width: 767\.98px\)[\s\S]*?\.public-site \.community-organization-hero \{/);
  });

  it("extiende el sistema de gradientes a los botones de toda la web pública", () => {
    expect(home).not.toContain("btn-gradient-trial");
    expect(premiumCss).toContain("--cnsc-action-gradient:");
    expect(premiumCss).toContain("--cnsc-action-gradient-secondary:");
    expect(premiumCss).toContain("--cnsc-action-gradient-soft:");
    expect(premiumCss).toContain("body.public-site .btn-primary");
    expect(premiumCss).toContain("body.public-site .home-login-link");
    expect(premiumCss).toContain("body.public-site .news-card__action");
    expect(premiumCss).toContain("body.public-site .share-button");
    expect(premiumCss).toContain("body.public-site .mobile-nav-toggle");
    expect(premiumCss).toContain("body.public-site .filter-pill.active");
    expect(premiumCss).toContain("transform: none !important;");
  });

  it("separa el cierre institucional y mantiene compactas las flechas de noticias", () => {
    expect(premiumCss).toContain("padding: 1.15rem 0 clamp(1.6rem, 3vw, 2.4rem) !important;");
    expect(premiumCss).toContain("body.public-site .site-invitation__panel");
    expect(premiumCss).toContain("body.public-site .news-card__action i");
    expect(premiumCss).toContain("font-size: 0.82rem !important;");
    expect(premiumCss).toContain("transform: none !important;");
    expect(home.match(/btn-register__icon/g)).toHaveLength(3);
    expect(premiumCss).toMatch(/body\.public-site\.index-page \.hero \.event-ticker a\.btn-register \{[^}]*border-radius: 999px !important;/s);
  });
});
