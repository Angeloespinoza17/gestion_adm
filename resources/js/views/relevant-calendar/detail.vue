<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import { getPdfMake } from "../../utils/pdfmake";
import EventFormModal from "../../components/relevant-calendar/event-form-modal.vue";

export default {
  components: {
    Layout,
    EventFormModal,
  },
  data() {
    return {
      loading: false,
      loadingCatalogs: false,
      uploading: false,
      error: null,
      event: null,
      catalogs: { capabilities: {} },
      showFormModal: false,
      newAttachment: null,
    };
  },
  computed: {
    canEdit() {
      if (!this.event) return false;
      if (this.catalogs.capabilities?.can_manage_all) return true;
      const managed = this.catalogs.managed_department_ids || [];
      return Boolean(this.event.department_id && managed.includes(this.event.department_id));
    },
    canDelete() {
      return Boolean(this.catalogs.capabilities?.can_manage_all);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadEvent();
  },
  methods: {
    async loadCatalogs() {
      this.loadingCatalogs = true;
      try {
        const response = await axios.get("/api/relevant-calendar/catalogs");
        this.catalogs = response.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingCatalogs = false;
      }
    },
    async loadEvent() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get(`/api/relevant-calendar/events/${this.$route.params.id}`);
        this.event = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async handleSaved(saved) {
      this.showFormModal = false;
      if (saved?.id && String(saved.id) !== String(this.$route.params.id)) {
        await this.$router.push(`/relevant-calendar/events/${saved.id}`);
      }
      await this.loadEvent();
    },
    async confirmDelete() {
      const result = await Swal.fire({
        title: "Eliminar evento",
        text: "Esta acción removerá el registro del calendario.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/relevant-calendar/events/${this.event.id}`);
        await this.$router.push("/relevant-calendar");
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    onAttachment(event) {
      this.newAttachment = event?.target?.files?.[0] || null;
    },
    async uploadAttachment() {
      if (!this.newAttachment || !this.event) return;

      this.uploading = true;
      try {
        const formData = new FormData();
        formData.append("document", this.newAttachment);
        await axios.post(`/api/relevant-calendar/events/${this.event.id}/attachments`, formData);
        this.newAttachment = null;
        await this.loadEvent();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.uploading = false;
      }
    },
    async downloadAttachment(attachment) {
      try {
        const response = await axios.get(`/api/relevant-calendar/attachments/${attachment.id}/download`, {
          responseType: "blob",
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement("a");
        link.href = url;
        link.download = attachment.file_name || "documento";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async removeAttachment(attachment) {
      const result = await Swal.fire({
        title: "Eliminar documento",
        text: attachment.file_name,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/relevant-calendar/attachments/${attachment.id}`);
        await this.loadEvent();
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    exportPdf() {
      if (!this.event) return;
      const pdfMake = getPdfMake();
      const docDefinition = {
        content: [
          { text: this.event.title, style: "header" },
          { text: `${this.kindLabel(this.event)} · ${this.event.process_type?.name || "-"}`, margin: [0, 2, 0, 10] },
          {
            columns: [
              [
                { text: `Institución: ${this.event.institution?.name || "-"}` },
                { text: `Departamento: ${this.event.department?.name || "-"}` },
                { text: `Responsable: ${this.event.responsible_user?.name || "-"}` },
                { text: `Prioridad: ${this.event.priority || "-"}` },
                { text: `Estado: ${this.event.effective_status || this.event.status || "-"}` },
              ],
              [
                { text: `Inicio: ${this.formatDate(this.event.start_date)}` },
                { text: `Término: ${this.formatDate(this.event.end_date || this.event.start_date)}` },
                { text: `Hora inicio: ${this.event.start_time ? String(this.event.start_time).slice(0, 5) : "-"}` },
                { text: `Hora término: ${this.event.end_time ? String(this.event.end_time).slice(0, 5) : "-"}` },
              ],
            ],
            columnGap: 18,
          },
          { text: "Descripción", style: "section" },
          { text: this.event.description || "-", margin: [0, 0, 0, 10] },
          { text: "Recordatorios", style: "section" },
          {
            ul: (this.event.reminders || []).map((item) => {
              if (item.reminder_type === "fixed_date") {
                return `Fecha específica: ${this.formatDate(item.reminder_date)}`;
              }
              if (item.reminder_type === "same_day") {
                return "El mismo día";
              }
              const prefix = item.reminder_type === "after_overdue" ? "después del vencimiento" : "antes del vencimiento";
              return `${item.days_before ?? 0} día(s) ${prefix}`;
            }),
            margin: [0, 0, 0, 10],
          },
          { text: "Adjuntos", style: "section" },
          {
            ul: (this.event.attachments || []).map((item) => item.file_name),
            margin: [0, 0, 0, 10],
          },
          { text: "Historial", style: "section" },
          {
            table: {
              headerRows: 1,
              widths: [120, 90, "*"],
              body: [
                ["Fecha", "Acción", "Detalle"],
                ...(this.event.logs || []).map((log) => [
                  this.formatDateTime(log.created_at),
                  log.action || "-",
                  log.description || "-",
                ]),
              ],
            },
            layout: "lightHorizontalLines",
          },
        ],
        styles: {
          header: { fontSize: 18, bold: true },
          section: { fontSize: 12, bold: true, margin: [0, 8, 0, 4] },
        },
        defaultStyle: { fontSize: 10 },
      };
      pdfMake.createPdf(docDefinition).download(`proceso_${this.event.id}.pdf`);
    },
    statusVariant(status) {
      if (status === "completado" || status === "enviado" || status === "declarado") return "success";
      if (status === "vencido") return "danger";
      if (status === "en_preparacion" || status === "en_revision") return "warning";
      if (status === "archivado" || status === "no_aplica") return "secondary";
      return "info";
    },
    priorityVariant(priority) {
      if (priority === "critica") return "danger";
      if (priority === "alta") return "warning";
      if (priority === "media") return "primary";
      return "secondary";
    },
    kindLabel(item) {
      const kind = item.event_kind;
      if (kind === "stage") return "Etapa";
      if (kind === "occurrence") return "Ocurrencia";
      if (kind === "series_master") return "Serie recurrente";
      if (kind === "process") return "Proceso";
      return "Evento";
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).slice(0, 10).split("-");
      return `${day}/${month}/${year}`;
    },
    formatDateTime(value) {
      if (!value) return "-";
      const safe = String(value).replace(" ", "T");
      const date = new Date(safe);
      if (Number.isNaN(date.getTime())) return value;
      return `${String(date.getDate()).padStart(2, "0")}/${String(date.getMonth() + 1).padStart(2, "0")}/${date.getFullYear()} ${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "No se pudo cargar el evento."
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
      <div class="mb-3 mb-sm-0">
        <div class="text-muted small mb-1">
          <router-link to="/relevant-calendar">Calendario y Fechas Relevantes</router-link>
        </div>
        <h4 class="mb-1">{{ event?.title || "Detalle del proceso" }}</h4>
        <div class="text-muted">{{ kindLabel(event || {}) }}</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <BButton variant="outline-secondary" @click="$router.push('/relevant-calendar')">Volver</BButton>
        <BButton v-if="canExport" variant="outline-danger" @click="exportPdf">Exportar PDF</BButton>
        <BButton v-if="canEdit" variant="outline-primary" @click="showFormModal = true">Editar</BButton>
        <BButton v-if="canDelete" variant="danger" @click="confirmDelete">Eliminar</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading">Cargando detalle...</BCard>

    <div v-else-if="event" class="row g-3">
      <div class="col-lg-8">
        <BCard class="mb-3">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <BBadge :variant="priorityVariant(event.priority)">{{ event.priority }}</BBadge>
            <BBadge :variant="statusVariant(event.effective_status || event.status)">{{ event.effective_status || event.status }}</BBadge>
            <BBadge variant="light">{{ kindLabel(event) }}</BBadge>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="text-muted small">Tipo de proceso</div>
              <div class="fw-semibold">{{ event.process_type?.name || "-" }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Institución</div>
              <div class="fw-semibold">{{ event.institution?.name || "-" }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Departamento</div>
              <div class="fw-semibold">{{ event.department?.name || "-" }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Responsable</div>
              <div class="fw-semibold">{{ event.responsible_user?.name || "-" }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Fecha inicio</div>
              <div class="fw-semibold">{{ formatDate(event.start_date) }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Fecha término</div>
              <div class="fw-semibold">{{ formatDate(event.end_date || event.start_date) }}</div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Horario</div>
              <div class="fw-semibold">
                {{ event.start_time ? String(event.start_time).slice(0, 5) : "-" }}
                -
                {{ event.end_time ? String(event.end_time).slice(0, 5) : "-" }}
              </div>
            </div>
            <div class="col-md-6">
              <div class="text-muted small">Enlace externo</div>
              <div class="fw-semibold">
                <a v-if="event.external_url" :href="event.external_url" target="_blank" rel="noopener noreferrer">{{ event.external_url }}</a>
                <span v-else>-</span>
              </div>
            </div>
            <div class="col-12">
              <div class="text-muted small">Descripción</div>
              <div>{{ event.description || "-" }}</div>
            </div>
            <div class="col-12">
              <div class="text-muted small">Observaciones internas</div>
              <div>{{ event.internal_observations || "-" }}</div>
            </div>
          </div>
        </BCard>

        <BCard class="mb-3">
          <h5 class="mb-3">Responsables y usuarios informados</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="text-muted small mb-2">Participantes</div>
              <ul class="mb-0 ps-3">
                <li v-for="item in (event.event_users || []).filter((entry) => entry.role_in_event === 'participant')" :key="`participant-${item.id}`">
                  {{ item.user?.name || "-" }}
                </li>
                <li v-if="!(event.event_users || []).some((entry) => entry.role_in_event === 'participant')" class="text-muted">Sin participantes.</li>
              </ul>
            </div>
            <div class="col-md-6">
              <div class="text-muted small mb-2">Informados</div>
              <ul class="mb-0 ps-3">
                <li v-for="item in (event.event_users || []).filter((entry) => entry.role_in_event === 'informed')" :key="`informed-${item.id}`">
                  {{ item.user?.name || "-" }}
                </li>
                <li v-if="!(event.event_users || []).some((entry) => entry.role_in_event === 'informed')" class="text-muted">Sin usuarios informados.</li>
              </ul>
            </div>
          </div>
        </BCard>

        <BCard class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Documentos adjuntos</h5>
          </div>
          <div class="row g-3 mb-3" v-if="canEdit">
            <div class="col-md-8">
              <BFormInput type="file" @change="onAttachment" />
            </div>
            <div class="col-md-4">
              <BButton variant="primary" :disabled="uploading || !newAttachment" @click="uploadAttachment">
                {{ uploading ? "Subiendo..." : "Subir documento" }}
              </BButton>
            </div>
          </div>
          <div v-if="!(event.attachments || []).length" class="text-muted">No hay documentos cargados.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Archivo</th>
                  <th>Tipo</th>
                  <th>Subido por</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="attachment in event.attachments" :key="attachment.id">
                  <td>{{ attachment.file_name }}</td>
                  <td>{{ attachment.file_type || "-" }}</td>
                  <td>{{ attachment.uploaded_by?.name || attachment.uploadedBy?.name || "-" }}</td>
                  <td>
                    <div class="d-flex gap-2">
                      <BButton size="sm" variant="outline-primary" @click="downloadAttachment(attachment)">Descargar</BButton>
                      <BButton v-if="canEdit" size="sm" variant="outline-danger" @click="removeAttachment(attachment)">Eliminar</BButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>

        <BCard class="mb-3">
          <h5 class="mb-3">Historial de acciones</h5>
          <div v-if="!(event.logs || []).length" class="text-muted">Sin auditoría registrada.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Usuario</th>
                  <th>Acción</th>
                  <th>Detalle</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in event.logs" :key="log.id">
                  <td>{{ formatDateTime(log.created_at) }}</td>
                  <td>{{ log.user?.name || "-" }}</td>
                  <td>{{ log.action }}</td>
                  <td>{{ log.description || "-" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>

      <div class="col-lg-4">
        <BCard class="mb-3">
          <h5 class="mb-3">Recordatorios</h5>
          <div v-if="!(event.reminders || []).length" class="text-muted">Sin recordatorios configurados.</div>
          <ul v-else class="mb-0 ps-3">
            <li v-for="reminder in event.reminders" :key="reminder.id" class="mb-2">
              <span v-if="reminder.reminder_type === 'same_day'">El mismo día</span>
              <span v-else-if="reminder.reminder_type === 'fixed_date'">Fecha específica: {{ formatDate(reminder.reminder_date) }}</span>
              <span v-else-if="reminder.reminder_type === 'after_overdue'">
                {{ reminder.days_before ?? 0 }} día(s) después del vencimiento
              </span>
              <span v-else>{{ reminder.days_before ?? 0 }} día(s) antes del vencimiento</span>
            </li>
          </ul>
        </BCard>

        <BCard class="mb-3">
          <h5 class="mb-3">Condiciones del proceso</h5>
          <ul class="mb-0 ps-3">
            <li>Requiere envío: {{ event.requires_submission ? "Sí" : "No" }}</li>
            <li>Requiere pago: {{ event.requires_payment ? "Sí" : "No" }}</li>
            <li>Requiere firma: {{ event.requires_signature ? "Sí" : "No" }}</li>
            <li>Requiere revisión: {{ event.requires_review ? "Sí" : "No" }}</li>
            <li>Requiere aprobación: {{ event.requires_approval ? "Sí" : "No" }}</li>
          </ul>
        </BCard>

        <BCard class="mb-3" v-if="event.parent_event">
          <h5 class="mb-3">Evento relacionado</h5>
          <div class="fw-semibold">{{ event.parent_event.title }}</div>
          <div class="text-muted small mb-2">{{ kindLabel(event.parent_event) }}</div>
          <router-link :to="`/relevant-calendar/events/${event.parent_event.id}`" class="btn btn-outline-primary btn-sm">
            Ver relacionado
          </router-link>
        </BCard>

        <BCard class="mb-3" v-if="event.child_events?.length">
          <h5 class="mb-3">Etapas o eventos relacionados</h5>
          <div class="list-group list-group-flush">
            <router-link
              v-for="child in event.child_events"
              :key="child.id"
              :to="`/relevant-calendar/events/${child.id}`"
              class="list-group-item list-group-item-action px-0"
            >
              <div class="fw-semibold">{{ child.title }}</div>
              <div class="text-muted small">{{ formatDate(child.end_date || child.start_date) }} · {{ child.status }}</div>
            </router-link>
          </div>
        </BCard>
      </div>
    </div>

    <EventFormModal
      v-model="showFormModal"
      :catalogs="catalogs"
      :event-record="event"
      @saved="handleSaved"
    />
  </Layout>
</template>
