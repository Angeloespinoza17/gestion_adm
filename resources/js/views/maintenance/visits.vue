<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

const emptyForm = () => ({
  id: null,
  maintenance_dependency_id: "",
  responsible: "",
  visit_date: new Date().toISOString().slice(0, 10),
  visit_time: "",
  visit_type: "Inspección",
  status: "Programada",
  notes: "",
});

export default {
  components: { Layout },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      success: null,
      showModalVisit: false,
      search: "",
      filters: {
        from: "",
        to: "",
        dependency_id: "",
        responsible: "",
        status: "",
        visit_type: "",
      },
      catalogs: {
        visit_types: ["Inspección", "Mantención", "Reunión", "Otro"],
        statuses: ["Programada", "En progreso", "Finalizada", "Cancelada"],
        review_statuses: ["OK", "No OK", "N/A"],
        responsibles: [],
        dependencies: [],
      },
      visits: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      form: emptyForm(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadVisits();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/maintenance/visits/catalogs");
      this.catalogs = response.data;
    },
    async loadVisits(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/maintenance/visits", {
          params: {
            page,
            search: this.search,
            ...this.filters,
          },
        });
        this.visits = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error cargando visitas";
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.error = null;
      this.success = null;
      this.form = emptyForm();
      this.showModalVisit = true;
    },
    editVisit(visit) {
      this.error = null;
      this.success = null;
      this.form = {
        ...emptyForm(),
        ...visit,
        maintenance_dependency_id: visit.maintenance_dependency_id || visit.dependency?.id || "",
        visit_date: String(visit.visit_date || "").slice(0, 10),
        visit_time: visit.visit_time ? String(visit.visit_time).slice(11, 16) : "",
      };
      this.showModalVisit = true;
    },
    async saveVisit() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        const payload = { ...this.form };
        if (!payload.visit_time) payload.visit_time = null;

        const response = this.isEditing
          ? await axios.put(`/api/maintenance/visits/${payload.id}`, payload)
          : await axios.post("/api/maintenance/visits", payload);

        this.success = response.data.message;
        this.showModalVisit = false;
        await this.loadVisits(this.pagination.current_page);
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message || error.message;
      } finally {
        this.saving = false;
      }
    },
    async deleteVisit(visit) {
      if (!confirm("¿Eliminar la visita?")) return;
      try {
        const response = await axios.delete(`/api/maintenance/visits/${visit.id}`);
        this.success = response.data.message;
        await this.loadVisits(this.pagination.current_page);
      } catch (error) {
        this.error = error.response?.data?.message || error.message;
      }
    },
    goChecklist(visit) {
      this.$router.push(`/maintenance/visits/${visit.id}/checklist`);
    },
    dependencyLabel(dep) {
      if (!dep) return "-";
      return `${dep.code} · ${dep.name}`;
    },
    formatDMY(value) {
      if (!value) return "-";
      const [y, m, d] = String(value).slice(0, 10).split("-");
      if (!y || !m || !d) return String(value);
      return `${d}-${m}-${y}`;
    },
  },
};
</script>

