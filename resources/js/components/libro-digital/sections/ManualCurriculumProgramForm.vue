<script setup>
import { computed, ref } from "vue";
import { libroDigitalApi } from "../../../services/libro-digital/api";
import { payloadData, payloadItems, showError, showSuccess } from "../module-utils";

const props = defineProps({
    context: { type: Object, required: true },
    matrix: { type: Object, required: true },
    capabilities: { type: Object, default: () => ({}) },
});
const emit = defineEmits(["created"]);

const currentYear = new Date().getFullYear();
const blankUnit = (order = 1) => ({
    unit_code: `U${order}`,
    official_title: `Unidad ${order}`,
    purpose: "",
    semester: order <= 2 ? 1 : 2,
    estimated_pedagogical_hours: "",
    page_start: "",
    page_end: "",
    objective_ids: [],
    skills_text: "",
    attitudes_text: "",
    knowledge_text: "",
    keywords_text: "",
});
const form = ref({
    schedule_subject_id: "",
    education_level_id: "",
    official_name: "",
    official_code: "",
    description: "",
    issuing_authority: "Ministerio de Educación",
    decree: "",
    edition: "",
    publication_year: currentYear,
    official_url: "",
    estimated_weeks: 38,
    estimated_pedagogical_hours: "",
    source_page_count: "",
    source_file: null,
    objective_ids: [],
    axes: [],
    units: [blankUnit(1)],
    publish_now: false,
});
const objectives = ref([]);
const objectiveSearch = ref("");
const loadingObjectives = ref(false);
const saving = ref(false);

const subjects = computed(() =>
    [...(props.matrix.rows || [])]
        .map((row) => row.subject)
        .sort((a, b) => a.name.localeCompare(b.name, "es"))
);
const levels = computed(() => props.matrix.levels || []);
const selectedSubject = computed(() =>
    subjects.value.find((item) => Number(item.id) === Number(form.value.schedule_subject_id))
);
const selectedLevel = computed(() =>
    levels.value.find((item) => Number(item.id) === Number(form.value.education_level_id))
);
const canPublish = computed(() =>
    Boolean(props.capabilities.can_publish_curriculum_programs)
);
const filteredObjectives = computed(() => {
    const query = objectiveSearch.value.trim().toLocaleLowerCase("es");
    return objectives.value.filter((objective) => {
        if (!query) return true;
        return `${objective.code} ${objective.description} ${objective.axis_code}`
            .toLocaleLowerCase("es")
            .includes(query);
    });
});
const selectedObjectives = computed(() => {
    const selected = new Set(form.value.objective_ids.map(Number));
    return objectives.value.filter((objective) => selected.has(Number(objective.id)));
});

const humanizeAxis = (value) =>
    String(value || "Eje curricular")
        .toLocaleLowerCase("es")
        .replaceAll("_", " ")
        .replace(/(^|\s)\S/g, (letter) => letter.toLocaleUpperCase("es"))
        .replace(/\b(De|Del|La|Las|El|Los|Y|En)\b/g, (word, _match, offset) =>
            offset === 0 ? word : word.toLocaleLowerCase("es")
        );
const splitLines = (value) =>
    String(value || "")
        .split(/\r?\n|;/)
        .map((item) => item.trim())
        .filter(Boolean);
