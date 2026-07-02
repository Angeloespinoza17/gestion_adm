<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  dependency_type_id: null,
  code: "",
  name: "",
  description: "",
  location: "",
  floor_sector: "",
  capacity_max: "",
  available_equipment: "",
  availability_status: "disponible",
  responsible_staff_id: null,
  observations: "",
  notes: "",
  calendar_color: "#34c38f",
  requires_approval: true,
  approver_user_ids: [],
  active: true,
  distribution: "",
  sector: "",
  zone: "",
  usage: "",
  distribution_code: "",
  floor_code: "",
  dependency_code: "",
  numbering: "",
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      dependencies: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      catalogs: {
        dependency_types: [],
        responsible_staff: [],
        approver_users: [],
        statuses: [],
      },
      filters: {
        search: "",
        dependency_type_id: null,
        responsible_staff_id: null,
        availability_status: null,
      },
      form: emptyForm(),
      imageFile: null,
      showModal: false,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    permissions() {
      return JSON.parse(localStorage.getItem("permissions") || "[]");
    },
    canCreate() {
      return this.permissions.includes("crear_dependencias");
    },
    canEdit() {
      return this.permissions.includes("editar_dependencias");
    },
    canDelete() {
      return this.permissions.includes("eliminar_dependencias");
    },
    canManageTypes() {
      return this.canCreate || this.canEdit || this.canDelete;
    },
    typeOptions() {
      return [{ value: null, label: "Sin tipo" }].concat(
        (this.catalogs.dependency_types || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    staffOptions() {
      return [{ value: null, label: "Sin responsable" }].concat(
        (this.catalogs.responsible_staff || []).map((item) => ({
          value: item.id,
          label: `${item.full_name}${item.rut ? ` (${item.rut})` : ""}`,
        }))
      );
    },
    approverOptions() {
      return (this.catalogs.approver_users || []).map((item) => ({
        value: item.id,
        label: `${item.staff?.full_name || item.name}${item.email ? ` · ${item.email}` : ""}`,
      }));
    },
    statusOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.statuses || []).map((item) => ({
          value: item.value,
          label: item.label,
        }))
      );
    },
  },
  mounted() {
    this.loadCatalogs();
    this.load();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/spaces/dependencies/catalogs");
      this.catalogs = response.data;
    },
    async load(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/spaces/dependencies", {
          params: {
            page,
            search: this.filters.search || null,
            dependency_type_id: this.filters.dependency_type_id,
            responsible_staff_id: this.filters.responsible_staff_id,
            availability_status: this.filters.availability_status,
          },
        });
        this.dependencies = response.data.data;
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
    openCreate() {
      if (!this.canCreate) {
        return;
      }
      this.form = emptyForm();
      this.form.requires_approval = true;
      this.imageFile = null;
      this.showModal = true;
    },
    async openEdit(item) {
      if (!this.canEdit) {
        return;
      }
      const response = await axios.get(`/api/spaces/dependencies/${item.id}`);
      const record = response.data.data || item;
      this.form = {
        ...emptyForm(),
        id: record.id,
        dependency_type_id: record.dependency_type_id ?? null,
        code: record.code || "",
        name: record.name || "",
        description: record.description || "",
        location: record.location || "",
        floor_sector: record.floor_sector || "",
        capacity_max: record.capacity_max || "",
        available_equipment: record.available_equipment || "",
        availability_status: record.availability_status || "disponible",
        responsible_staff_id: record.responsible_staff_id ?? null,
        observations: record.observations || "",
        notes: record.notes || "",
        calendar_color: record.calendar_color || "#34c38f",
        requires_approval: Boolean(record.requires_approval),
        approver_user_ids: (record.approvers || []).map((approver) => approver.id),
        active: Boolean(record.active),
        distribution: record.distribution || "",
        sector: record.sector || "",
        zone: record.zone || "",
        usage: record.usage || "",
        distribution_code: record.distribution_code || "",
        floor_code: record.floor_code || "",
        dependency_code: record.dependency_code || "",
        numbering: record.numbering || "",
      };
      this.imageFile = null;
      this.showModal = true;
    },
    onImage(event) {
      this.imageFile = event?.target?.files?.[0] || null;
    },
    buildFormData() {
      const fd = new FormData();
      Object.entries(this.form).forEach(([key, value]) => {
        if (Array.isArray(value)) {
          value.forEach((entry) => fd.append(`${key}[]`, entry));
          return;
        }
        if (typeof value === "boolean") {
          fd.append(key, value ? "1" : "0");
          return;
        }
        fd.append(key, value ?? "");
      });
      if (this.imageFile) {
        fd.append("image", this.imageFile);
      }
      return fd;
    },
    async save() {
      if (this.isEditing ? !this.canEdit : !this.canCreate) {
        return;
      }
      this.saving = true;
      this.error = null;
      try {
        const fd = this.buildFormData();
        if (this.isEditing) {
          fd.append("_method", "PUT");
          await axios.post(`/api/spaces/dependencies/${this.form.id}`, fd);
        } else {
          await axios.post("/api/spaces/dependencies", fd);
        }
        this.showModal = false;
        this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      if (!this.canDelete) {
        return;
      }
      const result = await Swal.fire({
        title: "Eliminar dependencia",
        text: `Se eliminará ${item.name}.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) {
        return;
      }
      try {
        await axios.delete(`/api/spaces/dependencies/${item.id}`);
        this.load(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    statusVariant(status) {
      if (status === "disponible") return "success";
      if (status === "mantencion") return "warning";
      if (status === "bloqueada") return "danger";
      return "secondary";
    },
    statusLabel(status) {
      return (this.catalogs.statuses || []).find((item) => item.value === status)?.label || status;
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
      <div class="mb-3 mb-sm-0">
        <h4 class="mb-0">Dependencias</h4>
        <div class="text-muted">Catálogo de salas, recintos y espacios reservables del colegio.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link v-if="canManageTypes" to="/spaces/dependency-types" class="btn btn-outline-secondary">Tipos</router-link>
        <router-link v-if="canEdit" to="/spaces/approvers" class="btn btn-outline-secondary">Gestores</router-link>
        <BButton v-if="canCreate" variant="primary" @click="openCreate">Nueva dependencia</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Nombre, código, ubicación..." @keyup.enter="load" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="filters.dependency_type_id" :options="typeOptions" :searchable="true" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <Multiselect v-model="filters.availability_status" :options="statusOptions" :searchable="false" />
        </div>
        <div class="col-md-2">
          <BButton variant="secondary" @click="load">Filtrar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard>
      <BTable
        :items="dependencies"
        :busy="loading"
        :fields="[
          { key: 'name', label: 'Dependencia' },
          { key: 'type', label: 'Tipo' },
          { key: 'availability_status', label: 'Estado' },
          { key: 'responsibleStaff', label: 'Responsable' },
          { key: 'upcoming_reservations_count', label: 'Próximas reservas' },
          { key: 'actions', label: 'Acciones' },
        ]"
        responsive
        small
      >
        <template #table-busy>
          <LoadingState message="Cargando dependencias..." compact />
        </template>
        <template #cell(name)="{ item }">
          <div class="d-flex align-items-center gap-3">
            <img
              v-if="item.image_url"
              :src="item.image_url"
              alt="Dependencia"
              class="rounded border"
              style="width: 52px; height: 52px; object-fit: cover"
            />
            <div>
              <div class="fw-semibold">{{ item.name }}</div>
              <div class="text-muted small">{{ item.location || item.floor_sector || "-" }}</div>
              <div class="small text-muted">{{ item.code }}</div>
            </div>
          </div>
        </template>
        <template #cell(type)="{ item }">
          {{ item.type?.name || "-" }}
        </template>
        <template #cell(availability_status)="{ item }">
          <BBadge :variant="statusVariant(item.availability_status)">
            {{ statusLabel(item.availability_status) }}
          </BBadge>
        </template>
        <template #cell(responsibleStaff)="{ item }">
          {{ item.responsible_staff?.full_name || "-" }}
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex flex-wrap gap-2">
            <router-link :to="`/spaces/dependencies/${item.id}`" class="btn btn-sm btn-outline-primary">Ver</router-link>
            <BButton v-if="canEdit" size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
            <BButton v-if="canDelete" size="sm" variant="danger" @click="remove(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>
    </BCard>

    <BModal v-model="showModal" :title="isEditing ? 'Editar dependencia' : 'Nueva dependencia'" size="xl" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Código</label>
          <BFormInput v-model="form.code" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <Multiselect v-model="form.dependency_type_id" :options="typeOptions" :searchable="true" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado</label>
          <Multiselect v-model="form.availability_status" :options="catalogs.statuses || []" value-prop="value" label="label" :searchable="false" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Responsable</label>
          <Multiselect v-model="form.responsible_staff_id" :options="staffOptions" :searchable="true" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Ubicación</label>
          <BFormInput v-model="form.location" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Piso o sector</label>
          <BFormInput v-model="form.floor_sector" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Capacidad máxima</label>
          <BFormInput v-model="form.capacity_max" type="number" min="0" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Color calendario</label>
          <input v-model="form.calendar_color" type="color" class="form-control form-control-color" />
        </div>
        <div class="col-md-6 d-flex align-items-end gap-4">
          <BFormCheckbox :model-value="true" disabled>Requiere aprobación</BFormCheckbox>
          <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
        </div>
        <div class="col-12">
          <div class="text-muted small">
            Todas las dependencias reservables exigen autorización previa para sus reservas.
          </div>
        </div>
        <div class="col-12">
          <div class="border rounded-3 p-3 bg-light-subtle">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="fw-semibold">Gestores de aprobación</div>
                <div class="text-muted small">Usuarios autorizados para aprobar o rechazar reservas de esta dependencia.</div>
              </div>
            </div>
            <Multiselect
              v-model="form.approver_user_ids"
              :options="approverOptions"
              mode="multiple"
              :close-on-select="false"
              :searchable="true"
              placeholder="Selecciona gestores"
            />
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="2" />
        </div>
        <div class="col-12">
          <label class="form-label">Equipamiento disponible</label>
          <BFormTextarea v-model="form.available_equipment" rows="2" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="2" />
        </div>
        <div class="col-12">
          <label class="form-label">Imagen</label>
          <input type="file" class="form-control" accept="image/*" @change="onImage" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving || (isEditing ? !canEdit : !canCreate)" @click="save">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
