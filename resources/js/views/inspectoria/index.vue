<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { getPdfMake } from "../../utils/pdfmake";
import { downloadWithdrawalReceipt } from "../../utils/withdrawal-pdf";

const localDateTime = (minutes = 0) => {
  const date = new Date(Date.now() + minutes * 60000 - new Date().getTimezoneOffset() * 60000);
  return date.toISOString().slice(0, 16);
};
const today = () => localDateTime().slice(0, 10);
const attentionForm = () => ({ student_profile_id: null, attended_at: localDateTime(), request_types: [], actions_taken: [], priority: "normal", brief_note: "", guardian_notified: false, requires_follow_up: false, psychosocial_referral_user_id: null });
const assignmentForm = () => ({ id: null, academic_year_id: null, course_section_id: null, inspector_staff_id: null, physical_location: "", starts_on: today(), ends_on: "", active: true, notes: "" });
const passForm = () => ({ id: null, student_profile_id: null, destination: "", destination_detail: "", valid_from: localDateTime(), valid_until: localDateTime(40), reason: "", regulation_version: "Reglamento Inspectoría vigente", signature_name: "", signature_rut: "", notes: "" });
const logForm = () => ({ id: null, student_profile_id: null, course_section_id: null, happened_at: localDateTime(), category: "novedad", priority: "media", status: "registrado", title: "", detail: "", requires_follow_up: false, follow_up_note: "" });
const restrictionForm = () => ({ id: null, student_profile_id: null, restricted_person_name: "", restricted_person_rut: "", restricted_person_relationship: "", restriction_type: "orden_alejamiento", reason: "", legal_reference: "", starts_on: today(), ends_on: "", active: true });

const routeMap = {
  "/inspectoria/atenciones": "attentions",
  "/inspectoria/asignaciones": "assignments",
  "/inspectoria/pases": "passes",
  "/inspectoria/alumnas": "students",
  "/inspectoria/restricciones": "restrictions",
  "/inspectoria/retiros": "withdrawals",
  "/inspectoria/bitacora": "dailyLog",
};

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      catalogsLoading: true,
      loading: false,
      saving: false,
      error: null,
      catalogs: { academic_years: [], courses: [], students: [], inspectors: [], request_types: [], attention_actions: [], psychosocial_professionals: [], destinations: [], log_categories: [], withdrawal_statuses: [], withdrawal_reasons: [], withdrawal_relationships: [], pickup_restriction_types: [], capabilities: {} },
      tabs: [
        { key: "attentions", label: "Atenciones", icon: "bx-message-square-check", route: "/inspectoria/atenciones" },
        { key: "assignments", label: "Cursos e inspectoras", icon: "bx-map-pin", route: "/inspectoria/asignaciones" },
        { key: "passes", label: "Pases prioritarios", icon: "bx-id-card", route: "/inspectoria/pases" },
        { key: "students", label: "Alumnas y fichas", icon: "bx-group", route: "/inspectoria/alumnas" },
        { key: "restrictions", label: "Restricciones", icon: "bx-error-circle", route: "/inspectoria/restricciones" },
        { key: "withdrawals", label: "Retiros", icon: "bx-log-out-circle", route: "/inspectoria/retiros" },
        { key: "dailyLog", label: "Bitácora diaria", icon: "bx-notepad", route: "/inspectoria/bitacora" },
      ],
      attention: attentionForm(),
      attentionSearch: "",
      attentionItems: [],
      attentionFilters: { search: "", date: today(), follow_up: null },
      showAttentionModal: false,
      selectedAttention: null,
      assignment: assignmentForm(),
      assignmentItems: [],
      assignmentFilters: { academic_year_id: null, inspector_staff_id: null, active: true },
      showAssignmentModal: false,
      selectedAssignment: null,
      pass: passForm(),
      passSearch: "",
      passItems: [],
      passFilters: { search: "", status: null, date: today() },
      showPassModal: false,
      selectedPass: null,
      studentItems: [],
      studentFilters: { search: "", course_section_id: null },
      selectedStudentFile: null,
      showStudentFile: false,
      studentFileLoading: false,
      restriction: restrictionForm(),
      restrictionSearch: "",
      restrictionItems: [],
      restrictionFilters: { search: "", course_section_id: null, restriction_type: null, active: true },
      selectedRestriction: null,
      showRestrictionModal: false,
      withdrawalMode: "today",
      withdrawalItems: [],
      withdrawalFilters: { search: "", course_section_id: null, status: null, date_from: "", date_to: "" },
      selectedWithdrawal: null,
      showWithdrawalFile: false,
      withdrawalFileLoading: false,
      dailyLog: logForm(),
      dailyStudentSearch: "",
      dailyLogItems: [],
      logFilters: { search: "", date: today(), category: null, priority: null },
      showDailyLogModal: false,
      selectedDailyLog: null,
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    activeTab() { return routeMap[this.$route.path] || "attentions"; },
    activeMeta() {
      const meta = {
        attentions: ["Atenciones de Inspectoría", "Consulta el historial en tabla y abre cada ficha o registra una nueva atención desde el modal."],
        assignments: ["Cursos e inspectoras", "Consulta las asignaciones en tabla y abre cada ficha o registra una nueva desde el modal."],
        passes: ["Pases prioritarios de Inspectoría", "Consulta los pases en tabla y abre cada ficha o emite uno nuevo desde el modal. Siempre prevalecen sobre Biblioteca."],
        students: ["Alumnas y fichas", "Consulta las alumnas en tabla y abre su ficha modal con el contexto e historial de Inspectoría."],
        restrictions: ["Restricciones de retiro", "Registra órdenes de alejamiento y otras restricciones para que Portería reciba una alerta inmediata."],
        withdrawals: ["Retiros de alumnas", "Revisa los retiros registrados por Portería para todos los cursos bajo tu responsabilidad."],
        dailyLog: ["Bitácora diaria", "Consulta los hechos de la jornada en tabla y registra o revisa cada ficha desde un modal."],
      };
      return meta[this.activeTab];
    },
    studentOptions() { return (this.catalogs.students || []).map((student) => ({ ...student, text: `${student.name} · ${student.rut || "Sin RUT"} · ${student.course || "Sin curso"}` })); },
    activeCourses() {
      const yearId = Number(this.assignment.academic_year_id || this.assignmentFilters.academic_year_id || this.catalogs.active_academic_year_id);
      return (this.catalogs.courses || []).filter((course) => !yearId || Number(course.academic_year_id) === yearId);
    },
    courseOptions() { return [{ value: null, text: "Todos los cursos" }, ...(this.catalogs.courses || []).map((course) => ({ value: course.id, text: course.display_name }))]; },
    inspectorOptions() { return [{ value: null, text: "Selecciona inspectora" }, ...(this.catalogs.inspectors || []).map((staff) => ({ value: staff.id, text: `${staff.full_name} · ${staff.rut || "Sin RUT"}` }))]; },
    destinationOptions() { return [{ value: "", text: "Selecciona destino" }, ...(this.catalogs.destinations || []).map((item) => ({ value: item.value, text: item.label }))]; },
    categoryOptions() { return (this.catalogs.log_categories || []).map((item) => ({ value: item.value, text: item.label })); },
    withdrawalStatusOptions() { return [{ value: null, text: "Todos los estados" }, ...(this.catalogs.withdrawal_statuses || []).map((item) => ({ value: item.value, text: item.label }))]; },
    restrictionTypeOptions() { return [{ value: null, text: "Todos los tipos" }, ...(this.catalogs.pickup_restriction_types || []).map((item) => ({ value: item.value, text: item.label }))]; },
    psychosocialProfessionalOptions() { return [{ value: null, text: "Selecciona psicóloga o trabajadora social" }, ...(this.catalogs.psychosocial_professionals || []).map((item) => ({ value: item.id, text: `${item.profession_label} · ${item.name}` }))]; },
    selectedPsychosocialProfessional() { return (this.catalogs.psychosocial_professionals || []).find((item) => Number(item.id) === Number(this.attention.psychosocial_referral_user_id)) || null; },
    hasPsychosocialReferral() { return this.attention.actions_taken.includes("derivacion_psicosocial"); },
    selectedRestrictionStudent() { return (this.catalogs.students || []).find((student) => Number(student.id) === Number(this.restriction.student_profile_id)) || null; },
  },
  watch: {
    "$route.path"() { this.loadActive(1); },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadActive(1);
  },
  methods: {
    navigate(route) { if (route !== this.$route.path) this.$router.push(route); },
    resetAssignment() { this.assignment = { ...assignmentForm(), academic_year_id: this.catalogs.active_academic_year_id }; },
    resetPass() { this.pass = passForm(); this.passSearch = ""; },
    passForm() { return passForm(); },
    async loadCatalogs() {
      this.catalogsLoading = true;
      try {
        const { data } = await axios.get("/api/inspectoria/catalogs");
        this.catalogs = data;
        this.assignment.academic_year_id = data.active_academic_year_id;
        this.assignmentFilters.academic_year_id = data.active_academic_year_id;
      } catch (error) { this.captureError(error); }
      finally { this.catalogsLoading = false; }
    },
    async loadActive(page = 1) {
      if (this.catalogsLoading) return;
      const loaders = { attentions: this.loadAttentions, assignments: this.loadAssignments, passes: this.loadPasses, students: this.loadStudents, restrictions: this.loadRestrictions, withdrawals: this.loadWithdrawals, dailyLog: this.loadDailyLogs };
      await loaders[this.activeTab](page);
    },
    setPagination(data) { this.pagination = { current_page: data.current_page || 1, total: data.total || 0, per_page: data.per_page || 15 }; },
    studentFromText(text) { return this.studentOptions.find((student) => student.text === text) || null; },
    syncAttentionStudent() { const student = this.studentFromText(this.attentionSearch); this.attention.student_profile_id = student?.id || null; },
    openNewAttention() {
      this.selectedAttention = null;
      this.attention = attentionForm();
      this.attentionSearch = "";
      this.showAttentionModal = true;
    },
    openAttentionFile(item) {
      this.selectedAttention = item;
      this.showAttentionModal = true;
    },
    resetAttentionModal() {
      this.selectedAttention = null;
      this.attention = attentionForm();
      this.attentionSearch = "";
    },
    syncPassStudent() {
      const student = this.studentFromText(this.passSearch);
      this.pass.student_profile_id = student?.id || null;
      if (student) { this.pass.signature_name = student.name; this.pass.signature_rut = student.rut || ""; }
    },
    syncDailyStudent() { const student = this.studentFromText(this.dailyStudentSearch); this.dailyLog.student_profile_id = student?.id || null; if (student?.course_section_id) this.dailyLog.course_section_id = student.course_section_id; },
    requestLabel(value) { return this.optionLabel(this.catalogs.request_types, value); },
    actionLabel(value) { return this.optionLabel(this.catalogs.attention_actions, value); },
    destinationLabel(value) { return this.optionLabel(this.catalogs.destinations, value); },
    categoryLabel(value) { return this.optionLabel(this.catalogs.log_categories, value); },
    optionLabel(options, value) { return (options || []).find((item) => item.value === value)?.label || String(value || "-").replaceAll("_", " "); },
    toggleArray(target, value) { const index = target.indexOf(value); index >= 0 ? target.splice(index, 1) : target.push(value); },
    toggleAttentionAction(value) {
      this.toggleArray(this.attention.actions_taken, value);
      if (value !== "derivacion_psicosocial") return;
      if (this.attention.actions_taken.includes(value)) this.attention.requires_follow_up = true;
      else this.attention.psychosocial_referral_user_id = null;
    },
    formatDate(value) { if (!value) return "-"; return new Intl.DateTimeFormat("es-CL", { dateStyle: "short", timeStyle: "short" }).format(new Date(value)); },
    initials(name) { return String(name || "A").split(/\s+/).slice(0, 2).map((p) => p[0]).join("").toUpperCase(); },
    async loadAttentions(page = 1) {
      this.loading = true; this.error = null;
      try { const { data } = await axios.get("/api/inspectoria/attentions", { params: { page, ...this.attentionFilters } }); this.attentionItems = data.data || []; this.setPagination(data); }
      catch (error) { this.captureError(error); } finally { this.loading = false; }
    },
    async saveAttention() {
      this.syncAttentionStudent();
      if (!this.attention.student_profile_id || !this.attention.request_types.length) return this.warn("Selecciona una alumna y al menos un tipo de solicitud.");
      if (this.hasPsychosocialReferral && !this.attention.psychosocial_referral_user_id) return this.warn("Selecciona la psicóloga o trabajadora social que recibirá la derivación.");
      this.saving = true;
      try {
        await axios.post("/api/inspectoria/attentions", this.attention);
        this.showAttentionModal = false; this.resetAttentionModal(); await this.loadAttentions(1); await this.success("Atención registrada");
      } catch (error) { this.captureError(error, true); } finally { this.saving = false; }
    },
    async loadAssignments(page = 1) {
      this.loading = true; this.error = null;
      try { const { data } = await axios.get("/api/inspectoria/course-assignments", { params: { page, ...this.assignmentFilters } }); this.assignmentItems = data.data || []; this.setPagination(data); }
      catch (error) { this.captureError(error); } finally { this.loading = false; }
    },
    openNewAssignment() {
      this.selectedAssignment = null;
      this.resetAssignment();
      this.showAssignmentModal = true;
    },
    openAssignmentFile(item) {
      this.selectedAssignment = item;
      this.showAssignmentModal = true;
    },
    editAssignment(item) {
      this.selectedAssignment = null;
      this.assignment = { id: item.id, academic_year_id: item.academic_year_id, course_section_id: item.course_section_id, inspector_staff_id: item.inspector_staff_id, physical_location: item.physical_location || "", starts_on: String(item.starts_on || "").slice(0, 10), ends_on: String(item.ends_on || "").slice(0, 10), active: item.active, notes: item.notes || "" };
      this.showAssignmentModal = true;
    },
    resetAssignmentModal() {
      this.selectedAssignment = null;
      this.resetAssignment();
    },
    async saveAssignment() {
      if (!this.assignment.course_section_id || !this.assignment.inspector_staff_id) return this.warn("Selecciona un curso y una inspectora.");
      this.saving = true;
      try {
        if (this.assignment.id) await axios.put(`/api/inspectoria/course-assignments/${this.assignment.id}`, this.assignment);
        else await axios.post("/api/inspectoria/course-assignments", this.assignment);
        this.showAssignmentModal = false; this.resetAssignmentModal(); await this.loadAssignments(1); await this.success("Asignación guardada");
      } catch (error) { this.captureError(error, true); } finally { this.saving = false; }
    },
    async closeAssignment(item) {
      const result = await Swal.fire({ icon: "warning", title: "Finalizar asignación", text: `Se cerrará la asignación de ${item.course_section?.display_name}.`, showCancelButton: true, confirmButtonText: "Finalizar", cancelButtonText: "Cancelar" });
      if (!result.isConfirmed) return;
      try { await axios.delete(`/api/inspectoria/course-assignments/${item.id}`); await this.loadAssignments(this.pagination.current_page); }
      catch (error) { this.captureError(error, true); }
    },
    async loadPasses(page = 1) {
      this.loading = true; this.error = null;
      try { const { data } = await axios.get("/api/inspectoria/passes", { params: { page, ...this.passFilters } }); this.passItems = data.data || []; this.setPagination(data); }
      catch (error) { this.captureError(error); } finally { this.loading = false; }
    },
    openNewPass() {
      this.selectedPass = null;
      this.resetPass();
      this.showPassModal = true;
    },
    openPassFile(item) {
      this.selectedPass = item;
      this.showPassModal = true;
    },
    editPass(item) {
      this.selectedPass = null;
      const student = this.catalogs.students.find((candidate) => Number(candidate.id) === Number(item.student_profile_id));
      this.pass = { ...passForm(), id: item.id, student_profile_id: item.student_profile_id, destination: item.destination, destination_detail: item.destination_detail || "", valid_from: String(item.valid_from).slice(0, 16), valid_until: String(item.valid_until).slice(0, 16), reason: item.reason, regulation_version: item.regulation_version, signature_name: item.signature_name || "", signature_rut: item.signature_rut || "", notes: item.notes || "" };
      this.passSearch = student ? this.studentOptions.find((candidate) => candidate.id === student.id)?.text : item.student_name_snapshot;
      this.showPassModal = true;
    },
    resetPassModal() {
      this.selectedPass = null;
      this.resetPass();
    },
    async savePass() {
      this.syncPassStudent();
      if (!this.pass.student_profile_id || !this.pass.destination || !this.pass.reason.trim()) return this.warn("Completa alumna, destino y motivo del pase.");
      this.saving = true;
      try {
        const { data } = this.pass.id ? await axios.put(`/api/inspectoria/passes/${this.pass.id}`, this.pass) : await axios.post("/api/inspectoria/passes", this.pass);
        this.showPassModal = false; this.resetPassModal(); await this.loadPasses(1);
        await Swal.fire({ icon: "success", title: "Pase emitido", text: data.data?.notes?.includes("prevaleció") ? "Se emitió el pase prioritario y se anularon pases de Biblioteca superpuestos." : "El pase prioritario quedó vigente.", confirmButtonText: "Entendido" });
      } catch (error) { this.captureError(error, true); } finally { this.saving = false; }
    },
    async transitionPass(item, status) {
      const result = await Swal.fire({ icon: status === "utilizado" ? "question" : "warning", title: status === "utilizado" ? "Registrar uso" : "Anular pase", showCancelButton: true, confirmButtonText: "Confirmar", cancelButtonText: "Cancelar" });
      if (!result.isConfirmed) return;
      try { await axios.post(`/api/inspectoria/passes/${item.id}/${status}`); await this.loadPasses(this.pagination.current_page); }
      catch (error) { this.captureError(error, true); }
    },
    downloadPass(item) {
      const destination = item.destination_detail || this.destinationLabel(item.destination);
      getPdfMake().createPdf({ pageSize: "A5", pageMargins: [32, 30, 32, 30], content: [
        { text: "PASE PRIORITARIO DE INSPECTORÍA", style: "title" },
        { text: item.pass_code, style: "code" },
        { text: "PRIORIDAD 100 · PREVALECE SOBRE OTROS PASES", style: "priority" },
        { columns: [{ width: "*", stack: [{ text: "ALUMNA", style: "label" }, { text: item.student_name_snapshot, style: "value" }, { text: item.student_rut_snapshot || "Sin RUT" }] }, { width: "*", stack: [{ text: "CURSO", style: "label" }, { text: item.course_section?.display_name || "Sin curso", style: "value" }] }], margin: [0, 18, 0, 14] },
        { table: { widths: ["*", "*"], body: [[{ stack: [{ text: "DESTINO", style: "label" }, { text: destination, style: "value" }] }, { stack: [{ text: "VIGENCIA", style: "label" }, { text: `${this.formatDate(item.valid_from)}\n${this.formatDate(item.valid_until)}`, style: "valueSmall" }] }]] }, layout: "lightHorizontalLines" },
        { text: "MOTIVO", style: "label", margin: [0, 16, 0, 4] }, { text: item.reason },
        { text: `Emitido por: ${item.inspector_name_snapshot || item.issued_by?.name || "Inspectoría"}`, margin: [0, 22, 0, 4] },
        { text: `Firma: ${item.signature_name || "________________________"}   RUT: ${item.signature_rut || "________________"}` },
      ], styles: { title: { fontSize: 17, bold: true, color: "#173b57", alignment: "center" }, code: { fontSize: 11, bold: true, alignment: "center", margin: [0, 5] }, priority: { fontSize: 9, bold: true, color: "#fff", fillColor: "#b42318", alignment: "center", margin: [0, 4, 0, 8] }, label: { fontSize: 8, bold: true, color: "#61778a" }, value: { fontSize: 12, bold: true, color: "#173b57", margin: [0, 3] }, valueSmall: { fontSize: 9, bold: true } } }).download(`pase-inspectoria-${item.pass_code}.pdf`);
    },
    async loadStudents(page = 1) {
      this.loading = true; this.error = null;
      try { const { data } = await axios.get("/api/inspectoria/students", { params: { page, ...this.studentFilters } }); this.studentItems = data.data || []; this.setPagination(data); }
      catch (error) { this.captureError(error); } finally { this.loading = false; }
    },
    async openStudentFile(student) {
      this.showStudentFile = true; this.studentFileLoading = true; this.selectedStudentFile = null;
      try { const { data } = await axios.get(`/api/inspectoria/students/${student.id}`); this.selectedStudentFile = data.data; }
      catch (error) { this.captureError(error, true); this.showStudentFile = false; } finally { this.studentFileLoading = false; }
    },
    restrictionTypeLabel(value) { return this.optionLabel(this.catalogs.pickup_restriction_types, value); },
    syncRestrictionStudent() {
      const student = this.studentFromText(this.restrictionSearch);
      this.restriction.student_profile_id = student?.id || null;
    },
    openNewRestriction() {
      this.selectedRestriction = null;
      this.restriction = restrictionForm();
      this.restrictionSearch = "";
      this.showRestrictionModal = true;
    },
    openRestrictionFile(item) { this.selectedRestriction = item; this.showRestrictionModal = true; },
    editRestriction(item) {
      this.selectedRestriction = null;
      this.restriction = {
        ...restrictionForm(), id: item.id, student_profile_id: item.student_profile_id,
        restricted_person_name: item.restricted_person_name, restricted_person_rut: item.restricted_person_rut || "",
        restricted_person_relationship: item.restricted_person_relationship || "", restriction_type: item.restriction_type,
        reason: item.reason, legal_reference: item.legal_reference || "", starts_on: String(item.starts_on || "").slice(0, 10),
        ends_on: item.ends_on ? String(item.ends_on).slice(0, 10) : "", active: item.active,
      };
      this.restrictionSearch = this.studentOptions.find((student) => Number(student.id) === Number(item.student_profile_id))?.text || "";
      this.showRestrictionModal = true;
    },
    resetRestrictionModal() { this.selectedRestriction = null; this.restriction = restrictionForm(); this.restrictionSearch = ""; },
    applyRestrictionGuardian(kind) {
      const student = this.selectedRestrictionStudent;
      if (!student) return;
      const backup = kind === "backup";
      this.restriction.restricted_person_name = backup ? (student.guardian_backup_name || "") : (student.guardian_name || "");
      this.restriction.restricted_person_rut = backup ? (student.guardian_backup_rut || "") : (student.guardian_rut || "");
      this.restriction.restricted_person_relationship = backup
        ? (student.guardian_backup_relationship || "Apoderado suplente")
        : (student.guardian_relationship || "Apoderado titular");
    },
    async loadRestrictions(page = 1) {
      this.loading = true; this.error = null;
      try { const { data } = await axios.get("/api/inspectoria/pickup-restrictions", { params: { page, ...this.restrictionFilters } }); this.restrictionItems = data.data || []; this.setPagination(data); }
      catch (error) { this.captureError(error); } finally { this.loading = false; }
    },
    async saveRestriction() {
      this.syncRestrictionStudent();
      if (!this.restriction.student_profile_id || !this.restriction.restricted_person_name.trim() || !this.restriction.reason.trim()) return this.warn("Completa la alumna, la persona restringida y el motivo.");
      this.saving = true;
      try {
        if (this.restriction.id) await axios.put(`/api/inspectoria/pickup-restrictions/${this.restriction.id}`, this.restriction);
        else await axios.post("/api/inspectoria/pickup-restrictions", this.restriction);
        this.showRestrictionModal = false; this.resetRestrictionModal(); await this.loadRestrictions(1); await this.success("Restricción guardada");
      } catch (error) { this.captureError(error, true); } finally { this.saving = false; }
    },
    async closeRestriction(item) {
      const result = await Swal.fire({ icon: "warning", title: "Finalizar restricción", text: `La alerta para ${item.restricted_person_name} dejará de aparecer en Portería.`, showCancelButton: true, confirmButtonText: "Finalizar", cancelButtonText: "Cancelar" });
      if (!result.isConfirmed) return;
      try { await axios.delete(`/api/inspectoria/pickup-restrictions/${item.id}`); await this.loadRestrictions(this.pagination.current_page); }
      catch (error) { this.captureError(error, true); }
    },
    async setWithdrawalMode(mode) {
      if (mode === this.withdrawalMode) return;
      this.withdrawalMode = mode;
      await this.loadWithdrawals(1);
    },
    async loadWithdrawals(page = 1) {
      this.loading = true; this.error = null;
      try {
        const { data } = await axios.get("/api/inspectoria/withdrawals", {
          params: { page, scope: this.withdrawalMode, ...this.withdrawalFilters },
        });
        this.withdrawalItems = data.data || [];
        this.setPagination(data);
      } catch (error) { this.captureError(error); } finally { this.loading = false; }
    },
    async openWithdrawalFile(item) {
      this.showWithdrawalFile = true; this.withdrawalFileLoading = true; this.selectedWithdrawal = null;
      try { const { data } = await axios.get(`/api/inspectoria/withdrawals/${item.id}`); this.selectedWithdrawal = data.data; }
      catch (error) { this.captureError(error, true); this.showWithdrawalFile = false; } finally { this.withdrawalFileLoading = false; }
    },
    withdrawalReasonLabel(value) { return this.optionLabel(this.catalogs.withdrawal_reasons, value); },
    withdrawalRelationshipLabel(value) { return this.optionLabel(this.catalogs.withdrawal_relationships, value); },
    withdrawalStatusLabel(value) { return this.optionLabel(this.catalogs.withdrawal_statuses, value); },
    userName(value, fallback = "Portería") { return value && typeof value === "object" ? value.name || fallback : (typeof value === "string" && value ? value : fallback); },
    downloadWithdrawal(item) {
      downloadWithdrawalReceipt(item, {
        reasonLabel: (value) => this.withdrawalReasonLabel(value),
        relationshipLabel: (value) => this.withdrawalRelationshipLabel(value),
        statusLabel: (value) => this.withdrawalStatusLabel(value),
      });
    },
    async loadDailyLogs(page = 1) {
      this.loading = true; this.error = null;
      try { const { data } = await axios.get("/api/inspectoria/daily-log", { params: { page, ...this.logFilters } }); this.dailyLogItems = data.data || []; this.setPagination(data); }
      catch (error) { this.captureError(error); } finally { this.loading = false; }
    },
    openNewDailyLog() {
      this.selectedDailyLog = null;
      this.dailyLog = logForm();
      this.dailyStudentSearch = "";
      this.showDailyLogModal = true;
    },
    openDailyLogFile(item) {
      this.selectedDailyLog = item;
      this.showDailyLogModal = true;
    },
    editDailyLog(item) {
      this.selectedDailyLog = null;
      this.dailyLog = { ...logForm(), id: item.id, student_profile_id: item.student_profile_id, course_section_id: item.course_section_id, happened_at: String(item.happened_at).slice(0, 16), category: item.category, priority: item.priority, status: item.status || "registrado", title: item.title, detail: item.detail, requires_follow_up: item.requires_follow_up, follow_up_note: item.follow_up_note || "" };
      const student = this.studentOptions.find((candidate) => Number(candidate.id) === Number(item.student_profile_id));
      this.dailyStudentSearch = student?.text || "";
      this.showDailyLogModal = true;
    },
    resetDailyLogModal() {
      this.selectedDailyLog = null;
      this.dailyLog = logForm();
      this.dailyStudentSearch = "";
    },
    async saveDailyLog() {
      this.syncDailyStudent();
      if (!this.dailyLog.title.trim() || !this.dailyLog.detail.trim()) return this.warn("Escribe un título y el detalle de la novedad.");
      this.saving = true;
      try {
        if (this.dailyLog.id) await axios.put(`/api/inspectoria/daily-log/${this.dailyLog.id}`, this.dailyLog);
        else await axios.post("/api/inspectoria/daily-log", this.dailyLog);
        this.showDailyLogModal = false; this.resetDailyLogModal(); await this.loadDailyLogs(1); await this.success("Bitácora actualizada");
      }
      catch (error) { this.captureError(error, true); } finally { this.saving = false; }
    },
    async success(title) { await Swal.fire({ icon: "success", title, timer: 1500, showConfirmButton: false }); },
    async warn(text) { await Swal.fire({ icon: "warning", title: "Revisa el formulario", text, confirmButtonText: "Entendido" }); },
    captureError(error, popup = false) {
      const errors = error?.response?.data?.errors || {};
      const key = Object.keys(errors)[0];
      this.error = errors[key]?.[0] || error?.response?.data?.message || error?.message || "No fue posible completar la operación.";
      if (popup) Swal.fire({ icon: "error", title: "No se pudo completar", text: this.error, confirmButtonText: "Entendido" });
    },
  },
};
</script>

