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

    <BModal v-model="showCreateModal" size="xl" title="Nuevo préstamo de equipo" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <div class="text-muted small">Selecciona un equipo disponible, define el solicitante y registra la fecha comprometida de devolución.</div>
        <InformaticaHelpButton
          title="Ayuda: nuevo préstamo"
          text="Si el sistema ya tiene usuarios, funcionarios o estudiantes puedes vincularlos. Si no, registra nombre, RUT y contacto manualmente."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Equipo disponible</label><BFormSelect v-model="form.it_equipment_id" :options="availableEquipmentOptions" /></div>
        <div class="col-md-3"><label class="form-label">Tipo solicitante</label><BFormSelect v-model="form.requester_type" :options="normalizeOptions(catalogs.requester_types || []).map((item) => ({ value: item.value, text: item.label }))" /></div>
        <div class="col-md-3"><label class="form-label">Responsable entrega</label><BFormSelect v-model="form.delivered_by_user_id" :options="userOptions" /></div>
        <div class="col-md-2"><label class="form-label">Adjunto</label><BFormFile @change="onAttachmentSelected($event, 'form')" /></div>

        <div class="col-md-4" v-if="form.requester_type === 'funcionario'">
          <label class="form-label">Funcionario del sistema</label>
          <BFormSelect v-model="form.requester_staff_id" :options="staffOptions" />
        </div>
        <div class="col-md-4" v-if="form.requester_type === 'estudiante'">
          <label class="form-label">Estudiante del sistema</label>
          <BFormSelect v-model="form.requester_student_profile_id" :options="studentOptions" />
        </div>
        <div class="col-md-4" v-if="['funcionario', 'apoderado', 'externo', 'otro'].includes(form.requester_type)">
          <label class="form-label">Usuario del sistema (opcional)</label>
          <BFormSelect v-model="form.requester_user_id" :options="userOptions" />
        </div>

        <div class="col-md-4"><label class="form-label">Nombre solicitante</label><BFormInput v-model="form.requester_name" /></div>
        <div class="col-md-4"><label class="form-label">RUT solicitante</label><BFormInput v-model="form.requester_rut" /></div>
        <div class="col-md-4"><label class="form-label">Contacto</label><BFormInput v-model="form.requester_contact" /></div>
        <div class="col-md-3"><label class="form-label">Fecha y hora préstamo</label><BFormInput v-model="form.borrowed_at" type="datetime-local" /></div>
        <div class="col-md-3"><label class="form-label">Fecha comprometida</label><BFormInput v-model="form.due_at" type="datetime-local" /></div>
        <div class="col-md-3"><label class="form-label">Lugar de uso</label><BFormInput v-model="form.location_name" /></div>
        <div class="col-md-3"><label class="form-label">Motivo</label><BFormInput v-model="form.purpose" /></div>
        <div class="col-12"><label class="form-label">Observaciones</label><BFormTextarea v-model="form.notes" rows="3" /></div>
      </div>
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
