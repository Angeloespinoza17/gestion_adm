<script>
import axios from "axios";
import Multiselect from "@vueform/multiselect";

const pad = (value) => String(value).padStart(2, "0");
const ymd = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

const emptyConfiguration = () => ({
  period_mode: "month",
  year: currentYear,
  month: currentMonth,
  start_date: "",
  end_date: "",
  responsible_staff_id: "",
  dependency_ids: [],
  frequency: "weekly",
  interval_value: 1,
  weekdays: [1, 3],
  month_day: 1,
  max_visits_per_day: 4,
  visits_per_dependency: 1,
  start_time: "09:00",
  slot_minutes: 45,
  visit_type: "Inspección",
  notes: "Planificación preventiva generada automáticamente.",
  exclude_weekends: true,
  exclude_non_school_days: true,
});

export default {
  components: { Multiselect },
  props: {
    modelValue: { type: Boolean, default: false },
    catalogs: { type: Object, required: true },
  },
  emits: ["update:modelValue", "confirmed"],
  data() {
    return {
      step: 1,
      loading: false,
      confirming: false,
      error: null,
      warnings: [],
      summary: {},
      rows: [],
      previewMode: "table",
      previewPage: 1,
      previewPageSize: 50,
      idempotencyKey: "",
      form: emptyConfiguration(),
      months: [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre",
      ],
      weekdayOptions: [
        { value: 1, label: "Lun" },
        { value: 2, label: "Mar" },
        { value: 3, label: "Mié" },
        { value: 4, label: "Jue" },
        { value: 5, label: "Vie" },
        { value: 6, label: "Sáb" },
        { value: 7, label: "Dom" },
      ],
    };
  },
  computed: {
    isOpen: {
      get() { return this.modelValue; },
      set(value) { this.$emit("update:modelValue", value); },
    },
    yearOptions() {
      return Array.from({ length: 6 }, (_, index) => currentYear - 1 + index);
    },
    dependencyOptions() {
      return (this.catalogs.dependencies || []).map((dependency) => ({
        value: Number(dependency.id),
        label: `${dependency.code} · ${dependency.name}`,
      }));
    },
    staffOptions() {
      return (this.catalogs.maintenance_assignees || []).map((person) => ({
        value: Number(person.id),
        label: person.label || person.full_name,
      }));
    },
    selectedDependencyCount() {
      return this.form.dependency_ids.length || this.dependencyOptions.length;
    },
    requestedVisits() {
      return this.selectedDependencyCount * Number(this.form.visits_per_dependency || 0);
    },
    includedRows() {
      return this.rows.filter((row) => row.included);
    },
    previewPageCount() {
      return Math.max(1, Math.ceil(this.rows.length / this.previewPageSize));
    },
    displayedRows() {
      const start = (this.previewPage - 1) * this.previewPageSize;
      return this.rows.slice(start, start + this.previewPageSize);
    },
    proposalGroups() {
      const groups = new Map();
      this.includedRows.forEach((row) => {
        if (!groups.has(row.visit_date)) groups.set(row.visit_date, []);
        groups.get(row.visit_date).push(row);
      });

      return [...groups.entries()]
        .sort(([dateA], [dateB]) => dateA.localeCompare(dateB))
        .map(([date, rows]) => ({
          date,
          label: this.longDate(date),
          rows: rows.sort((a, b) => String(a.visit_time).localeCompare(String(b.visit_time))),
        }));
    },
    frequencyHint() {
      const interval = Number(this.form.interval_value || 1);
      const labels = {
        daily: interval === 1 ? "Cada día habilitado" : `Cada ${interval} días`,
        weekly: interval === 1 ? "Todas las semanas" : `Cada ${interval} semanas`,
        biweekly: interval === 1 ? "Cada dos semanas" : `Cada ${interval * 2} semanas`,
        monthly: interval === 1 ? "Todos los meses" : `Cada ${interval} meses`,
      };
      return labels[this.form.frequency] || "";
    },
  },
  watch: {
    modelValue(value) {
      if (value) this.reset();
    },
    "form.period_mode"() { this.applyPeriod(); },
    "form.year"() { this.applyPeriod(); },
    "form.month"() { this.applyPeriod(); },
  },
  mounted() {
    this.applyPeriod();
  },
  methods: {
    reset() {
      this.step = 1;
      this.loading = false;
      this.confirming = false;
      this.error = null;
      this.warnings = [];
      this.summary = {};
      this.rows = [];
      this.previewMode = "table";
      this.previewPage = 1;
      this.idempotencyKey = "";
      this.form = emptyConfiguration();
      this.applyPeriod();
    },
    applyPeriod() {
      if (this.form.period_mode === "custom") return;

      const year = Number(this.form.year);
      const month = this.form.period_mode === "year" ? 1 : Number(this.form.month);
      const lastMonth = this.form.period_mode === "year" ? 12 : month;
      this.form.start_date = ymd(new Date(year, month - 1, 1, 12));
      this.form.end_date = ymd(new Date(year, lastMonth, 0, 12));
    },
    toggleWeekday(value) {
      const current = [...this.form.weekdays];
      const index = current.indexOf(value);
      if (index >= 0 && current.length > 1) current.splice(index, 1);
      else if (index < 0) current.push(value);
      this.form.weekdays = current.sort((a, b) => a - b);
    },
    buildConfiguration() {
      return {
        start_date: this.form.start_date,
        end_date: this.form.end_date,
        responsible_staff_id: Number(this.form.responsible_staff_id),
        dependency_ids: this.form.dependency_ids.map(Number),
        frequency: this.form.frequency,
        interval_value: Number(this.form.interval_value),
        weekdays: this.form.weekdays.map(Number),
        month_day: Number(this.form.month_day),
        max_visits_per_day: Number(this.form.max_visits_per_day),
        visits_per_dependency: Number(this.form.visits_per_dependency),
        start_time: this.form.start_time,
        slot_minutes: Number(this.form.slot_minutes),
        visit_type: this.form.visit_type,
        notes: this.form.notes || null,
        exclude_weekends: Boolean(this.form.exclude_weekends),
        exclude_non_school_days: Boolean(this.form.exclude_non_school_days),
      };
    },
    validateConfiguration() {
      if (!this.form.responsible_staff_id) return "Selecciona el funcionario base de la planificación.";
      if (!this.form.start_date || !this.form.end_date) return "Define el período de la propuesta.";
      if (this.form.start_date > this.form.end_date) return "La fecha inicial no puede ser posterior a la final.";
      if (this.requestedVisits < 1) return "Selecciona al menos una dependencia.";
      if (this.requestedVisits > 1000) return "La propuesta no puede superar 1.000 visitas.";
      return null;
    },
    async generatePreview() {
      this.error = this.validateConfiguration();
      if (this.error) return;

      this.loading = true;
      this.warnings = [];
      try {
        const response = await axios.post("/api/maintenance/visits/planning/preview", {
          configuration: this.buildConfiguration(),
        });
        this.rows = (response.data.data.rows || []).map((row) => ({
          ...row,
          _error: null,
          _editingDependency: false,
          _editingStaff: false,
        }));
        this.summary = response.data.data.summary || {};
        this.warnings = response.data.data.warnings || [];
        this.idempotencyKey = this.newIdempotencyKey();
        this.previewPage = 1;
        this.step = 2;
        this.$nextTick(() => this.$refs.planningContent?.scrollTo({ top: 0, behavior: "auto" }));
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async confirmPlan() {
      if (!this.includedRows.length) {
        this.error = "Mantén al menos una visita incluida antes de confirmar.";
        return;
      }

      this.confirming = true;
      this.error = null;
      this.rows.forEach((row) => { row._error = null; });
      try {
        const rows = this.includedRows.map((row) => ({
          maintenance_dependency_id: Number(row.maintenance_dependency_id),
          responsible_staff_id: Number(row.responsible_staff_id),
          visit_date: row.visit_date,
          visit_time: row.visit_time,
          visit_type: row.visit_type,
          notes: row.notes || null,
        }));
        const response = await axios.post("/api/maintenance/visits/planning/confirm", {
          configuration: this.buildConfiguration(),
          idempotency_key: this.idempotencyKey,
          rows,
        });
        this.$emit("confirmed", response.data);
        this.isOpen = false;
      } catch (error) {
        const validationErrors = error.response?.data?.errors || {};
        let firstErrorRow = null;
        Object.entries(validationErrors).forEach(([key, messages]) => {
          const match = key.match(/^rows\.(\d+)/);
          if (match && this.includedRows[Number(match[1])]) {
            const row = this.includedRows[Number(match[1])];
            row._error = [].concat(messages).join(" ");
            firstErrorRow ||= row;
          }
        });
        if (firstErrorRow) {
          this.previewMode = "table";
          this.previewPage = Math.floor(this.rows.indexOf(firstErrorRow) / this.previewPageSize) + 1;
          this.$nextTick(() => this.$refs.planningContent?.scrollTo({ top: 0, behavior: "smooth" }));
        }
        this.error = this.formatError(error);
      } finally {
        this.confirming = false;
      }
    },
    removeRow(row) {
      row.included = false;
    },
    backToConfiguration() {
      this.step = 1;
      this.error = null;
      this.$nextTick(() => this.$refs.planningContent?.scrollTo({ top: 0, behavior: "auto" }));
    },
    restoreAllRows() {
      this.rows.forEach((row) => { row.included = true; });
    },
    updateRowStaff(row) {
      row.responsible = this.staffOptions.find((person) => person.value === Number(row.responsible_staff_id))?.label || "";
      row._error = null;
      row._editingStaff = false;
    },
    updateRowDependency(row) {
      row._error = null;
      row._editingDependency = false;
    },
    dependencyLabel(id) {
      return this.dependencyOptions.find((item) => item.value === Number(id))?.label || "Dependencia";
    },
    staffLabel(id) {
      return this.staffOptions.find((item) => item.value === Number(id))?.label || "Funcionario";
    },
    longDate(value) {
      if (!value) return "Sin fecha";
      const [year, month, day] = value.split("-").map(Number);
      return new Intl.DateTimeFormat("es-CL", { weekday: "short", day: "2-digit", month: "short", year: "numeric" })
        .format(new Date(year, month - 1, day, 12));
    },
    shortDate(value) {
      if (!value) return "-";
      const [year, month, day] = value.split("-");
      return `${day}-${month}-${year}`;
    },
    newIdempotencyKey() {
      if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
      return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        const value = character === "x" ? random : (random & 0x3) | 0x8;
        return value.toString(16);
      });
    },
    formatError(error) {
      const errors = error.response?.data?.errors;
      if (errors) return Object.values(errors).flat().join(" ");
      return error.response?.data?.message || error.message || "No fue posible procesar la planificación.";
    },
  },
};
</script>