<template>
  <Layout>
    <main class="inspectoria-page">
      <header class="module-hero">
        <div class="hero-icon"><i class="bx bx-shield-quarter"></i></div>
        <div><span>GESTIÓN OPERATIVA</span><h2>{{ activeMeta[0] }}</h2><p>{{ activeMeta[1] }}</p></div>
        <div v-if="activeTab === 'passes'" class="priority-seal"><strong>100</strong><span>PRIORIDAD</span></div>
      </header>

      <nav class="module-tabs" aria-label="Secciones de Inspectoría">
        <button v-for="tab in tabs" :key="tab.key" type="button" :class="{ active: activeTab === tab.key }" @click="navigate(tab.route)"><i class="bx" :class="tab.icon"></i><span>{{ tab.label }}</span></button>
      </nav>

      <BAlert v-if="error" show dismissible variant="danger" @dismissed="error=null">{{ error }}</BAlert>
      <LoadingState v-if="catalogsLoading" message="Preparando módulo de Inspectoría..." />

      <template v-else-if="activeTab === 'attentions'">
        <section class="panel attentions-panel">
          <div class="panel-title attentions-heading">
            <div><span>REGISTRO DE INSPECTORÍA</span><h4>Tabla de atenciones</h4><small>{{ pagination.total }} atenciones encontradas</small></div>
            <BButton variant="primary" @click="openNewAttention"><i class="bx bx-plus me-1"></i>Nueva atención</BButton>
          </div>
          <div class="attention-filters">
            <div class="search-control attention-search"><i class="bx bx-search"></i><BFormInput v-model="attentionFilters.search" placeholder="Buscar alumna, curso, código o información" @keyup.enter="loadAttentions(1)" /></div>
            <BFormInput v-model="attentionFilters.date" type="date" aria-label="Fecha de atención" />
            <BFormSelect v-model="attentionFilters.follow_up" :options="[{value:null,text:'Todos los seguimientos'},{value:1,text:'Con seguimiento'},{value:0,text:'Sin seguimiento'}]" aria-label="Estado de seguimiento" />
            <BButton variant="outline-primary" @click="loadAttentions(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</BButton>
          </div>
          <LoadingState v-if="loading" compact message="Cargando atenciones..." />
          <div v-else class="table-responsive attention-table-wrap">
            <table class="table attention-table align-middle">
              <thead><tr><th>Fecha y hora</th><th>Alumna</th><th>Solicitud</th><th>Atendida por</th><th>Seguimiento</th><th>Prioridad</th><th class="text-end">Ficha</th></tr></thead>
              <tbody>
                <tr v-for="item in attentionItems" :key="item.id">
                  <td><strong>{{ formatDate(item.attended_at) }}</strong><small>{{ item.attention_code }}</small></td>
                  <td><strong>{{ item.student_name_snapshot }}</strong><small><i class="bx bx-group"></i>{{ item.course_name_snapshot || "Sin curso" }}</small></td>
                  <td><div class="tag-line"><em v-for="type in item.request_types" :key="type">{{ requestLabel(type) }}</em></div><small v-if="item.brief_note" class="attention-note">{{ item.brief_note }}</small></td>
                  <td>{{ item.inspector?.full_name || item.attended_by?.name || "Inspectoría" }}</td>
                  <td><span class="follow-status" :class="{ pending: item.requires_follow_up }"><i :class="item.requires_follow_up ? 'bx bx-time-five' : 'bx bx-check'"></i>{{ item.requires_follow_up ? "Pendiente" : "No requiere" }}</span></td>
                  <td><span class="priority-status" :class="{ urgent: item.priority === 'urgente' }">{{ item.priority === "urgente" ? "Urgente" : "Normal" }}</span></td>
                  <td class="text-end">
                    <div class="inspectoria-table-actions">
                      <button type="button" class="inspectoria-action-button inspectoria-action-button--view" title="Ver ficha de atención" :aria-label="`Ver ficha de atención ${item.attention_code}`" @click="openAttentionFile(item)">
                        <i class="bx bx-show-alt"></i><span class="visually-hidden">Ver ficha</span>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div v-if="!attentionItems.length" class="empty-state py-5"><i class="bx bx-message-square-x"></i><span>Sin atenciones para los filtros actuales.</span></div>
          </div>
        </section>
      </template>

      <template v-else-if="activeTab === 'assignments'">
        <section class="panel assignments-panel">
          <div class="panel-title assignments-heading">
            <div><span>DISTRIBUCIÓN DE CURSOS</span><h4>Tabla de asignaciones</h4><small>{{ pagination.total }} asignaciones encontradas</small></div>
            <BButton v-if="catalogs.capabilities.manage_assignments" variant="primary" @click="openNewAssignment"><i class="bx bx-plus me-1"></i>Nueva asignación</BButton>
          </div>
          <div class="assignment-filters">
            <BFormSelect v-model="assignmentFilters.academic_year_id" :options="catalogs.academic_years.map(y=>({value:y.id,text:y.name}))" aria-label="Año académico" />
            <BFormSelect v-model="assignmentFilters.inspector_staff_id" :options="[{value:null,text:'Todas las inspectoras'},...inspectorOptions.slice(1)]" aria-label="Inspectora" />
            <BFormSelect v-model="assignmentFilters.active" :options="[{value:null,text:'Todos los estados'},{value:true,text:'Vigentes'},{value:false,text:'Finalizadas'}]" aria-label="Estado de asignación" />
            <BButton variant="outline-primary" @click="loadAssignments(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</BButton>
          </div>
          <LoadingState v-if="loading" compact message="Cargando asignaciones..." />
          <div v-else class="table-responsive assignment-table-wrap">
            <table class="table assignment-table align-middle">
              <thead><tr><th>Curso</th><th>Inspectora</th><th>Ubicación física</th><th>Año académico</th><th>Vigencia</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
              <tbody>
                <tr v-for="item in assignmentItems" :key="item.id">
                  <td><strong>{{ item.course_section?.display_name || "Curso sin nombre" }}</strong></td>
                  <td><strong>{{ item.inspector?.full_name || "Sin inspectora" }}</strong><small>{{ item.inspector?.rut || "" }}</small></td>
                  <td><span class="location-chip"><i class="bx bx-map"></i>{{ item.physical_location || "Por definir" }}</span></td>
                  <td>{{ item.academic_year?.name || "-" }}</td>
                  <td><strong>{{ String(item.starts_on).slice(0,10) }}</strong><small>Hasta {{ item.ends_on ? String(item.ends_on).slice(0,10) : "fecha indefinida" }}</small></td>
                  <td><span class="status-chip" :class="item.active?'active':'closed'">{{ item.active ? "Vigente" : "Finalizada" }}</span></td>
                  <td class="text-end">
                    <div class="inspectoria-table-actions">
                      <button type="button" class="inspectoria-action-button inspectoria-action-button--view" title="Ver ficha de asignación" :aria-label="`Ver asignación de ${item.course_section?.display_name || 'curso'}`" @click="openAssignmentFile(item)">
                        <i class="bx bx-show-alt"></i><span class="visually-hidden">Ver ficha</span>
                      </button>
                      <button v-if="catalogs.capabilities.manage_assignments" type="button" class="inspectoria-action-button inspectoria-action-button--edit" title="Editar asignación" :aria-label="`Editar asignación de ${item.course_section?.display_name || 'curso'}`" @click="editAssignment(item)">
                        <i class="bx bx-edit"></i><span class="visually-hidden">Editar asignación</span>
                      </button>
                      <button v-if="catalogs.capabilities.manage_assignments && item.active" type="button" class="inspectoria-action-button inspectoria-action-button--danger" title="Finalizar asignación" :aria-label="`Finalizar asignación de ${item.course_section?.display_name || 'curso'}`" @click="closeAssignment(item)">
                        <i class="bx bx-x"></i><span class="visually-hidden">Finalizar asignación</span>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <div v-if="!assignmentItems.length" class="empty-state py-5"><i class="bx bx-map-pin"></i><span>No hay asignaciones para los filtros seleccionados.</span></div>
          </div>
        </section>
      </template>

      <template v-else-if="activeTab === 'passes'">
        <div class="priority-banner"><i class="bx bx-shield-quarter"></i><div><strong>Pase de máxima prioridad</strong><span>Si coincide con un pase de Biblioteca, el pase de Inspectoría prevalece y el anterior queda anulado con trazabilidad.</span></div><b>PRIORIDAD 100</b></div>
        <section class="panel passes-panel">
          <div class="panel-title passes-heading"><div><span>AUTORIZACIONES DE TRASLADO</span><h4>Tabla de pases prioritarios</h4><small>{{ pagination.total }} pases encontrados</small></div><BButton variant="danger" @click="openNewPass"><i class="bx bx-plus me-1"></i>Nuevo pase prioritario</BButton></div>
          <div class="pass-filters"><div class="search-control"><i class="bx bx-search"></i><BFormInput v-model="passFilters.search" placeholder="Buscar alumna, RUT, código o destino" @keyup.enter="loadPasses(1)" /></div><BFormInput v-model="passFilters.date" type="date" aria-label="Fecha del pase" /><BFormSelect v-model="passFilters.status" :options="[{value:null,text:'Todos los estados'},{value:'emitido',text:'Emitidos'},{value:'utilizado',text:'Utilizados'},{value:'vencido',text:'Vencidos'},{value:'anulado',text:'Anulados'}]" aria-label="Estado del pase" /><BButton variant="outline-primary" @click="loadPasses(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</BButton></div>
          <LoadingState v-if="loading" compact message="Cargando pases..." />
          <div v-else class="table-responsive pass-table-wrap"><table class="table pass-table align-middle"><thead><tr><th>Código</th><th>Alumna</th><th>Destino</th><th>Vigencia</th><th>Estado</th><th>Emitido por</th><th class="text-end">Acciones</th></tr></thead><tbody><tr v-for="item in passItems" :key="item.id"><td><strong>{{ item.pass_code }}</strong><small class="priority-mini"><i class="bx bx-shield-quarter"></i> Prioridad 100</small></td><td><strong>{{ item.student_name_snapshot }}</strong><small>{{ item.student_rut_snapshot || "Sin RUT" }} · {{ item.course_section?.display_name || "Sin curso" }}</small></td><td><strong>{{ item.destination_detail || destinationLabel(item.destination) }}</strong><small>{{ item.reason }}</small></td><td><strong>{{ formatDate(item.valid_from) }}</strong><small>Hasta {{ formatDate(item.valid_until) }}</small></td><td><span class="pass-status" :class="item.status">{{ item.status }}</span></td><td>{{ item.inspector_name_snapshot || item.inspector?.full_name || item.issued_by?.name || "Inspectoría" }}</td><td class="text-end"><div class="inspectoria-table-actions"><button type="button" class="inspectoria-action-button inspectoria-action-button--view" title="Ver ficha del pase" :aria-label="`Ver pase ${item.pass_code}`" @click="openPassFile(item)"><i class="bx bx-show-alt"></i><span class="visually-hidden">Ver ficha</span></button><button type="button" class="inspectoria-action-button inspectoria-action-button--download" title="Descargar PDF" :aria-label="`Descargar pase ${item.pass_code} en PDF`" @click="downloadPass(item)"><i class="bx bx-download"></i><span class="visually-hidden">Descargar PDF</span></button><button v-if="item.status==='emitido'" type="button" class="inspectoria-action-button inspectoria-action-button--edit" title="Editar pase" :aria-label="`Editar pase ${item.pass_code}`" @click="editPass(item)"><i class="bx bx-edit"></i><span class="visually-hidden">Editar pase</span></button><button v-if="item.status==='emitido'" type="button" class="inspectoria-action-button inspectoria-action-button--success" title="Marcar como usado" :aria-label="`Marcar pase ${item.pass_code} como usado`" @click="transitionPass(item,'utilizado')"><i class="bx bx-check"></i><span class="visually-hidden">Marcar como usado</span></button><button v-if="item.status==='emitido'" type="button" class="inspectoria-action-button inspectoria-action-button--danger" title="Anular pase" :aria-label="`Anular pase ${item.pass_code}`" @click="transitionPass(item,'anulado')"><i class="bx bx-x"></i><span class="visually-hidden">Anular pase</span></button></div></td></tr></tbody></table><div v-if="!passItems.length" class="empty-state py-5"><i class="bx bx-id-card"></i><span>No hay pases para los filtros seleccionados.</span></div></div>
        </section>
      </template>

      <template v-else-if="activeTab === 'students'">
        <section class="panel students-panel">
          <div class="panel-title students-heading"><div><span>DIRECTORIO ACTIVO</span><h4>Tabla de alumnas</h4><small>{{ pagination.total }} alumnas encontradas</small></div></div>
          <div class="student-table-filters"><div class="search-control"><i class="bx bx-search"></i><BFormInput v-model="studentFilters.search" placeholder="Buscar por nombre o RUT" @keyup.enter="loadStudents(1)" /></div><BFormSelect v-model="studentFilters.course_section_id" :options="courseOptions" aria-label="Curso" /><BButton variant="outline-primary" @click="loadStudents(1)"><i class="bx bx-search me-1"></i>Buscar</BButton></div>
          <LoadingState v-if="loading" compact message="Cargando alumnas..." />
          <div v-else class="table-responsive student-table-wrap"><table class="table student-table align-middle"><thead><tr><th>Alumna</th><th>RUT</th><th>Curso vigente</th><th>Contacto alumna</th><th>Apoderado/a</th><th>Estado</th><th class="text-end">Ficha</th></tr></thead><tbody><tr v-for="student in studentItems" :key="student.id"><td><div class="student-table-person"><div class="student-avatar">{{ initials(student.name) }}</div><strong>{{ student.name }}</strong></div></td><td>{{ student.rut || "Sin RUT" }}</td><td><span class="location-chip"><i class="bx bx-group"></i>{{ student.course || "Sin curso vigente" }}</span></td><td>{{ student.phone || "No informado" }}</td><td><strong>{{ student.guardian_name || "No informado" }}</strong><small>{{ student.guardian_phone || "Sin teléfono" }}</small></td><td><span class="status-chip" :class="student.status === 'active' || student.status === 'activo' ? 'active' : 'closed'">{{ student.status || "Sin estado" }}</span></td><td class="text-end"><div class="inspectoria-table-actions"><button type="button" class="inspectoria-action-button inspectoria-action-button--view" title="Ver ficha de la alumna" :aria-label="`Ver ficha de ${student.name}`" @click="openStudentFile(student)"><i class="bx bx-show-alt"></i><span class="visually-hidden">Ver ficha</span></button></div></td></tr></tbody></table><div v-if="!studentItems.length" class="empty-state py-5"><i class="bx bx-user-x"></i><span>No se encontraron alumnas.</span></div></div>
        </section>
      </template>

      <template v-else-if="activeTab === 'restrictions'">
        <section class="restriction-safety-banner"><i class="bx bxs-error-alt"></i><div><span>ALERTA COMPARTIDA CON PORTERÍA</span><strong>Toda restricción vigente aparecerá al seleccionar a la alumna en el formulario de retiro.</strong><small>El acceso y los registros están limitados a los cursos asignados a cada inspectora.</small></div></section>
        <section class="panel restrictions-panel">
          <div class="panel-title restrictions-heading"><div><span>CONTROL DE RETIROS</span><h4>Tabla de restricciones</h4><small>{{ pagination.total }} restricciones encontradas</small></div><BButton v-if="catalogs.capabilities.manage_pickup_restrictions" variant="danger" @click="openNewRestriction"><i class="bx bx-plus me-1"></i>Nueva restricción</BButton></div>
          <div class="restriction-filters"><div class="search-control"><i class="bx bx-search"></i><BFormInput v-model="restrictionFilters.search" placeholder="Buscar alumna, persona, RUT o motivo" @keyup.enter="loadRestrictions(1)" /></div><BFormSelect v-model="restrictionFilters.course_section_id" :options="courseOptions" aria-label="Curso" /><BFormSelect v-model="restrictionFilters.restriction_type" :options="restrictionTypeOptions" aria-label="Tipo de restricción" /><BFormSelect v-model="restrictionFilters.active" :options="[{value:null,text:'Todos los estados'},{value:true,text:'Vigentes'},{value:false,text:'Finalizadas'}]" aria-label="Estado" /><BButton variant="outline-primary" @click="loadRestrictions(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</BButton></div>
          <LoadingState v-if="loading" compact message="Cargando restricciones..." />
          <div v-else class="table-responsive restriction-table-wrap"><table class="table restriction-table align-middle"><thead><tr><th>Alumna</th><th>Curso</th><th>Persona restringida</th><th>Tipo y motivo</th><th>Vigencia</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody><tr v-for="item in restrictionItems" :key="item.id"><td><strong>{{ item.student?.registered_name_resolved || `${item.student?.first_name || ''} ${item.student?.last_name || ''}` }}</strong><small>{{ item.student?.rut || 'Sin RUT' }}</small></td><td><span class="location-chip"><i class="bx bx-group"></i>{{ item.course_section?.display_name || 'Sin curso' }}</span></td><td><strong>{{ item.restricted_person_name }}</strong><small>{{ item.restricted_person_relationship || 'Sin relación' }} · {{ item.restricted_person_rut || 'Sin RUT' }}</small></td><td><span class="restriction-type-chip">{{ restrictionTypeLabel(item.restriction_type) }}</span><small>{{ item.reason }}</small></td><td><strong>Desde {{ String(item.starts_on).slice(0,10) }}</strong><small>{{ item.ends_on ? `Hasta ${String(item.ends_on).slice(0,10)}` : 'Sin fecha de término' }}</small></td><td><span class="status-chip" :class="item.active?'closed':'active'">{{ item.active ? 'Vigente' : 'Finalizada' }}</span></td><td class="text-end"><div class="inspectoria-table-actions"><button type="button" class="inspectoria-action-button inspectoria-action-button--view" title="Ver restricción" @click="openRestrictionFile(item)"><i class="bx bx-show-alt"></i><span class="visually-hidden">Ver</span></button><button v-if="catalogs.capabilities.manage_pickup_restrictions" type="button" class="inspectoria-action-button inspectoria-action-button--edit" title="Editar restricción" @click="editRestriction(item)"><i class="bx bx-edit"></i><span class="visually-hidden">Editar</span></button><button v-if="catalogs.capabilities.manage_pickup_restrictions && item.active" type="button" class="inspectoria-action-button inspectoria-action-button--danger" title="Finalizar restricción" @click="closeRestriction(item)"><i class="bx bx-x"></i><span class="visually-hidden">Finalizar</span></button></div></td></tr></tbody></table><div v-if="!restrictionItems.length" class="empty-state py-5"><i class="bx bx-shield-x"></i><span>No hay restricciones para los filtros seleccionados.</span></div></div>
        </section>
      </template>

      <template v-else-if="activeTab === 'withdrawals'">
        <section class="withdrawal-scope-card">
          <div class="withdrawal-scope-icon"><i class="bx bx-shield-quarter"></i></div>
          <div>
            <span>CURSOS BAJO TU RESPONSABILIDAD</span>
            <strong>La información está limitada automáticamente a tus asignaciones vigentes.</strong>
            <div class="withdrawal-course-list">
              <em v-for="course in catalogs.courses" :key="course.id">{{ course.display_name }}</em>
              <em v-if="!catalogs.courses?.length" class="is-empty">No tienes cursos asignados actualmente</em>
            </div>
          </div>
          <div class="withdrawal-origin"><i class="bx bx-building-house"></i><span>Registro originado en</span><b>Portería</b></div>
        </section>

        <section class="panel withdrawals-panel">
          <div class="panel-title withdrawals-heading">
            <div><span>CONTROL DE SALIDAS</span><h4>{{ withdrawalMode === 'today' ? 'Retiros del día' : 'Retiros históricos' }}</h4><small>{{ pagination.total }} retiros encontrados</small></div>
            <div class="withdrawal-mode-switch" role="tablist" aria-label="Periodo de retiros">
              <button type="button" :class="{ active: withdrawalMode === 'today' }" @click="setWithdrawalMode('today')"><i class="bx bx-calendar-check"></i>Retiros de hoy</button>
              <button type="button" :class="{ active: withdrawalMode === 'history' }" @click="setWithdrawalMode('history')"><i class="bx bx-history"></i>Histórico</button>
            </div>
          </div>

          <div class="withdrawal-filters" :class="{ 'is-history': withdrawalMode === 'history' }">
            <div class="search-control"><i class="bx bx-search"></i><BFormInput v-model="withdrawalFilters.search" placeholder="Buscar alumna, RUT, persona o curso" @keyup.enter="loadWithdrawals(1)" /></div>
            <BFormSelect v-model="withdrawalFilters.course_section_id" :options="courseOptions" aria-label="Curso" />
            <BFormSelect v-model="withdrawalFilters.status" :options="withdrawalStatusOptions" aria-label="Estado" />
            <template v-if="withdrawalMode === 'history'">
              <BFormInput v-model="withdrawalFilters.date_from" type="date" aria-label="Fecha desde" />
              <BFormInput v-model="withdrawalFilters.date_to" type="date" aria-label="Fecha hasta" />
            </template>
            <BButton variant="outline-primary" @click="loadWithdrawals(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</BButton>
          </div>

          <LoadingState v-if="loading" compact message="Cargando retiros de tus cursos..." />
          <div v-else class="table-responsive withdrawal-table-wrap">
            <table class="table withdrawal-table align-middle">
              <thead><tr><th>Folio / fecha</th><th>Alumna</th><th>Curso</th><th>Persona que retira</th><th>Motivo</th><th>Estado</th><th>Registrado en Portería</th><th class="text-end">Acciones</th></tr></thead>
              <tbody>
                <tr v-for="item in withdrawalItems" :key="item.id">
                  <td><strong>{{ item.withdrawal_code }}</strong><small>{{ formatDate(item.withdrawn_at) }}</small></td>
                  <td><strong>{{ item.student_full_name_snapshot }}</strong><small>{{ item.student_rut_snapshot || "Sin RUT" }}</small></td>
                  <td><span class="location-chip"><i class="bx bx-group"></i>{{ item.course_name_snapshot || item.course_section?.display_name || "Sin curso" }}</span></td>
                  <td><strong>{{ item.person_name }}</strong><small>{{ withdrawalRelationshipLabel(item.person_relationship) }} · {{ item.person_rut || "Sin RUT" }}</small></td>
                  <td>{{ withdrawalReasonLabel(item.reason) }}</td>
                  <td><span class="withdrawal-status" :class="item.status">{{ withdrawalStatusLabel(item.status) }}</span></td>
                  <td><strong>{{ userName(item.registered_by) }}</strong><small><i class="bx bx-building-house"></i> Portería</small></td>
                  <td class="text-end"><div class="inspectoria-table-actions"><button type="button" class="inspectoria-action-button inspectoria-action-button--view" title="Ver ficha del retiro" :aria-label="`Ver retiro ${item.withdrawal_code}`" @click="openWithdrawalFile(item)"><i class="bx bx-show-alt"></i><span class="visually-hidden">Ver ficha</span></button><button type="button" class="inspectoria-action-button inspectoria-action-button--download" title="Descargar acta PDF" :aria-label="`Descargar acta ${item.withdrawal_code}`" @click="downloadWithdrawal(item)"><i class="bx bxs-file-pdf"></i><span class="visually-hidden">Descargar PDF</span></button></div></td>
                </tr>
              </tbody>
            </table>
            <div v-if="!withdrawalItems.length" class="empty-state py-5"><i class="bx bx-log-out-circle"></i><span>{{ withdrawalMode === 'today' ? 'No hay retiros registrados hoy para tus cursos.' : 'No hay retiros históricos para los filtros seleccionados.' }}</span></div>
          </div>
        </section>
      </template>

      <template v-else>
        <section class="panel daily-log-panel">
          <div class="panel-title daily-log-heading"><div><span>REGISTRO CRONOLÓGICO</span><h4>Tabla de bitácora diaria</h4><small>{{ pagination.total }} registros encontrados</small></div><BButton variant="primary" @click="openNewDailyLog"><i class="bx bx-plus me-1"></i>Nuevo registro</BButton></div>
          <div class="daily-log-filters"><div class="search-control"><i class="bx bx-search"></i><BFormInput v-model="logFilters.search" placeholder="Buscar título, detalle o alumna" @keyup.enter="loadDailyLogs(1)" /></div><BFormInput v-model="logFilters.date" type="date" aria-label="Fecha de bitácora" /><BFormSelect v-model="logFilters.category" :options="[{value:null,text:'Todas las categorías'},...categoryOptions]" aria-label="Categoría" /><BFormSelect v-model="logFilters.priority" :options="[{value:null,text:'Todas las prioridades'},{value:'baja',text:'Baja'},{value:'media',text:'Media'},{value:'alta',text:'Alta'},{value:'urgente',text:'Urgente'}]" aria-label="Prioridad" /><BButton variant="outline-primary" @click="loadDailyLogs(1)"><i class="bx bx-filter-alt me-1"></i>Filtrar</BButton></div>
          <LoadingState v-if="loading" compact message="Cargando bitácora..." />
          <div v-else class="table-responsive daily-log-table-wrap"><table class="table daily-log-table align-middle"><thead><tr><th>Fecha y hora</th><th>Categoría</th><th>Hecho registrado</th><th>Alumna / curso</th><th>Registrado por</th><th>Prioridad</th><th>Seguimiento</th><th class="text-end">Acciones</th></tr></thead><tbody><tr v-for="item in dailyLogItems" :key="item.id"><td><strong>{{ formatDate(item.happened_at) }}</strong></td><td><span class="category-chip">{{ categoryLabel(item.category) }}</span></td><td><strong>{{ item.title }}</strong><small>{{ item.detail }}</small></td><td><strong v-if="item.student">{{ item.student.registered_name_resolved || item.student.registered_name || `${item.student.first_name} ${item.student.last_name}` }}</strong><small>{{ item.course_section?.display_name || (item.student ? "Sin curso relacionado" : "Registro general") }}</small></td><td>{{ item.inspector?.full_name || item.registered_by?.name || "Inspectoría" }}</td><td><span class="log-priority" :class="item.priority">{{ item.priority }}</span></td><td><span class="follow-status" :class="{ pending:item.requires_follow_up }"><i :class="item.requires_follow_up ? 'bx bx-time-five' : 'bx bx-check'"></i>{{ item.requires_follow_up ? "Pendiente" : "Sin seguimiento" }}</span></td><td class="text-end"><div class="inspectoria-table-actions"><button type="button" class="inspectoria-action-button inspectoria-action-button--view" title="Ver registro de bitácora" :aria-label="`Ver registro ${item.title}`" @click="openDailyLogFile(item)"><i class="bx bx-show-alt"></i><span class="visually-hidden">Ver registro</span></button><button type="button" class="inspectoria-action-button inspectoria-action-button--edit" title="Editar registro" :aria-label="`Editar registro ${item.title}`" @click="editDailyLog(item)"><i class="bx bx-edit"></i><span class="visually-hidden">Editar registro</span></button></div></td></tr></tbody></table><div v-if="!dailyLogItems.length" class="empty-state py-5"><i class="bx bx-notepad"></i><span>No hay registros para los filtros seleccionados.</span></div></div>
        </section>
      </template>

      <div v-if="pagination.total > pagination.per_page" class="d-flex justify-content-center mt-4"><BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="loadActive" /></div>

      <BModal v-model="showAttentionModal" size="xl" :title="selectedAttention ? `Ficha de atención · ${selectedAttention.attention_code}` : 'Nueva atención de Inspectoría'" hide-footer @hidden="resetAttentionModal">
        <div v-if="selectedAttention" class="attention-file">
          <header>
            <div class="student-avatar large">{{ initials(selectedAttention.student_name_snapshot) }}</div>
            <div><small>{{ selectedAttention.attention_code }}</small><h3>{{ selectedAttention.student_name_snapshot }}</h3><p>{{ selectedAttention.course_name_snapshot || "Sin curso vigente" }}</p></div>
            <span class="priority-status" :class="{ urgent: selectedAttention.priority === 'urgente' }">{{ selectedAttention.priority === "urgente" ? "Urgente" : "Normal" }}</span>
          </header>
          <div class="attention-file-grid">
            <section><span>Fecha y hora</span><strong>{{ formatDate(selectedAttention.attended_at) }}</strong></section>
            <section><span>Atendida por</span><strong>{{ selectedAttention.inspector?.full_name || selectedAttention.attended_by?.name || "Inspectoría" }}</strong></section>
            <section><span>Estado</span><strong>{{ selectedAttention.status || "Registrada" }}</strong></section>
          </div>
          <div class="attention-file-section"><span>Solicitudes registradas</span><div class="tag-line"><em v-for="type in selectedAttention.request_types" :key="type">{{ requestLabel(type) }}</em></div></div>
          <div class="attention-file-section"><span>Acciones realizadas</span><div v-if="selectedAttention.actions_taken?.length" class="tag-line actions"><em v-for="action in selectedAttention.actions_taken" :key="action">{{ actionLabel(action) }}</em></div><p v-else>Sin acciones informadas.</p></div>
          <div v-if="selectedAttention.psychosocial_referral_name_snapshot" class="attention-referral-file"><div class="referral-file-icon"><i class="bx bx-transfer-alt"></i></div><div><span>Derivación psicosocial</span><strong>{{ selectedAttention.psychosocial_referral_name_snapshot }}</strong><small>{{ selectedAttention.psychosocial_referral_role_snapshot }} · {{ formatDate(selectedAttention.psychosocial_referred_at) }}</small></div><span class="follow-status pending"><i class="bx bx-time-five"></i>Seguimiento</span></div>
          <div class="attention-file-section"><span>Información breve</span><p>{{ selectedAttention.brief_note || "Sin información adicional." }}</p></div>
          <div class="attention-file-flags"><span :class="{ active: selectedAttention.guardian_notified }"><i :class="selectedAttention.guardian_notified ? 'bx bx-check-circle' : 'bx bx-circle'"></i>Apoderado informado</span><span :class="{ active: selectedAttention.requires_follow_up, warning: selectedAttention.requires_follow_up }"><i :class="selectedAttention.requires_follow_up ? 'bx bx-time-five' : 'bx bx-check-circle'"></i>{{ selectedAttention.requires_follow_up ? "Requiere seguimiento" : "Sin seguimiento pendiente" }}</span></div>
        </div>
        <form v-else class="attention-modal-form" @submit.prevent="saveAttention">
          <div class="modal-form-intro"><div><span>FORMULARIO BREVE</span><h5>Completa la ficha en pocos clics</h5></div><span class="speed-badge"><i class="bx bx-bolt-circle"></i> Llenado rápido</span></div>
          <label class="form-label">Buscar alumna *</label>
          <div class="search-control"><i class="bx bx-search"></i><input v-model="attentionSearch" list="inspectoria-attention-students" class="form-control" placeholder="Nombre, RUT o curso" @change="syncAttentionStudent" /></div>
          <datalist id="inspectoria-attention-students"><option v-for="student in studentOptions" :key="student.id" :value="student.text" /></datalist>
          <label class="form-label mt-4">¿Qué solicita? *</label>
          <div class="check-grid"><button v-for="item in catalogs.request_types" :key="item.value" type="button" class="check-card" :class="{ selected: attention.request_types.includes(item.value) }" @click="toggleArray(attention.request_types,item.value)"><i :class="attention.request_types.includes(item.value)?'bx bx-check-circle':'bx bx-circle'"></i>{{ item.label }}</button></div>
          <label class="form-label mt-4">Acciones realizadas</label>
          <div class="check-grid check-grid--actions"><button v-for="item in catalogs.attention_actions" :key="item.value" type="button" class="check-card" :class="{ selected: attention.actions_taken.includes(item.value), psychosocial: item.value === 'derivacion_psicosocial' }" @click="toggleAttentionAction(item.value)"><i :class="attention.actions_taken.includes(item.value)?'bx bx-check-circle':'bx bx-circle'"></i>{{ item.label }}</button></div>
          <section v-if="hasPsychosocialReferral" class="psychosocial-referral-box">
            <div class="psychosocial-referral-heading"><div class="referral-heading-icon"><i class="bx bx-brain"></i></div><div><span>DERIVACIÓN PSICOSOCIAL</span><h6>Selecciona quién recibirá la derivación</h6><p>Solo se muestran profesionales activas de Psicología y Trabajo Social.</p></div></div>
            <label class="form-label" for="psychosocial-professional">Profesional destinataria *</label>
            <BFormSelect id="psychosocial-professional" v-model="attention.psychosocial_referral_user_id" :options="psychosocialProfessionalOptions" required />
            <div v-if="selectedPsychosocialProfessional" class="selected-referral-professional"><div class="professional-avatar">{{ initials(selectedPsychosocialProfessional.name) }}</div><div><strong>{{ selectedPsychosocialProfessional.name }}</strong><span>{{ selectedPsychosocialProfessional.profession_label }}</span></div><i class="bx bx-check-circle"></i></div>
            <p v-if="!catalogs.psychosocial_professionals?.length" class="referral-empty"><i class="bx bx-error-circle"></i>No hay psicólogas o trabajadoras sociales activas configuradas.</p>
          </section>
          <div class="row g-3 mt-1"><div class="col-md-8"><label class="form-label">Información breve</label><BFormTextarea v-model="attention.brief_note" rows="3" maxlength="1000" placeholder="Solo agrega el contexto indispensable..." /></div><div class="col-md-4"><label class="form-label">Fecha y hora</label><BFormInput v-model="attention.attended_at" type="datetime-local" /><BFormCheckbox v-model="attention.guardian_notified" class="mt-3">Apoderado informado</BFormCheckbox><BFormCheckbox v-model="attention.requires_follow_up">Requiere seguimiento</BFormCheckbox></div></div>
          <div class="quick-actions"><BFormCheckbox v-model="attention.priority" value="urgente" unchecked-value="normal" switch>Atención urgente</BFormCheckbox><div class="d-flex gap-2"><BButton type="button" variant="light" @click="showAttentionModal=false">Cancelar</BButton><BButton type="submit" variant="primary" :disabled="saving"><i class="bx bx-check me-1"></i>{{ saving ? "Guardando..." : "Registrar atención" }}</BButton></div></div>
        </form>
      </BModal>

      <BModal v-model="showAssignmentModal" size="lg" :title="selectedAssignment ? 'Ficha de asignación' : (assignment.id ? 'Editar asignación de curso' : 'Nueva asignación de curso')" hide-footer @hidden="resetAssignmentModal">
        <div v-if="selectedAssignment" class="assignment-file">
          <header>
            <div class="assignment-file-icon"><i class="bx bx-map-pin"></i></div>
            <div><small>{{ selectedAssignment.academic_year?.name || "Año académico" }}</small><h3>{{ selectedAssignment.course_section?.display_name || "Curso sin nombre" }}</h3><p>Asignación de cobertura de Inspectoría</p></div>
            <span class="status-chip" :class="selectedAssignment.active ? 'active' : 'closed'">{{ selectedAssignment.active ? "Vigente" : "Finalizada" }}</span>
          </header>
          <div class="assignment-file-grid">
            <section><span>Inspectora responsable</span><strong>{{ selectedAssignment.inspector?.full_name || "Sin inspectora" }}</strong><small>{{ selectedAssignment.inspector?.rut || "RUT no informado" }}</small></section>
            <section><span>Ubicación física</span><strong><i class="bx bx-map me-1"></i>{{ selectedAssignment.physical_location || "Por definir" }}</strong></section>
            <section><span>Inicio de asignación</span><strong>{{ String(selectedAssignment.starts_on).slice(0,10) }}</strong></section>
            <section><span>Término de asignación</span><strong>{{ selectedAssignment.ends_on ? String(selectedAssignment.ends_on).slice(0,10) : "Indefinido" }}</strong></section>
          </div>
          <div class="assignment-file-note"><span>Observación</span><p>{{ selectedAssignment.notes || "Sin observaciones adicionales." }}</p></div>
          <div v-if="catalogs.capabilities.manage_assignments" class="d-flex justify-content-end mt-4"><BButton variant="outline-primary" @click="editAssignment(selectedAssignment)"><i class="bx bx-edit me-1"></i>Editar asignación</BButton></div>
        </div>
        <form v-else class="assignment-modal-form" @submit.prevent="saveAssignment">
          <div class="modal-form-intro"><div><span>COBERTURA DEL AÑO</span><h5>{{ assignment.id ? "Actualiza los datos de la asignación" : "Asigna un curso a una inspectora" }}</h5></div><span class="speed-badge"><i class="bx bx-map-pin"></i> Ubicación flexible</span></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Año académico *</label><BFormSelect v-model="assignment.academic_year_id" :options="catalogs.academic_years.map(y=>({value:y.id,text:y.name}))" @change="assignment.course_section_id=null" /></div>
            <div class="col-md-6"><label class="form-label">Curso *</label><BFormSelect v-model="assignment.course_section_id" :options="[{value:null,text:'Selecciona curso'},...activeCourses.map(c=>({value:c.id,text:c.display_name}))]" /></div>
            <div class="col-md-7"><label class="form-label">Inspectora *</label><BFormSelect v-model="assignment.inspector_staff_id" :options="inspectorOptions" /></div>
            <div class="col-md-5"><label class="form-label">Ubicación física</label><BFormInput v-model="assignment.physical_location" placeholder="Ej. Pabellón B" /></div>
            <div class="col-md-6"><label class="form-label">Desde *</label><BFormInput v-model="assignment.starts_on" type="date" /></div>
            <div class="col-md-6"><label class="form-label">Hasta</label><BFormInput v-model="assignment.ends_on" type="date" /></div>
            <div class="col-12"><label class="form-label">Observación</label><BFormTextarea v-model="assignment.notes" rows="3" placeholder="Información útil sobre ubicación o cobertura" /></div>
          </div>
          <div class="assignment-modal-actions"><BFormCheckbox v-model="assignment.active" switch>Asignación vigente</BFormCheckbox><div class="d-flex gap-2"><BButton type="button" variant="light" @click="showAssignmentModal=false">Cancelar</BButton><BButton type="submit" variant="primary" :disabled="saving"><i class="bx bx-check me-1"></i>{{ saving ? "Guardando..." : (assignment.id ? "Actualizar asignación" : "Guardar asignación") }}</BButton></div></div>
        </form>
      </BModal>

      <BModal v-model="showPassModal" size="xl" :title="selectedPass ? `Ficha de pase · ${selectedPass.pass_code}` : (pass.id ? 'Editar pase prioritario' : 'Nuevo pase prioritario de Inspectoría')" hide-footer @hidden="resetPassModal">
        <div v-if="selectedPass" class="pass-file">
          <div class="pass-file-priority"><i class="bx bx-shield-quarter"></i><div><span>PASE DE MÁXIMA PRIORIDAD</span><strong>{{ selectedPass.pass_code }}</strong></div><b>100</b></div>
          <header><div class="student-avatar large">{{ initials(selectedPass.student_name_snapshot) }}</div><div><small>{{ selectedPass.student_rut_snapshot || "Sin RUT" }}</small><h3>{{ selectedPass.student_name_snapshot }}</h3><p>{{ selectedPass.course_section?.display_name || "Sin curso vigente" }}</p></div><span class="pass-status" :class="selectedPass.status">{{ selectedPass.status }}</span></header>
          <div class="pass-file-grid"><section><span>Destino</span><strong>{{ selectedPass.destination_detail || destinationLabel(selectedPass.destination) }}</strong></section><section><span>Válido desde</span><strong>{{ formatDate(selectedPass.valid_from) }}</strong></section><section><span>Válido hasta</span><strong>{{ formatDate(selectedPass.valid_until) }}</strong></section><section><span>Emitido por</span><strong>{{ selectedPass.inspector_name_snapshot || selectedPass.inspector?.full_name || selectedPass.issued_by?.name || "Inspectoría" }}</strong></section></div>
          <div class="pass-file-section"><span>Motivo del pase</span><p>{{ selectedPass.reason }}</p></div><div class="pass-file-section"><span>Observaciones</span><p>{{ selectedPass.notes || "Sin observaciones adicionales." }}</p></div>
          <div class="pass-file-signature"><div><span>Nombre de firma</span><strong>{{ selectedPass.signature_name || "No informado" }}</strong></div><div><span>RUT de firma</span><strong>{{ selectedPass.signature_rut || "No informado" }}</strong></div></div>
          <div class="pass-file-actions"><BButton variant="outline-primary" @click="downloadPass(selectedPass)"><i class="bx bx-download me-1"></i>Descargar PDF</BButton><BButton v-if="selectedPass.status==='emitido'" variant="outline-secondary" @click="editPass(selectedPass)"><i class="bx bx-edit me-1"></i>Editar pase</BButton></div>
        </div>
        <form v-else class="pass-modal-form" @submit.prevent="savePass">
          <div class="pass-modal-priority"><i class="bx bx-shield-quarter"></i><div><strong>Prioridad 100</strong><span>Este pase prevalece sobre cualquier pase de Biblioteca superpuesto.</span></div></div>
          <label class="form-label">Buscar alumna *</label><div class="search-control"><i class="bx bx-search"></i><input v-model="passSearch" list="inspectoria-pass-students" class="form-control" :disabled="Boolean(pass.id)" placeholder="Nombre, RUT o curso" @change="syncPassStudent" /></div><datalist id="inspectoria-pass-students"><option v-for="student in studentOptions" :key="student.id" :value="student.text" /></datalist>
          <div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Destino *</label><BFormSelect v-model="pass.destination" :options="destinationOptions" /></div><div class="col-md-6"><label class="form-label">Detalle del lugar</label><BFormInput v-model="pass.destination_detail" placeholder="Sala, oficina o ubicación" /></div><div class="col-md-6"><label class="form-label">Válido desde *</label><BFormInput v-model="pass.valid_from" type="datetime-local" /></div><div class="col-md-6"><label class="form-label">Válido hasta *</label><BFormInput v-model="pass.valid_until" type="datetime-local" /></div><div class="col-12"><label class="form-label">Motivo *</label><BFormTextarea v-model="pass.reason" rows="3" /></div><div class="col-md-6"><label class="form-label">Nombre firma</label><BFormInput v-model="pass.signature_name" /></div><div class="col-md-6"><label class="form-label">RUT firma</label><BFormInput v-model="pass.signature_rut" /></div><div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="pass.notes" rows="2" /></div></div>
          <div class="modal-submit-actions"><BButton type="button" variant="light" @click="showPassModal=false">Cancelar</BButton><BButton type="submit" variant="danger" :disabled="saving"><i class="bx bx-id-card me-1"></i>{{ saving ? "Guardando..." : (pass.id ? "Actualizar pase" : "Emitir pase prioritario") }}</BButton></div>
        </form>
      </BModal>

      <BModal v-model="showDailyLogModal" size="xl" :title="selectedDailyLog ? 'Ficha de bitácora' : (dailyLog.id ? 'Editar registro de bitácora' : 'Nuevo registro de bitácora')" hide-footer @hidden="resetDailyLogModal">
        <div v-if="selectedDailyLog" class="daily-log-file">
          <header><div class="daily-log-file-icon"><i class="bx bx-notepad"></i></div><div><small>{{ categoryLabel(selectedDailyLog.category) }}</small><h3>{{ selectedDailyLog.title }}</h3><p>{{ formatDate(selectedDailyLog.happened_at) }}</p></div><span class="log-priority" :class="selectedDailyLog.priority">{{ selectedDailyLog.priority }}</span></header>
          <div class="daily-log-file-grid"><section><span>Alumna relacionada</span><strong v-if="selectedDailyLog.student">{{ selectedDailyLog.student.registered_name_resolved || selectedDailyLog.student.registered_name || `${selectedDailyLog.student.first_name} ${selectedDailyLog.student.last_name}` }}</strong><strong v-else>Registro general</strong></section><section><span>Curso relacionado</span><strong>{{ selectedDailyLog.course_section?.display_name || "Sin curso" }}</strong></section><section><span>Registrado por</span><strong>{{ selectedDailyLog.inspector?.full_name || selectedDailyLog.registered_by?.name || "Inspectoría" }}</strong></section><section><span>Estado</span><strong>{{ selectedDailyLog.status || "Registrado" }}</strong></section></div>
          <div class="daily-log-file-section"><span>Detalle relevante</span><p>{{ selectedDailyLog.detail }}</p></div><div class="daily-log-file-section"><span>Seguimiento</span><p>{{ selectedDailyLog.requires_follow_up ? (selectedDailyLog.follow_up_note || "Seguimiento pendiente") : "No requiere seguimiento." }}</p></div>
          <div class="d-flex justify-content-end mt-4"><BButton variant="outline-primary" @click="editDailyLog(selectedDailyLog)"><i class="bx bx-edit me-1"></i>Editar registro</BButton></div>
        </div>
        <form v-else class="daily-log-modal-form" @submit.prevent="saveDailyLog">
          <div class="modal-form-intro"><div><span>REGISTRO CRONOLÓGICO</span><h5>{{ dailyLog.id ? "Actualiza la información del hecho" : "Agrega un hecho relevante de la jornada" }}</h5></div><span class="speed-badge"><i class="bx bx-notepad"></i> Bitácora diaria</span></div>
          <div class="row g-3"><div class="col-md-6"><label class="form-label">Fecha y hora *</label><BFormInput v-model="dailyLog.happened_at" type="datetime-local" /></div><div class="col-md-6"><label class="form-label">Categoría *</label><BFormSelect v-model="dailyLog.category" :options="categoryOptions" /></div><div class="col-md-8"><label class="form-label">Título *</label><BFormInput v-model="dailyLog.title" placeholder="Resumen breve del hecho" /></div><div class="col-md-4"><label class="form-label">Prioridad</label><BFormSelect v-model="dailyLog.priority" :options="[{value:'baja',text:'Baja'},{value:'media',text:'Media'},{value:'alta',text:'Alta'},{value:'urgente',text:'Urgente'}]" /></div><div class="col-12"><label class="form-label">Detalle relevante *</label><BFormTextarea v-model="dailyLog.detail" rows="4" /></div><div class="col-md-7"><label class="form-label">Alumna relacionada (opcional)</label><div class="search-control"><i class="bx bx-search"></i><input v-model="dailyStudentSearch" list="inspectoria-log-students" class="form-control" placeholder="Nombre, RUT o curso" @change="syncDailyStudent" /></div><datalist id="inspectoria-log-students"><option v-for="student in studentOptions" :key="student.id" :value="student.text" /></datalist></div><div class="col-md-5"><label class="form-label">Curso relacionado</label><BFormSelect v-model="dailyLog.course_section_id" :options="courseOptions" /></div><div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="dailyLog.status" :options="[{value:'registrado',text:'Registrado'},{value:'en_seguimiento',text:'En seguimiento'},{value:'cerrado',text:'Cerrado'}]" /></div><div class="col-md-8 d-flex align-items-end"><BFormCheckbox v-model="dailyLog.requires_follow_up" switch>Requiere seguimiento</BFormCheckbox></div><div v-if="dailyLog.requires_follow_up" class="col-12"><label class="form-label">Seguimiento pendiente</label><BFormInput v-model="dailyLog.follow_up_note" placeholder="Indica el seguimiento pendiente" /></div></div>
          <div class="modal-submit-actions"><BButton type="button" variant="light" @click="showDailyLogModal=false">Cancelar</BButton><BButton type="submit" variant="primary" :disabled="saving"><i class="bx bx-check me-1"></i>{{ saving ? "Guardando..." : (dailyLog.id ? "Actualizar registro" : "Agregar a bitácora") }}</BButton></div>
        </form>
      </BModal>

      <BModal v-model="showRestrictionModal" size="xl" :title="selectedRestriction ? `Ficha de restricción · ${selectedRestriction.restriction_code}` : (restriction.id ? 'Editar restricción de retiro' : 'Nueva restricción de retiro')" hide-footer @hidden="resetRestrictionModal">
        <div v-if="selectedRestriction" class="restriction-file">
          <header><div class="restriction-file-icon"><i class="bx bxs-error-alt"></i></div><div><small>{{ selectedRestriction.restriction_code }}</small><h3>{{ selectedRestriction.student?.registered_name_resolved || `${selectedRestriction.student?.first_name || ''} ${selectedRestriction.student?.last_name || ''}` }}</h3><p>{{ selectedRestriction.course_section?.display_name || 'Sin curso' }}</p></div><span class="status-chip" :class="selectedRestriction.active?'closed':'active'">{{ selectedRestriction.active ? 'Vigente' : 'Finalizada' }}</span></header>
          <div class="restriction-file-grid"><section><span>Persona restringida</span><strong>{{ selectedRestriction.restricted_person_name }}</strong><small>{{ selectedRestriction.restricted_person_relationship || 'Sin relación' }} · {{ selectedRestriction.restricted_person_rut || 'Sin RUT' }}</small></section><section><span>Tipo</span><strong>{{ restrictionTypeLabel(selectedRestriction.restriction_type) }}</strong></section><section><span>Vigencia</span><strong>Desde {{ String(selectedRestriction.starts_on).slice(0,10) }}</strong><small>{{ selectedRestriction.ends_on ? `Hasta ${String(selectedRestriction.ends_on).slice(0,10)}` : 'Sin fecha de término' }}</small></section><section><span>Registrada por</span><strong>{{ selectedRestriction.created_by?.name || 'Inspectoría' }}</strong></section></div>
          <div class="restriction-file-section"><span>Motivo / antecedente</span><p>{{ selectedRestriction.reason }}</p></div><div class="restriction-file-section"><span>Referencia legal o respaldo</span><p>{{ selectedRestriction.legal_reference || 'Sin referencia informada.' }}</p></div>
          <div v-if="catalogs.capabilities.manage_pickup_restrictions" class="modal-submit-actions"><BButton variant="outline-warning" @click="editRestriction(selectedRestriction)"><i class="bx bx-edit me-1"></i>Editar restricción</BButton></div>
        </div>
        <form v-else class="restriction-modal-form" @submit.prevent="saveRestriction">
          <div class="restriction-form-alert"><i class="bx bx-shield-quarter"></i><div><strong>Esta información se mostrará en Portería</strong><span>Registra solo antecedentes operativos necesarios para impedir un retiro no autorizado.</span></div></div>
          <label class="form-label">Buscar alumna *</label><div class="search-control"><i class="bx bx-search"></i><input v-model="restrictionSearch" list="inspectoria-restriction-students" class="form-control" :disabled="Boolean(restriction.id)" placeholder="Nombre, RUT o curso" @input="syncRestrictionStudent" @change="syncRestrictionStudent" /></div><datalist id="inspectoria-restriction-students"><option v-for="student in studentOptions" :key="student.id" :value="student.text" /></datalist>
          <div v-if="selectedRestrictionStudent" class="restriction-guardian-picker"><span>Selecciona una persona para completar sus datos</span><button v-if="selectedRestrictionStudent.guardian_name" type="button" @click="applyRestrictionGuardian('primary')"><small>APODERADO TITULAR</small><strong>{{ selectedRestrictionStudent.guardian_name }}</strong><em>{{ selectedRestrictionStudent.guardian_rut || 'Sin RUT' }}</em><b><i class="bx bx-pointer"></i>Usar esta persona</b></button><button v-if="selectedRestrictionStudent.guardian_backup_name" type="button" @click="applyRestrictionGuardian('backup')"><small>APODERADO SUPLENTE</small><strong>{{ selectedRestrictionStudent.guardian_backup_name }}</strong><em>{{ selectedRestrictionStudent.guardian_backup_rut || 'Sin RUT' }}</em><b><i class="bx bx-pointer"></i>Usar esta persona</b></button></div>
          <div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Persona restringida *</label><BFormInput v-model="restriction.restricted_person_name" /></div><div class="col-md-3"><label class="form-label">RUT</label><BFormInput v-model="restriction.restricted_person_rut" /></div><div class="col-md-3"><label class="form-label">Relación</label><BFormInput v-model="restriction.restricted_person_relationship" placeholder="Ej. Padre" /></div><div class="col-md-6"><label class="form-label">Tipo de restricción *</label><BFormSelect v-model="restriction.restriction_type" :options="restrictionTypeOptions.slice(1)" /></div><div class="col-md-3"><label class="form-label">Vigente desde *</label><BFormInput v-model="restriction.starts_on" type="date" /></div><div class="col-md-3"><label class="form-label">Vigente hasta</label><BFormInput v-model="restriction.ends_on" type="date" /></div><div class="col-12"><label class="form-label">Motivo o antecedente *</label><BFormTextarea v-model="restriction.reason" rows="3" placeholder="Ej. Orden de alejamiento vigente; no autorizar el retiro." /></div><div class="col-12"><label class="form-label">Referencia legal o respaldo</label><BFormInput v-model="restriction.legal_reference" placeholder="Tribunal, causa, resolución u otro respaldo" /></div><div class="col-12"><BFormCheckbox v-model="restriction.active" switch>Restricción activa</BFormCheckbox></div></div>
          <div class="modal-submit-actions"><BButton type="button" variant="light" @click="showRestrictionModal=false">Cancelar</BButton><BButton type="submit" variant="danger" :disabled="saving"><i class="bx bx-shield-x me-1"></i>{{ saving ? 'Guardando...' : (restriction.id ? 'Actualizar restricción' : 'Crear restricción') }}</BButton></div>
        </form>
      </BModal>

      <BModal v-model="showStudentFile" size="xl" title="Ficha de alumna · Inspectoría" hide-footer>
        <LoadingState v-if="studentFileLoading" message="Cargando ficha..." />
        <div v-else-if="selectedStudentFile" class="student-file"><header><div class="student-avatar large">{{ initials(selectedStudentFile.student.registered_name_resolved) }}</div><div><small>{{ selectedStudentFile.student.rut || "Sin RUT" }}</small><h3>{{ selectedStudentFile.student.registered_name_resolved }}</h3><p>{{ selectedStudentFile.student.current_enrollment?.snapshot_course_display_name || selectedStudentFile.student.current_enrollment?.course_section?.display_name || "Sin curso vigente" }}</p></div><span class="status-chip active">{{ selectedStudentFile.student.general_status }}</span></header><div class="file-info"><div><span>Apoderado/a</span><strong>{{ selectedStudentFile.student.guardian_name || "No informado" }}</strong><small>{{ selectedStudentFile.student.guardian_phone || "Sin teléfono" }}</small></div><div><span>Contacto de emergencia</span><strong>{{ selectedStudentFile.student.emergency_contact_name || "No informado" }}</strong><small>{{ selectedStudentFile.student.emergency_contact_phone || "Sin teléfono" }}</small></div><div><span>Alertas operativas</span><strong>{{ selectedStudentFile.student.porter_alert_notes || "Sin alertas" }}</strong><small v-if="selectedStudentFile.student.pickup_restriction">Tiene restricción de retiro</small></div></div><div class="file-columns"><section><h5>Atenciones ({{ selectedStudentFile.attentions.length }})</h5><article v-for="item in selectedStudentFile.attentions" :key="item.id"><strong>{{ formatDate(item.attended_at) }}</strong><span>{{ item.request_types.map(requestLabel).join(', ') }}</span><p>{{ item.brief_note || "Sin nota adicional" }}</p></article></section><section><h5>Pases ({{ selectedStudentFile.passes.length }})</h5><article v-for="item in selectedStudentFile.passes" :key="item.id"><strong>{{ item.pass_code }} · {{ item.status }}</strong><span>{{ destinationLabel(item.destination) }} · {{ formatDate(item.valid_from) }}</span><p>{{ item.reason }}</p></article></section><section><h5>Bitácora ({{ selectedStudentFile.daily_logs.length }})</h5><article v-for="item in selectedStudentFile.daily_logs" :key="item.id"><strong>{{ item.title }}</strong><span>{{ formatDate(item.happened_at) }} · {{ item.priority }}</span><p>{{ item.detail }}</p></article></section></div></div>
      </BModal>

      <BModal v-model="showWithdrawalFile" size="xl" :title="selectedWithdrawal ? `Ficha de retiro · ${selectedWithdrawal.withdrawal_code}` : 'Ficha de retiro'" hide-footer>
        <LoadingState v-if="withdrawalFileLoading" message="Cargando ficha de retiro..." />
        <div v-else-if="selectedWithdrawal" class="withdrawal-file">
          <div class="withdrawal-file-origin"><i class="bx bx-building-house"></i><div><span>REGISTRO OFICIAL</span><strong>Retiro ingresado por Portería</strong></div><em>{{ selectedWithdrawal.withdrawal_code }}</em></div>
          <header><div class="student-avatar large">{{ initials(selectedWithdrawal.student_full_name_snapshot) }}</div><div><small>{{ selectedWithdrawal.student_rut_snapshot || "Sin RUT" }}</small><h3>{{ selectedWithdrawal.student_full_name_snapshot }}</h3><p>{{ selectedWithdrawal.course_name_snapshot || selectedWithdrawal.course_section?.display_name || "Sin curso" }}</p></div><span class="withdrawal-status" :class="selectedWithdrawal.status">{{ withdrawalStatusLabel(selectedWithdrawal.status) }}</span></header>
          <div class="withdrawal-file-grid">
            <section><span>Fecha y hora</span><strong>{{ formatDate(selectedWithdrawal.withdrawn_at) }}</strong></section>
            <section><span>Persona que retira</span><strong>{{ selectedWithdrawal.person_name }}</strong><small>{{ selectedWithdrawal.person_rut || "Sin RUT" }}</small></section>
            <section><span>Vínculo</span><strong>{{ withdrawalRelationshipLabel(selectedWithdrawal.person_relationship) }}</strong><small>{{ selectedWithdrawal.person_phone || "Sin teléfono" }}</small></section>
            <section><span>Motivo</span><strong>{{ withdrawalReasonLabel(selectedWithdrawal.reason) }}</strong></section>
            <section><span>Registrado por</span><strong>{{ userName(selectedWithdrawal.registered_by) }}</strong><small>Portería</small></section>
            <section><span>Autorización</span><strong>{{ selectedWithdrawal.person_authorized ? "Persona autorizada" : "Requiere validación especial" }}</strong><small v-if="selectedWithdrawal.authorized_by">Autorizó: {{ userName(selectedWithdrawal.authorized_by, '-') }}</small></section>
          </div>
          <div class="withdrawal-file-section"><span>Observaciones</span><p>{{ selectedWithdrawal.observations || "Sin observaciones adicionales." }}</p></div>
          <div v-if="selectedWithdrawal.authorization_notes" class="withdrawal-file-section is-warning"><span>Respaldo de autorización</span><p>{{ selectedWithdrawal.authorization_notes }}</p></div>
          <div class="withdrawal-signature-reminder"><i class="bx bx-pen"></i><div><strong>Respaldo físico con firma</strong><span>Descarga el acta para que Portería conserve la firma del apoderado o persona autorizada.</span></div><BButton variant="primary" @click="downloadWithdrawal(selectedWithdrawal)"><i class="bx bxs-file-pdf me-1"></i>Descargar acta PDF</BButton></div>
        </div>
      </BModal>
    </main>
  </Layout>
