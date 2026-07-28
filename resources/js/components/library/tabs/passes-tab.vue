<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import { getPdfMake } from "../../../utils/pdfmake";
import { buildLibraryPassPdfDefinition } from "../pass-pdf-definition.mjs";
import {
  confirmLibraryAction,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const nowLocal = () => {
  const date = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
  return date.toISOString().slice(0, 16);
};
const blank = () => ({
  id: null,
  student_profile_id: null,
  professor_staff_id: null,
  valid_from: nowLocal(),
  valid_until: new Date(Date.now() - new Date().getTimezoneOffset() * 60000 + 45 * 60000).toISOString().slice(0, 16),
  reason: "",
  regulation_version: "Reglamento Biblioteca 2026",
  signature_name: "",
  signature_rut: "",
  notes: "",
});

export default {
  components: { LoadingState, LibraryHelpButton, LibraryStatusBadge },
  props: { catalogs: { type: Object, required: true } },
  data() {
    return {
      loading: false,
      error: null,
      items: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: { search: "", status: null, date: "" },
      form: blank(),
      studentSearch: "",
      passLogoDataUrl: null,
      exportingPassId: null,
      showModal: false,
      saving: false,
    };
  },
  computed: {
    studentCandidates() {
      return (this.catalogs.students || []).map((item) => ({
        value: item.id,
        student: item,
        text: `${item.name} · ${item.rut || "Sin RUT"} · ${item.course || "Sin curso"}`,
      }));
    },
    studentMatches() {
      const query = this.normalizeSearch(this.studentSearch);
      if (!query) return this.studentCandidates.slice(0, 20);

      return this.studentCandidates
        .filter((item) => this.normalizeSearch(item.text).includes(query))
        .slice(0, 20);
    },
    selectedStudent() {
      return this.studentCandidates.find(
        (item) => Number(item.value) === Number(this.form.student_profile_id)
      )?.student || null;
    },
  },
  mounted() { this.load(); },
  methods: {
    studentInitials(name) {
      return String(name || "")
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join("")
        .toUpperCase() || "ES";
    },
    passDate(value) {
      if (!value) return "-";
      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      }).format(new Date(value));
    },
    passTime(value) {
      if (!value) return "-";
      return new Intl.DateTimeFormat("es-CL", {
        hour: "2-digit",
        minute: "2-digit",
      }).format(new Date(value));
    },
    normalizeSearch(value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
    },
    async load(page = 1) {
      this.loading = true;
      try {
        const response = await axios.get("/api/biblioteca/pases", { params: { page, ...this.filters } });
        this.items = response.data.data || [];
        this.pagination = { current_page: response.data.current_page, total: response.data.total, per_page: response.data.per_page };
      } catch (error) { this.error = formatLibraryError(error); }
      finally { this.loading = false; }
    },
    openCreate() {
      this.form = blank();
      this.studentSearch = "";
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        ...blank(),
        id: item.id,
        student_profile_id: item.student_profile_id,
        professor_staff_id: item.professor_staff_id,
        valid_from: String(item.valid_from).slice(0, 16),
        valid_until: String(item.valid_until).slice(0, 16),
        reason: item.reason,
        regulation_version: item.regulation_version,
        signature_name: item.signature_name || "",
        signature_rut: item.signature_rut || "",
        notes: item.notes || "",
      };
      this.studentSearch = this.studentCandidates.find(
        (student) => Number(student.value) === Number(item.student_profile_id)
      )?.text || item.student_name_snapshot || "";
      this.showModal = true;
    },
    resolveStudentSelection(value) {
      this.studentSearch = value || "";
      this.form.student_profile_id = null;

      const candidate = this.studentCandidates.find((item) => item.text === this.studentSearch);
      if (!candidate) return;

      this.form.student_profile_id = candidate.value;
      this.syncStudent();
    },
    clearStudent() {
      if (this.form.id) return;
      this.studentSearch = "";
      this.form.student_profile_id = null;
      this.form.signature_name = "";
      this.form.signature_rut = "";
    },
    syncStudent() {
      const student = this.selectedStudent;
      if (student) {
        this.form.signature_name = student.name;
        this.form.signature_rut = student.rut || "";
      }
    },
    async save() {
      this.saving = true;
      try {
        if (this.form.id) await axios.put(`/api/biblioteca/pases/${this.form.id}`, this.form);
        else await axios.post("/api/biblioteca/pases", this.form);
        this.showModal = false;
        await this.load(this.pagination.current_page);
        await showLibrarySuccess("Pase guardado correctamente.");
      } catch (error) { this.error = formatLibraryError(error); }
      finally { this.saving = false; }
    },
    async transition(item, status) {
      const result = await confirmLibraryAction({
        title: status === "utilizado" ? "Confirmar ingreso a Biblioteca" : "Anular pase",
        text: status === "utilizado" ? `Se registrará el uso del pase de ${item.student_name_snapshot}.` : `Se anulará el pase ${item.pass_code}.`,
        confirmButtonText: status === "utilizado" ? "Registrar uso" : "Anular",
        icon: status === "utilizado" ? "question" : "warning",
      });
      if (!result.isConfirmed) return;
      try {
        await axios.post(`/api/biblioteca/pases/${item.id}/${status}`);
        await this.load(this.pagination.current_page);
        await showLibrarySuccess(status === "utilizado" ? "Ingreso registrado." : "Pase anulado.");
      } catch (error) { this.error = formatLibraryError(error); }
    },
    async loadPassLogo() {
      if (this.passLogoDataUrl) return this.passLogoDataUrl;

      try {
        const response = await fetch("/brand/logo-cnsc.png");
        if (!response.ok) return null;
        const blob = await response.blob();
        this.passLogoDataUrl = await new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onload = () => resolve(reader.result);
          reader.onerror = reject;
          reader.readAsDataURL(blob);
        });
      } catch {
        this.passLogoDataUrl = null;
      }

      return this.passLogoDataUrl;
    },
    async exportPass(item) {
      this.exportingPassId = item.id;
      try {
        const logoDataUrl = await this.loadPassLogo();
        const definition = buildLibraryPassPdfDefinition(item, { logoDataUrl });
        getPdfMake()
          .createPdf(definition)
          .download(`pase-${item.pass_code}.pdf`);
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo generar el pase en PDF.");
      } finally {
        this.exportingPassId = null;
      }
    },
  },
};
</script>

