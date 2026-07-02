<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  name: "",
  year: "",
  starts_at: "",
  ends_at: "",
  is_active: false,
  is_closed: false,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      academicYears: [],
      showModal: false,
      form: emptyForm(),
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
        const response = await axios.get("/api/students/academic-years");
        this.academicYears = response.data.data || [];
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
        year: item.year,
        starts_at: item.starts_at || "",
        ends_at: item.ends_at || "",
        is_active: Boolean(item.is_active),
        is_closed: Boolean(item.is_closed),
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.isEditing) {
          await axios.put(`/api/students/academic-years/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/students/academic-years", this.form);
        }

        this.showModal = false;
        await this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.saving = false;
      }
    },
    async activate(item) {
      await axios.put(`/api/students/academic-years/${item.id}/activate`);
      this.load();
    },
    showErrorAlert(text) {
      return Swal.fire({ title: "Error", text, icon: "error" });
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
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Años académicos</h4>
        <div class="text-muted">Un solo año puede quedar activo a la vez.</div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/students" class="btn btn-outline-secondary">Volver</router-link>
        <BButton variant="primary" @click="openCreate">Nuevo año</BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <BCard>
      <BTable
        :items="academicYears"
        :busy="loading"
        responsive
        :fields="[
          { key: 'name', label: 'Nombre' },
          { key: 'year', label: 'Año' },
          { key: 'range', label: 'Rango' },
          { key: 'course_sections_count', label: 'Cursos' },
          { key: 'enrollments_count', label: 'Matrículas' },
          { key: 'status', label: 'Estado' },
          { key: 'actions', label: 'Acciones' },
        ]"
      >
        <template #table-busy>
          <LoadingState message="Cargando años académicos..." compact />
        </template>
        <template #cell(range)="{ item }">
          {{ item.starts_at || "-" }} / {{ item.ends_at || "-" }}
        </template>
        <template #cell(status)="{ item }">
          <div class="d-flex gap-2">
            <BBadge :variant="item.is_active ? 'success' : 'secondary'">{{ item.is_active ? "Activo" : "Inactivo" }}</BBadge>
            <BBadge :variant="item.is_closed ? 'dark' : 'light'">{{ item.is_closed ? "Cerrado" : "Abierto" }}</BBadge>
          </div>
        </template>
        <template #cell(actions)="{ item }">
          <div class="d-flex gap-2">
            <BButton size="sm" variant="outline-secondary" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="outline-primary" :disabled="item.is_active" @click="activate(item)">Activar</BButton>
          </div>
        </template>
      </BTable>
    </BCard>

    <BModal v-model="showModal" :title="isEditing ? 'Editar año académico' : 'Nuevo año académico'" hide-footer>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Año</label>
          <BFormInput v-model="form.year" type="number" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Inicio</label>
          <BFormInput v-model="form.starts_at" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Término</label>
          <BFormInput v-model="form.ends_at" type="date" />
        </div>
        <div class="col-md-6">
          <BFormCheckbox v-model="form.is_active">Dejar activo</BFormCheckbox>
        </div>
        <div class="col-md-6">
          <BFormCheckbox v-model="form.is_closed">Marcar cerrado</BFormCheckbox>
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
