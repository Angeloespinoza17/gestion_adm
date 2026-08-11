<script>
const ACTIONS = [
  { label: "Panel", description: "Estado operativo general", to: "/porter/dashboard", icon: "bx bx-grid-alt", tone: "blue" },
  { label: "Buscar estudiante", description: "Fichas, alertas y autorizados", to: "/porter/students", icon: "bx bx-search-alt", tone: "blue" },
  { label: "Registrar retiro", description: "Salida segura y trazable", to: "/porter/withdrawals", icon: "bx bx-log-out-circle", tone: "indigo" },
  { label: "Recepción", description: "Objetos y documentos", to: "/porter/received-items", icon: "bx bx-package", tone: "amber" },
  { label: "Mercadería", description: "Ingresos institucionales", to: "/porter/goods", icon: "bx bx-cube", tone: "violet" },
  { label: "Visitas", description: "Control de acceso", to: "/porter/visits", icon: "bx bx-id-card", tone: "emerald" },
  { label: "Proveedores", description: "Servicios externos", to: "/porter/providers", icon: "bx bx-briefcase-alt-2", tone: "sky" },
  { label: "Bitácora", description: "Novedades del turno", to: "/porter/daily-log", icon: "bx bx-notepad", tone: "rose" },
  { label: "Llaves", description: "Préstamos y devoluciones", to: "/porter/keys", icon: "bx bx-key", tone: "slate" },
  { label: "Reportes", description: "Indicadores y trazabilidad", to: "/porter/reports", icon: "bx bx-bar-chart-alt-2", tone: "sky" },
];

export default {
  name: "PorterActionDeck",
  props: {
    title: {
      type: String,
      default: "Acciones inmediatas",
    },
    subtitle: {
      type: String,
      default: "Continúa con una tarea relacionada sin perder el contexto operativo.",
    },
    eyebrow: {
      type: String,
      default: "Operación rápida",
    },
    featuredRoutes: {
      type: Array,
      default: () => ["/porter/students", "/porter/withdrawals"],
    },
    compact: {
      type: Boolean,
      default: false,
    },
    maxItems: {
      type: Number,
      default: null,
    },
  },
  computed: {
    actions() {
      const currentRoute = this.$route?.path;
      const available = ACTIONS.filter((action) => action.to !== currentRoute);
      const featured = this.featuredRoutes
        .map((route) => available.find((action) => action.to === route))
        .filter(Boolean);
      const featuredPaths = new Set(featured.map((action) => action.to));
      const secondary = available.filter((action) => !featuredPaths.has(action.to));
      const limit = this.maxItems || (this.compact ? 6 : 8);

      return featured.concat(secondary).slice(0, limit).map((action, index) => ({
        ...action,
        featured: featuredPaths.has(action.to),
        secondaryFeatured: index === 1 && featuredPaths.has(action.to),
      }));
    },
  },
};
</script>

<template>
  <section class="porter-action-deck" :class="{ 'porter-action-deck--compact': compact }" aria-labelledby="porter-action-deck-title">
    <div class="porter-action-deck__header">
      <div>
        <div class="porter-action-deck__eyebrow"><i class="bx bxs-zap"></i>{{ eyebrow }}</div>
        <h2 id="porter-action-deck-title">{{ title }}</h2>
        <p>{{ subtitle }}</p>
      </div>
      <span class="porter-action-deck__count"><i class="bx bx-command"></i>{{ actions.length }} accesos</span>
    </div>

    <div class="porter-action-deck__grid">
      <router-link
        v-for="action in actions"
        :key="action.to"
        :to="action.to"
        class="porter-action"
        :class="[
          { 'porter-action--featured': action.featured, 'porter-action--featured-secondary': action.secondaryFeatured },
          `porter-action--${action.tone}`,
        ]"
      >
        <span class="porter-action__icon"><i :class="action.icon"></i></span>
        <span class="porter-action__content">
          <strong>{{ action.label }}</strong>
          <small>{{ action.description }}</small>
        </span>
        <span class="porter-action__arrow" aria-hidden="true"><i class="bx bx-right-arrow-alt"></i></span>
      </router-link>
    </div>
  </section>
</template>

<style scoped>
.porter-action-deck {
  background:
    radial-gradient(circle at 100% 0, rgba(74, 114, 184, 0.1), transparent 28%),
    linear-gradient(145deg, rgba(var(--bs-body-bg-rgb), 0.97), rgba(242, 247, 255, 0.92));
  border: 1px solid rgba(182, 199, 224, 0.7);
  border-radius: 1rem;
  box-shadow: 0 0.85rem 2.6rem rgba(37, 67, 110, 0.09);
  margin-bottom: 1rem;
  overflow: hidden;
  padding: 1.15rem;
  position: relative;
}

