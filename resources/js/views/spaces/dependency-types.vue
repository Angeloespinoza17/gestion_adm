<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  name: "",
  description: "",
  color: "#556ee6",
  sort_order: 0,
  active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      search: "",
      types: [],
      form: emptyForm(),
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
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/spaces/dependency-types", {
          params: { search: this.search || null },
        });
        this.types = response.data.data;
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
      this.showModal = true;
    },
    openEdit(item) {
      if (!this.canEdit) {
        return;
      }
      this.form = {
        id: item.id,
        name: item.name,
        description: item.description || "",
        color: item.color || "#556ee6",
        sort_order: item.sort_order || 0,
        active: Boolean(item.active),
      };
      this.showModal = true;
    },
    async save() {
      if (this.isEditing ? !this.canEdit : !this.canCreate) {
        return;
      }
      this.saving = true;
      this.error = null;
      try {
        const payload = {
          name: this.form.name,
          description: this.form.description || null,
          color: this.form.color || null,
          sort_order: Number(this.form.sort_order || 0),
          active: this.form.active,
        };

        if (this.isEditing) {
          await axios.put(`/api/spaces/dependency-types/${this.form.id}`, payload);
        } else {
          await axios.post("/api/spaces/dependency-types", payload);
        }

        this.showModal = false;
        this.load();
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
        title: "Eliminar tipo",
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
        await axios.delete(`/api/spaces/dependency-types/${item.id}`);
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
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-0">Tipos de dependencia</h4>
        <div class="text-muted">Catálogo editable para clasificar salas, laboratorios y recintos.</div>
      </div>
      <BButton v-if="canCreate" variant="primary" @click="openCreate">Nuevo tipo</BButton>
    </div>

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

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard>
      <BTable
        :items="types"
        :busy="loading"
        :fields="[
          { key: 'name', label: 'Tipo' },
          { key: 'dependencies_count', label: 'Dependencias' },
          { key: 'active', label: 'Activo' },
          { key: 'actions', label: 'Acciones' },
        ]"
        small
      >
        <template #table-busy>
          <LoadingState message="Cargando tipos de dependencia..." compact />
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
        <template #cell(active)="{ item }">
          <BBadge :variant="item.active ? 'success' : 'secondary'">
            {{ item.active ? "Sí" : "No" }}
          </BBadge>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton v-if="canEdit" size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
            <BButton v-if="canDelete" size="sm" variant="danger" @click="remove(item)">Eliminar</BButton>
          </div>
        </template>
      </BTable>
    </BCard>

    <BModal v-model="showModal" :title="isEditing ? 'Editar tipo' : 'Nuevo tipo'" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Color</label>
          <input v-model="form.color" type="color" class="form-control form-control-color" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Orden</label>
          <BFormInput v-model="form.sort_order" type="number" min="0" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving || (isEditing ? !canEdit : !canCreate)" @click="save">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
