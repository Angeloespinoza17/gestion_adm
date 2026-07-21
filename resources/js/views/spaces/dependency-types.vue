<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Swal from "sweetalert2";
import "./shared.css";

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
    summaryCards() {
      return [
        {
          label: "Tipos",
          value: this.formatInteger(this.types.length),
          detail: "clasificaciones visibles",
          icon: "bx-category",
          tone: "blue",
        },
        {
          label: "Activos",
          value: this.formatInteger(this.types.filter((item) => item.active).length),
          detail: "disponibles para catálogo",
          icon: "bx-check-circle",
          tone: "green",
        },
        {
          label: "Dependencias",
          value: this.formatInteger(
            this.types.reduce((total, item) => total + Number(item.dependencies_count || 0), 0)
          ),
          detail: "asociadas a tipos",
          icon: "bx-buildings",
          tone: "slate",
        },
      ];
    },
    hasActiveSearch() {
      return Boolean(this.search);
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
    resetFilters() {
      this.search = "";
      this.load();
    },
    formatInteger(value) {
      return Number(value || 0).toLocaleString("es-CL", {
        maximumFractionDigits: 0,
      });
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
    <div class="spaces-shell">
      <section class="spaces-hero">
        <div class="spaces-hero__body">
          <div class="spaces-eyebrow">Dependencias y reservas</div>
          <h4>Tipos de dependencia</h4>
          <p>Catálogo editable para clasificar salas, laboratorios, oficinas y recintos operativos.</p>
        </div>
        <div class="spaces-actions">
          <router-link to="/spaces/dependencies" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back"></i>
            <span>Dependencias</span>
          </router-link>
          <BButton v-if="canCreate" variant="primary" @click="openCreate">
            <i class="bx bx-plus"></i>
            <span>Nuevo tipo</span>
          </BButton>
        </div>
      </section>

      <div class="spaces-summary-grid">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="spaces-summary-card"
          :class="`spaces-summary-card--${card.tone}`"
        >
          <div class="spaces-summary-icon">
            <i :class="`bx ${card.icon}`"></i>
          </div>
          <div>
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>
      </div>

      <section class="spaces-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-eyebrow">Filtros</div>
            <h5 class="spaces-panel-title">Buscar clasificación</h5>
          </div>
        </div>
        <div class="spaces-filter-grid">
          <label class="spaces-field">
            <span>Buscar</span>
            <BFormInput v-model="search" placeholder="Nombre o descripción" @keyup.enter="load" />
          </label>
          <div class="spaces-filter-actions">
            <BButton variant="primary" :disabled="loading" @click="load">
              <i class="bx bx-search"></i>
              <span>Buscar</span>
            </BButton>
            <BButton variant="outline-secondary" :disabled="loading || !hasActiveSearch" @click="resetFilters">
              <i class="bx bx-x"></i>
              <span>Limpiar</span>
            </BButton>
          </div>
        </div>
      </section>

      <BAlert v-if="error" variant="danger" show class="mb-0">{{ error }}</BAlert>

      <section class="spaces-panel">
        <div class="spaces-panel-header">
          <div>
            <div class="spaces-eyebrow">Listado</div>
            <h5 class="spaces-panel-title">Tipos registrados</h5>
          </div>
          <div class="spaces-panel-meta">{{ formatInteger(types.length) }} resultados</div>
        </div>

        <div v-if="loading" class="spaces-empty-state">
          <LoadingState message="Cargando tipos de dependencia..." compact />
        </div>
        <div v-else class="spaces-table-wrap">
          <table class="table spaces-data-table spaces-data-table--compact">
            <thead>
              <tr>
                <th>Tipo</th>
                <th class="text-center">Dependencias</th>
                <th class="text-center">Estado</th>
                <th class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in types" :key="item.id">
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span
                      class="rounded-circle border"
                      :style="{ width: '0.75rem', height: '0.75rem', backgroundColor: item.color || '#adb5bd' }"
                    ></span>
                    <div>
                      <div class="spaces-table-title">{{ item.name }}</div>
                      <span class="spaces-table-subtitle">{{ item.description || "Sin descripción" }}</span>
                    </div>
                  </div>
                </td>
                <td class="text-center">
                  <span class="spaces-count-pill">{{ formatInteger(item.dependencies_count) }}</span>
                </td>
                <td class="text-center">
                  <span
                    class="spaces-status-pill"
                    :class="item.active ? 'spaces-status-pill--active' : 'spaces-status-pill--inactive'"
                  >
                    {{ item.active ? "Activo" : "Inactivo" }}
                  </span>
                </td>
                <td>
                  <div class="spaces-row-actions">
                    <BButton
                      v-if="canEdit"
                      size="sm"
                      variant="outline-info"
                      title="Editar"
                      aria-label="Editar tipo"
                      @click="openEdit(item)"
                    >
                      <i class="bx bx-edit"></i>
                      <span>Editar</span>
                    </BButton>
                    <BButton
                      v-if="canDelete"
                      size="sm"
                      variant="outline-danger"
                      title="Eliminar"
                      aria-label="Eliminar tipo"
                      @click="remove(item)"
                    >
                      <i class="bx bx-trash"></i>
                      <span>Eliminar</span>
                    </BButton>
                  </div>
                </td>
              </tr>
              <tr v-if="types.length === 0">
                <td colspan="4">
                  <div class="spaces-empty-state">
                    <i class="bx bx-category"></i>
                    <strong>No hay tipos para mostrar</strong>
                    <span>Registra una clasificación o limpia la búsqueda.</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

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
