<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import Swal from "sweetalert2";

const emptyForm = () => ({
  name: "",
  description: "",
  requires_attachment: false,
  allows_with_pay: true,
  allows_without_pay: true,
  allows_hourly: false,
  allows_half_day: false,
  requires_manager_approval: true,
  requires_direction_approval: false,
  requires_hr_approval: false,
  max_days: "",
  minimum_notice_days: "",
  allows_retroactive: false,
  affects_salary: false,
  affects_attendance: true,
  requires_replacement: false,
  active: true,
});

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      items: [],
      form: emptyForm(),
      editingId: null,
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permission-types");
        this.items = response.data.data || [];
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    edit(item) {
      this.editingId = item.id;
      this.form = {
        ...emptyForm(),
        ...item,
        max_days: item.max_days ?? "",
        minimum_notice_days: item.minimum_notice_days ?? "",
      };
    },
    reset() {
      this.editingId = null;
      this.form = emptyForm();
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        if (this.editingId) {
          await axios.put(`/api/staff/permission-types/${this.editingId}`, this.form);
        } else {
          await axios.post("/api/staff/permission-types", this.form);
        }

        this.reset();
        await this.load();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async toggleActive(item) {
      const result = await Swal.fire({
        title: item.active ? "Desactivar tipo" : "Activar tipo",
        text: item.name,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: item.active ? "Desactivar" : "Activar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      await axios.put(`/api/staff/permission-types/${item.id}/active`, {
        active: !item.active,
      });
      await this.load();
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (errors ? errors[Object.keys(errors)[0]]?.[0] : null) || error?.response?.data?.message || error?.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">Tipos de permiso</h4>
        <div class="text-muted">Configuración del flujo, restricciones y efectos administrativos.</div>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-5">
        <BCard :title="editingId ? 'Editar tipo' : 'Nuevo tipo'">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nombre</label>
              <BFormInput v-model="form.name" />
            </div>
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <BFormTextarea v-model="form.description" rows="3" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Máximo de días</label>
              <BFormInput v-model="form.max_days" type="number" min="0" step="0.5" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Anticipación mínima</label>
              <BFormInput v-model="form.minimum_notice_days" type="number" min="0" step="1" />
            </div>
            <div class="col-md-6"><BFormCheckbox v-model="form.requires_attachment">Requiere adjunto</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.allows_hourly">Permite horas</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.allows_half_day">Permite media jornada</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.allows_retroactive">Permite retroactivo</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.allows_with_pay">Permite con goce</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.allows_without_pay">Permite sin goce</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.requires_manager_approval">Aprueba jefatura</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.requires_direction_approval">Aprueba Dirección</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.requires_hr_approval">Aprueba RRHH</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.affects_salary">Afecta remuneración</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.affects_attendance">Afecta asistencia</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.requires_replacement">Requiere reemplazo</BFormCheckbox></div>
            <div class="col-md-6"><BFormCheckbox v-model="form.active">Activo</BFormCheckbox></div>
          </div>
          <div class="d-flex gap-2 mt-3">
            <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
            <BButton variant="outline-secondary" @click="reset">Limpiar</BButton>
          </div>
        </BCard>
      </div>

      <div class="col-xl-7">
        <BCard title="Listado">
          <LoadingState v-if="loading" message="Cargando tipos..." compact />
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Flujo</th>
                  <th>Modalidad</th>
                  <th>Activo</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in items" :key="item.id">
                  <td>
                    <div class="fw-semibold">{{ item.name }}</div>
                    <div class="text-muted small">{{ item.description || "Sin descripción" }}</div>
                  </td>
                  <td class="small">
                    {{ item.requires_manager_approval ? "Jefatura" : "-" }} /
                    {{ item.requires_direction_approval ? "Dirección" : "-" }} /
                    {{ item.requires_hr_approval ? "RRHH" : "-" }}
                  </td>
                  <td class="small">
                    {{ item.allows_hourly ? "Horas" : "-" }} /
                    {{ item.allows_half_day ? "Media jornada" : "-" }}
                  </td>
                  <td>
                    <span class="badge" :class="item.active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                      {{ item.active ? "Activo" : "Inactivo" }}
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <BButton variant="outline-primary" @click="edit(item)">Editar</BButton>
                      <BButton variant="outline-secondary" @click="toggleActive(item)">
                        {{ item.active ? "Desactivar" : "Activar" }}
                      </BButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
