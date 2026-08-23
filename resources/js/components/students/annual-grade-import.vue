<script>
import axios from "axios";

const emptyWorkspace = () => ({
  summary: { imports: 0, matched: 0, unmatched: 0, applied: 0, pending: 0, blocked: 0 },
  imports: [],
  unmatched: [],
  issues: [],
  catalogs: { academic_years: [], school: null },
});

export default {
  props: {
    academicYears: { type: Array, default: () => [] },
    initialYearId: { type: [Number, String], default: null },
  },
  emits: ["completed"],
  data() {
    return {
      open: false,
      loading: false,
      uploading: false,
      retryingId: null,
      workspace: emptyWorkspace(),
      yearId: this.initialYearId || null,
      file: null,
      activeTab: "history",
      success: null,
      error: null,
      selectedRow: null,
      candidateSearch: "",
      candidates: [],
      candidateLoading: false,
      selectedStudentId: null,
      resolutionNote: "",
      resolving: false,
    };
  },
  computed: {
    years() {
      return this.workspace.catalogs?.academic_years?.length
        ? this.workspace.catalogs.academic_years
        : this.academicYears;
    },
    latestImport() {
      return this.workspace.imports?.[0] || null;
    },
    canUpload() {
      return Boolean(this.yearId && this.file && !this.uploading);
    },
    tabCounts() {
      return {
        unmatched: Number(this.workspace.summary?.unmatched || 0),
        issues: this.workspace.issues?.length || 0,
        history: this.workspace.imports?.length || 0,
      };
    },
  },
  watch: {
    initialYearId(value) {
      if (value && !this.open) this.yearId = value;
    },
  },
  beforeUnmount() {
    document.body.classList.remove("grade-import-open");
  },
  methods: {
    async show() {
      this.open = true;
      this.success = null;
      this.error = null;
      document.body.classList.add("grade-import-open");
      if (!this.yearId) this.yearId = this.academicYears?.[0]?.id || null;
      await this.loadWorkspace();
    },
    close() {
      if (this.uploading || this.resolving) return;
      this.open = false;
      this.closeMatcher();
      document.body.classList.remove("grade-import-open");
    },
    async loadWorkspace(preferredTab = null) {
      if (!this.yearId) return;
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get("/api/students/grades/annual-imports", {
          params: { academic_year_id: this.yearId },
        });
        this.workspace = data;
        if (preferredTab) {
          this.activeTab = preferredTab;
        } else if (Number(data.summary?.unmatched || 0) > 0) {
          this.activeTab = "unmatched";
        } else if (data.issues?.length) {
          this.activeTab = "issues";
        } else {
          this.activeTab = "history";
        }
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo cargar el centro de importación de notas.");
      } finally {
        this.loading = false;
      }
    },
    async changeYear() {
      this.file = null;
      this.success = null;
      this.closeMatcher();
      if (this.$refs.fileInput) this.$refs.fileInput.value = "";
      await this.loadWorkspace();
    },
    pickFile(event) {
      this.file = event.target.files?.[0] || null;
      this.success = null;
      this.error = null;
    },
    async upload() {
      if (!this.canUpload) return;
      this.uploading = true;
      this.error = null;
      this.success = null;
      try {
        const form = new FormData();
        form.append("academic_year_id", this.yearId);
        form.append("file", this.file);
        const { data } = await axios.post("/api/students/grades/annual-imports", form, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.success = data.message;
        this.file = null;
        if (this.$refs.fileInput) this.$refs.fileInput.value = "";
        await this.loadWorkspace();
        this.$emit("completed", data.data);
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo procesar el Excel anual.");
      } finally {
        this.uploading = false;
      }
    },
    async retryImport(item) {
      this.retryingId = item.id;
      this.error = null;
      this.success = null;
      try {
        const { data } = await axios.post(`/api/students/grades/annual-imports/${item.id}/retry`);
        this.success = data.message;
        await this.loadWorkspace(this.activeTab);
        this.$emit("completed", data.data);
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudieron reintentar los destinos pendientes.");
      } finally {
        this.retryingId = null;
      }
    },
    openMatcher(row) {
      this.selectedRow = row;
      this.candidateSearch = row.source_name || "";
      this.candidates = Array.isArray(row.candidates) ? [...row.candidates] : [];
      this.selectedStudentId = this.candidates?.[0]?.student_profile_id || null;
      this.resolutionNote = "";
    },
    closeMatcher() {
      this.selectedRow = null;
      this.candidateSearch = "";
      this.candidates = [];
      this.selectedStudentId = null;
      this.resolutionNote = "";
    },
    async searchCandidates() {
      if (!this.selectedRow || this.candidateSearch.trim().length < 2) return;
      this.candidateLoading = true;
      this.error = null;
      try {
        const { data } = await axios.get("/api/students/grades/annual-imports/candidates", {
          params: {
            annual_grade_import_row_id: this.selectedRow.id,
            search: this.candidateSearch.trim(),
          },
        });
        this.candidates = data.data || [];
        this.selectedStudentId = this.candidates?.[0]?.student_profile_id || null;
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudieron buscar estudiantes compatibles.");
      } finally {
        this.candidateLoading = false;
      }
    },
    async resolveMatch() {
      if (!this.selectedRow || !this.selectedStudentId) return;
      this.resolving = true;
      this.error = null;
      this.success = null;
      try {
        const { data } = await axios.patch(`/api/students/grades/annual-import-rows/${this.selectedRow.id}/match`, {
          student_profile_id: this.selectedStudentId,
          note: this.resolutionNote || null,
        });
        this.success = data.message;
        this.closeMatcher();
        await this.loadWorkspace("unmatched");
        this.$emit("completed", data.data);
      } catch (error) {
        this.error = this.errorMessage(error, "No se pudo guardar el match manual.");
      } finally {
        this.resolving = false;
      }
    },
    formatNumber(value) {
      return new Intl.NumberFormat("es-CL").format(Number(value || 0));
    },
    formatDate(value, withTime = false) {
      if (!value) return "—";
      const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(String(value));
      return new Intl.DateTimeFormat("es-CL", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        ...(dateOnly ? { timeZone: "UTC" } : {}),
        ...(withTime ? { hour: "2-digit", minute: "2-digit" } : {}),
      }).format(new Date(dateOnly ? `${value}T00:00:00Z` : value));
    },
    importStatusLabel(status) {
      return ({ completed: "Completada", partial: "Con pendientes", processing: "Procesando" })[status] || status;
    },
    issueLabel(status) {
      return ({
        course_not_found: "Curso no encontrado",
        course_ambiguous: "Curso ambiguo",
        subject_not_found: "Asignatura no encontrada",
        book_not_found: "Libro no creado",
        book_not_open: "Libro cerrado",
        book_ambiguous: "Libro ambiguo",
        assignment_missing: "Docente no vigente",
      })[status] || String(status || "Pendiente").replaceAll("_", " ");
    },
    errorMessage(error, fallback) {
      const errors = error?.response?.data?.errors;
      return (errors && Object.values(errors)?.[0]?.[0]) || error?.response?.data?.message || fallback;
    },
  },
};
</script>

