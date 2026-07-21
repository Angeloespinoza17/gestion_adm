<script>
import axios from "axios";
import Swal from "sweetalert2";
import InformaticaHelpButton from "../help-button.vue";
import InformaticaStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmInformaticaAction,
  confirmInformaticaCancel,
  formatInformaticaDate,
  formatInformaticaDateTime,
  formatInformaticaError,
  humanizeInformaticaStatus,
  normalizeOptions,
  showInformaticaError,
  showInformaticaSuccess,
  showInformaticaWarning,
  toInputDateTime,
} from "../module-utils";

const emptyForm = () => ({
  it_equipment_id: null,
  requester_type: "funcionario",
  requester_user_id: null,
  requester_staff_id: null,
  requester_student_profile_id: null,
  requester_name: "",
  requester_rut: "",
  requester_contact: "",
  borrowed_at: toInputDateTime(),
  due_at: toInputDateTime(new Date(Date.now() + 3 * 86400000)),
  purpose: "",
  location_name: "",
  delivered_by_user_id: null,
  notes: "",
  attachment: null,
});

const emptyReturnForm = () => ({
  returned_at: toInputDateTime(),
  received_by_user_id: null,
  return_condition: "bueno",
  post_return_status: null,
  return_notes: "",
  attachment: null,
});