<template>
  <BModal
    v-model="isOpen"
    modal-class="maintenance-planning-modal"
    dialog-class="maintenance-planning-dialog"
    body-class="p-0"
    size="xl"
    hide-header
    hide-footer
    centered
    scrollable
    teleport-to="body"
    lazy
    no-fade
  >
    <div class="planning-shell">
      <header class="planning-hero">
        <div class="planning-hero-icon"><i class="mdi mdi-calendar-multiple-check"></i></div>
        <div class="planning-hero-copy">
          <span>Herramienta exclusiva superadmin</span>
          <h4>Planificación automática de visitas</h4>
          <p>Construye una propuesta masiva, ajústala y confirma sólo cuando esté lista.</p>
        </div>
        <button class="planning-close" type="button" aria-label="Cerrar" @click="isOpen = false">
          <i class="mdi mdi-close"></i>
        </button>
      </header>

      <div class="planning-steps" aria-label="Etapas de planificación">
        <div :class="{ active: step === 1, complete: step > 1 }"><strong>1</strong><span>Configurar propuesta</span></div>
        <i class="mdi mdi-chevron-right"></i>
        <div :class="{ active: step === 2 }"><strong>2</strong><span>Revisar y confirmar</span></div>
      </div>

      <div ref="planningContent" class="planning-content">
        <BAlert v-if="error" show variant="danger" class="planning-alert">
          <i class="mdi mdi-alert-circle-outline"></i>{{ error }}
        </BAlert>

        <template v-if="step === 1">
          <div class="planning-config-layout">
            <div class="planning-sections">
              <section class="planning-section">
                <div class="planning-section-head">
                  <span class="tone-blue"><i class="mdi mdi-calendar-range"></i></span>
                  <div><h5>Período de trabajo</h5><p>Selecciona un mes, un año completo o un rango personalizado.</p></div>
                </div>
                <div class="planning-period-tabs">
                  <button v-for="option in [{ value: 'month', label: 'Mes' }, { value: 'year', label: 'Año completo' }, { value: 'custom', label: 'Personalizado' }]"
                    :key="option.value" type="button" :class="{ active: form.period_mode === option.value }" @click="form.period_mode = option.value">
                    {{ option.label }}
                  </button>
                </div>
                <div class="planning-grid planning-grid--period">
                  <label v-if="form.period_mode !== 'custom'" class="planning-field">
                    <span>Año</span>
                    <select v-model.number="form.year"><option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option></select>
                  </label>
                  <label v-if="form.period_mode === 'month'" class="planning-field">
                    <span>Mes</span>
                    <select v-model.number="form.month"><option v-for="(month, index) in months" :key="month" :value="index + 1">{{ month }}</option></select>
                  </label>
                  <label v-if="form.period_mode === 'custom'" class="planning-field"><span>Desde</span><input v-model="form.start_date" type="date" /></label>
                  <label v-if="form.period_mode === 'custom'" class="planning-field"><span>Hasta</span><input v-model="form.end_date" type="date" /></label>
                  <div v-if="form.period_mode !== 'custom'" class="planning-period-result">
                    <i class="mdi mdi-calendar-check-outline"></i>
                    <span>Período generado</span><strong>{{ shortDate(form.start_date) }} al {{ shortDate(form.end_date) }}</strong>
                  </div>
                </div>
              </section>

              <section class="planning-section">
                <div class="planning-section-head">
                  <span class="tone-indigo"><i class="mdi mdi-account-hard-hat-outline"></i></span>
                  <div><h5>Responsable y cobertura</h5><p>Define el funcionario base y las dependencias incluidas.</p></div>
                </div>
                <div class="planning-grid">
                  <div class="planning-field planning-field--wide">
                    <span>Funcionario base</span>
                    <Multiselect v-model="form.responsible_staff_id" :options="staffOptions" :searchable="true" :aria="{ 'aria-label': 'Funcionario base' }" placeholder="Buscar funcionario habilitado..." />
                    <small>Después podrás reasignar visitas individuales.</small>
                  </div>
                  <div class="planning-field planning-field--wide">
                    <span>Dependencias</span>
                    <Multiselect v-model="form.dependency_ids" mode="tags" :options="dependencyOptions" :searchable="true" :close-on-select="false" :aria="{ 'aria-label': 'Dependencias' }" placeholder="Todas las dependencias activas" />
                    <small v-if="!form.dependency_ids.length">Se incluirán automáticamente todas las dependencias activas.</small>
                    <small v-else>{{ form.dependency_ids.length }} dependencias seleccionadas.</small>
                  </div>
                </div>
              </section>

              <section class="planning-section">
                <div class="planning-section-head">
                  <span class="tone-green"><i class="mdi mdi-timeline-clock-outline"></i></span>
                  <div><h5>Frecuencia y capacidad</h5><p>Controla la unidad de tiempo, días y carga diaria.</p></div>
                </div>
                <div class="planning-grid planning-grid--four">
                  <label class="planning-field"><span>Frecuencia</span><select v-model="form.frequency"><option value="daily">Diaria</option><option value="weekly">Semanal</option><option value="biweekly">Quincenal</option><option value="monthly">Mensual</option></select><small>{{ frequencyHint }}</small></label>
                  <label class="planning-field"><span>Intervalo</span><input v-model.number="form.interval_value" type="number" min="1" max="12" /></label>
                  <label class="planning-field"><span>Visitas por dependencia</span><input v-model.number="form.visits_per_dependency" type="number" min="1" max="12" /></label>
                  <label class="planning-field"><span>Máximo diario</span><input v-model.number="form.max_visits_per_day" type="number" min="1" max="12" /></label>
                </div>
                <div v-if="form.frequency !== 'monthly'" class="planning-weekdays">
                  <span>Días habilitados</span>
                  <div><button v-for="day in weekdayOptions" :key="day.value" type="button" :class="{ active: form.weekdays.includes(day.value) }" @click="toggleWeekday(day.value)">{{ day.label }}</button></div>
                </div>
                <div v-else class="planning-grid planning-grid--four planning-month-day">
                  <label class="planning-field"><span>Día del mes</span><input v-model.number="form.month_day" type="number" min="1" max="31" /><small>Se ajusta al último día disponible.</small></label>
                </div>
                <div class="planning-grid planning-grid--four planning-time-grid">
                  <label class="planning-field"><span>Hora inicial</span><input v-model="form.start_time" type="time" /></label>
                  <label class="planning-field"><span>Separación</span><select v-model.number="form.slot_minutes"><option :value="30">30 minutos</option><option :value="45">45 minutos</option><option :value="60">60 minutos</option><option :value="90">90 minutos</option><option :value="120">120 minutos</option></select></label>
                  <label class="planning-field"><span>Tipo de visita</span><select v-model="form.visit_type"><option v-for="type in catalogs.visit_types" :key="type" :value="type">{{ type }}</option></select></label>
                  <label class="planning-field"><span>Nota general</span><input v-model="form.notes" type="text" maxlength="2000" /></label>
                </div>
                <div class="planning-rules">
                  <label><input v-model="form.exclude_weekends" type="checkbox" /><span><strong>Excluir fines de semana</strong><small>No utilizar sábados ni domingos.</small></span></label>
                  <label><input v-model="form.exclude_non_school_days" type="checkbox" /><span><strong>Excluir días no lectivos</strong><small>Respeta el calendario escolar confirmado.</small></span></label>
                </div>
              </section>
            </div>

            <aside class="planning-summary-card">
              <span class="planning-summary-kicker">Resumen estimado</span>
              <strong>{{ requestedVisits }}</strong>
              <p>visitas solicitadas</p>
              <dl>
                <div><dt>Dependencias</dt><dd>{{ selectedDependencyCount }}</dd></div>
                <div><dt>Repeticiones</dt><dd>{{ form.visits_per_dependency }}</dd></div>
                <div><dt>Capacidad diaria</dt><dd>{{ form.max_visits_per_day }}</dd></div>
                <div><dt>Período</dt><dd>{{ shortDate(form.start_date) }}<br>{{ shortDate(form.end_date) }}</dd></div>
              </dl>
              <div class="planning-summary-note"><i class="mdi mdi-shield-check-outline"></i><span>La propuesta no modifica visitas existentes. Los conflictos se evitan antes de confirmar.</span></div>
            </aside>
          </div>
        </template>

        <template v-else>
          <div class="planning-preview-head">
            <div><span>Propuesta generada</span><h5>Revisa cada visita antes de crearla</h5><p>Puedes cambiar fecha, hora, dependencia, tipo o funcionario.</p></div>
            <div class="planning-preview-toggle"><button type="button" :class="{ active: previewMode === 'table' }" @click="previewMode = 'table'"><i class="mdi mdi-table"></i>Tabla</button><button type="button" :class="{ active: previewMode === 'timeline' }" @click="previewMode = 'timeline'"><i class="mdi mdi-calendar-month-outline"></i>Agenda</button></div>
          </div>

          <div class="planning-metrics">
            <article><span class="metric-blue"><i class="mdi mdi-calendar-check"></i></span><div><small>Incluidas</small><strong>{{ includedRows.length }}</strong></div></article>
            <article><span class="metric-green"><i class="mdi mdi-map-marker-multiple"></i></span><div><small>Dependencias</small><strong>{{ summary.dependencies || 0 }}</strong></div></article>
            <article><span class="metric-indigo"><i class="mdi mdi-calendar-range"></i></span><div><small>Días usados</small><strong>{{ summary.scheduled_days || 0 }}</strong></div></article>
            <article><span class="metric-amber"><i class="mdi mdi-shield-check"></i></span><div><small>Choques evitados</small><strong>{{ summary.conflicts_avoided || 0 }}</strong></div></article>
          </div>

          <div v-if="warnings.length" class="planning-warnings"><div v-for="warning in warnings" :key="warning"><i class="mdi mdi-information-outline"></i><span>{{ warning }}</span></div></div>

          <div class="planning-preview-toolbar">
            <span>{{ rows.length - includedRows.length }} excluidas de la confirmación</span>
            <button v-if="rows.length !== includedRows.length" type="button" @click="restoreAllRows"><i class="mdi mdi-restore"></i>Restaurar todas</button>
          </div>

          <div v-if="previewMode === 'table'" class="planning-table-wrap">
            <table class="planning-table">
              <colgroup>
                <col class="planning-col-include" />
                <col class="planning-col-schedule" />
                <col class="planning-col-dependency" />
                <col class="planning-col-staff" />
                <col class="planning-col-type" />
                <col class="planning-col-action" />
              </colgroup>
              <thead><tr><th class="planning-cell-center" scope="col">Incluir</th><th scope="col">Fecha y hora</th><th scope="col">Dependencia</th><th scope="col">Funcionario</th><th scope="col">Tipo</th><th class="planning-cell-center" scope="col">Acción</th></tr></thead>
              <tbody>
                <tr v-for="row in displayedRows" :key="row.row_key" :class="{ excluded: !row.included, invalid: row._error }">
                  <td data-label="Incluir"><label class="planning-check"><input v-model="row.included" type="checkbox" /><span></span></label></td>
                  <td data-label="Fecha y hora"><div class="planning-inline-fields"><input v-model="row.visit_date" type="date" aria-label="Fecha de visita" @input="row._error = null" /><input v-model="row.visit_time" type="time" aria-label="Hora de visita" @input="row._error = null" /></div><small v-if="row._error" class="planning-row-error">{{ row._error }}</small></td>
                  <td data-label="Dependencia">
                    <button v-if="!row._editingDependency" class="planning-row-value" type="button" @click="row._editingDependency = true"><span>{{ dependencyLabel(row.maintenance_dependency_id) }}</span><i class="mdi mdi-pencil-outline"></i></button>
                    <Multiselect v-else v-model="row.maintenance_dependency_id" class="planning-row-select" :options="dependencyOptions" :searchable="true" :can-clear="false" :aria="{ 'aria-label': `Dependencia de ${row.visit_date}` }" @change="updateRowDependency(row)" />
                  </td>
                  <td data-label="Funcionario">
                    <button v-if="!row._editingStaff" class="planning-row-value" type="button" @click="row._editingStaff = true"><span>{{ staffLabel(row.responsible_staff_id) }}</span><i class="mdi mdi-pencil-outline"></i></button>
                    <Multiselect v-else v-model="row.responsible_staff_id" class="planning-row-select" :options="staffOptions" :searchable="true" :can-clear="false" :aria="{ 'aria-label': `Funcionario de ${row.visit_date}` }" @change="updateRowStaff(row)" />
                  </td>
                  <td data-label="Tipo"><select v-model="row.visit_type"><option v-for="type in catalogs.visit_types" :key="type" :value="type">{{ type }}</option></select></td>
                  <td><button class="planning-remove" type="button" title="Excluir visita" aria-label="Excluir visita" @click="removeRow(row)"><i class="mdi mdi-close"></i></button></td>
                </tr>
              </tbody>
            </table>
            <div v-if="!rows.length" class="planning-empty"><i class="mdi mdi-calendar-remove-outline"></i><strong>No se generaron visitas</strong><span>Ajusta el período o la capacidad y vuelve a generar.</span></div>
            <div v-if="previewPageCount > 1" class="planning-table-pagination">
              <span>Mostrando {{ (previewPage - 1) * previewPageSize + 1 }}–{{ Math.min(previewPage * previewPageSize, rows.length) }} de {{ rows.length }}</span>
              <div>
                <button type="button" :disabled="previewPage <= 1" @click="previewPage--"><i class="mdi mdi-chevron-left"></i>Anterior</button>
                <strong>{{ previewPage }} / {{ previewPageCount }}</strong>
                <button type="button" :disabled="previewPage >= previewPageCount" @click="previewPage++">Siguiente<i class="mdi mdi-chevron-right"></i></button>
              </div>
            </div>
          </div>

          <div v-else class="planning-timeline">
            <article v-for="group in proposalGroups" :key="group.date">
              <header><div><span>{{ group.date.slice(8, 10) }}</span><small>{{ group.label }}</small></div><strong>{{ group.rows.length }} visitas</strong></header>
              <div class="planning-timeline-visits">
                <button v-for="row in group.rows" :key="row.row_key" type="button" @click="previewMode = 'table'">
                  <span>{{ row.visit_time }}</span><div><strong>{{ dependencyLabel(row.maintenance_dependency_id) }}</strong><small>{{ staffLabel(row.responsible_staff_id) }} · {{ row.visit_type }}</small></div><i class="mdi mdi-pencil-outline"></i>
                </button>
              </div>
            </article>
            <div v-if="!proposalGroups.length" class="planning-empty"><i class="mdi mdi-calendar-remove-outline"></i><strong>No quedan visitas incluidas</strong></div>
          </div>
        </template>
      </div>

      <footer class="planning-footer">
        <button class="planning-button planning-button--secondary" type="button" @click="step === 1 ? (isOpen = false) : backToConfiguration()"><i v-if="step === 2" class="mdi mdi-arrow-left"></i>{{ step === 1 ? "Cancelar" : "Volver a configurar" }}</button>
        <div><span v-if="step === 2">Se crearán <strong>{{ includedRows.length }}</strong> visitas en estado Programada.</span><button v-if="step === 1" class="planning-button planning-button--primary" type="button" :disabled="loading" @click="generatePreview"><i class="mdi" :class="loading ? 'mdi-loading mdi-spin' : 'mdi-auto-fix'"></i>{{ loading ? "Generando..." : "Generar propuesta" }}</button><button v-else class="planning-button planning-button--confirm" type="button" :disabled="confirming || !includedRows.length" @click="confirmPlan"><i class="mdi" :class="confirming ? 'mdi-loading mdi-spin' : 'mdi-calendar-check'"></i>{{ confirming ? "Confirmando..." : "Confirmar planificación" }}</button></div>
      </footer>
    </div>
  </BModal>