const updateSuggestedIdentity = () => {
    if (!selectedSubject.value || !selectedLevel.value) return;
    if (!form.value.official_name) {
        form.value.official_name = `${selectedSubject.value.name} · Programa de Estudio · ${selectedLevel.value.name}`;
    }
    if (!form.value.official_code) {
        form.value.official_code = `${selectedSubject.value.code || "PROG"}-${selectedLevel.value.grade_code}`;
    }
};
const loadObjectives = async () => {
    updateSuggestedIdentity();
    form.value.objective_ids = [];
    form.value.axes = [];
    form.value.units.forEach((unit) => (unit.objective_ids = []));
    objectives.value = [];
    if (!form.value.schedule_subject_id || !selectedLevel.value?.grade_code) return;
    loadingObjectives.value = true;
    try {
        objectives.value = payloadItems(
            await libroDigitalApi.curriculumObjectives({
                school_id: props.context.school_id,
                academic_year_id: props.context.academic_year_id,
                schedule_subject_id: form.value.schedule_subject_id,
                grade_code: selectedLevel.value.grade_code,
                status: "active",
                per_page: 100,
            })
        ).filter((item) => ["OA", "OAH"].includes(item.objective_type));
    } catch (error) {
        await showError(error, "No se pudieron cargar los OA");
    } finally {
        loadingObjectives.value = false;
    }
};
const selectAllObjectives = () => {
    form.value.objective_ids = objectives.value.map((item) => item.id);
};
const clearObjectives = () => {
    form.value.objective_ids = [];
    form.value.axes = [];
    form.value.units.forEach((unit) => (unit.objective_ids = []));
};
const syncAxes = () => {
    const groups = new Map();
    selectedObjectives.value
        .filter((objective) => objective.objective_type === "OA")
        .forEach((objective) => {
            const key = objective.axis_code || "EJE_GENERAL";
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(objective.id);
        });
    form.value.axes = [...groups.entries()].map(([axis, objectiveIds]) => ({
        name: humanizeAxis(axis),
        description: "",
        objective_ids: objectiveIds,
    }));
};
const addAxis = () => form.value.axes.push({ name: "", description: "", objective_ids: [] });
const removeAxis = (index) => form.value.axes.splice(index, 1);
const addUnit = () => form.value.units.push(blankUnit(form.value.units.length + 1));
const removeUnit = (index) => {
    if (form.value.units.length > 1) form.value.units.splice(index, 1);
};
const pickSource = (event) => {
    form.value.source_file = event.target.files?.[0] || null;
};
const selectedLabel = (objective) => `${objective.code} · ${objective.description}`;

