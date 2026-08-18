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
        })[mode.value] || ["Psicología Escolar", "Gestión psicoeducativa."],
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
onMounted(api.loadCatalogs);
</script>
<template>
    <Layout>
        <div class="psi-page container-fluid py-3">
            <header class="psi-header mb-3">
                <div>
                    <p class="psi-kicker mb-1">Gestión psicoeducativa</p>
                    <h1>{{ page[0] }}</h1>
                    <p class="text-muted mb-0">{{ page[1] }}</p>
                </div>
                <div class="psi-lock">
                    <span class="psi-lock-icon">
                        <i class="bx bx-lock-alt"></i>
                    </span>
                    <span>
                        <strong>Acceso confidencial</strong>
                        <small>La actividad queda auditada</small>
                    </span>
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
                />
            </main>
        </div>
    </Layout>
</template>
<style scoped>
.psi-page {
    --psi-primary: var(--bs-primary, #556ee6);
    max-width: 1680px;
    color: #343a40;
}
.psi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.45rem 1.6rem;
    background: radial-gradient(circle at 8% 10%, #e8faff 0, transparent 35%),
        linear-gradient(135deg, #fff 15%, #f4f1ff 100%);
    border: 1px solid #dfe7f0;
    border-radius: 20px;
    box-shadow: 0 18px 52px rgba(45, 52, 85, 0.08);
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
    color: #24324a;
    font-size: 1.7rem;
    letter-spacing: -0.02em;
}
.psi-lock {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 230px;
    padding: 0.7rem 0.9rem;
    background: #fff;
    border: 1px solid #e9ebf0;
    border-radius: 13px;
    box-shadow: 0 6px 18px rgba(55, 43, 82, 0.08);
}
.psi-lock-icon {
    display: grid;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    color: var(--psi-primary);
    background: rgba(85, 110, 230, 0.1);
    border-radius: 50%;
}
.psi-lock-icon i {
    font-size: 1.4rem;
}
.psi-lock > span:last-child {
    display: flex;
    flex-direction: column;
    font-size: 0.8rem;
}
.psi-lock small {
    color: #74788d;
}
.psi-navigation {
    display: flex;
    gap: 0.3rem;
    overflow: auto;
    padding: 0.5rem;
    background: rgba(248, 250, 252, 0.96);
    border: 1px solid #e1e7ef;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(37, 48, 74, 0.05);
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
    background: #fff;
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
    }
    .psi-lock {
        width: 100%;
    }
}
</style>