<template>
  <div class="passes-view">
    <section class="passes-head">
      <div class="passes-symbol"><i class="bx bx-id-card"></i></div>
      <div><span>REGLAMENTO Y AUTORIZACIÓN</span><h5>Pases de Biblioteca con trazabilidad</h5><p>Emisión compartida entre Biblioteca e Inspectoría, con estudiante, profesor, RUT, horario y firma.</p></div>
      <div class="ms-auto d-flex gap-2"><LibraryHelpButton title="Ayuda: pases" text="Inspectoría y Biblioteca visualizan el mismo pase. Un pase no puede superponerse con otro vigente de la estudiante." /><BButton variant="primary" @click="openCreate"><i class="bx bx-plus me-1"></i>Emitir pase</BButton></div>
    </section>
    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
    <div class="pass-filters">
      <div class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label">Buscar estudiante, RUT, profesor o código</label><BFormInput v-model="filters.search" @keyup.enter="load" /></div>
        <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="[{value:null,text:'Todos'},{value:'emitido',text:'Emitidos'},{value:'utilizado',text:'Utilizados'},{value:'vencido',text:'Vencidos'},{value:'anulado',text:'Anulados'}]" /></div>
        <div class="col-md-2"><label class="form-label">Fecha</label><BFormInput v-model="filters.date" type="date" /></div>
        <div class="col-md-2"><BButton variant="primary" class="w-100" @click="load">Filtrar</BButton></div>
      </div>
    </div>
    <LoadingState v-if="loading" message="Cargando pases..." compact />
    <div v-else class="pass-list">
      <article v-for="item in items" :key="item.id" class="pass-card" :class="`pass-card--${item.status}`">
        <header class="pass-card__header">
          <div class="pass-code"><i class="bx bx-id-card"></i><span>PASE DE BIBLIOTECA</span><strong>{{ item.pass_code }}</strong></div>
          <div class="pass-status"><LibraryStatusBadge :status="item.status" /></div>
        </header>
        <div class="pass-card__body">
          <div class="pass-student">
            <div class="pass-avatar">{{ studentInitials(item.student_name_snapshot) }}</div>
            <div>
              <span>{{ item.student_rut_snapshot || "RUT no informado" }}</span>
              <h6>{{ item.student_name_snapshot }}</h6>
              <p><i class="bx bx-group"></i>{{ item.course_section?.display_name || "Sin curso asociado" }}</p>
            </div>
          </div>
          <div class="pass-validity">
            <div><span>DESDE</span><strong>{{ passTime(item.valid_from) }}</strong><small>{{ passDate(item.valid_from) }}</small></div>
            <i class="bx bx-right-arrow-alt"></i>
            <div><span>HASTA</span><strong>{{ passTime(item.valid_until) }}</strong><small>{{ passDate(item.valid_until) }}</small></div>
          </div>
          <div class="pass-details">
            <div><span><i class="bx bx-user-check"></i> Profesor/a responsable</span><strong>{{ item.professor_name_snapshot || "Sin profesor/a" }}</strong></div>
            <div><span><i class="bx bx-message-square-detail"></i> Motivo</span><strong>{{ item.reason }}</strong></div>
          </div>
        </div>
        <footer class="pass-actions">
          <BButton size="sm" variant="primary" class="pass-action--pdf" :disabled="exportingPassId===item.id" @click="exportPass(item)"><i :class="exportingPassId===item.id?'bx bx-loader-alt bx-spin':'bx bx-download'"></i>{{ exportingPassId===item.id ? "Generando..." : "Descargar pase" }}</BButton>
          <BButton v-if="item.status==='emitido'" size="sm" variant="outline-primary" @click="openEdit(item)"><i class="bx bx-edit-alt"></i>Editar</BButton>
          <BButton v-if="item.status==='emitido'" size="sm" variant="success" @click="transition(item,'utilizado')"><i class="bx bx-check-circle"></i>Registrar uso</BButton>
          <BButton v-if="item.status==='emitido'" size="sm" variant="link" class="pass-action--cancel ms-auto" @click="transition(item,'anulado')"><i class="bx bx-x-circle"></i>Anular</BButton>
        </footer>
      </article>
      <div v-if="!items.length" class="empty-passes"><i class="bx bx-id-card"></i><h5>No hay pases para mostrar</h5><p>Emite un pase o modifica los filtros.</p></div>
    </div>
    <div class="d-flex justify-content-end"><BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" /></div>

    <BModal v-model="showModal" size="lg" :title="form.id ? 'Editar pase' : 'Emitir pase de Biblioteca'" hide-footer>
      <div class="regulation-note"><i class="bx bx-shield-quarter"></i><div><strong>{{form.regulation_version}}</strong><span>La estudiante y el profesor responsable deben conocer el reglamento vigente.</span></div></div>
      <div class="row g-3">
        <div class="col-md-7">
          <label class="form-label">Buscar estudiante *</label>
          <div class="student-search">
            <i class="bx bx-search"></i>
            <BFormInput
              :model-value="studentSearch"
              list="library-pass-students"
              :disabled="Boolean(form.id)"
              autocomplete="off"
              placeholder="Escribe nombre, RUT o curso"
              @update:model-value="resolveStudentSelection"
            />
            <button v-if="studentSearch && !form.id" type="button" aria-label="Limpiar estudiante" @click="clearStudent"><i class="bx bx-x"></i></button>
          </div>
          <datalist id="library-pass-students">
            <option v-for="item in studentMatches" :key="item.value" :value="item.text">{{ item.student.name }}</option>
          </datalist>
          <small v-if="selectedStudent" class="student-selection-ok"><i class="bx bx-check-circle"></i> {{ selectedStudent.name }} · {{ selectedStudent.rut || "Sin RUT" }}</small>
          <small v-else-if="studentSearch" class="student-selection-pending">Selecciona una coincidencia de la lista para asociar la estudiante.</small>
          <small v-else class="student-selection-hint">Las coincidencias se cargan mientras escribes.</small>
        </div>
        <div class="col-md-5"><label class="form-label">Profesor/a responsable *</label><BFormSelect v-model="form.professor_staff_id" :options="(catalogs.staff || []).map((item)=>({value:item.id,text:`${item.full_name} · ${item.rut || 'Sin RUT'}`}))" /></div>
        <div class="col-md-6"><label class="form-label">Válido desde *</label><BFormInput v-model="form.valid_from" type="datetime-local" /></div>
        <div class="col-md-6"><label class="form-label">Válido hasta *</label><BFormInput v-model="form.valid_until" type="datetime-local" /></div>
        <div class="col-12"><label class="form-label">Motivo *</label><BFormTextarea v-model="form.reason" rows="2" /></div>
        <div class="col-md-6"><label class="form-label">Nombre en la firma</label><BFormInput v-model="form.signature_name" /></div>
        <div class="col-md-6"><label class="form-label">RUT firmante</label><BFormInput v-model="form.signature_rut" /></div>
        <div class="col-md-6"><label class="form-label">Versión del reglamento</label><BFormInput v-model="form.regulation_version" /></div>
        <div class="col-md-6"><label class="form-label">Observaciones</label><BFormInput v-model="form.notes" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4"><BButton variant="light" @click="showModal=false">Cancelar</BButton><BButton variant="primary" :disabled="saving || !form.student_profile_id" @click="save">{{saving?"Guardando...":"Guardar pase"}}</BButton></div>
    </BModal>
  </div>
