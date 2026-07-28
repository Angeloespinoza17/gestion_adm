<script>
import axios from "axios";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin from "@fullcalendar/interaction";
import bootstrap5Plugin from "@fullcalendar/bootstrap5";
import esLocale from "@fullcalendar/core/locales/es";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmLibraryAction,
  confirmLibraryCancel,
  formatLibraryDateTime,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const emptySpace = () => ({
  id: null,
  name: "",
  location: "",
  capacity: "",
  resources_text: "",
  active: true,
  notes: "",
});

const emptyUsage = () => ({
  id: null,
  biblioteca_espacio_id: null,
  activity_type: "clase",
  title: "",
  course_section_id: null,
  responsible_staff_id: null,
  requester_type: "interno",
  external_name: "",
  external_email: "",
  external_institution: "",
  external_phone: "",
  attendee_count: "",
  requested_resources_text: "",
  start_at: "",
  end_at: "",
  status: "solicitada",
  observations: "",
  evidence_text: "",
});

export default {
  components: {
    FullCalendar,
    LibraryHelpButton,
    LibraryStatusBadge,
    LoadingState,
  },
  props: {
    catalogs: { type: Object, required: true },
  },
  data() {
    return {
      loadingSpaces: false,
      loadingUsages: false,
      error: null,
      spaces: [],
      usages: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        biblioteca_espacio_id: null,
        status: null,
        course_section_id: null,
        date_from: "",
        date_to: "",
      },
      calendarOptions: {
        plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, bootstrap5Plugin],
        locales: [esLocale],
        locale: "es",
        themeSystem: "bootstrap5",
        initialView: "dayGridMonth",
        headerToolbar: { left: "prev,next today", center: "title", right: "dayGridMonth,timeGridWeek,timeGridDay" },
        events: [],
      },
      showSpaceModal: false,
      showUsageModal: false,
      spaceForm: emptySpace(),
      usageForm: emptyUsage(),
    };
  },
  mounted() {
    this.loadSpaces();
    this.loadUsages();
    this.loadCalendar();
  },
  methods: {
    formatLibraryDateTime,
    async loadSpaces() {
      this.loadingSpaces = true;
      try {
        const response = await axios.get("/api/biblioteca/espacios");
        this.spaces = response.data.data || [];
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudieron cargar los espacios.");
      } finally {
        this.loadingSpaces = false;
      }
    },
    async loadUsages(page = 1) {
      this.loadingUsages = true;
      try {
        const response = await axios.get("/api/biblioteca/uso-espacios", {
          params: { page, ...this.filters },
        });
        this.usages = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo cargar el uso de espacios.");
      } finally {
        this.loadingUsages = false;
      }
    },
    async loadCalendar() {
      const response = await axios.get("/api/biblioteca/uso-espacios/calendar");
      this.calendarOptions = {
        ...this.calendarOptions,
        events: response.data.data || [],
      };
    },
    openSpaceCreate() {
      this.spaceForm = emptySpace();
      this.showSpaceModal = true;
    },
    openSpaceEdit(item) {
      this.spaceForm = {
        id: item.id,
        name: item.name,
        location: item.location || "",
        capacity: item.capacity || "",
        resources_text: (item.resources || []).join(", "),
        active: Boolean(item.active),
        notes: item.notes || "",
      };
      this.showSpaceModal = true;
    },
    openUsageCreate() {
      this.usageForm = emptyUsage();
      this.showUsageModal = true;
    },
    async saveSpace() {
      const payload = {
        id: this.spaceForm.id,
        name: this.spaceForm.name,
        location: this.spaceForm.location || null,
        capacity: this.spaceForm.capacity || null,
        resources: this.spaceForm.resources_text.split(",").map((item) => item.trim()).filter(Boolean),
        active: this.spaceForm.active,
        notes: this.spaceForm.notes || null,
      };
      if (this.spaceForm.id) {
        await axios.put(`/api/biblioteca/espacios/${this.spaceForm.id}`, payload);
      } else {
        await axios.post("/api/biblioteca/espacios", payload);
      }
      this.showSpaceModal = false;
      this.$emit("refresh-catalogs");
      await this.loadSpaces();
      await showLibrarySuccess("Espacio guardado correctamente.");
    },
    async saveUsage() {
      const payload = {
        biblioteca_espacio_id: this.usageForm.biblioteca_espacio_id,
        activity_type: this.usageForm.activity_type,
        title: this.usageForm.title,
        course_section_id: this.usageForm.course_section_id || null,
        responsible_staff_id: this.usageForm.responsible_staff_id || null,
        requester_type: this.usageForm.requester_type,
        external_name: this.usageForm.requester_type === "externo" ? this.usageForm.external_name : null,
        external_email: this.usageForm.requester_type === "externo" ? this.usageForm.external_email : null,
        external_institution: this.usageForm.requester_type === "externo" ? this.usageForm.external_institution : null,
        external_phone: this.usageForm.requester_type === "externo" ? this.usageForm.external_phone || null : null,
        attendee_count: this.usageForm.attendee_count || null,
        requested_resources: this.usageForm.requested_resources_text.split(",").map((item) => item.trim()).filter(Boolean),
        start_at: this.usageForm.start_at,
        end_at: this.usageForm.end_at,
        status: this.usageForm.status,
        observations: this.usageForm.observations || null,
        evidence: this.usageForm.evidence_text.split(",").map((item) => item.trim()).filter(Boolean),
      };
      if (this.usageForm.id) {
        await axios.put(`/api/biblioteca/uso-espacios/${this.usageForm.id}`, payload);
      } else {
        await axios.post("/api/biblioteca/uso-espacios", payload);
      }
      this.showUsageModal = false;
      await this.loadUsages(this.pagination.current_page);
      await this.loadCalendar();
      await showLibrarySuccess("Reserva de espacio guardada correctamente.");
    },
    async transition(item, status) {
      const confirmed = await confirmLibraryAction({
        title: "Actualizar estado",
        text: `Se cambiará el estado de "${item.title}" a ${status}.`,
        confirmButtonText: "Confirmar",
      });
      if (!confirmed.isConfirmed) return;
      await axios.post(`/api/biblioteca/uso-espacios/${item.id}/status/${status}`);
      await this.loadUsages(this.pagination.current_page);
      await this.loadCalendar();
      await showLibrarySuccess("Estado actualizado correctamente.");
    },
    exportCalendar() {
      const events = this.calendarOptions.events || [];
      const formatIcsDate = (value) => new Date(value).toISOString().replaceAll("-", "").replaceAll(":", "").replace(".000", "");
      const escapeIcs = (value) => String(value || "").replaceAll("\\", "\\\\").replaceAll(",", "\\,").replaceAll(";", "\\;").replaceAll("\n", "\\n");
      const rows = [
        "BEGIN:VCALENDAR",
        "VERSION:2.0",
        "PRODID:-//AVIS//Biblioteca Escolar//ES",
        "CALSCALE:GREGORIAN",
        ...events.flatMap((event) => [
          "BEGIN:VEVENT",
          `UID:biblioteca-${event.id}@avis`,
          `DTSTAMP:${formatIcsDate(new Date())}`,
          `DTSTART:${formatIcsDate(event.start)}`,
          `DTEND:${formatIcsDate(event.end)}`,
          `SUMMARY:${escapeIcs(event.title)}`,
          `DESCRIPTION:${escapeIcs("Reserva de espacio de biblioteca")}`,
          "END:VEVENT",
        ]),
        "END:VCALENDAR",
      ];
      const blob = new Blob([rows.join("\r\n")], { type: "text/calendar;charset=utf-8" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `calendario-biblioteca-${new Date().toISOString().slice(0, 10)}.ics`;
      link.click();
      URL.revokeObjectURL(url);
    },
    async closeSpaceModal() {
      const confirmed = await confirmLibraryCancel("el formulario del espacio");
      if (confirmed.isConfirmed) this.showSpaceModal = false;
    },
    async closeUsageModal() {
      const confirmed = await confirmLibraryCancel("el formulario de uso de espacio");
      if (confirmed.isConfirmed) this.showUsageModal = false;
    },
  },
};
</script>

<template>
  <div class="space-view">
    <section class="space-head">
      <div><span>AGENDA DE BIBLIOTECA</span><h5>Sala 1 media · Sala 2 básica</h5><p>Reservas internas y solicitudes externas en un calendario único y exportable.</p></div>
      <div class="d-flex gap-2 flex-wrap">
        <LibraryHelpButton
          title="Ayuda: uso de espacios"
          text="Aquí se administran los espacios CRA, sus capacidades, recursos asociados y el calendario diario, semanal o mensual de actividades."
        />
        <BButton variant="light" class="head-button" @click="exportCalendar"><i class="bx bx-calendar-export me-1"></i>Exportar calendario</BButton>
        <BButton variant="outline-secondary" @click="openSpaceCreate">Nuevo espacio</BButton>
        <BButton variant="primary" @click="openUsageCreate">Nueva reserva</BButton>
      </div>
    </section>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-4">
        <BCard class="room-card border-0 h-100">
          <template #header><div class="fw-semibold">Espacios disponibles</div></template>
          <LoadingState v-if="loadingSpaces" message="Cargando espacios..." compact />
          <div v-else class="d-flex flex-column gap-2">
            <div v-for="space in spaces" :key="space.id" class="border rounded p-3">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold">{{ space.name }}</div>
                  <div class="small text-muted">{{ space.location || "Sin ubicación" }} · Capacidad {{ space.capacity || "-" }}</div>
                </div>
                <BButton size="sm" variant="outline-primary" @click="openSpaceEdit(space)">Editar</BButton>
              </div>
            </div>
          </div>
        </BCard>
      </div>
      <div class="col-xl-8">
        <BCard class="calendar-card border-0 h-100">
          <template #header><div class="d-flex justify-content-between align-items-center"><div class="fw-semibold">Calendario de reservas</div><BButton size="sm" variant="outline-primary" @click="exportCalendar">Exportar .ics</BButton></div></template>
          <FullCalendar :options="calendarOptions" />
        </BCard>
      </div>
    </div>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Actividad..." @keyup.enter="loadUsages" /></div>
        <div class="col-md-3"><label class="form-label">Espacio</label><BFormSelect v-model="filters.biblioteca_espacio_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.spaces || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat((catalogs.space_statuses || []).map((item) => ({ value: item.value, text: item.label })))" /></div>
        <div class="col-md-2"><label class="form-label">Curso</label><BFormSelect v-model="filters.course_section_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" /></div>
        <div class="col-md-2"><label class="form-label">Desde</label><BFormInput v-model="filters.date_from" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Hasta</label><BFormInput v-model="filters.date_to" type="date" /></div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="loadUsages">Filtrar</BButton>
          <BButton variant="light" @click="filters = { search: '', biblioteca_espacio_id: null, status: null, course_section_id: null, date_from: '', date_to: '' }; loadUsages();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loadingUsages" message="Cargando reservas de espacios..." compact />
      <BTable
        v-else
        responsive
        :items="usages"
        :fields="[
          { key: 'title', label: 'Actividad' },
          { key: 'espacio', label: 'Espacio' },
          { key: 'start_at', label: 'Inicio' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(espacio)="{ item }">{{ item.espacio?.name || "-" }}</template>
        <template #cell(start_at)="{ item }">{{ formatLibraryDateTime(item.start_at) }}</template>
        <template #cell(status)="{ item }"><LibraryStatusBadge :status="item.status" /></template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-success" @click="transition(item, 'aprobada')">Aprobar</BButton>
            <BButton size="sm" variant="outline-info" @click="transition(item, 'realizada')">Realizada</BButton>
            <BButton size="sm" variant="outline-danger" @click="transition(item, 'cancelada')">Cancelar</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="loadUsages" />
      </div>
    </BCard>

    <BModal v-model="showSpaceModal" title="Espacio de biblioteca" hide-footer>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Nombre</label><BFormInput v-model="spaceForm.name" /></div>
        <div class="col-md-6"><label class="form-label">Ubicación</label><BFormInput v-model="spaceForm.location" /></div>
        <div class="col-md-4"><label class="form-label">Capacidad</label><BFormInput v-model="spaceForm.capacity" type="number" /></div>
        <div class="col-md-8"><label class="form-label">Recursos asociados</label><BFormInput v-model="spaceForm.resources_text" placeholder="Separar por coma" /></div>
        <div class="col-md-4 d-flex align-items-center"><BFormCheckbox v-model="spaceForm.active">Activo</BFormCheckbox></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="spaceForm.notes" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeSpaceModal">Cancelar</BButton>
        <BButton variant="primary" @click="saveSpace">Guardar</BButton>
      </div>
    </BModal>

    <BModal v-model="showUsageModal" size="xl" title="Reserva de espacio" hide-footer>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Espacio</label><BFormSelect v-model="usageForm.biblioteca_espacio_id" :options="(catalogs.spaces || []).map((item) => ({ value: item.id, text: item.name }))" /></div>
        <div class="col-md-4"><label class="form-label">Tipo actividad</label><BFormSelect v-model="usageForm.activity_type" :options="(catalogs.space_activity_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="usageForm.status" :options="(catalogs.space_statuses || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-4"><label class="form-label">Solicitante</label><BFormSelect v-model="usageForm.requester_type" :options="[{value:'interno',text:'Interno del establecimiento'},{value:'externo',text:'Persona o institución externa'}]" /></div>
        <div class="col-md-6"><label class="form-label">Título / actividad</label><BFormInput v-model="usageForm.title" /></div>
        <div class="col-md-3"><label class="form-label">Curso</label><BFormSelect v-model="usageForm.course_section_id" :options="[{ value: null, text: 'Sin curso' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" /></div>
        <div class="col-md-3"><label class="form-label">Docente responsable</label><BFormSelect v-model="usageForm.responsible_staff_id" :options="[{ value: null, text: 'Sin responsable' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))" /></div>
        <template v-if="usageForm.requester_type === 'externo'">
          <div class="col-12"><div class="external-title"><i class="bx bx-buildings"></i> Datos de la solicitud externa</div></div>
          <div class="col-md-4"><label class="form-label">Nombre completo *</label><BFormInput v-model="usageForm.external_name" /></div>
          <div class="col-md-3"><label class="form-label">Correo *</label><BFormInput v-model="usageForm.external_email" type="email" /></div>
          <div class="col-md-3"><label class="form-label">Institución *</label><BFormInput v-model="usageForm.external_institution" /></div>
          <div class="col-md-2"><label class="form-label">Teléfono</label><BFormInput v-model="usageForm.external_phone" /></div>
        </template>
        <div class="col-md-2"><label class="form-label">Asistentes</label><BFormInput v-model="usageForm.attendee_count" type="number" /></div>
        <div class="col-md-5"><label class="form-label">Recursos solicitados</label><BFormInput v-model="usageForm.requested_resources_text" placeholder="Separar por coma" /></div>
        <div class="col-md-2"><label class="form-label">Inicio</label><BFormInput v-model="usageForm.start_at" type="datetime-local" /></div>
        <div class="col-md-2"><label class="form-label">Término</label><BFormInput v-model="usageForm.end_at" type="datetime-local" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="usageForm.observations" rows="2" /></div>
        <div class="col-12"><label class="form-label">Evidencia (URLs)</label><BFormInput v-model="usageForm.evidence_text" placeholder="Separar por coma" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeUsageModal">Cancelar</BButton>
        <BButton variant="primary" @click="saveUsage">Guardar</BButton>
      </div>
    </BModal>
  </div>
</template>

<style scoped>
.space-view{display:flex;flex-direction:column;gap:1rem}.space-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.35rem 1.5rem;color:#fff;border-radius:19px;background:linear-gradient(135deg,#26465f,#31778a 58%,#40a58d);box-shadow:0 16px 36px rgba(38,90,101,.18)}.space-head>div:first-child>span{font-size:.66rem;font-weight:800;letter-spacing:.15em;color:#b8eee3}.space-head h5{color:#fff;margin:.25rem 0}.space-head p{margin:0;color:rgba(255,255,255,.72)}.head-button{color:#245c69!important;font-weight:700}.room-card,.calendar-card{border-radius:16px;box-shadow:0 12px 28px rgba(28,52,86,.08)}.external-title{display:flex;align-items:center;gap:.5rem;padding:.7rem .85rem;color:#8b6320;border-radius:11px;background:#fff7e8;font-weight:750}@media(max-width:900px){.space-head{align-items:flex-start;flex-direction:column}}
</style>
