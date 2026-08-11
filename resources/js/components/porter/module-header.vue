<script>
export default {
  name: "PorterModuleHeader",
  props: {
    title: {
      type: String,
      required: true,
    },
    subtitle: {
      type: String,
      default: "",
    },
    eyebrow: {
      type: String,
      default: "Operación y trazabilidad",
    },
    icon: {
      type: String,
      default: "bx bx-shield-quarter",
    },
    showNavigation: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      navigationItems: [
        { label: "Panel", shortLabel: "Panel", to: "/porter/dashboard", icon: "bx bx-grid-alt" },
        { label: "Estudiantes", shortLabel: "Estudiantes", to: "/porter/students", icon: "bx bx-search-alt" },
        { label: "Retiros", shortLabel: "Retiros", to: "/porter/withdrawals", icon: "bx bx-log-out-circle" },
        { label: "Recepción", shortLabel: "Recepción", to: "/porter/received-items", icon: "bx bx-package" },
        { label: "Mercadería", shortLabel: "Mercadería", to: "/porter/goods", icon: "bx bx-cube" },
        { label: "Visitas", shortLabel: "Visitas", to: "/porter/visits", icon: "bx bx-id-card" },
        { label: "Proveedores", shortLabel: "Proveedores", to: "/porter/providers", icon: "bx bx-briefcase-alt-2" },
        { label: "Bitácora", shortLabel: "Bitácora", to: "/porter/daily-log", icon: "bx bx-notepad" },
        { label: "Llaves", shortLabel: "Llaves", to: "/porter/keys", icon: "bx bx-key" },
        { label: "Reportes", shortLabel: "Reportes", to: "/porter/reports", icon: "bx bx-bar-chart-alt-2" },
      ],
    };
  },
};
</script>

<template>
  <header class="porter-module-header">
    <div class="porter-module-header__main">
      <div class="porter-module-header__identity">
        <span class="porter-module-header__icon" aria-hidden="true">
          <i :class="icon"></i>
        </span>
        <div class="porter-module-header__copy">
          <div class="porter-module-header__eyebrow">
            <span class="porter-module-header__pulse"></span>
            {{ eyebrow }}
          </div>
          <h1>{{ title }}</h1>
          <p v-if="subtitle">{{ subtitle }}</p>
          <div v-if="$slots.meta" class="porter-module-header__meta">
            <slot name="meta" />
          </div>
        </div>
      </div>

      <div v-if="$slots.actions" class="porter-module-header__actions">
        <slot name="actions" />
      </div>
    </div>

    <nav v-if="showNavigation" class="porter-module-nav" aria-label="Secciones de Portería">
      <router-link
        v-for="item in navigationItems"
        :key="item.to"
        :to="item.to"
        class="porter-module-nav__item"
        active-class="porter-module-nav__item--active"
      >
        <i :class="item.icon" aria-hidden="true"></i>
        <span>{{ item.shortLabel }}</span>
      </router-link>
    </nav>
  </header>
</template>

<style src="./porter-ui.css"></style>