</template>

<style scoped>
.passes-view {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.passes-head {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.3rem 1.4rem;
  background: linear-gradient(135deg, #f2edff, #fff);
  border: 1px solid #e2d8ff;
  border-radius: 18px;
}

.passes-symbol {
  width: 54px;
  height: 54px;
  border-radius: 16px;
  background: #ded2ff;
  color: #6a4bc4;
  display: grid;
  place-items: center;
  font-size: 1.7rem;
}

.passes-head > div:nth-child(2) > span {
  font-size: .66rem;
  font-weight: 800;
  letter-spacing: .14em;
  color: #6a4bc4;
}

.passes-head h5 { margin: .2rem 0; }
.passes-head p { margin: 0; color: #7d8798; }

.pass-filters {
  background: #fff;
  border: 1px solid #e6eaf1;
  border-radius: 15px;
  padding: 1rem;
}

.pass-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(540px, 1fr));
  gap: 1rem;
}

.pass-card {
  --pass: #5268dd;
  --pass-soft: #eef1ff;
  position: relative;
  overflow: hidden;
  background: #fff;
  border: 1px solid #e2e7f0;
  border-top: 4px solid var(--pass);
  border-radius: 18px;
  box-shadow: 0 12px 30px rgba(31, 47, 78, .08);
  transition: transform .18s ease, box-shadow .18s ease;
}

.pass-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 18px 38px rgba(31, 47, 78, .12);
}

