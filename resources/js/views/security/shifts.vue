<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

const toLocalDateInput = (value = new Date()) => {
  const date = new Date(value);
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 10);
};
const toLocalDateTimeInput = (value = new Date()) => {
  const date = new Date(value);
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 16);
};
const todayDate = () => toLocalDateInput();
const nowDateTime = () => toLocalDateTimeInput();

const emptyShiftForm = () => ({
  id: null,
  staff_id: null,
  weekdays: [],
  recurrence_starts_on: todayDate(),
  recurrence_ends_on: "",
  coverage_label: "Todo el colegio",
  general_observations: "",
});

const emptyRoundForm = () => ({
  administrative_entry: false,
  occurrence_date: "",
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
    isSuperAdmin() {
      return Boolean(this.catalogs.current_user?.is_superadmin);
    },
    canRegisterRounds() {
      return Boolean(this.catalogs.capabilities?.can_register_rounds);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    shiftStaffOptions() {
      return (this.catalogs.staff || []).map((item) => ({ value: item.id, label: item.full_name }));
    },
    weekdayOptions() {
      return (this.catalogs.weekday_options || []).map((item) => ({ value: item.value, label: item.label }));
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
    selectedRounds() {
      if (!this.selectedShift) return [];
      return this.selectedShift.is_weekly_template
        ? this.selectedShift.recent_rounds || []
        : this.selectedShift.rounds || [];
    },
    registrationOpen() {
      return Boolean(this.selectedShift?.registration?.can_register);
    },
    assignedTodayCount() {
      return this.shifts.filter((shift) => shift.registration?.open).length;
    },
    weeklyCoverage() {
      return this.weekdayOptions.map((day, index) => ({
        ...day,
        exitLabel: this.weekdayOptions[(index + 1) % this.weekdayOptions.length]?.label || day.label,
        shifts: this.shifts.filter((shift) => (shift.weekdays || []).includes(day.value)),
      }));
    },
    selectedWorkNights() {
      if (!this.selectedShift) return [];
      return this.weeklyCoverage.filter((day) => (this.selectedShift.weekdays || []).includes(day.value));
    },
    scheduledStaffIds() {
      return new Set(this.shifts.map((shift) => Number(shift.staff_id)));
    },
    availableStaffOptions() {
      const editingStaffId = Number(this.shiftForm.staff_id || 0);
      return this.shiftStaffOptions.filter(
        (option) => Number(option.value) === editingStaffId || !this.scheduledStaffIds.has(Number(option.value))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadShifts();
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
          params: { page, per_page: 100, templates_only: 1 },
        });
        this.shifts = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };

        const selectedId = this.selectedShift?.is_weekly_template ? this.selectedShift.id : null;
        const preferred = this.shifts.find((shift) => shift.id === selectedId)
          || this.shifts.find((shift) => Number(shift.staff_id) === Number(this.catalogs.current_user?.staff_id))
          || this.shifts[0];
        if (preferred) {
          await this.openShift(preferred.id);
        } else {
          this.selectedShift = null;
        }
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    async openShift(shiftOrId) {
      const shiftId = typeof shiftOrId === "object" ? shiftOrId.id : shiftOrId;
      const response = await axios.get(`/api/security/shifts/${shiftId}`);
      this.selectedShift = {
        ...response.data.data,
        recent_rounds: response.data.recent_rounds || [],
      };
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
        weekdays: this.selectedShift.weekdays || [],
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
          staff_id: this.shiftForm.staff_id,
          schedule_type: "weekly",
          weekdays: this.ensureArray(this.shiftForm.weekdays),
          recurrence_starts_on: this.shiftForm.recurrence_starts_on || todayDate(),
          recurrence_ends_on: this.shiftForm.recurrence_ends_on || null,
          coverage_label: "Todo el colegio",
          general_observations: this.shiftForm.general_observations || null,
        };
        const response = this.shiftForm.id
          ? await axios.put(`/api/security/shifts/${this.shiftForm.id}`, payload)
          : await axios.post("/api/security/shifts", payload);

        this.showShiftModal = false;
        await this.loadShifts(this.pagination.current_page);
        if (response.data?.data?.id) {
          await this.openShift(response.data.data.id);
        }
        await this.showSuccess(this.shiftForm.id ? "Días actualizados correctamente." : "Días asignados correctamente.");
      } catch (error) {
        this.error = this.formatError(error);
        await this.showError(this.error);
      } finally {
        this.saving = false;
      }
    },
    openRoundModal(administrativeEntry = false) {
      const isAdministrativeEntry = Boolean(administrativeEntry && this.isSuperAdmin);
      if (!this.selectedShift || (!isAdministrativeEntry && !this.registrationOpen)) return;

      this.roundForm = emptyRoundForm();
      this.roundForm.administrative_entry = isAdministrativeEntry;
      if (isAdministrativeEntry) {
        const defaults = this.administrativeEntryDefaults(this.selectedShift);
        this.roundForm.occurrence_date = defaults.occurrenceDate;
        this.roundForm.recorded_at = defaults.recordedAt;
        this.roundForm.nochero_confirmation_name = "";
      } else {
        this.roundForm.nochero_confirmation_name = this.catalogs.current_user?.name || "";
      }
      this.addSector();
      this.showRoundModal = true;
    },
    administrativeEntryDefaults(shift) {
      const now = new Date();
      const weekdayValues = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
      const assignedDays = new Set(shift?.weekdays || []);
      const recurrenceStart = this.toInputDate(shift?.recurrence_starts_on);
      const recurrenceEnd = this.toInputDate(shift?.recurrence_ends_on);

      for (let offset = 0; offset <= 7; offset += 1) {
        const occurrence = new Date(now);
        occurrence.setHours(0, 0, 0, 0);
        occurrence.setDate(occurrence.getDate() - offset);
        const occurrenceDate = toLocalDateInput(occurrence);
        if (!assignedDays.has(weekdayValues[occurrence.getDay()])) continue;
        if (recurrenceStart && occurrenceDate < recurrenceStart) continue;
        if (recurrenceEnd && occurrenceDate > recurrenceEnd) continue;

        const startsAt = new Date(occurrence);
        startsAt.setHours(20, 0, 0, 0);
        if (now < startsAt) continue;

        const endsAt = new Date(occurrence);
        endsAt.setDate(endsAt.getDate() + 1);
        endsAt.setHours(7, 30, 0, 0);
        const recordedAt = now <= endsAt ? now : endsAt;

        return {
          occurrenceDate,
          recordedAt: toLocalDateTimeInput(recordedAt),
        };
      }

      return { occurrenceDate: "", recordedAt: nowDateTime() };
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

      if (!this.shiftForm.weekdays.length) {
        return "Debes seleccionar al menos un día de la semana.";
      }

      return null;
    },
    validateRoundPayload() {
      if (this.roundForm.administrative_entry && !this.roundForm.occurrence_date) {
        return "Debes seleccionar la noche programada a la que pertenece el registro.";
      }

      if (this.roundForm.administrative_entry && !this.roundForm.recorded_at) {
        return "Debes indicar la fecha y hora real del registro administrativo.";
      }

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
          administrative_entry: this.roundForm.administrative_entry,
          occurrence_date: this.roundForm.administrative_entry ? this.roundForm.occurrence_date : null,
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
        await this.showSuccess(
          this.roundForm.administrative_entry
            ? "Registro administrativo agregado al turno correctamente."
            : "Ronda registrada y acta generada correctamente."
        );
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
      const pdfMake = await getPdfMake();
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
    toggleWeekday(value) {
      const days = new Set(this.ensureArray(this.shiftForm.weekdays));
      days.has(value) ? days.delete(value) : days.add(value);
      this.shiftForm.weekdays = this.weekdayOptions
        .map((option) => option.value)
        .filter((day) => days.has(day));
    },
    isWeekdaySelected(value) {
      return this.ensureArray(this.shiftForm.weekdays).includes(value);
    },
    nextWeekdayLabel(value) {
      const index = this.weekdayOptions.findIndex((option) => option.value === value);
      if (index < 0 || !this.weekdayOptions.length) return "día siguiente";
      return this.weekdayOptions[(index + 1) % this.weekdayOptions.length].label;
    },
    registrationTitle(shift) {
      if (this.isSuperAdmin && (!shift?.registration?.open || shift?.registration?.closed)) return "Gestión administrativa disponible";
      if (shift?.registration?.closed) return "Turno cerrado";
      if (shift?.registration?.can_register) return "Registro habilitado";
      if (shift?.registration?.open) return "Turno vigente";
      return "Fuera de turno";
    },
    registrationDescription(shift) {
      if (shift?.registration?.can_register) {
        return `Disponible hasta ${this.formatTime(shift.registration.ends_at)}.`;
      }
      if (shift?.registration?.open && !this.canRegisterRounds) {
        return "El turno está vigente, pero tu cuenta no tiene permiso para registrar rondas.";
      }
      if (this.isSuperAdmin) {
        return "Puedes agregar información a una noche programada indicando su fecha y hora real.";
      }
      if (shift?.registration?.next_starts_at) {
        return `Próximo turno: ${this.formatDateTime(shift.registration.next_starts_at)}.`;
      }
      return "No hay un próximo turno programado.";
    },
    formatTime(value) {
      if (!value) return "-";
      return new Date(String(value).replace(" ", "T")).toLocaleTimeString("es-CL", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: false,
      });
    },
    roundStatusLabel(value) {
      return {
        sin_novedad: "Sin novedad",
        observado: "Observado",
        requiere_atencion: "Requiere atención",
      }[value] || value || "Sin estado";
    },
    roundStatusVariant(value) {
      return {
        sin_novedad: "bg-success-subtle text-success",
        observado: "bg-warning-subtle text-warning-emphasis",
        requiere_atencion: "bg-danger-subtle text-danger",
      }[value] || "bg-light text-dark";
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
    <section class="shift-hero mb-4">
      <div class="shift-hero__glow"></div>
      <div class="shift-hero__content">
        <span class="shift-eyebrow"><i class="bx bx-moon"></i> Control nocturno</span>
        <h1>Días de trabajo y rondas</h1>
        <p>Define la noche que inicia cada nochero. Si entra el viernes, su turno continúa hasta la mañana del sábado y el registro permanece habilitado.</p>
        <div class="shift-hero__facts">
          <span><i class="bx bx-log-in-circle"></i> Entra a las 20:00</span>
          <span><i class="bx bx-log-out-circle"></i> Sale a las 07:30 del día siguiente</span>
          <span><i class="bx bx-shield-quarter"></i> Registro protegido por turno</span>
        </div>
      </div>
      <div class="shift-hero__actions">
        <button type="button" class="shift-button shift-button--ghost" @click="loadShifts()">
          <i class="bx bx-refresh"></i> Actualizar
        </button>
        <button v-if="canManageShifts" type="button" class="shift-button shift-button--light" @click="newShift">
          <i class="bx bx-calendar-plus"></i> Asignar días
        </button>
      </div>
    </section>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <section class="weekly-roster mb-4">
      <div class="weekly-roster__header">
        <div>
          <span>Semana nocturna</span>
          <h2>Quién entra y cuándo sale</h2>
          <p>Cada columna corresponde a la noche que comienza ese día.</p>
        </div>
        <div class="weekly-roster__status">
          <i></i> {{ assignedTodayCount }} {{ assignedTodayCount === 1 ? "turno vigente" : "turnos vigentes" }} ahora
        </div>
      </div>

      <LoadingState v-if="loading" message="Cargando programación..." compact />
      <div v-else class="weekly-roster__grid">
        <article v-for="day in weeklyCoverage" :key="day.value" class="night-slot" :class="{ 'night-slot--covered': day.shifts.length }">
          <header>
            <span>Entra</span>
            <strong>{{ day.label }}</strong>
            <i class="bx bx-right-arrow-alt"></i>
            <div><span>Sale</span><strong>{{ day.exitLabel }}</strong></div>
          </header>
          <div class="night-slot__time"><b>20:00</b><span></span><b>07:30</b></div>
          <div v-if="day.shifts.length" class="night-slot__people">
            <button
              v-for="shift in day.shifts"
              :key="shift.id"
              type="button"
              :class="{ active: selectedShift?.id === shift.id }"
              @click="openShift(shift)"
            >
              <span>{{ String(shift.staff?.full_name || 'N').charAt(0) }}</span>
              <strong>{{ shift.staff?.full_name || "Sin nombre" }}</strong>
              <i v-if="shift.registration?.open" title="Turno vigente"></i>
            </button>
          </div>
          <div v-else class="night-slot__empty">Sin asignación</div>
        </article>
      </div>

      <div v-if="!shifts.length" class="weekly-roster__empty">
        <i class="bx bx-calendar-x"></i>
        <span>No hay nocheros programados todavía.</span>
        <button v-if="canManageShifts" type="button" @click="newShift">Asignar los primeros días</button>
      </div>
    </section>

    <div class="shift-workspace">
      <main class="shift-detail">
        <template v-if="selectedShift">
          <div class="shift-detail__top">
            <div class="shift-profile">
              <span class="shift-avatar shift-avatar--large">{{ String(selectedShift.staff?.full_name || 'N').charAt(0) }}</span>
              <div>
                <span>Programación activa</span>
                <h2>{{ selectedShift.staff?.full_name || "Sin nombre" }}</h2>
                <p>{{ (selectedShift.weekday_labels || []).join(", ") }}</p>
              </div>
            </div>
            <button v-if="canManageShifts" type="button" class="shift-icon-button" aria-label="Editar días" @click="editShift">
              <i class="bx bx-edit-alt"></i>
            </button>
          </div>

          <div class="shift-week">
            <div v-for="day in selectedWorkNights" :key="day.value" class="active">
              <span>Entrada</span>
              <strong>{{ day.label }} · 20:00</strong>
              <small><i class="bx bx-right-arrow-alt"></i> Salida {{ day.exitLabel }} · 07:30</small>
            </div>
          </div>

          <section class="shift-access" :class="{ 'shift-access--open': selectedShift.registration?.open || isSuperAdmin }">
            <span class="shift-access__icon"><i class="bx" :class="selectedShift.registration?.open || isSuperAdmin ? 'bx-lock-open-alt' : 'bx-lock-alt'"></i></span>
            <div class="shift-access__copy">
              <span>Disponibilidad del registro</span>
              <h3>{{ registrationTitle(selectedShift) }}</h3>
              <p>{{ registrationDescription(selectedShift) }}</p>
            </div>
            <button
              v-if="isSuperAdmin || registrationOpen"
              type="button"
              class="shift-button shift-button--primary"
              @click="openRoundModal(isSuperAdmin)"
            >
              <i class="bx" :class="isSuperAdmin ? 'bx-plus-circle' : 'bx-map-pin'"></i>
              {{ isSuperAdmin ? "Agregar registro" : "Registrar ronda" }}
            </button>
            <span v-else class="shift-access__locked"><i class="bx bx-time"></i> Se habilita automáticamente</span>
          </section>

          <section class="shift-logbook">
            <div class="shift-panel-header">
              <div>
                <span>Registro cronológico</span>
                <h2>Bitácora de rondas</h2>
              </div>
              <button
                v-if="canExport && !selectedShift.is_weekly_template"
                type="button"
                class="shift-text-button"
                @click="exportShiftPdf"
              >Acta PDF</button>
            </div>

            <div v-if="selectedRounds.length" class="shift-timeline">
              <article v-for="round in selectedRounds" :key="round.id" class="round-card">
                <div class="round-card__rail"><span></span></div>
                <div class="round-card__main">
                  <div class="round-card__header">
                    <div>
                      <strong>Ronda #{{ round.round_number }}</strong>
                      <span>{{ formatDateTime(round.recorded_at) }} · {{ round.act_number }}</span>
                      <span v-if="round.recorded_by?.name || round.recordedBy?.name" class="round-card__author">
                        Registró: {{ round.recorded_by?.name || round.recordedBy?.name }}
                      </span>
                    </div>
                    <span class="round-status" :class="roundStatusVariant(round.overall_status)">
                      {{ roundStatusLabel(round.overall_status) }}
                    </span>
                  </div>
                  <p>{{ round.observations || "Recorrido completado sin observaciones generales." }}</p>
                  <div class="round-card__meta">
                    <span><i class="bx bx-map"></i> {{ (round.sectors || []).length }} sectores</span>
                    <span><i class="bx bx-error-circle"></i> {{ (round.incidents || []).length }} novedades</span>
                  </div>
                </div>
              </article>
            </div>
            <div v-else class="shift-empty shift-empty--history">
              <span><i class="bx bx-walk"></i></span>
              <strong>Aún no hay rondas registradas</strong>
              <p>Cuando se registre un recorrido durante un turno habilitado, aparecerá aquí.</p>
            </div>
          </section>
        </template>

        <div v-else class="shift-empty shift-empty--detail">
          <span><i class="bx bx-moon"></i></span>
          <strong>Selecciona a un nochero en la semana</strong>
          <p>Verás sus noches de entrada, las salidas del día siguiente y su bitácora reciente.</p>
        </div>
      </main>
    </div>

    <BModal v-model="showShiftModal" title="Asignar días de trabajo" size="lg" hide-footer modal-class="shift-schedule-modal">
      <div class="shift-modal-intro">
        <span><i class="bx bx-calendar-check"></i></span>
        <div>
          <strong>Programación semanal simple</strong>
          <p>Marca la noche de entrada. Por ejemplo: viernes significa que entra el viernes a las 20:00 y sale el sábado a las 07:30.</p>
        </div>
      </div>
      <div class="mb-4">
        <label class="shift-field-label">Nochero</label>
        <Multiselect
          v-model="shiftForm.staff_id"
          :options="availableStaffOptions"
          :searchable="true"
          :disabled="Boolean(shiftForm.id)"
          placeholder="Selecciona un nochero"
        />
      </div>

      <div>
        <label class="shift-field-label">Días que trabaja</label>
        <div class="shift-day-picker">
          <button
            v-for="option in weekdayOptions"
            :key="option.value"
            type="button"
            :class="{ active: isWeekdaySelected(option.value) }"
            @click="toggleWeekday(option.value)"
          >
            <span>Entra</span>
            <strong>{{ option.label }} · 20:00</strong>
            <small><i class="bx bx-right-arrow-alt"></i> Sale {{ nextWeekdayLabel(option.value) }} · 07:30</small>
            <i class="bx" :class="isWeekdaySelected(option.value) ? 'bx-check-circle' : 'bx-circle'"></i>
          </button>
        </div>
      </div>

      <div class="shift-modal-footer">
        <button type="button" class="shift-button shift-button--cancel" @click="showShiftModal = false">Cancelar</button>
        <button type="button" class="shift-button shift-button--primary" :disabled="saving" @click="saveShift">
          <i class="bx bx-save"></i> {{ saving ? "Guardando..." : "Guardar días" }}
        </button>
      </div>
    </BModal>

    <BModal
      v-model="showRoundModal"
      :title="roundForm.administrative_entry ? 'Agregar registro al turno' : 'Registrar ronda'"
      size="xl"
      hide-footer
    >
      <div class="shift-round-banner" :class="{ 'shift-round-banner--admin': roundForm.administrative_entry }">
        <span><i class="bx bx-lock-open-alt"></i></span>
        <div>
          <strong>{{ roundForm.administrative_entry ? "Intervención administrativa trazable" : "Registro habilitado para este turno" }}</strong>
          <p v-if="roundForm.administrative_entry">
            Se guardará quién agregó la información, la noche seleccionada y la hora real del registro.
          </p>
          <p v-else>La fecha, la hora y el nochero se registran automáticamente para mantener la trazabilidad.</p>
        </div>
      </div>
      <div v-if="roundForm.administrative_entry" class="shift-admin-entry mb-4">
        <div>
          <label class="shift-field-label">Noche programada</label>
          <BFormInput v-model="roundForm.occurrence_date" type="date" />
          <small>Debe ser uno de los días asignados al nochero.</small>
        </div>
        <div>
          <label class="shift-field-label">Fecha y hora real</label>
          <BFormInput v-model="roundForm.recorded_at" type="datetime-local" />
          <small>Entre las 20:00 y las 07:30 del día siguiente.</small>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Estado general</label>
          <Multiselect v-model="roundForm.overall_status" :options="roundStatusOptions" :searchable="true" />
        </div>
        <div class="col-md-7">
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
        <BButton variant="primary" :disabled="saving" @click="saveRound">
          {{ roundForm.administrative_entry ? "Agregar al turno" : "Guardar ronda" }}
        </BButton>
      </div>

      <datalist id="security-sector-suggestions">
        <option v-for="sector in sectorSuggestions" :key="sector" :value="sector"></option>
      </datalist>
    </BModal>
  </Layout>
</template>

<style scoped>
.shift-hero {
  position: relative;
  display: flex;
  min-height: 230px;
  align-items: flex-end;
  justify-content: space-between;
  gap: 2rem;
  overflow: hidden;
  padding: 2.2rem;
  border-radius: 24px;
  color: #fff;
  background:
    radial-gradient(circle at 82% 16%, rgba(129, 140, 248, 0.38), transparent 27%),
    linear-gradient(125deg, #111b45 0%, #25266d 54%, #4338ca 100%);
  box-shadow: 0 22px 45px rgba(28, 33, 83, 0.2);
}

.shift-hero::after {
  position: absolute;
  width: 210px;
  height: 210px;
  right: 7%;
  top: -95px;
  border: 1px solid rgba(255, 255, 255, 0.13);
  border-radius: 50%;
  content: "";
}

.shift-hero__glow {
  position: absolute;
  width: 330px;
  height: 330px;
  right: -130px;
  bottom: -220px;
  border-radius: 50%;
  background: rgba(129, 140, 248, 0.24);
  filter: blur(1px);
}

.shift-hero__content,
.shift-hero__actions { position: relative; z-index: 1; }
.shift-hero__content { max-width: 720px; }
.shift-hero h1 { margin: 0.45rem 0 0.7rem; color: #fff; font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 750; letter-spacing: -0.04em; }
.shift-hero p { max-width: 680px; margin: 0; color: rgba(255, 255, 255, 0.76); font-size: 1.03rem; line-height: 1.65; }
.shift-eyebrow { display: inline-flex; align-items: center; gap: 0.5rem; color: #c7d2fe; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
.shift-eyebrow i { font-size: 1rem; }
.shift-hero__facts { display: flex; flex-wrap: wrap; gap: 0.65rem; margin-top: 1.25rem; }
.shift-hero__facts span { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.52rem 0.76rem; border: 1px solid rgba(255, 255, 255, 0.13); border-radius: 999px; color: rgba(255, 255, 255, 0.82); background: rgba(255, 255, 255, 0.07); font-size: 0.76rem; }
.shift-hero__actions { display: flex; flex-direction: column; gap: 0.65rem; min-width: 170px; }

.shift-button {
  display: inline-flex;
  min-height: 42px;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  padding: 0.68rem 1rem;
  border: 0;
  border-radius: 12px;
  font-weight: 700;
  transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
}
.shift-button:hover:not(:disabled) { transform: translateY(-1px); }
.shift-button:disabled { cursor: wait; opacity: 0.62; }
.shift-button--ghost { color: #fff; background: rgba(255, 255, 255, 0.1); }
.shift-button--ghost:hover { background: rgba(255, 255, 255, 0.16); }
.shift-button--light { color: #28236b; background: #fff; box-shadow: 0 10px 24px rgba(5, 8, 30, 0.18); }
.shift-button--primary { color: #fff; background: linear-gradient(135deg, #4f46e5, #3730a3); box-shadow: 0 9px 20px rgba(79, 70, 229, 0.22); }
.shift-button--cancel { color: #475569; background: #eef2f7; }

.weekly-roster {
  overflow: hidden;
  border: 1px solid #e5e9f2;
  border-radius: 20px;
  background: #fff;
  box-shadow: 0 14px 35px rgba(29, 38, 77, 0.065);
}
.weekly-roster__header { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.35rem 1rem; border-bottom: 1px solid #edf0f5; }
.weekly-roster__header > div:first-child > span { color: #6366f1; font-size: 0.67rem; font-weight: 800; letter-spacing: 0.09em; text-transform: uppercase; }
.weekly-roster__header h2 { margin: 0.18rem 0 0; color: #1e293b; font-size: 1.1rem; font-weight: 750; }
.weekly-roster__header p { margin: 0.22rem 0 0; color: #7c8799; font-size: 0.76rem; }
.weekly-roster__status { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.46rem 0.7rem; border-radius: 999px; color: #476070; background: #f1f5f9; font-size: 0.7rem; font-weight: 700; white-space: nowrap; }
.weekly-roster__status i { width: 7px; height: 7px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 4px #d1fae5; }
.weekly-roster__grid { display: grid; grid-template-columns: repeat(7, minmax(130px, 1fr)); gap: 0.65rem; overflow-x: auto; padding: 1rem 1.1rem 1.2rem; scrollbar-width: thin; }
.night-slot { min-width: 0; padding: 0.85rem; border: 1px solid #e5e9f2; border-radius: 15px; background: #f8fafc; scroll-snap-align: start; }
.night-slot--covered { border-color: #d7dcff; background: linear-gradient(180deg, #f8f9ff, #f1f3ff); }
.night-slot header { display: grid; grid-template-columns: 1fr auto 1fr; grid-template-rows: auto auto; align-items: center; column-gap: 0.2rem; }
.night-slot header > span { grid-column: 1; grid-row: 1; color: #7c8799; font-size: 0.56rem; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; }
.night-slot header > strong { grid-column: 1; grid-row: 2; overflow: hidden; color: #28334a; font-size: 0.78rem; text-overflow: ellipsis; }
.night-slot header > i { grid-column: 2; grid-row: 1 / 3; color: #818cf8; font-size: 1rem; }
.night-slot header > div { display: flex; grid-column: 3; grid-row: 1 / 3; min-width: 0; flex-direction: column; }
.night-slot header > div span { color: #7c8799; font-size: 0.56rem; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; }
.night-slot header > div strong { overflow: hidden; color: #28334a; font-size: 0.78rem; text-overflow: ellipsis; }
.night-slot__time { display: flex; align-items: center; gap: 0.35rem; margin: 0.7rem 0; color: #4f46e5; }
.night-slot__time b { font-size: 0.65rem; }
.night-slot__time span { height: 1px; flex: 1; background: #c7d2fe; }
.night-slot__people { display: flex; flex-direction: column; gap: 0.35rem; }
.night-slot__people button { display: grid; grid-template-columns: 25px minmax(0, 1fr) auto; align-items: center; gap: 0.42rem; width: 100%; padding: 0.38rem; border: 1px solid transparent; border-radius: 9px; text-align: left; background: rgba(255, 255, 255, 0.8); }
.night-slot__people button:hover,
.night-slot__people button.active { border-color: #a5b4fc; background: #fff; box-shadow: 0 5px 12px rgba(79, 70, 229, 0.08); }
.night-slot__people button > span { display: grid; width: 25px; height: 25px; place-items: center; border-radius: 8px; color: #3730a3; background: #e0e7ff; font-size: 0.62rem; font-weight: 800; }
.night-slot__people button > strong { overflow: hidden; color: #475569; font-size: 0.64rem; text-overflow: ellipsis; white-space: nowrap; }
.night-slot__people button > i { width: 6px; height: 6px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 3px #d1fae5; }
.night-slot__empty { padding: 0.45rem 0; color: #a0a9b8; font-size: 0.65rem; text-align: center; }
.weekly-roster__empty { display: flex; align-items: center; justify-content: center; gap: 0.55rem; margin: 0 1.1rem 1.1rem; padding: 0.75rem; border: 1px dashed #cbd5e1; border-radius: 12px; color: #718096; background: #f8fafc; font-size: 0.72rem; }
.weekly-roster__empty > i { color: #6366f1; font-size: 1rem; }
.weekly-roster__empty button { border: 0; color: #4f46e5; background: transparent; font-weight: 750; }

.shift-workspace { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.25rem; align-items: start; }
.shift-detail { border: 1px solid #e5e9f2; border-radius: 20px; background: #fff; box-shadow: 0 14px 35px rgba(29, 38, 77, 0.065); }
.shift-detail { min-height: 520px; padding: 1.35rem; }
.shift-panel-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.2rem 1.2rem 0.95rem; }
.shift-panel-header > div { min-width: 0; }
.shift-panel-header span,
.shift-profile > div > span { color: #6366f1; font-size: 0.67rem; font-weight: 800; letter-spacing: 0.09em; text-transform: uppercase; }
.shift-panel-header h2,
.shift-profile h2 { margin: 0.18rem 0 0; color: #1e293b; font-size: 1.05rem; font-weight: 750; }
.shift-avatar { display: grid; width: 40px; height: 40px; flex: 0 0 40px; place-items: center; border-radius: 13px; color: #3730a3; background: linear-gradient(145deg, #e0e7ff, #c7d2fe); font-size: 0.95rem; font-weight: 800; }
.shift-avatar--large { width: 50px; height: 50px; flex-basis: 50px; border-radius: 16px; font-size: 1.1rem; }

.shift-detail__top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-bottom: 1.15rem; border-bottom: 1px solid #edf0f5; }
.shift-profile { display: flex; align-items: center; gap: 0.85rem; }
.shift-profile p { margin: 0.22rem 0 0; color: #7d8799; font-size: 0.78rem; }
.shift-icon-button { display: grid; width: 40px; height: 40px; place-items: center; border: 1px solid #e2e8f0; border-radius: 12px; color: #4f46e5; background: #fff; font-size: 1.05rem; }
.shift-icon-button:hover { border-color: #a5b4fc; background: #eef2ff; }
.shift-week { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.65rem; margin: 1.2rem 0; }
.shift-week > div { display: flex; min-height: 82px; flex-direction: column; justify-content: center; gap: 0.16rem; padding: 0.8rem 0.9rem; border: 1px solid #c7d2fe; border-radius: 13px; color: #3730a3; background: linear-gradient(135deg, #f5f7ff, #eef2ff); }
.shift-week > div > span { color: #6366f1; font-size: 0.58rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
.shift-week strong { color: #312e81; font-size: 0.78rem; font-weight: 750; }
.shift-week small { display: inline-flex; align-items: center; gap: 0.28rem; color: #64748b; font-size: 0.68rem; }
.shift-week small i { color: #818cf8; font-size: 0.9rem; }

.shift-access { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 0.9rem; padding: 1rem; border: 1px solid #e7eaf0; border-radius: 16px; background: #f8fafc; }
.shift-access--open { border-color: #a7f3d0; background: linear-gradient(110deg, #ecfdf5, #f7fffb); }
.shift-access__icon { display: grid; width: 44px; height: 44px; place-items: center; border-radius: 13px; color: #64748b; background: #e8edf3; font-size: 1.25rem; }
.shift-access--open .shift-access__icon { color: #047857; background: #d1fae5; }
.shift-access__copy > span { color: #7c879b; font-size: 0.67rem; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; }
.shift-access__copy h3 { margin: 0.12rem 0 0.2rem; color: #1e293b; font-size: 1rem; }
.shift-access__copy p { margin: 0; color: #738095; font-size: 0.76rem; }
.shift-access__locked { display: inline-flex; align-items: center; gap: 0.35rem; color: #7c879b; font-size: 0.72rem; font-weight: 700; }

.shift-logbook { margin-top: 1rem; overflow: hidden; border: 1px solid #e8ebf1; border-radius: 16px; }
.shift-logbook .shift-panel-header { padding-bottom: 1rem; border-bottom: 1px solid #edf0f5; }
.shift-timeline { padding: 0.35rem 1.1rem 1.1rem; }
.round-card { display: grid; grid-template-columns: 22px minmax(0, 1fr); gap: 0.7rem; padding-top: 1rem; }
.round-card__rail { position: relative; display: flex; justify-content: center; }
.round-card__rail::after { position: absolute; top: 14px; bottom: -18px; width: 1px; background: #dfe4ee; content: ""; }
.round-card:last-child .round-card__rail::after { display: none; }
.round-card__rail span { position: relative; z-index: 1; width: 10px; height: 10px; margin-top: 5px; border: 2px solid #fff; border-radius: 50%; background: #6366f1; box-shadow: 0 0 0 3px #e0e7ff; }
.round-card__main { padding: 0 0 1rem; }
.round-card__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.8rem; }
.round-card__header > div { display: flex; flex-direction: column; }
.round-card__header strong { color: #27324a; font-size: 0.83rem; }
.round-card__header div span { color: #8a94a6; font-size: 0.69rem; }
.round-card__header div .round-card__author { margin-top: 0.16rem; color: #6366f1; font-weight: 700; }
.round-card__main p { margin: 0.5rem 0; color: #5f6b7d; font-size: 0.78rem; line-height: 1.5; }
.round-card__meta { display: flex; flex-wrap: wrap; gap: 0.8rem; color: #7c8799; font-size: 0.69rem; }
.round-card__meta span { display: inline-flex; align-items: center; gap: 0.28rem; }
.round-status { padding: 0.34rem 0.52rem; border-radius: 999px; font-size: 0.63rem; font-weight: 750; }
.shift-text-button { border: 0; color: #4f46e5; background: transparent; font-size: 0.76rem; font-weight: 750; }

.shift-empty { display: flex; align-items: center; justify-content: center; flex-direction: column; color: #8190a5; text-align: center; }
.shift-empty > span { display: grid; width: 52px; height: 52px; place-items: center; margin-bottom: 0.7rem; border-radius: 17px; color: #6366f1; background: #eef2ff; font-size: 1.45rem; }
.shift-empty strong { color: #3a465b; font-size: 0.88rem; }
.shift-empty p { max-width: 370px; margin: 0.35rem 0 0; font-size: 0.75rem; line-height: 1.5; }
.shift-empty--compact { min-height: 280px; padding: 2rem 1rem; }
.shift-empty--history { min-height: 220px; padding: 2rem; }
.shift-empty--detail { min-height: 490px; }

.shift-modal-intro,
.shift-round-banner { display: flex; align-items: center; gap: 0.8rem; margin-bottom: 1.25rem; padding: 0.85rem; border: 1px solid #dbe4ff; border-radius: 14px; background: #f5f7ff; }
.shift-modal-intro > span,
.shift-round-banner > span { display: grid; width: 42px; height: 42px; flex: 0 0 42px; place-items: center; border-radius: 12px; color: #4338ca; background: #e0e7ff; font-size: 1.2rem; }
.shift-modal-intro strong,
.shift-round-banner strong { color: #27324a; font-size: 0.84rem; }
.shift-modal-intro p,
.shift-round-banner p { margin: 0.15rem 0 0; color: #748096; font-size: 0.74rem; }
.shift-field-label { display: block; margin-bottom: 0.45rem; color: #344054; font-size: 0.78rem; font-weight: 750; }
.shift-day-picker { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.55rem; }
.shift-day-picker button { position: relative; display: flex; min-height: 106px; flex-direction: column; align-items: flex-start; justify-content: center; gap: 0.2rem; padding: 0.75rem 0.6rem; border: 1px solid #e3e7ef; border-radius: 14px; color: #8490a3; text-align: left; background: #f8fafc; }
.shift-day-picker button > span { color: #8a94a6; font-size: 0.54rem; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; }
.shift-day-picker button > strong { color: #475569; font-size: 0.68rem; }
.shift-day-picker button > small { display: flex; align-items: center; gap: 0.18rem; color: #8490a3; font-size: 0.56rem; line-height: 1.35; }
.shift-day-picker button > small i { font-size: 0.75rem; }
.shift-day-picker button > i { position: absolute; top: 0.55rem; right: 0.55rem; font-size: 0.95rem; }
.shift-day-picker button.active { border-color: #818cf8; color: #3730a3; background: #eef2ff; box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.12); }
.shift-day-picker button.active > i { color: #10b981; }
.shift-day-picker button.active > strong { color: #312e81; }
.shift-modal-footer { display: flex; justify-content: flex-end; gap: 0.65rem; margin-top: 1.45rem; padding-top: 1rem; border-top: 1px solid #edf0f5; }
.shift-round-banner { border-color: #a7f3d0; background: #ecfdf5; }
.shift-round-banner > span { color: #047857; background: #d1fae5; }
.shift-round-banner--admin { border-color: #c7d2fe; background: linear-gradient(135deg, #eef2ff, #f8faff); }
.shift-round-banner--admin > span { color: #4338ca; background: #e0e7ff; }
.shift-admin-entry { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.8rem; padding: 0.9rem; border: 1px solid #e0e7ff; border-radius: 14px; background: #f8faff; }
.shift-admin-entry small { display: block; margin-top: 0.35rem; color: #7c8799; font-size: 0.68rem; }

@media (max-width: 1199.98px) {
  .weekly-roster__grid { grid-template-columns: repeat(7, minmax(150px, 1fr)); scroll-snap-type: x proximity; }
}

@media (max-width: 767.98px) {
  .shift-hero { min-height: 0; align-items: stretch; flex-direction: column; padding: 1.35rem; border-radius: 18px; }
  .shift-hero h1 { font-size: 2rem; }
  .shift-hero__actions { min-width: 0; flex-direction: row; }
  .shift-hero__actions .shift-button { flex: 1; }
  .weekly-roster__header { align-items: flex-start; flex-direction: column; }
  .weekly-roster__grid { grid-template-columns: repeat(7, minmax(145px, 1fr)); }
  .shift-detail { padding: 1rem; }
  .shift-day-picker { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .shift-admin-entry { grid-template-columns: 1fr; }
  .shift-day-picker button { min-height: 70px; }
  .shift-access { grid-template-columns: auto minmax(0, 1fr); }
  .shift-access .shift-button,
  .shift-access__locked { grid-column: 1 / -1; width: 100%; }
}

@media (max-width: 479.98px) {
  .shift-hero__facts { flex-direction: column; align-items: flex-start; }
  .shift-hero__actions { flex-direction: column; }
  .shift-day-picker { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .weekly-roster__grid { margin-right: -1rem; padding-right: 1rem; }
  .round-card__header { flex-direction: column; }
}
</style>
