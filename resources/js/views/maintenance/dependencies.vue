<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

const emptyForm = () => ({
  id: null,
  code: "",
  name: "",
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
      search: "",
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
        total: 0,
        active: 0,
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
    async saveDependency() {
      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        const payload = {
          ...this.form,
          numbering: this.form.numbering || null,
        };

        const response = this.isEditing
          ? await axios.put(`/api/maintenance/dependencies/${this.form.id}`, payload)
          : await axios.post("/api/maintenance/dependencies", payload);

        this.success = response.data.message;
        this.resetForm();
        await this.loadCatalogs();
        await this.loadDependencies(this.pagination.current_page);
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    editDependency(dependency) {
      this.form = {
        ...emptyForm(),
        ...dependency,
        numbering: dependency.numbering ?? "",
      };
      window.scrollTo({ top: 0, behavior: "smooth" });
    },
    async deleteDependency(dependency) {
      if (!confirm(`¿Eliminar la dependencia ${dependency.code}?`)) return;

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
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0 font-size-18">Activos técnicos</h4>
          <div class="page-title-right">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item active">Mantención</li>
              <li class="breadcrumb-item active">Activos técnicos</li>
            </ol>
          </div>
        </div>
      </BCol>
    </BRow>

    <BAlert show variant="info" class="mb-3">
      Aquí se gestionan tableros, equipos y elementos técnicos de mantención. Los recintos reservables se administran en
      <router-link to="/spaces/dependencies" class="alert-link ms-1">Dependencias</router-link>.
    </BAlert>

    <BRow>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="d-flex align-items-center">
              <div class="avatar-sm rounded-circle bg-primary bg-soft me-3">
                <span class="avatar-title rounded-circle bg-primary bg-soft text-primary font-size-18">
                  <i class="bx bx-buildings"></i>
                </span>
              </div>
              <div>
                <p class="text-muted mb-1">Activos técnicos</p>
                <h4 class="mb-0">{{ catalogs.total }}</h4>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="d-flex align-items-center">
              <div class="avatar-sm rounded-circle bg-success bg-soft me-3">
                <span class="avatar-title rounded-circle bg-success bg-soft text-success font-size-18">
                  <i class="bx bx-check-circle"></i>
                </span>
              </div>
              <div>
                <p class="text-muted mb-1">Activas</p>
                <h4 class="mb-0">{{ catalogs.active }}</h4>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="d-flex align-items-center">
              <div class="avatar-sm rounded-circle bg-info bg-soft me-3">
                <span class="avatar-title rounded-circle bg-info bg-soft text-info font-size-18">
                  <i class="bx bx-map"></i>
                </span>
              </div>
              <div>
                <p class="text-muted mb-1">Zonas</p>
                <h4 class="mb-0">{{ catalogs.zones.length }}</h4>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <BCol lg="4">
        <BCard no-body>
          <BCardBody>
            <h5 class="card-title mb-3">{{ isEditing ? "Editar activo técnico" : "Nuevo activo técnico" }}</h5>

            <BAlert v-if="error" show variant="danger">{{ error }}</BAlert>
            <BAlert v-if="success" show variant="success">{{ success }}</BAlert>

            <form @submit.prevent="saveDependency">
              <div class="mb-3">
                <label class="form-label">Código</label>
                <input v-model="form.code" type="text" class="form-control" placeholder="Ej: AD1-AD1" required />
              </div>

              <div class="mb-3">
                <label class="form-label">Activo técnico</label>
                <input v-model="form.name" type="text" class="form-control" placeholder="Ej: Tablero eléctrico patio norte" required />
              </div>

              <div class="mb-3">
                <label class="form-label">Distribución</label>
                <input v-model="form.distribution" type="text" class="form-control" list="distributions" />
                <datalist id="distributions">
                  <option v-for="item in catalogs.distributions" :key="item" :value="item" />
                </datalist>
              </div>

              <div class="mb-3">
                <label class="form-label">Sector</label>
                <input v-model="form.sector" type="text" class="form-control" list="sectors" />
                <datalist id="sectors">
                  <option v-for="item in catalogs.sectors" :key="item" :value="item" />
                </datalist>
              </div>

              <div class="mb-3">
                <label class="form-label">Zona</label>
                <input v-model="form.zone" type="text" class="form-control" list="zones" />
                <datalist id="zones">
                  <option v-for="item in catalogs.zones" :key="item" :value="item" />
                </datalist>
              </div>

              <div class="mb-3">
                <label class="form-label">Uso</label>
                <input v-model="form.usage" type="text" class="form-control" list="usages" />
                <datalist id="usages">
                  <option v-for="item in catalogs.usages" :key="item" :value="item" />
                </datalist>
              </div>

              <BRow>
                <BCol md="4">
                  <div class="mb-3">
                    <label class="form-label">Cod. distribución</label>
                    <input v-model="form.distribution_code" type="text" class="form-control" />
                  </div>
                </BCol>
                <BCol md="4">
                  <div class="mb-3">
                    <label class="form-label">Cod. piso</label>
                    <input v-model="form.floor_code" type="text" class="form-control" />
                  </div>
                </BCol>
                <BCol md="4">
                  <div class="mb-3">
                    <label class="form-label">Numeración</label>
                    <input v-model="form.numbering" type="number" min="0" class="form-control" />
                  </div>
                </BCol>
              </BRow>

              <div class="mb-3">
                <label class="form-label">Cod. dependencia</label>
                <input v-model="form.dependency_code" type="text" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea v-model="form.notes" class="form-control" rows="3"></textarea>
              </div>

              <div class="form-check form-switch mb-3">
                <input id="dependency-active" v-model="form.active" class="form-check-input" type="checkbox" />
                <label class="form-check-label" for="dependency-active">Dependencia activa</label>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit" :disabled="saving">
                  {{ saving ? "Guardando..." : isEditing ? "Actualizar" : "Crear" }}
                </button>
                <button class="btn btn-light" type="button" @click="resetForm">Limpiar</button>
              </div>
            </form>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="8">
        <BCard no-body>
          <BCardBody>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <h5 class="card-title mb-0">Listado de dependencias</h5>
              <div class="d-flex gap-2">
                <input v-model="search" type="search" class="form-control" placeholder="Buscar código, zona, sector..." @keyup.enter="loadDependencies()" />
                <button class="btn btn-outline-primary" type="button" @click="loadDependencies()">Buscar</button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-centered table-nowrap align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Código</th>
                    <th>Dependencia</th>
                    <th>Distribución</th>
                    <th>Sector</th>
                    <th>Zona</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="7" class="text-center text-muted py-4">Cargando dependencias...</td>
                  </tr>
                  <tr v-else-if="dependencies.length === 0">
                    <td colspan="7" class="text-center text-muted py-4">No hay dependencias registradas.</td>
                  </tr>
                  <tr v-for="dependency in dependencies" :key="dependency.id">
                    <td><strong>{{ dependency.code }}</strong></td>
                    <td>{{ dependency.name }}</td>
                    <td>{{ dependency.distribution || "-" }}</td>
                    <td>{{ dependency.sector || "-" }}</td>
                    <td>{{ dependency.zone || "-" }}</td>
                    <td>
                      <span class="badge" :class="dependency.active ? 'bg-success' : 'bg-secondary'">
                        {{ dependency.active ? "Activa" : "Inactiva" }}
                      </span>
                    </td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary me-2" type="button" @click="editDependency(dependency)">Editar</button>
                      <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteDependency(dependency)">Eliminar</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex align-items-center justify-content-between">
              <span class="text-muted">Total: {{ pagination.total }}</span>
              <div class="btn-group">
                <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page <= 1" @click="loadDependencies(pagination.current_page - 1)">
                  Anterior
                </button>
                <button class="btn btn-outline-secondary" type="button" disabled>
                  {{ pagination.current_page }} / {{ pagination.last_page }}
                </button>
                <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadDependencies(pagination.current_page + 1)">
                  Siguiente
                </button>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
