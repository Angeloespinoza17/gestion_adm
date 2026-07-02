<script>
import MetisMenu from "metismenujs";
const axios = window.axios;

import { menuItems } from "./menu";
import { useAuthStore } from "@/state/pinia";
import LoadingState from "@/components/ui/loading-state.vue";
import logoMark from "@/assets/images/logo.svg";
import logoWordmark from "@/assets/images/logo-dark.png";
import avatar1 from "@/assets/images/users/avatar-1.jpg";

/**
 * Side-nav component
 */
export default {
  components: { LoadingState },
  emits: ["toggle-menu"],
  data() {
    return {
      menuItems: [],
      isLoadingMenu: true,
      logoMark,
      logoWordmark,
      avatar1,
      auth: useAuthStore(),
    };
  },
  watch: {
    $route() {
      this.$nextTick(() => this.activateMenu());
    },
  },
  async mounted() {
    await this.loadMenu();
    await this.$nextTick();
    this.initMenu();
    this.activateMenu();
  },
  methods: {
    studentsFallbackSection() {
      return {
        id: "fallback-students",
        label: "Estudiantes",
        icon: "bx-user",
        subItems: [
          {
            id: "fallback-students-list",
            label: "Listado de estudiantes",
            link: "/students",
            parentId: "fallback-students",
          },
          {
            id: "fallback-students-levels",
            label: "Niveles",
            link: "/students/levels",
            parentId: "fallback-students",
          },
          {
            id: "fallback-students-academic-years",
            label: "Años académicos",
            link: "/students/academic-years",
            parentId: "fallback-students",
          },
          {
            id: "fallback-students-courses",
            label: "Cursos por año",
            link: "/students/courses",
            parentId: "fallback-students",
          },
          {
            id: "fallback-students-promotions",
            label: "Promoción anual",
            link: "/students/promotions",
            parentId: "fallback-students",
          },
          {
            id: "fallback-students-movements",
            label: "Cambios y retiros",
            link: "/students/movements",
            parentId: "fallback-students",
          },
        ],
      };
    },
    spacesFallbackSection() {
      return {
        id: "fallback-spaces",
        label: "Espacios",
        icon: "bx-calendar-event",
        subItems: [
          {
            id: "fallback-spaces-dependencies",
            label: "Dependencias",
            link: "/spaces/dependencies",
            parentId: "fallback-spaces",
          },
          {
            id: "fallback-spaces-types",
            label: "Tipos de dependencia",
            link: "/spaces/dependency-types",
            parentId: "fallback-spaces",
          },
          {
            id: "fallback-spaces-approvers",
            label: "Gestores",
            link: "/spaces/approvers",
            parentId: "fallback-spaces",
          },
          {
            id: "fallback-spaces-reservations",
            label: "Reservas",
            link: "/spaces/reservations",
            parentId: "fallback-spaces",
          },
          {
            id: "fallback-spaces-calendar",
            label: "Calendario",
            link: "/spaces/calendar",
            parentId: "fallback-spaces",
          },
          {
            id: "fallback-spaces-statistics",
            label: "Estadísticas",
            link: "/spaces/statistics",
            parentId: "fallback-spaces",
          },
        ],
      };
    },
    porterFallbackSection() {
      return {
        id: "fallback-porter",
        label: "Portería",
        icon: "bx-door-open",
        subItems: [
          {
            id: "fallback-porter-dashboard",
            label: "Panel de portería",
            link: "/porter/dashboard",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-students",
            label: "Buscar estudiante",
            link: "/porter/students",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-withdrawals",
            label: "Retiros",
            link: "/porter/withdrawals",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-items",
            label: "Recepción de objetos",
            link: "/porter/received-items",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-goods",
            label: "Mercadería",
            link: "/porter/goods",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-visits",
            label: "Control de visitas",
            link: "/porter/visits",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-providers",
            label: "Control de proveedores",
            link: "/porter/providers",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-daily-log",
            label: "Bitácora diaria",
            link: "/porter/daily-log",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-keys",
            label: "Control de llaves",
            link: "/porter/keys",
            parentId: "fallback-porter",
          },
          {
            id: "fallback-porter-reports",
            label: "Reportes de portería",
            link: "/porter/reports",
            parentId: "fallback-porter",
          },
        ],
      };
    },
    securityFallbackSection() {
      return {
        id: "fallback-security",
        label: "Control de Nochero",
        icon: "bx-shield-quarter",
        subItems: [
          {
            id: "fallback-security-dashboard",
            label: "Panel de rondas",
            link: "/security/dashboard",
            parentId: "fallback-security",
          },
          {
            id: "fallback-security-shifts",
            label: "Turnos y rondas",
            link: "/security/shifts",
            parentId: "fallback-security",
          },
          {
            id: "fallback-security-incidents",
            label: "Novedades pendientes",
            link: "/security/incidents",
            parentId: "fallback-security",
          },
        ],
      };
    },
    relevantCalendarFallbackSection() {
      return {
        id: "fallback-relevant-calendar",
        label: "Calendario y Fechas Relevantes",
        icon: "bx-calendar-event",
        subItems: [
          {
            id: "fallback-relevant-calendar-main",
            label: "Calendario",
            link: "/relevant-calendar",
            parentId: "fallback-relevant-calendar",
          },
          {
            id: "fallback-relevant-calendar-process-types",
            label: "Tipos de procesos",
            link: "/relevant-calendar/process-types",
            parentId: "fallback-relevant-calendar",
          },
          {
            id: "fallback-relevant-calendar-institutions",
            label: "Instituciones",
            link: "/relevant-calendar/institutions",
            parentId: "fallback-relevant-calendar",
          },
        ],
      };
    },
    riskPreventionFallbackSection() {
      return {
        id: "fallback-risk-prevention",
        label: "Prevención de Riesgos",
        icon: "bx-shield-alt-2",
        subItems: [
          {
            id: "fallback-risk-prevention-dashboard",
            label: "Dashboard",
            link: "/risk-prevention",
            parentId: "fallback-risk-prevention",
          },
          {
            id: "fallback-risk-prevention-extinguishers",
            label: "Extintores",
            link: "/risk-prevention/extinguishers",
            parentId: "fallback-risk-prevention",
          },
          {
            id: "fallback-risk-prevention-accidents",
            label: "Accidentes",
            link: "/risk-prevention/accidents",
            parentId: "fallback-risk-prevention",
          },
          {
            id: "fallback-risk-prevention-emergencies",
            label: "Emergencias y planes",
            link: "/risk-prevention/emergencies",
            parentId: "fallback-risk-prevention",
          },
          {
            id: "fallback-risk-prevention-epp",
            label: "EPP y seguridad",
            link: "/risk-prevention/epp",
            parentId: "fallback-risk-prevention",
          },
          {
            id: "fallback-risk-prevention-trainings",
            label: "Capacitaciones",
            link: "/risk-prevention/trainings",
            parentId: "fallback-risk-prevention",
          },
          {
            id: "fallback-risk-prevention-documents",
            label: "Centro de documentos",
            link: "/risk-prevention/documents",
            parentId: "fallback-risk-prevention",
          },
          {
            id: "fallback-risk-prevention-reports",
            label: "Reportes",
            link: "/risk-prevention/reports",
            parentId: "fallback-risk-prevention",
          },
        ],
      };
    },
    async loadMenu() {
      this.isLoadingMenu = true;
      const token = localStorage.getItem("token");
      if (!token) {
        this.menuItems = this.normalizeMenuLinks(menuItems);
        this.isLoadingMenu = false;
        return;
      }

      try {
        const auth = `Bearer ${token}`;
        const response = await axios.get("/api/me/modules", {
          headers: {
            Authorization: auth,
            "X-Authorization": auth,
            "X-Api-Token": token,
          },
        });
        const dynamicMenu = this.buildMenuFromModules(response.data.data || []);
        this.menuItems = this.normalizeMenuLinks(this.mergeFallbackSections(dynamicMenu));
      } catch (error) {
        this.menuItems = this.normalizeMenuLinks(menuItems);
      } finally {
        this.isLoadingMenu = false;
      }
    },
    normalizeMenuLinks(items = []) {
      return items.map((item) => {
        const normalized = { ...item };

        if (Array.isArray(item.subItems) && item.subItems.length > 0) {
          normalized.subItems = this.normalizeMenuLinks(item.subItems);
        }

        if (normalized.link) {
          normalized.link = this.resolveMenuLink(normalized.link);
        }

        return normalized;
      });
    },
    resolveMenuLink(link) {
      if (!link) {
        return "/pages-500";
      }

      if (/^(https?:)?\/\//.test(link)) {
        return link;
      }

      const resolved = this.$router.resolve(link);
      return resolved?.matched?.length ? link : "/pages-500";
    },
    buildMenuFromModules(modules) {
      const byParent = new Map();
      modules.forEach((mod) => {
        const parentId = mod.parent_id ?? null;
        if (!byParent.has(parentId)) byParent.set(parentId, []);
        byParent.get(parentId).push(mod);
      });

      const sortMods = (arr) =>
        [...arr].sort((a, b) => {
          const ao = a.sort_order ?? 0;
          const bo = b.sort_order ?? 0;
          if (ao !== bo) return ao - bo;
          return String(a.name).localeCompare(String(b.name));
        });

      const buildItems = (parentId) => {
        const children = sortMods(byParent.get(parentId) || []);
        return children.map((mod) => {
          const subItems = buildItems(mod.id);
          const item = {
            id: mod.id,
            label: mod.name,
            icon: mod.icon || undefined,
          };

          if (subItems.length > 0) {
            item.subItems = subItems;
          } else {
            item.link = mod.frontend_route || "/";
          }

          return item;
        });
      };

      return buildItems(null);
    },
    normalizeStudentsSection(items) {
      const fallbackStudents = this.studentsFallbackSection();
      let foundStudents = false;

      const normalized = items.map((item) => {
        const childLinks = (item.subItems || []).map((subitem) => subitem.link);
        const isStudentsSection =
          item.label === "Estudiantes" ||
          item.link === "/students" ||
          childLinks.some((link) =>
            ["/students", "/students/levels", "/students/academic-years", "/students/courses", "/students/promotions", "/students/movements"].includes(link)
          );

        if (!isStudentsSection) {
          return item;
        }

        foundStudents = true;

        return {
          ...fallbackStudents,
          id: item.id || fallbackStudents.id,
          subItems: fallbackStudents.subItems.map((subitem, index) => ({
            ...subitem,
            id: item.subItems?.[index]?.id || subitem.id,
            parentId: item.id || fallbackStudents.id,
          })),
        };
      });

      if (foundStudents) {
        return normalized;
      }

      return [...normalized, fallbackStudents];
    },
    normalizeSpacesSection(items) {
      const fallbackSpaces = this.spacesFallbackSection();
      let foundSpaces = false;

      const normalized = items.map((item) => {
        const childLinks = (item.subItems || []).map((subitem) => subitem.link);
        const isSpacesSection =
          item.label === "Dependencias y Reservas" ||
          item.label === "Espacios" ||
          childLinks.some((link) =>
            ["/spaces/dependencies", "/spaces/dependency-types", "/spaces/approvers", "/spaces/reservations", "/spaces/calendar", "/spaces/statistics"].includes(link)
          );

        if (!isSpacesSection) {
          return item;
        }

        foundSpaces = true;

        return {
          ...fallbackSpaces,
          id: item.id || fallbackSpaces.id,
          subItems: fallbackSpaces.subItems.map((subitem, index) => ({
            ...subitem,
            id: item.subItems?.[index]?.id || subitem.id,
            parentId: item.id || fallbackSpaces.id,
          })),
        };
      });

      if (foundSpaces) {
        return normalized;
      }

      return [...normalized, fallbackSpaces];
    },
    normalizeRiskPreventionSection(items) {
      const fallbackRiskPrevention = this.riskPreventionFallbackSection();
      let foundRiskPrevention = false;

      const normalized = items.map((item) => {
        const childLinks = (item.subItems || []).map((subitem) => subitem.link);
        const isRiskPreventionSection =
          item.label === "Prevención de Riesgos" ||
          item.link === "/risk-prevention" ||
          childLinks.some((link) =>
            [
              "/risk-prevention",
              "/risk-prevention/extinguishers",
              "/risk-prevention/accidents",
              "/risk-prevention/emergencies",
              "/risk-prevention/epp",
              "/risk-prevention/trainings",
              "/risk-prevention/documents",
              "/risk-prevention/reports",
            ].includes(link)
          );

        if (!isRiskPreventionSection) {
          return item;
        }

        foundRiskPrevention = true;

        return {
          ...fallbackRiskPrevention,
          id: item.id || fallbackRiskPrevention.id,
          subItems: fallbackRiskPrevention.subItems.map((subitem, index) => ({
            ...subitem,
            id: item.subItems?.[index]?.id || subitem.id,
            parentId: item.id || fallbackRiskPrevention.id,
          })),
        };
      });

      if (foundRiskPrevention) {
        return normalized;
      }

      return normalized;
    },
    mergeFallbackSections(items) {
      const hasStudentsSection = items.some((item) => {
        if (item.link === "/students") return true;
        return (item.subItems || []).some((subitem) =>
          ["/students", "/students/levels", "/students/academic-years", "/students/courses", "/students/promotions", "/students/movements"].includes(subitem.link)
        );
      });
      const hasStaffSection = items.some((item) => {
        if (item.link === "/staff") return true;
        return (item.subItems || []).some((subitem) => subitem.link === "/staff");
      });
      const hasContractsSection = items.some((item) => {
        if (item.link === "/contracts") return true;
        return (item.subItems || []).some((subitem) => subitem.link === "/contracts");
      });
      const hasPermissionsSection = items.some((item) => {
        if (item.link === "/staff/permissions") return true;
        return (item.subItems || []).some((subitem) =>
          [
            "/staff/permissions/dashboard",
            "/staff/permissions",
            "/staff/permissions/review",
            "/staff/permissions/reports",
            "/staff/permissions/types",
            "/staff/permissions/watchers",
            "/staff/permissions/watchers-summary",
          ].includes(subitem.link)
        );
      });
      const hasSpacesSection = items.some((item) => {
        if (item.link === "/spaces/dependencies") return true;
        return (item.subItems || []).some((subitem) =>
          ["/spaces/dependencies", "/spaces/dependency-types", "/spaces/approvers", "/spaces/reservations", "/spaces/calendar", "/spaces/statistics"].includes(subitem.link)
        );
      });
      const hasSecuritySection = items.some((item) => {
        if (item.link === "/security/dashboard") return true;
        return (item.subItems || []).some((subitem) =>
          ["/security/dashboard", "/security/shifts", "/security/incidents"].includes(subitem.link)
        );
      });
      const hasRelevantCalendarSection = items.some((item) => {
        if (item.link === "/relevant-calendar") return true;
        return (item.subItems || []).some((subitem) =>
          ["/relevant-calendar", "/relevant-calendar/process-types", "/relevant-calendar/institutions"].includes(subitem.link)
        );
      });
      const hasRiskPreventionSection = items.some((item) => {
        if (item.link === "/risk-prevention") return true;
        return (item.subItems || []).some((subitem) =>
          [
            "/risk-prevention",
            "/risk-prevention/extinguishers",
            "/risk-prevention/accidents",
            "/risk-prevention/emergencies",
            "/risk-prevention/epp",
            "/risk-prevention/trainings",
            "/risk-prevention/documents",
            "/risk-prevention/reports",
          ].includes(subitem.link)
        );
      });
      const hasPorterSection = items.some((item) => {
        if (item.link === "/porter/dashboard") return true;
        return (item.subItems || []).some((subitem) =>
          ["/porter/dashboard", "/porter/students", "/porter/withdrawals", "/porter/received-items", "/porter/goods", "/porter/visits", "/porter/providers", "/porter/daily-log", "/porter/keys", "/porter/reports"].includes(subitem.link)
        );
      });

      const fallbacks = [];

      if (!hasStudentsSection) {
        fallbacks.push(this.studentsFallbackSection());
      }

      if (!hasStaffSection) {
        fallbacks.push({
          id: "fallback-staff",
          label: "Funcionarios",
          icon: "bx-id-card",
          subItems: [
            {
              id: "fallback-staff-list",
              label: "Listado de funcionarios",
              link: "/staff",
              parentId: "fallback-staff",
            },
            {
              id: "fallback-staff-departments",
              label: "Departamentos",
              link: "/staff/departments",
              parentId: "fallback-staff",
            },
          ],
        });
      }

      if (!hasContractsSection) {
        fallbacks.push({
          id: "fallback-contracts",
          label: "Contratos",
          icon: "bx-file",
          subItems: [
            {
              id: "fallback-contracts-list",
              label: "Listado de contratos",
              link: "/contracts",
              parentId: "fallback-contracts",
            },
            {
              id: "fallback-contracts-templates",
              label: "Plantillas",
              link: "/contracts/templates",
              parentId: "fallback-contracts",
            },
            {
              id: "fallback-contracts-clauses",
              label: "Cláusulas",
              link: "/contracts/clauses",
              parentId: "fallback-contracts",
            },
            {
              id: "fallback-contracts-signatures",
              label: "Firmas",
              link: "/contracts/signatures",
              parentId: "fallback-contracts",
            },
          ],
        });
      }

      if (!hasPermissionsSection) {
        fallbacks.push({
          id: "fallback-staff-permissions",
          label: "Permisos",
          icon: "bx-calendar-minus",
          subItems: [
            {
              id: "fallback-staff-permissions-dashboard",
              label: "Dashboard permisos",
              link: "/staff/permissions/dashboard",
              parentId: "fallback-staff-permissions",
            },
            {
              id: "fallback-staff-permissions-requests",
              label: "Mis permisos",
              link: "/staff/permissions",
              parentId: "fallback-staff-permissions",
            },
            {
              id: "fallback-staff-permissions-review",
              label: "Bandeja de permisos",
              link: "/staff/permissions/review",
              parentId: "fallback-staff-permissions",
            },
            {
              id: "fallback-staff-permissions-reports",
              label: "Reportes de permisos",
              link: "/staff/permissions/reports",
              parentId: "fallback-staff-permissions",
            },
            {
              id: "fallback-staff-permissions-types",
              label: "Tipos de permiso",
              link: "/staff/permissions/types",
              parentId: "fallback-staff-permissions",
            },
            {
              id: "fallback-staff-permissions-watchers",
              label: "Quién debe enterarse",
              link: "/staff/permissions/watchers",
              parentId: "fallback-staff-permissions",
            },
            {
              id: "fallback-staff-permissions-watchers-summary",
              label: "Destinatarios por funcionario",
              link: "/staff/permissions/watchers-summary",
              parentId: "fallback-staff-permissions",
            },
          ],
        });
      }

      if (!hasSpacesSection) {
        fallbacks.push(this.spacesFallbackSection());
      }

      if (!hasSecuritySection) {
        fallbacks.push(this.securityFallbackSection());
      }

      if (!hasRelevantCalendarSection) {
        fallbacks.push(this.relevantCalendarFallbackSection());
      }

      if (!hasRiskPreventionSection) {
        fallbacks.push(this.riskPreventionFallbackSection());
      }

      if (!hasPorterSection) {
        fallbacks.push(this.porterFallbackSection());
      }

      const normalizedItems = this.normalizeRiskPreventionSection(
        this.normalizeSpacesSection(this.normalizeStudentsSection(items))
      ).map((item) => {
        if (item.label !== "Mantención" || !Array.isArray(item.subItems)) {
          return item;
        }

        return item;
      });

      if (fallbacks.length === 0) {
        return normalizedItems;
      }

      const settingsIndex = normalizedItems.findIndex((item) => item.label === "Configuración");
      if (settingsIndex >= 0) {
        const next = [...normalizedItems];
        next.splice(settingsIndex, 0, ...fallbacks);
        return next;
      }

      return [...normalizedItems, ...fallbacks];
    },
    initMenu() {
      if (document.getElementById("side-menu")) new MetisMenu("#side-menu");
    },
    activateMenu() {
      var links = document.getElementsByClassName("side-nav-link-ref");
      var matchingMenuItem = null;
      const paths = [];

      for (var i = 0; i < links.length; i++) {
        paths.push(links[i]["pathname"]);
      }
      var itemIndex = paths.indexOf(window.location.pathname);
      if (itemIndex === -1) {
        const strIndex = window.location.pathname.lastIndexOf("/");
        const item = window.location.pathname.substr(0, strIndex).toString();
        matchingMenuItem = links[paths.indexOf(item)];
      } else {
        matchingMenuItem = links[itemIndex];
      }

      if (matchingMenuItem) {
        matchingMenuItem.classList.add("active");
        var parent = matchingMenuItem.parentElement;

        if (parent) {
          parent.classList.add("mm-active");
          const parent2 = parent.parentElement.closest("ul");
          if (parent2 && parent2.id !== "side-menu") {
            parent2.classList.add("mm-show");

            const parent3 = parent2.parentElement;
            if (parent3) {
              parent3.classList.add("mm-active");

              var badgeChildAnchor = parent3.querySelector(".badge");

              if (!badgeChildAnchor) {
                var childAnchor = parent3.querySelector(".has-arrow");
              }

              var childDropdown = parent3.querySelector(".has-dropdown");
              if (childAnchor) childAnchor.classList.add("mm-active");
              if (childDropdown) childDropdown.classList.add("mm-active");

              const parent4 = parent3.parentElement;
              if (parent4 && parent4.id !== "side-menu") {
                parent4.classList.add("mm-show");
                const parent5 = parent4.parentElement;
                if (parent5 && parent5.id !== "side-menu") {
                  parent5.classList.add("mm-active");
                  const childanchor = parent5.querySelector(".is-parent");
                  if (childanchor && parent5.id !== "side-menu") {
                    childanchor.classList.add("mm-active");
                  }
                }
              }
            }
          }
        }
      }
    },
    /**
     * Returns true or false if given menu item has child or not
     * @param item menuItem
     */
    hasItems(item) {
      return item.subItems !== undefined ? item.subItems.length > 0 : false;
    },
    onLeafNavigation() {
      if (window.innerWidth < 992) {
        this.$emit("toggle-menu");
      }
    },
    logoutUser() {
      axios
        .post("/api/logout")
        .catch(() => null)
        .finally(() => {
          localStorage.removeItem("user");
          localStorage.removeItem("token");
          localStorage.removeItem("permissions");
          document.cookie = "cnsc_token=; Max-Age=0; path=/; samesite=lax";
          delete axios.defaults.headers.common["Authorization"];
          this.$router.push("/login");
        });
    },
  },
  computed: {
    sidebarUser() {
      const storedUser = this.auth.currentUser;
      if (storedUser?.name || storedUser?.email) {
        return {
          name: storedUser.name || "Usuario",
          email: storedUser.email || "",
        };
      }

      try {
        const user = JSON.parse(localStorage.getItem("user") || "{}");
        return {
          name: user.name || "Usuario",
          email: user.email || "",
        };
      } catch (error) {
        return {
          name: "Usuario",
          email: "",
        };
      }
    },
    userInitial() {
      return (this.sidebarUser.name || "U").trim().charAt(0).toUpperCase();
    },
  },
};
</script>

