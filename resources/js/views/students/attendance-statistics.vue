<script setup>
import { computed, defineAsyncComponent, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import AttendanceFilterPanel from "../../components/attendance-statistics/attendance-filter-panel.vue";
import AttendanceDashboard from "../../components/attendance-statistics/attendance-dashboard.vue";
import { useAttendanceStatistics } from "../../composables/useAttendanceStatistics";
import { waitForAndDownloadAttendanceExport } from "../../utils/attendance-export";

const AttendanceStudentExplorer = defineAsyncComponent(() => import("../../components/attendance-statistics/attendance-student-explorer.vue"));
const AttendanceCourseMatrix = defineAsyncComponent(() => import("../../components/attendance-statistics/attendance-course-matrix.vue"));
const AttendanceOperations = defineAsyncComponent(() => import("../../components/attendance-statistics/attendance-operations.vue"));
const AttendanceGoalsProjections = defineAsyncComponent(() => import("../../components/attendance-statistics/attendance-goals-projections.vue"));
const AttendanceQualityExport = defineAsyncComponent(() => import("../../components/attendance-statistics/attendance-quality-export.vue"));
const AttendanceConfiguration = defineAsyncComponent(() => import("../../components/attendance-statistics/attendance-configuration.vue"));

const route = useRoute(); const router = useRouter();
const { filters, dashboard, loading, refreshing, error, activeFilters, activeFilterCount, fetchDashboard, resetFilters, drillToCourse } = useAttendanceStatistics();
const activeTab = ref(typeof route.query.section === "string" ? route.query.section : "dashboard");
const mountedTabs = reactive({ dashboard: true });
const exporting = ref(false);
const tabs = computed(() => {
  const capabilities = dashboard.value?.meta?.capabilities || {};
  return [
    { key: "dashboard", label: "Resumen", icon: "bx-grid-alt", visible: true },
    { key: "students", label: "Estudiantes", icon: "bx-group", visible: capabilities.can_view_student },
    { key: "courses", label: "Cursos", icon: "bx-grid-horizontal", visible: capabilities.can_view_course },
    { key: "operations", label: "Gestión", icon: "bx-bell", visible: capabilities.can_manage_alerts || capabilities.can_manage_interventions || true },
    { key: "goals", label: "Metas", icon: "bx-target-lock", visible: true },
    { key: "quality", label: "Calidad y archivos", icon: "bx-check-shield", visible: capabilities.can_configure || capabilities.can_export || capabilities.can_view_audit },
    { key: "configuration", label: "Configuración", icon: "bx-cog", visible: capabilities.can_configure },
  ].filter((tab) => tab.visible);
});
const capabilities = computed(() => dashboard.value?.meta?.capabilities || {});
const periodLabel = computed(() => {
  const range = dashboard.value?.meta?.date_range;
  if (!range?.from || !range?.to) return "Sin periodo";
  return `${new Date(`${range.from}T12:00:00`).toLocaleDateString("es-CL")} al ${new Date(`${range.to}T12:00:00`).toLocaleDateString("es-CL")}`;
});
const sourceLabel = computed(() => dashboard.value?.meta?.source || "Registros de asistencia");
const setTab = async (key) => { mountedTabs[key] = true; activeTab.value = key; await router.replace({ query: { ...route.query, ...activeFilters.value, section: key } }); };
const handleCourseDrill = async (courseId) => { drillToCourse(courseId); if (capabilities.value.can_view_course) await setTab("courses"); };
const quickExport = async (format) => {
  exporting.value = true;
  let progressOpen = false;
  try {
    const response = await axios.post("/api/attendance-statistics/exports", { academic_year_id: Number(filters.academic_year_id), report_type: "executive", format, filters: { ...activeFilters.value } });
    Swal.fire({ title: "Preparando archivo", text: "La descarga comenzará automáticamente.", allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
    progressOpen = true;
    await waitForAndDownloadAttendanceExport(response.data);
    Swal.close(); progressOpen = false;
    await Swal.fire({ icon: "success", title: "Descarga iniciada", text: "El archivo también quedó guardado en Calidad y archivos.", timer: 1800, showConfirmButton: false });
  } catch (requestError) {
    if (progressOpen) Swal.close();
    const timedOut = requestError.code === "ATTENDANCE_EXPORT_TIMEOUT";
    await Swal.fire({ icon: timedOut ? "info" : "error", title: timedOut ? "La exportación continúa" : "No se pudo exportar", text: timedOut ? "El archivo aparecerá en Calidad y archivos cuando termine." : requestError.response?.data?.message || requestError.message || "Intenta nuevamente." });
  } finally { exporting.value = false; }
};
onMounted(async () => { await fetchDashboard(); if (!tabs.value.some((tab) => tab.key === activeTab.value)) activeTab.value = "dashboard"; mountedTabs[activeTab.value] = true; });
</script>

<template>
  <Layout>
    <main class="page-content attendance-page">
      <div class="container-fluid attendance-container">
        <header class="page-heading">
          <div><span class="eyebrow">GESTIÓN ACADÉMICA</span><h1>Estadísticas avanzadas de asistencia</h1><div class="heading-meta"><span><i class="bx bx-calendar"></i>{{ periodLabel }}</span><span><i class="bx bx-data"></i>{{ sourceLabel }}</span><span v-if="dashboard?.meta?.generated_at"><i class="bx bx-time-five"></i>{{ new Date(dashboard.meta.generated_at).toLocaleString('es-CL') }}</span></div></div>
          <div v-if="capabilities.can_export" class="heading-actions"><button type="button" class="btn btn-outline-success" :disabled="exporting" @click="quickExport('xls')"><i class="bx bx-spreadsheet"></i>Excel</button><button type="button" class="btn btn-danger" :disabled="exporting" @click="quickExport('pdf')"><i class="bx bxs-file-pdf"></i>PDF</button></div>
        </header>

        <div v-if="loading && !dashboard" class="initial-state"><span class="spinner-border text-primary"></span><strong>Calculando estadísticas</strong></div>
        <div v-else-if="error && !dashboard" class="error-state"><i class="bx bx-error-circle"></i><strong>{{ error }}</strong><button type="button" class="btn btn-outline-danger" @click="fetchDashboard">Reintentar</button></div>
        <template v-else-if="dashboard">
          <AttendanceFilterPanel :model-value="filters" :catalogs="dashboard.catalogs" :active-count="activeFilterCount" :refreshing="refreshing" @apply="fetchDashboard" @reset="resetFilters" />
          <div v-if="error" class="inline-error"><i class="bx bx-error-circle"></i>{{ error }}<button type="button" @click="fetchDashboard">Reintentar</button></div>
          <nav class="report-tabs" aria-label="Secciones de estadísticas">
            <button v-for="tab in tabs" :key="tab.key" type="button" :class="{ active: activeTab === tab.key }" @click="setTab(tab.key)"><i class="bx" :class="tab.icon"></i><span>{{ tab.label }}</span></button>
            <span v-if="refreshing" class="refresh-state"><span class="spinner-border spinner-border-sm"></span>Actualizando</span>
          </nav>
          <section class="tab-content-shell" :class="{ refreshing }">
            <AttendanceDashboard v-if="mountedTabs.dashboard" v-show="activeTab === 'dashboard'" :data="dashboard" @drill-course="handleCourseDrill" />
            <AttendanceStudentExplorer v-if="mountedTabs.students" v-show="activeTab === 'students'" :filters="activeFilters" :capabilities="capabilities" />
            <AttendanceCourseMatrix v-if="mountedTabs.courses" v-show="activeTab === 'courses'" :filters="activeFilters" :catalogs="dashboard.catalogs" />
            <AttendanceOperations v-if="mountedTabs.operations" v-show="activeTab === 'operations'" :filters="activeFilters" :capabilities="capabilities" />
            <AttendanceGoalsProjections v-if="mountedTabs.goals" v-show="activeTab === 'goals'" :filters="activeFilters" :dashboard="dashboard" :capabilities="capabilities" />
            <AttendanceQualityExport v-if="mountedTabs.quality" v-show="activeTab === 'quality'" :filters="activeFilters" :capabilities="capabilities" />
            <AttendanceConfiguration v-if="mountedTabs.configuration" v-show="activeTab === 'configuration'" :filters="activeFilters" />
          </section>
        </template>
      </div>
    </main>
  </Layout>
</template>

<style scoped>
.attendance-page{min-height:100vh;background:#f4f7fa}.attendance-container{display:grid;gap:.8rem;padding-bottom:2rem}.page-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;padding:.35rem 0}.eyebrow{color:#405189;font-size:.63rem;font-weight:800}.page-heading h1{margin:.15rem 0 .25rem;color:#202b3c;font-size:1.45rem;letter-spacing:0}.heading-meta{display:flex;flex-wrap:wrap;gap:.8rem;color:#697689;font-size:.68rem}.heading-meta span{display:flex;align-items:center;gap:.25rem}.heading-actions{display:flex;gap:.45rem}.heading-actions .btn{display:inline-flex;align-items:center;gap:.35rem;min-height:36px;font-size:.72rem}.report-tabs{position:relative;display:flex;gap:.15rem;overflow-x:auto;border-bottom:1px solid #dfe5ec;background:#fff;padding:0 .25rem}.report-tabs button{display:flex;align-items:center;gap:.35rem;min-width:max-content;height:42px;padding:0 .7rem;border:0;border-bottom:2px solid transparent;background:transparent;color:#697689;font-size:.7rem;font-weight:650}.report-tabs button.active{border-bottom-color:#405189;color:#405189}.report-tabs button:focus-visible{outline:3px solid rgba(64,81,137,.2);outline-offset:-3px}.refresh-state{display:flex;align-items:center;gap:.3rem;margin-left:auto;padding:0 .65rem;color:#667286;font-size:.63rem}.tab-content-shell{position:relative;min-height:300px;transition:opacity .15s}.tab-content-shell.refreshing{opacity:.82}.initial-state,.error-state{display:grid;place-items:center;align-content:center;gap:.65rem;min-height:420px;border:1px solid #e0e5ec;border-radius:8px;background:#fff;color:#667286}.error-state i{color:#c13c4a;font-size:2rem}.inline-error{display:flex;align-items:center;gap:.45rem;padding:.55rem .7rem;border-left:3px solid #c13c4a;background:#fff0f1;color:#9d2e3a;font-size:.68rem}.inline-error button{margin-left:auto;border:0;background:transparent;color:inherit;font-weight:700}@media(max-width:767px){.page-heading{align-items:flex-start;flex-direction:column}.heading-actions{width:100%}.heading-actions .btn{flex:1;justify-content:center}.heading-meta{gap:.35rem .7rem}.report-tabs{margin-inline:-.75rem;padding-inline:.75rem}.refresh-state{display:none}.page-heading h1{font-size:1.22rem}}
</style>
