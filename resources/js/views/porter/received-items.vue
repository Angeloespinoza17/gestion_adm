<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import PorterStatusBadge from "../../components/porter/status-badge.vue";

const emptyForm = () => ({
  recipient_type: "student",
  recipient_label: "",
  student_profile_id: null,
  staff_id: null,
  department_id: null,
  received_from_name: "",
  received_from_rut: "",
  received_from_phone: "",
  item_type: "objeto",
  description: "",
  status: "recibido_en_porteria",
  observations: "",
  attachment: null,
});

export default {
  components: { Layout, LoadingState, PorterStatusBadge },
  data() {
    return {
      loadingCatalogs: false,
      saving: false,
      loadingList: false,
      error: null,
      catalogs: {
        received_item_types: [],
        received_item_statuses: [],
        received_item_recipient_types: [],
        departments: [],
        staff: [],
      },
      studentSearch: "",
      studentOptions: [],
      form: emptyForm(),
      items: [],
      filters: {
        search: "",
        status: null,
        item_type: null,
      },
      pagination: { current_page: 1, total: 0, per_page: 15 },
    };
  },
  computed: {
    recipientOptions() {
      return (this.catalogs.received_item_recipient_types || []).map((item) => ({ value: item.value, text: item.label }));
    },
    typeOptions() {
      return (this.catalogs.received_item_types || []).map((item) => ({ value: item.value, text: item.label }));
    },
    statusOptions() {
      return [{ value: null, text: "Todos" }].concat(
        (this.catalogs.received_item_statuses || []).map((item) => ({ value: item.value, text: item.label }))
      );
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadItems();
  },
  methods: {
    async loadCatalogs() {
      this.loadingCatalogs = true;
      try {
        const response = await axios.get("/api/porter/catalogs");
        this.catalogs = response.data;
      } finally {
        this.loadingCatalogs = false;
      }
    },
    async searchStudents() {
      if (!this.studentSearch.trim()) {
        this.studentOptions = [];
        return;
      }
      const response = await axios.get("/api/porter/students", {
        params: { search: this.studentSearch, per_page: 8 },
      });
      this.studentOptions = response.data.data || [];
    },
    onFileChange(event) {
      this.form.attachment = event?.target?.files?.[0] || null;
    },
    async submit() {
      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        Object.entries(this.form).forEach(([key, value]) => {
          if (value === null || value === undefined || value === "") return;
          if (key === "attachment") {
            if (value) formData.append("attachment", value);
            return;
          }
          formData.append(key, value);
        });

        await axios.post("/api/porter/received-items", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        this.form = emptyForm();
        this.studentSearch = "";
        this.studentOptions = [];
        await this.loadItems(1);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async loadItems(page = 1) {
      this.loadingList = true;
      this.error = null;
      try {
        const response = await axios.get("/api/porter/received-items", {
          params: {
            page,
            search: this.filters.search || null,
            status: this.filters.status,
            item_type: this.filters.item_type,
          },
        });
        this.items = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loadingList = false;
      }
    },
    async updateStatus(item) {
      const { value } = await Swal.fire({
        title: "Actualizar estado",
        html: `
          <select id="item-status" class="swal2-select">
            ${(this.catalogs.received_item_statuses || [])
              .map((status) => `<option value="${status.value}" ${status.value === item.status ? "selected" : ""}>${status.label}</option>`)
              .join("")}
          </select>
          <input id="delivered-to-name" class="swal2-input" placeholder="Persona que recibe" />
          <input id="delivered-to-rut" class="swal2-input" placeholder="RUT o identificación" />
          <textarea id="delivery-observations" class="swal2-textarea" placeholder="Observaciones"></textarea>
        `,
        preConfirm: () => ({
          status: document.getElementById("item-status").value,
          delivered_to_name: document.getElementById("delivered-to-name").value,
          delivered_to_rut: document.getElementById("delivered-to-rut").value,
          delivery_observations: document.getElementById("delivery-observations").value,
        }),
      });

      if (!value) return;
      await axios.put(`/api/porter/received-items/${item.id}/status`, value);
      await this.loadItems(this.pagination.current_page || 1);
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || {};
      const firstKey = Object.keys(errors)[0];
      return errors[firstKey]?.[0] || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0">Recepción de objetos y documentos</h4>
        <div class="text-muted">Registro de entregas en portería para estudiantes, funcionarios o departamentos.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <h5 class="mb-3">Nueva recepción</h5>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Tipo de destinatario</label>
          <BFormSelect v-model="form.recipient_type" :options="recipientOptions" />
        </div>
        <div v-if="form.recipient_type === 'student'" class="col-md-5">
          <label class="form-label">Buscar estudiante</label>
          <div class="d-flex gap-2">
            <BFormInput v-model="studentSearch" placeholder="Nombre o RUT" @keyup.enter="searchStudents" />
            <BButton variant="outline-primary" @click="searchStudents">Buscar</BButton>
          </div>
        </div>
        <div v-if="form.recipient_type === 'student'" class="col-md-4">
          <label class="form-label">Seleccionar estudiante</label>
          <BFormSelect
            v-model="form.student_profile_id"
            :options="[{ value: null, text: 'Seleccionar...' }].concat(studentOptions.map((item) => ({ value: item.id, text: item.full_name })))"
          />
        </div>
        <div v-if="form.recipient_type === 'staff'" class="col-md-6">
          <label class="form-label">Funcionario destinatario</label>
          <BFormSelect
            v-model="form.staff_id"
            :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.staff || []).map((item) => ({ value: item.id, text: item.full_name })))"
          />
        </div>
        <div v-if="form.recipient_type === 'department'" class="col-md-6">
          <label class="form-label">Departamento destinatario</label>
          <BFormSelect
            v-model="form.department_id"
            :options="[{ value: null, text: 'Seleccionar...' }].concat((catalogs.departments || []).map((item) => ({ value: item.id, text: item.name })))"
          />
        </div>
        <div v-if="form.recipient_type === 'other'" class="col-md-6">
          <label class="form-label">Destinatario</label>
          <BFormInput v-model="form.recipient_label" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Quien entrega</label>
          <BFormInput v-model="form.received_from_name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">RUT</label>
          <BFormInput v-model="form.received_from_rut" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Teléfono</label>
          <BFormInput v-model="form.received_from_phone" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="form.item_type" :options="typeOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado inicial</label>
          <BFormSelect v-model="form.status" :options="statusOptions.filter((item) => item.value)" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Adjunto</label>
          <BFormInput type="file" @change="onFileChange" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="2" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="2" />
        </div>
        <div class="col-12 d-flex justify-content-end">
          <BButton variant="primary" :disabled="saving" @click="submit">{{ saving ? "Guardando..." : "Registrar recepción" }}</BButton>
        </div>
      </div>
    </BCard>

    <BCard>
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0">Historial</h5>
        <div class="d-flex gap-2 flex-wrap">
          <BFormInput v-model="filters.search" placeholder="Buscar" @keyup.enter="loadItems(1)" />
          <BFormSelect v-model="filters.status" :options="statusOptions" />
          <BFormSelect v-model="filters.item_type" :options="[{ value: null, text: 'Todos los tipos' }].concat(typeOptions)" />
          <BButton variant="outline-primary" @click="loadItems(1)">Filtrar</BButton>
        </div>
      </div>

      <BTable
        :items="items"
        :busy="loadingList"
        responsive
        :fields="[
          { key: 'description', label: 'Recepción' },
          { key: 'recipient_label', label: 'Destinatario' },
          { key: 'status', label: 'Estado' },
          { key: 'received_at', label: 'Fecha' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando recepciones..." compact />
        </template>
        <template #cell(description)="{ item }">
          <div class="fw-semibold">{{ item.description }}</div>
          <div class="small text-muted">{{ item.received_from_name }}</div>
        </template>
        <template #cell(recipient_label)="{ item }">
          {{ item.recipient_label || item.student_profile?.full_name || item.department?.name || item.staff?.full_name || "-" }}
        </template>
        <template #cell(status)="{ item }">
          <PorterStatusBadge :value="item.status" :label="item.status" />
        </template>
        <template #cell(received_at)="{ item }">
          {{ formatDateTime(item.received_at) }}
        </template>
        <template #cell(actions)="{ item }">
          <BButton size="sm" variant="outline-primary" @click="updateStatus(item)">Actualizar estado</BButton>
        </template>
      </BTable>

      <div class="d-flex justify-content-end mt-3">
        <BPagination
          v-model="pagination.current_page"
          :total-rows="pagination.total"
          :per-page="pagination.per_page"
          @update:model-value="loadItems"
        />
      </div>
    </BCard>
  </Layout>
</template>
