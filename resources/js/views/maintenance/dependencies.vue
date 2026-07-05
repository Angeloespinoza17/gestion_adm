<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

const emptyForm = () => ({
  id: null,
  code: "",
  name: "",
  parent_dependency_id: null,
  distribution: "",
  sector: "",
  zone: "",
  usage: "",
  distribution_code: "",
  floor_code: "",
  dependency_code: "",
  numbering: "",
  active: true,
  notes: "",
});

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      saving: false,
      showFormModal: false,
      search: "",
      parentFilter: "",
      parentDependencySearch: "",
      dependencies: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      catalogs: {
        distributions: [],
        sectors: [],
        zones: [],
        usages: [],
        physical_dependencies: [],
        total: 0,
        active: 0,
        associated: 0,
        unassociated: 0,
      },
      form: emptyForm(),
      error: null,
      success: null,
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    physicalDependencies() {
      return this.catalogs.physical_dependencies || [];
    },
    selectedParentDependency() {
      if (!this.form.parent_dependency_id) return null;

      return this.physicalDependencies.find(
        (dependency) => Number(dependency.id) === Number(this.form.parent_dependency_id)
      ) || null;
    },
    associatedPercent() {
      if (!this.catalogs.total) return 0;

      return Math.round((Number(this.catalogs.associated || 0) / Number(this.catalogs.total)) * 100);
    },
    statCards() {
      return [
        {
          label: "Total áreas",
          value: this.catalogs.total,
          detail: "Puntos técnicos registrados",
          icon: "mdi-tools",
          tone: "blue",
        },
        {
          label: "Activas",
          value: this.catalogs.active,
          detail: "Disponibles para OT",
          icon: "mdi-check-circle-outline",
          tone: "green",
        },
        {
          label: "Asociadas",
          value: this.catalogs.associated,
          detail: `${this.associatedPercent}% del total`,
          icon: "mdi-link-variant",
          tone: "amber",
        },
        {
          label: "Sin dependencia",
          value: this.catalogs.unassociated,
          detail: "Requieren ubicación física",
          icon: "mdi-map-marker-alert-outline",
          tone: "slate",
        },
      ];
    },
    activeFiltersCount() {
      return [this.search, this.parentFilter].filter(Boolean).length;
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadDependencies();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/maintenance/catalogs");
      this.catalogs = response.data;
    },
    async loadDependencies(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get("/api/maintenance/dependencies", {
          params: {
            page,
            search: this.search,
            parent_dependency_id: this.parentFilter || null,
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
    async refreshView() {
      await this.loadCatalogs();
      await this.loadDependencies(this.pagination.current_page);
    },
    async saveDependency() {
      this.saving = true;
      this.error = null;
      this.success = null;
      this.syncParentDependency();

      try {
        const payload = {
          ...this.form,
          parent_dependency_id: this.form.parent_dependency_id || null,
          numbering: this.form.numbering || null,
        };

        const response = this.isEditing
          ? await axios.put(`/api/maintenance/dependencies/${this.form.id}`, payload)
          : await axios.post("/api/maintenance/dependencies", payload);

        this.success = response.data.message;
        this.showFormModal = false;
        this.resetForm();
        await this.loadCatalogs();
        await this.loadDependencies(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    openCreateModal() {
      this.resetForm();
      this.error = null;
      this.success = null;
      this.showFormModal = true;
    },
    editDependency(dependency) {
      this.form = {
        ...emptyForm(),
        ...dependency,
        parent_dependency_id: dependency.parent_dependency_id || dependency.parent_dependency?.id || null,
        numbering: dependency.numbering ?? "",
      };
      this.parentDependencySearch = dependency.parent_dependency
        ? this.dependencyLabel(dependency.parent_dependency)
        : "";
      this.error = null;
      this.success = null;
      this.showFormModal = true;
    },
    async deleteDependency(dependency) {
      if (!confirm(`¿Eliminar el área técnica ${dependency.code}?`)) return;

      try {
        const response = await axios.delete(`/api/maintenance/dependencies/${dependency.id}`);
        this.success = response.data.message;
        await this.loadCatalogs();
        await this.loadDependencies(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      }
    },
    resetForm() {
      this.form = emptyForm();
      this.parentDependencySearch = "";
    },
    closeFormModal() {
      this.showFormModal = false;
      this.error = null;
      this.resetForm();
    },
    handleFormModalHidden() {
      this.error = null;
      this.resetForm();
    },
    resetFilters() {
      this.search = "";
      this.parentFilter = "";
      this.loadDependencies();
    },
    clearParentDependency() {
      this.form.parent_dependency_id = null;
      this.parentDependencySearch = "";
    },
    syncParentDependency() {
      const search = String(this.parentDependencySearch || "").trim();

      if (!search) {
        this.form.parent_dependency_id = null;
        return;
      }

      const match = this.physicalDependencies.find(
        (dependency) => this.dependencyLabel(dependency) === search
      );

      this.form.parent_dependency_id = match ? match.id : this.form.parent_dependency_id || null;
    },
    suggestCode() {
      const parent = this.selectedParentDependency;
      const prefix = parent?.code || this.slugCode(this.form.distribution || this.form.sector || "TEC");
      const area = this.slugCode(this.form.dependency_code || this.form.name || "AREA").slice(0, 4) || "AREA";
      const number = this.form.numbering
        ? String(this.form.numbering).padStart(2, "0")
        : String((this.catalogs.total || 0) + 1).padStart(3, "0");

      this.form.code = `${prefix}-AT-${area}${number}`;
    },
    dependencyLabel(dependency) {
      if (!dependency) return "";

      return [
        dependency.code,
        dependency.name,
        dependency.sector || dependency.distribution || dependency.zone,
      ].filter(Boolean).join(" · ");
    },
    technicalLocation(dependency) {
      return [
        dependency.distribution,
        dependency.sector,
        dependency.zone,
        dependency.usage,
      ].filter(Boolean).join(" · ") || "-";
    },
    slugCode(value) {
      return String(value || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-zA-Z0-9]+/g, "")
        .toUpperCase();
    },
    formatError(error) {
      const errors = error.response?.data?.errors;

      if (errors) {
        return Object.values(errors).flat().join(" ");
      }

      return error.response?.data?.message || error.message || "Error desconocido";
    },
  },
};
</script>

<template>
  <Layout>
    <div class="technical-page">
      <div class="technical-header">
        <div>
          <span class="technical-eyebrow">Mantención</span>
          <h4>Áreas técnicas</h4>
          <p>Administra tableros, cajas de red, equipos fijos y puntos técnicos asociados a dependencias físicas.</p>
        </div>
        <div class="technical-header-actions">
          <button class="technical-secondary-button" type="button" @click="refreshView">
            <i class="mdi mdi-refresh"></i>
            Actualizar
          </button>
          <button class="technical-primary-button" type="button" @click="openCreateModal">
            <i class="mdi mdi-plus"></i>
            Nueva área
          </button>
        </div>
      </div>

      <BAlert v-if="error && !showFormModal" show variant="danger" class="mb-3">{{ error }}</BAlert>
      <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

      <div class="technical-helper">
        <i class="mdi mdi-information-outline"></i>
        <span>Las áreas técnicas no son espacios reservables. Asociarlas a una dependencia física mejora la trazabilidad en OT, inventario y mantención.</span>
      </div>

      <div class="technical-stat-grid">
        <div
          v-for="card in statCards"
          :key="card.label"
          class="technical-stat-card"
          :class="`technical-stat-card--${card.tone}`"
        >
          <div class="technical-stat-icon">
            <i :class="`mdi ${card.icon}`"></i>
          </div>
          <div>
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
            <small>{{ card.detail }}</small>
          </div>
        </div>
      </div>

      <section class="technical-panel">
        <div class="technical-panel-head">
          <div>
            <span class="technical-eyebrow">Listado</span>
            <h5>Áreas técnicas registradas</h5>
          </div>
          <div class="technical-filter-count" :class="{ 'is-active': activeFiltersCount > 0 }">
            {{ activeFiltersCount }} filtros
          </div>
        </div>

        <div class="technical-filters">
          <label class="technical-filter-field technical-filter-field--search">
            <span>Búsqueda</span>
            <input
              v-model="search"
              type="search"
              placeholder="Código, nombre, dependencia, ubicación..."
              @keyup.enter="loadDependencies()"
            />
          </label>
          <label class="technical-filter-field">
            <span>Dependencia asociada</span>
            <select v-model="parentFilter">
              <option value="">Todas las dependencias</option>
              <option v-for="dependency in physicalDependencies" :key="dependency.id" :value="dependency.id">
                {{ dependencyLabel(dependency) }}
              </option>
            </select>
          </label>
          <div class="technical-filter-actions">
            <button class="technical-primary-button" type="button" @click="loadDependencies()">
              <i class="mdi mdi-filter-outline"></i>
              Buscar
            </button>
            <button class="technical-secondary-button" type="button" @click="resetFilters">
              Limpiar
            </button>
          </div>
        </div>

        <div class="technical-table-wrap">
          <table class="technical-table">
            <thead>
              <tr>
                <th class="technical-col-code">Código</th>
                <th class="technical-col-name">Área técnica</th>
                <th class="technical-col-parent">Dependencia asociada</th>
                <th class="technical-col-location">Ubicación técnica</th>
                <th class="technical-col-internal">Codificación</th>
                <th class="technical-col-status">Estado</th>
                <th class="technical-col-actions">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7">
                  <div class="technical-empty-state">Cargando áreas técnicas...</div>
                </td>
              </tr>
              <tr v-else-if="dependencies.length === 0">
                <td colspan="7">
                  <div class="technical-empty-state">No hay áreas técnicas registradas.</div>
                </td>
              </tr>
              <tr v-for="dependency in dependencies" :key="dependency.id">
                <td class="technical-cell-center">
                  <span class="technical-code-chip">{{ dependency.code }}</span>
                </td>
                <td>
                  <div class="technical-name">{{ dependency.name }}</div>
                  <div v-if="dependency.notes" class="technical-note">{{ dependency.notes }}</div>
                </td>
                <td>
                  <div v-if="dependency.parent_dependency" class="technical-parent">
                    <span>{{ dependency.parent_dependency.name }}</span>
                    <small>{{ dependency.parent_dependency.code }}</small>
                  </div>
                  <span v-else class="technical-pill technical-pill--warning">Sin dependencia</span>
                </td>
                <td>
                  <div class="technical-location">{{ technicalLocation(dependency) }}</div>
                </td>
                <td>
                  <div class="technical-code-list">
                    <span v-if="dependency.distribution_code">Dist. {{ dependency.distribution_code }}</span>
                    <span v-if="dependency.floor_code">Piso {{ dependency.floor_code }}</span>
                    <span v-if="dependency.dependency_code">Tipo {{ dependency.dependency_code }}</span>
                    <span v-if="dependency.numbering">Nro. {{ dependency.numbering }}</span>
                    <span v-if="!dependency.distribution_code && !dependency.floor_code && !dependency.dependency_code && !dependency.numbering">-</span>
                  </div>
                </td>
                <td class="technical-cell-center">
                  <span class="technical-pill" :class="dependency.active ? 'technical-pill--active' : 'technical-pill--inactive'">
                    {{ dependency.active ? "Activa" : "Inactiva" }}
                  </span>
                </td>
                <td>
                  <div class="technical-actions">
                    <button class="technical-edit-button" type="button" @click="editDependency(dependency)">
                      Editar
                    </button>
                    <button class="technical-danger-button" type="button" @click="deleteDependency(dependency)">
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="technical-pagination">
          <span>Total: {{ pagination.total }}</span>
          <div class="technical-pagination-actions">
            <button type="button" :disabled="pagination.current_page <= 1" @click="loadDependencies(pagination.current_page - 1)">
              Anterior
            </button>
            <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <button type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadDependencies(pagination.current_page + 1)">
              Siguiente
            </button>
          </div>
        </div>
      </section>
    </div>

    <BModal
      v-model="showFormModal"
      :title="isEditing ? 'Editar área técnica' : 'Nueva área técnica'"
      title-class="technical-modal-title"
      header-class="technical-modal-header"
      body-class="technical-modal-body p-0"
      modal-class="technical-modal"
      size="xl"
      scrollable
      hide-footer
      centered
      @hidden="handleFormModalHidden"
    >
      <form class="technical-form" @submit.prevent="saveDependency">
        <div class="technical-modal-scroll">
          <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

          <section class="technical-form-section">
            <div class="technical-form-section-head">
              <i class="mdi mdi-identifier"></i>
              <div>
                <h6>Identificación</h6>
                <span>Nombre, código y estado operativo del punto técnico</span>
              </div>
            </div>

            <div class="technical-form-grid technical-form-grid--two">
              <label class="technical-form-field">
                <span>Código</span>
                <div class="technical-input-group">
                  <input v-model="form.code" type="text" placeholder="Ej: DEP-003-AT-TAB01" required />
                  <button type="button" @click="suggestCode">Sugerir</button>
                </div>
              </label>

              <label class="technical-form-field">
                <span>Nombre del área técnica</span>
                <input v-model="form.name" type="text" placeholder="Ej: Tablero eléctrico laboratorio" required />
              </label>

              <label class="technical-form-field technical-form-field--wide">
                <span>Dependencia asociada</span>
                <div class="technical-input-group">
                  <input
                    v-model="parentDependencySearch"
                    type="text"
                    list="physical-dependencies"
                    placeholder="Buscar por código, nombre o sector"
                    @change="syncParentDependency"
                  />
                  <button type="button" @click="clearParentDependency">Limpiar</button>
                </div>
                <small>Opcional, pero recomendado para ubicar el activo en OT e inventario.</small>
                <datalist id="physical-dependencies">
                  <option v-for="dependency in physicalDependencies" :key="dependency.id" :value="dependencyLabel(dependency)" />
                </datalist>
              </label>

              <label class="technical-switch-field">
                <input id="technical-area-active" v-model="form.active" type="checkbox" />
                <span>Área técnica activa</span>
              </label>
            </div>
          </section>

          <section class="technical-form-section">
            <div class="technical-form-section-head">
              <i class="mdi mdi-map-marker-outline"></i>
              <div>
                <h6>Ubicación técnica</h6>
                <span>Datos descriptivos para encontrar el punto de trabajo</span>
              </div>
            </div>

            <div class="technical-form-grid technical-form-grid--four">
              <label class="technical-form-field">
                <span>Distribución</span>
                <input v-model="form.distribution" type="text" list="technical-distributions" />
                <datalist id="technical-distributions">
                  <option v-for="item in catalogs.distributions" :key="item" :value="item" />
                </datalist>
              </label>

              <label class="technical-form-field">
                <span>Sector</span>
                <input v-model="form.sector" type="text" list="technical-sectors" />
                <datalist id="technical-sectors">
                  <option v-for="item in catalogs.sectors" :key="item" :value="item" />
                </datalist>
              </label>

              <label class="technical-form-field">
                <span>Zona</span>
                <input v-model="form.zone" type="text" list="technical-zones" />
                <datalist id="technical-zones">
                  <option v-for="item in catalogs.zones" :key="item" :value="item" />
                </datalist>
              </label>

              <label class="technical-form-field">
                <span>Tipo / uso técnico</span>
                <input v-model="form.usage" type="text" list="technical-usages" />
                <datalist id="technical-usages">
                  <option v-for="item in catalogs.usages" :key="item" :value="item" />
                </datalist>
              </label>
            </div>
          </section>

          <section class="technical-form-section">
            <div class="technical-form-section-head">
              <i class="mdi mdi-barcode"></i>
              <div>
                <h6>Codificación interna</h6>
                <span>Campos auxiliares para mantener códigos consistentes</span>
              </div>
            </div>

            <div class="technical-form-grid technical-form-grid--four">
              <label class="technical-form-field">
                <span>Cod. dist.</span>
                <input v-model="form.distribution_code" type="text" />
              </label>

              <label class="technical-form-field">
                <span>Cod. piso</span>
                <input v-model="form.floor_code" type="text" />
              </label>

              <label class="technical-form-field">
                <span>Cod. técnico</span>
                <input v-model="form.dependency_code" type="text" placeholder="TAB, RED, AUDIO" />
              </label>

              <label class="technical-form-field">
                <span>Número</span>
                <input v-model="form.numbering" type="number" min="0" />
              </label>

              <label class="technical-form-field technical-form-field--wide">
                <span>Notas</span>
                <textarea v-model="form.notes" rows="3" placeholder="Observaciones para mantención"></textarea>
              </label>
            </div>
          </section>
        </div>

        <div class="technical-modal-footer">
          <button class="technical-secondary-button" type="button" @click="closeFormModal">Cancelar</button>
          <button class="technical-primary-button" type="submit" :disabled="saving">
            {{ saving ? "Guardando..." : isEditing ? "Actualizar área" : "Crear área" }}
          </button>
        </div>
      </form>
    </BModal>
  </Layout>
</template>

<style scoped>
.technical-page {
  padding: 4px 0 24px;
}

.technical-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  padding: 18px 0 16px;
  border-bottom: 1px solid #e3ebfb;
  margin-bottom: 18px;
}

.technical-eyebrow {
  display: block;
  color: #6d7690;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0;
  line-height: 1.2;
  text-transform: uppercase;
}

.technical-header h4,
.technical-panel-head h5 {
  margin: 4px 0 0;
  color: #303848;
  font-weight: 700;
  letter-spacing: 0;
}

.technical-header p {
  margin: 8px 0 0;
  color: #717b94;
  font-size: 15px;
  font-weight: 400;
}

.technical-header-actions,
.technical-filter-actions,
.technical-actions,
.technical-pagination-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.technical-primary-button,
.technical-secondary-button,
.technical-edit-button,
.technical-danger-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 40px;
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.technical-primary-button {
  color: #fff;
  background: #5b74df;
  border-color: #5b74df;
}

.technical-primary-button:hover {
  color: #fff;
  background: #4f66ca;
  border-color: #4f66ca;
}

.technical-primary-button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.technical-secondary-button {
  color: #566079;
  background: #fff;
  border-color: #b9c3d8;
}

.technical-secondary-button:hover {
  color: #384154;
  background: #f5f7fb;
  border-color: #8d99b2;
}

.technical-helper {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  margin-bottom: 18px;
  color: #53607a;
  background: rgba(238, 244, 255, 0.72);
  border: 1px solid #d8e5fb;
  border-radius: 8px;
  font-size: 14px;
}

.technical-helper i {
  color: #3152c9;
  font-size: 20px;
}

.technical-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.technical-stat-card {
  display: grid;
  grid-template-columns: 46px minmax(0, 1fr);
  align-items: center;
  gap: 14px;
  min-height: 116px;
  padding: 20px;
  border: 1px solid #dfebfb;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: 0 18px 42px rgba(63, 84, 120, 0.06);
}

.technical-stat-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  border-radius: 8px;
  font-size: 23px;
}

.technical-stat-card span,
.technical-stat-card small {
  display: block;
}

.technical-stat-card span {
  color: #6d7690;
  font-size: 14px;
  font-weight: 600;
}

.technical-stat-card strong {
  display: block;
  margin-top: 4px;
  color: #303848;
  font-size: 28px;
  font-weight: 700;
  line-height: 1;
}

.technical-stat-card small {
  margin-top: 6px;
  color: #7b849c;
  font-size: 13px;
}

.technical-stat-card--blue .technical-stat-icon {
  color: #3152c9;
  background: #eef4ff;
}

.technical-stat-card--green .technical-stat-icon {
  color: #047857;
  background: #ecfdf5;
}

.technical-stat-card--amber .technical-stat-icon {
  color: #b45309;
  background: #fffbeb;
}

.technical-stat-card--slate .technical-stat-icon {
  color: #475569;
  background: #f8fafc;
}

.technical-panel {
  padding: 22px;
  border: 1px solid #dfebfb;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.84);
  box-shadow: 0 18px 44px rgba(63, 84, 120, 0.06);
}

.technical-panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.technical-filter-count {
  display: inline-flex;
  align-items: center;
  min-height: 30px;
  padding: 0 12px;
  border: 1px solid #dce5f4;
  border-radius: 999px;
  color: #647089;
  background: #f4f7fb;
  font-size: 13px;
  font-weight: 600;
}

.technical-filter-count.is-active {
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
}

.technical-filters {
  display: grid;
  grid-template-columns: minmax(280px, 1.5fr) minmax(280px, 1.2fr) auto;
  gap: 12px;
  align-items: end;
  margin-bottom: 18px;
}

.technical-filter-field,
.technical-form-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
  margin: 0;
}

.technical-filter-field span,
.technical-form-field span {
  color: #4c5568;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
}

.technical-filter-field input,
.technical-filter-field select,
.technical-form-field input,
.technical-form-field textarea {
  width: 100%;
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid #dce5f4;
  border-radius: 8px;
  color: #303848;
  background: #fff;
  font-size: 14px;
  font-weight: 400;
  outline: none;
}

.technical-form-field textarea {
  min-height: 88px;
  padding-top: 12px;
  resize: vertical;
}

.technical-filter-field input:focus,
.technical-filter-field select:focus,
.technical-form-field input:focus,
.technical-form-field textarea:focus {
  border-color: #9db1f8;
  box-shadow: 0 0 0 3px rgba(91, 116, 223, 0.12);
}

.technical-filter-actions {
  justify-content: flex-end;
}

.technical-table-wrap {
  overflow-x: auto;
  border-top: 1px solid #e2eaf8;
}

.technical-table {
  width: 100%;
  min-width: 1200px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

.technical-table th {
  padding: 16px 14px;
  color: #727b92;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0;
  line-height: 1.25;
  text-align: center;
  text-transform: uppercase;
  border-bottom: 1px solid #dce7f7;
}

.technical-table td {
  padding: 18px 14px;
  color: #364154;
  font-size: 14px;
  font-weight: 400;
  line-height: 1.35;
  vertical-align: middle;
  border-bottom: 1px solid #e5edf9;
}

.technical-col-code {
  width: 138px;
}

.technical-col-name {
  width: 240px;
}

.technical-col-parent {
  width: 230px;
}

.technical-col-location {
  width: 230px;
}

.technical-col-internal {
  width: 200px;
}

.technical-col-status {
  width: 110px;
}

.technical-col-actions {
  width: 250px;
  text-align: center;
}

.technical-cell-center {
  text-align: center;
}

.technical-code-chip,
.technical-pill,
.technical-code-list span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  border: 1px solid transparent;
  line-height: 1;
  white-space: nowrap;
}

.technical-code-chip {
  min-width: 86px;
  min-height: 30px;
  padding: 0 12px;
  color: #3152c9;
  background: #eef4ff;
  border-color: #c7d7fe;
  font-size: 12px;
  font-weight: 600;
}

.technical-name {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  color: #303848;
  font-weight: 600;
}

.technical-note,
.technical-location,
.technical-parent small {
  color: #68728b;
  font-size: 13px;
}

.technical-note {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  margin-top: 4px;
}

.technical-parent {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.technical-parent span {
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  color: #303848;
  font-weight: 500;
}

.technical-code-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.technical-code-list span {
  min-height: 26px;
  padding: 0 10px;
  color: #475569;
  background: #f8fafc;
  border-color: #d7dee9;
  font-size: 12px;
  font-weight: 500;
}

.technical-pill {
  min-width: 82px;
  min-height: 30px;
  padding: 0 12px;
  font-size: 12px;
  font-weight: 600;
}

.technical-pill--active {
  color: #047857;
  background: #ecfdf5;
  border-color: #a7f3d0;
}

.technical-pill--inactive {
  color: #475569;
  background: #f8fafc;
  border-color: #d7dee9;
}

.technical-pill--warning {
  color: #b45309;
  background: #fffbeb;
  border-color: #fcd34d;
}

.technical-actions {
  justify-content: center;
  flex-wrap: nowrap;
  gap: 10px;
}

.technical-actions .technical-edit-button,
.technical-actions .technical-danger-button {
  min-width: 96px;
  min-height: 36px;
  padding: 0 18px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 700;
  white-space: nowrap;
}

.technical-edit-button {
  color: #fff;
  background: #f7b84b;
  border-color: #f7b84b;
}

.technical-edit-button:hover {
  color: #fff;
  background: #f5aa25;
  border-color: #f5aa25;
}

.technical-danger-button {
  color: #dc2626;
  background: #fff;
  border-color: #fecaca;
}

.technical-danger-button:hover {
  color: #b91c1c;
  background: #fef2f2;
  border-color: #fca5a5;
}

.technical-empty-state {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 90px;
  color: #7a849a;
  font-weight: 500;
}

.technical-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 16px;
  color: #717b94;
  font-size: 13px;
}

.technical-pagination-actions button {
  min-height: 36px;
  padding: 0 12px;
  border: 1px solid #cfd8ea;
  border-radius: 8px;
  color: #566079;
  background: #fff;
  font-weight: 600;
}

.technical-pagination-actions button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.technical-pagination-actions span {
  color: #303848;
  font-weight: 600;
}

:deep(.technical-modal .modal-dialog) {
  max-width: min(1040px, calc(100vw - 32px));
}

:deep(.technical-modal .modal-content) {
  overflow: hidden;
  border: 1px solid #dce5f4;
  border-radius: 8px;
}

:deep(.technical-modal-header) {
  min-height: 68px;
  padding: 18px 24px;
  background: #fff;
  border-bottom: 1px solid #e2eaf8;
}

:deep(.technical-modal-title) {
  color: #303848;
  font-size: 20px;
  font-weight: 700;
}

.technical-form {
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 110px);
  background: #f8fafc;
}

.technical-modal-scroll {
  overflow-y: auto;
  padding: 18px 22px;
}

.technical-form-section {
  padding: 18px;
  border: 1px solid #e0e8f6;
  border-radius: 8px;
  background: #fff;
}

.technical-form-section + .technical-form-section {
  margin-top: 14px;
}

.technical-form-section-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.technical-form-section-head i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  color: #3152c9;
  background: #eef4ff;
  font-size: 20px;
}

