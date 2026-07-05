<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const emptyForm = () => ({
  id: null,
  name: "",
  rut: "",
  business_name: "",
  email: "",
  phone: "",
  address: "",
  active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      search: "",
      suppliers: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      showModal: false,
      form: emptyForm(),
      error: null,
      success: null,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  mounted() {
    this.loadSuppliers();
  },
  methods: {
    async loadSuppliers(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/inventory/suppliers", {
          params: { page, search: this.search },
        });
        this.suppliers = response.data.data;
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
      this.form = emptyForm();
      this.showModal = true;
    },
    openEdit(supplier) {
      this.form = {
        id: supplier.id,
        name: supplier.name || "",
        rut: supplier.rut || "",
        business_name: supplier.business_name || "",
        email: supplier.email || "",
        phone: supplier.phone || "",
        address: supplier.address || "",
        active: Boolean(supplier.active),
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        const payload = {
          name: this.form.name,
          rut: this.form.rut || null,
          business_name: this.form.business_name || null,
          email: this.form.email || null,
          phone: this.form.phone || null,
          address: this.form.address || null,
          active: this.form.active,
        };
        if (this.isEditing) {
          await axios.put(`/api/inventory/suppliers/${this.form.id}`, payload);
          this.success = "Proveedor actualizado.";
        } else {
          await axios.post("/api/inventory/suppliers", payload);
          this.success = "Proveedor creado.";
        }
        this.showModal = false;
        await this.loadSuppliers(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async remove(supplier) {
      if (!confirm(`Eliminar proveedor ${supplier.name}?`)) return;
      await axios.delete(`/api/inventory/suppliers/${supplier.id}`);
      this.loadSuppliers(this.pagination.current_page);
    },
    formatError(error) {
      return (
        error?.response?.data?.message ||
        error?.response?.data?.errors?.[
          Object.keys(error.response.data.errors || {})[0]
        ]?.[0] ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Inventario · Proveedores</h4>
      <BButton variant="primary" @click="openCreate">Nuevo proveedor</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <div class="row mb-3">
      <div class="col-md-4">
        <BFormInput v-model="search" placeholder="Buscar" @keyup.enter="loadSuppliers(1)" />
      </div>
      <div class="col-md-2">
        <BButton variant="secondary" @click="loadSuppliers(1)">Buscar</BButton>
      </div>
    </div>

    <div class="table-responsive">
      <BTable
        :items="suppliers"
        :busy="loading"
        :fields="[
          { key: 'name', label: 'Nombre' },
          { key: 'business_name', label: 'Razón social' },
          { key: 'rut', label: 'RUT' },
          { key: 'email', label: 'Email' },
          { key: 'active', label: 'Activo' },
          { key: 'actions', label: 'Acciones' },
        ]"
        small
      >
        <template #table-busy>
          <LoadingState message="Cargando proveedores..." compact />
        </template>
        <template #cell(active)="{ item }">
          <BBadge :variant="item.active ? 'success' : 'secondary'">
            {{ item.active ? "Sí" : "No" }}
          </BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton size="sm" variant="warning" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="danger" @click="remove(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>
    </div>

    <div class="d-flex justify-content-end">
      <BPagination
        v-model="pagination.current_page"
        :per-page="15"
        :total-rows="pagination.total"
        @update:model-value="loadSuppliers"
      />
    </div>

    <BModal
      v-model="showModal"
      :title="isEditing ? 'Editar proveedor' : 'Nuevo proveedor'"
      size="lg"
      hide-footer
    >
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Razón social</label>
          <BFormInput v-model="form.business_name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">RUT</label>
          <BFormInput v-model="form.rut" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Email</label>
          <BFormInput v-model="form.email" type="email" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Teléfono</label>
          <BFormInput v-model="form.phone" />
        </div>
        <div class="col-12">
          <label class="form-label">Dirección</label>
          <BFormInput v-model="form.address" />
        </div>
        <div class="col-12">
          <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
