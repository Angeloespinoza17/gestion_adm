<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { errorMessage, pedagogicalManagementApi } from "../../services/pedagogical-management-api";

const loading = ref(true);
const saving = ref(false);
const catalogs = ref({ schools: [] });
const data = ref({ academic_years: [], coordinators: [], courses: [], education_levels: [], assignments: [] });
const selection = reactive({ school_id: "", academic_year_id: "", coordinator_user_id: "", course_ids: [], education_level_ids: [] });
const alert = reactive({ tone: "", text: "" });

const selectedCoordinator = computed(() => data.value.coordinators.find((item) => Number(item.id) === Number(selection.coordinator_user_id)));
const assignedCourseCount = computed(() => selection.course_ids.length);
const assignedLevelCount = computed(() => selection.education_level_ids.length);

function notify(tone, text) {
    Object.assign(alert, { tone, text });
    window.setTimeout(() => { if (alert.text === text) alert.text = ""; }, 6000);
}

async function initialize() {
    loading.value = true;
    try {
        catalogs.value = await pedagogicalManagementApi.catalogs();
        selection.school_id = String(catalogs.value.selected_school_id || catalogs.value.schools[0]?.id || "");
        if (selection.school_id) await loadAssignments();
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible cargar la configuración."));
    } finally {
        loading.value = false;
    }
}

async function loadAssignments({ preserveYear = false } = {}) {
    if (!selection.school_id) return;
    const response = await pedagogicalManagementApi.coordinatorAssignments({
        school_id: selection.school_id,
        academic_year_id: preserveYear ? selection.academic_year_id || undefined : undefined,
        coordinator_user_id: selection.coordinator_user_id || undefined,
    });
    data.value = response;
    selection.academic_year_id = String(response.selected_academic_year_id || "");
    hydrateScopes();
}

function hydrateScopes() {
    const coordinatorId = Number(selection.coordinator_user_id);
    const rows = (data.value.assignments || []).filter((item) => !coordinatorId || Number(item.coordinator_user_id) === coordinatorId);
    selection.course_ids = rows.filter((item) => item.target_type === "course").map((item) => Number(item.target_id));
    selection.education_level_ids = rows.filter((item) => item.target_type === "level").map((item) => Number(item.target_id));
}

async function changeYear() {
    selection.course_ids = [];
    selection.education_level_ids = [];
    try { await loadAssignments({ preserveYear: true }); } catch (error) { notify("danger", errorMessage(error)); }
}

async function changeCoordinator() {
    if (!selection.coordinator_user_id) {
        selection.course_ids = [];
        selection.education_level_ids = [];
        return;
    }
    try { await loadAssignments({ preserveYear: true }); } catch (error) { notify("danger", errorMessage(error)); }
}

function toggleAllCourses() {
    selection.course_ids = selection.course_ids.length === data.value.courses.length ? [] : data.value.courses.map((item) => Number(item.id));
}

function toggleAllLevels() {
    selection.education_level_ids = selection.education_level_ids.length === data.value.education_levels.length ? [] : data.value.education_levels.map((item) => Number(item.id));
}

async function save() {
    if (!selection.coordinator_user_id) return notify("danger", "Selecciona una coordinadora académica.");
    saving.value = true;
    try {
        const response = await pedagogicalManagementApi.saveCoordinatorAssignments({
            school_id: Number(selection.school_id),
            academic_year_id: Number(selection.academic_year_id),
            coordinator_user_id: Number(selection.coordinator_user_id),
            course_ids: selection.course_ids,
            education_level_ids: selection.education_level_ids,
        });
        notify("success", response.message);
        await loadAssignments({ preserveYear: true });
    } catch (error) {
        notify("danger", errorMessage(error, "No fue posible guardar la asignación."));
    } finally {
        saving.value = false;
    }
}

onMounted(initialize);
</script>