.porter-action-deck::before {
  background: linear-gradient(90deg, #62e5ad 0%, #4e7ed1 52%, transparent 100%);
  content: "";
  height: 0.2rem;
  inset: 0 0 auto;
  position: absolute;
}

.porter-action-deck__header {
  align-items: flex-end;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding: 0.15rem 0.1rem 0;
}

.porter-action-deck__eyebrow {
  align-items: center;
  color: #3d68a0;
  display: flex;
  font-size: 0.68rem;
  font-weight: 800;
  gap: 0.32rem;
  letter-spacing: 0.1em;
  margin-bottom: 0.25rem;
  text-transform: uppercase;
}

.porter-action-deck__eyebrow i {
  color: #d49a32;
  font-size: 0.9rem;
}

.porter-action-deck h2 {
  color: var(--porter-ink);
  font-size: 1.08rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  margin: 0;
}

.porter-action-deck p {
  color: var(--porter-muted);
  font-size: 0.78rem;
  margin: 0.28rem 0 0;
}

.porter-action-deck__count {
  align-items: center;
  background: rgba(45, 89, 145, 0.07);
  border: 1px solid rgba(85, 122, 176, 0.13);
  border-radius: 999px;
  color: #49688f;
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 0.72rem;
  font-weight: 700;
  gap: 0.38rem;
  padding: 0.42rem 0.68rem;
}

.porter-action-deck__grid {
  display: grid;
  gap: 0.7rem;
  grid-template-columns: repeat(6, minmax(0, 1fr));
}

.porter-action {
  --action-accent: #5573a2;
  --action-tint: rgba(85, 115, 162, 0.1);
  align-items: center;
  background: rgba(var(--bs-body-bg-rgb), 0.92);
  border: 1px solid rgba(183, 197, 219, 0.64);
  border-radius: 0.8rem;
  box-shadow: inset 0 0.18rem 0 var(--action-accent);
  color: var(--porter-ink);
  display: grid;
  gap: 0.65rem;
  grid-template-columns: auto minmax(0, 1fr) auto;
  min-height: 6.3rem;
  overflow: hidden;
  padding: 0.85rem;
  position: relative;
  text-decoration: none;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.porter-action::after {
  background: radial-gradient(circle, var(--action-tint), transparent 68%);
  content: "";
  height: 6rem;
  opacity: 0.85;
  pointer-events: none;
  position: absolute;
  right: -2.25rem;
  top: -2.8rem;
  width: 6rem;
}

.porter-action:hover {
  border-color: var(--action-accent);
  box-shadow: 0 0.8rem 1.6rem rgba(36, 61, 99, 0.12), inset 0 0.2rem 0 var(--action-accent);
  color: var(--porter-ink);
  transform: translateY(-3px);
}

.porter-action:focus-visible {
  box-shadow: 0 0 0 0.22rem rgba(var(--bs-primary-rgb), 0.17), inset 0 0.18rem 0 var(--action-accent);
  outline: none;
}

.porter-action--featured {
  background:
    radial-gradient(circle at 91% 3%, rgba(255, 255, 255, 0.18), transparent 28%),
    linear-gradient(125deg, #19345f 0%, #2f5e9e 100%);
  border-color: rgba(51, 95, 153, 0.78);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.13), 0 0.65rem 1.4rem rgba(31, 61, 104, 0.13);
  color: #fff;
  grid-column: span 3;
  min-height: 7.15rem;
  padding: 1rem 1.1rem;
}

.porter-action-deck--compact .porter-action-deck__grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.porter-action-deck--compact .porter-action--featured {
  grid-column: span 2;
}

.porter-action--featured::before {
  background-image: radial-gradient(rgba(255, 255, 255, 0.22) 0.7px, transparent 0.7px);
  background-size: 12px 12px;
  content: "";
  inset: 0 0 0 58%;
  opacity: 0.26;
  pointer-events: none;
  position: absolute;
}

.porter-action--featured-secondary {
  background:
    radial-gradient(circle at 91% 3%, rgba(255, 255, 255, 0.17), transparent 28%),
    linear-gradient(125deg, #293467 0%, #505fba 100%);
  border-color: rgba(76, 89, 172, 0.8);
}

.porter-action--featured:hover {
  border-color: rgba(113, 231, 183, 0.75);
  box-shadow: 0 0.9rem 1.8rem rgba(31, 61, 104, 0.2), inset 0 -0.2rem 0 #6ce3b5;
  color: #fff;
}

.porter-action__icon {
  align-items: center;
  background: var(--action-tint);
  border-radius: 0.68rem;
  color: var(--action-accent);
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.22rem;
  height: 2.65rem;
  justify-content: center;
  position: relative;
  width: 2.65rem;
  z-index: 1;
}

.porter-action__content {
  display: flex;
  flex-direction: column;
  min-width: 0;
  position: relative;
  z-index: 1;
}

.porter-action__content strong {
  font-size: 0.88rem;
  font-weight: 750;
  line-height: 1.2;
}

.porter-action__content small {
  color: var(--porter-muted);
  font-size: 0.69rem;
  line-height: 1.35;
  margin-top: 0.28rem;
}

.porter-action__arrow {
  align-items: center;
  color: var(--action-accent);
  display: inline-flex;
  font-size: 1.18rem;
  justify-content: center;
  opacity: 0.5;
  position: relative;
  transition: opacity 0.18s ease, transform 0.18s ease;
  z-index: 1;
}

.porter-action:hover .porter-action__arrow {
  opacity: 1;
  transform: translateX(2px);
}

.porter-action--featured .porter-action__icon {
  background: rgba(255, 255, 255, 0.13);
  border: 1px solid rgba(255, 255, 255, 0.16);
  color: #fff;
  font-size: 1.45rem;
  height: 3.35rem;
  width: 3.35rem;
}

.porter-action--featured .porter-action__content strong {
  color: #fff;
  font-size: 1.02rem;
}

.porter-action--featured .porter-action__content small {
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.75rem;
}

.porter-action--featured .porter-action__arrow {
  align-items: center;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  color: #fff;
  height: 2rem;
  opacity: 0.8;
  width: 2rem;
}

.porter-action--amber {
  --action-accent: #b67b21;
  --action-tint: rgba(222, 159, 61, 0.13);
}

.porter-action--violet {
  --action-accent: #7656b6;
  --action-tint: rgba(118, 86, 182, 0.11);
}

.porter-action--emerald {
  --action-accent: #239c71;
  --action-tint: rgba(35, 156, 113, 0.11);
}

.porter-action--sky {
  --action-accent: #2c88bb;
  --action-tint: rgba(44, 136, 187, 0.11);
}

.porter-action--rose {
  --action-accent: #bb6075;
  --action-tint: rgba(187, 96, 117, 0.11);
}

.porter-action--slate {
  --action-accent: #61718a;
  --action-tint: rgba(97, 113, 138, 0.11);
}

:global([data-bs-theme="dark"]) .porter-action-deck,
:global(body[data-layout-mode="dark"]) .porter-action-deck {
  background:
    radial-gradient(circle at 100% 0, rgba(74, 114, 184, 0.16), transparent 28%),
    linear-gradient(145deg, rgba(28, 40, 61, 0.98), rgba(23, 34, 53, 0.95));
  border-color: rgba(153, 174, 207, 0.18);
}

:global([data-bs-theme="dark"]) .porter-action,
:global(body[data-layout-mode="dark"]) .porter-action {
  background: rgba(31, 44, 66, 0.92);
  border-color: rgba(153, 174, 207, 0.17);
}

:global([data-bs-theme="dark"]) .porter-action--featured,
:global(body[data-layout-mode="dark"]) .porter-action--featured {
  background:
    radial-gradient(circle at 91% 3%, rgba(255, 255, 255, 0.14), transparent 28%),
    linear-gradient(125deg, #172f56 0%, #2b5792 100%);
}

:global([data-bs-theme="dark"]) .porter-action--featured-secondary,
:global(body[data-layout-mode="dark"]) .porter-action--featured-secondary {
  background:
    radial-gradient(circle at 91% 3%, rgba(255, 255, 255, 0.14), transparent 28%),
    linear-gradient(125deg, #252f5d 0%, #4654a5 100%);
}

@media (max-width: 1199.98px) {
  .porter-action-deck__grid,
  .porter-action-deck--compact .porter-action-deck__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .porter-action--featured,
  .porter-action-deck--compact .porter-action--featured {
    grid-column: span 1;
  }
}

@media (max-width: 575.98px) {
  .porter-action-deck {
    padding: 0.9rem;
  }

  .porter-action-deck__header {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.7rem;
  }

  .porter-action-deck__grid,
  .porter-action-deck--compact .porter-action-deck__grid {
    grid-template-columns: 1fr;
  }

  .porter-action--featured,
  .porter-action-deck--compact .porter-action--featured {
    grid-column: auto;
  }
}
</style>