</template>

<style scoped>
.inspectoria-table-actions{display:flex;align-items:center;justify-content:flex-end;gap:.4rem;min-width:max-content}
.inspectoria-action-button{--action-color:#4d6576;--action-border:#c7d5df;--action-soft:#f3f7f9;width:42px;height:42px;flex:0 0 42px;display:inline-grid;place-items:center;padding:0;border:1px solid var(--action-border);border-radius:12px;appearance:none;background:#fff;color:var(--action-color);cursor:pointer;line-height:1;box-shadow:0 1px 2px rgba(23,59,87,.05);transition:color .16s ease,background-color .16s ease,border-color .16s ease,box-shadow .16s ease,transform .16s ease}
.inspectoria-action-button i{font-size:1.22rem;line-height:1;pointer-events:none}
.inspectoria-action-button:hover{border-color:var(--action-color);background:var(--action-soft);color:var(--action-color);box-shadow:0 5px 12px rgba(23,59,87,.12);transform:translateY(-1px)}
.inspectoria-action-button:active{box-shadow:0 2px 5px rgba(23,59,87,.1);transform:translateY(0)}
.inspectoria-action-button:focus-visible{outline:3px solid rgba(44,166,164,.22);outline-offset:2px}
.inspectoria-action-button:disabled{cursor:not-allowed;opacity:.48;transform:none}
.inspectoria-action-button--view{--action-color:#42637a;--action-border:#bfd0e6;--action-soft:#eef5fb}
.inspectoria-action-button--edit{--action-color:#a86800;--action-border:#e5af38;--action-soft:#ffedb0;background:#fff7dc}
.inspectoria-action-button--download{--action-color:#526a83;--action-border:#c2cfdb;--action-soft:#eef4f8}
.inspectoria-action-button--success{--action-color:#16794a;--action-border:#a9dfc3;--action-soft:#eaf8f0}
.inspectoria-action-button--danger{--action-color:#e24b50;--action-border:#f1a3a6;--action-soft:#fff1f1}
.inspectoria-page{--ink:#173b57;--blue:#176b87;--aqua:#2ca6a4;--soft:#f3f8fa;--line:#dce8ed;display:flex;flex-direction:column;gap:1rem;color:#263b4a}.module-hero{display:flex;align-items:center;gap:1rem;padding:1.4rem 1.5rem;border-radius:20px;background:linear-gradient(125deg,#123a55,#176b87 68%,#2ca6a4);color:#fff;box-shadow:0 14px 36px rgba(18,58,85,.18)}.hero-icon{width:58px;height:58px;border-radius:17px;background:rgba(255,255,255,.15);display:grid;place-items:center;font-size:1.8rem}.module-hero span{font-size:.68rem;font-weight:800;letter-spacing:.14em;opacity:.75}.module-hero h2{font-size:1.55rem;margin:.2rem 0}.module-hero p{margin:0;opacity:.82}.priority-seal{margin-left:auto;width:80px;height:70px;border:1px solid rgba(255,255,255,.4);border-radius:14px;text-align:center;display:grid;place-content:center}.priority-seal strong{font-size:1.7rem;line-height:1}.priority-seal span{font-size:.57rem}.module-tabs{display:flex;gap:.45rem;padding:.45rem;background:#fff;border:1px solid var(--line);border-radius:16px;overflow-x:auto}.module-tabs button{border:0;background:transparent;padding:.7rem 1rem;border-radius:11px;color:#6b7e8c;font-weight:700;white-space:nowrap}.module-tabs button i{margin-right:.45rem;font-size:1.1rem}.module-tabs button.active{background:var(--ink);color:#fff}.panel{background:#fff;border:1px solid var(--line);border-radius:18px;padding:1.25rem;box-shadow:0 7px 22px rgba(24,64,82,.045)}.panel-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.15rem}.panel-title span{display:block;font-size:.65rem;font-weight:800;letter-spacing:.13em;color:var(--aqua)}.panel-title h4{margin:.15rem 0 0;color:var(--ink)}.quick-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(330px,.75fr);gap:1rem}.speed-badge{padding:.5rem .7rem;background:#e9f8f5;border-radius:10px!important;letter-spacing:0!important;font-size:.72rem!important}.search-control{position:relative}.search-control>i{position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:#8296a4}.search-control .form-control{padding-left:2.4rem}.check-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.55rem}.check-grid--actions{grid-template-columns:repeat(2,minmax(0,1fr))}.check-card{display:flex;align-items:center;gap:.55rem;text-align:left;border:1px solid var(--line);background:#fff;border-radius:11px;padding:.7rem;color:#546a78;font-size:.82rem;font-weight:650}.check-card i{font-size:1.15rem;color:#9aadb7}.check-card.selected{border-color:var(--aqua);background:#edf9f7;color:var(--ink);box-shadow:0 0 0 1px var(--aqua)}.check-card.selected i{color:var(--aqua)}.quick-actions{display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--line);margin-top:1rem;padding-top:1rem}.count-pill{background:var(--ink);color:#fff!important;border-radius:99px!important;padding:.4rem .65rem;letter-spacing:0!important}.filter-row,.filter-inline,.student-filters,.filter-card{display:flex;gap:.55rem}.timeline-list{max-height:650px;overflow:auto}.timeline-list article{display:grid;grid-template-columns:12px 1fr;gap:.65rem;padding:.85rem 0;border-bottom:1px solid #edf2f4}.timeline-dot{width:10px;height:10px;margin-top:.3rem;border-radius:50%;background:var(--aqua);box-shadow:0 0 0 4px #e5f7f5}.timeline-dot.urgent{background:#d92d20;box-shadow:0 0 0 4px #fee4e2}.record-head{display:flex;justify-content:space-between;gap:.5rem}.record-head strong{color:var(--ink)}.record-head small,.timeline-list article>div>span{color:#8495a0;font-size:.72rem}.tag-line{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.4rem}.tag-line em{font-style:normal;font-size:.65rem;padding:.2rem .4rem;border-radius:6px;background:#eef4f6;color:#4d6877}.timeline-list p{margin:.5rem 0 0;font-size:.8rem}.location-chip,.status-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .55rem;border-radius:8px;background:#eef5f7;color:#4b6878;font-size:.75rem;font-weight:700}.status-chip.active{background:#e8f8ef;color:#16794a}.status-chip.closed{background:#f0f1f3;color:#667085}.priority-banner{display:flex;align-items:center;gap:.8rem;background:#fff5f3;border:1px solid #ffd2cc;color:#8f2218;border-radius:15px;padding:.85rem 1rem}.priority-banner>i{font-size:1.6rem}.priority-banner div{display:flex;flex-direction:column}.priority-banner div span{font-size:.78rem}.priority-banner b{margin-left:auto;font-size:.7rem;letter-spacing:.1em}.pass-layout{display:grid;grid-template-columns:minmax(360px,.72fr) minmax(500px,1.28fr);gap:1rem}.pass-form{align-self:start;position:sticky;top:82px}.filter-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:.8rem;margin-bottom:.75rem}.pass-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.pass-card{background:#fff;border:1px solid var(--line);border-top:4px solid #f79009;border-radius:15px;overflow:hidden}.pass-card.utilizado{border-top-color:#12b76a}.pass-card.vencido,.pass-card.anulado{border-top-color:#98a2b3;opacity:.82}.pass-card>header{display:flex;justify-content:space-between;padding:.7rem .9rem;background:#f8fafb}.pass-card header span{font-size:.73rem;font-weight:800;color:var(--ink)}.pass-card header em{font-size:.64rem;text-transform:uppercase;font-style:normal}.pass-person{display:flex;gap:.65rem;padding:.85rem}.pass-person>div,.student-avatar{width:42px;height:42px;flex:none;border-radius:13px;background:linear-gradient(135deg,var(--ink),var(--blue));color:#fff;display:grid;place-items:center;font-weight:800}.pass-person section{display:flex;flex-direction:column}.pass-person small,.pass-person span{font-size:.68rem;color:#8293a0}.pass-person strong{color:var(--ink)}.pass-route{margin:0 .85rem;padding:.65rem;display:grid;grid-template-columns:1fr auto 1fr;align-items:center;background:var(--soft);border-radius:10px;gap:.45rem}.pass-route div{display:flex;flex-direction:column}.pass-route small{font-size:.58rem;font-weight:800;color:#8498a4}.pass-route strong{font-size:.75rem;color:var(--ink)}.pass-card>p{padding:0 .9rem;margin:.75rem 0;font-size:.78rem;min-height:2.2rem}.pass-card>footer{display:flex;gap:.35rem;padding:.7rem .85rem;border-top:1px solid var(--line)}.student-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.student-card{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.75rem;border:1px solid var(--line);border-radius:14px;padding:.9rem}.student-card h5{font-size:.92rem;margin:.1rem 0;color:var(--ink)}.student-card small,.student-card p,.student-card span{font-size:.72rem;color:#718593;margin:0}.student-card p i,.student-card span i{margin-right:.3rem}.log-layout{display:grid;grid-template-columns:minmax(340px,.65fr) minmax(500px,1.35fr);gap:1rem}.log-form{align-self:start}.log-list article{display:grid;grid-template-columns:118px 1fr;border-left:4px solid #84adbb;padding:.85rem;border-bottom:1px solid var(--line)}.log-list article.priority-alta{border-left-color:#f79009}.log-list article.priority-urgente{border-left-color:#d92d20;background:#fff9f8}.log-time{font-size:.7rem;color:#7d929f}.log-title{display:flex;align-items:center;gap:.55rem}.log-title span{font-size:.62rem;background:#edf5f7;padding:.2rem .4rem;border-radius:6px}.log-title strong{color:var(--ink)}.log-title em{margin-left:auto;font-size:.62rem;text-transform:uppercase;font-style:normal}.log-list p{font-size:.8rem;margin:.45rem 0}.log-list small{margin-right:.8rem;color:#718692}.log-list small i{margin-right:.3rem}.follow-chip{margin-top:.5rem;display:inline-flex;padding:.3rem .55rem;border-radius:7px;background:#fff1d8;color:#925f00;font-size:.7rem}.empty-state{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;color:#8799a4;grid-column:1/-1}.empty-state i{font-size:2rem}.student-file>header{display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--soft);border-radius:14px}.student-avatar.large{width:58px;height:58px;font-size:1.1rem}.student-file header h3{margin:.1rem 0;color:var(--ink)}.student-file header p{margin:0}.student-file header>.status-chip{margin-left:auto}.file-info{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin:1rem 0}.file-info>div{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:12px;padding:.8rem}.file-info span{font-size:.65rem;color:#82939e}.file-info strong{color:var(--ink)}.file-info small{color:#718491}.file-columns{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}.file-columns section{border:1px solid var(--line);border-radius:12px;padding:.8rem;max-height:420px;overflow:auto}.file-columns h5{color:var(--ink);position:sticky;top:-.8rem;background:#fff;padding:.7rem 0}.file-columns article{border-top:1px solid var(--line);padding:.65rem 0;display:flex;flex-direction:column}.file-columns article strong{font-size:.75rem}.file-columns article span{font-size:.7rem;color:#718491}.file-columns article p{font-size:.75rem;margin:.25rem 0 0}.form-label{font-size:.74rem;font-weight:750;color:#4d6472;margin-bottom:.35rem}.table th{font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#748893}.table td{font-size:.8rem}
.attentions-heading{align-items:flex-start}.attentions-heading h4{font-size:1.15rem}.attentions-heading small{display:block;margin-top:.25rem;color:#80929e}.attention-filters{display:grid;grid-template-columns:minmax(320px,1fr) 180px 210px auto;gap:.65rem;padding:1rem;background:var(--soft);border:1px solid #e4edf0;border-radius:14px;margin-bottom:1rem}.attention-table-wrap{border:1px solid var(--line);border-radius:14px;overflow:hidden}.attention-table{margin:0}.attention-table thead{background:#f7fafb}.attention-table th{padding:.8rem .9rem;border-bottom:1px solid var(--line);white-space:nowrap}.attention-table td{padding:.85rem .9rem;border-color:#edf2f4;vertical-align:middle}.attention-table td>strong,.attention-table td>small{display:block}.attention-table td>strong{color:var(--ink);font-size:.8rem}.attention-table td>small{margin-top:.2rem;color:#7b8f9c;font-size:.68rem}.attention-table td>small i{margin-right:.3rem}.attention-table .tag-line{margin:0;min-width:190px}.attention-note{max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.follow-status,.priority-status{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .55rem;border-radius:99px;background:#eef5f2;color:#35715a;font-size:.68rem;font-weight:750;white-space:nowrap}.follow-status.pending{background:#fff3dc;color:#925f00}.priority-status{background:#eef2f4;color:#586f7d;text-transform:uppercase;letter-spacing:.04em}.priority-status.urgent{background:#fee4e2;color:#b42318}.modal-form-intro{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;margin-bottom:1rem;border-radius:13px;background:var(--soft)}.modal-form-intro>div>span{font-size:.63rem;font-weight:800;letter-spacing:.12em;color:var(--aqua)}.modal-form-intro h5{margin:.15rem 0 0;color:var(--ink)}.attention-file>header{display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--soft);border-radius:14px}.attention-file header h3{margin:.1rem 0;color:var(--ink)}.attention-file header p{margin:0;color:#718491}.attention-file header>.priority-status{margin-left:auto}.attention-file-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin:1rem 0}.attention-file-grid section,.attention-file-section{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:12px;padding:.85rem}.attention-file-grid span,.attention-file-section>span{font-size:.65rem;font-weight:750;text-transform:uppercase;letter-spacing:.06em;color:#81939e}.attention-file-grid strong{margin-top:.25rem;color:var(--ink);text-transform:capitalize}.attention-file-section{margin-top:.75rem}.attention-file-section .tag-line{margin-top:.6rem}.attention-file-section .tag-line.actions em{background:#e9f8f5;color:#236d6c}.attention-file-section p{margin:.5rem 0 0;color:#526976}.attention-file-flags{display:flex;gap:.65rem;margin-top:1rem}.attention-file-flags span{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem .7rem;border-radius:9px;background:#f1f3f4;color:#71828d;font-size:.73rem;font-weight:700}.attention-file-flags span.active{background:#e8f8ef;color:#16794a}.attention-file-flags span.warning{background:#fff3dc;color:#925f00}
.assignments-heading{align-items:flex-start}.assignments-heading h4{font-size:1.15rem}.assignments-heading small{display:block;margin-top:.25rem;color:#80929e}.assignment-filters{display:grid;grid-template-columns:minmax(180px,.8fr) minmax(260px,1.3fr) minmax(170px,.7fr) auto;gap:.65rem;padding:1rem;background:var(--soft);border:1px solid #e4edf0;border-radius:14px;margin-bottom:1rem}.assignment-table-wrap{border:1px solid var(--line);border-radius:14px;overflow:hidden}.assignment-table{margin:0}.assignment-table thead{background:#f7fafb}.assignment-table th{padding:.8rem .9rem;border-bottom:1px solid var(--line);white-space:nowrap}.assignment-table td{padding:.85rem .9rem;border-color:#edf2f4;vertical-align:middle}.assignment-table td>strong,.assignment-table td>small{display:block}.assignment-table td>strong{color:var(--ink)}.assignment-table td>small{margin-top:.2rem;color:#7b8f9c;font-size:.68rem}.assignment-actions{white-space:nowrap}.assignment-actions .btn+.btn{margin-left:.3rem}.assignment-file>header{display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--soft);border-radius:14px}.assignment-file-icon{width:58px;height:58px;display:grid;place-items:center;flex:none;border-radius:16px;background:linear-gradient(135deg,var(--ink),var(--blue));color:#fff;font-size:1.65rem}.assignment-file header h3{margin:.1rem 0;color:var(--ink)}.assignment-file header p{margin:0;color:#718491}.assignment-file header>.status-chip{margin-left:auto}.assignment-file-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin-top:1rem}.assignment-file-grid section,.assignment-file-note{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:12px;padding:.85rem}.assignment-file-grid span,.assignment-file-note>span{font-size:.65rem;font-weight:750;text-transform:uppercase;letter-spacing:.06em;color:#81939e}.assignment-file-grid strong{margin-top:.25rem;color:var(--ink)}.assignment-file-grid small{margin-top:.15rem;color:#718491}.assignment-file-note{margin-top:.75rem}.assignment-file-note p{margin:.45rem 0 0;color:#526976}.assignment-modal-actions{display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--line);margin-top:1.25rem;padding-top:1rem}
.passes-heading,.students-heading,.restrictions-heading,.daily-log-heading{align-items:flex-start}.passes-heading h4,.students-heading h4,.restrictions-heading h4,.daily-log-heading h4{font-size:1.15rem}.passes-heading small,.students-heading small,.restrictions-heading small,.daily-log-heading small{display:block;margin-top:.25rem;color:#80929e}.pass-filters{display:grid;grid-template-columns:minmax(320px,1fr) 180px 190px auto;gap:.65rem;padding:1rem;background:var(--soft);border:1px solid #e4edf0;border-radius:14px;margin-bottom:1rem}.student-table-filters{display:grid;grid-template-columns:minmax(320px,1fr) minmax(220px,.65fr) auto;gap:.65rem;padding:1rem;background:var(--soft);border:1px solid #e4edf0;border-radius:14px;margin-bottom:1rem}.daily-log-filters{display:grid;grid-template-columns:minmax(280px,1fr) 170px 190px 170px auto;gap:.65rem;padding:1rem;background:var(--soft);border:1px solid #e4edf0;border-radius:14px;margin-bottom:1rem}.pass-table-wrap,.student-table-wrap,.daily-log-table-wrap{border:1px solid var(--line);border-radius:14px;overflow:hidden}.pass-table,.student-table,.daily-log-table{margin:0}.pass-table thead,.student-table thead,.daily-log-table thead{background:#f7fafb}.pass-table th,.student-table th,.daily-log-table th{padding:.8rem .9rem;border-bottom:1px solid var(--line);white-space:nowrap}.pass-table td,.student-table td,.daily-log-table td{padding:.85rem .9rem;border-color:#edf2f4;vertical-align:middle}.pass-table td>strong,.pass-table td>small,.student-table td>strong,.student-table td>small,.daily-log-table td>strong,.daily-log-table td>small{display:block}.pass-table td>strong,.student-table td>strong,.daily-log-table td>strong{color:var(--ink)}.pass-table td>small,.student-table td>small,.daily-log-table td>small{margin-top:.2rem;color:#7b8f9c;font-size:.68rem;max-width:270px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.priority-mini{color:#b42318!important;font-weight:750}.pass-status,.log-priority{display:inline-flex;padding:.3rem .55rem;border-radius:99px;background:#fff3dc;color:#925f00;font-size:.67rem;font-weight:800;text-transform:uppercase;white-space:nowrap}.pass-status.utilizado{background:#e8f8ef;color:#16794a}.pass-status.vencido,.pass-status.anulado{background:#f0f1f3;color:#667085}.pass-status.anulado{background:#fee4e2;color:#b42318}.pass-actions,.daily-log-actions{white-space:nowrap}.pass-actions .btn+.btn,.daily-log-actions .btn+.btn{margin-left:.3rem}.student-table-person{display:flex;align-items:center;gap:.65rem;min-width:220px}.student-table-person .student-avatar{width:36px;height:36px;border-radius:11px;font-size:.72rem}.category-chip{display:inline-flex;padding:.3rem .5rem;border-radius:7px;background:#edf5f7;color:#426675;font-size:.68rem;font-weight:750;white-space:nowrap}.log-priority.baja{background:#eef2f4;color:#586f7d}.log-priority.media{background:#eaf3fb;color:#246b95}.log-priority.alta{background:#fff3dc;color:#925f00}.log-priority.urgente{background:#fee4e2;color:#b42318}.pass-file-priority{display:flex;align-items:center;gap:.8rem;padding:.9rem 1rem;border-radius:13px;background:#fff1ef;border:1px solid #ffd2cc;color:#8f2218}.pass-file-priority>i{font-size:1.7rem}.pass-file-priority>div{display:flex;flex-direction:column}.pass-file-priority span{font-size:.62rem;font-weight:800;letter-spacing:.1em}.pass-file-priority b{margin-left:auto;font-size:1.65rem}.pass-file>header{display:flex;align-items:center;gap:1rem;margin-top:1rem;padding:1rem;background:var(--soft);border-radius:14px}.pass-file header h3{margin:.1rem 0;color:var(--ink)}.pass-file header p{margin:0;color:#718491}.pass-file header>.pass-status{margin-left:auto}.pass-file-grid,.pass-file-signature{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin-top:1rem}.pass-file-grid section,.pass-file-section,.pass-file-signature>div{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:12px;padding:.85rem}.pass-file-grid span,.pass-file-section>span,.pass-file-signature span{font-size:.65rem;font-weight:750;text-transform:uppercase;letter-spacing:.06em;color:#81939e}.pass-file-grid strong,.pass-file-signature strong{margin-top:.25rem;color:var(--ink)}.pass-file-section{margin-top:.75rem}.pass-file-section p{margin:.45rem 0 0;color:#526976}.pass-file-actions,.modal-submit-actions{display:flex;justify-content:flex-end;gap:.5rem;border-top:1px solid var(--line);margin-top:1.2rem;padding-top:1rem}.pass-modal-priority{display:flex;align-items:center;gap:.75rem;padding:.8rem 1rem;margin-bottom:1rem;border-radius:12px;background:#fff1ef;color:#8f2218}.pass-modal-priority i{font-size:1.55rem}.pass-modal-priority div{display:flex;flex-direction:column}.pass-modal-priority span{font-size:.72rem}.daily-log-file>header{display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--soft);border-radius:14px}.daily-log-file-icon{width:58px;height:58px;display:grid;place-items:center;flex:none;border-radius:16px;background:linear-gradient(135deg,var(--ink),var(--blue));color:#fff;font-size:1.65rem}.daily-log-file header h3{margin:.1rem 0;color:var(--ink)}.daily-log-file header p{margin:0;color:#718491}.daily-log-file header>.log-priority{margin-left:auto}.daily-log-file-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin-top:1rem}.daily-log-file-grid section,.daily-log-file-section{display:flex;flex-direction:column;border:1px solid var(--line);border-radius:12px;padding:.85rem}.daily-log-file-grid span,.daily-log-file-section>span{font-size:.65rem;font-weight:750;text-transform:uppercase;letter-spacing:.06em;color:#81939e}.daily-log-file-grid strong{margin-top:.25rem;color:var(--ink)}.daily-log-file-section{margin-top:.75rem}.daily-log-file-section p{margin:.45rem 0 0;color:#526976;white-space:pre-line}.attention-file,.attention-modal-form,.assignment-file,.assignment-modal-form,.pass-file,.pass-modal-form,.daily-log-file,.daily-log-modal-form,.restriction-file,.restriction-modal-form,.student-file,.withdrawal-file{--ink:#173b57;--blue:#176b87;--aqua:#2ca6a4;--soft:#f3f8fa;--line:#dce8ed}
.restriction-safety-banner{align-items:center;background:linear-gradient(120deg,#8f2218,#b54733);border-radius:16px;color:#fff;display:flex;gap:1rem;padding:1rem 1.15rem;box-shadow:0 10px 24px rgba(143,34,24,.15)}.restriction-safety-banner>i{background:rgba(255,255,255,.14);border-radius:12px;display:grid;flex:0 0 46px;font-size:1.5rem;height:46px;place-items:center}.restriction-safety-banner div{display:flex;flex-direction:column}.restriction-safety-banner span{font-size:.62rem;font-weight:800;letter-spacing:.11em;opacity:.75}.restriction-safety-banner small{font-size:.72rem;opacity:.8;margin-top:.15rem}.restriction-filters{display:grid;grid-template-columns:minmax(280px,1fr) 190px 190px 150px auto;gap:.65rem;padding:1rem;background:var(--soft);border:1px solid #e4edf0;border-radius:14px;margin-bottom:1rem}.restriction-table-wrap{border:1px solid var(--line);border-radius:14px;overflow:hidden}.restriction-table{margin:0}.restriction-table thead{background:#f7fafb}.restriction-table th{padding:.8rem .9rem;border-bottom:1px solid var(--line);white-space:nowrap}.restriction-table td{padding:.85rem .9rem;border-color:#edf2f4;vertical-align:middle}.restriction-table td>strong,.restriction-table td>small{display:block}.restriction-table td>strong{color:var(--ink)}.restriction-table td>small{color:#7b8f9c;font-size:.68rem;margin-top:.2rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.restriction-type-chip{background:#fff0ed;border-radius:99px;color:#a32e23;display:inline-flex;font-size:.66rem;font-weight:800;padding:.32rem .58rem;white-space:nowrap}.restriction-form-alert{align-items:center;background:#fff5f3;border:1px solid #f2cdc6;border-radius:13px;color:#8f2218;display:flex;gap:.75rem;margin-bottom:1rem;padding:.85rem 1rem}.restriction-form-alert>i{font-size:1.6rem}.restriction-form-alert div{display:flex;flex-direction:column}.restriction-form-alert span{font-size:.72rem;color:#73534f}.restriction-guardian-picker{background:var(--soft);border:1px solid var(--line);border-radius:13px;display:grid;gap:.6rem;grid-template-columns:repeat(2,1fr);margin-top:.85rem;padding:.8rem}.restriction-guardian-picker>span{color:#718491;font-size:.68rem;font-weight:750;grid-column:1/-1}.restriction-guardian-picker button{background:#fff;border:1px solid var(--line);border-radius:11px;display:flex;flex-direction:column;padding:.75rem;text-align:left}.restriction-guardian-picker button:hover{border-color:#b54733;box-shadow:0 4px 12px rgba(143,34,24,.08)}.restriction-guardian-picker small{color:#8f2218;font-size:.62rem;font-weight:800}.restriction-guardian-picker em{color:#718491;font-size:.7rem;font-style:normal}.restriction-guardian-picker b{color:#176b87;font-size:.7rem;margin-top:.45rem}.restriction-file>header{align-items:center;background:#fff5f3;border-radius:14px;display:flex;gap:1rem;padding:1rem}.restriction-file-icon{background:linear-gradient(135deg,#8f2218,#b54733);border-radius:16px;color:#fff;display:grid;flex:none;font-size:1.65rem;height:58px;place-items:center;width:58px}.restriction-file header h3{color:var(--ink);margin:.1rem 0}.restriction-file header p{color:#718491;margin:0}.restriction-file header>.status-chip{margin-left:auto}.restriction-file-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;margin-top:1rem}.restriction-file-grid section,.restriction-file-section{border:1px solid var(--line);border-radius:12px;display:flex;flex-direction:column;padding:.85rem}.restriction-file-grid span,.restriction-file-section>span{color:#81939e;font-size:.65rem;font-weight:750;letter-spacing:.06em;text-transform:uppercase}.restriction-file-grid strong{color:var(--ink);margin-top:.25rem}.restriction-file-grid small{color:#718491}.restriction-file-section{margin-top:.75rem}.restriction-file-section p{color:#526976;margin:.45rem 0 0;white-space:pre-line}
.withdrawal-scope-card{align-items:flex-start;background:linear-gradient(120deg,#173b57,#216d7b);border-radius:16px;color:#fff;display:flex;gap:1rem;padding:1rem 1.15rem;box-shadow:0 10px 24px rgba(23,59,87,.12)}
.withdrawal-scope-icon{align-items:center;background:rgba(255,255,255,.13);border-radius:12px;display:flex;flex:0 0 auto;font-size:1.4rem;height:44px;justify-content:center;width:44px}.withdrawal-scope-card>div:nth-child(2){flex:1}.withdrawal-scope-card span{display:block;font-size:.62rem;font-weight:800;letter-spacing:.11em;opacity:.72}.withdrawal-scope-card strong{display:block;margin:.2rem 0 .5rem}.withdrawal-course-list{display:flex;flex-wrap:wrap;gap:.35rem}.withdrawal-course-list em{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);border-radius:99px;font-size:.68rem;font-style:normal;font-weight:700;padding:.25rem .55rem}.withdrawal-course-list em.is-empty{color:#ffd4cc}.withdrawal-origin{align-items:flex-end;display:flex!important;flex-direction:column;flex:0 0 auto}.withdrawal-origin i{font-size:1.35rem}.withdrawal-origin b{font-size:.9rem}
.withdrawals-heading{align-items:flex-start}.withdrawals-heading h4{font-size:1.15rem}.withdrawals-heading small{display:block;margin-top:.25rem;color:#80929e}.withdrawal-mode-switch{background:#edf3f6;border-radius:11px;display:flex;padding:.25rem}.withdrawal-mode-switch button{align-items:center;background:transparent;border:0;border-radius:8px;color:#637986;display:flex;font-size:.76rem;font-weight:750;gap:.35rem;padding:.55rem .75rem}.withdrawal-mode-switch button.active{background:#fff;box-shadow:0 2px 8px rgba(23,59,87,.1);color:var(--ink)}
.withdrawal-filters{display:grid;grid-template-columns:minmax(290px,1fr) minmax(200px,.6fr) 175px auto;gap:.65rem;padding:1rem;background:var(--soft);border:1px solid #e4edf0;border-radius:14px;margin-bottom:1rem}.withdrawal-filters.is-history{grid-template-columns:minmax(260px,1fr) minmax(180px,.55fr) 165px 150px 150px auto}.withdrawal-table-wrap{border:1px solid var(--line);border-radius:14px;overflow:hidden}.withdrawal-table{margin:0}.withdrawal-table thead{background:#f7fafb}.withdrawal-table th{padding:.8rem .9rem;border-bottom:1px solid var(--line);white-space:nowrap}.withdrawal-table td{padding:.85rem .9rem;border-color:#edf2f4;vertical-align:middle}.withdrawal-table td>strong,.withdrawal-table td>small{display:block}.withdrawal-table td>strong{color:var(--ink)}.withdrawal-table td>small{color:#7b8f9c;font-size:.68rem;margin-top:.2rem;white-space:nowrap}.withdrawal-status{background:#eef2f4;border-radius:99px;color:#586f7d;display:inline-flex;font-size:.66rem;font-weight:800;padding:.32rem .58rem;text-transform:uppercase;white-space:nowrap}.withdrawal-status.registrado,.withdrawal-status.autorizado{background:#e8f8ef;color:#16794a}.withdrawal-status.observado{background:#fff3dc;color:#925f00}.withdrawal-status.rechazado,.withdrawal-status.anulado{background:#fee4e2;color:#b42318}
.withdrawal-file-origin{align-items:center;background:linear-gradient(120deg,#173b57,#216d7b);border-radius:14px;color:#fff;display:flex;gap:.75rem;padding:.85rem 1rem}.withdrawal-file-origin>i{font-size:1.6rem}.withdrawal-file-origin div{display:flex;flex-direction:column}.withdrawal-file-origin span{font-size:.6rem;font-weight:800;letter-spacing:.1em;opacity:.7}.withdrawal-file-origin em{font-size:.75rem;font-style:normal;font-weight:800;margin-left:auto}.withdrawal-file>header{align-items:center;background:var(--soft);border-radius:14px;display:flex;gap:1rem;margin-top:.85rem;padding:1rem}.withdrawal-file header h3{color:var(--ink);margin:.1rem 0}.withdrawal-file header p{margin:0}.withdrawal-file header>.withdrawal-status{margin-left:auto}.withdrawal-file-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-top:1rem}.withdrawal-file-grid section,.withdrawal-file-section{border:1px solid var(--line);border-radius:12px;display:flex;flex-direction:column;padding:.85rem}.withdrawal-file-grid span,.withdrawal-file-section>span{color:#81939e;font-size:.65rem;font-weight:750;letter-spacing:.06em;text-transform:uppercase}.withdrawal-file-grid strong{color:var(--ink);margin-top:.25rem}.withdrawal-file-grid small{color:#718491;margin-top:.15rem}.withdrawal-file-section{margin-top:.75rem}.withdrawal-file-section p{color:#526976;margin:.45rem 0 0;white-space:pre-line}.withdrawal-file-section.is-warning{background:#fffaf0;border-color:#f2d69e}.withdrawal-signature-reminder{align-items:center;background:#edf7f4;border:1px solid #cde8de;border-radius:13px;color:#175e4c;display:flex;gap:.75rem;margin-top:1rem;padding:.9rem 1rem}.withdrawal-signature-reminder>i{font-size:1.65rem}.withdrawal-signature-reminder>div{display:flex;flex:1;flex-direction:column}.withdrawal-signature-reminder span{font-size:.72rem}
.check-card.psychosocial{border-style:dashed}.check-card.psychosocial.selected{border-style:solid;background:linear-gradient(135deg,#edf9f7,#f4f0fb)}.psychosocial-referral-box{margin-top:1rem;padding:1rem;border:1px solid #cfc5e4;border-radius:15px;background:linear-gradient(135deg,#f4fbfa,#f7f3fc);box-shadow:0 8px 22px rgba(74,55,112,.06)}.psychosocial-referral-heading{display:flex;align-items:center;gap:.75rem;margin-bottom:.8rem}.referral-heading-icon,.referral-file-icon{display:grid;place-items:center;flex:none;width:44px;height:44px;border-radius:13px;background:linear-gradient(135deg,#176b87,#69549a);color:#fff;font-size:1.3rem}.psychosocial-referral-heading span,.attention-referral-file>div:nth-child(2)>span{display:block;font-size:.61rem;font-weight:800;letter-spacing:.11em;color:#69549a}.psychosocial-referral-heading h6{margin:.12rem 0;color:var(--ink)}.psychosocial-referral-heading p{margin:0;color:#718491;font-size:.72rem}.selected-referral-professional{display:grid;grid-template-columns:40px 1fr auto;align-items:center;gap:.65rem;margin-top:.7rem;padding:.65rem .75rem;border:1px solid #d7e7e5;border-radius:12px;background:#fff}.professional-avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#e9f8f5;color:#176b87;font-size:.72rem;font-weight:800}.selected-referral-professional strong,.selected-referral-professional span{display:block}.selected-referral-professional strong{color:var(--ink);font-size:.8rem}.selected-referral-professional span{color:#718491;font-size:.68rem}.selected-referral-professional>i{font-size:1.25rem;color:#12a06a}.referral-empty{display:flex;align-items:center;gap:.4rem;margin:.7rem 0 0;color:#b42318;font-size:.72rem}.attention-referral-file{display:grid;grid-template-columns:48px 1fr auto;align-items:center;gap:.75rem;margin-top:.75rem;padding:.8rem;border:1px solid #d8cee9;border-radius:13px;background:#f7f3fc}.attention-referral-file strong,.attention-referral-file small{display:block}.attention-referral-file strong{color:var(--ink)}.attention-referral-file small{color:#718491;font-size:.7rem}.attention-referral-file .follow-status{align-self:center}
@media(max-width:1200px){.quick-grid,.pass-layout,.log-layout{grid-template-columns:1fr}.pass-form{position:static}.student-grid{grid-template-columns:repeat(2,1fr)}.attention-filters{grid-template-columns:minmax(260px,1fr) 170px 200px auto}.assignment-filters{grid-template-columns:1fr 1.2fr 1fr auto}.restriction-filters{grid-template-columns:1fr 1fr 1fr}.restriction-filters .search-control{grid-column:1/-1}.daily-log-filters{grid-template-columns:1fr 170px 190px 170px}.daily-log-filters>.btn{grid-column:1/-1}.withdrawal-filters,.withdrawal-filters.is-history{grid-template-columns:1fr 1fr 1fr}.withdrawal-filters .search-control{grid-column:1/-1}}
@media(max-width:768px){.module-hero{align-items:flex-start}.priority-seal{display:none}.module-hero p{font-size:.78rem}.module-tabs button span{display:none}.module-tabs button i{margin:0}.quick-grid,.student-grid,.file-info,.file-columns,.attention-file-grid,.assignment-file-grid,.pass-file-grid,.pass-file-signature,.daily-log-file-grid{grid-template-columns:1fr}.check-grid,.check-grid--actions,.pass-list{grid-template-columns:1fr}.student-card{grid-template-columns:auto 1fr}.student-card .btn{grid-column:1/-1}.student-filters,.filter-card{flex-direction:column}.log-list article{grid-template-columns:1fr}.log-time{margin-bottom:.35rem}.attention-filters,.assignment-filters,.pass-filters,.student-table-filters,.daily-log-filters{grid-template-columns:1fr}.daily-log-filters>.btn{grid-column:auto}.attentions-heading,.assignments-heading,.passes-heading,.students-heading,.daily-log-heading{gap:1rem}.attentions-heading .btn,.assignments-heading .btn,.passes-heading .btn,.daily-log-heading .btn{white-space:nowrap}.attention-file-flags{flex-direction:column}.attention-referral-file{grid-template-columns:44px 1fr}.attention-referral-file .follow-status{grid-column:2;justify-self:start}.assignment-modal-actions{align-items:flex-start;flex-direction:column;gap:1rem}.assignment-modal-actions>div{align-self:stretch}.assignment-modal-actions>div .btn{flex:1}.pass-file-actions,.modal-submit-actions{flex-direction:column-reverse}.pass-file-actions .btn,.modal-submit-actions .btn{width:100%}}
@media(max-width:768px){.restriction-safety-banner{align-items:flex-start}.restriction-filters,.restriction-guardian-picker,.restriction-file-grid{grid-template-columns:1fr}.restriction-filters .search-control,.restriction-guardian-picker>span{grid-column:auto}.withdrawal-scope-card{flex-wrap:wrap}.withdrawal-origin{align-items:flex-start;flex-basis:100%;padding-left:3.75rem}.withdrawals-heading{flex-direction:column;gap:.8rem}.withdrawal-mode-switch{width:100%}.withdrawal-mode-switch button{flex:1;justify-content:center}.withdrawal-filters,.withdrawal-filters.is-history{grid-template-columns:1fr}.withdrawal-filters .search-control{grid-column:auto}.withdrawal-file-grid{grid-template-columns:1fr}.withdrawal-signature-reminder{align-items:flex-start;flex-wrap:wrap}.withdrawal-signature-reminder .btn{margin-left:2.4rem;width:calc(100% - 2.4rem)}}
</style>
