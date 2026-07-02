<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

const todayDate = () => new Date().toISOString().slice(0, 10);
const nowDateTime = () => new Date().toISOString().slice(0, 16);

const emptyShiftForm = () => ({
  id: null,
  staff_id: null,
  schedule_type: "single",
  scheduled_start_at: "",
  scheduled_end_at: "",
  weekdays: [],
  template_start_time: "22:00",
  template_end_time: "07:00",
  recurrence_starts_on: todayDate(),
  recurrence_ends_on: "",
  coverage_label: "Todo el colegio",
  general_observations: "",
});

const emptyRoundForm = () => ({
  recorded_at: nowDateTime(),
  observations: "",
  overall_status: null,
  nochero_confirmation_name: "",
  latitude: "",
  longitude: "",
  location_accuracy: "",
  sectors: [],
  incidents: [],
  roundEvidenceFiles: [],
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      acting: false,
      error: null,
      catalogs: {
        shift_statuses: [],
        schedule_types: [],
        weekday_options: [],
        round_statuses: [],
        sector_states: [],
        priorities: [],
        incident_statuses: [],
        staff: [],
        inventory_items: [],
        responsible_users: [],
        current_user: {},
        capabilities: {},
      },
      filters: {
        search: "",
        status: null,
        staff_id: null,
        from: "",
        to: "",
      },
      shifts: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      selectedShift: null,
      showShiftModal: false,
      showRoundModal: false,
      shiftForm: emptyShiftForm(),
      roundForm: emptyRoundForm(),
      logoDataUrl: null,
      sectorSuggestions: [
        "Acceso principal",
        "Portería",
        "Patio central",
        "Pasillo primer piso",
        "Pasillo segundo piso",
        "Casino",
        "Gimnasio",
        "Biblioteca",
        "Laboratorio",
        "Sala de profesores",
        "Oficinas administrativas",
        "Bodega",
        "Estacionamiento",
      ],
    };
  },
  computed: {
    canManageShifts() {
      return Boolean(this.catalogs.capabilities?.can_manage_shifts);
    },
    canRegisterRounds() {
      return Boolean(this.catalogs.capabilities?.can_register_rounds);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    staffOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.staff || []).map((item) => ({ value: item.id, label: item.full_name }))
      );
    },
    shiftStaffOptions() {
      return (this.catalogs.staff || []).map((item) => ({ value: item.id, label: item.full_name }));
    },
    scheduleTypeOptions() {
      return (this.catalogs.schedule_types || []).map((item) => ({ value: item.value, label: item.label }));
    },
    weekdayOptions() {
      return (this.catalogs.weekday_options || []).map((item) => ({ value: item.value, label: item.label }));
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.shift_statuses || []).map((item) => ({ value: item.value, label: item.label }))
      );
    },
    sectorStateOptions() {
      return (this.catalogs.sector_states || []).map((item) => ({ value: item.value, label: item.label }));
    },
    priorityOptions() {
      return (this.catalogs.priorities || []).map((item) => ({ value: item.value, label: item.label }));
    },
    roundStatusOptions() {
      return (this.catalogs.round_statuses || []).map((item) => ({ value: item.value, label: item.label }));
    },
    responsibleUserOptions() {
      return (this.catalogs.responsible_users || []).map((item) => ({
        value: item.id,
        label: item.staff?.full_name ? `${item.staff.full_name} (${item.name})` : item.name,
      }));
    },
    inventoryOptions() {
      return [{ value: null, label: "Sin bien asociado" }].concat(
        (this.catalogs.inventory_items || []).map((item) => ({
          value: item.id,
          label: `${item.code || "-"} · ${item.name}`,
        }))
      );
    },
    isWeeklyForm() {
      return this.shiftForm.schedule_type === "weekly";
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadShifts();
    this.loadLogo();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/security/catalogs");
      this.catalogs = response.data;
      if (!this.roundForm.nochero_confirmation_name) {
        this.roundForm.nochero_confirmation_name = this.catalogs.current_user?.name || "";
      }
    },
    async loadShifts(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/security/shifts", {
          params: { page, ...this.filters },
        });
        this.shifts = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openShift(shiftOrId) {
      const shiftId = typeof shiftOrId === "object" ? shiftOrId.id : shiftOrId;
      const response = await axios.get(`/api/security/shifts/${shiftId}`);
      this.selectedShift = response.data.data;
    },
    newShift() {
      this.shiftForm = emptyShiftForm();
      this.showShiftModal = true;
    },
    editShift() {
      if (!this.selectedShift) return;
      this.shiftForm = {
        id: this.selectedShift.id,
        staff_id: this.selectedShift.staff_id,
        schedule_type: this.selectedShift.schedule_type || "single",
        scheduled_start_at: this.toInputDateTime(this.selectedShift.scheduled_start_at),
        scheduled_end_at: this.toInputDateTime(this.selectedShift.scheduled_end_at),
        weekdays: this.selectedShift.weekdays || [],
        template_start_time: this.selectedShift.template_start_time ? String(this.selectedShift.template_start_time).slice(0, 5) : "22:00",
        template_end_time: this.selectedShift.template_end_time ? String(this.selectedShift.template_end_time).slice(0, 5) : "07:00",
        recurrence_starts_on: this.toInputDate(this.selectedShift.recurrence_starts_on) || todayDate(),
        recurrence_ends_on: this.toInputDate(this.selectedShift.recurrence_ends_on),
        coverage_label: "Todo el colegio",
        general_observations: this.selectedShift.general_observations || "",
      };
      this.showShiftModal = true;
    },
    async saveShift() {
      const validationMessage = this.validateShiftForm();
      if (validationMessage) {
        await this.showWarning(validationMessage);
        return;
      }

      this.saving = true;
      this.error = null;
      try {
        const payload = {
          ...this.shiftForm,
          weekdays: this.ensureArray(this.shiftForm.weekdays),
          coverage_label: "Todo el colegio",
        };
        const response = this.shiftForm.id
          ? await axios.put(`/api/security/shifts/${this.shiftForm.id}`, payload)
          : await axios.post("/api/security/shifts", payload);

        this.showShiftModal = false;
        await this.loadShifts(this.pagination.current_page);
        if (response.data?.data?.id) {
          await this.openShift(response.data.data.id);
        }
        await this.showSuccess(this.shiftForm.id ? "Turno actualizado correctamente." : "Turno creado correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.saving = false;
      }
    },
    async startShift() {
      if (!this.selectedShift) return;

      const result = await Swal.fire({
        icon: "question",
        title: this.selectedShift.is_weekly_template ? "Iniciar turno semanal de hoy" : "Iniciar turno",
        text: this.selectedShift.is_weekly_template
          ? "Se generará automáticamente la instancia del día para este turno semanal."
          : "El turno quedará marcado como en curso.",
        showCancelButton: true,
        confirmButtonText: "Iniciar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      this.acting = true;
      try {
        const response = await axios.post(`/api/security/shifts/${this.selectedShift.id}/start`);
        const activeShift = response.data?.data;
        await this.loadShifts(this.pagination.current_page);
        if (activeShift?.id) {
          await this.openShift(activeShift.id);
        }
        await this.showSuccess("Turno iniciado correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.acting = false;
      }
    },
    async finishShift() {
      if (!this.selectedShift) return;

      const result = await Swal.fire({
        icon: "warning",
        title: "Cerrar turno",
        input: "textarea",
        inputLabel: "Observaciones de cierre",
        inputPlaceholder: "Resumen de cierre del turno...",
        showCancelButton: true,
        confirmButtonText: "Cerrar turno",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      this.acting = true;
      try {
        await axios.post(`/api/security/shifts/${this.selectedShift.id}/finish`, {
          closing_observations: result.value || null,
        });
        await this.openShift(this.selectedShift.id);
        await this.loadShifts(this.pagination.current_page);
        await this.showSuccess("Turno cerrado correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.acting = false;
      }
    },
    openRoundModal() {
      if (!this.selectedShift || this.selectedShift.is_weekly_template) return;
      this.roundForm = emptyRoundForm();
      this.roundForm.nochero_confirmation_name = this.catalogs.current_user?.name || "";
      this.addSector();
      this.showRoundModal = true;
    },
    addSector() {
      this.roundForm.sectors.push({
        temp_key: `sector_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
        sector_name: "",
        sector_state: "sin_novedad",
        observations: "",
      });
    },
    addIncident() {
      this.roundForm.incidents.push({
        temp_key: `incident_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
        title: "",
        description: "",
        priority: "media",
        sector_temp_key: this.roundForm.sectors[0]?.temp_key || null,
        assignee_user_ids: [],
        inventory_item_id: null,
        evidenceFiles: [],
      });
    },
    removeSector(index) {
      this.roundForm.sectors.splice(index, 1);
    },
    removeIncident(index) {
      this.roundForm.incidents.splice(index, 1);
    },
    extractSelectedFiles(payload) {
      if (payload?.target?.files) {
        return Array.from(payload.target.files);
      }

      if (payload?.files) {
        return Array.from(payload.files);
      }

      if (Array.isArray(payload)) {
        return payload.filter((item) => item instanceof File);
      }

      if (payload instanceof File) {
        return [payload];
      }

      return [];
    },
    mergeFiles(currentFiles, incomingFiles) {
      const merged = [...(currentFiles || []), ...(incomingFiles || [])];
      const unique = [];
      const seen = new Set();

      for (const file of merged) {
        const key = `${file.name}-${file.size}-${file.lastModified}`;
        if (!seen.has(key)) {
          seen.add(key);
          unique.push(file);
        }
      }

      return unique;
    },
    onRoundEvidenceChange(payload) {
      this.roundForm.roundEvidenceFiles = this.extractSelectedFiles(payload);
    },
    onRoundCameraChange(payload) {
      this.roundForm.roundEvidenceFiles = this.mergeFiles(
        this.roundForm.roundEvidenceFiles,
        this.extractSelectedFiles(payload)
      );
    },
    onIncidentEvidenceChange(payload, incident) {
      incident.evidenceFiles = this.extractSelectedFiles(payload);
    },
    onIncidentCameraChange(payload, incident) {
      incident.evidenceFiles = this.mergeFiles(incident.evidenceFiles, this.extractSelectedFiles(payload));
    },
    openFilePicker(refName) {
      const target = this.$refs[refName];
      const element = Array.isArray(target) ? target[0] : target;
      element?.click();
    },
    incidentEvidenceNames(incident) {
      return (incident.evidenceFiles || []).map((file) => file.name).join(", ");
    },
    roundEvidenceNames() {
      return (this.roundForm.roundEvidenceFiles || []).map((file) => file.name).join(", ");
    },
    clearIncidentEvidence(incident, refName) {
      incident.evidenceFiles = [];
      [refName, refName.replace("Gallery", "Camera")].forEach((currentRef) => {
        const target = this.$refs[currentRef];
        const element = Array.isArray(target) ? target[0] : target;
        if (element) {
          element.value = "";
        }
      });
    },
    clearRoundEvidence() {
      this.roundForm.roundEvidenceFiles = [];
      const refs = ["roundGalleryInput", "roundCameraInput"];
      refs.forEach((refName) => {
        const target = this.$refs[refName];
        const element = Array.isArray(target) ? target[0] : target;
        if (element) {
          element.value = "";
        }
      });
    },
    async captureLocation() {
      if (!navigator.geolocation) {
        await this.showWarning("Este dispositivo no permite geolocalización.");
        return;
      }

      navigator.geolocation.getCurrentPosition(
        (position) => {
          this.roundForm.latitude = position.coords.latitude;
          this.roundForm.longitude = position.coords.longitude;
          this.roundForm.location_accuracy = position.coords.accuracy;
          this.showSuccess("Ubicación capturada.");
        },
        async () => {
          await this.showError("No fue posible obtener la ubicación actual.");
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    },
    validateShiftForm() {
      if (!this.shiftForm.staff_id) {
        return "Debes seleccionar el nochero o funcionario del turno.";
      }

      if (this.isWeeklyForm) {
        if (!this.shiftForm.weekdays.length) {
          return "Debes seleccionar al menos un día de la semana.";
        }
        if (!this.shiftForm.template_start_time || !this.shiftForm.template_end_time) {
          return "Debes definir horario de inicio y término para el turno semanal.";
        }
        if (!this.shiftForm.recurrence_starts_on) {
          return "Debes indicar desde cuándo aplica la plantilla semanal.";
        }
      } else if (!this.shiftForm.scheduled_start_at || !this.shiftForm.scheduled_end_at) {
        return "Debes indicar fecha y hora de inicio y término.";
      }

      return null;
    },
    validateRoundPayload() {
      if (!this.roundForm.sectors.length) {
        return "Debes registrar al menos un sector revisado.";
      }

      for (const sector of this.roundForm.sectors) {
        if (!sector.sector_name || !String(sector.sector_name).trim()) {
          return "Cada sector revisado debe tener un nombre visible.";
        }
      }

      for (const incident of this.roundForm.incidents) {
        if (!incident.title || !incident.description) {
          return "Cada novedad debe tener título y descripción.";
        }
        if (["alta", "critica"].includes(incident.priority) && !(incident.evidenceFiles || []).length) {
          return "Las novedades altas o críticas deben incluir evidencia fotográfica.";
        }
      }

      return null;
    },
    async saveRound() {
      if (!this.selectedShift) return;
      const validationError = this.validateRoundPayload();
      if (validationError) {
        await this.showWarning(validationError);
        return;
      }

      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        const payload = {
          recorded_at: this.roundForm.recorded_at,
          observations: this.roundForm.observations,
          overall_status: this.roundForm.overall_status,
          nochero_confirmation_name: this.roundForm.nochero_confirmation_name,
          latitude: this.roundForm.latitude || null,
          longitude: this.roundForm.longitude || null,
          location_accuracy: this.roundForm.location_accuracy || null,
          sectors: this.roundForm.sectors.map((sector) => ({
            temp_key: sector.temp_key,
            sector_name: sector.sector_name,
            sector_state: sector.sector_state,
            observations: sector.observations,
          })),
          round_evidence_keys: [],
          incidents: [],
        };

        (this.roundForm.roundEvidenceFiles || []).forEach((file, index) => {
          const key = `round_${index}`;
          payload.round_evidence_keys.push(key);
          formData.append(`evidence_files[${key}]`, file);
        });

        this.roundForm.incidents.forEach((incident) => {
          const evidenceKeys = [];
          (incident.evidenceFiles || []).forEach((file, index) => {
            const key = `${incident.temp_key}_${index}`;
            evidenceKeys.push(key);
            formData.append(`evidence_files[${key}]`, file);
          });

          payload.incidents.push({
            temp_key: incident.temp_key,
            title: incident.title,
            description: incident.description,
            priority: incident.priority,
            sector_temp_key: incident.sector_temp_key,
            sector_name: this.sectorLabel(incident.sector_temp_key),
            assignee_user_ids: this.ensureArray(incident.assignee_user_ids),
            inventory_item_id: incident.inventory_item_id,
            evidence_keys: evidenceKeys,
          });
        });

        formData.append("payload", JSON.stringify(payload));

        await axios.post(`/api/security/shifts/${this.selectedShift.id}/rounds`, formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        this.showRoundModal = false;
        await this.openShift(this.selectedShift.id);
        await this.loadShifts(this.pagination.current_page);
        await this.showSuccess("Ronda registrada y acta generada correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.saving = false;
      }
    },
    async loadLogo() {
      try {
        const response = await fetch("/brand/logo-cnsc.png");
        if (!response.ok) return;
        const blob = await response.blob();
        this.logoDataUrl = await this.blobToDataUrl(blob);
      } catch (error) {
        this.logoDataUrl = null;
      }
    },
    blobToDataUrl(blob) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },
    async exportShiftPdf() {
      if (!this.selectedShift || !this.canExport) return;
      const pdfMake = getPdfMake();
      const rounds = this.selectedShift.rounds || [];
      const body = [];

      rounds.forEach((round) => {
        body.push([{ text: `Ronda #${round.round_number}`, colSpan: 4, bold: true, fillColor: "#eef3ff" }, {}, {}, {}]);
        body.push(["Hora", "Sectores", "Novedades", "Estado"]);
        body.push([
          this.formatDateTime(round.recorded_at),
          (round.sectors || []).map((sector) => sector.sector_name).join(", ") || "-",
          (round.incidents || []).map((incident) => `${incident.title} (${incident.priority})`).join("\n") || "Sin novedades",
          round.overall_status,
        ]);
      });

      pdfMake.createPdf({
        content: [
          this.logoDataUrl
            ? {
                columns: [
                  { image: this.logoDataUrl, width: 70 },
                  {
                    width: "*",
                    stack: [
                      { text: "Acta de rondas de seguridad", style: "title" },
                      { text: this.selectedShift.staff?.full_name || "-", color: "#6c757d" },
                    ],
                  },
                ],
                margin: [0, 0, 0, 12],
              }
            : { text: "Acta de rondas de seguridad", style: "title" },
          { text: "Cobertura: Todo el colegio", margin: [0, 0, 0, 4] },
          { text: `Turno: ${this.shiftWindowLabel(this.selectedShift)}`, margin: [0, 0, 0, 10] },
          {
            table: {
              headerRows: 0,
              widths: [110, "*", "*", 85],
              body: body.length ? body : [["Sin rondas registradas", "", "", ""]],
            },
            layout: "lightHorizontalLines",
          },
        ],
        styles: {
          title: { fontSize: 18, bold: true },
        },
        defaultStyle: { fontSize: 10 },
      }).download(`acta-rondas-turno-${this.selectedShift.id}.pdf`);
    },
    sectorOptions() {
      return this.roundForm.sectors.map((sector) => ({
        value: sector.temp_key,
        label: sector.sector_name || "Sector en edición",
      }));
    },
    sectorLabel(tempKey) {
      return this.roundForm.sectors.find((sector) => sector.temp_key === tempKey)?.sector_name || "Todo el colegio";
    },
    shiftWindowLabel(shift) {
      if (!shift) return "-";
      if (shift.is_weekly_template) {
        return shift.next_occurrence_at
          ? `${this.formatDateTime(shift.next_occurrence_at)} a ${this.formatDateTime(shift.next_occurrence_end_at)}`
          : shift.schedule_summary;
      }
      return `${this.formatDateTime(shift.scheduled_start_at)} a ${this.formatDateTime(shift.scheduled_end_at)}`;
    },
    shiftListDate(shift) {
      return shift.is_weekly_template ? shift.next_occurrence_at || shift.recurrence_starts_on : shift.scheduled_start_at;
    },
    shiftStatusVariant(shift) {
      if (shift.is_weekly_template) return "bg-info-subtle text-info";
      return shift.status === "en_curso"
        ? "bg-success-subtle text-success"
        : shift.status === "finalizado"
        ? "bg-secondary-subtle text-secondary"
        : shift.status === "cancelado"
        ? "bg-danger-subtle text-danger"
        : "bg-primary-subtle text-primary";
    },
    toInputDateTime(value) {
      return value ? String(value).slice(0, 16) : "";
    },
    toInputDate(value) {
      return value ? String(value).slice(0, 10) : "";
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Turnos y rondas</h4>
        <div class="text-muted">Los turnos cubren todo el colegio y pueden programarse por fecha o como plantilla semanal.</div>
      </div>
      <div class="d-flex gap-2">
        <BButton variant="outline-secondary" @click="loadShifts()">Actualizar</BButton>
        <BButton v-if="canManageShifts" variant="primary" @click="newShift">Nuevo turno</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-lg-4">
          <label class="form-label">Búsqueda</label>
          <BFormInput v-model="filters.search" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.status" :options="statusOptions" :searchable="true" />
        </div>
        <div class="col-lg-3">
          <label class="form-label">Funcionario</label>
          <Multiselect v-model="filters.staff_id" :options="staffOptions" :searchable="true" />
        </div>
        <div class="col-lg-1">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.from" type="date" />
        </div>
        <div class="col-lg-1">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.to" type="date" />
        </div>
        <div class="col-lg-1 d-flex align-items-end">
          <BButton variant="primary" class="w-100" @click="loadShifts(1)">Filtrar</BButton>
        </div>
      </div>
    </BCard>

    <div class="row g-3">
      <div class="col-xl-5">
        <BCard title="Turnos programados y plantillas semanales">
          <LoadingState v-if="loading" message="Cargando turnos..." compact />
          <div v-else class="vstack gap-3">
            <button
              v-for="shift in shifts"
              :key="shift.id"
              type="button"
              class="btn text-start border rounded-3 p-3 shift-card"
              :class="{ 'border-primary bg-primary-subtle': selectedShift?.id === shift.id }"
              @click="openShift(shift)"
            >
              <div class="d-flex justify-content-between gap-2">
                <div>
                  <div class="fw-semibold">{{ shift.staff?.full_name || "-" }}</div>
                  <div class="small text-muted">{{ shift.schedule_summary }}</div>
                </div>
                <span class="badge" :class="shiftStatusVariant(shift)">
                  {{ shift.is_weekly_template ? "semanal" : shift.status }}
                </span>
              </div>
              <div class="small mt-2">{{ shiftWindowLabel(shift) }}</div>
              <div class="small text-muted">Cobertura: Todo el colegio</div>
              <div class="small text-muted">
                Rondas: {{ shift.rounds_count }} · Pendientes: {{ shift.pending_incidents_count }}
              </div>
            </button>
            <div v-if="!shifts.length" class="text-muted">No hay turnos registrados con los filtros actuales.</div>
          </div>
        </BCard>
      </div>

      <div class="col-xl-7">
        <BCard v-if="selectedShift" class="h-100">
          <template #header>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div>
                <div class="fw-semibold">{{ selectedShift.staff?.full_name || "-" }}</div>
                <div class="small text-muted">{{ selectedShift.schedule_summary }}</div>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <BButton
                  v-if="canRegisterRounds && (selectedShift.status === 'programado' || selectedShift.is_weekly_template)"
                  size="sm"
                  variant="success"
                  :disabled="acting"
                  @click="startShift"
                >
                  {{ selectedShift.is_weekly_template ? "Iniciar turno de hoy" : "Iniciar turno" }}
                </BButton>
                <BButton
                  v-if="selectedShift.status !== 'finalizado' && selectedShift.status !== 'cancelado' && canRegisterRounds && !selectedShift.is_weekly_template"
                  size="sm"
                  variant="primary"
                  @click="openRoundModal"
                >
                  Registrar ronda
                </BButton>
                <BButton
                  v-if="selectedShift.status === 'en_curso' && canRegisterRounds && !selectedShift.is_weekly_template"
                  size="sm"
                  variant="outline-danger"
                  :disabled="acting"
                  @click="finishShift"
                >
                  Cerrar turno
                </BButton>
                <BButton v-if="canManageShifts" size="sm" variant="outline-secondary" @click="editShift">Editar</BButton>
                <BButton v-if="canExport && !selectedShift.is_weekly_template" size="sm" variant="outline-primary" @click="exportShiftPdf">Acta PDF</BButton>
              </div>
            </div>
          </template>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="small text-muted">Programación</div>
              <div>{{ shiftWindowLabel(selectedShift) }}</div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Estado</div>
              <div>{{ selectedShift.is_weekly_template ? "Plantilla semanal" : selectedShift.status }}</div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Cobertura</div>
              <div>Todo el colegio</div>
            </div>
          </div>

          <div class="mb-3">
            <div class="small text-muted">Observaciones generales</div>
            <div>{{ selectedShift.general_observations || "Sin observaciones generales." }}</div>
          </div>

          <div v-if="selectedShift.is_weekly_template" class="alert alert-info mb-3">
            Esta plantilla semanal no acumula rondas directamente. Al iniciar el turno se crea la instancia real del día y desde ahí se registran las rondas.
          </div>

          <div v-if="selectedShift.generated_shifts?.length" class="mb-4">
            <div class="fw-semibold mb-2">Últimas instancias generadas</div>
            <div class="vstack gap-2">
              <div v-for="generated in selectedShift.generated_shifts" :key="generated.id" class="border rounded-3 p-2">
                <div class="d-flex justify-content-between gap-2">
                  <div>{{ formatDateTime(generated.scheduled_start_at) }}</div>
                  <span class="badge bg-light text-dark">{{ generated.status }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="vstack gap-3">
            <div v-for="round in selectedShift.rounds || []" :key="round.id" class="border rounded-3 p-3">
              <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                  <div class="fw-semibold">Ronda #{{ round.round_number }}</div>
                  <div class="small text-muted">{{ formatDateTime(round.recorded_at) }} · {{ round.act_number }}</div>
                </div>
                <span class="badge bg-light text-dark">{{ round.overall_status }}</span>
              </div>
              <div class="small mt-2">{{ round.observations || "Sin observaciones generales." }}</div>
              <div class="small text-muted mt-2">
                Sectores:
                {{ (round.sectors || []).map((sector) => sector.sector_name).join(", ") || "-" }}
              </div>
              <div v-if="round.incidents?.length" class="mt-3">
                <div class="fw-semibold small mb-2">Novedades</div>
                <div class="vstack gap-2">
                  <div v-for="incident in round.incidents" :key="incident.id" class="bg-light rounded-3 p-2">
                    <div class="d-flex justify-content-between gap-2">
                      <div class="fw-semibold">{{ incident.title }}</div>
                      <span class="badge bg-warning text-dark">{{ incident.priority }}</span>
                    </div>
                    <div class="small">{{ incident.description }}</div>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="!selectedShift.is_weekly_template && !(selectedShift.rounds || []).length" class="text-muted">
              Aún no hay rondas registradas para este turno.
            </div>
          </div>
        </BCard>

        <BCard v-else class="h-100">
          <div class="text-muted">Selecciona un turno para ver su detalle, iniciar el recorrido o registrar rondas.</div>
        </BCard>
      </div>
    </div>

    <BModal v-model="showShiftModal" :title="shiftForm.id ? 'Editar turno' : 'Nuevo turno'" size="lg" hide-footer>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Funcionario</label>
          <Multiselect v-model="shiftForm.staff_id" :options="shiftStaffOptions" :searchable="true" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Tipo de programación</label>
          <Multiselect v-model="shiftForm.schedule_type" :options="scheduleTypeOptions" :searchable="false" />
        </div>

        <template v-if="!isWeeklyForm">
          <div class="col-md-6">
            <label class="form-label">Inicio</label>
            <BFormInput v-model="shiftForm.scheduled_start_at" type="datetime-local" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Término</label>
            <BFormInput v-model="shiftForm.scheduled_end_at" type="datetime-local" />
          </div>
        </template>

        <template v-else>
          <div class="col-md-6">
            <label class="form-label">Días de la semana</label>
            <Multiselect
              v-model="shiftForm.weekdays"
              :options="weekdayOptions"
              mode="tags"
              :searchable="true"
              :close-on-select="false"
            />
          </div>
          <div class="col-md-3">
            <label class="form-label">Hora inicio</label>
            <BFormInput v-model="shiftForm.template_start_time" type="time" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Hora término</label>
            <BFormInput v-model="shiftForm.template_end_time" type="time" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Vigente desde</label>
            <BFormInput v-model="shiftForm.recurrence_starts_on" type="date" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Vigente hasta</label>
            <BFormInput v-model="shiftForm.recurrence_ends_on" type="date" />
          </div>
        </template>

        <div class="col-12">
          <label class="form-label">Cobertura</label>
          <BFormInput :model-value="'Todo el colegio'" disabled />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones generales</label>
          <BFormTextarea v-model="shiftForm.general_observations" rows="3" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="outline-secondary" @click="showShiftModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="saveShift">Guardar</BButton>
      </div>
    </BModal>

    <BModal v-model="showRoundModal" title="Registrar ronda" size="xl" hide-footer>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Fecha y hora</label>
          <BFormInput v-model="roundForm.recorded_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado general</label>
          <Multiselect v-model="roundForm.overall_status" :options="roundStatusOptions" :searchable="true" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Confirmación del nochero</label>
          <BFormInput v-model="roundForm.nochero_confirmation_name" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones generales</label>
          <BFormTextarea v-model="roundForm.observations" rows="2" />
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <div class="fw-semibold">Sectores revisados</div>
        <BButton size="sm" variant="outline-primary" @click="addSector">Agregar sector</BButton>
      </div>
      <div class="vstack gap-3">
        <div v-for="(sector, index) in roundForm.sectors" :key="sector.temp_key" class="border rounded-3 p-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Sector</label>
              <BFormInput v-model="sector.sector_name" list="security-sector-suggestions" placeholder="Ejemplo: Patio central" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Estado</label>
              <Multiselect v-model="sector.sector_state" :options="sectorStateOptions" :searchable="true" />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="sector.observations" rows="2" />
            </div>
          </div>
          <div class="text-end mt-2">
            <BButton v-if="roundForm.sectors.length > 1" size="sm" variant="outline-danger" @click="removeSector(index)">Quitar sector</BButton>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <div class="fw-semibold">Novedades detectadas</div>
        <BButton size="sm" variant="outline-danger" @click="addIncident">Agregar novedad</BButton>
      </div>
      <div v-if="!roundForm.incidents.length" class="text-muted mb-3">Sin novedades registradas por ahora.</div>
      <div class="vstack gap-3">
        <div v-for="(incident, index) in roundForm.incidents" :key="incident.temp_key" class="border rounded-3 p-3 bg-light-subtle">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Título</label>
              <BFormInput v-model="incident.title" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Prioridad</label>
              <Multiselect v-model="incident.priority" :options="priorityOptions" :searchable="true" />
            </div>
            <div class="col-md-5">
              <label class="form-label">Sector relacionado</label>
              <Multiselect v-model="incident.sector_temp_key" :options="sectorOptions()" :searchable="true" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Responsables</label>
              <Multiselect
                v-model="incident.assignee_user_ids"
                :options="responsibleUserOptions"
                mode="tags"
                :searchable="true"
                :close-on-select="false"
              />
            </div>
            <div class="col-md-6">
              <label class="form-label">Bien asociado</label>
              <Multiselect v-model="incident.inventory_item_id" :options="inventoryOptions" :searchable="true" />
            </div>
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <BFormTextarea v-model="incident.description" rows="2" />
            </div>
            <div class="col-12">
              <label class="form-label">Evidencia fotográfica</label>
              <div class="d-flex flex-wrap gap-2">
                <input
                  :ref="`incidentGalleryInput_${incident.temp_key}`"
                  class="d-none"
                  type="file"
                  accept="image/*"
                  multiple
                  @change="onIncidentEvidenceChange($event, incident)"
                />
                <input
                  :ref="`incidentCameraInput_${incident.temp_key}`"
                  class="d-none"
                  type="file"
                  accept="image/*"
                  capture="environment"
                  @change="onIncidentCameraChange($event, incident)"
                />
                <BButton
                  size="sm"
                  variant="outline-secondary"
                  @click="openFilePicker(`incidentGalleryInput_${incident.temp_key}`)"
                >
                  Elegir archivos
                </BButton>
                <BButton
                  size="sm"
                  variant="outline-primary"
                  @click="openFilePicker(`incidentCameraInput_${incident.temp_key}`)"
                >
                  Tomar foto
                </BButton>
                <BButton
                  v-if="(incident.evidenceFiles || []).length"
                  size="sm"
                  variant="outline-danger"
                  @click="clearIncidentEvidence(incident, `incidentGalleryInput_${incident.temp_key}`)"
                >
                  Limpiar
                </BButton>
              </div>
              <div class="small text-muted mt-2">
                {{ incidentEvidenceNames(incident) || "Sin archivos seleccionados." }}
              </div>
            </div>
          </div>
          <div class="text-end mt-2">
            <BButton size="sm" variant="outline-danger" @click="removeIncident(index)">Quitar novedad</BButton>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-3">
        <div class="col-md-6">
          <label class="form-label">Evidencia general de la ronda</label>
          <div class="d-flex flex-wrap gap-2">
            <input
              ref="roundGalleryInput"
              class="d-none"
              type="file"
              accept="image/*"
              multiple
              @change="onRoundEvidenceChange"
            />
            <input
              ref="roundCameraInput"
              class="d-none"
              type="file"
              accept="image/*"
              capture="environment"
              @change="onRoundCameraChange"
            />
            <BButton size="sm" variant="outline-secondary" @click="openFilePicker('roundGalleryInput')">
              Elegir archivos
            </BButton>
            <BButton size="sm" variant="outline-primary" @click="openFilePicker('roundCameraInput')">
              Tomar foto
            </BButton>
            <BButton
              v-if="(roundForm.roundEvidenceFiles || []).length"
              size="sm"
              variant="outline-danger"
              @click="clearRoundEvidence"
            >
              Limpiar
            </BButton>
          </div>
          <div class="small text-muted mt-2">
            {{ roundEvidenceNames() || "Sin archivos seleccionados." }}
          </div>
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <BButton variant="outline-secondary" class="w-100" @click="captureLocation">Capturar geolocalización</BButton>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="outline-secondary" @click="showRoundModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="saveRound">Guardar ronda</BButton>
      </div>

      <datalist id="security-sector-suggestions">
        <option v-for="sector in sectorSuggestions" :key="sector" :value="sector"></option>
      </datalist>
    </BModal>
  </Layout>
</template>

<style scoped>
.shift-card {
  transition: all 0.18s ease;
}

.shift-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
}
</style>