<template>
  <button type="button" class="btn grade-import-launch" @click="show">
    <span class="grade-import-launch-icon"><i class="bx bx-table"></i></span>
    <span><small>Libro Digital</small>Notas anuales</span>
  </button>

  <Teleport to="body">
    <div v-if="open" class="grade-import-shell" role="dialog" aria-modal="true" aria-labelledby="grade-import-title">
      <button type="button" class="grade-import-backdrop" aria-label="Cerrar" @click="close"></button>
      <section class="grade-import-modal">
        <header class="grade-import-hero">
          <div class="grade-import-heading">
            <span class="grade-import-symbol"><i class="bx bx-spreadsheet"></i></span>
            <div>
              <span class="grade-import-eyebrow">Estudiantes · Libro Digital</span>
              <h3 id="grade-import-title">Importación anual de calificaciones</h3>
              <p>Carga el reporte completo, concilia excepciones y conserva la trazabilidad de cada nota.</p>
            </div>
          </div>
          <button type="button" class="grade-import-close" :disabled="uploading || resolving" aria-label="Cerrar" @click="close"><i class="bx bx-x"></i></button>
        </header>

        <div class="grade-import-body">
          <section class="grade-import-uploader">
            <div class="grade-import-field year-field">
              <label for="grade-import-year">Año académico</label>
              <select id="grade-import-year" v-model="yearId" class="form-select" :disabled="uploading" @change="changeYear">
                <option v-for="year in years" :key="year.id" :value="year.id">{{ year.name }}{{ year.is_active ? " · Activo" : "" }}</option>
              </select>
            </div>
            <label class="grade-import-file" :class="{ selected: file }">
              <input ref="fileInput" type="file" accept=".xlsx,.xls" :disabled="uploading" @change="pickFile" />
              <span class="grade-file-icon"><i class="bx" :class="file ? 'bx-check' : 'bx-cloud-upload'"></i></span>
              <span><strong>{{ file ? file.name : "Seleccionar Excel anual" }}</strong><small>{{ file ? "Listo para procesar" : "Formato .xlsx o .xls · máximo 25 MB" }}</small></span>
            </label>
            <button type="button" class="grade-import-submit" :disabled="!canUpload" @click="upload">
              <span v-if="uploading" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bx bx-import"></i>
              {{ uploading ? "Analizando y conciliando…" : "Procesar archivo" }}
            </button>
          </section>

          <section class="grade-import-rules" aria-label="Reglas de importación">
            <div><span class="rule-dot numeric">6,5</span><p><strong>Notas numéricas</strong><small>Se registran en la evaluación correspondiente.</small></p></div>
            <div><span class="rule-dot pending">P</span><p><strong>Pendientes</strong><small>Quedan abiertas; no se inventa una calificación.</small></p></div>
            <div><span class="rule-dot exempt">—</span><p><strong>No aplica</strong><small>Se conserva como eximida/no aplicable.</small></p></div>
            <div><span class="rule-dot teacher"><i class="bx bx-user-plus"></i></span><p><strong>Docente posterior</strong><small>La nota se asocia a la asignatura aunque el libro aún no tenga docente.</small></p></div>
            <div><span class="rule-dot repeat"><i class="bx bx-refresh"></i></span><p><strong>Recarga segura</strong><small>El mismo archivo nunca duplica resultados.</small></p></div>
          </section>

          <div v-if="success" class="grade-import-feedback success"><i class="bx bx-check-circle"></i><span>{{ success }}</span></div>
          <div v-if="error" class="grade-import-feedback error"><i class="bx bx-error-circle"></i><span>{{ error }}</span><button type="button" @click="error = null"><i class="bx bx-x"></i></button></div>

          <div v-if="loading" class="grade-import-loading"><span class="spinner-border"></span><strong>Cargando trazabilidad anual…</strong></div>

          <template v-else>
            <section class="grade-import-kpis">
              <article><span class="kpi-icon violet"><i class="bx bx-layer"></i></span><div><small>Versiones</small><strong>{{ formatNumber(workspace.summary.imports) }}</strong></div></article>
              <article><span class="kpi-icon teal"><i class="bx bx-check-double"></i></span><div><small>Resultados aplicados</small><strong>{{ formatNumber(workspace.summary.applied) }}</strong></div></article>
              <article><span class="kpi-icon amber"><i class="bx bx-time-five"></i></span><div><small>Notas pendientes</small><strong>{{ formatNumber(workspace.summary.pending) }}</strong></div></article>
              <article><span class="kpi-icon rose"><i class="bx bx-user-x"></i></span><div><small>Match manual</small><strong>{{ formatNumber(workspace.summary.unmatched) }}</strong></div></article>
              <article><span class="kpi-icon slate"><i class="bx bx-block"></i></span><div><small>Celdas por resolver</small><strong>{{ formatNumber(workspace.summary.blocked) }}</strong></div></article>
            </section>

            <nav class="grade-import-tabs" aria-label="Secciones de importación">
              <button type="button" :class="{ active: activeTab === 'unmatched' }" @click="activeTab = 'unmatched'">
                <i class="bx bx-link-alt"></i>Match manual <span>{{ formatNumber(tabCounts.unmatched) }}</span>
              </button>
              <button type="button" :class="{ active: activeTab === 'issues' }" @click="activeTab = 'issues'">
                <i class="bx bx-wrench"></i>Destinos pendientes <span>{{ formatNumber(tabCounts.issues) }}</span>
              </button>
              <button type="button" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
                <i class="bx bx-history"></i>Historial <span>{{ formatNumber(tabCounts.history) }}</span>
              </button>
            </nav>

            <section v-if="activeTab === 'unmatched'" class="grade-import-panel">
              <div class="grade-panel-heading">
                <div><span>Cola de conciliación</span><h4>Alumnas no encontradas automáticamente</h4></div>
                <p>La carga ya continuó. Vincula cada fila cuando tengas la identificación correcta.</p>
              </div>
              <div v-if="workspace.unmatched?.length" class="unmatched-grid">
                <article v-for="row in workspace.unmatched" :key="row.id" class="unmatched-card">
                  <div class="unmatched-avatar">{{ String(row.source_name || '?').charAt(0) }}</div>
                  <div class="unmatched-copy">
                    <span>{{ row.source_course_name }} · fila {{ row.source_row }}</span>
                    <strong>{{ row.source_name }}</strong>
                    <small>RUN {{ row.source_rut || "No informado" }} · {{ row.filename }}</small>
                  </div>
                  <div v-if="row.candidates?.length" class="unmatched-hint"><i class="bx bx-bulb"></i>{{ row.candidates.length }} coincidencia{{ row.candidates.length === 1 ? "" : "s" }} sugerida{{ row.candidates.length === 1 ? "" : "s" }}</div>
                  <button type="button" class="btn btn-sm btn-outline-primary" @click="openMatcher(row)">Resolver match</button>
                </article>
              </div>
              <div v-else class="grade-empty"><span><i class="bx bx-user-check"></i></span><strong>Sin matches manuales pendientes</strong><p>Todas las filas del año seleccionado están conciliadas.</p></div>
              <p v-if="workspace.summary.unmatched > workspace.unmatched.length" class="grade-limit-note">Se muestran las primeras {{ workspace.unmatched.length }} filas operativas de {{ formatNumber(workspace.summary.unmatched) }} pendientes.</p>
            </section>

            <section v-if="activeTab === 'issues'" class="grade-import-panel">
              <div class="grade-panel-heading issue-heading">
                <div><span>Preparación del Libro Digital</span><h4>Cursos, asignaturas o libros por habilitar</h4></div>
                <button v-if="latestImport" type="button" class="btn btn-sm btn-outline-primary" :disabled="retryingId === latestImport.id" @click="retryImport(latestImport)">
                  <span v-if="retryingId === latestImport.id" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-refresh"></i>Reintentar última carga
                </button>
              </div>
              <div v-if="workspace.issues?.length" class="issues-list">
                <article v-for="(issue, index) in workspace.issues" :key="`${issue.annual_grade_import_id}-${issue.status}-${index}`">
                  <span class="issue-icon"><i class="bx bx-git-compare"></i></span>
                  <div><span>{{ issue.course }}</span><strong>{{ issue.subject || "Asignatura sin nombre" }}</strong><small>{{ issue.message }}</small></div>
                  <div class="issue-metrics"><span><strong>{{ formatNumber(issue.columns) }}</strong> columnas</span><span><strong>{{ formatNumber(issue.cells) }}</strong> celdas</span></div>
                  <span class="issue-status">{{ issueLabel(issue.status) }}</span>
                </article>
              </div>
              <div v-else class="grade-empty"><span><i class="bx bx-check-shield"></i></span><strong>Todos los destinos están disponibles</strong><p>Las columnas del archivo se conciliaron con libros abiertos.</p></div>
            </section>

            <section v-if="activeTab === 'history'" class="grade-import-panel">
              <div class="grade-panel-heading">
                <div><span>Versiones conservadas</span><h4>Historial anual de importaciones</h4></div>
                <p>Cada archivo distinto crea una versión; una recarga idéntica solo reintenta pendientes.</p>
              </div>
              <div v-if="workspace.imports?.length" class="history-list">
                <article v-for="item in workspace.imports" :key="item.id" class="history-card">
                  <div class="history-version"><small>Versión</small><strong>v{{ item.version }}</strong><span :class="item.status">{{ importStatusLabel(item.status) }}</span></div>
                  <div class="history-main"><strong>{{ item.filename }}</strong><span>{{ item.courses }} cursos · {{ formatNumber(item.student_rows) }} filas · {{ formatNumber(item.columns) }} evaluaciones</span><small>Corte {{ formatDate(item.source_date_from) }} — {{ formatDate(item.source_date_to) }} · cargado {{ formatDate(item.created_at, true) }} por {{ item.created_by || "Sistema" }}</small></div>
                  <div class="history-stats"><span><strong>{{ formatNumber(item.applied_results) }}</strong> aplicadas</span><span><strong>{{ formatNumber(item.pending_cells) }}</strong> pendientes</span><span><strong>{{ formatNumber(item.unmatched_rows) }}</strong> sin match</span></div>
                  <button type="button" class="history-retry" :disabled="retryingId === item.id" title="Reintentar sin duplicar" @click="retryImport(item)"><span v-if="retryingId === item.id" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-refresh"></i></button>
                </article>
              </div>
              <div v-else class="grade-empty"><span><i class="bx bx-file-blank"></i></span><strong>Aún no hay importaciones</strong><p>Selecciona el Excel anual para iniciar la primera versión.</p></div>
            </section>
          </template>
        </div>

        <footer class="grade-import-footer">
          <span><i class="bx bx-shield-quarter"></i>Las notas manuales existentes tienen prioridad y nunca se sobrescriben.</span>
          <button type="button" class="btn btn-light" :disabled="uploading || resolving" @click="close">Cerrar</button>
        </footer>
      </section>

      <section v-if="selectedRow" class="grade-match-modal" role="dialog" aria-modal="true" aria-labelledby="grade-match-title">
        <header>
          <div><span>Match manual</span><h4 id="grade-match-title">Vincular estudiante</h4></div>
          <button type="button" :disabled="resolving" aria-label="Cerrar conciliación" @click="closeMatcher"><i class="bx bx-x"></i></button>
        </header>
        <div class="grade-match-source">
          <span>{{ selectedRow.source_course_name }} · fila {{ selectedRow.source_row }}</span>
          <strong>{{ selectedRow.source_name }}</strong>
          <small>RUN de origen: {{ selectedRow.source_rut || "No informado" }}</small>
        </div>
        <form @submit.prevent="searchCandidates">
          <label>Buscar por nombre o RUN</label>
          <div class="grade-match-search"><input v-model="candidateSearch" type="search" class="form-control" minlength="2" required /><button type="submit" class="btn btn-primary" :disabled="candidateLoading"><span v-if="candidateLoading" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-search"></i></button></div>
        </form>
        <div class="grade-candidates">
          <label v-for="candidate in candidates" :key="candidate.student_profile_id" :class="{ selected: Number(selectedStudentId) === Number(candidate.student_profile_id) }">
            <input v-model="selectedStudentId" type="radio" :value="candidate.student_profile_id" />
            <span class="candidate-check"><i class="bx bx-check"></i></span>
            <span><strong>{{ candidate.name }}</strong><small>{{ candidate.rut || "Sin RUN" }} · {{ candidate.course }}</small></span>
            <em>{{ candidate.score }}%</em>
          </label>
          <div v-if="!candidates.length && !candidateLoading" class="candidate-empty">Busca una estudiante matriculada en el año y curso compatible.</div>
        </div>
        <label class="grade-match-note">Nota de resolución <span>opcional</span><textarea v-model="resolutionNote" class="form-control" rows="2" maxlength="500" placeholder="Ej.: RUN corregido contra ficha de matrícula"></textarea></label>
        <footer><button type="button" class="btn btn-light" :disabled="resolving" @click="closeMatcher">Cancelar</button><button type="button" class="btn btn-primary" :disabled="!selectedStudentId || resolving" @click="resolveMatch"><span v-if="resolving" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-link"></i>{{ resolving ? "Vinculando…" : "Confirmar vínculo" }}</button></footer>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.grade-import-launch{display:inline-flex;align-items:center;gap:.6rem;padding:.42rem .8rem;background:linear-gradient(135deg,#312e81,#4f46e5);border:0;color:#fff;box-shadow:0 8px 20px rgba(49,46,129,.2);text-align:left}.grade-import-launch:hover,.grade-import-launch:focus{color:#fff;background:linear-gradient(135deg,#29236f,#4338ca);transform:translateY(-1px)}.grade-import-launch-icon{display:grid;place-items:center;width:2rem;height:2rem;border-radius:.6rem;background:rgba(255,255,255,.14);font-size:1.1rem}.grade-import-launch span:last-child{display:grid;line-height:1.05;font-size:.83rem;font-weight:700}.grade-import-launch small{font-size:.61rem;letter-spacing:.06em;text-transform:uppercase;opacity:.72;margin-bottom:.2rem}
