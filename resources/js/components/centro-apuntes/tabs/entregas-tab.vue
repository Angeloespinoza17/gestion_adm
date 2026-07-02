<script>
import axios from "axios";
import Swal from "sweetalert2";
import CentroApuntesHelpButton from "../help-button.vue";
import CentroApuntesStatusBadge from "../status-badge.vue";
import LoadingState from "../../ui/loading-state.vue";
import {
  confirmCentroApuntesAction,
  confirmCentroApuntesCancel,
  formatCentroApuntesDateTime,
  formatCentroApuntesError,
  normalizeOptions,
  printCentroApuntesHtml,
  showCentroApuntesSuccess,
} from "../module-utils";

const emptyDetail = () => ({
  insumo_id: null,
  quantity: 1,
  notes: "",
});

const emptyForm = () => ({
  id: null,
  requested_by_user_id: null,
  withdrawn_by_user_id: null,
  department_id: null,
  requested_at: new Date().toISOString().slice(0, 16),
  observations: "",
  receipt_notes: "",
  details: [emptyDetail()],
});

export default {
  components: {
    CentroApuntesHelpButton,
    CentroApuntesStatusBadge,
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
      detailLoading: false,
      error: null,
      items: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      filters: {
        search: "",
        requested_by_user_id: null,
        department_id: null,
        status: null,
      },
      showModal: false,
      showDetailModal: false,
      form: emptyForm(),
      selectedDelivery: null,
    };
  },
  computed: {
    userOptions() {
      return normalizeOptions(this.catalogs.users || []);
    },
    departmentOptions() {
      return normalizeOptions(this.catalogs.departments || []);
    },
    deliveryStatusOptions() {
      return normalizeOptions(this.catalogs.delivery_statuses || []);
    },
    supplyOptions() {
      return normalizeOptions((this.catalogs.supplies || []).map((item) => ({
        value: item.id,
        label: `${item.name} · ${item.current_stock} ${item.unit_of_measure}`,
      })));
    },
  },
  mounted() {
    this.load();
    this.consumeRouteFocus();
  },
  methods: {
    formatCentroApuntesDateTime,
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/centro-apuntes/entregas", {
          params: { page, ...this.filters },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page,
          total: response.data.total,
          per_page: response.data.per_page,
        };
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudieron cargar las entregas.");
      } finally {
        this.loading = false;
      }
    },
    async consumeRouteFocus() {
      if (!this.$route.query.delivery) return;
      await this.openDetail(this.$route.query.delivery);
    },
    resetForm() {
      this.form = emptyForm();
      this.selectedDelivery = null;
    },
    openCreate() {
      this.resetForm();
      this.showModal = true;
    },
    addDetail() {
      this.form.details.push(emptyDetail());
    },
    removeDetail(index) {
      if (this.form.details.length === 1) return;
      this.form.details.splice(index, 1);
    },
    async openEdit(item) {
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/centro-apuntes/entregas/${item.id}`);
        this.selectedDelivery = response.data.data;
        this.form = {
          id: this.selectedDelivery.id,
          requested_by_user_id: this.selectedDelivery.requested_by_user_id,
          withdrawn_by_user_id: this.selectedDelivery.withdrawn_by_user_id || null,
          department_id: this.selectedDelivery.department_id || null,
          requested_at: String(this.selectedDelivery.requested_at || "").replace(" ", "T").slice(0, 16),
          observations: this.selectedDelivery.observations || "",
          receipt_notes: this.selectedDelivery.receipt_notes || "",
          details: (this.selectedDelivery.details || []).map((detail) => ({
            insumo_id: detail.insumo_id,
            quantity: detail.quantity,
            notes: detail.notes || "",
          })),
        };
        this.showModal = true;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo abrir la entrega seleccionada.");
      } finally {
        this.detailLoading = false;
      }
    },
    async openDetail(itemOrId) {
      const id = typeof itemOrId === "object" ? itemOrId.id : itemOrId;
      this.detailLoading = true;
      try {
        const response = await axios.get(`/api/centro-apuntes/entregas/${id}`);
        this.selectedDelivery = response.data.data;
        this.showDetailModal = true;
      } catch (error) {
        this.error = formatCentroApuntesError(error, "No se pudo cargar el detalle de la entrega.");
      } finally {
        this.detailLoading = false;
      }
    },
    async save() {
      const confirmed = await confirmCentroApuntesAction({
        title: this.form.id ? "Guardar solicitud" : "Crear solicitud de materiales",
        text: this.form.id
          ? "Se actualizará la solicitud de materiales."
          : "Se registrará una nueva solicitud de materiales al pañol.",
        confirmButtonText: "Guardar",
      });

      if (!confirmed.isConfirmed) return;

      this.saving = true;
      try {
        if (this.form.id) {
          await axios.put(`/api/centro-apuntes/entregas/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/centro-apuntes/entregas", this.form);
        }
        this.showModal = false;
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess(this.form.id ? "Solicitud de materiales actualizada correctamente." : "Solicitud de materiales registrada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      } finally {
        this.saving = false;
      }
    },
    async approve(item) {
      await this.transition(item, "approve", "Aprobar solicitud", "La solicitud quedará disponible para preparación.", "Aprobar");
    },
    async reject(item) {
      await this.transition(item, "reject", "Rechazar solicitud", "Indique el motivo del rechazo.", "Rechazar");
    },
    async annul(item) {
      await this.transition(item, "annul", "Anular solicitud", "Indique el motivo de la anulación.", "Anular");
    },
    async deliver(item) {
      const optionsHtml = this.userOptions
        .map((option) => `<option value="${option.value}" ${item.withdrawn_by_user_id === option.value ? "selected" : ""}>${option.label}</option>`)
        .join("");

      const result = await Swal.fire({
        title: "Registrar entrega de materiales",
        html: `
          <div class="text-start">
            <label class="form-label">Funcionario que retira</label>
            <select id="withdrawn-by" class="swal2-input">${optionsHtml}</select>
            <label class="form-label mt-2">Observaciones</label>
            <textarea id="deliver-note" class="swal2-textarea" placeholder="Detalle de la entrega"></textarea>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Registrar entrega",
        cancelButtonText: "Cancelar",
        preConfirm: () => ({
          withdrawn_by_user_id: document.getElementById("withdrawn-by").value,
          notes: document.getElementById("deliver-note").value,
        }),
      });

      if (!result.isConfirmed) return;

      try {
        await axios.post(`/api/centro-apuntes/entregas/${item.id}/deliver`, result.value);
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess("Entrega registrada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    async transition(item, action, title, text, confirmButtonText) {
      const result = await Swal.fire({
        title,
        input: "textarea",
        inputLabel: text,
        inputPlaceholder: "Observaciones opcionales",
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        await axios.post(`/api/centro-apuntes/entregas/${item.id}/${action}`, {
          notes: result.value,
        });
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess("Estado actualizado correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    async destroy(item) {
      const confirmed = await confirmCentroApuntesAction({
        title: "Eliminar solicitud de materiales",
        text: `Se eliminará la solicitud ${item.delivery_code}.`,
        confirmButtonText: "Sí, eliminar",
        icon: "warning",
      });

      if (!confirmed.isConfirmed) return;

      try {
        await axios.delete(`/api/centro-apuntes/entregas/${item.id}`);
        await this.load(this.pagination.current_page);
        await showCentroApuntesSuccess("Solicitud eliminada correctamente.");
      } catch (error) {
        this.error = formatCentroApuntesError(error);
      }
    },
    printReceipt() {
      if (!this.selectedDelivery) return;

      const html = `
        <table>
          <tbody>
            <tr><th>Código</th><td>${this.selectedDelivery.delivery_code}</td></tr>
            <tr><th>Solicitante</th><td>${this.selectedDelivery.requested_by_name_snapshot}</td></tr>
            <tr><th>Retira</th><td>${this.selectedDelivery.withdrawn_by_name_snapshot || "-"}</td></tr>
            <tr><th>Área</th><td>${this.selectedDelivery.department_name_snapshot || "-"}</td></tr>
            <tr><th>Estado</th><td>${this.selectedDelivery.status}</td></tr>
            <tr><th>Fecha</th><td>${formatCentroApuntesDateTime(this.selectedDelivery.requested_at)}</td></tr>
          </tbody>
        </table>
        <table>
          <thead><tr><th>Insumo</th><th>Cantidad</th><th>Costo unitario</th><th>Total</th></tr></thead>
          <tbody>
            ${(this.selectedDelivery.details || [])
              .map((detail) => `<tr><td>${detail.insumo_name_snapshot || detail.insumo?.name}</td><td>${detail.quantity}</td><td>${detail.unit_cost_estimated}</td><td>${detail.line_total_estimated}</td></tr>`)
              .join("")}
          </tbody>
        </table>
      `;

      printCentroApuntesHtml(`Comprobante ${this.selectedDelivery.delivery_code}`, html);
    },
    clearFilters() {
      this.filters = {
        search: "",
        requested_by_user_id: null,
        department_id: null,
        status: null,
      };
      this.load();
    },
    async closeModal() {
      const confirmed = await confirmCentroApuntesCancel("la edición de la solicitud de materiales");
      if (confirmed.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div class="d-flex flex-column gap-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Solicitudes y entregas de materiales</div>
      <div class="d-flex gap-2">
        <CentroApuntesHelpButton
          title="Ayuda: entregas de materiales"
          text="Aquí se solicitan, aprueban y registran entregas de materiales a funcionarios o áreas, con descuento automático de stock y comprobante de entrega."
        />
        <BButton variant="primary" @click="openCreate">Nueva solicitud de materiales</BButton>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>

    <BCard class="border-0 shadow-sm">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Código, solicitante, área..." @keyup.enter="load" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Solicitante</label>
          <BFormSelect v-model="filters.requested_by_user_id" :options="[{ value: null, text: 'Todos' }].concat(userOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Área</label>
          <BFormSelect v-model="filters.department_id" :options="[{ value: null, text: 'Todas' }].concat(departmentOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="[{ value: null, text: 'Todos' }].concat(deliveryStatusOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-3">
          <BButton variant="secondary" class="me-2" @click="load">Filtrar</BButton>
          <BButton variant="light" @click="clearFilters">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BCard class="border-0 shadow-sm">
      <LoadingState v-if="loading || detailLoading" message="Cargando entregas..." compact />
      <BTable
        v-else
        responsive
        :items="items"
        :fields="[
          { key: 'delivery_code', label: 'Solicitud' },
          { key: 'requested_by_name_snapshot', label: 'Solicitante' },
          { key: 'department_name_snapshot', label: 'Área' },
          { key: 'details_count', label: 'Insumos' },
          { key: 'total_estimated_cost', label: 'Costo est.' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #cell(delivery_code)="{ item }">
          <div class="fw-semibold">{{ item.delivery_code }}</div>
          <div class="small text-muted">{{ formatCentroApuntesDateTime(item.requested_at) }}</div>
        </template>
        <template #cell(total_estimated_cost)="{ item }">
          ${{ Number(item.total_estimated_cost || 0).toLocaleString("es-CL") }}
        </template>
        <template #cell(status)="{ item }">
          <CentroApuntesStatusBadge :status="item.status" />
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-info" @click="openDetail(item)">Ver</BButton>
            <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="outline-success" @click="approve(item)">Aprobar</BButton>
            <BButton size="sm" variant="outline-warning" @click="deliver(item)">Entregar</BButton>
            <BButton size="sm" variant="outline-danger" @click="reject(item)">Rechazar</BButton>
            <BButton size="sm" variant="outline-secondary" @click="annul(item)">Anular</BButton>
            <BButton size="sm" variant="outline-dark" @click="destroy(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>
      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="load"
        />
      </div>
    </BCard>

    <BModal v-model="showModal" size="xl" title="Solicitud de materiales" hide-footer>
      <div class="d-flex justify-content-end mb-3">
        <CentroApuntesHelpButton
          title="Ayuda: formulario de entrega"
          text="Use este formulario para solicitar materiales desde el pañol, indicando solicitante, área, funcionario que retira e insumos requeridos."
        />
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Solicitante</label>
          <BFormSelect v-model="form.requested_by_user_id" :options="userOptions.map((item) => ({ value: item.value, text: item.label }))" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Funcionario que retira</label>
          <BFormSelect v-model="form.withdrawn_by_user_id" :options="[{ value: null, text: 'Sin definir' }].concat(userOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Área</label>
          <BFormSelect v-model="form.department_id" :options="[{ value: null, text: 'Sin área' }].concat(departmentOptions.map((item) => ({ value: item.value, text: item.label })))" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha de solicitud</label>
          <BFormInput v-model="form.requested_at" type="datetime-local" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Observaciones</label>
          <BFormInput v-model="form.observations" />
        </div>
      </div>

      <div class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold">Insumos solicitados</div>
          <BButton size="sm" variant="outline-primary" @click="addDetail">Agregar insumo</BButton>
        </div>
        <div v-for="(detail, index) in form.details" :key="index" class="row g-3 align-items-end border rounded p-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Insumo</label>
            <BFormSelect v-model="detail.insumo_id" :options="supplyOptions.map((item) => ({ value: item.value, text: item.label }))" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Cantidad</label>
            <BFormInput v-model="detail.quantity" type="number" min="1" step="0.01" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Observación línea</label>
            <BFormInput v-model="detail.notes" />
          </div>
          <div class="col-md-1">
            <BButton variant="outline-danger" @click="removeDetail(index)">X</BButton>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="light" @click="closeModal">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDetailModal" size="xl" title="Detalle de entrega" hide-footer>
      <template v-if="selectedDelivery">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div>
            <div class="fw-semibold">{{ selectedDelivery.delivery_code }}</div>
            <div class="text-muted small">{{ formatCentroApuntesDateTime(selectedDelivery.requested_at) }}</div>
          </div>
          <div class="d-flex gap-2">
            <CentroApuntesStatusBadge :status="selectedDelivery.status" />
            <BButton size="sm" variant="outline-dark" @click="printReceipt">Imprimir comprobante</BButton>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <div class="text-muted small">Solicitante</div>
            <div>{{ selectedDelivery.requested_by_name_snapshot }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Retira</div>
            <div>{{ selectedDelivery.withdrawn_by_name_snapshot || "-" }}</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Área</div>
            <div>{{ selectedDelivery.department_name_snapshot || "-" }}</div>
          </div>
        </div>

        <div class="mt-4">
          <div class="fw-semibold mb-2">Detalle de insumos</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Insumo</th>
                  <th>Cantidad</th>
                  <th>Costo unitario</th>
                  <th>Total</th>
                  <th>Notas</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="detail in selectedDelivery.details || []" :key="detail.id">
                  <td>{{ detail.insumo_name_snapshot || detail.insumo?.name }}</td>
                  <td>{{ detail.quantity }}</td>
                  <td>${{ Number(detail.unit_cost_estimated || 0).toLocaleString("es-CL") }}</td>
                  <td>${{ Number(detail.line_total_estimated || 0).toLocaleString("es-CL") }}</td>
                  <td>{{ detail.notes || "-" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </BModal>
  </div>
</template>
