<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      selectionLoading: false,
      saving: false,
      error: null,
      catalogs: {
        priorities: [],
        incident_statuses: [],
        responsible_users: [],
        capabilities: {},
      },
      filters: {
        search: "",
        priority: null,
        status_id: null,
        responsible_user_id: null,
        from: "",
        to: "",
        pending_only: true,
      },
      incidents: [],
      summary: { total: 0, open: 0, critical: 0, overdue: 0, unassigned: 0 },
      pagination: { current_page: 1, last_page: 1, total: 0, per_page: 15, from: null, to: null },
      selectedIncident: null,
      updateForm: {
        priority: "media",
        status_id: null,
        current_responsible_user_id: null,
        assignee_user_ids: [],
        response_due_at: "",
        response_summary: "",
        closure_evidence_notes: "",
        comment: "",
        evidenceFiles: [],
      },
    };
  },
  computed: {
    priorityOptions() {
      return [{ value: null, label: "Todas" }].concat(
        (this.catalogs.priorities || []).map((item) => ({ value: item.value, label: item.label }))
      );
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.incident_statuses || []).map((item) => ({ value: item.id, label: item.name }))
      );
    },
    responsibleOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.responsible_users || []).map((item) => ({
          value: item.id,
          label: item.staff?.full_name ? `${item.staff.full_name} (${item.name})` : item.name,
        }))
      );
    },
    canManageIncidents() {
      return Boolean(this.catalogs.capabilities?.can_manage_incidents);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    canUpdateSelected() {
      if (!this.selectedIncident) return false;
      const userId = Number(this.catalogs.current_user?.id || 0);
      return this.canManageIncidents
        || Number(this.selectedIncident.current_responsible_user_id || 0) === userId
        || (this.selectedIncident.assignments || []).some((assignment) => assignment.is_current !== false && Number(assignment.user_id || assignment.user?.id) === userId);
    },
    activeFilterCount() {
      return [this.filters.search, this.filters.priority, this.filters.status_id, this.filters.responsible_user_id, this.filters.from, this.filters.to]
        .filter((value) => String(value || "").trim() !== "").length;
    },
    rangeLabel() {
      if (!this.pagination.total) return "Sin incidencias para esta selección";
      return `${this.pagination.from || 1}–${this.pagination.to || this.incidents.length} de ${this.pagination.total}`;
    },
    selectedEvidenceCount() {
      return (this.selectedIncident?.evidences || []).length;
    },
  },
  async mounted() {
    await Promise.all([this.loadCatalogs(), this.loadIncidents()]);
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/security/catalogs");
      this.catalogs = response.data;
    },
    async loadIncidents(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/security/incidents", {
          params: { page, ...this.filters },
        });
        this.incidents = response.data.data || [];
        this.summary = response.data.summary || this.summary;
        this.pagination = {
          current_page: response.data.current_page || 1,
          last_page: response.data.last_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || this.pagination.per_page,
          from: response.data.from,
          to: response.data.to,
        };
        if (this.selectedIncident && !this.incidents.some((incident) => incident.id === this.selectedIncident.id)) {
          this.selectedIncident = null;
        }
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openIncident(incident) {
      this.selectionLoading = true;
      this.error = null;
      try {
        const response = await axios.get(`/api/security/incidents/${incident.id}`);
        this.selectedIncident = response.data.data;
        this.updateForm = {
          priority: this.selectedIncident.priority,
          status_id: this.selectedIncident.status_id,
          current_responsible_user_id: this.selectedIncident.current_responsible_user_id,
          assignee_user_ids: (this.selectedIncident.assignments || []).filter((item) => item.is_current !== false).map((item) => item.user_id || item.user?.id),
          response_due_at: this.toInputDateTime(this.selectedIncident.response_due_at),
          response_summary: this.selectedIncident.response_summary || "",
          closure_evidence_notes: this.selectedIncident.closure_evidence_notes || "",
          comment: "",
          evidenceFiles: [],
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.selectionLoading = false;
      }
    },
    onEvidenceChange(event) {
      this.updateForm.evidenceFiles = Array.from(event.target.files || []);
    },
    async saveIncident() {
      if (!this.selectedIncident) return;
      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        formData.append("priority", this.updateForm.priority);
        if (this.updateForm.status_id) formData.append("status_id", this.updateForm.status_id);
        if (this.updateForm.current_responsible_user_id) formData.append("current_responsible_user_id", this.updateForm.current_responsible_user_id);
        if (this.updateForm.response_due_at) formData.append("response_due_at", this.updateForm.response_due_at);
        if (this.updateForm.response_summary) formData.append("response_summary", this.updateForm.response_summary);
        if (this.updateForm.closure_evidence_notes) formData.append("closure_evidence_notes", this.updateForm.closure_evidence_notes);
        this.ensureArray(this.updateForm.assignee_user_ids).forEach((id) => formData.append("assignee_user_ids[]", id));
        (this.updateForm.evidenceFiles || []).forEach((file) => formData.append("evidence_files[]", file));
        formData.append("_method", "PUT");

        await axios.post(`/api/security/incidents/${this.selectedIncident.id}`, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        await this.openIncident(this.selectedIncident);
        await this.loadIncidents(this.pagination.current_page);
        await this.showSuccess("Novedad actualizada correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.saving = false;
      }
    },
    async saveFollowUp() {
      if (!this.selectedIncident || !this.updateForm.comment.trim()) {
        this.error = "Debes ingresar un comentario de seguimiento.";
        await this.showWarning(this.error);
        return;
      }

      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        formData.append("comment", this.updateForm.comment);
        if (this.updateForm.status_id) formData.append("status_id", this.updateForm.status_id);
        if (this.updateForm.current_responsible_user_id) formData.append("assigned_to_user_id", this.updateForm.current_responsible_user_id);
        (this.updateForm.evidenceFiles || []).forEach((file) => formData.append("evidence_files[]", file));

        await axios.post(`/api/security/incidents/${this.selectedIncident.id}/comments`, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        await this.openIncident(this.selectedIncident);
        await this.loadIncidents(this.pagination.current_page);
        await this.showSuccess("Seguimiento registrado correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.saving = false;
      }
    },
    async exportPdf() {
      const pdfMake = await getPdfMake();
      pdfMake.createPdf({
        pageOrientation: "landscape",
        content: [
          { text: "Incidencias nocturnas", style: "title" },
          { text: `Exportación de ${this.incidents.length} registros visibles · ${new Date().toLocaleString("es-CL")}`, style: "subtitle" },
          {
            table: {
              headerRows: 1,
              body: [
                ["Fecha", "Turno", "Sector", "Título", "Prioridad", "Estado", "Responsable"],
                ...(this.incidents || []).map((item) => [
                  this.formatDateTime(item.created_at),
                  item.shift?.staff?.full_name || "-",
                  item.sector_name || item.shift?.coverage_label || "-",
                  item.title,
                  item.priority,
                  item.status?.name || "-",
                  item.current_responsible?.name || "-",
                ]),
              ],
            },
            layout: "lightHorizontalLines",
          },
        ],
        styles: {
          title: { fontSize: 16, bold: true, margin: [0, 0, 0, 10] },
          subtitle: { fontSize: 8, color: "#667085", margin: [0, -6, 0, 10] },
        },
        defaultStyle: { fontSize: 9 },
      }).download(`incidencias-nocturnas-${new Date().toISOString().slice(0, 10)}.pdf`);
    },
    selectQueue(pendingOnly) {
      this.filters.pending_only = pendingOnly;
      this.loadIncidents(1);
    },
    clearFilters() {
      this.filters = {
        search: "",
        priority: null,
        status_id: null,
        responsible_user_id: null,
        from: "",
        to: "",
        pending_only: this.filters.pending_only,
      };
      this.loadIncidents(1);
    },
    priorityLabel(priority) {
      return (this.catalogs.priorities || []).find((item) => item.value === priority)?.label || this.humanize(priority);
    },
    priorityClass(priority) {
      return {
        critica: "incident-badge--critical",
        alta: "incident-badge--high",
        media: "incident-badge--medium",
        baja: "incident-badge--low",
      }[priority] || "incident-badge--low";
    },
    statusClass(incident) {
      if (incident?.status?.is_closed) return "incident-badge--closed";
      if (incident?.status?.code === "en_revision") return "incident-badge--review";
      if (incident?.status?.code === "derivada") return "incident-badge--derived";
      return "incident-badge--open";
    },
    dueState(incident) {
      if (!incident?.response_due_at || incident?.status?.is_closed) return null;
      const due = new Date(String(incident.response_due_at).replace(" ", "T"));
      if (Number.isNaN(due.getTime())) return null;
      return due.getTime() < Date.now() ? "overdue" : "pending";
    },
    dueLabel(incident) {
      const state = this.dueState(incident);
      if (!state) return incident?.status?.is_closed ? "Caso cerrado" : "Sin fecha compromiso";
      return `${state === "overdue" ? "Vencida" : "Compromiso"} · ${this.formatDateTime(incident.response_due_at)}`;
    },
    formatDateParts(value) {
      if (!value) return { day: "--", month: "---", time: "--:--" };
      const date = new Date(String(value).replace(" ", "T"));
      if (Number.isNaN(date.getTime())) return { day: "--", month: "---", time: "--:--" };
      return {
        day: new Intl.DateTimeFormat("es-CL", { day: "2-digit" }).format(date),
        month: new Intl.DateTimeFormat("es-CL", { month: "short" }).format(date).replace(".", ""),
        time: new Intl.DateTimeFormat("es-CL", { hour: "2-digit", minute: "2-digit", hour12: false }).format(date),
      };
    },
    humanize(value) {
      return value ? String(value).replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase()) : "—";
    },
    toInputDateTime(value) {
      return value ? String(value).slice(0, 16) : "";
    },
    formatDateTime(value) {
      if (!value) return "-";
      return new Date(value).toLocaleString("es-CL", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (errors ? errors[Object.keys(errors)[0]]?.[0] : null) || error?.response?.data?.message || error?.message || "No se pudo completar la operación.";
    },
    ensureArray(value) {
      if (Array.isArray(value)) return value;
      if (value === null || value === undefined || value === "") return [];
      return [value];
    },
    showSuccess(message) {
      return Swal.fire({
        icon: "success",
        title: "Operación realizada",
        text: message,
        confirmButtonText: "OK",
      });
    },
    showWarning(message) {
      return Swal.fire({
        icon: "warning",
        title: "Revisa la información",
        text: message,
        confirmButtonText: "OK",
      });
    },
    showError(message) {
      return Swal.fire({
        icon: "error",
        title: "No se pudo completar la operación",
        text: message,
        confirmButtonText: "OK",
      });
    },
  },
};
</script>

<template>
  <Layout>
    <main class="incident-workspace">
      <section class="incident-hero">
        <div class="incident-hero__orb incident-hero__orb--one"></div>
        <div class="incident-hero__orb incident-hero__orb--two"></div>
        <div class="incident-hero__content">
          <span class="incident-eyebrow"><i class="bx bx-shield-quarter"></i> Seguimiento de seguridad nocturna</span>
          <h1>Incidencias de rondas</h1>
          <p>Prioriza cada novedad, asigna responsables y deja trazabilidad hasta su resolución. El Superadmin puede supervisar estos registros desde su bitácora consolidada.</p>
          <div class="incident-hero__facts">
            <span><i class="bx bx-moon"></i> Vinculada a la ronda</span>
            <span><i class="bx bx-user-check"></i> Responsable identificable</span>
            <span><i class="bx bx-history"></i> Historial completo</span>
          </div>
        </div>
        <div class="incident-hero__actions">
          <button type="button" class="incident-button incident-button--ghost" :disabled="loading" @click="loadIncidents(pagination.current_page)">
            <i class="bx bx-refresh" :class="{ 'bx-spin': loading }"></i> Actualizar
          </button>
          <button v-if="canExport" type="button" class="incident-button incident-button--light" @click="exportPdf">
            <i class="bx bx-download"></i> Exportar PDF
          </button>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="incident-alert"><i class="bx bx-error-circle"></i>{{ error }}</BAlert>

      <section class="incident-metrics" aria-label="Resumen de incidencias">
        <article class="incident-metric incident-metric--indigo"><span><i class="bx bx-list-check"></i></span><div><small>En la selección</small><strong>{{ summary.total }}</strong><p>incidencias encontradas</p></div></article>
        <article class="incident-metric incident-metric--rose"><span><i class="bx bx-error-alt"></i></span><div><small>Críticas</small><strong>{{ summary.critical }}</strong><p>requieren prioridad</p></div></article>
        <article class="incident-metric incident-metric--amber"><span><i class="bx bx-time-five"></i></span><div><small>Compromiso vencido</small><strong>{{ summary.overdue }}</strong><p>sin cierre registrado</p></div></article>
        <article class="incident-metric incident-metric--slate"><span><i class="bx bx-user-x"></i></span><div><small>Sin responsable</small><strong>{{ summary.unassigned }}</strong><p>requieren asignación</p></div></article>
      </section>

      <section class="incident-filter-panel">
        <div class="incident-filter-panel__head">
          <div class="incident-queue-switch" aria-label="Tipo de bandeja">
            <button type="button" :class="{ active: filters.pending_only }" @click="selectQueue(true)"><i class="bx bx-loader-circle"></i> Pendientes</button>
            <button type="button" :class="{ active: !filters.pending_only }" @click="selectQueue(false)"><i class="bx bx-archive"></i> Historial completo</button>
          </div>
          <span>{{ rangeLabel }}</span>
        </div>
        <form class="incident-filters" @submit.prevent="loadIncidents(1)">
          <div class="incident-field incident-field--search">
            <label for="incident-search">Buscar incidencia</label>
            <div class="incident-search"><i class="bx bx-search"></i><input id="incident-search" v-model.trim="filters.search" type="search" placeholder="Título, detalle, sector o nochero"><button type="submit" aria-label="Buscar"><i class="bx bx-right-arrow-alt"></i></button></div>
          </div>
          <div class="incident-field"><label>Prioridad</label><Multiselect v-model="filters.priority" :options="priorityOptions" :searchable="false" /></div>
          <div class="incident-field"><label>Estado</label><Multiselect v-model="filters.status_id" :options="statusOptions" :searchable="true" /></div>
          <div class="incident-field"><label>Responsable</label><Multiselect v-model="filters.responsible_user_id" :options="responsibleOptions" :searchable="true" /></div>
          <div class="incident-field"><label for="incident-from">Desde</label><input id="incident-from" v-model="filters.from" type="date" class="form-control"></div>
          <div class="incident-field"><label for="incident-to">Hasta</label><input id="incident-to" v-model="filters.to" type="date" class="form-control"></div>
          <div class="incident-filter-actions">
            <button v-if="activeFilterCount" type="button" class="incident-clear" @click="clearFilters"><i class="bx bx-x"></i> Limpiar {{ activeFilterCount }}</button>
            <button type="submit" class="incident-button incident-button--primary"><i class="bx bx-filter-alt"></i> Aplicar filtros</button>
          </div>
        </form>
      </section>

      <section class="incident-content">
        <aside class="incident-inbox">
          <header class="incident-panel-heading">
            <div><span>Bandeja operativa</span><h2>{{ filters.pending_only ? "Pendientes de gestión" : "Todas las incidencias" }}</h2></div>
            <b>{{ pagination.total }}</b>
          </header>

          <LoadingState v-if="loading" message="Cargando incidencias..." compact />
          <div v-else-if="!incidents.length" class="incident-empty incident-empty--list">
            <span><i class="bx bx-check-shield"></i></span>
            <strong>{{ filters.pending_only ? "No hay incidencias pendientes" : "No hay registros para estos filtros" }}</strong>
            <p>{{ filters.pending_only ? "La bandeja nocturna está al día." : "Prueba con una búsqueda o periodo diferente." }}</p>
          </div>
          <div v-else class="incident-list">
            <button
              v-for="incident in incidents"
              :key="incident.id"
              type="button"
              class="incident-card"
              :class="[{ 'incident-card--active': selectedIncident?.id === incident.id }, `incident-card--${incident.priority}`]"
              @click="openIncident(incident)"
            >
              <span class="incident-card__date"><strong>{{ formatDateParts(incident.created_at).day }}</strong><b>{{ formatDateParts(incident.created_at).month }}</b><small>{{ formatDateParts(incident.created_at).time }}</small></span>
              <span class="incident-card__body">
                <span class="incident-card__badges"><i class="incident-badge" :class="priorityClass(incident.priority)">{{ priorityLabel(incident.priority) }}</i><i class="incident-badge" :class="statusClass(incident)">{{ incident.status?.name || "Sin estado" }}</i></span>
                <strong>{{ incident.title }}</strong>
                <small><i class="bx bx-map-pin"></i>{{ incident.sector_name || incident.shift?.coverage_label || "Sin sector" }} · {{ incident.shift?.staff?.full_name || "Nochero no informado" }}</small>
                <em :class="`incident-due--${dueState(incident) || 'neutral'}`"><i class="bx bx-time-five"></i>{{ dueLabel(incident) }}</em>
              </span>
              <i class="bx bx-chevron-right incident-card__arrow"></i>
            </button>
          </div>

          <div v-if="pagination.last_page > 1" class="incident-pagination">
            <button type="button" :disabled="pagination.current_page <= 1" @click="loadIncidents(pagination.current_page - 1)"><i class="bx bx-chevron-left"></i></button>
            <span>Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadIncidents(pagination.current_page + 1)"><i class="bx bx-chevron-right"></i></button>
          </div>
        </aside>

        <section class="incident-detail">
          <LoadingState v-if="selectionLoading" message="Abriendo incidencia..." compact />
          <template v-else-if="selectedIncident">
            <header class="incident-detail__hero">
              <span class="incident-detail__icon"><i class="bx bx-error-alt"></i></span>
              <div>
                <span>Incidencia #{{ selectedIncident.id }} · {{ formatDateTime(selectedIncident.created_at) }}</span>
                <h2>{{ selectedIncident.title }}</h2>
                <div><i class="incident-badge" :class="priorityClass(selectedIncident.priority)">{{ priorityLabel(selectedIncident.priority) }}</i><i class="incident-badge" :class="statusClass(selectedIncident)">{{ selectedIncident.status?.name || "Sin estado" }}</i></div>
              </div>
            </header>

            <div class="incident-context">
              <article><i class="bx bx-moon"></i><div><span>Nochero</span><strong>{{ selectedIncident.shift?.staff?.full_name || "No informado" }}</strong></div></article>
              <article><i class="bx bx-map-pin"></i><div><span>Sector</span><strong>{{ selectedIncident.sector_name || selectedIncident.shift?.coverage_label || "No informado" }}</strong></div></article>
              <article><i class="bx bx-file"></i><div><span>Acta / ronda</span><strong>{{ selectedIncident.round?.act_number || `Ronda ${selectedIncident.round?.round_number || "—"}` }}</strong></div></article>
              <article><i class="bx bx-user-check"></i><div><span>Responsable</span><strong>{{ selectedIncident.current_responsible?.name || "Sin asignar" }}</strong></div></article>
            </div>

            <section class="incident-description"><span>Hecho informado</span><p>{{ selectedIncident.description }}</p></section>

            <div v-if="!canUpdateSelected" class="incident-readonly"><i class="bx bx-lock-alt"></i><div><strong>Consulta de sólo lectura</strong><span>Puedes revisar la trazabilidad, pero la gestión corresponde a los responsables asignados.</span></div></div>

            <template v-else>
              <section class="incident-form-section">
                <div class="incident-section-heading"><span><i class="bx bx-git-branch"></i></span><div><small>Gestión del caso</small><h3>Prioridad, estado y responsables</h3></div></div>
                <div class="incident-form-grid">
                  <div class="incident-field"><label>Prioridad</label><Multiselect v-model="updateForm.priority" :options="priorityOptions.slice(1)" :searchable="false" /></div>
                  <div class="incident-field"><label>Estado</label><Multiselect v-model="updateForm.status_id" :options="statusOptions.slice(1)" :searchable="true" /></div>
                  <div class="incident-field"><label>Responsable principal</label><Multiselect v-model="updateForm.current_responsible_user_id" :options="responsibleOptions.slice(1)" :searchable="true" /></div>
                  <div class="incident-field incident-field--wide"><label>Equipo asignado</label><Multiselect v-model="updateForm.assignee_user_ids" :options="responsibleOptions.slice(1)" mode="tags" :searchable="true" :close-on-select="false" /></div>
                  <div class="incident-field"><label>Fecha compromiso</label><BFormInput v-model="updateForm.response_due_at" type="datetime-local" /></div>
                </div>
              </section>

              <section class="incident-form-section">
                <div class="incident-section-heading"><span><i class="bx bx-check-shield"></i></span><div><small>Resolución</small><h3>Respuesta y respaldo de cierre</h3></div></div>
                <div class="incident-form-grid">
                  <div class="incident-field incident-field--wide"><label>Respuesta o acciones realizadas</label><BFormTextarea v-model="updateForm.response_summary" rows="3" placeholder="Describe las medidas adoptadas..." /></div>
                  <div class="incident-field incident-field--wide"><label>Notas de cierre</label><BFormTextarea v-model="updateForm.closure_evidence_notes" rows="2" placeholder="Resultado de la verificación final..." /></div>
                  <div class="incident-field incident-field--wide"><label>Evidencias</label><BFormInput type="file" accept="image/*" multiple @change="onEvidenceChange" /><small>{{ selectedEvidenceCount }} existentes · {{ updateForm.evidenceFiles.length }} nuevas</small></div>
                </div>
                <div class="incident-save-row"><button type="button" class="incident-button incident-button--primary" :disabled="saving" @click="saveIncident"><i class="bx bx-save"></i>{{ saving ? "Guardando..." : "Guardar gestión" }}</button></div>
              </section>

              <section class="incident-followup">
                <div class="incident-section-heading"><span><i class="bx bx-message-square-detail"></i></span><div><small>Bitácora del caso</small><h3>Agregar seguimiento</h3></div></div>
                <BFormTextarea v-model="updateForm.comment" rows="3" placeholder="Escribe una actualización concreta para el historial..." />
                <button type="button" class="incident-button incident-button--secondary" :disabled="saving || !updateForm.comment.trim()" @click="saveFollowUp"><i class="bx bx-plus"></i>Registrar seguimiento</button>
              </section>
            </template>

            <section class="incident-history">
              <div class="incident-section-heading"><span><i class="bx bx-history"></i></span><div><small>Trazabilidad</small><h3>Historial de seguimiento</h3></div><b>{{ (selectedIncident.comments || []).length }}</b></div>
              <div v-if="!(selectedIncident.comments || []).length" class="incident-history__empty">Aún no se han agregado comentarios.</div>
              <div v-else class="incident-timeline">
                <article v-for="comment in selectedIncident.comments" :key="comment.id">
                  <span><i></i></span>
                  <div><header><strong>{{ comment.user?.name || "Usuario" }}</strong><time>{{ formatDateTime(comment.responded_at || comment.created_at) }}</time></header><small>{{ comment.status?.name || "Sin cambio de estado" }}</small><p>{{ comment.comment }}</p></div>
                </article>
              </div>
            </section>
          </template>

          <div v-else class="incident-empty incident-empty--detail">
            <span><i class="bx bx-pointer"></i></span>
            <strong>Selecciona una incidencia</strong>
            <p>Verás su ronda de origen, responsables, compromisos y todo el historial de gestión.</p>
          </div>
        </section>
      </section>
    </main>
  </Layout>
</template>

<style scoped>
.incident-workspace { --incident-ink: #172033; --incident-muted: #6f7b90; padding-bottom: 2rem; color: var(--incident-ink); }
.incident-hero { position: relative; isolation: isolate; display: flex; min-height: 245px; align-items: flex-end; justify-content: space-between; gap: 2rem; overflow: hidden; margin-bottom: 1rem; padding: 2.1rem 2.25rem; border-radius: 24px; color: #fff; background: linear-gradient(125deg, #111d49 0%, #312e81 54%, #5b21b6 100%); box-shadow: 0 22px 55px rgba(30, 41, 89, .22); }
.incident-hero__orb { position: absolute; z-index: -1; border-radius: 50%; opacity: .3; filter: blur(1px); }
.incident-hero__orb--one { width: 280px; height: 280px; top: -170px; right: 16%; background: #38bdf8; }
.incident-hero__orb--two { width: 250px; height: 250px; right: -100px; bottom: -135px; background: #fb7185; }
.incident-hero__content { max-width: 740px; }
.incident-eyebrow { display: inline-flex; align-items: center; gap: .42rem; margin-bottom: .8rem; padding: .38rem .68rem; border: 1px solid rgba(255,255,255,.23); border-radius: 999px; color: #dbeafe; background: rgba(255,255,255,.09); font-size: .7rem; font-weight: 750; letter-spacing: .065em; text-transform: uppercase; }
.incident-hero h1 { margin: 0 0 .55rem; color: #fff; font-size: clamp(1.9rem, 3vw, 2.75rem); font-weight: 760; letter-spacing: -.04em; }
.incident-hero p { max-width: 700px; margin: 0; color: rgba(255,255,255,.8); font-size: .92rem; line-height: 1.65; }
.incident-hero__facts { display: flex; flex-wrap: wrap; gap: .7rem 1rem; margin-top: 1.1rem; color: #e0e7ff; font-size: .73rem; }
.incident-hero__facts span { display: inline-flex; align-items: center; gap: .3rem; }
.incident-hero__actions { display: grid; min-width: 145px; gap: .6rem; }
.incident-button { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; gap: .42rem; padding: .62rem .88rem; border: 0; border-radius: 11px; font-size: .75rem; font-weight: 750; transition: .18s ease; }
.incident-button:hover:not(:disabled) { transform: translateY(-1px); }
.incident-button:disabled { cursor: not-allowed; opacity: .58; }
.incident-button--ghost { color: #fff; border: 1px solid rgba(255,255,255,.24); background: rgba(255,255,255,.1); }
.incident-button--light { color: #312e81; background: #fff; box-shadow: 0 10px 25px rgba(11, 18, 52, .16); }
.incident-button--primary { color: #fff; background: linear-gradient(135deg, #4f46e5, #3730a3); box-shadow: 0 8px 18px rgba(79, 70, 229, .2); }
.incident-button--secondary { align-self: flex-end; margin-top: .7rem; color: #4338ca; background: #e9eafe; }
.incident-alert { display: flex; align-items: center; gap: .45rem; border: 0; border-radius: 14px; }
.incident-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; margin-bottom: .8rem; }
.incident-metric { display: flex; min-height: 102px; align-items: center; gap: .8rem; padding: .9rem 1rem; border: 1px solid #e6eaf2; border-radius: 17px; background: #fff; box-shadow: 0 8px 20px rgba(31, 43, 75, .05); }
.incident-metric > span { display: grid; flex: 0 0 42px; width: 42px; height: 42px; place-items: center; border-radius: 13px; font-size: 1.2rem; }
.incident-metric small, .incident-metric p { display: block; margin: 0; color: var(--incident-muted); }
.incident-metric small { font-size: .69rem; font-weight: 700; }
.incident-metric strong { display: block; margin: .08rem 0; color: var(--incident-ink); font-size: 1.5rem; line-height: 1; }
.incident-metric p { font-size: .63rem; }
.incident-metric--indigo > span { color: #4f46e5; background: #eef2ff; }
.incident-metric--rose > span { color: #be3652; background: #fff0f3; }
.incident-metric--amber > span { color: #b45309; background: #fff7e6; }
.incident-metric--slate > span { color: #475569; background: #eef2f6; }
.incident-filter-panel { margin-bottom: .8rem; overflow: hidden; border: 1px solid #e5e9f1; border-radius: 18px; background: #fff; box-shadow: 0 8px 22px rgba(31, 43, 75, .045); }
.incident-filter-panel__head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; border-bottom: 1px solid #edf0f5; background: #fafbfe; }
.incident-filter-panel__head > span { color: #7b8596; font-size: .69rem; }
.incident-queue-switch { display: inline-flex; gap: .25rem; padding: .24rem; border-radius: 11px; background: #edf0f6; }
.incident-queue-switch button { display: inline-flex; align-items: center; gap: .28rem; padding: .42rem .64rem; border: 0; border-radius: 8px; color: #667085; background: transparent; font-size: .68rem; font-weight: 700; }
.incident-queue-switch button.active { color: #3730a3; background: #fff; box-shadow: 0 3px 9px rgba(35, 43, 76, .1); }
.incident-filters { display: grid; grid-template-columns: minmax(250px, 1.7fr) repeat(5, minmax(120px, 1fr)); gap: .7rem; padding: .95rem 1rem; }
.incident-field { min-width: 0; }
.incident-field label { display: block; margin: 0 0 .32rem; color: #596478; font-size: .66rem; font-weight: 750; }
.incident-field :deep(.multiselect), .incident-field .form-control { min-height: 40px; border-color: #dfe4ed; border-radius: 10px; font-size: .74rem; }
.incident-field textarea.form-control { min-height: auto; }
.incident-search { display: flex; min-height: 40px; align-items: center; overflow: hidden; border: 1px solid #dfe4ed; border-radius: 10px; background: #fff; }
.incident-search > i { margin-left: .7rem; color: #8a94a6; font-size: 1rem; }
.incident-search input { min-width: 0; flex: 1; padding: .5rem; border: 0; outline: 0; color: var(--incident-ink); background: transparent; font-size: .75rem; }
.incident-search button { display: grid; align-self: stretch; width: 40px; border: 0; place-items: center; color: #fff; background: #4f46e5; }
.incident-filter-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: .55rem; }
.incident-clear { border: 0; color: #5b5bd6; background: transparent; font-size: .7rem; font-weight: 700; }
.incident-content { display: grid; grid-template-columns: minmax(330px, .78fr) minmax(0, 1.35fr); gap: .8rem; align-items: start; }
.incident-inbox, .incident-detail { overflow: hidden; border: 1px solid #e5e9f1; border-radius: 19px; background: #fff; box-shadow: 0 10px 28px rgba(31, 43, 75, .055); }
.incident-inbox { position: sticky; top: 84px; }
.incident-detail { min-height: 620px; }
.incident-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: 1rem 1.1rem .8rem; border-bottom: 1px solid #edf0f5; }
.incident-panel-heading span { color: #635bce; font-size: .62rem; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
.incident-panel-heading h2 { margin: .1rem 0 0; color: var(--incident-ink); font-size: .96rem; font-weight: 750; }
.incident-panel-heading > b { display: grid; min-width: 32px; height: 32px; padding: 0 .4rem; place-items: center; border-radius: 10px; color: #4f46e5; background: #eef2ff; font-size: .72rem; }
.incident-list { display: grid; max-height: 770px; overflow-y: auto; scrollbar-width: thin; }
.incident-card { position: relative; display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; align-items: center; gap: .75rem; width: 100%; padding: .9rem 1rem; border: 0; border-bottom: 1px solid #edf0f5; text-align: left; background: #fff; transition: .15s ease; }
.incident-card::before { position: absolute; inset: 0 auto 0 0; width: 3px; background: #94a3b8; content: ""; opacity: 0; }
.incident-card--critica::before { background: #e44d66; }
.incident-card--alta::before { background: #f59e0b; }
.incident-card:hover, .incident-card--active { background: #f8f9ff; }
.incident-card--active::before { opacity: 1; }
.incident-card__date { display: grid; text-align: center; }
.incident-card__date strong { color: #27324a; font-size: 1rem; line-height: 1; }
.incident-card__date b { color: #5f6a7d; font-size: .58rem; text-transform: uppercase; }
.incident-card__date small { margin-top: .12rem; color: #9aa3b2; font-size: .56rem; }
.incident-card__body { min-width: 0; }
.incident-card__badges { display: flex; flex-wrap: wrap; gap: .3rem; margin-bottom: .38rem; }
.incident-card__body > strong { display: block; overflow: hidden; color: #253047; font-size: .76rem; text-overflow: ellipsis; white-space: nowrap; }
.incident-card__body > small, .incident-card__body > em { display: flex; align-items: center; gap: .22rem; overflow: hidden; margin-top: .26rem; font-style: normal; font-size: .62rem; text-overflow: ellipsis; white-space: nowrap; }
.incident-card__body > small { color: #788397; }
.incident-card__body > em { color: #7a8495; }
.incident-due--overdue { color: #be3652 !important; font-weight: 700; }
.incident-due--pending { color: #9a5b03 !important; }
.incident-card__arrow { color: #a6adba; font-size: 1.1rem; }
.incident-badge { display: inline-flex; align-items: center; padding: .25rem .43rem; border-radius: 999px; font-size: .57rem; font-style: normal; font-weight: 750; line-height: 1; }
.incident-badge--critical { color: #b4233e; background: #ffe9ee; }
.incident-badge--high { color: #9a5b03; background: #fff1cf; }
.incident-badge--medium { color: #1e628f; background: #e8f5ff; }
.incident-badge--low { color: #647084; background: #edf1f6; }
.incident-badge--closed { color: #08785e; background: #e5f8f1; }
.incident-badge--review { color: #6541b5; background: #f0eaff; }
.incident-badge--derived { color: #156a75; background: #e5f8fa; }
.incident-badge--open { color: #9a5b03; background: #fff4d9; }
.incident-pagination { display: flex; align-items: center; justify-content: center; gap: .65rem; padding: .8rem; border-top: 1px solid #edf0f5; }
.incident-pagination button { display: grid; width: 30px; height: 30px; border: 1px solid #dde2eb; border-radius: 9px; place-items: center; color: #4f46e5; background: #fff; }
.incident-pagination button:disabled { color: #b1b8c4; background: #f3f5f8; }
.incident-pagination span { color: #707b8d; font-size: .65rem; }
.incident-detail__hero { display: flex; align-items: flex-start; gap: .8rem; padding: 1.15rem 1.2rem; border-bottom: 1px solid #edf0f5; background: linear-gradient(135deg, #fbfcff, #f5f3ff); }
.incident-detail__icon { display: grid; flex: 0 0 44px; width: 44px; height: 44px; place-items: center; border-radius: 14px; color: #be3652; background: #ffe9ee; font-size: 1.25rem; }
.incident-detail__hero > div { min-width: 0; }
.incident-detail__hero > div > span { color: #7a8497; font-size: .63rem; font-weight: 700; text-transform: uppercase; }
.incident-detail__hero h2 { margin: .2rem 0 .45rem; color: #202b41; font-size: 1.15rem; font-weight: 760; }
.incident-detail__hero > div > div { display: flex; flex-wrap: wrap; gap: .35rem; }
.incident-context { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .6rem; padding: 1rem 1.2rem 0; }
.incident-context article { display: flex; min-width: 0; align-items: center; gap: .55rem; padding: .72rem; border: 1px solid #e8ebf2; border-radius: 12px; background: #fafbfe; }
.incident-context article > i { color: #635bce; font-size: 1.05rem; }
.incident-context span, .incident-context strong { display: block; }
.incident-context span { color: #8992a2; font-size: .56rem; text-transform: uppercase; }
.incident-context strong { overflow: hidden; margin-top: .08rem; color: #3b4558; font-size: .66rem; text-overflow: ellipsis; white-space: nowrap; }
.incident-description, .incident-form-section, .incident-followup, .incident-history { margin: 1rem 1.2rem 0; padding: 1rem; border: 1px solid #e5e9f1; border-radius: 14px; background: #fff; }
.incident-description > span { color: #635bce; font-size: .62rem; font-weight: 750; letter-spacing: .055em; text-transform: uppercase; }
.incident-description p { margin: .4rem 0 0; color: #536075; font-size: .75rem; line-height: 1.65; white-space: pre-wrap; }
.incident-readonly { display: flex; align-items: center; gap: .65rem; margin: 1rem 1.2rem 0; padding: .8rem; border: 1px solid #dbe5fa; border-radius: 13px; color: #304a79; background: #f1f6ff; }
.incident-readonly > i { font-size: 1.15rem; }
.incident-readonly strong, .incident-readonly span { display: block; }
.incident-readonly strong { font-size: .72rem; }
.incident-readonly span { margin-top: .1rem; color: #647594; font-size: .65rem; }
.incident-section-heading { display: flex; align-items: center; gap: .6rem; margin-bottom: .8rem; }
.incident-section-heading > span { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border-radius: 11px; color: #4f46e5; background: #eef2ff; font-size: 1rem; }
.incident-section-heading small { display: block; color: #8b94a4; font-size: .56rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
.incident-section-heading h3 { margin: .08rem 0 0; color: #303a4e; font-size: .8rem; font-weight: 750; }
.incident-section-heading > b { display: grid; min-width: 28px; height: 28px; margin-left: auto; padding: 0 .3rem; place-items: center; border-radius: 9px; color: #4f46e5; background: #eef2ff; font-size: .65rem; }
.incident-form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .7rem; }
.incident-field--wide { grid-column: 1 / -1; }
.incident-field > small { display: block; margin-top: .3rem; color: #8a94a5; font-size: .61rem; }
.incident-save-row { display: flex; justify-content: flex-end; margin-top: .85rem; }
.incident-followup { display: flex; flex-direction: column; background: #fafaff; }
.incident-history { margin-bottom: 1.2rem; }
.incident-history__empty { padding: .8rem; border-radius: 10px; color: #7d8798; background: #f7f8fb; font-size: .69rem; text-align: center; }
.incident-timeline { display: grid; }
.incident-timeline article { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: .5rem; }
.incident-timeline article > span { position: relative; display: flex; justify-content: center; }
.incident-timeline article > span::after { position: absolute; width: 1px; height: 100%; top: 10px; background: #dfe3ed; content: ""; }
.incident-timeline article:last-child > span::after { display: none; }
.incident-timeline article > span i { z-index: 1; width: 8px; height: 8px; margin-top: .42rem; border-radius: 50%; background: #6366f1; box-shadow: 0 0 0 4px #eef2ff; }
.incident-timeline article > div { padding: 0 0 1rem; }
.incident-timeline header { display: flex; align-items: center; justify-content: space-between; gap: .8rem; }
.incident-timeline strong { color: #374154; font-size: .7rem; }
.incident-timeline time, .incident-timeline small { color: #8b94a5; font-size: .6rem; }
.incident-timeline p { margin: .3rem 0 0; color: #596579; font-size: .69rem; line-height: 1.55; }
.incident-empty { display: grid; place-items: center; padding: 3rem 1rem; text-align: center; }
.incident-empty > span { display: grid; width: 58px; height: 58px; margin-bottom: .8rem; place-items: center; border-radius: 18px; color: #4f46e5; background: #eef2ff; font-size: 1.55rem; }
.incident-empty strong { color: #303a4d; font-size: .84rem; }
.incident-empty p { max-width: 360px; margin: .3rem 0 0; color: var(--incident-muted); font-size: .69rem; line-height: 1.55; }
.incident-empty--detail { min-height: 620px; }
.incident-empty--list { min-height: 290px; }
.bx-spin { animation: incident-spin .8s linear infinite; }
@keyframes incident-spin { to { transform: rotate(360deg); } }
@media (max-width: 1399px) {
  .incident-filters { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .incident-field--search { grid-column: span 3; }
  .incident-context { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 1199px) {
  .incident-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .incident-content { grid-template-columns: 1fr; }
  .incident-inbox { position: static; }
  .incident-list { max-height: none; }
}
@media (max-width: 767px) {
  .incident-hero { min-height: 0; align-items: stretch; flex-direction: column; padding: 1.35rem; border-radius: 18px; }
  .incident-hero__actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .incident-metrics { gap: .6rem; }
  .incident-metric { min-height: 92px; padding: .75rem; }
  .incident-filter-panel__head { align-items: flex-start; flex-direction: column; }
  .incident-queue-switch { width: 100%; }
  .incident-queue-switch button { flex: 1; justify-content: center; }
  .incident-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: .85rem; }
  .incident-field--search { grid-column: span 2; }
  .incident-filter-actions { align-items: stretch; flex-direction: column-reverse; }
  .incident-context, .incident-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .incident-field--wide { grid-column: 1 / -1; }
}
@media (max-width: 480px) {
  .incident-hero__actions, .incident-metrics, .incident-filters, .incident-context, .incident-form-grid { grid-template-columns: 1fr; }
  .incident-field--search, .incident-field--wide { grid-column: 1; }
  .incident-filter-panel__head > span { display: none; }
  .incident-card { grid-template-columns: 38px minmax(0, 1fr); padding: .85rem; }
  .incident-card__arrow { display: none; }
  .incident-detail__hero, .incident-context { padding-inline: .9rem; }
  .incident-description, .incident-form-section, .incident-followup, .incident-history, .incident-readonly { margin-inline: .9rem; }
  .incident-timeline header { align-items: flex-start; flex-direction: column; gap: .1rem; }
}
</style>