<template>
    <Layout>
        <div class="assignment-page container-fluid py-4">
            <header class="assignment-hero mb-4"><div><span>CONFIGURACIÓN PEDAGÓGICA</span><h1>Ámbitos de coordinación</h1><p>Define qué niveles o cursos revisará cada coordinadora académica en el año escolar seleccionado.</p></div><i class="bx bx-sitemap"></i></header>
            <div v-if="alert.text" class="alert" :class="`alert-${alert.tone}`">{{ alert.text }}</div>
            <LoadingState v-if="loading" message="Cargando coordinaciones..." />
            <template v-else>
                <section class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><div class="row g-3">
                    <div class="col-lg-5"><label class="form-label">Año académico</label><select v-model="selection.academic_year_id" class="form-select" @change="changeYear"><option v-for="year in data.academic_years" :key="year.id" :value="String(year.id)">{{ year.name || year.year }}</option></select></div>
                    <div class="col-lg-7"><label class="form-label">Coordinadora académica</label><select v-model="selection.coordinator_user_id" class="form-select" @change="changeCoordinator"><option value="">Selecciona una coordinadora</option><option v-for="person in data.coordinators" :key="person.id" :value="String(person.id)">{{ person.name }} · {{ person.role }}{{ person.has_school_access ? "" : " · acceso pendiente" }}</option></select><small v-if="selectedCoordinator && !selectedCoordinator.has_school_access" class="coordinator-access-note"><i class="bx bx-link-alt"></i> Al guardar se habilitará su acceso vigente a este establecimiento.</small></div>
                </div></div></section>

                <div v-if="!data.coordinators.length" class="alert alert-warning">No hay usuarios activos con rol de coordinación académica vinculados al establecimiento.</div>
                <div v-else-if="selection.coordinator_user_id" class="row g-4">
                    <div class="col-xl-8">
                        <section class="scope-card mb-4">
                            <div class="scope-heading"><div><span class="scope-icon level"><i class="bx bx-layer"></i></span><div><h2>Niveles completos</h2><p>La coordinadora verá todos los cursos actuales del nivel y los que se creen durante el año.</p></div></div><button class="btn btn-sm btn-light" type="button" @click="toggleAllLevels">{{ selection.education_level_ids.length === data.education_levels.length ? "Limpiar" : "Seleccionar todos" }}</button></div>
                            <div class="scope-grid"><label v-for="level in data.education_levels" :key="level.id" class="scope-option" :class="{ selected: selection.education_level_ids.includes(Number(level.id)) }"><input v-model="selection.education_level_ids" type="checkbox" :value="Number(level.id)" /><span><strong>{{ level.name }}</strong><small>{{ level.type || "Nivel educativo" }}</small></span><i class="bx bx-check"></i></label></div>
                        </section>
                        <section class="scope-card">
                            <div class="scope-heading"><div><span class="scope-icon course"><i class="bx bx-chalkboard"></i></span><div><h2>Cursos específicos</h2><p>Úsalo para excepciones o repartos particulares dentro de un mismo nivel.</p></div></div><button class="btn btn-sm btn-light" type="button" @click="toggleAllCourses">{{ selection.course_ids.length === data.courses.length ? "Limpiar" : "Seleccionar todos" }}</button></div>
                            <div class="scope-grid courses"><label v-for="course in data.courses" :key="course.id" class="scope-option" :class="{ selected: selection.course_ids.includes(Number(course.id)) }"><input v-model="selection.course_ids" type="checkbox" :value="Number(course.id)" /><span><strong>{{ course.name }}</strong><small>Curso activo</small></span><i class="bx bx-check"></i></label></div>
                        </section>
                    </div>
                    <div class="col-xl-4">
                        <aside class="summary-card">
                            <div class="avatar"><i class="bx bx-user-check"></i></div><h2>{{ selectedCoordinator?.name }}</h2><p>{{ selectedCoordinator?.email }}</p>
                            <div class="summary-stats"><div><strong>{{ assignedLevelCount }}</strong><span>niveles</span></div><div><strong>{{ assignedCourseCount }}</strong><span>cursos</span></div></div>
                            <div class="summary-note"><i class="bx bx-info-circle"></i><span>La bandeja de Revisión documental sólo mostrará instrumentos dentro de estos ámbitos. Los niveles incluyen automáticamente sus cursos.</span></div>
                            <button class="btn btn-primary btn-lg w-100" type="button" :disabled="saving" @click="save"><i class="bx bx-save me-1"></i>{{ saving ? "Guardando..." : "Guardar asignación" }}</button>
                        </aside>
                    </div>
                </div>
                <section v-else class="card border-0 shadow-sm"><div class="empty"><i class="bx bx-user-pin"></i><h2>Selecciona una coordinadora</h2><p>Podrás configurar sus niveles y cursos sin modificar la configuración académica base.</p></div></section>
            </template>
        </div>
    </Layout>