<template>
  <Layout>
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0 font-size-18">Planificación de visitas</h4>
          <div class="page-title-right">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item active">Gestión de mantención</li>
            </ol>
          </div>
        </div>
      </BCol>
    </BRow>

    <BCard no-body class="mb-3">
      <BCardBody>
        <div class="d-flex flex-wrap align-items-end gap-2">
          <button class="btn btn-primary" type="button" @click="openCreate">Nueva visita</button>
          <div class="flex-grow-1" style="min-width: 220px">
            <label class="form-label">Buscar dependencia</label>
            <input v-model="search" class="form-control" type="search" placeholder="Código o nombre..." @keyup.enter="loadVisits()" />
          </div>
          <div>
            <label class="form-label">Desde</label>
            <input v-model="filters.from" class="form-control" type="date" />
          </div>
          <div>
            <label class="form-label">Hasta</label>
            <input v-model="filters.to" class="form-control" type="date" />
          </div>
          <div style="min-width: 210px">
            <label class="form-label">Dependencia</label>
            <select v-model="filters.dependency_id" class="form-select">
              <option value="">Todas</option>
              <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">
                {{ dep.code }} · {{ dep.name }}
              </option>
            </select>
          </div>
          <div style="min-width: 180px">
            <label class="form-label">Responsable</label>
            <select v-model="filters.responsible" class="form-select">
              <option value="">Todos</option>
              <option v-for="p in catalogs.responsibles" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div style="min-width: 160px">
            <label class="form-label">Tipo</label>
            <select v-model="filters.visit_type" class="form-select">
              <option value="">Todos</option>
              <option v-for="t in catalogs.visit_types" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div style="min-width: 170px">
            <label class="form-label">Estado</label>
            <select v-model="filters.status" class="form-select">
              <option value="">Todos</option>
              <option v-for="s in catalogs.statuses" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <button class="btn btn-outline-primary" type="button" @click="loadVisits()">Filtrar</button>
        </div>
      </BCardBody>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>

    <BCard no-body>
      <BCardBody>
        <div class="table-responsive">
          <table class="table table-centered table-nowrap align-middle">
            <thead class="table-light">
              <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Dependencia</th>
                <th>Responsable</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7" class="text-center text-muted py-4">Cargando...</td>
              </tr>
              <tr v-else-if="visits.length === 0">
                <td colspan="7" class="text-center text-muted py-4">No hay visitas registradas.</td>
              </tr>
              <tr v-for="visit in visits" :key="visit.id">
                <td>{{ formatDMY(visit.visit_date) }}</td>
                <td>{{ visit.visit_time ? String(visit.visit_time).slice(11, 16) : "-" }}</td>
                <td>{{ dependencyLabel(visit.dependency) }}</td>
                <td>{{ visit.responsible }}</td>
                <td>{{ visit.visit_type }}</td>
                <td>{{ visit.status }}</td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary me-2" type="button" @click="goChecklist(visit)">Checklist</button>
                  <button class="btn btn-sm btn-outline-secondary me-2" type="button" @click="editVisit(visit)">Editar</button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteVisit(visit)">Eliminar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex align-items-center justify-content-between">
          <span class="text-muted">Total: {{ pagination.total }}</span>
          <div class="btn-group">
            <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page <= 1" @click="loadVisits(pagination.current_page - 1)">
              Anterior
            </button>
            <button class="btn btn-outline-secondary" type="button" disabled>
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </button>
            <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadVisits(pagination.current_page + 1)">
              Siguiente
            </button>
          </div>
        </div>
      </BCardBody>
    </BCard>

    <BModal v-model="showModalVisit" :title="isEditing ? 'Editar visita' : 'Nueva visita'" title-class="font-18" body-class="p-3" size="lg" hide-footer centered scrollable>
      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
      <form @submit.prevent="saveVisit">
        <BRow>
          <BCol md="8">
            <div class="mb-3">
              <label class="form-label">Dependencia</label>
              <select v-model="form.maintenance_dependency_id" class="form-select" required>
                <option value="">Selecciona...</option>
                <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">{{ dep.code }} · {{ dep.name }}</option>
              </select>
            </div>
          </BCol>
          <BCol md="4">
            <div class="mb-3">
              <label class="form-label">Responsable</label>
              <select v-model="form.responsible" class="form-select" required>
                <option value="">Selecciona...</option>
                <option v-for="p in catalogs.responsibles" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
          </BCol>
        </BRow>

        <BRow>
          <BCol md="4">
            <div class="mb-3">
              <label class="form-label">Fecha</label>
              <input v-model="form.visit_date" type="date" class="form-control" required />
            </div>
          </BCol>
          <BCol md="4">
            <div class="mb-3">
              <label class="form-label">Hora</label>
              <input v-model="form.visit_time" type="time" class="form-control" />
            </div>
          </BCol>
          <BCol md="4">
            <div class="mb-3">
              <label class="form-label">Tipo de visita</label>
              <select v-model="form.visit_type" class="form-select" required>
                <option v-for="t in catalogs.visit_types" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
          </BCol>
        </BRow>

        <BRow>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Estado</label>
              <select v-model="form.status" class="form-select" required>
                <option v-for="s in catalogs.statuses" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
          </BCol>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Notas</label>
              <input v-model="form.notes" type="text" class="form-control" />
            </div>
          </BCol>
        </BRow>

        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-light" type="button" @click="showModalVisit = false">Cancelar</button>
          <button class="btn btn-primary" type="submit" :disabled="saving">{{ saving ? "Guardando..." : "Guardar" }}</button>
        </div>
      </form>
    </BModal>
  </Layout>
</template>

