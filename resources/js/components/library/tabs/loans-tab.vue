<script>
import axios from "axios";
import Swal from "sweetalert2";
import LibraryHelpButton from "../help-button.vue";
import LibraryStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmLibraryAction,
  confirmLibraryCancel,
  downloadPdfReport,
  formatLibraryDate,
  formatLibraryError,
  showLibrarySuccess,
} from "../module-utils";

const emptyForm = () => ({
  borrower_type: "student",
  student_profile_id: null,
  staff_id: null,
  user_id: null,
  course_section_id: null,
  biblioteca_ejemplar_id: null,
  same_as_borrower: false,
  borrowed_at: new Date().toISOString().slice(0, 10),
  due_at: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10),
  pickup_person_type: "student",
  pickup_person_name: "",
  pickup_person_rut: "",
  pickup_person_email: "",
  pickup_person_relationship: "",
  signature_name: "",
  signature_rut: "",
  delivery_notes: "",
  notes: "",
});

const emptyEditForm = () => ({
  due_at: "",
  pickup_person_type: "student",
  pickup_person_name: "",
  pickup_person_rut: "",
  pickup_person_email: "",
  pickup_person_relationship: "",
  signature_name: "",
  signature_rut: "",
  delivery_notes: "",
  notes: "",
  register_return: false,
  returned_condition: "bueno",
});