export default {
  components: {
    InformaticaHelpButton,
    InformaticaStatusBadge,
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
      returning: false,
      error: null,
      items: [],
      summary: {
        active: 0,
        overdue: 0,
        returned_month: 0,
        cancelled_month: 0,
      },
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        status: null,
        it_equipment_id: null,
        requester_type: null,
        delivered_by_user_id: null,
        date_from: "",
        date_to: "",
        due_date_from: "",
        due_date_to: "",
        only_overdue: false,
        only_active: false,
      },
      showCreateModal: false,
      showDetailModal: false,
      showReturnModal: false,
      form: emptyForm(),
      returnForm: emptyReturnForm(),
      selectedLoan: null,
    };
  },
  computed: {
    capabilities() {
      return this.catalogs.capabilities || {};
    },
    loanStatusOptions() {
      return normalizeOptions(this.catalogs.loan_statuses || [], true).map((item) => ({ value: item.value, text: item.label }));
    },
    requesterTypeOptions() {
      return normalizeOptions(this.catalogs.requester_types || [], true).map((item) => ({ value: item.value, text: item.label }));
    },
    returnConditionOptions() {
      return normalizeOptions(this.catalogs.return_conditions || []).map((item) => ({ value: item.value, text: item.label }));
    },
    userOptions() {
      return [{ value: null, text: "Seleccionar usuario" }].concat(
        (this.catalogs.users || []).map((item) => ({ value: item.id, text: item.name }))
      );
    },
    staffOptions() {
      return [{ value: null, text: "Seleccionar funcionario" }].concat(
        (this.catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name }))
      );
    },
    studentOptions() {
      return [{ value: null, text: "Seleccionar estudiante" }].concat(
        (this.catalogs.students || []).map((item) => ({ value: item.id, text: `${item.name}${item.course ? ` · ${item.course}` : ""}` }))
      );
    },
    availableEquipmentOptions() {
      return [{ value: null, text: "Seleccionar equipo disponible" }].concat(
        (this.catalogs.equipment || [])
          .filter((item) => item.status === "disponible" && item.active !== false)
          .map((item) => ({
            value: item.id,
            text: `${item.internal_code} · ${humanizeInformaticaStatus(item.equipment_type)} · ${[item.brand, item.model].filter(Boolean).join(" ")}`,
          }))
      );
    },
    equipmentFilterOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.equipment || []).map((item) => ({
          value: item.id,
          text: `${item.internal_code} · ${[item.brand, item.model].filter(Boolean).join(" ")}`,
        }))
      );
    },
    summaryCards() {
      return [
        { label: "Activos", value: this.summary.active, help: "Préstamos abiertos, incluidos los atrasados." },
        { label: "Atrasados", value: this.summary.overdue, help: "Préstamos cuya devolución comprometida ya venció." },
        { label: "Devueltos del mes", value: this.summary.returned_month, help: "Préstamos marcados como devueltos durante el mes actual." },
        { label: "Cancelados del mes", value: this.summary.cancelled_month, help: "Préstamos cancelados durante el mes actual." },
      ];
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatInformaticaDate,
    formatInformaticaDateTime,
    humanizeInformaticaStatus,
    normalizeOptions,
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/informatica/prestamos", {
          params: {
            page,
            ...this.filters,
            only_overdue: this.filters.only_overdue ? 1 : 0,
            only_active: this.filters.only_active ? 1 : 0,
          },
        });
        this.items = response.data.items.data || [];
        this.summary = response.data.summary || this.summary;
        this.pagination = {
          current_page: response.data.items.current_page,
          total: response.data.items.total,
          per_page: response.data.items.per_page,
        };
      } catch (error) {
        this.handleError(error, "No se pudieron cargar los préstamos.");
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = {
        search: "",
        status: null,
        it_equipment_id: null,
        requester_type: null,
        delivered_by_user_id: null,
        date_from: "",
        date_to: "",
        due_date_from: "",
        due_date_to: "",
        only_overdue: false,
        only_active: false,
      };
      this.load();
    },
    openCreate() {
      this.form = emptyForm();
      this.showCreateModal = true;
    },
    onAttachmentSelected(event, target = "form") {
      const file = event.target.files?.[0] || null;
      if (target === "return") {
        this.returnForm.attachment = file;
      } else {
        this.form.attachment = file;
      }
    },
    clearCreateAttachment() {
      this.form.attachment = null;
      if (this.$refs.createAttachmentInput) this.$refs.createAttachmentInput.value = "";
    },
    formatFileSize(bytes) {
      if (!bytes) return "0 KB";
      if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;
      return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    },
    buildCreateFormData() {
      const payload = new FormData();
      [
        "it_equipment_id",
        "requester_type",
        "requester_user_id",
        "requester_staff_id",
        "requester_student_profile_id",
        "requester_name",
        "requester_rut",
        "requester_contact",
        "borrowed_at",
        "due_at",
        "purpose",
        "location_name",
        "delivered_by_user_id",
        "notes",
      ].forEach((key) => {
        const value = this.form[key];
        if (value !== null && value !== undefined && value !== "") {
          payload.append(key, value);
        }
      });

      if (this.form.attachment) {
        payload.append("attachment", this.form.attachment);
      }

      return payload;
    },
    buildReturnFormData() {
      const payload = new FormData();
      [
        "returned_at",
        "received_by_user_id",
        "return_condition",
        "post_return_status",
        "return_notes",
      ].forEach((key) => {
        const value = this.returnForm[key];
        if (value !== null && value !== undefined && value !== "") {
          payload.append(key, value);
        }
      });

      if (this.returnForm.attachment) {
        payload.append("attachment", this.returnForm.attachment);
      }

      return payload;
    },
    async save() {
      const available = (this.catalogs.equipment || []).find((item) => item.id === this.form.it_equipment_id);
      if (available && available.status !== "disponible") {
        await showInformaticaWarning("El equipo seleccionado no está disponible para préstamo.");
        return;
      }

      const confirmation = await confirmInformaticaAction({
        title: "Confirmar creación del préstamo",
        text: "Se registrará el préstamo y el equipo cambiará automáticamente a estado prestado.",
        confirmButtonText: "Sí, registrar",
      });

      if (!confirmation.isConfirmed) return;

      this.saving = true;
      try {
        const response = await axios.post("/api/informatica/prestamos", this.buildCreateFormData(), {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.showCreateModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showInformaticaSuccess(response.data.message || "Préstamo registrado correctamente.");
      } catch (error) {
        this.handleError(error);
      } finally {
        this.saving = false;
      }
    },
    async openDetail(item) {
      try {
        const response = await axios.get(`/api/informatica/prestamos/${item.id}`);
        this.selectedLoan = response.data.data;
        this.showDetailModal = true;
      } catch (error) {
        this.handleError(error, "No se pudo cargar el detalle del préstamo.");
      }
    },
    async openReturn(item) {
      try {
        const response = await axios.get(`/api/informatica/prestamos/${item.id}`);
        this.selectedLoan = response.data.data;
        this.returnForm = {
          ...emptyReturnForm(),
          returned_at: toInputDateTime(),
        };
        this.showReturnModal = true;
      } catch (error) {
        this.handleError(error, "No se pudo abrir el formulario de devolución.");
      }
    },
    async registerReturn() {
      if (!this.selectedLoan) return;

      if (this.returnForm.return_condition === "danado") {
        await showInformaticaWarning("El equipo se devolverá como dañado y el estado final sugerido será dañado o en mantención.");
      }

      const confirmation = await confirmInformaticaAction({
        title: "Confirmar devolución",
        text: "Se registrará la devolución y el estado del equipo se actualizará automáticamente.",
        confirmButtonText: "Sí, devolver",
      });

      if (!confirmation.isConfirmed) return;

      this.returning = true;
      try {
        const response = await axios.post(
          `/api/informatica/prestamos/${this.selectedLoan.id}/return`,
          this.buildReturnFormData(),
          { headers: { "Content-Type": "multipart/form-data" } }
        );
        this.showReturnModal = false;
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showInformaticaSuccess(response.data.message || "Devolución registrada correctamente.");
      } catch (error) {
        this.handleError(error);
      } finally {
        this.returning = false;
      }
    },
    async cancel(item) {
      const result = await Swal.fire({
        title: `Cancelar préstamo ${item.loan_code}`,
        input: "textarea",
        inputLabel: "Motivo u observaciones",
        inputPlaceholder: "Ejemplo: actividad suspendida o error de registro",
        showCancelButton: true,
        confirmButtonText: "Cancelar préstamo",
        cancelButtonText: "Cerrar",
      });

      if (!result.isConfirmed) return;

      const confirmation = await confirmInformaticaAction({
        title: "Confirmar cancelación",
        text: "El equipo volverá a quedar disponible, salvo que exista otro impedimento lógico.",
        confirmButtonText: "Sí, cancelar",
        icon: "warning",
      });

      if (!confirmation.isConfirmed) return;

      try {
        const response = await axios.post(`/api/informatica/prestamos/${item.id}/cancel`, {
          notes: result.value || null,
        });
        this.$emit("refresh-catalogs");
        await this.load(this.pagination.current_page);
        await showInformaticaSuccess(response.data.message || "Préstamo cancelado correctamente.");
      } catch (error) {
        this.handleError(error);
      }
    },
    attachmentUrl(attachment) {
      return `/api/informatica/adjuntos/${attachment.id}/download`;
    },
    async closeCreateModal() {
      const confirmation = await confirmInformaticaCancel("el registro del préstamo");
      if (confirmation.isConfirmed) {
        this.showCreateModal = false;
      }
    },
    handleError(error, fallback = "No se pudo completar la operación solicitada.") {
      this.error = formatInformaticaError(error, fallback);
      showInformaticaError(this.error);
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Flujo de préstamos de equipos</div>
      <div class="d-flex gap-2 flex-wrap">
        <InformaticaHelpButton
          title="Ayuda: préstamos de equipos"
          text="Aquí se controlan préstamos activos, atrasados, devueltos y cancelados. El sistema bloquea dobles préstamos y actualiza el estado del equipo al devolver."
        />
        <BButton v-if="capabilities.can_create_loans" variant="primary" @click="openCreate">Nuevo préstamo</BButton>
      </div>
    </div>

    <div class="row g-3">
      <div v-for="card in summaryCards" :key="card.label" class="col-md-3">
        <BCard class="border-0 shadow-sm h-100">
          <div class="d-flex justify-content-between gap-2">
            <div>
              <div class="small text-muted">{{ card.label }}</div>
              <div class="display-6 fw-semibold">{{ card.value }}</div>
            </div>
            <InformaticaHelpButton :title="card.label" :text="card.help" />
          </div>
        </BCard>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <template #header>
        <div class="d-flex justify-content-between align-items-center gap-2">
          <div class="fw-semibold">Filtros de préstamos</div>
          <InformaticaHelpButton
            title="Ayuda: filtros de préstamos"
            text="Filtra por estado, equipo, solicitante, responsable de entrega, fechas de préstamo, vencimiento o listas específicas de atrasados y activos."
          />
        </div>
      </template>
      <div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Buscar</label><BFormInput v-model="filters.search" placeholder="Código, solicitante, RUT..." @keyup.enter="load" /></div>
        <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="loanStatusOptions" /></div>
        <div class="col-md-3"><label class="form-label">Equipo</label><BFormSelect v-model="filters.it_equipment_id" :options="equipmentFilterOptions" /></div>
        <div class="col-md-2"><label class="form-label">Tipo solicitante</label><BFormSelect v-model="filters.requester_type" :options="requesterTypeOptions" /></div>
        <div class="col-md-2"><label class="form-label">Responsable entrega</label><BFormSelect v-model="filters.delivered_by_user_id" :options="[{ value: null, text: 'Todos' }].concat((catalogs.users || []).map((item) => ({ value: item.id, text: item.name })))" /></div>
        <div class="col-md-2"><label class="form-label">Desde préstamo</label><BFormInput v-model="filters.date_from" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Hasta préstamo</label><BFormInput v-model="filters.date_to" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Compromiso desde</label><BFormInput v-model="filters.due_date_from" type="date" /></div>
        <div class="col-md-2"><label class="form-label">Compromiso hasta</label><BFormInput v-model="filters.due_date_to" type="date" /></div>
        <div class="col-md-2 d-flex align-items-center"><BFormCheckbox v-model="filters.only_overdue">Solo atrasados</BFormCheckbox></div>
        <div class="col-md-2 d-flex align-items-center"><BFormCheckbox v-model="filters.only_active">Solo activos</BFormCheckbox></div>
        <div class="col-md-2 d-flex gap-2">
          <BButton variant="secondary" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="resetFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading" message="Cargando préstamos..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'loan_code', label: 'Código' },
          { key: 'equipment', label: 'Equipo' },
          { key: 'requester_name_snapshot', label: 'Solicitante' },
          { key: 'borrowed_at', label: 'Préstamo' },
          { key: 'due_at', label: 'Compromiso' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(equipment)="{ item }">
          <div class="fw-semibold">{{ item.equipment?.internal_code || "-" }}</div>
          <div class="small text-muted">{{ [item.equipment?.brand, item.equipment?.model].filter(Boolean).join(" ") || "-" }}</div>
        </template>
        <template #cell(borrowed_at)="{ item }">{{ formatInformaticaDateTime(item.borrowed_at) }}</template>
        <template #cell(due_at)="{ item }">{{ formatInformaticaDateTime(item.due_at) }}</template>
        <template #cell(status)="{ item }"><InformaticaStatusBadge :status="item.status" /></template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-info" @click="openDetail(item)">Detalle</BButton>
            <BButton
              v-if="capabilities.can_return_loans && ['activo', 'atrasado'].includes(item.status)"
              size="sm"
              variant="outline-success"
              @click="openReturn(item)"
            >
              Devolver
            </BButton>
            <BButton
              v-if="capabilities.can_cancel_loans && ['activo', 'atrasado'].includes(item.status)"
              size="sm"
              variant="outline-danger"
              @click="cancel(item)"
            >
              Cancelar
            </BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination v-model="pagination.current_page" :total-rows="pagination.total" :per-page="pagination.per_page" @update:model-value="load" />
      </div>
    </BCard>

    <BModal v-model="showCreateModal" size="xl" title="Nuevo préstamo de equipo" hide-footer scrollable>
      <div class="loan-form-intro">
        <div><strong>Registra solo la información disponible</strong><span>Únicamente el equipo es obligatorio. Los demás antecedentes se pueden completar más adelante.</span></div>
        <InformaticaHelpButton
          title="Ayuda: nuevo préstamo"
          text="Selecciona el equipo. Puedes vincular un funcionario, estudiante o usuario existente; todos los demás campos son opcionales."
        />
      </div>

      <section class="loan-form-section">
        <div class="loan-form-section__title"><span><i class="bx bx-laptop"></i></span><div><strong>Equipo y entrega</strong><small>Selecciona el activo que saldrá en préstamo</small></div></div>
        <div class="row g-3">
          <div class="col-lg-6"><label class="form-label">Equipo disponible <span class="text-danger">*</span></label><BFormSelect v-model="form.it_equipment_id" :options="availableEquipmentOptions" /></div>
          <div class="col-lg-3"><label class="form-label">Responsable entrega <span class="optional-label">Opcional</span></label><BFormSelect v-model="form.delivered_by_user_id" :options="userOptions" /></div>
          <div class="col-lg-3"><label class="form-label">Fecha y hora <span class="optional-label">Opcional</span></label><BFormInput v-model="form.borrowed_at" type="datetime-local" /></div>
        </div>
      </section>

      <section class="loan-form-section">
        <div class="loan-form-section__title"><span><i class="bx bx-user"></i></span><div><strong>Solicitante</strong><small>Vincúlalo al sistema o escribe los datos que conozcas</small></div></div>
        <div class="row g-3">
          <div class="col-lg-4"><label class="form-label">Tipo solicitante <span class="optional-label">Opcional</span></label><BFormSelect v-model="form.requester_type" :options="normalizeOptions(catalogs.requester_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
          <div class="col-lg-4" v-if="form.requester_type === 'funcionario'"><label class="form-label">Funcionario del sistema <span class="optional-label">Opcional</span></label><BFormSelect v-model="form.requester_staff_id" :options="staffOptions" /></div>
          <div class="col-lg-4" v-if="form.requester_type === 'estudiante'"><label class="form-label">Estudiante del sistema <span class="optional-label">Opcional</span></label><BFormSelect v-model="form.requester_student_profile_id" :options="studentOptions" /></div>
          <div class="col-lg-4" v-if="['funcionario', 'apoderado', 'externo', 'otro'].includes(form.requester_type)"><label class="form-label">Usuario del sistema <span class="optional-label">Opcional</span></label><BFormSelect v-model="form.requester_user_id" :options="userOptions" /></div>
          <div class="col-lg-4"><label class="form-label">Nombre <span class="optional-label">Opcional</span></label><BFormInput v-model="form.requester_name" placeholder="Nombre del solicitante" /></div>
          <div class="col-lg-4"><label class="form-label">RUT <span class="optional-label">Opcional</span></label><BFormInput v-model="form.requester_rut" placeholder="12.345.678-9" /></div>
          <div class="col-lg-4"><label class="form-label">Contacto <span class="optional-label">Opcional</span></label><BFormInput v-model="form.requester_contact" placeholder="Correo o teléfono" /></div>
        </div>
      </section>

      <section class="loan-form-section">
        <div class="loan-form-section__title"><span><i class="bx bx-calendar"></i></span><div><strong>Uso y devolución</strong><small>Información complementaria del préstamo</small></div></div>
        <div class="row g-3">
          <div class="col-lg-4"><label class="form-label">Fecha comprometida <span class="optional-label">Opcional</span></label><BFormInput v-model="form.due_at" type="datetime-local" /></div>
          <div class="col-lg-4"><label class="form-label">Lugar de uso <span class="optional-label">Opcional</span></label><BFormInput v-model="form.location_name" placeholder="Sala, oficina o dependencia" /></div>
          <div class="col-lg-4"><label class="form-label">Motivo <span class="optional-label">Opcional</span></label><BFormInput v-model="form.purpose" placeholder="Actividad o finalidad" /></div>
          <div class="col-12"><label class="form-label">Observaciones <span class="optional-label">Opcional</span></label><BFormTextarea v-model="form.notes" rows="2" placeholder="Información adicional relevante" /></div>
        </div>
      </section>

      <section class="loan-form-section mb-0">
        <div class="loan-form-section__title"><span><i class="bx bx-paperclip"></i></span><div><strong>Documento de respaldo</strong><small>Acta, autorización o fotografía, si corresponde</small></div></div>
        <input ref="createAttachmentInput" type="file" class="visually-hidden" accept="image/*,.pdf,.doc,.docx" @change="onAttachmentSelected($event, 'form')" />
        <button v-if="!form.attachment" type="button" class="loan-file-dropzone" @click="$refs.createAttachmentInput.click()">
          <span class="loan-file-dropzone__icon"><i class="bx bx-cloud-upload"></i></span>
          <span><strong>Seleccionar archivo</strong><small>PDF, imagen o documento · máximo 20 MB · opcional</small></span>
        </button>
        <div v-else class="loan-file-selected">
          <span class="loan-file-selected__icon"><i class="bx bx-file"></i></span>
          <span class="loan-file-selected__info"><strong>{{ form.attachment.name }}</strong><small>{{ formatFileSize(form.attachment.size) }}</small></span>
          <BButton size="sm" variant="light" @click="$refs.createAttachmentInput.click()">Cambiar</BButton>
          <BButton size="sm" variant="outline-danger" aria-label="Quitar archivo" @click="clearCreateAttachment"><i class="bx bx-trash"></i></BButton>
        </div>
      </section>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeCreateModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Registrar préstamo" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDetailModal" size="xl" title="Detalle del préstamo" hide-footer scrollable>
      <template v-if="selectedLoan">
        <div class="row g-3">
          <div class="col-md-3"><div class="small text-muted">Código</div><div class="fw-semibold">{{ selectedLoan.loan_code }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Estado</div><div><InformaticaStatusBadge :status="selectedLoan.status" /></div></div>
          <div class="col-md-3"><div class="small text-muted">Solicitante</div><div class="fw-semibold">{{ selectedLoan.requester_name_snapshot }}</div></div>
          <div class="col-md-3"><div class="small text-muted">Tipo</div><div class="fw-semibold">{{ humanizeInformaticaStatus(selectedLoan.requester_type) }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Equipo</div><div class="fw-semibold">{{ selectedLoan.equipment?.internal_code || "-" }}</div><div class="small text-muted">{{ [selectedLoan.equipment?.brand, selectedLoan.equipment?.model].filter(Boolean).join(" ") || "-" }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Préstamo</div><div class="fw-semibold">{{ formatInformaticaDateTime(selectedLoan.borrowed_at) }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Devolución comprometida</div><div class="fw-semibold">{{ formatInformaticaDateTime(selectedLoan.due_at) }}</div></div>
          <div class="col-md-4"><div class="small text-muted">RUT</div><div class="fw-semibold">{{ selectedLoan.requester_rut_snapshot || "-" }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Contacto</div><div class="fw-semibold">{{ selectedLoan.requester_contact_snapshot || "-" }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Responsable entrega</div><div class="fw-semibold">{{ selectedLoan.delivered_by?.name || selectedLoan.deliveredBy?.name || "-" }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Responsable recepción</div><div class="fw-semibold">{{ selectedLoan.received_by?.name || selectedLoan.receivedBy?.name || "-" }}</div></div>
          <div class="col-md-4"><div class="small text-muted">Condición devolución</div><div><InformaticaStatusBadge v-if="selectedLoan.return_condition" :status="selectedLoan.return_condition" /><span v-else>-</span></div></div>
          <div class="col-md-4"><div class="small text-muted">Fecha devolución</div><div class="fw-semibold">{{ formatInformaticaDateTime(selectedLoan.returned_at) }}</div></div>
          <div class="col-12"><div class="small text-muted">Motivo</div><div>{{ selectedLoan.purpose || "-" }}</div></div>
          <div class="col-12"><div class="small text-muted">Observaciones</div><div>{{ selectedLoan.notes || "-" }}</div></div>
          <div class="col-12"><div class="small text-muted">Observaciones devolución</div><div>{{ selectedLoan.return_notes || "-" }}</div></div>
        </div>

        <BCard class="border-0 shadow-sm mt-3">
          <template #header><div class="fw-semibold">Adjuntos del préstamo</div></template>
          <BTable
            small
            responsive
            :items="selectedLoan.attachments || []"
            :fields="[
              { key: 'original_name', label: 'Archivo' },
              { key: 'category', label: 'Categoría' },
              { key: 'created_at', label: 'Fecha' },
              { key: 'download', label: 'Descarga' },
            ]"
          >
            <template #cell(category)="{ item }">{{ humanizeInformaticaStatus(item.category) }}</template>
            <template #cell(created_at)="{ item }">{{ formatInformaticaDateTime(item.created_at) }}</template>
            <template #cell(download)="{ item }">
              <a :href="attachmentUrl(item)" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">Descargar</a>
            </template>
          </BTable>
        </BCard>
      </template>
    </BModal>

    <BModal v-model="showReturnModal" size="lg" title="Registrar devolución" hide-footer>
      <template v-if="selectedLoan">
        <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
          <div class="text-muted small">Al devolver, el sistema puede volver el equipo a disponible o dejarlo dañado/en mantención según la condición registrada.</div>
          <InformaticaHelpButton
            title="Ayuda: registrar devolución"
            text="Si el equipo vuelve dañado o incompleto puedes forzar el estado final para mantener la trazabilidad técnica correcta."
          />
        </div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Fecha y hora devolución</label><BFormInput v-model="returnForm.returned_at" type="datetime-local" /></div>
          <div class="col-md-6"><label class="form-label">Responsable recepción</label><BFormSelect v-model="returnForm.received_by_user_id" :options="userOptions" /></div>
          <div class="col-md-6"><label class="form-label">Condición del equipo</label><BFormSelect v-model="returnForm.return_condition" :options="returnConditionOptions" /></div>
          <div class="col-md-6">
            <label class="form-label">Estado final sugerido</label>
            <BFormSelect
              v-model="returnForm.post_return_status"
              :options="[{ value: null, text: 'Automático según condición' }].concat(
                normalizeOptions(catalogs.equipment_statuses || []).map((item) => ({ value: item.value, text: item.label }))
              )"
            />
          </div>
          <div class="col-md-12"><label class="form-label">Observaciones devolución</label><BFormTextarea v-model="returnForm.return_notes" rows="3" /></div>
          <div class="col-md-12"><label class="form-label">Adjunto evidencia</label><BFormFile @change="onAttachmentSelected($event, 'return')" /></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <BButton variant="light" @click="showReturnModal = false">Cancelar</BButton>
          <BButton variant="primary" :disabled="returning" @click="registerReturn">{{ returning ? "Registrando..." : "Registrar devolución" }}</BButton>
        </div>
      </template>
    </BModal>
  </div>
</template>

<style scoped>
.loan-form-intro { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding: .85rem 1rem; color: #395a50; border-radius: 11px; background: rgba(52,195,143,.09); }.loan-form-intro strong,.loan-form-intro span { display: block; }.loan-form-intro span { margin-top: .15rem; color: #6a7d77; font-size: .75rem; }
.loan-form-section { margin-bottom: 1rem; padding: 1rem 1.1rem 1.15rem; border: 1px solid #e8ebf2; border-radius: 14px; background: #fff; }
.loan-form-section__title { display: flex; align-items: center; gap: .7rem; margin-bottom: 1rem; }.loan-form-section__title > span { display: grid; flex: 0 0 36px; height: 36px; place-items: center; color: #556ee6; border-radius: 10px; background: #eef1ff; font-size: 1.15rem; }.loan-form-section__title strong,.loan-form-section__title small { display: block; }.loan-form-section__title strong { color: #2d3548; }.loan-form-section__title small { margin-top: .1rem; color: #858d9d; font-size: .7rem; }
.optional-label { margin-left: .25rem; color: #9299a7; font-size: .62rem; font-weight: 500; }
.loan-file-dropzone { display: flex; width: 100%; align-items: center; justify-content: center; gap: .85rem; min-height: 92px; padding: 1rem; color: #667085; text-align: left; border: 1.5px dashed #bdc7e9; border-radius: 12px; background: #fafbff; transition: .18s ease; }.loan-file-dropzone:hover { color: #4057d6; border-color: #7285e4; background: #f3f5ff; }.loan-file-dropzone__icon { font-size: 2rem; }.loan-file-dropzone strong,.loan-file-dropzone small { display: block; }.loan-file-dropzone small { margin-top: .2rem; color: #8a92a2; font-size: .72rem; }
.loan-file-selected { display: flex; align-items: center; gap: .75rem; padding: .85rem; border: 1px solid #cfd7f3; border-radius: 12px; background: #f8f9ff; }.loan-file-selected__icon { display: grid; flex: 0 0 40px; height: 40px; place-items: center; color: #556ee6; border-radius: 10px; background: #e8ecff; font-size: 1.3rem; }.loan-file-selected__info { min-width: 0; flex: 1; }.loan-file-selected__info strong,.loan-file-selected__info small { display: block; }.loan-file-selected__info strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.loan-file-selected__info small { color: #87909f; }
@media (max-width: 575.98px) { .loan-form-section { padding: .9rem; }.loan-file-selected { flex-wrap: wrap; }.loan-file-selected__info { flex-basis: calc(100% - 55px); } }
</style>