.grade-import-shell{position:fixed;inset:0;z-index:1090;display:grid;place-items:center;padding:1.4rem}.grade-import-backdrop{position:absolute;inset:0;border:0;background:rgba(10,15,35,.72);backdrop-filter:blur(8px)}.grade-import-modal{position:relative;display:flex;flex-direction:column;width:min(1320px,96vw);max-height:94vh;overflow:hidden;border:1px solid rgba(255,255,255,.14);border-radius:1.35rem;background:#f4f6fb;box-shadow:0 28px 90px rgba(6,9,23,.38)}
.grade-import-hero{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.35rem 1.55rem;color:#fff;background:radial-gradient(circle at 78% -80%,rgba(45,212,191,.45),transparent 42%),linear-gradient(120deg,#111936,#26205f 58%,#3d277d)}.grade-import-heading{display:flex;align-items:center;gap:1rem}.grade-import-symbol{display:grid;place-items:center;flex:0 0 3.4rem;height:3.4rem;border-radius:1rem;background:linear-gradient(145deg,rgba(255,255,255,.22),rgba(255,255,255,.08));border:1px solid rgba(255,255,255,.2);font-size:1.65rem;box-shadow:inset 0 1px rgba(255,255,255,.2)}.grade-import-eyebrow{display:block;margin-bottom:.25rem;color:#9deadd;font-size:.68rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.grade-import-hero h3{margin:0;font-size:1.45rem;font-weight:750;color:#fff}.grade-import-hero p{margin:.28rem 0 0;color:rgba(255,255,255,.7);font-size:.86rem}.grade-import-close,.grade-match-modal header button{display:grid;place-items:center;width:2.5rem;height:2.5rem;border:1px solid rgba(255,255,255,.16);border-radius:.8rem;background:rgba(255,255,255,.08);color:#fff;font-size:1.35rem}.grade-import-close:hover{background:rgba(255,255,255,.16)}
.grade-import-body{padding:1.15rem 1.35rem 1.35rem;overflow:auto}.grade-import-uploader{display:grid;grid-template-columns:200px minmax(330px,1fr) auto;gap:.85rem;align-items:end;padding:1rem;border:1px solid #dfe3ee;border-radius:1rem;background:#fff;box-shadow:0 5px 18px rgba(25,32,67,.04)}.grade-import-field label,.grade-match-modal form>label,.grade-match-note{display:block;margin-bottom:.35rem;color:#576077;font-size:.72rem;font-weight:750;text-transform:uppercase;letter-spacing:.05em}.grade-import-file{display:flex;align-items:center;gap:.75rem;height:58px;padding:.6rem .85rem;border:1.5px dashed #c7ccdc;border-radius:.8rem;background:#fafbfe;cursor:pointer}.grade-import-file:hover,.grade-import-file.selected{border-color:#6559d9;background:#f7f5ff}.grade-import-file input{position:absolute;opacity:0;pointer-events:none}.grade-file-icon{display:grid;place-items:center;flex:0 0 2.35rem;height:2.35rem;border-radius:.7rem;background:#eeebff;color:#5549c8;font-size:1.25rem}.grade-import-file.selected .grade-file-icon{background:#dff7f0;color:#087d68}.grade-import-file>span:last-child{display:grid;min-width:0}.grade-import-file strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#20263a;font-size:.82rem}.grade-import-file small{margin-top:.15rem;color:#858ca0;font-size:.69rem}.grade-import-submit{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;height:58px;padding:0 1.25rem;border:0;border-radius:.8rem;background:linear-gradient(135deg,#4f46e5,#6d4fd1);color:#fff;font-size:.82rem;font-weight:750;box-shadow:0 8px 18px rgba(79,70,229,.2)}.grade-import-submit:disabled{opacity:.5;box-shadow:none}.grade-import-rules{display:grid;grid-template-columns:repeat(5,1fr);gap:.65rem;margin-top:.75rem}.grade-import-rules>div{display:flex;align-items:center;gap:.65rem;padding:.72rem .8rem;border:1px solid #e2e5ed;border-radius:.8rem;background:rgba(255,255,255,.72)}.rule-dot{display:grid;place-items:center;flex:0 0 2rem;height:2rem;border-radius:.62rem;font-size:.68rem;font-weight:800}.rule-dot.numeric{background:#ddf8ef;color:#087d68}.rule-dot.pending{background:#fff2cc;color:#94670c}.rule-dot.exempt{background:#edf0f6;color:#5c6478}.rule-dot.teacher{background:#e1f0ff;color:#17639a;font-size:1rem}.rule-dot.repeat{background:#eae8ff;color:#5145c6;font-size:1rem}.grade-import-rules p{display:grid;margin:0}.grade-import-rules strong{color:#343b50;font-size:.72rem}.grade-import-rules small{margin-top:.08rem;color:#858ca0;font-size:.62rem;line-height:1.25}
.grade-import-feedback{display:flex;align-items:center;gap:.55rem;margin-top:.75rem;padding:.7rem .85rem;border-radius:.75rem;font-size:.76rem;font-weight:650}.grade-import-feedback.success{background:#e1f8f0;color:#08755f}.grade-import-feedback.error{background:#ffebec;color:#a32c39}.grade-import-feedback button{margin-left:auto;border:0;background:transparent;color:inherit}.grade-import-loading{display:grid;place-items:center;gap:.7rem;min-height:260px;color:#5c6478;font-size:.82rem}.grade-import-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:.65rem;margin-top:.8rem}.grade-import-kpis article{display:flex;align-items:center;gap:.7rem;padding:.82rem;border:1px solid #e0e4ed;border-radius:.85rem;background:#fff}.kpi-icon{display:grid;place-items:center;flex:0 0 2.45rem;height:2.45rem;border-radius:.72rem;font-size:1.15rem}.kpi-icon.violet{background:#eceaff;color:#574ac8}.kpi-icon.teal{background:#ddf8ef;color:#087d68}.kpi-icon.amber{background:#fff2d1;color:#9b6b0c}.kpi-icon.rose{background:#ffe6e8;color:#b13242}.kpi-icon.slate{background:#e9edf5;color:#596277}.grade-import-kpis article div{display:grid}.grade-import-kpis small{color:#7d8497;font-size:.65rem}.grade-import-kpis strong{color:#22283b;font-size:1.14rem;line-height:1.2}.grade-import-tabs{display:flex;gap:.3rem;margin-top:.85rem;padding:.28rem;border:1px solid #dfe3ec;border-radius:.8rem;background:#e9ecf3}.grade-import-tabs button{display:inline-flex;align-items:center;justify-content:center;gap:.38rem;min-height:38px;padding:.45rem .8rem;border:0;border-radius:.62rem;background:transparent;color:#60687c;font-size:.73rem;font-weight:750}.grade-import-tabs button.active{background:#fff;color:#4439b4;box-shadow:0 3px 10px rgba(30,36,68,.08)}.grade-import-tabs button span{padding:.08rem .4rem;border-radius:1rem;background:#dce0e9;color:#60687c;font-size:.62rem}.grade-import-tabs button.active span{background:#eceaff;color:#4439b4}.grade-import-panel{margin-top:.75rem;padding:1rem;border:1px solid #e0e4ed;border-radius:1rem;background:#fff}.grade-panel-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:.85rem}.grade-panel-heading>div>span{display:block;color:#6559c8;font-size:.63rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.grade-panel-heading h4{margin:.18rem 0 0;color:#262c40;font-size:1rem}.grade-panel-heading>p{max-width:470px;margin:0;color:#7a8194;font-size:.7rem;text-align:right}.issue-heading{align-items:center}
.unmatched-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem;max-height:330px;overflow:auto;padding-right:.25rem}.unmatched-card{display:grid;grid-template-columns:auto 1fr auto;grid-template-areas:"avatar copy button" "avatar hint button";align-items:center;gap:.25rem .7rem;padding:.72rem;border:1px solid #e3e6ee;border-radius:.82rem;background:#fbfcfe}.unmatched-avatar{grid-area:avatar;display:grid;place-items:center;width:2.6rem;height:2.6rem;border-radius:.8rem;background:linear-gradient(145deg,#e9e6ff,#dcd7ff);color:#5145bd;font-size:1rem;font-weight:800;text-transform:uppercase}.unmatched-copy{grid-area:copy;display:grid;min-width:0}.unmatched-copy span{color:#7166ce;font-size:.61rem;font-weight:750;text-transform:uppercase}.unmatched-copy strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#293044;font-size:.77rem}.unmatched-copy small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#8a90a0;font-size:.63rem}.unmatched-hint{grid-area:hint;display:flex;align-items:center;gap:.25rem;color:#9a6d10;font-size:.62rem}.unmatched-card>button{grid-area:button}.grade-limit-note{margin:.7rem 0 0;color:#7d8496;font-size:.65rem}.issues-list,.history-list{display:grid;gap:.5rem;max-height:345px;overflow:auto;padding-right:.25rem}.issues-list article{display:grid;grid-template-columns:auto minmax(220px,1fr) auto auto;align-items:center;gap:.75rem;padding:.72rem;border:1px solid #e4e7ee;border-radius:.78rem}.issue-icon{display:grid;place-items:center;width:2.3rem;height:2.3rem;border-radius:.7rem;background:#fff1dc;color:#9e6711;font-size:1.05rem}.issues-list article>div:nth-child(2){display:grid}.issues-list article>div:nth-child(2)>span{color:#665ac7;font-size:.61rem;font-weight:750}.issues-list article>div:nth-child(2)>strong{color:#293044;font-size:.75rem}.issues-list article>div:nth-child(2)>small{color:#858b9c;font-size:.63rem}.issue-metrics{display:flex;gap:.5rem}.issue-metrics span{display:grid;min-width:58px;padding:.35rem .5rem;border-radius:.55rem;background:#f2f4f8;color:#808699;font-size:.57rem;text-align:center}.issue-metrics strong{color:#394054;font-size:.72rem}.issue-status{padding:.3rem .55rem;border-radius:1rem;background:#fff0dc;color:#94630f;font-size:.6rem;font-weight:750;white-space:nowrap}.history-card{display:grid;grid-template-columns:90px minmax(280px,1fr) auto 34px;align-items:center;gap:.8rem;padding:.72rem;border:1px solid #e3e6ee;border-radius:.82rem;background:#fbfcfe}.history-version{display:grid;padding-right:.7rem;border-right:1px solid #e4e7ee}.history-version small{color:#858b9d;font-size:.57rem;text-transform:uppercase}.history-version strong{color:#3e35a7;font-size:1.1rem}.history-version span{width:max-content;margin-top:.2rem;padding:.16rem .4rem;border-radius:1rem;background:#fff0d4;color:#8c620d;font-size:.55rem;font-weight:750}.history-version span.completed{background:#def7ee;color:#08765f}.history-main{display:grid;min-width:0}.history-main strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#293044;font-size:.75rem}.history-main span{color:#60687c;font-size:.65rem}.history-main small{color:#8b91a2;font-size:.59rem}.history-stats{display:flex;gap:.45rem}.history-stats span{display:grid;min-width:70px;padding:.35rem .5rem;border-radius:.55rem;background:#f0f2f7;color:#7e8598;font-size:.55rem;text-align:center}.history-stats strong{color:#333a4e;font-size:.7rem}.history-retry{display:grid;place-items:center;width:2rem;height:2rem;border:1px solid #d9dde8;border-radius:.6rem;background:#fff;color:#5146bd}.grade-empty{display:grid;place-items:center;min-height:180px;text-align:center}.grade-empty>span{display:grid;place-items:center;width:3.3rem;height:3.3rem;border-radius:1rem;background:#eceaff;color:#5145bd;font-size:1.55rem}.grade-empty strong{margin-top:.55rem;color:#30364a;font-size:.8rem}.grade-empty p{margin:.12rem 0 0;color:#858b9e;font-size:.68rem}.grade-import-footer{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.75rem 1.35rem;border-top:1px solid #dfe3ec;background:#fff}.grade-import-footer>span{display:flex;align-items:center;gap:.4rem;color:#666e81;font-size:.67rem}.grade-import-footer>span i{color:#0e8a74;font-size:1rem}
.grade-match-modal{position:absolute;z-index:3;top:50%;left:50%;width:min(520px,calc(100vw - 2rem));max-height:88vh;overflow:auto;transform:translate(-50%,-50%);padding:1rem;border:1px solid #dddfea;border-radius:1rem;background:#fff;box-shadow:0 28px 80px rgba(10,14,37,.36)}.grade-match-modal header{display:flex;justify-content:space-between;align-items:center}.grade-match-modal header span{display:block;color:#665ac8;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.09em}.grade-match-modal header h4{margin:.15rem 0 0;color:#242a3e;font-size:1.05rem}.grade-match-modal header button{border-color:#e1e4ed;background:#f4f5f8;color:#5c6477}.grade-match-source{display:grid;margin:.8rem 0;padding:.78rem;border-radius:.75rem;background:linear-gradient(130deg,#efedff,#f8f7ff)}.grade-match-source span{color:#6257bd;font-size:.62rem;font-weight:700}.grade-match-source strong{color:#282e42;font-size:.82rem}.grade-match-source small{color:#777e91;font-size:.65rem}.grade-match-search{display:flex;gap:.4rem}.grade-candidates{display:grid;gap:.4rem;max-height:230px;overflow:auto;margin-top:.7rem}.grade-candidates label{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.6rem;padding:.65rem;border:1px solid #e1e4ec;border-radius:.7rem;cursor:pointer}.grade-candidates label.selected{border-color:#6256cd;background:#f6f4ff}.grade-candidates input{position:absolute;opacity:0}.candidate-check{display:grid;place-items:center;width:1.6rem;height:1.6rem;border:1px solid #d7dae5;border-radius:50%;color:transparent}.grade-candidates label.selected .candidate-check{border-color:#5b50c6;background:#5b50c6;color:#fff}.grade-candidates label>span:nth-child(3){display:grid}.grade-candidates strong{color:#30364a;font-size:.72rem}.grade-candidates small{color:#858b9b;font-size:.62rem}.grade-candidates em{color:#6256c8;font-size:.65rem;font-style:normal;font-weight:750}.candidate-empty{padding:1.3rem;border:1px dashed #daddE6;border-radius:.7rem;color:#858c9d;font-size:.67rem;text-align:center}.grade-match-note{margin-top:.75rem}.grade-match-note span{color:#969bad;font-weight:500;text-transform:none}.grade-match-note textarea{margin-top:.35rem;text-transform:none;letter-spacing:0}.grade-match-modal footer{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.8rem;padding-top:.8rem;border-top:1px solid #e4e7ee}.grade-match-modal footer .btn{display:inline-flex;align-items:center;gap:.35rem}
@media (max-width:991px){.grade-import-shell{padding:.65rem}.grade-import-modal{width:100%;max-height:97vh}.grade-import-uploader{grid-template-columns:160px 1fr}.grade-import-submit{grid-column:1/-1;height:44px}.grade-import-rules{grid-template-columns:repeat(2,1fr)}.grade-import-kpis{grid-template-columns:repeat(3,1fr)}.unmatched-grid{grid-template-columns:1fr}.history-card{grid-template-columns:75px 1fr 34px}.history-stats{grid-column:2/3}.issues-list article{grid-template-columns:auto 1fr auto}.issue-metrics{grid-column:2/3}.issue-status{grid-row:1;grid-column:3}}
@media (max-width:576px){.grade-import-shell{padding:0;place-items:stretch}.grade-import-modal{width:100%;max-height:none;height:100%;border:0;border-radius:0}.grade-import-hero{padding:1rem}.grade-import-symbol{display:none}.grade-import-hero h3{font-size:1.05rem}.grade-import-hero p{font-size:.68rem}.grade-import-body{padding:.8rem}.grade-import-uploader{grid-template-columns:1fr}.grade-import-file,.grade-import-submit{grid-column:auto}.grade-import-rules{grid-template-columns:1fr 1fr}.grade-import-rules>div{padding:.55rem}.grade-import-rules small{display:none}.grade-import-kpis{grid-template-columns:1fr 1fr}.grade-import-kpis article:last-child{grid-column:1/-1}.grade-import-tabs{overflow:auto}.grade-import-tabs button{flex:0 0 auto}.grade-panel-heading{display:grid;align-items:start}.grade-panel-heading>p{text-align:left}.unmatched-card{grid-template-columns:auto 1fr;grid-template-areas:"avatar copy" "hint hint" "button button"}.unmatched-card>button{width:100%}.issues-list article{grid-template-columns:auto 1fr}.issue-status{grid-row:auto;grid-column:2}.history-card{grid-template-columns:60px 1fr 30px}.history-stats{display:grid;grid-template-columns:repeat(3,1fr);grid-column:1/-1}.history-stats span{min-width:0}.grade-import-footer{padding:.65rem .8rem}.grade-import-footer>span{max-width:70%}.grade-import-footer>button{font-size:.7rem}}
</style>
