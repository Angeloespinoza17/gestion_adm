<script>
const SECTIONS = [
  { to: "/admin/metricas-web", label: "Métricas", icon: "bx-line-chart", permission: "ver_metricas_sitio", group: "Analítica" },
  { to: "/admin/noticias", label: "Noticias", icon: "bx-news", permission: "ver_noticias", group: "Contenido" },
  { to: "/admin/eventos", label: "Eventos", icon: "bx-calendar-event", permission: "ver_eventos", group: "Contenido" },
  { to: "/admin/testimonios", label: "Testimonios", icon: "bx-message-rounded-dots", permission: "ver_testimonios", group: "Contenido" },
  { to: "/admin/vida-estudiantil", label: "Vida estudiantil", icon: "bx-images", permission: "ver_vida_estudiantil", group: "Contenido" },
  { to: "/admin/instalaciones", label: "Instalaciones", icon: "bx-buildings", permission: "ver_instalaciones_sitio", group: "Contenido" },
  { to: "/admin/cgpa", label: "CGPA", icon: "bx-group", permission: "ver_cgpa_sitio", group: "Comunidad" },
  { to: "/admin/cde", label: "CDE", icon: "bx-user-voice", permission: "ver_cde_sitio", group: "Comunidad" },
  { to: "/admin/comite-paritario", label: "Comité Paritario", icon: "bx-shield-quarter", permission: "ver_comite_paritario_sitio", group: "Comunidad" },
  { to: "/admin/contactos", label: "Contactos", icon: "bx-envelope", permission: "ver_contactos_sitio", group: "Bandeja" },
];

export default {
  computed: {
    currentPath() {
      return this.$route?.path || "";
    },
    permissions() {
      try {
        const stored = JSON.parse(localStorage.getItem("permissions") || "[]");
        return Array.isArray(stored) ? stored : [];
      } catch (error) {
        return [];
      }
    },
    visibleSections() {
      const isSuperAdmin = this.permissions.includes("__superadmin__");
      return SECTIONS.filter((section) => (
        isSuperAdmin
        || this.permissions.includes(section.permission)
        || this.currentPath === section.to
      ));
    },
    groupedSections() {
      const currentGroup = SECTIONS.find((section) => section.to === this.currentPath)?.group;
      const groupOrder = [currentGroup, "Analítica", "Contenido", "Comunidad", "Bandeja"].filter(
        (label, index, labels) => label && labels.indexOf(label) === index
      );

      return groupOrder
        .map((label) => ({
          label,
          sections: this.visibleSections.filter((section) => section.group === label),
        }))
        .filter((group) => group.sections.length);
    },
  },
};
</script>

<template>
  <section class="site-admin-navigation" aria-label="Navegación de gestión del sitio web">
    <div class="site-admin-navigation__brand">
      <span><i class="bx bx-globe"></i></span>
      <div>
        <small>Portal CNSC</small>
        <strong>Gestión del sitio web</strong>
      </div>
    </div>

    <div class="site-admin-navigation__scroll">
      <div v-for="group in groupedSections" :key="group.label" class="site-admin-navigation__group">
        <span class="site-admin-navigation__group-label">{{ group.label }}</span>
        <nav :aria-label="group.label">
          <router-link
            v-for="section in group.sections"
            :key="section.to"
            :to="section.to"
            :class="{ active: currentPath === section.to }"
            :aria-current="currentPath === section.to ? 'page' : undefined"
          >
            <i class="bx" :class="section.icon"></i>
            <span>{{ section.label }}</span>
          </router-link>
        </nav>
      </div>
    </div>
  </section>
</template>

<style scoped>
.site-admin-navigation {
  display: flex;
  align-items: stretch;
  gap: 1rem;
  margin-bottom: 0.9rem;
  overflow: hidden;
  border: 1px solid rgba(18, 77, 94, 0.11);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 12px 32px rgba(14, 54, 70, 0.07);
}

.site-admin-navigation__brand {
  display: flex;
  align-items: center;
  flex: 0 0 auto;
  gap: 0.65rem;
  min-width: 205px;
  padding: 0.8rem 1rem;
  background: linear-gradient(120deg, #07394f, #0b6576);
  color: #fff;
}

.site-admin-navigation__brand > span {
  display: grid;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.1);
  color: #efc77f;
  font-size: 1.1rem;
}

.site-admin-navigation__brand div {
  display: grid;
}

.site-admin-navigation__brand small {
  color: rgba(255, 255, 255, 0.62);
  font-size: 0.56rem;
  font-weight: 850;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.site-admin-navigation__brand strong {
  color: #fff;
  font-size: 0.72rem;
  line-height: 1.25;
}

.site-admin-navigation__scroll {
  display: flex;
  align-items: center;
  flex: 1;
  gap: 0.8rem;
  min-width: 0;
  overflow-x: auto;
  padding: 0.55rem 0.8rem 0.55rem 0;
  scrollbar-width: none;
}

.site-admin-navigation__scroll::-webkit-scrollbar {
  display: none;
}

.site-admin-navigation__group {
  display: grid;
  flex: 0 0 auto;
  gap: 0.3rem;
}

.site-admin-navigation__group + .site-admin-navigation__group {
  border-left: 1px solid #e5edef;
  padding-left: 0.8rem;
}

.site-admin-navigation__group-label {
  color: #83949d;
  font-size: 0.51rem;
  font-weight: 850;
  letter-spacing: 0.09em;
  text-transform: uppercase;
}

.site-admin-navigation nav {
  display: flex;
  gap: 0.28rem;
}

.site-admin-navigation a {
  display: inline-flex;
  align-items: center;
  gap: 0.32rem;
  min-height: 34px;
  border: 1px solid transparent;
  border-radius: 10px;
  padding: 0.42rem 0.58rem;
  color: #5d7480;
  font-size: 0.59rem;
  font-weight: 800;
  white-space: nowrap;
  transition: 0.18s ease;
}

.site-admin-navigation a i {
  color: #75909a;
  font-size: 0.86rem;
}

.site-admin-navigation a:hover {
  border-color: #d6e5e8;
  background: #f3f8f8;
  color: #0a6577;
}

.site-admin-navigation a.active {
  border-color: #b9d8dc;
  background: linear-gradient(145deg, #e7f3f3, #f2f7f2);
  color: #075d70;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.75);
}

.site-admin-navigation a.active i {
  color: #0b7080;
}

@media (max-width: 991.98px) {
  .site-admin-navigation {
    display: block;
  }

  .site-admin-navigation__brand {
    min-width: 0;
    padding: 0.65rem 0.8rem;
  }

  .site-admin-navigation__brand > span {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
  }

  .site-admin-navigation__scroll {
    padding: 0.55rem 0.7rem 0.65rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .site-admin-navigation a {
    transition: none;
  }
}
</style>