<template>
  <!-- ========== Left Sidebar Start ========== -->

  <!--- Sidemenu -->
  <div class="sidebar-shell d-flex flex-column h-100">
    <div class="sidebar-shell__header">
      <router-link to="/inicio" class="sidebar-brand">
        <span class="sidebar-brand__mark">
          <img :src="logoMark" alt="Skote" height="22" />
        </span>
        <span class="sidebar-brand__wordmark">
          <img :src="logoWordmark" alt="Skote" height="18" />
        </span>
      </router-link>

      <button type="button" class="sidebar-shell__toggle" aria-label="Alternar menú" @click="$emit('toggle-menu')">
        <i class="fa fa-fw fa-bars d-none d-lg-inline-block"></i>
        <i class="fa fa-fw fa-times d-inline-block d-lg-none"></i>
      </button>
    </div>

    <div id="sidebar-menu" class="sidebar-shell__menu flex-grow-1">
      <div v-if="isLoadingMenu" class="sidebar-menu-loading px-3 py-2">
        <LoadingState message="Cargando menú..." compact />
      </div>
      <!-- Left Menu Start -->
      <ul v-else id="side-menu" class="metismenu list-unstyled">
        <template v-for="item in menuItems">
          <li class="menu-title" v-if="item.isTitle" :key="item.id">
            {{ $t(item.label) }}
          </li>
          <li v-if="!item.isTitle && !item.isLayout" :key="item.id">
            <BLink v-if="hasItems(item)" href="javascript:void(0);" class="is-parent"
              :class="{ 'has-arrow': !item.badge, 'has-dropdown': item.badge }">
              <i :class="`bx ${item.icon}`" v-if="item.icon"></i>
              <span>{{ $t(item.label) }}</span>
              <span :class="`badge rounded-pill bg-${item.badge.variant} float-end `" v-if="item.badge">{{
                $t(item.badge.text) }}</span>
            </BLink>

            <router-link :to="item.link" v-if="!hasItems(item)" class="side-nav-link-ref" @click="onLeafNavigation">
              <i :class="`bx ${item.icon}`" v-if="item.icon"></i>
              <span>{{ $t(item.label) }}</span>
              <span :class="`badge rounded-pill bg-${item.badge.variant} float-end`" v-if="item.badge">{{
                $t(item.badge.text) }}</span>
            </router-link>

            <ul v-if="hasItems(item)" class="sub-menu" aria-expanded="false" :id="item.id">
              <li v-for="(subitem, index) of item.subItems" :key="index">
                <router-link :to="subitem.link" v-if="!hasItems(subitem)" class="side-nav-link-ref" @click="onLeafNavigation">
                  <span>{{ $t(subitem.label) }}</span>
                  <span :class="`badge rounded-pill bg-${subitem.badge.variant} float-end`" v-if="subitem.badge">{{
                    $t(subitem.badge.text) }}</span>
                </router-link>
                <BLink v-if="hasItems(subitem)" class="side-nav-link-a-ref has-arrow" href="javascript:void(0);">{{
                  $t(subitem.label) }}</BLink>
                <ul v-if="hasItems(subitem)" class="sub-menu mm-collapse" aria-expanded="false">
                  <li v-for="(subSubitem, index) of subitem.subItems" :key="index">
                    <router-link :to="subSubitem.link" class="side-nav-link-ref" @click="onLeafNavigation">{{ $t(subSubitem.label) }}</router-link>
                  </li>
                </ul>
              </li>
            </ul>
          </li>
        </template>
      </ul>
    </div>

    <div class="sidebar-shell__footer">
      <div class="sidebar-account">
        <div class="sidebar-account__user">
          <div class="sidebar-account__avatar">
            <img :src="avatar1" alt="" />
            <span>{{ userInitial }}</span>
          </div>
          <div class="sidebar-account__meta">
            <span class="sidebar-account__label">Cuenta</span>
            <strong class="sidebar-account__name">{{ sidebarUser.name }}</strong>
            <span class="sidebar-account__email">{{ sidebarUser.email }}</span>
          </div>
        </div>

        <div class="sidebar-account__actions">
          <button type="button" class="sidebar-account__action sidebar-account__action--logout" @click="logoutUser">
            <i class="bx bx-log-out"></i>
            <span>Cerrar sesión</span>
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- Sidebar -->
</template>