.pass-card--utilizado { --pass: #218765; --pass-soft: #e9f7f1; }
.pass-card--vencido { --pass: #c77924; --pass-soft: #fff4e5; }
.pass-card--anulado { --pass: #7b8493; --pass-soft: #f0f2f5; }

.pass-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: .75rem;
  padding: .8rem 1rem;
  background: linear-gradient(90deg, var(--pass-soft), #fff 70%);
  border-bottom: 1px solid #edf0f5;
}

.pass-code {
  display: grid;
  grid-template-columns: 36px 1fr;
  align-items: center;
  column-gap: .65rem;
}

.pass-code > i {
  grid-row: 1 / 3;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  background: var(--pass);
  color: #fff;
  font-size: 1.2rem;
}

.pass-code span {
  color: #7b8799;
  font-size: .58rem;
  font-weight: 800;
  letter-spacing: .1em;
}

.pass-code strong {
  color: #25344d;
  font-size: .8rem;
  letter-spacing: .02em;
}

.pass-status :deep(.badge) {
  display: inline-flex !important;
  width: auto !important;
  min-width: 0 !important;
  height: auto !important;
  padding: .42rem .65rem !important;
  border-radius: 999px !important;
  font-size: .62rem !important;
  line-height: 1 !important;
}

.pass-card__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: .9rem;
  padding: 1rem;
}

.pass-student {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: .75rem;
}

.pass-avatar {
  width: 48px;
  height: 48px;
  flex: 0 0 48px;
  border-radius: 14px;
  display: grid;
  place-items: center;
  background: var(--pass-soft);
  color: var(--pass);
  font-size: .95rem;
  font-weight: 900;
}

.pass-student > div:last-child { min-width: 0; }
.pass-student span { color: #8994a5; font-size: .64rem; }
.pass-student h6 {
  margin: .12rem 0 .18rem;
  overflow: hidden;
  color: #20304a;
  font-size: .96rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pass-student p {
  display: flex;
  align-items: center;
  gap: .3rem;
  margin: 0;
  color: #65738a;
  font-size: .7rem;
}

.pass-validity {
  display: grid;
  grid-template-columns: auto 24px auto;
  align-items: center;
  gap: .35rem;
  padding: .6rem .7rem;
  background: #f7f9fc;
  border: 1px solid #e8ecf3;
  border-radius: 13px;
}

.pass-validity > div {
  display: flex;
  flex-direction: column;
}

.pass-validity span {
  color: #909aaa;
  font-size: .52rem;
  font-weight: 800;
  letter-spacing: .08em;
}

.pass-validity strong { color: #273850; font-size: .88rem; }
.pass-validity small { color: #7c8899; font-size: .58rem; text-transform: capitalize; }
.pass-validity > i { color: var(--pass); font-size: 1.25rem; text-align: center; }

.pass-details {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: minmax(180px, .8fr) minmax(220px, 1.2fr);
  gap: .65rem;
}

.pass-details > div {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: .25rem;
  padding: .62rem .72rem;
  background: #f8f9fc;
  border-radius: 11px;
}

.pass-details span {
  display: flex;
  align-items: center;
  gap: .3rem;
  color: #8893a4;
  font-size: .62rem;
}

.pass-details span i { color: var(--pass); font-size: .9rem; }
.pass-details strong {
  overflow: hidden;
  color: #344158;
  font-size: .73rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pass-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .45rem;
  padding: .75rem 1rem;
  background: #fbfcfe;
  border-top: 1px solid #edf0f4;
}

.pass-actions :deep(.btn) {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  min-height: 34px;
  padding: .38rem .68rem;
  border-radius: 10px;
  font-size: .7rem;
  font-weight: 750;
}

.pass-action--pdf {
  background: var(--pass) !important;
  border-color: var(--pass) !important;
}

.pass-action--cancel {
  color: #d45d68 !important;
  text-decoration: none;
}

.empty-passes {
  grid-column: 1 / -1;
  text-align: center;
  padding: 3rem;
  border: 1px dashed #ccd3df;
  border-radius: 16px;
  color: #7f8999;
}

.empty-passes i { font-size: 2.8rem; }

.regulation-note {
  display: flex;
  align-items: center;
  gap: .7rem;
  padding: .8rem 1rem;
  margin-bottom: 1rem;
  background: #f2edff;
  color: #6349b2;
  border-radius: 12px;
}

.regulation-note i { font-size: 1.7rem; }
.regulation-note span { display: block; color: #7e72a1; font-size: .7rem; }
.student-search { position: relative; }

.student-search > i {
  position: absolute;
  z-index: 2;
  top: 50%;
  left: .85rem;
  color: #78859a;
  font-size: 1.05rem;
  transform: translateY(-50%);
}

.student-search :deep(.form-control) { padding-right: 2.6rem; padding-left: 2.55rem; }

.student-search button {
  position: absolute;
  top: 50%;
  right: .45rem;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  color: #7c8798;
  display: grid;
  place-items: center;
  transform: translateY(-50%);
}

.student-search button:hover { background: #f0edfb; color: #654db2; }
.student-selection-ok,
.student-selection-pending,
.student-selection-hint {
  display: flex;
  align-items: center;
  gap: .25rem;
  margin-top: .4rem;
  font-size: .7rem;
}

.student-selection-ok { color: #23855f; font-weight: 700; }
.student-selection-pending { color: #b36b21; }
.student-selection-hint { color: #8791a1; }

@media (max-width: 700px) {
  .passes-head { align-items: flex-start; flex-wrap: wrap; }
  .passes-head .ms-auto { width: 100%; margin-left: 0 !important; }
  .pass-list { grid-template-columns: 1fr; }
  .pass-card__body { grid-template-columns: 1fr; }
  .pass-validity { justify-content: stretch; }
  .pass-details { grid-template-columns: 1fr; }
  .pass-actions .ms-auto { margin-left: 0 !important; }
}
</style>