</template>

<style scoped>
.planning-shell { color: #344054; background: #f6f8fc; min-height: 620px; }
.planning-hero { display: flex; align-items: center; gap: 16px; padding: 22px 26px; color: #fff; background: linear-gradient(128deg, #263c8f 0%, #4766d7 58%, #6284ed 100%); }
.planning-hero-icon { display: grid; place-items: center; width: 52px; height: 52px; flex: 0 0 52px; border-radius: 15px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.24); font-size: 27px; }
.planning-hero-copy { flex: 1; min-width: 0; }
.planning-hero-copy > span { display: block; margin-bottom: 3px; color: #dfe7ff; font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
.planning-hero h4 { margin: 0; color: #fff; font-size: 22px; font-weight: 750; }
.planning-hero p { margin: 4px 0 0; color: #e8edff; font-size: 13px; }
.planning-close { display: grid; place-items: center; width: 38px; height: 38px; border: 0; border-radius: 10px; color: #fff; background: rgba(255,255,255,.12); font-size: 21px; }
.planning-steps { display: flex; align-items: center; justify-content: center; gap: 18px; padding: 14px 24px; border-bottom: 1px solid #e5e9f2; background: #fff; }
.planning-steps > div { display: flex; align-items: center; gap: 9px; color: #98a2b3; font-size: 13px; font-weight: 650; }
.planning-steps strong { display: grid; place-items: center; width: 27px; height: 27px; border-radius: 50%; background: #eef1f6; }
.planning-steps .active { color: #3854c5; }.planning-steps .active strong,.planning-steps .complete strong { color: #fff; background: #4f69d8; }.planning-steps > i { color: #c6ccd8; }
.planning-content { padding: 20px 24px 24px; max-height: calc(100vh - 260px); overflow-y: auto; }
.planning-alert { display: flex; align-items: flex-start; gap: 8px; border-radius: 10px; font-size: 13px; }
.planning-config-layout { display: grid; grid-template-columns: minmax(0, 1fr) 238px; gap: 18px; align-items: start; }
.planning-sections { display: grid; gap: 14px; }
.planning-section { padding: 18px; border: 1px solid #e1e6f0; border-radius: 14px; background: #fff; box-shadow: 0 3px 12px rgba(31,49,94,.035); }
.planning-section-head { display: flex; gap: 11px; align-items: center; margin-bottom: 15px; }
.planning-section-head > span { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; font-size: 18px; }.tone-blue{color:#3159c7;background:#eef4ff}.tone-indigo{color:#5b45c6;background:#f1efff}.tone-green{color:#087b59;background:#e9f9f3}
.planning-section h5 { margin: 0; color: #273044; font-size: 15px; font-weight: 750; }.planning-section p { margin: 2px 0 0; color: #7a8498; font-size: 12px; }
.planning-period-tabs { display: inline-flex; gap: 4px; margin-bottom: 14px; padding: 4px; border: 1px solid #dce3ef; border-radius: 10px; background: #f7f9fc; }
.planning-period-tabs button,.planning-preview-toggle button { min-height: 34px; padding: 0 13px; border: 0; border-radius: 7px; color: #687286; background: transparent; font-size: 12px; font-weight: 650; }.planning-period-tabs button.active,.planning-preview-toggle button.active { color: #3152c9; background: #fff; box-shadow: 0 1px 5px rgba(35,55,110,.12); }
.planning-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; }.planning-grid--period { grid-template-columns: 150px 190px minmax(0,1fr); }.planning-grid--four { grid-template-columns: repeat(4,minmax(0,1fr)); }.planning-field--wide { min-width: 0; }
.planning-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }.planning-field > span,.planning-weekdays > span { color: #566176; font-size: 11px; font-weight: 750; text-transform: uppercase; letter-spacing: .035em; }.planning-field small { color: #8992a5; font-size: 11px; line-height: 1.25; }
.planning-field input,.planning-field select,.planning-table input,.planning-table select { width: 100%; min-height: 40px; padding: 0 11px; border: 1px solid #ced6e3; border-radius: 8px; color: #344054; background: #fff; font-size: 13px; outline: none; }.planning-field input:focus,.planning-field select:focus,.planning-table input:focus,.planning-table select:focus { border-color: #6b82df; box-shadow: 0 0 0 3px rgba(79,105,216,.11); }
:deep(.planning-field .multiselect) { min-height: 40px; border-color: #ced6e3; border-radius: 8px; font-size: 13px; }:deep(.planning-field .multiselect.is-active) { border-color: #6b82df; box-shadow: 0 0 0 3px rgba(79,105,216,.11); }:deep(.planning-field .multiselect-tags-search) { border: 0; box-shadow: none; min-height: 26px; }:deep(.planning-field .multiselect-tag) { background: #4f69d8; }
.planning-period-result { display: flex; align-items: center; gap: 9px; align-self: end; min-height: 40px; padding: 7px 12px; border-radius: 9px; color: #687286; background: #f3f6fb; font-size: 11px; }.planning-period-result i { color: #4f69d8; font-size: 18px; }.planning-period-result span { flex: 1; }.planning-period-result strong { color: #344054; font-size: 12px; }
.planning-weekdays { display: flex; align-items: center; gap: 16px; margin-top: 14px; }.planning-weekdays > div { display: flex; gap: 6px; flex-wrap: wrap; }.planning-weekdays button { width: 40px; height: 34px; border: 1px solid #d5dce8; border-radius: 8px; color: #667085; background: #fff; font-size: 11px; font-weight: 700; }.planning-weekdays button.active { color: #fff; background: #4f69d8; border-color: #4f69d8; }
.planning-month-day,.planning-time-grid { margin-top: 14px; }.planning-rules { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 10px; margin-top: 14px; }.planning-rules label { display: flex; align-items: flex-start; gap: 9px; padding: 11px; border: 1px solid #e2e7f0; border-radius: 9px; background: #fafbfe; }.planning-rules input { margin-top: 3px; accent-color: #4f69d8; }.planning-rules span { display: grid; gap: 2px; }.planning-rules strong { color: #465168; font-size: 12px; }.planning-rules small { color: #8791a4; font-size: 11px; }
.planning-summary-card { position: sticky; top: 0; padding: 20px; border-radius: 15px; color: #fff; background: linear-gradient(155deg,#263d90,#536fdc); box-shadow: 0 12px 26px rgba(45,67,151,.2); }.planning-summary-kicker { color: #d8e1ff; font-size: 10px; font-weight: 750; text-transform: uppercase; letter-spacing: .07em; }.planning-summary-card > strong { display: block; margin-top: 10px; font-size: 38px; line-height: 1; }.planning-summary-card > p { margin: 4px 0 18px; color: #dfe6ff; font-size: 12px; }.planning-summary-card dl { margin: 0; border-top: 1px solid rgba(255,255,255,.18); }.planning-summary-card dl div { display: flex; justify-content: space-between; gap: 8px; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,.13); }.planning-summary-card dt { color: #dbe3ff; font-size: 11px; font-weight: 500; }.planning-summary-card dd { margin: 0; text-align: right; font-size: 11px; font-weight: 750; }.planning-summary-note { display: flex; gap: 8px; margin-top: 15px; padding: 10px; border-radius: 9px; background: rgba(255,255,255,.1); color: #edf1ff; font-size: 10px; line-height: 1.4; }.planning-summary-note i { font-size: 17px; }
.planning-preview-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 14px; margin-bottom: 15px; }.planning-preview-head span { color: #536ed6; font-size: 10px; font-weight: 750; text-transform: uppercase; letter-spacing: .07em; }.planning-preview-head h5 { margin: 3px 0; color: #273044; font-size: 18px; font-weight: 750; }.planning-preview-head p { margin: 0; color: #7c8699; font-size: 12px; }.planning-preview-toggle { display: flex; gap: 4px; padding: 4px; border: 1px solid #dce3ef; border-radius: 10px; background: #fff; }.planning-preview-toggle button { display: flex; align-items: center; gap: 6px; }
.planning-metrics { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 10px; margin-bottom: 12px; }.planning-metrics article { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border: 1px solid #e2e7f0; border-radius: 11px; background: #fff; }.planning-metrics article > span { display: grid; place-items: center; width: 35px; height: 35px; border-radius: 9px; font-size: 18px; }.metric-blue{color:#3159c7;background:#eef4ff}.metric-green{color:#087b59;background:#e9f9f3}.metric-indigo{color:#5b45c6;background:#f1efff}.metric-amber{color:#b26506;background:#fff5dd}.planning-metrics div { display: grid; }.planning-metrics small { color: #7c8699; font-size: 10px; text-transform: uppercase; }.planning-metrics strong { color: #283247; font-size: 20px; line-height: 1.1; }
.planning-warnings { display: grid; gap: 5px; margin-bottom: 12px; }.planning-warnings div { display: flex; gap: 7px; padding: 8px 10px; border-radius: 8px; color: #87590b; background: #fff8e8; font-size: 11px; }
.planning-preview-toolbar { display: flex; justify-content: space-between; align-items: center; margin: 0 0 8px; color: #818a9c; font-size: 11px; }.planning-preview-toolbar button { border: 0; color: #4561cd; background: transparent; font-size: 11px; font-weight: 650; }
.planning-table-wrap { border: 1px solid #dce3ee; border-radius: 12px; background: #fff; overflow: auto; box-shadow: 0 5px 18px rgba(38,55,98,.035); }.planning-table { width: 100%; min-width: 1050px; table-layout: fixed; border-collapse: separate; border-spacing: 0; }.planning-col-include { width: 64px; }.planning-col-schedule { width: 274px; }.planning-col-dependency { width: 254px; }.planning-col-staff { width: 218px; }.planning-col-type { width: 154px; }.planning-col-action { width: 60px; }.planning-table th { position: sticky; top: 0; z-index: 2; padding: 11px 9px; color: #687286; text-align: left; background: #f4f6fa; border-bottom: 1px solid #dce3ee; font-size: 10px; text-transform: uppercase; letter-spacing: .045em; }.planning-table td { min-width: 0; padding: 9px 7px; border-bottom: 1px solid #edf0f5; vertical-align: middle; }.planning-table tr:last-child td { border-bottom: 0; }.planning-table tbody tr { transition: background-color .16s ease; }.planning-table tbody tr:hover { background: #f8faff; }.planning-table tr.excluded { opacity: .48; background: #f7f8fa; }.planning-table tr.invalid { background: #fff4f4; }.planning-table .planning-cell-center,.planning-table td:first-child { text-align: center; }.planning-inline-fields { display: grid; min-width: 0; grid-template-columns: minmax(0,1.35fr) minmax(0,1fr); gap: 7px; }.planning-inline-fields input { min-width: 0; padding-right: 7px; padding-left: 9px; }.planning-row-error { display: block; margin-top: 4px; color: #c24141; font-size: 10px; line-height: 1.3; }.planning-check { display: inline-grid; place-items: center; min-width: 34px; min-height: 34px; margin: 0; cursor: pointer; }.planning-check input { display: none; }.planning-check span { display: inline-block; width: 19px; height: 19px; border: 2px solid #b7c0d1; border-radius: 5px; transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease; }.planning-check:hover span { border-color: #7084df; }.planning-check input:checked + span { background: #4f69d8; border-color: #4f69d8; box-shadow: inset 0 0 0 3px #fff; }.planning-remove { display: grid; place-items: center; width: 32px; height: 32px; margin: 0 auto; border: 0; border-radius: 8px; color: #a45151; background: #fff1f1; transition: color .15s ease, background-color .15s ease; }.planning-remove:hover { color: #8d3030; background: #ffe3e3; }
:deep(.planning-row-select.multiselect) { min-height: 40px; border-color: #ced6e3; border-radius: 8px; font-size: 12px; }
:deep(.planning-row-select .multiselect-wrapper) { min-height: 38px; }
:deep(.planning-row-select .multiselect-single-label) { padding-right: 30px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.planning-row-value { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; min-width: 0; min-height: 40px; padding: 0 10px; border: 1px solid #d6dde8; border-radius: 8px; color: #465168; text-align: left; background: #fff; font-size: 12px; transition: border-color .15s ease, box-shadow .15s ease; }.planning-row-value:hover,.planning-row-value:focus-visible { border-color: #8293dd; box-shadow: 0 0 0 3px rgba(79,105,216,.09); outline: 0; }.planning-row-value span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.planning-row-value i { flex: 0 0 auto; color: #6c7ed0; }
.planning-table-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 13px; border-top: 1px solid #e3e8f0; color: #7b8598; font-size: 11px; }.planning-table-pagination > div { display: flex; align-items: center; gap: 8px; }.planning-table-pagination button { display: inline-flex; align-items: center; gap: 4px; min-height: 30px; padding: 0 9px; border: 1px solid #d2d9e5; border-radius: 7px; color: #536078; background: #fff; font-size: 11px; font-weight: 650; }.planning-table-pagination button:disabled { opacity: .45; }.planning-table-pagination strong { color: #3d485e; }
.planning-timeline { display: grid; gap: 11px; }.planning-timeline article { display: grid; grid-template-columns: 180px minmax(0,1fr); border: 1px solid #dfe5ef; border-radius: 12px; background: #fff; overflow: hidden; }.planning-timeline article > header { display: flex; justify-content: space-between; flex-direction: column; padding: 14px; color: #fff; background: linear-gradient(145deg,#334d9e,#5671dc); }.planning-timeline header div { display: flex; align-items: center; gap: 10px; }.planning-timeline header span { font-size: 26px; font-weight: 750; }.planning-timeline header small { color: #e0e7ff; font-size: 11px; text-transform: capitalize; }.planning-timeline header > strong { color: #dfe6ff; font-size: 11px; }.planning-timeline-visits { display: grid; gap: 1px; background: #e9edf4; }.planning-timeline-visits button { display: grid; grid-template-columns: 56px minmax(0,1fr) 25px; gap: 9px; align-items: center; padding: 11px 13px; border: 0; text-align: left; background: #fff; }.planning-timeline-visits button > span { color: #3e5fce; font-weight: 750; }.planning-timeline-visits button div { display: grid; min-width: 0; }.planning-timeline-visits strong { overflow: hidden; color: #354057; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }.planning-timeline-visits small { color: #8690a3; font-size: 10px; }.planning-timeline-visits i { color: #8a94a7; }
.planning-empty { display: grid; place-items: center; gap: 5px; padding: 44px; color: #8b95a8; text-align: center; }.planning-empty i { font-size: 34px; }.planning-empty strong { color: #4b566b; }
.planning-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 15px 24px; border-top: 1px solid #dfe5ef; background: #fff; }.planning-footer > div { display: flex; align-items: center; gap: 15px; }.planning-footer > div > span { color: #697386; font-size: 11px; }.planning-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 41px; padding: 0 17px; border-radius: 9px; font-size: 12px; font-weight: 700; }.planning-button--secondary { color: #59657a; background: #fff; border: 1px solid #c9d1df; }.planning-button--primary { color: #fff; background: #4f69d8; border: 1px solid #4f69d8; }.planning-button--confirm { color: #fff; background: #118361; border: 1px solid #118361; }.planning-button:disabled { opacity: .6; cursor: not-allowed; }

@media (max-width: 992px) { .planning-config-layout { grid-template-columns: 1fr; }.planning-summary-card { position: static; }.planning-grid--four { grid-template-columns: repeat(2,minmax(0,1fr)); }.planning-metrics { grid-template-columns: repeat(2,minmax(0,1fr)); } }
@media (max-width: 768px) {
  :deep(.maintenance-planning-dialog) { width: 100%; max-width: none; height: 100%; margin: 0; }
  :deep(.maintenance-planning-dialog .modal-content) { height: 100%; border: 0; border-radius: 0; }
  :deep(.maintenance-planning-dialog .modal-body) { display: flex; flex: 1 1 auto; min-height: 0; overflow: hidden; }
  .planning-shell { display: flex; flex: 1 1 auto; flex-direction: column; width: 100%; height: 100%; min-height: 0; }.planning-hero,.planning-steps,.planning-footer { flex: 0 0 auto; }.planning-hero { align-items: flex-start; padding: 17px 15px; }.planning-hero-icon { width: 42px; height: 42px; flex-basis: 42px; font-size: 22px; }.planning-hero h4 { font-size: 17px; }.planning-hero p { font-size: 11px; }.planning-steps { gap: 8px; padding: 11px; }.planning-steps > div span { display: none; }.planning-content { flex: 1 1 auto; min-height: 0; padding: 13px; max-height: none; }.planning-config-layout,.planning-grid,.planning-grid--period,.planning-grid--four,.planning-rules { grid-template-columns: 1fr; }.planning-period-tabs { display: grid; grid-template-columns: repeat(3,1fr); width: 100%; }.planning-period-tabs button { padding: 0 7px; }.planning-weekdays { align-items: flex-start; flex-direction: column; gap: 8px; }.planning-weekdays > div { width: 100%; }.planning-weekdays button { flex: 1; min-width: 36px; }.planning-summary-card { display: none; }.planning-preview-head { align-items: stretch; flex-direction: column; }.planning-preview-toggle { align-self: flex-start; }.planning-metrics { grid-template-columns: repeat(2,minmax(0,1fr)); }.planning-metrics article { padding: 9px; }.planning-table { min-width: 0; }.planning-table colgroup { display: none; }.planning-table thead { display: none; }.planning-table tbody,.planning-table tr,.planning-table td { display: block; width: 100% !important; }.planning-table tr { position: relative; padding: 52px 12px 9px; border-bottom: 1px solid #e2e7f0; }.planning-table td { padding: 4px 0; border: 0; }.planning-table td::before { content: attr(data-label); display: block; margin-bottom: 3px; color: #8b94a5; font-size: 9px; font-weight: 750; text-transform: uppercase; }.planning-table td:first-child { position: absolute; top: 8px; left: 9px; display: flex; align-items: center; gap: 7px; width: 110px !important; text-align: left; }.planning-table td:first-child::before { content: 'Incluir'; display: block; flex: 0 0 auto; margin: 0; }.planning-table td:first-child .planning-check { min-width: 32px; min-height: 32px; }.planning-table td:last-child { position: absolute; right: 10px; top: 8px; width: 32px !important; }.planning-table select { padding-right: 38px; }.planning-table-pagination { align-items: stretch; flex-direction: column; }.planning-table-pagination > div { justify-content: space-between; }.planning-timeline article { grid-template-columns: 1fr; }.planning-timeline article > header { gap: 8px; }.planning-footer { align-items: stretch; padding: 10px 13px; }.planning-footer,.planning-footer > div { flex-direction: column; gap: 7px; }.planning-footer > div > span { text-align: center; }.planning-button { width: 100%; min-height: 38px; }
}
</style>

<style>
@media (max-width: 768px) {
  .maintenance-planning-modal .maintenance-planning-dialog {
    width: 100%;
    max-width: none;
    height: 100%;
    margin: 0;
  }

  .maintenance-planning-modal .maintenance-planning-dialog .modal-content {
    height: 100%;
    border: 0;
    border-radius: 0;
  }

  .maintenance-planning-modal .maintenance-planning-dialog .modal-body {
    display: flex;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden !important;
  }
}
</style>
