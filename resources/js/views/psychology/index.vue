<script setup>
import { computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import Layout from "../../layouts/main.vue";
import PsychologyDashboard from "../../components/psychology/PsychologyDashboard.vue";
import PsychologyReferrals from "../../components/psychology/PsychologyReferrals.vue";
import PsychologyCases from "../../components/psychology/PsychologyCases.vue";
import PsychologyOperations from "../../components/psychology/PsychologyOperations.vue";
import { usePsychology } from "../../composables/usePsychology";
const route = useRoute();
const router = useRouter();
const api = usePsychology();
const mode = computed(() => route.meta.psychologyView || "dashboard");
const page = computed(
    () =>
        ({
            dashboard: [
                "Psicología Escolar",
                "Indicadores, derivaciones e intervenciones del equipo profesional.",
            ],
            referrals: [
                "Derivaciones",
                "Recepción, priorización y asignación de solicitudes de atención.",
            ],
            cases: [
                "Casos y atenciones",
                "Seguimiento confidencial, entrevistas, acuerdos y planes de intervención.",
            ],
            calendar: [
                "Agenda profesional",
                "Sesiones, entrevistas y próximos seguimientos programados.",
            ],
            tasks: [
                "Tareas del equipo",
                "Compromisos, responsables y vencimientos asociados a los casos.",
            ],
            alerts: [
                "Alertas de seguimiento",
                "Riesgos, inactividad y acciones que requieren atención prioritaria.",
            ],
            reports: [
                "Reportes de Psicología",
                "Indicadores de gestión con protección de datos sensibles.",
            ],
            config: [
                "Configuración",
                "Catálogos y parámetros operativos del módulo.",
            ],
            audit: [
                "Auditoría",
                "Trazabilidad de accesos y cambios sobre información confidencial.",
            ],
        }[mode.value] || ["Psicología Escolar", "Gestión psicoeducativa."])
);
const items = computed(() => [
    ["dashboard", "Resumen", "bx-grid-alt", "/psychology"],
    ...(api.catalogs.capabilities.view_referrals
        ? [
              [
                  "referrals",
                  "Derivaciones",
                  "bx-transfer",
                  "/psychology/referrals",
              ],
          ]
        : []),
    ...(api.catalogs.capabilities.view_cases
        ? [
              ["cases", "Casos", "bx-folder-open", "/psychology/cases"],
              ["calendar", "Agenda", "bx-calendar", "/psychology/calendar"],
              ["tasks", "Tareas", "bx-task", "/psychology/tasks"],
              ["alerts", "Alertas", "bx-error-circle", "/psychology/alerts"],
          ]
        : []),
    ...(api.catalogs.capabilities.reports_aggregate
        ? [["reports", "Reportes", "bx-bar-chart-alt-2", "/psychology/reports"]]
        : []),
    ...(api.catalogs.capabilities.config
        ? [["config", "Configuración", "bx-cog", "/psychology/configuration"]]
        : []),
    ...(api.catalogs.capabilities.audit
        ? [["audit", "Auditoría", "bx-shield-quarter", "/psychology/audit"]]
        : []),
]);
const scope = computed(() => {
    if (api.catalogs.capabilities.full_domain) {
        return {
            icon: "bx-shield-quarter",
            eyebrow: "Cobertura institucional",
            title: "Vista global Super Admin",
            detail: "Todos los casos, atenciones y equipos, con trazabilidad activa.",
        };
    }
    if (api.catalogs.capabilities.personal_scope) {
        return {
            icon: "bx-user-check",
            eyebrow: "Espacio profesional privado",
            title: "Solo tu cartera y atenciones",
            detail: "Los casos y registros de otras psicólogas permanecen aislados.",
        };
    }
    return {
        icon: "bx-lock-alt",
        eyebrow: "Acceso protegido",
        title: "Información confidencial",
        detail: "La visibilidad depende de tu autorización y toda actividad queda auditada.",
    };
});
onMounted(api.loadCatalogs);
</script>
<template>
    <Layout>
        <div class="psi-page container-fluid py-3">
            <header class="psi-header mb-3">
                <div class="psi-heading">
                    <span class="psi-brand-mark" aria-hidden="true">
                        <i class="bx bx-bulb"></i>
                    </span>
                    <div>
                        <p class="psi-kicker mb-1">Gestión psicoeducativa</p>
                        <h1>{{ page[0] }}</h1>
                        <p class="psi-subtitle mb-0">{{ page[1] }}</p>
                    </div>
                </div>
                <div class="psi-lock">
                    <span class="psi-lock-icon">
                        <i :class="`bx ${scope.icon}`"></i>
                    </span>
                    <span class="psi-lock-copy">
                        <small>{{ scope.eyebrow }}</small>
                        <strong>{{ scope.title }}</strong>
                        <span>{{ scope.detail }}</span>
                    </span>
                    <span class="psi-live-dot" title="Trazabilidad activa"></span>
                </div>
            </header>
            <div v-if="api.error.value" class="alert alert-danger">
                {{ api.error.value }}
            </div>

            <nav class="psi-navigation mb-3" aria-label="Psicología">
                <button
                    v-for="item in items"
                    :key="item[0]"
                    type="button"
                    :class="{ active: mode === item[0] }"
                    :aria-current="mode === item[0] ? 'page' : undefined"
                    @click="router.push(item[3])"
                >
                    <i :class="`bx ${item[2]}`"></i>{{ item[1] }}
                </button>
            </nav>

            <main>
                <PsychologyDashboard
                    v-if="mode === 'dashboard'"
                    :catalogs="api.catalogs"
                /><PsychologyReferrals
                    v-else-if="mode === 'referrals'"
                    :catalogs="api.catalogs"
                    @changed="api.loadCatalogs"
                /><PsychologyCases
                    v-else-if="mode === 'cases'"
                    :catalogs="api.catalogs"
                /><PsychologyOperations
                    v-else
                    :mode="mode"
                    :catalogs="api.catalogs"
                    @changed="api.loadCatalogs"
                />
            </main>
        </div>
    </Layout>
</template>
<style scoped>
.psi-page {
    --psi-primary: var(--bs-primary, #556ee6);
    --psi-ink: #21324a;
    --psi-muted: #718096;
    max-width: 1680px;
    color: #343a40;
}
.psi-header {
    position: relative;
    isolation: isolate;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    min-height: 156px;
    overflow: hidden;
    padding: 1.7rem 1.8rem;
    background:
        radial-gradient(circle at 8% 15%, rgba(73, 196, 211, 0.2), transparent 30%),
        radial-gradient(circle at 82% 0, rgba(135, 105, 194, 0.17), transparent 34%),
        linear-gradient(135deg, #ffffff 10%, #f7fbff 52%, #f7f3ff 100%);
    border: 1px solid rgba(202, 214, 228, 0.82);
    border-radius: 24px;
    box-shadow: 0 22px 60px rgba(35, 50, 74, 0.09);
}
.psi-header::after {
    position: absolute;
    z-index: -1;
    top: -72px;
    right: 28%;
    width: 170px;
    height: 170px;
    border: 1px solid rgba(111, 85, 153, 0.1);
    border-radius: 50%;
    box-shadow: 0 0 0 28px rgba(111, 85, 153, 0.025),
        0 0 0 56px rgba(111, 85, 153, 0.018);
    content: "";
}
.psi-heading {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}
.psi-brand-mark {
    display: grid;
    flex: 0 0 auto;
    width: 3.6rem;
    height: 3.6rem;
    place-items: center;
    color: #fff;
    background: linear-gradient(145deg, #675584, #4e7899);
    border: 5px solid rgba(255, 255, 255, 0.75);
    border-radius: 18px;
    box-shadow: 0 12px 28px rgba(70, 61, 100, 0.22);
}
.psi-brand-mark i {
    font-size: 1.7rem;
}
.psi-kicker {
    color: #725a99;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}
.psi-header h1 {
    margin: 0;
    color: var(--psi-ink);
    font-size: clamp(1.55rem, 2vw, 2rem);
    letter-spacing: -0.035em;
}
.psi-subtitle {
    max-width: 680px;
    margin-top: 0.35rem;
    color: var(--psi-muted);
    font-size: 0.88rem;
}
.psi-lock {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    width: min(390px, 38%);
    min-width: 300px;
    padding: 0.9rem 2rem 0.9rem 1rem;
    background: rgba(255, 255, 255, 0.87);
    border: 1px solid rgba(215, 222, 232, 0.88);
    border-radius: 17px;
    box-shadow: 0 10px 28px rgba(55, 43, 82, 0.09);
    backdrop-filter: blur(14px);
}
.psi-lock-icon {
    display: grid;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    color: #61507c;
    background: #f1edf8;
    border-radius: 11px;
}
.psi-lock-icon i {
    font-size: 1.4rem;
}
.psi-lock-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    line-height: 1.35;
}
.psi-lock-copy small {
    color: #7d6a9b;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
.psi-lock-copy strong {
    color: #2c3b50;
    font-size: 0.84rem;
}
.psi-lock-copy > span {
    margin-top: 0.15rem;
    color: #738095;
    font-size: 0.7rem;
}
.psi-live-dot {
    position: absolute;
    top: 0.85rem;
    right: 0.85rem;
    width: 0.48rem;
    height: 0.48rem;
    background: #34c38f;
    border: 2px solid #fff;
    border-radius: 50%;
    box-shadow: 0 0 0 3px rgba(52, 195, 143, 0.13);
}
.psi-navigation {
    display: flex;
    gap: 0.3rem;
    overflow: auto;
    padding: 0.45rem;
    background: rgba(247, 249, 252, 0.94);
    border: 1px solid #e1e7ef;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(37, 48, 74, 0.05);
    scrollbar-width: none;
}
.psi-navigation::-webkit-scrollbar {
    display: none;
}
.psi-navigation button {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 40px;
    padding: 0.65rem 0.8rem;
    white-space: nowrap;
    background: transparent;
    border: 0;
    border-radius: 11px;
    color: #58677b;
    transition: background-color 0.15s ease, color 0.15s ease;
}
.psi-navigation button:hover {
    color: #473862;
    background: #eef2f7;
}
.psi-navigation button.active {
    color: #574277;
    background: linear-gradient(145deg, #fff, #f8f5fc);
    box-shadow: 0 6px 18px rgba(55, 43, 82, 0.1);
    font-weight: 700;
}
.psi-navigation button i {
    font-size: 1.05rem;
}

@media (max-width: 575.98px) {
    .psi-header {
        align-items: flex-start;
        flex-direction: column;
        padding: 1.2rem;
    }
    .psi-heading {
        align-items: flex-start;
    }
    .psi-brand-mark {
        width: 3rem;
        height: 3rem;
        border-radius: 15px;
    }
    .psi-lock {
        width: 100%;
        min-width: 0;
    }
}

@media (min-width: 576px) and (max-width: 991.98px) {
    .psi-header {
        align-items: flex-start;
        flex-direction: column;
    }
    .psi-lock {
        width: 100%;
    }
}
</style>