const validateBeforeSubmit = () => {
    if (!form.value.schedule_subject_id || !form.value.education_level_id)
        return "Selecciona la asignatura y el nivel.";
    if (!form.value.official_name.trim()) return "Ingresa el nombre oficial del programa.";
    if (!form.value.objective_ids.length) return "Selecciona al menos un objetivo de aprendizaje.";
    if (!form.value.units.length || form.value.units.some((unit) => !unit.official_title.trim() || !unit.objective_ids.length))
        return "Cada unidad debe tener título y al menos un OA.";
    if (form.value.publish_now && !form.value.source_file)
        return "Adjunta el PDF oficial antes de publicar.";
    return null;
};
const submit = async () => {
    const validation = validateBeforeSubmit();
    if (validation) return showError(new Error(validation), "Revisa el programa");
    saving.value = true;
    try {
        const body = new FormData();
        const scalarFields = [
            "schedule_subject_id", "education_level_id", "official_name", "official_code",
            "description", "issuing_authority", "decree", "edition", "publication_year",
            "official_url", "estimated_weeks", "estimated_pedagogical_hours", "source_page_count",
        ];
        body.set("school_id", props.context.school_id);
        body.set("academic_year_id", props.context.academic_year_id);
        scalarFields.forEach((field) => {
            if (form.value[field] !== "" && form.value[field] !== null) body.set(field, form.value[field]);
        });
        body.set("publish_now", form.value.publish_now ? "1" : "0");
        body.set("objective_ids", JSON.stringify(form.value.objective_ids));
        body.set("axes", JSON.stringify(form.value.axes));
        body.set("units", JSON.stringify(form.value.units.map((unit) => ({
            unit_code: unit.unit_code,
            official_title: unit.official_title,
            purpose: unit.purpose || null,
            semester: unit.semester || null,
            estimated_pedagogical_hours: unit.estimated_pedagogical_hours || null,
            page_start: unit.page_start || null,
            page_end: unit.page_end || null,
            objective_ids: unit.objective_ids,
            skills: splitLines(unit.skills_text),
            attitudes: splitLines(unit.attitudes_text),
            knowledge: splitLines(unit.knowledge_text),
            keywords: splitLines(unit.keywords_text),
        }))));
        if (form.value.source_file) body.set("source_file", form.value.source_file);
        const program = payloadData(await libroDigitalApi.createManualCurriculumProgram(body));
        await showSuccess(
            form.value.publish_now ? "Programa publicado" : "Borrador creado",
            "La creación manual omitió la extracción automática y conservó la trazabilidad ingresada."
        );
        emit("created", program);
    } catch (error) {
        await showError(error, "No se pudo crear el programa");
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <form class="manual-program" @submit.prevent="submit">
        <header class="manual-hero">
            <div class="manual-hero__icon"><i class="bx bx-edit-alt"></i></div>
            <div>
                <span>Vía rápida con control humano</span>
                <h3>Crear programa curricular manualmente</h3>
                <p>Registra la estructura oficial sin esperar la lectura completa del PDF. El documento se cifra y conserva como respaldo.</p>
            </div>
            <div class="manual-hero__badge"><strong>4 pasos</strong><small>sin procesamiento OCR</small></div>
        </header>

        <section class="form-card">
            <div class="step-heading"><span>1</span><div><strong>Identidad curricular</strong><small>Define asignatura, nivel y versión oficial.</small></div></div>
            <div class="form-grid cols-3">
                <label><span>Asignatura *</span><select v-model="form.schedule_subject_id" @change="loadObjectives"><option value="">Seleccionar…</option><option v-for="subject in subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option></select></label>
                <label><span>Nivel de enseñanza *</span><select v-model="form.education_level_id" @change="loadObjectives"><option value="">Seleccionar…</option><option v-for="level in levels" :key="level.id" :value="level.id">{{ level.name }} · {{ level.grade_code }}</option></select></label>
                <label><span>Código institucional</span><input v-model="form.official_code" placeholder="CN-1B-2018" /></label>
                <label class="span-2"><span>Nombre oficial *</span><input v-model="form.official_name" placeholder="Nombre completo del programa" /></label>
                <label><span>Autoridad emisora *</span><input v-model="form.issuing_authority" /></label>
                <label><span>Decreto o resolución</span><input v-model="form.decree" placeholder="Decreto N.º…" /></label>
                <label><span>Edición</span><input v-model="form.edition" placeholder="Segunda edición 2018" /></label>
                <label><span>Año de publicación</span><input v-model.number="form.publication_year" type="number" min="1900" max="2200" /></label>
                <label><span>Semanas</span><input v-model.number="form.estimated_weeks" type="number" min="1" max="60" /></label>
                <label><span>Horas pedagógicas</span><input v-model.number="form.estimated_pedagogical_hours" type="number" min="1" /></label>
                <label><span>Páginas del PDF</span><input v-model.number="form.source_page_count" type="number" min="1" /></label>
                <label class="span-3"><span>Descripción</span><textarea v-model="form.description" rows="2" placeholder="Alcance y observaciones de la versión curricular"></textarea></label>
            </div>
        </section>

        <section class="form-card">
            <div class="step-heading"><span>2</span><div><strong>Fuente de respaldo</strong><small>El PDF se archiva cifrado; no se ejecuta extracción automática.</small></div></div>
            <div class="source-grid">
                <label class="source-picker">
                    <input type="file" accept="application/pdf,.pdf" @change="pickSource" />
                    <i class="bx bxs-file-pdf"></i>
                    <span><strong>{{ form.source_file?.name || 'Seleccionar PDF oficial' }}</strong><small>Máximo 40 MB · almacenamiento privado</small></span>
                    <em>Examinar</em>
                </label>
                <label><span>URL oficial</span><input v-model="form.official_url" type="url" placeholder="https://…" /></label>
            </div>
        </section>

        <section class="form-card">
            <div class="step-heading objectives-heading"><span>3</span><div><strong>Objetivos y ejes</strong><small>Solo se muestran OA activos de la asignatura y nivel.</small></div><div class="heading-actions"><button type="button" @click="selectAllObjectives">Seleccionar todos</button><button type="button" @click="clearObjectives">Limpiar</button></div></div>
            <div v-if="loadingObjectives" class="inline-state"><i class="bx bx-loader-alt bx-spin"></i>Cargando objetivos…</div>
            <div v-else-if="!form.schedule_subject_id || !form.education_level_id" class="inline-state"><i class="bx bx-filter-alt"></i>Selecciona asignatura y nivel para ver sus OA.</div>
            <template v-else>
                <div class="objective-toolbar"><div><strong>{{ form.objective_ids.length }}</strong><span>OA seleccionados de {{ objectives.length }}</span></div><label><i class="bx bx-search"></i><input v-model="objectiveSearch" type="search" placeholder="Buscar código o texto…" /></label></div>
                <div class="objective-grid">
                    <label v-for="objective in filteredObjectives" :key="objective.id" class="objective-option" :class="{ selected: form.objective_ids.includes(objective.id) }">
                        <input v-model="form.objective_ids" type="checkbox" :value="objective.id" />
                        <span><strong>{{ objective.code }}</strong><small>{{ humanizeAxis(objective.axis_code) }}</small><p>{{ objective.description }}</p></span>
                    </label>
                </div>
                <div class="axes-panel">
                    <div class="axes-panel__header"><div><strong>Ejes curriculares</strong><small>Relaciona cada eje con los OA seleccionados.</small></div><div><button type="button" @click="syncAxes"><i class="bx bx-refresh"></i> Generar desde OA</button><button type="button" @click="addAxis"><i class="bx bx-plus"></i> Agregar eje</button></div></div>
                    <div v-if="form.axes.length" class="axis-list">
                        <article v-for="(axis, index) in form.axes" :key="index"><label><span>Nombre</span><input v-model="axis.name" /></label><label><span>OA del eje</span><select v-model="axis.objective_ids" multiple><option v-for="objective in selectedObjectives.filter((item) => item.objective_type === 'OA')" :key="objective.id" :value="objective.id">{{ objective.code }}</option></select></label><button type="button" title="Quitar eje" @click="removeAxis(index)"><i class="bx bx-trash"></i></button></article>
                    </div>
                </div>
            </template>
        </section>

        <section class="form-card">
            <div class="step-heading objectives-heading"><span>4</span><div><strong>Unidades del programa</strong><small>Distribuye OA, horas y contenidos por unidad.</small></div><button type="button" class="add-unit" @click="addUnit"><i class="bx bx-plus"></i> Agregar unidad</button></div>
            <div class="unit-editor-list">
                <article v-for="(unit, index) in form.units" :key="index" class="unit-editor">
                    <header><span>U{{ index + 1 }}</span><div><strong>{{ unit.official_title || `Unidad ${index + 1}` }}</strong><small>{{ unit.objective_ids.length }} objetivos asociados</small></div><button type="button" :disabled="form.units.length === 1" @click="removeUnit(index)"><i class="bx bx-trash"></i></button></header>
                    <div class="form-grid cols-4">
                        <label><span>Código *</span><input v-model="unit.unit_code" /></label>
                        <label class="span-2"><span>Título *</span><input v-model="unit.official_title" /></label>
                        <label><span>Semestre</span><select v-model.number="unit.semester"><option :value="1">1.º</option><option :value="2">2.º</option></select></label>
                        <label><span>Horas</span><input v-model.number="unit.estimated_pedagogical_hours" type="number" min="1" /></label>
                        <label><span>Página inicial</span><input v-model.number="unit.page_start" type="number" min="1" /></label>
                        <label><span>Página final</span><input v-model.number="unit.page_end" type="number" min="1" /></label>
                        <label class="span-4"><span>Objetivos de la unidad *</span><select v-model="unit.objective_ids" multiple class="objective-select"><option v-for="objective in selectedObjectives.filter((item) => item.objective_type === 'OA')" :key="objective.id" :value="objective.id">{{ selectedLabel(objective) }}</option></select><small>Usa Cmd/Ctrl para seleccionar varios.</small></label>
                        <label class="span-4"><span>Propósito</span><textarea v-model="unit.purpose" rows="3"></textarea></label>
                        <label class="span-2"><span>Conocimientos · uno por línea</span><textarea v-model="unit.knowledge_text" rows="3"></textarea></label>
                        <label class="span-2"><span>Habilidades · una por línea</span><textarea v-model="unit.skills_text" rows="3"></textarea></label>
                        <label class="span-2"><span>Actitudes · una por línea</span><textarea v-model="unit.attitudes_text" rows="3"></textarea></label>
                        <label class="span-2"><span>Palabras clave · una por línea</span><textarea v-model="unit.keywords_text" rows="3"></textarea></label>
                    </div>
                </article>
            </div>
        </section>

        <footer class="manual-actions">
            <label v-if="canPublish" class="publish-switch"><input v-model="form.publish_now" type="checkbox" /><span></span><div><strong>Publicar inmediatamente</strong><small>Requiere PDF y permiso de publicación.</small></div></label>
            <div v-else class="publish-note"><i class="bx bx-lock-alt"></i>Se guardará como borrador.</div>
            <button class="submit-program" type="submit" :disabled="saving"><i class="bx" :class="saving ? 'bx-loader-alt bx-spin' : form.publish_now ? 'bx-check-shield' : 'bx-save'"></i>{{ saving ? 'Guardando…' : form.publish_now ? 'Crear y publicar' : 'Guardar borrador' }}</button>
        </footer>
    </form>
</template>

<style scoped>
.manual-program{--brand:#405189;--teal:#0a9d8b;--ink:#202b3d;--muted:#78849a;display:grid;gap:1rem}.manual-hero{position:relative;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:1rem;overflow:hidden;border-radius:18px;background:linear-gradient(125deg,#f3f5fb,#fff);border:1px solid #dfe4ef;padding:1.35rem 1.5rem}.manual-hero:after{position:absolute;right:11%;bottom:-80px;width:180px;height:180px;border:28px solid rgba(64,81,137,.035);border-radius:50%;content:""}.manual-hero__icon{display:grid;place-items:center;width:54px;height:54px;border-radius:15px;background:#405189;color:#fff;font-size:1.55rem;box-shadow:0 10px 22px rgba(64,81,137,.22)}.manual-hero>div:nth-child(2){position:relative;z-index:1}.manual-hero span,.step-heading small,.manual-hero small{color:var(--muted);font-size:.69rem}.manual-hero>div:nth-child(2)>span{color:var(--teal);font-weight:850;text-transform:uppercase;letter-spacing:.11em}.manual-hero h3{margin:.22rem 0;font-size:1.3rem}.manual-hero p{margin:0;color:#68758a;font-size:.78rem}.manual-hero__badge{position:relative;z-index:1;display:grid;min-width:130px;border:1px solid #dfe4ef;border-radius:13px;background:#fff;padding:.7rem 1rem;text-align:center}.manual-hero__badge strong{color:var(--brand)}.form-card{border:1px solid #e2e7ef;border-radius:16px;background:#fff;padding:1.2rem;box-shadow:0 6px 18px rgba(35,48,75,.04)}.step-heading{display:flex;align-items:center;gap:.65rem;margin-bottom:1rem}.step-heading>span{display:grid;place-items:center;width:32px;height:32px;border-radius:10px;background:#e9edf8;color:var(--brand);font-weight:850}.step-heading>div{display:grid}.step-heading strong{font-size:.84rem}.objectives-heading>div:nth-child(2){flex:1}.heading-actions,.axes-panel__header>div:last-child{display:flex;gap:.35rem}.heading-actions button,.axes-panel button,.add-unit{border:1px solid #dce2ec;border-radius:8px;background:#fff;padding:.43rem .6rem;color:var(--brand);font-size:.66rem;font-weight:750}.form-grid{display:grid;gap:.7rem}.form-grid.cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.form-grid.cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}.form-grid label,.source-grid>label{display:grid;gap:.3rem;min-width:0}.form-grid label>span,.source-grid>label>span,.axis-list label>span{color:#68758a;font-size:.63rem;font-weight:750}.form-grid input,.form-grid select,.form-grid textarea,.source-grid input,.axis-list input,.axis-list select{width:100%;border:1px solid #dce2eb;border-radius:8px;background:#fbfcfd;padding:.58rem .65rem;color:var(--ink);font-size:.7rem;outline:none}.form-grid input:focus,.form-grid select:focus,.form-grid textarea:focus,.source-grid input:focus,.axis-list input:focus,.axis-list select:focus{border-color:#7685b2;box-shadow:0 0 0 3px rgba(64,81,137,.08)}.form-grid textarea{resize:vertical}.form-grid label>small{color:#8a95a6;font-size:.57rem}.span-2{grid-column:span 2}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.source-grid{display:grid;grid-template-columns:1.25fr 1fr;align-items:end;gap:.75rem}.source-picker{grid-template-columns:auto 1fr auto!important;align-items:center!important;min-height:70px;border:1px dashed #bac5dc;border-radius:11px;background:#f8f9fc;padding:.75rem;cursor:pointer}.source-picker input{display:none}.source-picker>i{color:#d24d62;font-size:1.7rem}.source-picker>span{display:grid}.source-picker strong{color:#354158;font-size:.72rem}.source-picker em{border-radius:7px;background:#e9edf8;padding:.45rem .6rem;color:var(--brand);font-size:.63rem;font-style:normal;font-weight:800}.inline-state{display:flex;align-items:center;justify-content:center;gap:.45rem;min-height:100px;border:1px dashed #d8dee8;border-radius:10px;color:#7a8799;font-size:.7rem}.objective-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.7rem}.objective-toolbar>div{display:grid}.objective-toolbar strong{font-size:1.15rem;color:var(--brand)}.objective-toolbar span{color:var(--muted);font-size:.59rem}.objective-toolbar label{display:flex;align-items:center;gap:.4rem;width:min(340px,100%);border:1px solid #dce2eb;border-radius:9px;padding:.5rem .65rem}.objective-toolbar input{flex:1;min-width:0;border:0;outline:0;font-size:.68rem}.objective-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.45rem;max-height:370px;overflow:auto;padding:.15rem}.objective-option{display:grid;grid-template-columns:auto 1fr;gap:.55rem;border:1px solid #e3e7ee;border-radius:10px;background:#fff;padding:.65rem;cursor:pointer}.objective-option.selected{border-color:#a8dcd5;background:#f1fbf9}.objective-option input{margin-top:.15rem;accent-color:var(--teal)}.objective-option span{display:grid;grid-template-columns:auto 1fr;column-gap:.4rem}.objective-option strong{color:var(--brand);font-size:.66rem}.objective-option small{color:#8b96a7;font-size:.56rem}.objective-option p{grid-column:1/3;margin:.25rem 0 0;color:#5f6c81;font-size:.62rem;line-height:1.45}.axes-panel{margin-top:1rem;border-top:1px solid #edf0f4;padding-top:1rem}.axes-panel__header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.axes-panel__header>div:first-child{display:grid}.axes-panel__header strong{font-size:.75rem}.axes-panel__header small{color:var(--muted);font-size:.59rem}.axis-list{display:grid;gap:.45rem;margin-top:.7rem}.axis-list article{display:grid;grid-template-columns:1fr 1.2fr auto;align-items:end;gap:.55rem;border-radius:10px;background:#f8f9fc;padding:.65rem}.axis-list label{display:grid;gap:.25rem}.axis-list select{min-height:65px}.axis-list article>button,.unit-editor header>button{width:34px;height:34px;border:0;border-radius:8px;background:#fff0f2;color:#c84a5d}.unit-editor-list{display:grid;gap:.7rem}.unit-editor{overflow:hidden;border:1px solid #e1e6ee;border-radius:13px}.unit-editor>header{display:flex;align-items:center;gap:.65rem;border-bottom:1px solid #e8ecf2;background:#f8f9fc;padding:.7rem .85rem}.unit-editor>header>span{display:grid;place-items:center;width:36px;height:36px;border-radius:9px;background:var(--brand);color:#fff;font-size:.7rem;font-weight:850}.unit-editor>header>div{display:grid;flex:1}.unit-editor>header strong{font-size:.72rem}.unit-editor>header small{color:var(--muted);font-size:.56rem}.unit-editor>.form-grid{padding:.85rem}.objective-select{min-height:105px}.manual-actions{position:sticky;bottom:.6rem;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid #dce2ec;border-radius:14px;background:rgba(255,255,255,.96);padding:.8rem 1rem;box-shadow:0 12px 30px rgba(34,46,75,.13);backdrop-filter:blur(8px)}.publish-switch{display:grid;grid-template-columns:auto 1fr;grid-template-rows:auto auto;column-gap:.65rem;cursor:pointer}.publish-switch input{display:none}.publish-switch>span{grid-row:1/3;position:relative;width:38px;height:22px;border-radius:999px;background:#cdd3dd;transition:.2s}.publish-switch>span:after{position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;content:"";transition:.2s}.publish-switch input:checked+span{background:var(--teal)}.publish-switch input:checked+span:after{transform:translateX(16px)}.publish-switch div{display:grid}.publish-switch strong{font-size:.69rem}.publish-switch small,.publish-note{color:var(--muted);font-size:.58rem}.submit-program{border:0;border-radius:10px;background:linear-gradient(120deg,#405189,#5365a1);padding:.72rem 1.1rem;color:#fff;font-size:.7rem;font-weight:800;box-shadow:0 8px 18px rgba(64,81,137,.2)}.submit-program:disabled{opacity:.65}@media(max-width:991px){.form-grid.cols-3,.form-grid.cols-4{grid-template-columns:repeat(2,minmax(0,1fr))}.span-3,.span-4{grid-column:span 2}.source-grid,.objective-grid{grid-template-columns:1fr}.manual-hero{grid-template-columns:auto 1fr}.manual-hero__badge{display:none}}@media(max-width:575px){.manual-hero{grid-template-columns:1fr}.manual-hero__icon{display:none}.form-grid.cols-3,.form-grid.cols-4{grid-template-columns:1fr}.span-2,.span-3,.span-4{grid-column:1}.objective-toolbar,.axes-panel__header,.manual-actions{align-items:stretch;flex-direction:column}.axis-list article{grid-template-columns:1fr}.heading-actions{grid-column:1/3}.manual-actions{position:static}.submit-program{width:100%}}
</style>