<style scoped>
.porter-module-header {
  background:
    radial-gradient(circle at 88% -10%, rgba(255, 255, 255, 0.2), transparent 32%),
    linear-gradient(128deg, #16294f 0%, #274a7e 52%, #3868a1 100%);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  box-shadow: 0 1rem 2.5rem rgba(29, 53, 93, 0.16);
  color: #fff;
  margin-bottom: 1.25rem;
  overflow: hidden;
  position: relative;
}

.porter-module-header::after {
  background-image: radial-gradient(rgba(255, 255, 255, 0.13) 0.75px, transparent 0.75px);
  background-size: 14px 14px;
  content: "";
  inset: 0 0 auto 55%;
  height: 100%;
  opacity: 0.45;
  pointer-events: none;
  position: absolute;
}

.porter-module-header__main {
  align-items: center;
  display: flex;
  gap: 1.5rem;
  justify-content: space-between;
  min-height: 9.25rem;
  padding: 1.45rem 1.6rem 1.25rem;
  position: relative;
  z-index: 1;
}

.porter-module-header__identity {
  align-items: center;
  display: flex;
  gap: 1rem;
  min-width: 0;
}

.porter-module-header__icon {
  align-items: center;
  background: rgba(255, 255, 255, 0.14);
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: 0.9rem;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 1.85rem;
  height: 4rem;
  justify-content: center;
  width: 4rem;
}

.porter-module-header__copy {
  min-width: 0;
}

.porter-module-header__eyebrow {
  align-items: center;
  color: rgba(255, 255, 255, 0.76);
  display: flex;
  font-size: 0.72rem;
  font-weight: 700;
  gap: 0.45rem;
  letter-spacing: 0.1em;
  margin-bottom: 0.42rem;
  text-transform: uppercase;
}

.porter-module-header__pulse {
  background: #62e5ad;
  border-radius: 50%;
  box-shadow: 0 0 0 0 rgba(98, 229, 173, 0.55);
  height: 0.45rem;
  width: 0.45rem;
}

.porter-module-header h1 {
  color: #fff;
  font-size: clamp(1.55rem, 2.5vw, 2.1rem);
  font-weight: 700;
  letter-spacing: -0.025em;
  line-height: 1.15;
  margin: 0;
}

.porter-module-header p {
  color: rgba(255, 255, 255, 0.75);
  font-size: 0.92rem;
  margin: 0.45rem 0 0;
  max-width: 47rem;
}

.porter-module-header__meta {
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.76rem;
  margin-top: 0.5rem;
}

.porter-module-header__actions {
  align-items: center;
  display: flex;
  flex: 0 0 auto;
  flex-wrap: wrap;
  gap: 0.55rem;
  justify-content: flex-end;
  position: relative;
  z-index: 1;
}

.porter-module-header__actions :deep(.btn) {
  backdrop-filter: blur(6px);
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
  min-height: 2.6rem;
}

.porter-module-header__actions :deep(.btn:hover),
.porter-module-header__actions :deep(.btn:focus) {
  background: #fff;
  border-color: #fff;
  color: #234777;
}

.porter-module-header__actions :deep(.btn-primary) {
  background: #fff;
  border-color: #fff;
  color: #234777;
}

.porter-module-nav {
  align-items: center;
  backdrop-filter: blur(10px);
  background: rgba(7, 21, 47, 0.28);
  border-top: 1px solid rgba(255, 255, 255, 0.12);
  display: flex;
  gap: 0.25rem;
  overflow-x: auto;
  padding: 0.55rem 0.7rem;
  position: relative;
  scrollbar-width: thin;
  z-index: 2;
}

.porter-module-nav__item {
  align-items: center;
  border-radius: 0.55rem;
  color: rgba(255, 255, 255, 0.72);
  display: inline-flex;
  flex: 0 0 auto;
  font-size: 0.78rem;
  font-weight: 600;
  gap: 0.38rem;
  min-height: 2.35rem;
  padding: 0.48rem 0.68rem;
  text-decoration: none;
  transition: background-color 0.16s ease, color 0.16s ease, transform 0.16s ease;
}

.porter-module-nav__item i {
  font-size: 1rem;
}

.porter-module-nav__item:hover,
.porter-module-nav__item:focus,
.porter-module-nav__item--active {
  background: rgba(255, 255, 255, 0.14);
  color: #fff;
  outline: none;
}

.porter-module-nav__item--active {
  box-shadow: inset 0 -2px 0 #75e6b4;
}

@media (max-width: 991.98px) {
  .porter-module-header__main {
    align-items: flex-start;
    flex-direction: column;
    min-height: auto;
  }

  .porter-module-header__actions {
    justify-content: flex-start;
    width: 100%;
  }
}

@media (max-width: 575.98px) {
  .porter-module-header {
    border-radius: 0.8rem;
  }

  .porter-module-header__main {
    gap: 1rem;
    padding: 1.1rem;
  }

  .porter-module-header__identity {
    align-items: flex-start;
  }

  .porter-module-header__icon {
    border-radius: 0.7rem;
    font-size: 1.35rem;
    height: 3rem;
    width: 3rem;
  }

  .porter-module-header__actions :deep(.btn) {
    flex: 1 1 auto;
  }

  .porter-module-nav {
    padding-inline: 0.55rem;
  }
}
</style>