</template>

<style scoped>
.assignment-page{--navy:#153d64;--teal:#16877e;--ink:#18253a;color:var(--ink)}.assignment-hero{background:linear-gradient(120deg,#153650,#176878,#248e7f);border-radius:24px;padding:2rem 2.25rem;color:#fff;display:flex;justify-content:space-between;align-items:center;box-shadow:0 18px 45px rgba(19,59,78,.2)}.assignment-hero span{font-size:.7rem;letter-spacing:.16em;font-weight:800;opacity:.75}.assignment-hero h1{color:#fff;margin:.35rem 0;font-size:clamp(1.7rem,3vw,2.35rem)}.assignment-hero p{margin:0;opacity:.85}.assignment-hero>i{font-size:4rem;opacity:.22}.card,.scope-card,.summary-card{border-radius:20px}.scope-card{background:#fff;padding:1.35rem;box-shadow:0 7px 28px rgba(26,48,78,.08)}.scope-heading,.scope-heading>div{display:flex;align-items:center;gap:.85rem}.scope-heading{justify-content:space-between;margin-bottom:1.1rem}.scope-heading h2{font-size:1.05rem;margin:0}.scope-heading p{color:#748092;font-size:.82rem;margin:.2rem 0 0}.scope-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;color:#fff;font-size:1.25rem}.scope-icon.level{background:#6655b5}.scope-icon.course{background:var(--teal)}.scope-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem}.scope-grid.courses{grid-template-columns:repeat(4,1fr)}.scope-option{position:relative;border:1px solid #e1e7ed;border-radius:14px;padding:.85rem;display:flex;align-items:center;gap:.65rem;cursor:pointer;min-height:70px;transition:.18s}.scope-option:hover,.scope-option.selected{border-color:var(--teal);background:#edf8f6;box-shadow:0 0 0 3px rgba(22,135,126,.08)}.scope-option input{position:absolute;opacity:0}.scope-option strong,.scope-option small{display:block}.scope-option small{color:#788496}.scope-option>i{margin-left:auto;color:#fff;background:var(--teal);border-radius:50%;padding:.15rem;opacity:0}.scope-option.selected>i{opacity:1}.summary-card{position:sticky;top:85px;background:linear-gradient(155deg,#fff,#f4f9fa);box-shadow:0 7px 28px rgba(26,48,78,.08);padding:1.6rem;text-align:center}.avatar{width:68px;height:68px;margin:auto;border-radius:22px;display:grid;place-items:center;background:linear-gradient(140deg,var(--navy),var(--teal));color:#fff;font-size:2rem}.summary-card h2{font-size:1.2rem;margin:1rem 0 .2rem}.summary-card>p{color:#798596}.summary-stats{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin:1.2rem 0}.summary-stats div{background:#fff;border-radius:14px;padding:.9rem}.summary-stats strong,.summary-stats span{display:block}.summary-stats strong{font-size:1.5rem;color:var(--navy)}.summary-stats span{color:#7b8797}.summary-note{display:flex;text-align:left;gap:.6rem;background:#eaf4fa;color:#456174;border-radius:14px;padding:.85rem;font-size:.8rem;margin-bottom:1.2rem}.summary-note i{font-size:1.15rem;color:#2f789c}.empty{min-height:400px;display:grid;place-content:center;text-align:center;color:#8490a0}.empty i{font-size:3rem}.empty h2{font-size:1.2rem;margin-top:.6rem}@media(max-width:1199px){.scope-grid.courses{grid-template-columns:repeat(3,1fr)}.summary-card{position:static}}@media(max-width:767px){.assignment-page{padding-left:.7rem!important;padding-right:.7rem!important}.assignment-hero{border-radius:18px;padding:1.4rem}.assignment-hero>i{display:none}.scope-heading,.scope-heading>div{align-items:flex-start}.scope-heading{flex-direction:column}.scope-grid,.scope-grid.courses{grid-template-columns:1fr 1fr}}@media(max-width:450px){.scope-grid,.scope-grid.courses{grid-template-columns:1fr}}
.coordinator-access-note{align-items:center;color:#8a641e;display:flex;font-size:.72rem;gap:.35rem;margin-top:.45rem}.coordinator-access-note i{font-size:1rem}
</style>
