<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

const emptyForm = () => ({
  id: null,
  name: "",
  slug: "",
  description: "",
  active: true,
});

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      saving: false,
      cargos: [],
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
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      try {
        const response = await axios.get("/api/admin/cargos");
        this.cargos = response.data.data;
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
    openEdit(cargo) {
      this.form = {
        id: cargo.id,
        name: cargo.name,
        slug: cargo.slug,
        description: cargo.description || "",
        active: Boolean(cargo.active),
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/admin/cargos/${this.form.id}`, this.form);
          this.success = "Cargo actualizado.";
        } else {
          await axios.post("/api/admin/cargos", this.form);
          this.success = "Cargo creado.";
        }
        this.showModal = false;
        this.load();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async toggle(cargo) {
      await axios.put(`/api/admin/cargos/${cargo.id}/active`, { active: !cargo.active });
      this.load();
    },
    formatError(error) {
      return error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Cargos</h4>
      <BButton variant="primary" @click="openCreate">Nuevo cargo</BButton>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>

    <BTable
      :items="cargos"
      :busy="loading"
      small
      :fields="[
        { key: 'name', label: 'Nombre' },
        { key: 'slug', label: 'Slug' },
        { key: 'active', label: 'Activo' },
        { key: 'actions', label: 'Acciones' },
      ]"
    >
      <template #cell(active)="{ item }">
        <BBadge :variant="item.active ? 'success' : 'secondary'">{{ item.active ? "Sí" : "No" }}</BBadge>
      </template>
      <template #cell(actions)="{ item }">
        <div class="d-flex gap-2">
          <BButton size="sm" variant="info" @click="openEdit(item)">Editar</BButton>
          <BButton size="sm" :variant="item.active ? 'warning' : 'success'" @click="toggle(item)">
            {{ item.active ? "Desactivar" : "Activar" }}
          </BButton>
        </div>
      </template>
    </BTable>

    <BModal v-model="showModal" :title="isEditing ? 'Editar cargo' : 'Nuevo cargo'" hide-footer>
      <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
      <div class="mb-3">
        <label class="form-label">Nombre</label>
        <BFormInput v-model="form.name" />
      </div>
      <div class="mb-3">
        <label class="form-label">Slug</label>
        <BFormInput v-model="form.slug" />
      </div>
      <div class="mb-3">
        <label class="form-label">Descripción</label>
        <BFormTextarea v-model="form.description" rows="2" />
      </div>
      <div class="mb-3">
        <BFormCheckbox v-model="form.active">Activo</BFormCheckbox>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          {{ saving ? "Guardando..." : "Guardar" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>