export default {
  components: {
    LibraryHelpButton,
    LibraryStatusBadge,
    LoadingState,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      items: [],
      failedLoanCovers: {},
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        status: null,
        student_profile_id: null,
        staff_id: null,
        course_section_id: null,
        overdue_only: false,
        date_from: "",
        date_to: "",
      },
      showModal: false,
      showEditModal: false,
      selectedLoan: null,
      editForm: emptyEditForm(),
      form: emptyForm(),
      borrowerSearch: "",
      exemplarSearch: "",
    };
  },
  computed: {
    selectedStudent() {
      return (this.catalogs.students || []).find(
        (item) => Number(item.id) === Number(this.form.student_profile_id)
      ) || null;
    },
    isEarlyChildhoodLoan() {
      if (this.form.borrower_type !== "student") return false;
      const level = String(this.selectedStudent?.level || "").toUpperCase().replaceAll(" ", "");
      return ["NT1", "NT2"].some((code) => level.includes(code));
    },
    selectedBorrower() {
      if (this.form.borrower_type === "student") {
        return this.selectedStudent;
      }
      if (["staff", "teacher"].includes(this.form.borrower_type)) {
        return (this.catalogs.staff || []).find(
          (item) => Number(item.id) === Number(this.form.staff_id)
        ) || null;
      }
      if (this.form.borrower_type === "guardian") {
        return (this.catalogs.guardians || []).find(
          (item) => Number(item.student_profile_id) === Number(this.form.student_profile_id)
        ) || null;
      }
      if (this.form.borrower_type === "course") {
        return (this.catalogs.courses || []).find(
          (item) => Number(item.id) === Number(this.form.course_section_id)
        ) || null;
      }
      return null;
    },
    canRepeatBorrower() {
      return Boolean(this.selectedBorrower && this.form.borrower_type !== "course" && !this.isEarlyChildhoodLoan);
    },
    borrowerMatches() {
      const options = this.borrowerOptions();
      const query = this.normalizeExemplarSearch(this.borrowerSearch);
      if (!query) return options.slice(0, 12);

      return options
        .filter((item) => this.normalizeExemplarSearch(item.text).includes(query))
        .slice(0, 12);
    },
    selectedBorrowerLabel() {
      const selectedValue = this.form[this.selectedBorrowerModel()];
      return this.borrowerOptions().find(
        (item) => Number(item.value) === Number(selectedValue)
      )?.text || "";
    },
    availableExemplarOptions() {
      return (this.catalogs.exemplars || [])
        .filter((item) => item.availability_status === "disponible")
        .map((item) => ({
          ...item,
          searchLabel: [
            item.isbn ? `ISBN ${item.isbn}` : "Sin ISBN",
            item.title || "Sin título",
            item.main_author || "Autor no informado",
            item.code,
          ].join(" · "),
        }));
    },
    selectedExemplar() {
      return this.availableExemplarOptions.find(
        (item) => Number(item.id) === Number(this.form.biblioteca_ejemplar_id)
      ) || null;
    },
    canSave() {
      return Boolean(
        this.form[this.selectedBorrowerModel()] &&
        this.form.biblioteca_ejemplar_id &&
        this.form.borrowed_at &&
        this.form.due_at
      );
    },
    loanResultRange() {
      if (!this.pagination.total || !this.items.length) return "Sin resultados";
      const start = (this.pagination.current_page - 1) * this.pagination.per_page + 1;
      const end = Math.min(start + this.items.length - 1, this.pagination.total);
      return `${start}–${end} de ${this.pagination.total}`;
    },
  },
  mounted() {
    this.consumeRouteFilters();
    this.load();
  },
  watch: {
    "$route.query": {
      deep: true,
      handler() {
        this.consumeRouteFilters();
      },
    },
  },
  methods: {
    formatLibraryDate,
    consumeRouteFilters() {
      if (this.$route.query.student) {
        this.filters.student_profile_id = Number(this.$route.query.student);
      }
      if (this.$route.query.staff) {
        this.filters.staff_id = Number(this.$route.query.staff);
      }
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/biblioteca/prestamos", {
          params: { page, ...this.filters, overdue_only: this.filters.overdue_only ? 1 : "" },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudieron cargar los préstamos.");
      } finally {
        this.loading = false;
      }
    },
    borrowerInitials(name) {
      return String(name || "?")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join("")
        .toUpperCase();
    },
    borrowerTypeLabel(item) {
      const type = item.borrower_estate || item.borrower_type;
      return {
        student: "Estudiante",
        staff: "Funcionario/a",
        teacher: "Docente",
        guardian: "Apoderado/a",
        course: "Curso",
      }[type] || "Solicitante";
    },
    loanCoverAvailable(item) {
      return Boolean(item?.obra?.cover_image_url && !this.failedLoanCovers[item.id]);
    },
    markLoanCoverFailed(item) {
      this.failedLoanCovers = { ...this.failedLoanCovers, [item.id]: true };
    },
    isLoanOpen(item) {
      return ["activo", "renovado", "vencido"].includes(item.status);
    },
    openCreate() {
      this.form = emptyForm();
      this.borrowerSearch = "";
      this.exemplarSearch = "";
      this.showModal = true;
    },
    changeBorrowerType(value) {
      this.form.borrower_type = value;
      this.borrowerSearch = "";
      this.clearBorrowerSelection();
    },
    clearBorrowerSelection({ keepSearch = false } = {}) {
      this.form.student_profile_id = null;
      this.form.staff_id = null;
      this.form.course_section_id = null;
      this.form.user_id = null;
      this.form.same_as_borrower = false;
      this.clearPickupDetails();
      if (!keepSearch) this.borrowerSearch = "";
    },
    matchBorrower(value) {
      this.borrowerSearch = value;
      const query = this.normalizeExemplarSearch(value);
      const match = this.borrowerOptions().find(
        (item) => this.normalizeExemplarSearch(item.text) === query
      );

      if (match) {
        this.selectBorrower(match.value);
        return;
      }

      this.clearBorrowerSelection({ keepSearch: true });
    },
    selectBorrower(value) {
      const selectedOption = this.borrowerOptions().find(
        (item) => Number(item.value) === Number(value)
      );
      const model = this.selectedBorrowerModel();
      this.form.student_profile_id = null;
      this.form.staff_id = null;
      this.form.course_section_id = null;
      this.form.user_id = null;
      this.form[model] = value;
      this.borrowerSearch = selectedOption?.text || this.borrowerSearch;

      if (this.form.borrower_type === "student") {
        const student = (this.catalogs.students || []).find((item) => Number(item.id) === Number(value));
        const level = String(student?.level || "").toUpperCase().replaceAll(" ", "");
        const early = ["NT1", "NT2"].some((code) => level.includes(code));
        this.form.pickup_person_type = early ? "guardian" : "student";
        this.form.pickup_person_name = early ? student?.guardian_name || "" : student?.name || "";
        this.form.pickup_person_rut = early ? student?.guardian_rut || "" : student?.rut || "";
        this.form.pickup_person_email = early ? student?.guardian_email || "" : "";
        this.form.pickup_person_relationship = early ? student?.guardian_relationship || "Apoderado/a" : "";
        this.form.signature_name = this.form.pickup_person_name;
        this.form.signature_rut = this.form.pickup_person_rut;
        if (early) {
          this.form.same_as_borrower = false;
          return;
        }
      }

      this.clearPickupDetails();
      if (this.form.same_as_borrower) this.applyBorrowerToPickup();
    },
    borrowerSearchPlaceholder() {
      return {
        student: "Escribe nombre, RUT o curso",
        staff: "Escribe nombre, RUT o cargo",
        teacher: "Escribe nombre o RUT del docente",
        guardian: "Escribe nombre, RUT o estudiante relacionada",
        course: "Escribe curso o nivel",
      }[this.form.borrower_type] || "Escribe para buscar";
    },
    borrowerOptions() {
      if (this.form.borrower_type === "student") {
        return (this.catalogs.students || []).map((item) => ({
          value: item.id,
          text: `${item.name} · ${item.rut || "Sin RUT"} · ${item.course || "Sin curso"}`,
        }));
      }
      if (this.form.borrower_type === "teacher") {
        return (this.catalogs.staff || [])
          .filter((item) => this.isTeachingStaff(item))
          .map((item) => ({ value: item.id, text: `${item.full_name} · ${item.rut || "Sin RUT"}` }));
      }
      if (this.form.borrower_type === "staff") {
        return (this.catalogs.staff || []).map((item) => ({
          value: item.id,
          text: `${item.full_name} · ${item.rut || "Sin RUT"} · ${item.cargo?.name || "Funcionario/a"}`,
        }));
      }
      if (this.form.borrower_type === "guardian") {
        return (this.catalogs.guardians || []).map((item) => ({
          value: item.student_profile_id,
          text: `${item.name} · ${item.rut || "Sin RUT"} · Apoderado/a de ${item.student_name}`,
        }));
      }
      if (this.form.borrower_type === "course") {
        return (this.catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name }));
      }
      return [];
    },
    selectedBorrowerModel() {
      return ["student", "guardian"].includes(this.form.borrower_type)
        ? "student_profile_id"
        : this.form.borrower_type === "staff" || this.form.borrower_type === "teacher"
        ? "staff_id"
        : this.form.borrower_type === "course"
        ? "course_section_id"
        : "user_id";
    },
    isTeachingStaff(item) {
      const role = `${item?.cargo?.name || ""} ${item?.cargo?.slug || ""}`
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase();
      return ["docent", "profesor", "educador"].some((term) => role.includes(term));
    },
    clearPickupDetails() {
      this.form.pickup_person_type = this.form.borrower_type === "course"
        ? "teacher"
        : this.form.borrower_type;
      this.form.pickup_person_name = "";
      this.form.pickup_person_rut = "";
      this.form.pickup_person_email = "";
      this.form.pickup_person_relationship = "";
      this.form.signature_name = "";
      this.form.signature_rut = "";
    },
    applyBorrowerToPickup() {
      const borrower = this.selectedBorrower;
      if (!borrower || this.form.borrower_type === "course") return;

      const isStaff = ["staff", "teacher"].includes(this.form.borrower_type);
      this.form.pickup_person_type = this.form.borrower_type;
      this.form.pickup_person_name = isStaff ? borrower.full_name || "" : borrower.name || "";
      this.form.pickup_person_rut = borrower.rut || "";
      this.form.pickup_person_email = borrower.email || "";
      this.form.pickup_person_relationship = this.form.borrower_type === "guardian"
        ? borrower.relationship || "Apoderado/a"
        : "";
      this.form.signature_name = this.form.pickup_person_name;
      this.form.signature_rut = this.form.pickup_person_rut;
    },
    toggleSameAsBorrower(value) {
      this.form.same_as_borrower = Boolean(value);
      if (this.form.same_as_borrower) this.applyBorrowerToPickup();
    },
    normalizeExemplarSearch(value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toLowerCase();
    },
    matchExemplar(value) {
      this.exemplarSearch = value;
      const query = this.normalizeExemplarSearch(value);
      const match = this.availableExemplarOptions.find((item) =>
        [item.searchLabel, item.code].some(
          (candidate) => this.normalizeExemplarSearch(candidate) === query
        )
      );
      this.form.biblioteca_ejemplar_id = match?.id || null;
    },
    clearExemplar() {
      this.exemplarSearch = "";
      this.form.biblioteca_ejemplar_id = null;
    },
    async save() {
      const confirmed = await confirmLibraryAction({
        title: "Confirmar préstamo",
        text: "Se registrará el préstamo seleccionado y se actualizará la disponibilidad del ejemplar.",
        confirmButtonText: "Sí, registrar",
      });
      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        const payload = {
          borrower_type: this.form.borrower_type,
          student_profile_id: this.form.student_profile_id || null,
          staff_id: this.form.staff_id || null,
          user_id: this.form.user_id || null,
          course_section_id: this.form.course_section_id || null,
          biblioteca_ejemplar_id: this.form.biblioteca_ejemplar_id,
          borrowed_at: this.form.borrowed_at,
          due_at: this.form.due_at,
          pickup_person_type: this.form.pickup_person_type,
          pickup_person_name: this.form.pickup_person_name || null,
          pickup_person_rut: this.form.pickup_person_rut || null,
          pickup_person_email: this.form.pickup_person_email || null,
          pickup_person_relationship: this.form.pickup_person_relationship || null,
          signature_name: this.form.signature_name || null,
          signature_rut: this.form.signature_rut || null,
          delivery_notes: this.form.delivery_notes || null,
          notes: this.form.notes || null,
        };
        await axios.post("/api/biblioteca/prestamos", payload);
        this.showModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showLibrarySuccess("Préstamo registrado correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    async openEdit(item) {
      this.error = null;
      try {
        const response = await axios.get(`/api/biblioteca/prestamos/${item.id}`);
        this.selectedLoan = response.data.data;
        this.editForm = {
          ...emptyEditForm(),
          due_at: String(this.selectedLoan.due_at || "").slice(0, 10),
          pickup_person_type: this.selectedLoan.pickup_person_type || "student",
          pickup_person_name: this.selectedLoan.pickup_person_name || "",
          pickup_person_rut: this.selectedLoan.pickup_person_rut || "",
          pickup_person_email: this.selectedLoan.pickup_person_email || "",
          pickup_person_relationship: this.selectedLoan.pickup_person_relationship || "",
          signature_name: this.selectedLoan.signature_name || "",
          signature_rut: this.selectedLoan.signature_rut || "",
          delivery_notes: this.selectedLoan.delivery_notes || "",
          notes: this.selectedLoan.notes || "",
        };
        this.showEditModal = true;
      } catch (error) {
        this.error = formatLibraryError(error, "No se pudo abrir la ficha del préstamo.");
      }
    },
    async saveEdit() {
      this.saving = true;
      this.error = null;
      try {
        await axios.put(`/api/biblioteca/prestamos/${this.selectedLoan.id}`, {
          due_at: this.editForm.due_at,
          pickup_person_type: this.editForm.pickup_person_type,
          pickup_person_name: this.editForm.pickup_person_name || null,
          pickup_person_rut: this.editForm.pickup_person_rut || null,
          pickup_person_email: this.editForm.pickup_person_email || null,
          pickup_person_relationship: this.editForm.pickup_person_relationship || null,
          signature_name: this.editForm.signature_name || null,
          signature_rut: this.editForm.signature_rut || null,
          delivery_notes: this.editForm.delivery_notes || null,
          notes: this.editForm.notes || null,
        });
        if (this.editForm.register_return) {
          await axios.post(`/api/biblioteca/prestamos/${this.selectedLoan.id}/return`, {
            returned_condition: this.editForm.returned_condition,
          });
        }
        this.showEditModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showLibrarySuccess(this.editForm.register_return
          ? "Ficha actualizada y devolución registrada."
          : "Ficha de préstamo actualizada correctamente.");
      } catch (error) {
        this.error = formatLibraryError(error);
      } finally {
        this.saving = false;
      }
    },
    exportLoan(item) {
      const borrower = [
        ["Nombre", item.borrower_name_snapshot || "-"],
        ["RUT", item.borrower_rut_snapshot || "-"],
        ["Estamento", item.borrower_estate || item.borrower_type || "-"],
        ["Curso", item.course_name_snapshot || "-"],
      ];
      const book = [
        ["Título", item.obra?.title || "-"],
        ["Código ejemplar", item.ejemplar?.code || "-"],
        ["Código préstamo", item.loan_code],
      ];
      const delivery = [
        ["Fecha préstamo", formatLibraryDate(item.borrowed_at)],
        ["Fecha de entrega", formatLibraryDate(item.due_at)],
        ["Persona que retira", item.pickup_person_name || item.borrower_name_snapshot || "-"],
        ["RUT retiro / firma", item.pickup_person_rut || item.signature_rut || "-"],
        ["Firma", "________________________________________"],
      ];
      downloadPdfReport(
        `ficha-prestamo-${item.loan_code}`,
        "Ficha de préstamo de biblioteca",
        "Registro de entrega y compromiso de devolución",
        [
          { title: "Datos de la estudiante / funcionario", headers: ["Campo", "Información"], rows: borrower },
          { title: "Datos del libro", headers: ["Campo", "Información"], rows: book },
          { title: "Entrega y firma", headers: ["Campo", "Información"], rows: delivery },
        ]
      );
    },
    async renew(item) {
      const result = await Swal.fire({
        title: "Renovar préstamo",
        input: "date",
        inputLabel: "Nueva fecha de devolución",
        inputValue: item.due_at,
        showCancelButton: true,
        confirmButtonText: "Renovar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed || !result.value) return;

      await axios.post(`/api/biblioteca/prestamos/${item.id}/renew`, { due_at: result.value });
      await this.load(this.pagination.current_page);
      await showLibrarySuccess("Préstamo renovado correctamente.");
    },
    async cancel(item) {
      const confirmed = await confirmLibraryAction({
        title: "Cancelar préstamo",
        text: `Se cancelará el préstamo ${item.loan_code}.`,
        confirmButtonText: "Sí, cancelar",
        icon: "warning",
      });

      if (!confirmed.isConfirmed) return;

      await axios.post(`/api/biblioteca/prestamos/${item.id}/cancel`);
      this.$emit("refresh-catalogs");
      await this.load(this.pagination.current_page);
      await showLibrarySuccess("Préstamo cancelado correctamente.");
    },
    async closeModal() {
      const confirmed = await confirmLibraryCancel("el registro del préstamo");
      if (confirmed.isConfirmed) this.showModal = false;
    },
  },
};
</script>

<template>
  <div class="loan-view">
    <section class="loan-head">
      <div>
        <span>CIRCULACIÓN Y ENTREGA</span>
        <h5>Préstamos con ficha, firma y alertas</h5>
        <p>NT1 y NT2 registran al apoderado que retira; desde 1° básico la estudiante puede retirar directamente.</p>
      </div>
      <div class="d-flex gap-2">
        <LibraryHelpButton
          title="Ayuda: flujo de préstamos"
          text="La ficha reúne datos de la estudiante o funcionario, libro, retiro, firma y fecha de entrega. La devolución se gestiona dentro de Editar ficha."
        />
        <BButton variant="light" class="head-action" @click="openCreate"><i class="bx bx-plus me-1"></i>Nuevo préstamo</BButton>
      </div>
    </section>

    <div class="rule-grid">
      <article><i class="bx bx-user-voice"></i><div><strong>NT1 y NT2</strong><span>Retiro obligatorio por apoderado con nombre y RUT.</span></div></article>
      <article><i class="bx bx-user-check"></i><div><strong>1° básico a 4° medio</strong><span>La estudiante puede retirar el libro personalmente.</span></div></article>
      <article><i class="bx bx-bell"></i><div><strong>Alertas visibles</strong><span>Vencimientos y días de atraso directamente en la tabla.</span></div></article>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Código, usuario, obra..." @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat((catalogs.loan_statuses || []).map((item) => ({ value: item.value, text: item.label })))" /></div>
        <div class="col-md-2"><label class="form-label">Estudiante</label><BFormSelect v-model="filters.student_profile_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.students || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-2"><label class="form-label">Funcionario</label><BFormSelect v-model="filters.staff_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))" /></div>
        <div class="col-md-2"><label class="form-label">Curso</label><BFormSelect v-model="filters.course_section_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.courses || []).map((item) => ({ value: item.id, text: item.display_name })))" /></div>
        <div class="col-md-2"><label class="form-label">Desde</label><BFormInput v-model="filters.date_from" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Hasta</label><BFormInput v-model="filters.date_to" type="date" /></div>
        <div class="col-md-3 d-flex align-items-center"><BFormCheckbox v-model="filters.overdue_only">Solo mora</BFormCheckbox></div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="filters = { search: '', status: null, student_profile_id: null, staff_id: null, course_section_id: null, overdue_only: false, date_from: '', date_to: '' }; load();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <section class="loan-register">
      <header class="loan-register__head">
        <div class="loan-register__title">
          <span><i class="bx bx-transfer-alt"></i></span>
          <div>
            <small>REGISTRO DE CIRCULACIÓN</small>
            <h5>{{ pagination.total }} préstamo{{ pagination.total === 1 ? "" : "s" }}</h5>
            <p>Seguimiento de entrega, vencimiento y devolución por ejemplar.</p>
          </div>
        </div>
        <div class="loan-register__legend">
          <span><i class="legend-dot legend-dot--ok"></i> Vigente</span>
          <span><i class="legend-dot legend-dot--warning"></i> Próximo</span>
          <span><i class="legend-dot legend-dot--danger"></i> Vencido</span>
        </div>
      </header>

      <LoadingState v-if="loading" message="Cargando préstamos..." compact />

      <div v-else-if="!items.length" class="loan-empty">
        <span><i class="bx bx-book-open"></i></span>
        <h5>No hay préstamos para mostrar</h5>
        <p>Modifica los filtros o registra una nueva entrega.</p>
        <button type="button" data-cnsc-action-ignore @click="openCreate"><i class="bx bx-plus"></i> Nuevo préstamo</button>
      </div>

      <div v-else class="loan-table-wrap">
        <table class="loan-table">
          <thead>
            <tr>
              <th>Préstamo</th>
              <th>Solicitante</th>
              <th>Libro entregado</th>
              <th>Vencimiento</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id" :class="{ 'loan-row--overdue': item.status === 'vencido' }">
              <td data-label="Préstamo">
                <div class="loan-code-cell">
                  <span><i class="bx bx-receipt"></i></span>
                  <div>
                    <code>{{ item.loan_code }}</code>
                    <small>Prestado {{ formatLibraryDate(item.borrowed_at) }}</small>
                  </div>
                </div>
              </td>
              <td data-label="Solicitante">
                <div class="loan-borrower-cell">
                  <span class="borrower-avatar">{{ borrowerInitials(item.borrower_name_snapshot) }}</span>
                  <div>
                    <strong>{{ item.borrower_name_snapshot }}</strong>
                    <small>{{ item.borrower_rut_snapshot || "Sin RUT registrado" }}</small>
                    <div>
                      <em>{{ borrowerTypeLabel(item) }}</em>
                      <span v-if="item.course_name_snapshot">{{ item.course_name_snapshot }}</span>
                    </div>
                  </div>
                </div>
              </td>
              <td data-label="Libro entregado">
                <div class="loan-book-cell">
                  <span class="loan-book-cover">
                    <img
                      v-if="loanCoverAvailable(item)"
                      :src="item.obra.cover_image_url"
                      :alt="`Portada de ${item.obra?.title}`"
                      @error="markLoanCoverFailed(item)"
                    />
                    <i v-else class="bx bx-book-open"></i>
                  </span>
                  <div>
                    <strong>{{ item.obra?.title || "Obra no disponible" }}</strong>
                    <small>{{ item.obra?.main_author || "Autor no informado" }}</small>
                    <code>{{ item.ejemplar?.code || "Sin código" }}</code>
                  </div>
                </div>
              </td>
              <td data-label="Vencimiento">
                <div class="loan-due-cell" :class="`loan-due-cell--${item.status}`">
                  <span><i class="bx" :class="item.status === 'vencido' ? 'bx-error-circle' : item.status === 'devuelto' ? 'bx-check-circle' : 'bx-calendar'"></i></span>
                  <div>
                    <strong>{{ formatLibraryDate(item.due_at) }}</strong>
                    <small v-if="item.overdue_days">{{ item.overdue_days }} día(s) de atraso</small>
                    <small v-else-if="item.status === 'devuelto'">Entrega completada</small>
                    <small v-else>Fecha comprometida</small>
                  </div>
                </div>
              </td>
              <td data-label="Estado">
                <div class="loan-status-cell">
                  <LibraryStatusBadge :status="item.status" />
                  <small v-if="item.renewed_count">{{ item.renewed_count }} renovación(es)</small>
                  <small v-else>Sin renovaciones</small>
                </div>
              </td>
              <td data-label="Acciones">
                <div class="loan-row-actions">
                  <button
                    v-if="isLoanOpen(item)"
                    type="button"
                    class="loan-row-action loan-row-action--edit"
                    data-cnsc-action-ignore
                    :aria-label="`Editar ${item.loan_code}`"
                    title="Editar ficha"
                    @click="openEdit(item)"
                  ><i class="bx bx-edit-alt"></i><span>Editar</span></button>
                  <button
                    type="button"
                    class="loan-row-action loan-row-action--file"
                    data-cnsc-action-ignore
                    :aria-label="`Descargar ficha ${item.loan_code}`"
                    title="Descargar ficha"
                    @click="exportLoan(item)"
                  ><i class="bx bx-download"></i><span>Ficha</span></button>
                  <button
                    v-if="isLoanOpen(item)"
                    type="button"
                    class="loan-row-action loan-row-action--renew"
                    data-cnsc-action-ignore
                    :aria-label="`Renovar ${item.loan_code}`"
                    title="Renovar préstamo"
                    @click="renew(item)"
                  ><i class="bx bx-revision"></i><span>Renovar</span></button>
                  <button
                    v-if="isLoanOpen(item)"
                    type="button"
                    class="loan-row-action loan-row-action--cancel"
                    data-cnsc-action-ignore
                    :aria-label="`Cancelar ${item.loan_code}`"
                    title="Cancelar préstamo"
                    @click="cancel(item)"
                  ><i class="bx bx-x"></i><span class="visually-hidden">Cancelar</span></button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="pagination.total" class="loan-register__footer">
        <span>Mostrando {{ loanResultRange }} préstamos</span>
        <BPagination
          v-if="pagination.total > pagination.per_page"
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="load"
        />
      </footer>
    </section>

    <BModal v-model="showModal" size="xl" title="Nuevo préstamo" hide-footer scrollable>
      <div class="loan-form-head">
        <span><i class="bx bx-book-open"></i></span>
        <div>
          <small>ENTREGA CONTROLADA</small>
          <h5>Registrar préstamo de biblioteca</h5>
          <p>Selecciona al solicitante y un ejemplar disponible. El sistema validará automáticamente disponibilidad y mora.</p>
        </div>
        <LibraryHelpButton
          title="Ayuda: nuevo préstamo"
          text="Selecciona el tipo de usuario, el ejemplar y la fecha comprometida de devolución. El sistema validará disponibilidad y mora existente."
        />
      </div>

      <section class="loan-form-section">
        <header>
          <span>1</span>
          <div><small>SOLICITANTE</small><h6>¿A quién se entrega el recurso?</h6></div>
        </header>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tipo de usuario</label>
            <BFormSelect
              :model-value="form.borrower_type"
              :options="(catalogs.borrower_types || []).map((item) => ({ value: item.value, text: item.label }))"
              @update:model-value="changeBorrowerType"
            />
            <small class="form-hint">Filtra estudiantes, funcionarios, docentes, apoderados o cursos.</small>
          </div>
          <div class="col-md-8">
            <label class="form-label">Buscar solicitante</label>
            <div class="exemplar-search borrower-search">
              <i class="bx bx-search"></i>
              <BFormInput
                :model-value="borrowerSearch"
                list="loan-borrower-options"
                autocomplete="off"
                :placeholder="borrowerSearchPlaceholder()"
                @update:model-value="matchBorrower"
              />
              <button v-if="borrowerSearch" type="button" aria-label="Limpiar solicitante" @click="clearBorrowerSelection()"><i class="bx bx-x"></i></button>
            </div>
            <datalist id="loan-borrower-options">
              <option
                v-for="item in borrowerMatches"
                :key="`${form.borrower_type}-${item.value}`"
                :value="item.text"
              />
            </datalist>
            <small v-if="selectedBorrower" class="form-hint form-hint--selected">
              <i class="bx bx-check-circle"></i> Solicitante seleccionado: {{ selectedBorrowerLabel }}
            </small>
            <small v-else class="form-hint">
              Escribe para ver coincidencias · {{ borrowerOptions().length }} registro(s) disponibles.
            </small>
          </div>
        </div>
        <BAlert v-if="isEarlyChildhoodLoan" show variant="info" class="early-alert">
          <div>
            <i class="bx bx-info-circle me-1"></i>
            <strong>{{ selectedStudent?.level }}:</strong> el préstamo debe quedar retirado y firmado por su apoderado.
          </div>
        </BAlert>
      </section>

      <section class="loan-form-section">
        <header>
          <span>2</span>
          <div><small>LIBRO Y PLAZO</small><h6>Ejemplar disponible y fechas</h6></div>
        </header>
        <div class="row g-3 align-items-start">
          <div class="col-md-8">
            <label class="form-label">Buscar ejemplar</label>
            <div class="exemplar-search">
              <i class="bx bx-search"></i>
              <BFormInput
                :model-value="exemplarSearch"
                list="loan-exemplar-options"
                autocomplete="off"
                placeholder="Escribe ISBN, título, autor o código del ejemplar"
                @update:model-value="matchExemplar"
              />
              <button v-if="exemplarSearch" type="button" aria-label="Limpiar ejemplar" @click="clearExemplar"><i class="bx bx-x"></i></button>
            </div>
            <datalist id="loan-exemplar-options">
              <option v-for="item in availableExemplarOptions" :key="item.id" :value="item.searchLabel">{{ item.code }}</option>
            </datalist>
            <small class="form-hint">{{ availableExemplarOptions.length }} ejemplar(es) disponibles. Las opciones muestran ISBN, título, autor y código.</small>
          </div>
          <div class="col-md-2"><label class="form-label">Fecha préstamo</label><BFormInput v-model="form.borrowed_at" type="date" /></div>
          <div class="col-md-2"><label class="form-label">Fecha devolución</label><BFormInput v-model="form.due_at" type="date" /></div>
        </div>

        <div v-if="selectedExemplar" class="selected-exemplar">
          <span><i class="bx bx-book-open"></i></span>
          <div>
            <small>EJEMPLAR SELECCIONADO</small>
            <strong>{{ selectedExemplar.title }}</strong>
            <p>{{ selectedExemplar.main_author || "Autor no informado" }} · {{ selectedExemplar.isbn ? `ISBN ${selectedExemplar.isbn}` : "Sin ISBN" }}</p>
          </div>
          <code>{{ selectedExemplar.code }}</code>
          <em><i class="bx bx-check-circle"></i> Disponible</em>
        </div>
      </section>

      <section class="loan-form-section">
        <header class="pickup-section-head">
          <span>3</span>
          <div><small>RETIRO Y FIRMA</small><h6>Persona que recibe el ejemplar</h6></div>
          <BFormCheckbox
            :model-value="form.same_as_borrower"
            :disabled="!canRepeatBorrower"
            class="same-borrower-check"
            @update:model-value="toggleSameAsBorrower"
          >
            Usar los datos del solicitante
          </BFormCheckbox>
        </header>
        <div v-if="form.borrower_type === 'course'" class="pickup-guidance">
          <i class="bx bx-info-circle"></i> Para préstamos a un curso, registra al docente o funcionario responsable del retiro.
        </div>
        <div v-else-if="isEarlyChildhoodLoan" class="pickup-guidance pickup-guidance--guardian">
          <i class="bx bx-user-check"></i> Los datos del apoderado se completaron desde la ficha de la estudiante.
        </div>

        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Tipo</label><BFormSelect v-model="form.pickup_person_type" :options="[{value:'student',text:'Estudiante'},{value:'guardian',text:'Apoderado/a'},{value:'teacher',text:'Docente'},{value:'staff',text:'Funcionario/a'},{value:'other',text:'Otra persona'}]" /></div>
          <div class="col-md-3"><label class="form-label">Nombre completo</label><BFormInput v-model="form.pickup_person_name" /></div>
          <div class="col-md-2"><label class="form-label">RUT</label><BFormInput v-model="form.pickup_person_rut" /></div>
          <div class="col-md-2"><label class="form-label">Correo</label><BFormInput v-model="form.pickup_person_email" type="email" /></div>
          <div class="col-md-2"><label class="form-label">Relación</label><BFormInput v-model="form.pickup_person_relationship" placeholder="Madre, padre..." /></div>
          <div class="col-md-4"><label class="form-label">Nombre en firma</label><BFormInput v-model="form.signature_name" /></div>
          <div class="col-md-3"><label class="form-label">RUT de firma</label><BFormInput v-model="form.signature_rut" /></div>
          <div class="col-md-5"><label class="form-label">Detalle de la entrega</label><BFormInput v-model="form.delivery_notes" placeholder="Condición, observaciones o acuerdo" /></div>
          <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.notes" rows="2" placeholder="Información adicional del préstamo" /></div>
        </div>
      </section>

      <div class="loan-form-actions">
        <button type="button" class="loan-form-cancel" data-cnsc-action-ignore @click="closeModal">Cancelar</button>
        <button type="button" class="loan-form-save" data-cnsc-action-ignore :disabled="saving || !canSave" @click="save">
          <span v-if="saving" class="spinner-border spinner-border-sm"></span>
          <i v-else class="bx bx-check-circle"></i>
          {{ saving ? "Guardando..." : "Registrar préstamo" }}
        </button>
      </div>
    </BModal>

    <BModal v-model="showEditModal" v-if="selectedLoan" size="xl" title="Editar ficha de préstamo" hide-footer scrollable>
      <div class="edit-summary">
        <div><span>{{ selectedLoan.loan_code }}</span><strong>{{ selectedLoan.borrower_name_snapshot }}</strong><small>{{ selectedLoan.borrower_rut_snapshot || "Sin RUT" }} · {{ selectedLoan.course_name_snapshot || selectedLoan.borrower_estate }}</small></div>
        <div><span>Libro</span><strong>{{ selectedLoan.obra?.title }}</strong><small>{{ selectedLoan.ejemplar?.code }}</small></div>
        <BButton variant="outline-primary" size="sm" @click="exportLoan(selectedLoan)"><i class="bx bx-download me-1"></i>Exportar ficha</BButton>
      </div>

      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Fecha comprometida de entrega</label><BFormInput v-model="editForm.due_at" type="date" /></div>
        <div class="col-md-3"><label class="form-label">Tipo de retiro</label><BFormSelect v-model="editForm.pickup_person_type" :options="[{value:'student',text:'Estudiante'},{value:'guardian',text:'Apoderado/a'},{value:'teacher',text:'Docente'},{value:'staff',text:'Funcionario/a'},{value:'other',text:'Otra persona'}]" /></div>
        <div class="col-md-3"><label class="form-label">Persona que retira</label><BFormInput v-model="editForm.pickup_person_name" /></div>
        <div class="col-md-3"><label class="form-label">RUT retiro</label><BFormInput v-model="editForm.pickup_person_rut" /></div>
        <div class="col-md-4"><label class="form-label">Correo</label><BFormInput v-model="editForm.pickup_person_email" type="email" /></div>
        <div class="col-md-4"><label class="form-label">Relación</label><BFormInput v-model="editForm.pickup_person_relationship" /></div>
        <div class="col-md-4"><label class="form-label">Detalle de entrega</label><BFormInput v-model="editForm.delivery_notes" /></div>
        <div class="col-md-4"><label class="form-label">Nombre de firma</label><BFormInput v-model="editForm.signature_name" /></div>
        <div class="col-md-4"><label class="form-label">RUT de firma</label><BFormInput v-model="editForm.signature_rut" /></div>
        <div class="col-md-4"><label class="form-label">Estado actual</label><div class="pt-2"><LibraryStatusBadge :status="selectedLoan.status" /></div></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="editForm.notes" rows="2" /></div>
      </div>

      <div class="return-panel mt-4">
        <BFormCheckbox v-model="editForm.register_return">
          <strong>Registrar la devolución al guardar esta ficha</strong>
        </BFormCheckbox>
        <div v-if="editForm.register_return" class="mt-3">
          <label class="form-label">Condición del ejemplar devuelto</label>
          <BFormSelect v-model="editForm.returned_condition" :options="[{value:'bueno',text:'Bueno'},{value:'regular',text:'Regular'},{value:'danado',text:'Dañado'},{value:'perdido',text:'Perdido'}]" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="showEditModal=false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="saveEdit">{{ saving ? "Guardando..." : editForm.register_return ? "Guardar y devolver" : "Actualizar ficha" }}</BButton>
      </div>
    </BModal>
  </div>
</template>

<style scoped>
.loan-view{display:flex;flex-direction:column;gap:1rem}.loan-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.35rem 1.5rem;color:#fff;border-radius:19px;background:linear-gradient(135deg,#17365d,#285f9f 58%,#198c80);box-shadow:0 16px 36px rgba(26,66,111,.18)}.loan-head>div:first-child>span{font-size:.66rem;font-weight:800;letter-spacing:.15em;color:#a9e4dc}.loan-head h5{margin:.25rem 0;color:#fff}.loan-head p{margin:0;color:rgba(255,255,255,.72)}.head-action{color:#214e79!important;font-weight:700}.rule-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}.rule-grid article{display:flex;align-items:center;gap:.75rem;padding:.9rem 1rem;border:1px solid #e5eaf2;border-radius:14px;background:#fff}.rule-grid i{display:grid;place-items:center;width:40px;height:40px;flex:0 0 40px;color:#416bd5;border-radius:12px;background:#edf2ff;font-size:1.25rem}.rule-grid article:nth-child(2) i{color:#148878;background:#e8f8f4}.rule-grid article:nth-child(3) i{color:#d79229;background:#fff5df}.rule-grid div{display:flex;flex-direction:column}.rule-grid span{font-size:.7rem;color:#7e8999}.form-section-title{display:flex;align-items:center;gap:.5rem;padding:.65rem .8rem;color:#365fbd;border-radius:10px;background:#f0f4ff;font-weight:750}.edit-summary{display:grid;grid-template-columns:1fr 1fr auto;gap:1rem;align-items:center;padding:1rem;margin-bottom:1rem;border-radius:14px;background:#f5f8fc}.edit-summary>div{display:flex;flex-direction:column}.edit-summary span,.edit-summary small{font-size:.69rem;color:#78869a}.return-panel{padding:1rem;border:1px solid #f0d79a;border-radius:13px;background:#fffaf0}

.loan-register {
  overflow: hidden;
  border: 1px solid #dfe6f1;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 12px 30px rgba(42, 59, 90, .07);
}

.loan-register__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.15rem;
  border-bottom: 1px solid #e5eaf2;
  background: linear-gradient(135deg, #fbfcff, #f5f9f8);
}

.loan-register__title {
  display: flex;
  align-items: center;
  gap: .72rem;
}

.loan-register__title > span {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: 12px;
  color: #4b67d5;
  background: #e9eeff;
  font-size: 1.18rem;
}

.loan-register__title small {
  color: #7b89a0;
  font-size: .59rem;
  font-weight: 800;
  letter-spacing: .11em;
}

.loan-register__title h5 {
  margin: .08rem 0;
  color: #2d3d55;
  font-size: .9rem;
}

.loan-register__title p {
  margin: 0;
  color: #8591a2;
  font-size: .65rem;
}

.loan-register__legend {
  display: flex;
  align-items: center;
  gap: .85rem;
  color: #78869a;
  font-size: .62rem;
}

.loan-register__legend > span {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
}

.legend-dot {
  width: 7px;
  height: 7px;
  display: inline-block;
  border-radius: 50%;
}

.legend-dot--ok { background: #31b987; }
.legend-dot--warning { background: #e7ab3b; }
.legend-dot--danger { background: #e65d6f; }

.loan-table-wrap {
  overflow-x: auto;
}

.loan-table {
  width: 100%;
  min-width: 1110px;
  border-collapse: separate;
  border-spacing: 0;
}

.loan-table th {
  padding: .72rem .8rem;
  border-bottom: 1px solid #e6ebf3;
  color: #7a8699;
  background: #fafbfe;
  font-size: .59rem;
  font-weight: 800;
  letter-spacing: .09em;
  text-transform: uppercase;
  white-space: nowrap;
}

.loan-table th:first-child,
.loan-table td:first-child {
  padding-left: 1.15rem;
}

.loan-table th:last-child,
.loan-table td:last-child {
  padding-right: 1.15rem;
}

.loan-table td {
  padding: .85rem .8rem;
  border-bottom: 1px solid #edf0f5;
  vertical-align: middle;
  background: #fff;
  transition: background .15s ease;
}

.loan-table tbody tr:last-child td {
  border-bottom: 0;
}

.loan-table tbody tr:hover td {
  background: #fbfcff;
}

.loan-row--overdue td {
  background: #fffafb;
}

.loan-row--overdue td:first-child {
  box-shadow: inset 3px 0 #e65d6f;
}

.loan-code-cell,
.loan-borrower-cell,
.loan-book-cell,
.loan-due-cell {
  display: flex;
  align-items: center;
  gap: .65rem;
}

.loan-code-cell > span {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  border-radius: 10px;
  color: #516bd2;
  background: #edf1ff;
  font-size: .95rem;
}

.loan-code-cell code {
  display: block;
  color: #3c4d66;
  font-size: .68rem;
  font-weight: 800;
  white-space: nowrap;
}

.loan-code-cell small,
.loan-borrower-cell small,
.loan-book-cell small,
.loan-due-cell small,
.loan-status-cell small {
  display: block;
  margin-top: .13rem;
  color: #8a95a5;
  font-size: .59rem;
}

.borrower-avatar {
  width: 39px;
  height: 39px;
  flex: 0 0 39px;
  display: grid;
  place-items: center;
  border: 1px solid #dbe4fb;
  border-radius: 12px;
  color: #4861bd;
  background: linear-gradient(135deg, #eef2ff, #e8f5f2);
  font-size: .68rem;
  font-weight: 850;
}

.loan-borrower-cell > div,
.loan-book-cell > div {
  min-width: 0;
}

.loan-borrower-cell strong,
.loan-book-cell strong {
  display: block;
  max-width: 220px;
  overflow: hidden;
  color: #34445b;
  font-size: .7rem;
  font-weight: 750;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.loan-borrower-cell > div > div {
  display: flex;
  align-items: center;
  gap: .35rem;
  margin-top: .28rem;
}

.loan-borrower-cell em,
.loan-borrower-cell > div > div > span {
  padding: .18rem .38rem;
  border-radius: 6px;
  font-size: .54rem;
  font-style: normal;
  font-weight: 750;
  white-space: nowrap;
}

.loan-borrower-cell em {
  color: #4761bc;
  background: #edf1ff;
}

.loan-borrower-cell > div > div > span {
  color: #337763;
  background: #ebf7f3;
}

.loan-book-cover {
  width: 38px;
  height: 50px;
  flex: 0 0 38px;
  overflow: hidden;
  display: grid;
  place-items: center;
  border-radius: 7px;
  color: #5c72c4;
  background: linear-gradient(145deg, #e8edff, #eff7f4);
  box-shadow: 0 4px 10px rgba(50, 68, 104, .12);
}

.loan-book-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.loan-book-cover i {
  font-size: 1rem;
}

.loan-book-cell code {
  display: inline-block;
  margin-top: .28rem;
  padding: .16rem .35rem;
  border-radius: 5px;
  color: #67748a;
  background: #f1f3f7;
  font-size: .55rem;
}

.loan-due-cell > span {
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  color: #397b67;
  background: #e9f7f2;
  font-size: .9rem;
}

.loan-due-cell strong {
  display: block;
  color: #3c4b61;
  font-size: .7rem;
  white-space: nowrap;
}

.loan-due-cell--vencido > span {
  color: #c9475a;
  background: #ffeaed;
}

.loan-due-cell--vencido strong,
.loan-due-cell--vencido small {
  color: #c9475a;
}

.loan-due-cell--renovado > span {
  color: #b17a1e;
  background: #fff3dc;
}

.loan-status-cell {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.loan-status-cell :deep(.badge) {
  padding: .38rem .52rem;
  border-radius: 7px;
  font-size: .55rem;
  letter-spacing: .03em;
}

.loan-row-actions {
  display: flex;
  justify-content: flex-end;
  gap: .35rem;
  min-width: 236px;
}

.loan-row-action {
  min-height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .3rem;
  padding: .36rem .55rem;
  border-radius: 8px !important;
  font-size: .61rem;
  font-weight: 750;
  white-space: nowrap;
  transition: transform .15s ease, border-color .15s ease, background .15s ease;
}

.loan-row-action:hover {
  transform: translateY(-1px);
}

.loan-row-action i {
  font-size: .85rem;
}

.loan-row-action--edit {
  border: 1px solid #4865d4 !important;
  color: #fff;
  background: #516ddb;
}

.loan-row-action--file {
  border: 1px solid #dce3ef !important;
  color: #55657c;
  background: #fff;
}

.loan-row-action--renew {
  border: 1px solid #c9e7dd !important;
  color: #287c65;
  background: #edf8f4;
}

.loan-row-action--cancel {
  width: 32px;
  padding: 0;
  border: 1px solid #f0cbd1 !important;
  color: #cf5362;
  background: #fff6f7;
}

.loan-register__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  min-height: 54px;
  padding: .65rem 1.15rem;
  border-top: 1px solid #e9edf3;
  color: #7e8a9c;
  background: #fbfcfe;
  font-size: .62rem;
}

.loan-register__footer :deep(.pagination) {
  margin: 0;
}

.loan-empty {
  padding: 2.7rem 1rem;
  text-align: center;
}

.loan-empty > span {
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  margin: 0 auto .75rem;
  border-radius: 16px;
  color: #5069c8;
  background: #edf1ff;
  font-size: 1.5rem;
}

.loan-empty h5 {
  margin: 0;
  color: #35455c;
  font-size: .85rem;
}

.loan-empty p {
  margin: .25rem 0 .8rem;
  color: #8792a3;
  font-size: .66rem;
}

.loan-empty button {
  min-height: 36px;
  padding: .45rem .8rem;
  border: 0 !important;
  border-radius: 9px !important;
  color: #fff;
  background: #516ddb;
  font-size: .65rem;
  font-weight: 750;
}

.loan-form-head {
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) auto;
  align-items: center;
  gap: .85rem;
  padding: .85rem 1rem;
  margin-bottom: .85rem;
  border: 1px solid #dfe6f2;
  border-radius: 14px;
  background: linear-gradient(135deg, #f5f8ff 0%, #f4faf8 100%);
}

.loan-form-head > span {
  width: 46px;
  height: 46px;
  display: grid;
  place-items: center;
  border-radius: 13px;
  color: #4664d3;
  background: #e6ecff;
  font-size: 1.35rem;
}

.loan-form-head small,
.loan-form-section header small,
.selected-exemplar small {
  color: #7084c2;
  font-size: .6rem;
  font-weight: 800;
  letter-spacing: .11em;
}

.loan-form-head h5 {
  margin: .12rem 0;
  color: #2d3d55;
  font-size: .95rem;
}

.loan-form-head p {
  margin: 0;
  color: #7b8799;
  font-size: .7rem;
}

.loan-form-section {
  padding: .9rem 1rem 1rem;
  margin-top: .7rem;
  border: 1px solid #e3e8f1;
  border-radius: 14px;
  background: #fff;
}

.loan-form-section > header {
  display: flex;
  align-items: center;
  gap: .65rem;
  margin-bottom: .8rem;
}

.loan-form-section > header > span {
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  color: #4664d3;
  background: #eaf0ff;
  font-size: .68rem;
  font-weight: 800;
}

.loan-form-section header h6 {
  margin: .06rem 0 0;
  color: #33435a;
  font-size: .79rem;
}

.loan-form-section :deep(.form-control),
.loan-form-section :deep(.form-select) {
  min-height: 42px;
  border-color: #dfe5ef;
  border-radius: 10px;
  color: #3c4b61;
  font-size: .72rem;
  box-shadow: none;
}

.loan-form-section :deep(.form-control:focus),
.loan-form-section :deep(.form-select:focus) {
  border-color: #8ea4f0;
  box-shadow: 0 0 0 3px rgba(76, 111, 255, .1);
}

.loan-form-section .form-label {
  margin-bottom: .34rem;
  color: #4a586c;
  font-size: .67rem;
  font-weight: 750;
}

.form-hint {
  display: block;
  margin-top: .28rem;
  color: #8b96a7;
  font-size: .61rem;
}

.form-hint--selected {
  display: flex;
  align-items: center;
  gap: .25rem;
  color: #238068;
  font-weight: 750;
}

.form-hint--selected i {
  font-size: .78rem;
}

.early-alert {
  margin: .75rem 0 0;
  padding: .65rem .75rem;
  border: 0;
  border-radius: 10px;
  font-size: .68rem;
}

.exemplar-search {
  position: relative;
}

.exemplar-search > .bx-search {
  position: absolute;
  z-index: 2;
  top: 50%;
  left: .78rem;
  transform: translateY(-50%);
  color: #71809a;
  font-size: 1rem;
}

.exemplar-search :deep(.form-control) {
  padding-left: 2.35rem;
  padding-right: 2.3rem;
}

.exemplar-search > button {
  position: absolute;
  z-index: 2;
  top: 50%;
  right: .55rem;
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  transform: translateY(-50%);
  border: 0 !important;
  border-radius: 8px !important;
  color: #71809a;
  background: #eef1f6;
}

.selected-exemplar {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr) auto auto;
  align-items: center;
  gap: .75rem;
  padding: .7rem .8rem;
  margin-top: .75rem;
  border: 1px solid #cfeadd;
  border-radius: 12px;
  background: #f1faf6;
}

.selected-exemplar > span {
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border-radius: 11px;
  color: #22846a;
  background: #dff4eb;
  font-size: 1.1rem;
}

.selected-exemplar strong,
.selected-exemplar p {
  display: block;
  margin: 0;
}

.selected-exemplar strong {
  margin-top: .05rem;
  color: #33435a;
  font-size: .74rem;
}

.selected-exemplar p {
  margin-top: .1rem;
  color: #78869a;
  font-size: .63rem;
}

.selected-exemplar code {
  padding: .3rem .45rem;
  border-radius: 7px;
  color: #4a5f79;
  background: #fff;
  font-size: .65rem;
}

.selected-exemplar em {
  color: #218265;
  font-size: .64rem;
  font-style: normal;
  font-weight: 750;
}

.pickup-section-head {
  flex-wrap: wrap;
}

.same-borrower-check {
  margin-left: auto;
  padding: .45rem .7rem .45rem 2rem;
  border: 1px solid #dbe4f5;
  border-radius: 9px;
  color: #4c6288;
  background: #f5f8ff;
  font-size: .66rem;
  font-weight: 750;
}

.same-borrower-check :deep(.form-check-input) {
  margin-top: .08rem;
}

.pickup-guidance {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .55rem .65rem;
  margin: -.15rem 0 .75rem;
  border-radius: 9px;
  color: #596a83;
  background: #f3f6fb;
  font-size: .65rem;
}

.pickup-guidance--guardian {
  color: #28725d;
  background: #edf9f5;
}

.loan-form-actions {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: flex-end;
  gap: .55rem;
  padding-top: .9rem;
  margin-top: .2rem;
  background: linear-gradient(to bottom, rgba(255,255,255,0), #fff 24%);
}

.loan-form-cancel,
.loan-form-save {
  min-height: 39px;
  padding: .5rem 1rem;
  border-radius: 10px !important;
  font-size: .7rem;
  font-weight: 750;
}

.loan-form-cancel {
  border: 1px solid #dfe5ef !important;
  color: #647287;
  background: #fff;
}

.loan-form-save {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  border: 0 !important;
  color: #fff;
  background: linear-gradient(135deg, #4564d8, #6682e8);
  box-shadow: 0 8px 18px rgba(69, 100, 216, .2);
}

.loan-form-save:disabled {
  opacity: .48;
  box-shadow: none;
}

@media(max-width:900px){
  .loan-head{align-items:flex-start;flex-direction:column}
  .rule-grid{grid-template-columns:1fr}
  .edit-summary{grid-template-columns:1fr}
}

@media (min-width: 768px) and (max-width: 1500px) {
  .loan-table-wrap {
    overflow-x: hidden;
  }

  .loan-table {
    min-width: 0;
    table-layout: fixed;
  }

  .loan-table th:nth-child(1) { width: 15%; }
  .loan-table th:nth-child(2) { width: 22%; }
  .loan-table th:nth-child(3) { width: 21%; }
  .loan-table th:nth-child(4) { width: 14%; }
  .loan-table th:nth-child(5) { width: 10%; }
  .loan-table th:nth-child(6) { width: 18%; }

  .loan-table th,
  .loan-table td {
    padding-right: .5rem;
    padding-left: .5rem;
  }

  .loan-table th:first-child,
  .loan-table td:first-child {
    padding-left: .75rem;
  }

  .loan-table th:last-child,
  .loan-table td:last-child {
    padding-right: .75rem;
  }

  .loan-code-cell > span {
    display: none;
  }

  .loan-code-cell code {
    overflow: hidden;
    font-size: .6rem;
    text-overflow: ellipsis;
  }

  .loan-borrower-cell,
  .loan-book-cell,
  .loan-due-cell {
    gap: .42rem;
  }

  .borrower-avatar {
    width: 32px;
    height: 32px;
    flex-basis: 32px;
    border-radius: 9px;
    font-size: .6rem;
  }

  .loan-book-cover {
    width: 32px;
    height: 43px;
    flex-basis: 32px;
  }

  .loan-borrower-cell strong,
  .loan-book-cell strong {
    max-width: 100%;
    font-size: .64rem;
  }

  .loan-due-cell > span {
    width: 28px;
    height: 28px;
    flex-basis: 28px;
  }

  .loan-due-cell strong {
    font-size: .63rem;
  }

  .loan-row-actions {
    min-width: 0;
    gap: .25rem;
  }

  .loan-row-action {
    width: 30px;
    min-height: 30px;
    padding: 0;
  }

  .loan-row-action > span:not(.visually-hidden) {
    display: none;
  }

  .loan-row-action--cancel {
    width: 30px;
  }
}

@media(max-width:767px) {
  .loan-register {
    border-radius: 16px;
  }

  .loan-register__head {
    align-items: flex-start;
    flex-direction: column;
    padding: 1rem;
  }

  .loan-register__title > span {
    width: 38px;
    height: 38px;
    flex-basis: 38px;
  }

  .loan-register__legend {
    width: 100%;
    justify-content: space-between;
    gap: .35rem;
    padding-top: .7rem;
    border-top: 1px solid #e5e9f1;
  }

  .loan-table-wrap {
    overflow: visible;
    padding: .7rem;
    background: #f6f8fc;
  }

  .loan-table {
    display: block;
    min-width: 0;
  }

  .loan-table thead {
    display: none;
  }

  .loan-table tbody {
    display: grid;
    gap: .7rem;
  }

  .loan-table tbody tr,
  .loan-table tbody tr:hover {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    padding: .72rem .8rem;
    border: 1px solid #e0e6ef;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 7px 18px rgba(52, 67, 95, .06);
  }

  .loan-table tbody tr.loan-row--overdue {
    border-color: #f0cbd1;
    background: #fffafb;
  }

  .loan-table td,
  .loan-table td:first-child,
  .loan-table td:last-child {
    display: block;
    width: 100%;
    padding: .62rem 0;
    border-bottom: 1px solid #edf0f5;
    background: transparent;
  }

  .loan-table td::before {
    content: attr(data-label);
    display: block;
    margin-bottom: .38rem;
    color: #96a0af;
    font-size: .52rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
  }

  .loan-table td:last-child {
    padding-bottom: .1rem;
    border-bottom: 0;
  }

  .loan-row-actions {
    min-width: 0;
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .loan-register__footer {
    align-items: flex-start;
    flex-direction: column;
    padding: .85rem 1rem;
  }

  .loan-form-head {
    grid-template-columns: 42px minmax(0, 1fr);
  }

  .loan-form-head > span {
    width: 40px;
    height: 40px;
  }

  .loan-form-head > :last-child {
    grid-column: 1 / -1;
    justify-self: start;
  }

  .same-borrower-check {
    width: 100%;
    margin-left: 0;
  }

  .selected-exemplar {
    grid-template-columns: 38px minmax(0, 1fr);
  }

  .selected-exemplar code,
  .selected-exemplar em {
    grid-column: 2;
    justify-self: start;
  }

  .loan-form-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }
}
</style>