.technical-form-section-head h6 {
  margin: 0;
  color: #303848;
  font-size: 15px;
  font-weight: 700;
}

.technical-form-section-head span,
.technical-form-field small {
  color: #778199;
  font-size: 12px;
  font-weight: 400;
}

.technical-form-grid {
  display: grid;
  gap: 14px;
}

.technical-form-grid--two {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.technical-form-grid--four {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.technical-form-field--wide {
  grid-column: 1 / -1;
}

.technical-input-group {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
}

.technical-input-group input {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}

.technical-input-group button {
  min-width: 96px;
  border: 1px solid #b9c3d8;
  border-left: 0;
  border-radius: 0 8px 8px 0;
  color: #566079;
  background: #f8fafc;
  font-weight: 600;
}

.technical-switch-field {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  margin: 0;
  padding: 0 14px;
  border: 1px solid #dce5f4;
  border-radius: 8px;
  color: #303848;
  background: #fff;
  font-weight: 500;
}

.technical-switch-field input {
  width: 18px;
  height: 18px;
  accent-color: #5b74df;
}

.technical-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px;
  border-top: 1px solid #e2eaf8;
  background: #fff;
}

@media (max-width: 1200px) {
  .technical-stat-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .technical-filters,
  .technical-form-grid--four {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .technical-filter-actions {
    justify-content: flex-start;
  }
}

@media (max-width: 768px) {
  .technical-header,
  .technical-panel-head,
  .technical-pagination {
    flex-direction: column;
    align-items: stretch;
  }

  .technical-header-actions,
  .technical-filter-actions,
  .technical-actions,
  .technical-pagination-actions {
    flex-wrap: wrap;
  }

  .technical-stat-grid,
  .technical-filters,
  .technical-form-grid--two,
  .technical-form-grid--four {
    grid-template-columns: 1fr;
  }

  .technical-panel,
  .technical-form-section {
    padding: 16px;
  }

  .technical-modal-scroll,
  .technical-modal-footer {
    padding-left: 16px;
    padding-right: 16px;
  }
}
</style>
