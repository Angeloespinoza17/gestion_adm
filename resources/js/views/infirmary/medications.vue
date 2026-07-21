<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryDocumentPanel from "../../components/infirmary/document-panel.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import InfirmaryStudentContextCard from "../../components/infirmary/student-context-card.vue";
import { getPdfMake } from "../../utils/pdfmake";
import {
  confirmInfirmaryAction,
  confirmInfirmaryCancel,
  formatInfirmaryDate,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  showInfirmaryError,
  showInfirmarySuccess,
  showInfirmaryWarning,
  toInputDate,
  toInputDateTime,
} from "../../components/infirmary/module-utils";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryDocumentPanel,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
    InfirmaryStudentSearch,
    InfirmaryStudentContextCard,
  },
  data() {
    return {
      loading: false,
      saving: false,
      administering: false,
      error: null,
      selectedStudentContext: null,
      catalogs: {
        medications: [],
        users: [],
        medication_regimen_options: [],
        medication_schedule_mode_options: [],
        medication_dose_unit_options: [],
        medication_route_options: [],
        medication_administration_status_options: [],
        medication_non_administration_reason_options: [],
        medication_daily_status_options: [],
        capabilities: {},
      },
      filters: {
        search: "",
        student_profile_id: null,
        medication_id: null,
        status: null,
        daily_status: null,
      },
      authorizations: [],
      dailyStatusDate: "",
      pagination: { current_page: 1, total: 0, per_page: 15 },
      selectedAuthorization: null,
      showModal: false,
      showViewModal: false,
      showAdministrationModal: false,
      form: this.emptyForm(),
      administrationForm: this.emptyAdministrationForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    canManage() {
      return Boolean(this.catalogs.capabilities?.can_manage_medications);
    },
    canExport() {
      return Boolean(this.catalogs.capabilities?.can_export);
    },
    medicationOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.medications || []).map((item) => ({
          value: item.id,
          text: item.commercial_name || item.name,
        }))
      );
    },
    statusOptions() {
      return [
        { value: null, text: "Todos" },
        { value: "vigente", text: "Vigente" },
        { value: "proxima_a_vencer", text: "Próxima a vencer" },
        { value: "vencida", text: "Vencida" },
        { value: "terminada", text: "Terminada" },
      ];
    },
    userOptions() {
      return [{ value: null, text: "Automático" }].concat(
        (this.catalogs.users || []).map((item) => ({
          value: item.id,
          text: item.name,
        }))
      );
    },
    regimenOptions() {
      return this.selectOptions(this.catalogs.medication_regimen_options || []);
    },
    scheduleModeOptions() {
      return this.selectOptions(this.catalogs.medication_schedule_mode_options || []);
    },
    dailyStatusOptions() {
      return this.selectOptions(this.catalogs.medication_daily_status_options || [], {
        value: null,
        text: "Todos",
      });
    },
    doseUnitOptions() {
      return this.selectOptions(this.catalogs.medication_dose_unit_options || []);
    },
    routeOptions() {
      return this.selectOptions(this.catalogs.medication_route_options || []);
    },
    administrationStatusOptions() {
      return this.selectOptions(this.catalogs.medication_administration_status_options || []);
    },
    nonAdministrationReasonOptions() {
      return this.selectOptions(this.catalogs.medication_non_administration_reason_options || []);
    },
    isDurationRegimen() {
      return ["meses", "semanas", "dias"].includes(this.form.regimen_type);
    },
    durationQuantityLabel() {
      return {
        meses: "Cantidad de meses",
        semanas: "Cantidad de semanas",
        dias: "Cantidad de días",
      }[this.form.regimen_type] || "Cantidad";
    },
    isSpecificEndDate() {
      return this.form.regimen_type === "fecha_especifica";
    },
    isSosRegimen() {
      return this.form.regimen_type === "sos";
    },
    isNoAdministration() {
      return this.administrationForm.administration_status === "no_administrada";
    },
    isAdministrationForToday() {
      return String(this.administrationForm.administered_at || "").slice(0, 10)
        === this.selectedAuthorization?.daily_status?.date;
    },
    administrationScheduleOptions() {
      if (this.selectedAuthorization?.regimen_type === "sos") return [];

      const dailySlots = this.selectedAuthorization?.daily_status?.slots || [];
      const fallbackSlots = (this.selectedAuthorization?.schedules || []).map((schedule) => ({
        schedule_id: schedule.id,
        dose_order: schedule.dose_order,
        scheduled_time: schedule.scheduled_time ? String(schedule.scheduled_time).slice(0, 5) : null,
        label: schedule.scheduled_time
          ? `Dosis ${schedule.dose_order} · ${String(schedule.scheduled_time).slice(0, 5)}`
          : `Dosis ${schedule.dose_order}`,
        registered: false,
      }));
      const slots = dailySlots.length ? dailySlots : fallbackSlots;

      return [{ value: null, text: "Selecciona la dosis" }].concat(
        slots.map((slot) => ({
          value: slot.schedule_id,
          text: slot.registered && this.isAdministrationForToday
            ? `${slot.label} · registrada`
            : slot.label,
          disabled: Boolean(slot.registered && this.isAdministrationForToday),
        }))
      );
    },
  },
  watch: {
    "form.daily_dose_count"() {
      this.syncFormSchedules();
    },
    "form.schedule_mode"() {
      this.syncFormSchedules();
    },
    "form.regimen_type"() {
      this.syncFormSchedules();
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadAuthorizations();
  },
  methods: {
    formatInfirmaryDate,
    formatInfirmaryDateTime,
    emptyForm() {
      return {
        id: null,
        student_profile_id: null,
        student_label: "",
        medication_id: null,
        diagnosis: "",
        dose: "",
        dose_amount: null,
        dose_unit: "mg",
        administration_route: "oral",
        frequency: "",
        daily_dose_count: 1,
        schedule_mode: "fixed_time",
        schedule_text: "",
        schedules: [{ dose_order: 1, scheduled_time: "" }],
        regimen_type: "permanente",
        duration_quantity: null,
        start_date: toInputDate(new Date().toISOString()),
        end_date: "",
        physician_name: "",
        medical_authorization_expires_at: "",
        guardian_authorization_expires_at: "",
        observations: "",
        status: "vigente",
      };
    },
    emptyAdministrationForm() {
      return {
        administered_at: toInputDateTime(new Date().toISOString()),
        administration_status: "administrada",
        schedule_id: null,
        medication_id: null,
        student_profile_id: null,
        quantity_administered: 1,
        dose_amount: null,
        dose_unit: "mg",
        administration_route: "oral",
        administered_by_user_id: null,
        schedule_reference: "",
        non_administration_reason: "",
        observations: "",
      };
    },
    selectOptions(items, prepend = null) {
      const options = (items || []).map((item) => ({
        value: item.value,
        text: item.text || item.label || item.value,
      }));

      return prepend ? [prepend].concat(options) : options;
    },
    optionLabel(options, value) {
      return (options || []).find((item) => item.value === value)?.text || value || "-";
    },
    medicationName(medication) {
      return medication?.commercial_name || medication?.name || "-";
    },
    studentName(student) {
      if (!student) return "-";
      return [student.first_name, student.last_name].filter(Boolean).join(" ") || student.registered_name || "-";
    },
    doseLabel(item) {
      if (item?.dose_amount && item?.dose_unit) {
        return `${Number(item.dose_amount).toLocaleString("es-CL")} ${item.dose_unit}`;
      }

      return item?.dose || "-";
    },
    regimenLabel(item) {
      const type = this.optionLabel(this.regimenOptions, item?.regimen_type || "permanente");

      if (["meses", "semanas", "dias"].includes(item?.regimen_type) && item?.duration_quantity) {
        return `${type} · ${item.duration_quantity}`;
      }

      return type;
    },
    dailyFrequencyLabel(count) {
      const value = Number(count || 0);
      if (!value) return "Sin frecuencia diaria";
      return value === 1 ? "Una vez al día" : `${value} veces al día`;
    },
    dailyStatusVariant(authorization) {
      return authorization?.daily_status?.variant || "secondary";
    },
    dailyStatusLabel(authorization) {
      return authorization?.daily_status?.label || "Sin control diario";
    },
    dailyStatusDetail(authorization) {
      return authorization?.daily_status?.detail || "No hay información para hoy.";
    },
    canRegisterToday(authorization) {
      if (!authorization) return false;
      if (authorization.regimen_type === "sos") return true;
      const dailyStatus = authorization.daily_status;
      return Boolean(dailyStatus?.applicable && dailyStatus?.pending_count > 0);
    },
    syncFormSchedules() {
      if (this.form.regimen_type === "sos") {
        this.form.schedules = [];
        return;
      }

      const count = Math.max(1, Math.min(12, Number(this.form.daily_dose_count || 1)));
      this.form.daily_dose_count = count;
      const existing = Array.isArray(this.form.schedules) ? this.form.schedules : [];
      this.form.schedules = Array.from({ length: count }, (_, index) => ({
        dose_order: index + 1,
        scheduled_time: this.form.schedule_mode === "fixed_time"
          ? String(existing[index]?.scheduled_time || "").slice(0, 5)
          : null,
      }));
    },
    handleScheduleModeChange() {
      this.syncFormSchedules();
    },
    selectAdministrationSchedule(scheduleId) {
      const slot = (this.selectedAuthorization?.daily_status?.slots || [])
        .find((item) => Number(item.schedule_id) === Number(scheduleId));
      const schedule = (this.selectedAuthorization?.schedules || [])
        .find((item) => Number(item.id) === Number(scheduleId));
      const time = slot?.scheduled_time || (schedule?.scheduled_time ? String(schedule.scheduled_time).slice(0, 5) : "");
      const order = slot?.dose_order || schedule?.dose_order;

      this.administrationForm.schedule_reference = time || (order ? `Dosis ${order}` : "");
    },
    administrationDay(value) {
      if (!value) return "-";
      return new Date(value).toLocaleDateString("es-CL", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
      });
    },
    administrationHour(value) {
      if (!value) return "-";
      return new Date(value).toLocaleTimeString("es-CL", {
        hour: "2-digit",
        minute: "2-digit",
      });
    },
    nonAdministrationReasonLabel(value) {
      return this.optionLabel(this.nonAdministrationReasonOptions, value);
    },
    pdfValue(value) {
      if (value === null || value === undefined || value === "") {
        return "-";
      }

      return String(value);
    },
    pdfFileSegment(value) {
      return String(value || "ficha")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^\w-]+/g, "-")
        .replace(/^-+|-+$/g, "")
        .toLowerCase() || "ficha";
    },
    pdfKeyValueSection(title, rows) {
      return [
        { text: title, style: "section" },
        {
          table: {
            widths: ["32%", "*"],
            body: rows.map(([label, value]) => [
              { text: label, style: "tableLabel" },
              this.pdfValue(value),
            ]),
          },
          layout: "lightHorizontalLines",
          margin: [0, 0, 0, 10],
        },
      ];
    },
    pdfDataTableSection(title, headers, rows, widths) {
      return [
        { text: title, style: "section" },
        {
          table: {
            headerRows: 1,
            widths,
            body: [
              headers.map((header) => ({ text: header, style: "tableHeader" })),
              ...rows.map((row) => row.map((cell) => this.pdfValue(cell))),
            ],
          },
          layout: "lightHorizontalLines",
          margin: [0, 0, 0, 10],
        },
      ];
    },
    medicationAdministrationPdfRows(authorization) {
      const administrations = authorization?.administrations || [];

      if (!administrations.length) {
        return [["-", "Sin administraciones registradas.", "-", "-", "-", "-"]];
      }

      return administrations.map((item) => [
        this.medicationName(item.medication || authorization.medication),
        this.doseLabel(item),
        this.optionLabel(this.routeOptions, item.administration_route || authorization.administration_route || "oral"),
        this.administrationDay(item.administered_at),
        this.administrationHour(item.administered_at),
        item.administration_status === "no_administrada"
          ? `No administrada · ${this.nonAdministrationReasonLabel(item.non_administration_reason)}`
          : "Administrada",
      ]);
    },
    async exportAuthorizationPdf(authorization) {
      if (!authorization?.id) return;

      try {
        const detail = await this.fetchAuthorization(authorization);
        if (!detail) return;

        const pdfMake = getPdfMake();
        const student = this.studentName(detail.student);
        const medication = this.medicationName(detail.medication);
        const fileName = `ficha_suministro_${String(detail.id).padStart(5, "0")}_${this.pdfFileSegment(student)}.pdf`;
        const content = [
          { text: "Ficha de Suministro de Medicamento", style: "title" },
          {
            text: `${medication} · Generada el ${this.formatInfirmaryDateTime(new Date())}`,
            style: "subtitle",
          },
          ...this.pdfKeyValueSection("Ficha de la estudiante", [
            ["Estudiante", student],
            ["RUT", detail.student?.rut],
            ["Curso", this.selectedStudentContext?.course],
            ["Apoderado", detail.student?.guardian_name],
            ["Teléfono apoderado", detail.student?.guardian_phone],
          ]),
          ...this.pdfKeyValueSection("Rutina de suministro", [
            ["Medicamento correcto", medication],
            ["Dosis", this.doseLabel(detail)],
            ["Vía", this.optionLabel(this.routeOptions, detail.administration_route || "oral")],
            ["Frecuencia", detail.frequency],
            ["Horario", detail.schedule_text],
            ["Tipo de término", this.regimenLabel(detail)],
            ["Inicio", this.formatInfirmaryDate(detail.start_date)],
            ["Término", this.formatInfirmaryDate(detail.end_date)],
            ["Estado", this.optionLabel(this.statusOptions, detail.status)],
          ]),
          ...this.pdfKeyValueSection("Autorizaciones y control", [
            ["Autorización médica", this.formatInfirmaryDate(detail.medical_authorization_expires_at)],
            ["Autorización apoderado", this.formatInfirmaryDate(detail.guardian_authorization_expires_at)],
            ["Médico tratante", detail.physician_name],
            ["Stock disponible", `${detail.medication?.current_stock || 0} ${detail.medication?.unit || ""}`],
            ["Motivo clínico / indicación", detail.diagnosis],
            ["Observaciones", detail.observations],
          ]),
          ...this.pdfDataTableSection(
            "Detalle de administración",
            ["Medicamento", "Dosis", "Vía", "Día", "Hora", "Estado / motivo"],
            this.medicationAdministrationPdfRows(detail),
            ["23%", "12%", "13%", "14%", "12%", "26%"]
          ),
        ];

        pdfMake.createPdf({
          pageSize: "LETTER",
          pageMargins: [36, 40, 36, 44],
          content,
          footer: (currentPage, pageCount) => ({
            text: `Página ${currentPage} de ${pageCount}`,
            alignment: "right",
            margin: [0, 0, 36, 0],
            fontSize: 8,
            color: "#74788d",
          }),
          styles: {
            title: { fontSize: 18, bold: true, color: "#2a3042", margin: [0, 0, 0, 4] },
            subtitle: { fontSize: 9, color: "#74788d", margin: [0, 0, 0, 14] },
            section: { fontSize: 12, bold: true, color: "#2a3042", margin: [0, 8, 0, 6] },
            tableHeader: { bold: true, color: "#2a3042", fillColor: "#eff2f7" },
            tableLabel: { bold: true, color: "#2a3042", fillColor: "#f8fafc" },
          },
          defaultStyle: { fontSize: 9, color: "#4b5563" },
        }).download(fileName);
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo exportar la ficha a PDF."));
      }
    },
    async loadCatalogs() {
      const response = await axios.get("/api/infirmary/catalogs");
      this.catalogs = response.data;
    },
    async loadAuthorizations(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/medication-authorizations", {
          params: {
            page,
            ...this.filters,
          },
        });
        this.authorizations = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
        this.dailyStatusDate = response.data.daily_status_date || "";

        if (!this.authorizations.length) {
          this.selectedAuthorization = null;
          this.selectedStudentContext = null;
        }
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar la administración de medicamentos.");
      } finally {
        this.loading = false;
      }
    },
    async fetchAuthorization(authorization) {
      try {
        const response = await axios.get(`/api/infirmary/medication-authorizations/${authorization.id}`);
        this.selectedAuthorization = response.data.data;
        if (this.selectedAuthorization?.student_profile_id) {
          const history = await axios.get(`/api/infirmary/student-history/${this.selectedAuthorization.student_profile_id}`);
          this.selectedStudentContext = history.data.student;
        }
        return this.selectedAuthorization;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el detalle de la autorización.");
        return null;
      }
    },
    async openView(authorization) {
      const loaded = await this.fetchAuthorization(authorization);
      if (loaded) this.showViewModal = true;
    },
    selectStudent(student) {
      this.filters.student_profile_id = student.id;
      this.loadAuthorizations(1);
    },
    selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
    },
    resetFilters() {
      this.filters = {
        search: "",
        student_profile_id: null,
        medication_id: null,
        status: null,
        daily_status: null,
      };
      this.loadAuthorizations(1);
    },
    openCreate() {
      this.form = this.emptyForm();
      this.syncFormSchedules();
      this.showModal = true;
    },
    openEdit(authorization) {
      this.form = {
        id: authorization.id,
        student_profile_id: authorization.student_profile_id,
        student_label: authorization.student ? `${authorization.student.first_name} ${authorization.student.last_name}` : "",
        medication_id: authorization.medication_id,
        diagnosis: authorization.diagnosis || "",
        dose: authorization.dose || "",
        dose_amount: authorization.dose_amount || null,
        dose_unit: authorization.dose_unit || "mg",
        administration_route: authorization.administration_route || "oral",
        frequency: authorization.frequency || "",
        daily_dose_count: authorization.daily_dose_count || authorization.schedules?.length || 1,
        schedule_mode: authorization.schedule_mode || "fixed_time",
        schedule_text: authorization.schedule_text || "",
        schedules: (authorization.schedules || []).map((schedule, index) => ({
          dose_order: schedule.dose_order || index + 1,
          scheduled_time: schedule.scheduled_time ? String(schedule.scheduled_time).slice(0, 5) : "",
        })),
        regimen_type: authorization.regimen_type || "permanente",
        duration_quantity: authorization.duration_quantity || null,
        start_date: toInputDate(authorization.start_date),
        end_date: toInputDate(authorization.end_date),
        physician_name: authorization.physician_name || "",
        medical_authorization_expires_at: toInputDate(authorization.medical_authorization_expires_at),
        guardian_authorization_expires_at: toInputDate(authorization.guardian_authorization_expires_at),
        observations: authorization.observations || "",
        status: authorization.status || "vigente",
      };
      this.syncFormSchedules();
      this.showModal = true;
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel("la rutina de suministro");
      if (result.isConfirmed) this.showModal = false;
    },
    async save() {
      if (!this.form.student_profile_id) {
        await showInfirmaryWarning("Debes seleccionar una estudiante.");
        return;
      }

      if (!this.form.medication_id) {
        await showInfirmaryWarning("Debes seleccionar el medicamento correcto.");
        return;
      }

      if (!this.form.dose_amount && !this.form.dose) {
        await showInfirmaryWarning("Debes indicar la dosis.");
        return;
      }

      if (!this.isSosRegimen) {
        this.syncFormSchedules();
        if (this.form.schedule_mode === "fixed_time" && this.form.schedules.some((item) => !item.scheduled_time)) {
          await showInfirmaryWarning("Debes indicar la hora de cada dosis diaria.");
          return;
        }
      }

      this.saving = true;
      try {
        const dose = this.doseLabel(this.form);
        const payload = {
          ...this.form,
          dose,
          frequency: this.isSosRegimen ? "S.O.S." : this.dailyFrequencyLabel(this.form.daily_dose_count),
          schedule_text: this.isSosRegimen
            ? "Según necesidad"
            : this.form.schedule_mode === "fixed_time"
              ? this.form.schedules.map((item) => item.scheduled_time).join(" / ")
              : "Sin horario fijo",
          schedules: this.isSosRegimen ? [] : this.form.schedules,
          end_date: this.form.end_date || null,
          medical_authorization_expires_at: this.form.medical_authorization_expires_at || null,
          guardian_authorization_expires_at: this.form.guardian_authorization_expires_at || null,
        };

        if (this.isEditing) {
          await axios.put(`/api/infirmary/medication-authorizations/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/medication-authorizations", payload);
        }

        this.showModal = false;
        await this.loadAuthorizations(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isEditing ? "Rutina actualizada correctamente." : "Rutina registrada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar la rutina de suministro."));
      } finally {
        this.saving = false;
      }
    },
    async remove(authorization) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar rutina",
        text: "Se eliminará la rutina de suministro seleccionada.",
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/medication-authorizations/${authorization.id}`);
        await this.loadAuthorizations(this.pagination.current_page || 1);
        await showInfirmarySuccess("Rutina eliminada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar la rutina."));
      }
    },
    async openAdministration(authorization) {
      const source = this.selectedAuthorization?.id === authorization.id ? this.selectedAuthorization : authorization;

      if (source.regimen_type !== "sos" && !source.daily_status?.applicable) {
        await showInfirmaryWarning(source.daily_status?.detail || "Esta rutina no requiere administración hoy.");
        return;
      }

      const pendingSlot = (source.daily_status?.slots || []).find((slot) => !slot.registered);
      if (source.regimen_type !== "sos" && !pendingSlot) {
        await showInfirmaryWarning("Todas las dosis de hoy ya fueron registradas.");
        return;
      }

      this.selectedAuthorization = source;
      this.administrationForm = {
        ...this.emptyAdministrationForm(),
        schedule_id: pendingSlot?.schedule_id || null,
        medication_id: source.medication_id,
        student_profile_id: source.student_profile_id,
        quantity_administered: source.dose_amount || 1,
        dose_amount: source.dose_amount || null,
        dose_unit: source.dose_unit || "mg",
        administration_route: source.administration_route || "oral",
        schedule_reference: pendingSlot?.scheduled_time || (pendingSlot ? `Dosis ${pendingSlot.dose_order}` : source.schedule_text || ""),
      };
      this.showAdministrationModal = true;
    },
    async cancelAdministrationModal() {
      const result = await confirmInfirmaryCancel("la administración del medicamento");
      if (result.isConfirmed) this.showAdministrationModal = false;
    },
    async saveAdministration() {
      if (!this.selectedAuthorization?.id) return;

      if (this.isNoAdministration && !this.administrationForm.non_administration_reason) {
        await showInfirmaryWarning("Debes indicar el motivo de no administración.");
        return;
      }

      if (this.selectedAuthorization.regimen_type !== "sos" && !this.administrationForm.schedule_id) {
        await showInfirmaryWarning("Debes seleccionar la dosis planificada que estás registrando.");
        return;
      }

      this.administering = true;
      try {
        const payload = {
          ...this.administrationForm,
          quantity_administered: this.isNoAdministration
            ? 0
            : this.administrationForm.dose_amount || this.administrationForm.quantity_administered,
          non_administration_reason: this.isNoAdministration ? this.administrationForm.non_administration_reason : null,
        };

        await axios.post(
          `/api/infirmary/medication-authorizations/${this.selectedAuthorization.id}/administrations`,
          payload
        );
        this.showAdministrationModal = false;
        await this.fetchAuthorization(this.selectedAuthorization);
        await this.loadAuthorizations(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isNoAdministration ? "No administración registrada correctamente." : "Administración registrada correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo registrar la administración."));
      } finally {
        this.administering = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Administración de medicamentos</h4>
        <div class="text-muted">
          Rutinas de suministro por estudiante y registro diario de dosis administradas o no administradas.
        </div>
      </div>
      <div class="d-flex gap-2">
        <InfirmaryHelpButton
          title="Ayuda: administración de medicamentos"
          text="Aquí se crean rutinas de suministro, se controla su vigencia y se registra cada dosis diaria con motivo si no fue administrada."
        />
        <BButton v-if="canManage" variant="primary" @click="openCreate">Nueva rutina</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="fw-semibold">Filtros de rutinas</div>
          <InfirmaryHelpButton
            title="Ayuda: filtros de rutinas"
            text="Filtra por estudiante, medicamento, vigencia o texto libre para revisar rutinas activas e históricas."
          />
        </div>
      </template>
      <div class="row g-3">
        <div class="col-lg-3">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Estudiante, medicamento, diagnóstico o médico" @keyup.enter="loadAuthorizations(1)" />
        </div>
        <div class="col-lg-3">
          <label class="form-label">Medicamento</label>
          <BFormSelect v-model="filters.medication_id" :options="medicationOptions" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Vigencia</label>
          <BFormSelect v-model="filters.status" :options="statusOptions" />
        </div>
        <div class="col-lg-2">
          <label class="form-label">Control de hoy</label>
          <BFormSelect v-model="filters.daily_status" :options="dailyStatusOptions" />
        </div>
        <div class="col-lg-2 d-flex align-items-end gap-2">
          <BButton variant="primary" @click="loadAuthorizations(1)">Aplicar</BButton>
          <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
        </div>
        <div class="col-12">
          <InfirmaryStudentSearch button-label="Filtrar estudiante" @selected="selectStudent" />
        </div>
      </div>
    </BCard>

    <BCard>
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="fw-semibold">Rutinas de suministro</div>
          <InfirmaryHelpButton
            title="Ayuda: lista de rutinas"
            text="La tabla muestra el avance de las dosis esperadas hoy y permite identificar de inmediato las rutinas pendientes."
          />
        </div>
      </template>

      <LoadingState v-if="loading" message="Cargando rutinas..." compact />

      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Estudiante</th>
              <th>Medicamento correcto</th>
              <th>Dosis</th>
              <th>Vía</th>
              <th>Término</th>
              <th>
                <div>Administración de hoy</div>
                <small v-if="dailyStatusDate" class="text-muted fw-normal">{{ formatInfirmaryDate(dailyStatusDate) }}</small>
              </th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!authorizations.length">
              <td colspan="7" class="text-center text-muted py-4">No hay rutinas para los filtros seleccionados.</td>
            </tr>
            <template v-else>
              <tr
                v-for="authorization in authorizations"
                :key="authorization.id"
                :class="{ 'table-active': selectedAuthorization?.id === authorization.id }"
                role="button"
                @click="openView(authorization)"
              >
                <td>
                  <div class="fw-semibold">{{ studentName(authorization.student) }}</div>
                  <div class="small text-muted">{{ authorization.student?.rut || "Sin RUT" }}</div>
                </td>
                <td>
                  <div class="fw-semibold">{{ medicationName(authorization.medication) }}</div>
                  <div class="small text-muted">{{ authorization.frequency || "Sin frecuencia" }}</div>
                </td>
                <td>{{ doseLabel(authorization) }}</td>
                <td>{{ optionLabel(routeOptions, authorization.administration_route || "oral") }}</td>
                <td>
                  <div>{{ regimenLabel(authorization) }}</div>
                  <div class="small text-muted">
                    {{ formatInfirmaryDate(authorization.start_date) }} - {{ formatInfirmaryDate(authorization.end_date) }}
                  </div>
                </td>
                <td>
                  <div class="infirmary-daily-status">
                    <BBadge :variant="dailyStatusVariant(authorization)">
                      {{ dailyStatusLabel(authorization) }}
                    </BBadge>
                    <small>{{ dailyStatusDetail(authorization) }}</small>
                  </div>
                </td>
                <td class="text-end">
                  <div class="infirmary-medication-actions">
                    <button
                      type="button"
                      class="cnsc-action-btn cnsc-action-btn--view"
                      title="Ver ficha"
                      aria-label="Ver ficha"
                      @click.stop="openView(authorization)"
                    >
                      <i class="mdi mdi-eye-outline"></i>
                    </button>
                    <button
                      v-if="canManage"
                      type="button"
                      class="cnsc-action-btn cnsc-action-btn--edit"
                      title="Editar rutina"
                      aria-label="Editar rutina"
                      @click.stop="openEdit(authorization)"
                    >
                      <i class="mdi mdi-pencil-outline"></i>
                    </button>
                    <button
                      v-if="canManage"
                      type="button"
                      class="cnsc-action-btn cnsc-action-btn--success"
                      :title="canRegisterToday(authorization) ? 'Registrar dosis' : dailyStatusDetail(authorization)"
                      aria-label="Registrar dosis"
                      :disabled="!canRegisterToday(authorization)"
                      @click.stop="openAdministration(authorization)"
                    >
                      <i class="mdi mdi-pill"></i>
                    </button>
                    <button
                      v-if="canManage"
                      type="button"
                      class="cnsc-action-btn cnsc-action-btn--delete"
                      title="Eliminar rutina"
                      aria-label="Eliminar rutina"
                      @click.stop="remove(authorization)"
                    >
                      <i class="mdi mdi-trash-can-outline"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="loadAuthorizations"
        />
      </div>
    </BCard>

    <BModal v-model="showViewModal" title="Ficha de suministro" size="xl" hide-footer>
      <div v-if="selectedAuthorization" class="infirmary-medication-view">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
          <div>
            <h5 class="mb-1">{{ medicationName(selectedAuthorization.medication) }}</h5>
            <div class="text-muted">{{ studentName(selectedAuthorization.student) }}</div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <BButton
              v-if="canExport"
              size="sm"
              variant="outline-danger"
              @click="exportAuthorizationPdf(selectedAuthorization)"
            >
              <i class="mdi mdi-file-pdf-box me-1"></i>
              PDF ficha
            </BButton>
            <BButton
              v-if="canManage"
              size="sm"
              variant="outline-primary"
              @click="showViewModal = false; openEdit(selectedAuthorization)"
            >
              <i class="mdi mdi-pencil-outline me-1"></i>
              Editar rutina
            </BButton>
            <BButton
              v-if="canManage"
              size="sm"
              variant="primary"
              :disabled="!canRegisterToday(selectedAuthorization)"
              @click="showViewModal = false; openAdministration(selectedAuthorization)"
            >
              <i class="mdi mdi-pill me-1"></i>
              Registrar dosis
            </BButton>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-4">
            <InfirmaryStudentContextCard :student="selectedStudentContext" />
          </div>
          <div class="col-lg-8">
            <div class="infirmary-medication-detail-grid">
              <div class="infirmary-medication-detail">
                <span>Medicamento correcto</span>
                <strong>{{ medicationName(selectedAuthorization.medication) }}</strong>
              </div>
              <div class="infirmary-medication-detail">
                <span>Dosis</span>
                <strong>{{ doseLabel(selectedAuthorization) }}</strong>
              </div>
              <div class="infirmary-medication-detail">
                <span>Vía</span>
                <strong>{{ optionLabel(routeOptions, selectedAuthorization.administration_route || "oral") }}</strong>
              </div>
              <div class="infirmary-medication-detail">
                <span>Frecuencia</span>
                <strong>{{ selectedAuthorization.frequency || "-" }}</strong>
              </div>
              <div class="infirmary-medication-detail">
                <span>Horario</span>
                <strong>{{ selectedAuthorization.schedule_text || "-" }}</strong>
              </div>
              <div class="infirmary-medication-detail">
                <span>Término</span>
                <strong>{{ regimenLabel(selectedAuthorization) }}</strong>
                <small>{{ formatInfirmaryDate(selectedAuthorization.start_date) }} - {{ formatInfirmaryDate(selectedAuthorization.end_date) }}</small>
              </div>
              <div class="infirmary-medication-detail">
                <span>Autorización médica</span>
                <strong>{{ formatInfirmaryDate(selectedAuthorization.medical_authorization_expires_at) }}</strong>
              </div>
              <div class="infirmary-medication-detail">
                <span>Autorización apoderado</span>
                <strong>{{ formatInfirmaryDate(selectedAuthorization.guardian_authorization_expires_at) }}</strong>
              </div>
              <div class="infirmary-medication-detail">
                <span>Stock disponible</span>
                <strong>{{ selectedAuthorization.medication?.current_stock || 0 }} {{ selectedAuthorization.medication?.unit || "" }}</strong>
              </div>
              <div class="infirmary-medication-detail">
                <span>Vigencia</span>
                <div><InfirmaryStatusBadge :status="selectedAuthorization.status" /></div>
              </div>
              <div class="infirmary-medication-detail">
                <span>Administración de hoy</span>
                <div><BBadge :variant="dailyStatusVariant(selectedAuthorization)">{{ dailyStatusLabel(selectedAuthorization) }}</BBadge></div>
                <small>{{ dailyStatusDetail(selectedAuthorization) }}</small>
              </div>
            </div>

            <div class="infirmary-medication-note">
              <span>Motivo clínico / indicación</span>
              <p>{{ selectedAuthorization.diagnosis || "Sin diagnóstico." }}</p>
            </div>
            <div class="infirmary-medication-note">
              <span>Observaciones</span>
              <p>{{ selectedAuthorization.observations || "Sin observaciones." }}</p>
            </div>
          </div>
        </div>

        <div>
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h5 class="mb-0">Detalle de administración</h5>
            <BButton
              v-if="canManage"
              size="sm"
              variant="primary"
              :disabled="!canRegisterToday(selectedAuthorization)"
              @click="showViewModal = false; openAdministration(selectedAuthorization)"
            >
              Registrar dosis
            </BButton>
          </div>
          <div v-if="!(selectedAuthorization.administrations || []).length" class="text-muted">
            No hay administraciones registradas.
          </div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Medicamento correcto</th>
                  <th>Dosis</th>
                  <th>Vía</th>
                  <th>Día</th>
                  <th>Hora</th>
                  <th>Estado / motivo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in selectedAuthorization.administrations" :key="item.id">
                  <td>{{ medicationName(item.medication || selectedAuthorization.medication) }}</td>
                  <td>{{ doseLabel(item) }}</td>
                  <td>{{ optionLabel(routeOptions, item.administration_route || selectedAuthorization.administration_route || "oral") }}</td>
                  <td>{{ administrationDay(item.administered_at) }}</td>
                  <td>{{ administrationHour(item.administered_at) }}</td>
                  <td>
                    <InfirmaryStatusBadge :status="item.administration_status || 'administrada'" />
                    <div v-if="item.administration_status === 'no_administrada'" class="small text-muted mt-1">
                      {{ nonAdministrationReasonLabel(item.non_administration_reason) }}
                    </div>
                    <div v-if="item.observations" class="small text-muted mt-1">{{ item.observations }}</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <InfirmaryDocumentPanel
          :documents="selectedAuthorization.documents || []"
          :upload-url="`/api/infirmary/medication-authorizations/${selectedAuthorization.id}/documents`"
          :student-id="selectedAuthorization.student_profile_id"
          :categories="catalogs.document_categories || []"
          title="Adjuntos clínicos"
          help-text="Adjunta receta, autorización médica, autorización del apoderado u otros respaldos de la rutina."
          @refresh="fetchAuthorization(selectedAuthorization)"
        />

        <div class="d-flex justify-content-end">
          <BButton variant="outline-secondary" @click="showViewModal = false">Cerrar</BButton>
        </div>
      </div>
    </BModal>

    <BModal v-model="showModal" :title="isEditing ? 'Editar rutina de suministro' : 'Nueva rutina de suministro'" size="xl" hide-footer>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Alumna</label>
          <InfirmaryStudentSearch button-label="Seleccionar" @selected="selectFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-2">Seleccionada: {{ form.student_label }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Medicamento correcto</label>
          <BFormSelect v-model="form.medication_id" :options="medicationOptions.filter((item) => item.value !== null)" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Motivo clínico / indicación</label>
          <BFormInput v-model="form.diagnosis" placeholder="Ej: tratamiento indicado por receta médica" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Dosis</label>
          <BFormInput v-model="form.dose_amount" type="number" min="0.01" step="0.01" placeholder="Ej: 5" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Unidad</label>
          <BFormSelect v-model="form.dose_unit" :options="doseUnitOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Vía</label>
          <BFormSelect v-model="form.administration_route" :options="routeOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo de término</label>
          <BFormSelect v-model="form.regimen_type" :options="regimenOptions" @update:model-value="syncFormSchedules" />
        </div>
        <div v-if="!isSosRegimen" class="col-md-4">
          <label class="form-label">Dosis por día</label>
          <BFormInput
            v-model="form.daily_dose_count"
            type="number"
            min="1"
            max="12"
            step="1"
            @change="syncFormSchedules"
          />
          <div class="form-text">{{ dailyFrequencyLabel(form.daily_dose_count) }}</div>
        </div>
        <div v-if="!isSosRegimen" class="col-md-4">
          <label class="form-label">Horario de aplicación</label>
          <BFormSelect
            v-model="form.schedule_mode"
            :options="scheduleModeOptions"
            @update:model-value="handleScheduleModeChange"
          />
        </div>
        <div v-if="!isSosRegimen && form.schedule_mode === 'fixed_time'" class="col-12">
          <label class="form-label">Horas de aplicación</label>
          <div class="infirmary-schedule-grid">
            <div v-for="schedule in form.schedules" :key="schedule.dose_order" class="infirmary-schedule-field">
              <span>Dosis {{ schedule.dose_order }}</span>
              <BFormInput v-model="schedule.scheduled_time" type="time" />
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha inicio</label>
          <BFormInput v-model="form.start_date" type="date" />
        </div>
        <div v-if="isDurationRegimen" class="col-md-3">
          <label class="form-label">{{ durationQuantityLabel }}</label>
          <BFormInput v-model="form.duration_quantity" type="number" min="1" step="1" />
        </div>
        <div v-if="isSpecificEndDate" class="col-md-3">
          <label class="form-label">Fecha de término</label>
          <BFormInput v-model="form.end_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Vence autorización médica</label>
          <BFormInput v-model="form.medical_authorization_expires_at" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Vence autorización apoderado</label>
          <BFormInput v-model="form.guardian_authorization_expires_at" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Médico tratante</label>
          <BFormInput v-model="form.physician_name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.status" :options="statusOptions.filter((item) => item.value !== null)" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="3" placeholder="Indicaciones del apoderado, cuidados especiales o respaldos pendientes." />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar rutina" }}
          </BButton>
        </div>
      </div>
    </BModal>

    <BModal v-model="showAdministrationModal" title="Registrar dosis diaria" size="lg" hide-footer>
      <div class="row g-3">
        <div v-if="selectedAuthorization?.regimen_type !== 'sos'" class="col-12">
          <label class="form-label">Dosis planificada</label>
          <BFormSelect
            v-model="administrationForm.schedule_id"
            :options="administrationScheduleOptions"
            @update:model-value="selectAdministrationSchedule"
          />
        </div>
        <div class="col-md-6">
          <label class="form-label">Día y hora</label>
          <BFormInput v-model="administrationForm.administered_at" type="datetime-local" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="administrationForm.administration_status" :options="administrationStatusOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Funcionario</label>
          <BFormSelect v-model="administrationForm.administered_by_user_id" :options="userOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Medicamento correcto</label>
          <BFormSelect v-model="administrationForm.medication_id" :options="medicationOptions.filter((item) => item.value !== null)" />
        </div>
        <div v-if="!isNoAdministration" class="col-md-4">
          <label class="form-label">Dosis</label>
          <BFormInput v-model="administrationForm.dose_amount" type="number" min="0.01" step="0.01" />
        </div>
        <div v-if="!isNoAdministration" class="col-md-4">
          <label class="form-label">Unidad</label>
          <BFormSelect v-model="administrationForm.dose_unit" :options="doseUnitOptions" />
        </div>
        <div v-if="!isNoAdministration" class="col-md-4">
          <label class="form-label">Vía</label>
          <BFormSelect v-model="administrationForm.administration_route" :options="routeOptions" />
        </div>
        <div v-if="isNoAdministration" class="col-12">
          <label class="form-label">Motivo de no administración</label>
          <BFormSelect v-model="administrationForm.non_administration_reason" :options="nonAdministrationReasonOptions" />
        </div>
        <div class="col-12">
          <label class="form-label">Referencia de horario</label>
          <BFormInput v-model="administrationForm.schedule_reference" :readonly="selectedAuthorization?.regimen_type !== 'sos'" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="administrationForm.observations" rows="3" placeholder="Contexto del suministro, indicaciones o información útil del registro diario." />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelAdministrationModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="administering" @click="saveAdministration">
            {{ administering ? "Registrando..." : "Registrar dosis" }}
          </BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>

<style scoped>
.infirmary-medication-actions {
  align-items: center;
  display: inline-flex;
  gap: 0.55rem;
  justify-content: flex-end;
}

.infirmary-medication-actions .cnsc-action-btn + .cnsc-action-btn {
  margin-left: 0 !important;
}

.infirmary-medication-actions .cnsc-action-btn:disabled {
  cursor: not-allowed;
  opacity: 0.42;
}

.infirmary-daily-status {
  align-items: flex-start;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 150px;
}

.infirmary-daily-status small {
  color: #74788d;
  line-height: 1.3;
}

.infirmary-schedule-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.infirmary-schedule-field {
  display: grid;
  gap: 0.35rem;
}

.infirmary-schedule-field span {
  color: #596275;
  font-size: 0.82rem;
  font-weight: 600;
}

.infirmary-medication-view {
  display: grid;
  gap: 1rem;
}

.infirmary-medication-detail-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
}

.infirmary-medication-detail,
.infirmary-medication-note {
  background: #fff;
  border: 1px solid #e5eaf4;
  border-radius: 8px;
  display: grid;
  gap: 0.2rem;
  padding: 0.75rem;
}

.infirmary-medication-detail span,
.infirmary-medication-note span {
  color: #7a8498;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.infirmary-medication-detail strong {
  color: #303846;
  font-size: 0.95rem;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.infirmary-medication-detail small {
  color: #7a8498;
}

.infirmary-medication-note {
  margin-top: 0.75rem;
}

.infirmary-medication-note p {
  color: #3f4858;
  margin-bottom: 0;
  overflow-wrap: anywhere;
}
</style>
