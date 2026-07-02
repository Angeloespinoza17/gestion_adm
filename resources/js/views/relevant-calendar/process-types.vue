<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";

const emptyForm = () => ({
  id: null,
  name: "",
  description: "",
  color: "#0d6efd",
  is_active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      search: "",
      items: [],
      form: emptyForm(),
      showModal: false,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/relevant-calendar/process-types", {
          params: { search: this.search || null },
        });
        this.items = response.data.data || [];
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
    openEdit(item) {
      this.form = {
        id: item.id,
        name: item.name,
        description: item.description || "",
        color: item.color || "#0d6efd",
        is_active: Boolean(item.is_active),
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        const payload = {
          name: this.form.name,
          description: this.form.description || null,
          color: this.form.color || null,
          is_active: this.form.is_active,
        };
        if (this.isEditing) {
          await axios.put(`/api/relevant-calendar/process-types/${this.form.id}`, payload);
        } else {
          await axios.post("/api/relevant-calendar/process-types", payload);
        }
        this.showModal = false;
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async toggle(item) {
      try {
        await axios.put(`/api/relevant-calendar/process-types/${item.id}/active`, {
          is_active: !item.is_active,
        });
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    async remove(item) {
      const result = await Swal.fire({
        title: "Eliminar tipo de proceso",
        text: item.name,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/relevant-calendar/process-types/${item.id}`);
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "No se pudo gestionar el catálogo."
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Tipos de procesos</h4>
        <div class="text-muted">Catálogo editable para clasificar vencimientos y procesos declarativos.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/relevant-calendar" class="btn btn-outline-secondary">Volver</router-link>
        <BButton variant="primary" @click="openCreate">Nuevo tipo</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard class="mb-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="search" placeholder="Nombre o descripción" @keyup.enter="load" />
        </div>
        <div class="col-md-3">
          <BButton variant="secondary" @click="load">Buscar</BButton>
        </div>
      </div>
    </BCard>

    <BCard>
      <BTable
        :items="items"
        :busy="loading"
        responsive
        small
        :fields="[
          { key: 'name', label: 'Tipo' },
          { key: 'events_count', label: 'Eventos' },
          { key: 'is_active', label: 'Activo' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando tipos..." compact />
        </template>
        <template #cell(name)="{ item }">
          <div class="d-flex align-items-center gap-2">
            <span class="rounded-circle border" :style="{ width: '14px', height: '14px', backgroundColor: item.color || '#adb5bd' }"></span>
            <div>
              <div class="fw-semibold">{{ item.name }}</div>
              <div class="text-muted small">{{ item.description || "-" }}</div>
            </div>
          </div>
        </template>
        <template #cell(is_active)="{ item }">
          <BBadge :variant="item.is_active ? 'success' : 'secondary'">{{ item.is_active ? "Sí" : "No" }}</BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" :variant="item.is_active ? 'outline-warning' : 'outline-success'" @click="toggle(item)">
              {{ item.is_active ? "Desactivar" : "Activar" }}
            </BButton>
            <BButton size="sm" variant="outline-danger" @click="remove(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>
    </BCard>

    <BModal v-model="showModal" :title="isEditing ? 'Editar tipo' : 'Nuevo tipo'" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Color</label>
          <input v-model="form.color" type="color" class="form-control form-control-color" />
        </div>
        <div class="col-md-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>
        <div class="col-md-12">
          <BFormCheckbox v-model="form.is_active">Activo</BFormCheckbox>
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>
  </Layout>
</template>
