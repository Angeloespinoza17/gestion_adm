<script setup>
import { computed, nextTick, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "axios";
import Layout from "../../layouts/main.vue";
import AttendanceManagementDashboard from "../../components/attendance-management/AttendanceManagementDashboard.vue";
import AttendanceManagementStudents from "../../components/attendance-management/AttendanceManagementStudents.vue";
import AttendanceManagementCases from "../../components/attendance-management/AttendanceManagementCases.vue";
import AttendanceManagementReports from "../../components/attendance-management/AttendanceManagementReports.vue";
import AttendanceManagementConfiguration from "../../components/attendance-management/AttendanceManagementConfiguration.vue";
import { useAttendanceManagement } from "../../composables/useAttendanceManagement";

const route = useRoute(); const router = useRouter();
const { filters, params, dashboard, loading, refreshing, error, loadDashboard } = useAttendanceManagement();
const activeTab = ref(typeof route.query.section === "string" ? route.query.section : "dashboard");
const studentsRef = ref(null); const casesRef = ref(null); const configuration = ref(null); const configLoading = ref(false); const initialPattern = ref(""); const pendingStatus = ref("");
const capabilities = computed(() => dashboard.value?.meta?.capabilities || {});
const tabs = computed(() => [
  { key: "dashboard", label: "Dashboard", icon: "bx-grid-alt", visible: true },
  { key: "students", label: "Estudiantes", icon: "bx-group", visible: true },
  { key: "cases", label: "Expedientes", icon: "bx-folder-open", visible: true },
  { key: "pending", label: "Gestión pendiente", icon: "bx-time-five", visible: true, count: dashboard.value?.pending_management?.unreviewed_cases || 0 },
  { key: "reports", label: "Reportes", icon: "bx-file", visible: capabilities.value.can_export },
  { key: "configuration", label: "Configuración", icon: "bx-cog", visible: capabilities.value.can_configure },
].filter((item) => item.visible));
const academicLabel = computed(() => dashboard.value?.meta?.academic_year?.name || "Año académico");
const analyzedLabel = computed(() => dashboard.value?.meta?.snapshot_date ? `Análisis al ${new Date(`${dashboard.value.meta.snapshot_date}T12:00:00`).toLocaleDateString("es-CL")}` : "Análisis preventivo pendiente");
const loadConfiguration = async () => { if (!filters.academic_year_id) return; configLoading.value = true; try { const { data } = await axios.get("/api/attendance-management/configuration", { params: { academic_year_id: filters.academic_year_id } }); configuration.value = data; } finally { configLoading.value = false; } };
const refresh = async () => { await loadDashboard(); await loadConfiguration(); studentsRef.value?.reload?.(); casesRef.value?.reload?.(); };
const setTab = async (key) => { activeTab.value = key; if (key === "pending") pendingStatus.value = "detected"; await router.replace({ query: { ...route.query, section: key } }); };
const openStudent = async (id) => { await setTab("students"); await nextTick(); studentsRef.value?.openStudent?.(id); };
const openCases = async (status = "") => { pendingStatus.value = status; await setTab(status ? "pending" : "cases"); await nextTick(); casesRef.value?.setStatus?.(status); };
const filterPattern = async (pattern) => { initialPattern.value = pattern; await setTab("students"); };
const changeGlobalFilter = async () => { await refresh(); };
onMounted(async () => { await loadDashboard(); await loadConfiguration(); if (!tabs.value.some((tab) => tab.key === activeTab.value)) activeTab.value = "dashboard"; if (route.query.case) { await setTab("cases"); await nextTick(); casesRef.value?.openCase?.(route.query.case); } });
</script>

<template>
  <Layout>
    <main class="page-content absence-page">
      <div class="container-fluid absence-container">
        <header class="hero">
          <div class="hero-copy"><span class="eyebrow">GESTIÓN PREVENTIVA Y ACOMPAÑAMIENTO</span><h1>Gestión de ausencia y asistencia escolar</h1><p>Detecta señales tempranas, comprende causas y coordina apoyos con trazabilidad, sin duplicar la asistencia oficial.</p><div class="hero-meta"><span><i class="bx bx-calendar"></i>{{ academicLabel }}</span><span><i class="bx bx-radar"></i>{{ analyzedLabel }}</span><span><i class="bx bx-data"></i>Fuente oficial consolidada</span></div></div>
          <div class="hero-actions"><label><span>Año académico</span><select v-model="filters.academic_year_id" @change="filters.course_section_id=null;changeGlobalFilter()"><option v-for="year in dashboard?.catalogs?.academic_years || []" :key="year.id" :value="year.id">{{ year.name }}</option></select></label><label><span>Curso</span><select v-model="filters.course_section_id" @change="changeGlobalFilter"><option :value="null">Todos los autorizados</option><option v-for="course in dashboard?.catalogs?.courses || []" :key="course.id" :value="course.id">{{ course.display_name }}</option></select></label><button type="button" class="refresh" :disabled="refreshing" @click="refresh"><span v-if="refreshing" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-refresh"></i><span>Actualizar</span></button></div>
        </header>

        <div v-if="loading && !dashboard" class="initial-state"><span class="spinner-border text-primary"></span><strong>Preparando gestión preventiva</strong><p>Validando jornadas lectivas, matrículas y permisos…</p></div>
        <div v-else-if="error && !dashboard" class="initial-state error"><i class="bx bx-error-circle"></i><strong>{{ error }}</strong><button type="button" class="btn btn-outline-danger" @click="refresh">Reintentar</button></div>
        <template v-else-if="dashboard">
          <div v-if="error" class="inline-error"><i class="bx bx-error-circle"></i>{{ error }}<button type="button" @click="refresh">Reintentar</button></div>
          <nav class="workspace-tabs" aria-label="Secciones de gestión de asistencia"><button v-for="tab in tabs" :key="tab.key" type="button" :class="{ active: activeTab===tab.key }" @click="setTab(tab.key)"><i class="bx" :class="tab.icon"></i><span>{{ tab.label }}</span><em v-if="tab.count">{{ tab.count }}</em></button></nav>
          <section class="workspace" :class="{ refreshing }">
            <AttendanceManagementDashboard v-if="activeTab==='dashboard'" :data="dashboard" @open-student="openStudent" @open-cases="openCases" @filter-risk="filterPattern" />
            <AttendanceManagementStudents v-if="activeTab==='students'" ref="studentsRef" :filters="params" :catalogs="dashboard.catalogs" :capabilities="capabilities" :configuration="configuration || {}" :initial-pattern="initialPattern" />
            <AttendanceManagementCases v-if="activeTab==='cases' || activeTab==='pending'" ref="casesRef" :filters="params" :catalogs="dashboard.catalogs" :capabilities="capabilities" :configuration="configuration || {}" :initial-status="activeTab==='pending' ? (pendingStatus || 'detected') : ''" :initial-case-id="route.query.case || null" />
            <AttendanceManagementReports v-if="activeTab==='reports'" :filters="params" :capabilities="capabilities" />
            <div v-if="activeTab==='configuration' && configLoading" class="initial-state compact"><span class="spinner-border spinner-border-sm"></span>Cargando configuración…</div>
            <AttendanceManagementConfiguration v-else-if="activeTab==='configuration' && configuration" :data="configuration" :academic-year-id="filters.academic_year_id" @updated="loadConfiguration" />
          </section>
        </template>
      </div>
    </main>
  </Layout>
</template>

<style scoped>
.absence-page{min-height:100vh;background:#f3f6f9}.absence-container{display:grid;gap:.85rem;padding-bottom:2rem}.hero{position:relative;display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;overflow:hidden;border-radius:0 0 15px 15px;background:linear-gradient(118deg,#24334f 0%,#405189 56%,#5570a5 100%);padding:1.15rem 1.3rem;color:#fff;box-shadow:0 9px 24px rgba(31,45,72,.15)}.hero:after{position:absolute;right:30%;bottom:-85px;width:220px;height:220px;border:38px solid rgba(255,255,255,.035);border-radius:50%;content:""}.hero-copy{position:relative;z-index:1;max-width:680px}.eyebrow{color:#c7d4f0;font-size:.58rem;font-weight:850;letter-spacing:.13em}.hero h1{margin:.18rem 0;color:#fff;font-size:1.45rem}.hero-copy>p{margin:0;color:#dbe2f1;font-size:.7rem}.hero-meta{display:flex;flex-wrap:wrap;gap:.8rem;margin-top:.55rem;color:#d6deed;font-size:.59rem}.hero-meta span{display:flex;align-items:center;gap:.25rem}.hero-actions{position:relative;z-index:1;display:flex;align-items:flex-end;gap:.45rem}.hero-actions label span{display:block;margin-bottom:.24rem;color:#d5deef;font-size:.56rem;font-weight:700}.hero-actions select{min-width:145px;height:36px;border:1px solid rgba(255,255,255,.23);border-radius:7px;background:rgba(255,255,255,.1);padding:0 .55rem;color:#fff;font-size:.65rem;backdrop-filter:blur(4px)}.hero-actions select option{color:#283448}.refresh{display:flex;align-items:center;gap:.3rem;height:36px;border:1px solid rgba(255,255,255,.26);border-radius:7px;background:#fff;padding:0 .7rem;color:#34476c;font-size:.62rem;font-weight:750}.workspace-tabs{display:flex;gap:.15rem;overflow-x:auto;border:1px solid #e0e5eb;border-radius:10px;background:#fff;padding:.25rem;box-shadow:0 4px 13px rgba(32,44,64,.035)}.workspace-tabs button{display:flex;align-items:center;gap:.32rem;min-width:max-content;height:38px;border:0;border-radius:7px;background:transparent;padding:0 .7rem;color:#6d798b;font-size:.66rem;font-weight:700}.workspace-tabs button.active{background:#405189;color:#fff;box-shadow:0 4px 10px rgba(64,81,137,.22)}.workspace-tabs em{display:grid;place-items:center;min-width:18px;height:18px;border-radius:999px;background:#cf4b59;color:#fff;font-size:.52rem;font-style:normal}.workspace-tabs button.active em{background:#fff;color:#b52d3a}.workspace{min-height:420px;transition:opacity .15s}.workspace.refreshing{opacity:.76}.initial-state{display:grid;place-items:center;align-content:center;gap:.5rem;min-height:430px;border:1px solid #e0e5eb;border-radius:12px;background:#fff;color:#718095;font-size:.7rem}.initial-state p{margin:0;font-size:.61rem}.initial-state.error i{color:#c13c4a;font-size:2rem}.initial-state.compact{min-height:220px}.inline-error{display:flex;align-items:center;gap:.4rem;border:1px solid #efc5c9;border-left:4px solid #c13c4a;border-radius:8px;background:#fff1f2;padding:.6rem .75rem;color:#a9323e;font-size:.66rem}.inline-error button{margin-left:auto;border:0;background:transparent;color:inherit;font-weight:750}@media(max-width:1000px){.hero{align-items:flex-start;flex-direction:column}.hero-actions{width:100%}.hero-actions label{flex:1}.hero-actions select{width:100%}}@media(max-width:650px){.absence-container{padding-inline:.6rem}.hero{margin-inline:-.6rem;padding:1rem}.hero h1{font-size:1.2rem}.hero-actions{display:grid;grid-template-columns:1fr 1fr}.refresh{grid-column:1/-1;justify-content:center}.workspace-tabs{margin-inline:-.2rem}}
</style>
