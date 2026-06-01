<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";

const emptyForm = () => {
  const now = new Date();
  return {
    id: null,
    maintenance_dependency_id: "",
    planned_year: now.getFullYear(),
    planned_month: now.getMonth() + 1,
    category: "General",
    responsible: "",
    frequency: "Mensual",
    status: "Programada",
    title: "",
    description: "",
    scheduled_date: "",
    completed_date: "",
    notes: "",
  };
};

export default {
  components: { Layout },
  data() {
    return {
      debugModals: false,
      loading: false,
      saving: false,
      error: null,
      success: null,
      showModalPlan: false,
      search: "",
      filters: {
        dependency_id: "",
        planned_year: new Date().getFullYear(),
        planned_month: "",
        responsible: "",
        status: "",
        frequency: "",
        category: "",
      },
      catalogs: {
        frequencies: ["Diaria", "Semanal", "Mensual", "Semestral", "Anual"],
        statuses: ["Programada", "En ejecución", "Cumplida", "Vencida", "Cancelada"],
        categories: ["General"],
        responsibles: [],
        dependencies: [],
      },
      plans: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      form: emptyForm(),
      monthOptions: [
        { value: 1, label: "Enero" },
        { value: 2, label: "Febrero" },
        { value: 3, label: "Marzo" },
        { value: 4, label: "Abril" },
        { value: 5, label: "Mayo" },
        { value: 6, label: "Junio" },
        { value: 7, label: "Julio" },
        { value: 8, label: "Agosto" },
        { value: 9, label: "Septiembre" },
        { value: 10, label: "Octubre" },
        { value: 11, label: "Noviembre" },
        { value: 12, label: "Diciembre" },
      ],
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  mounted() {
    try {
      this.debugModals = localStorage.getItem("CNSC_DEBUG_MODALS") === "1";
    } catch (e) {
      this.debugModals = false;
    }
    this.debugLog("mounted", { debugModals: this.debugModals });
    this.loadCatalogs();
    this.loadPlans();
  },
  methods: {
    debugLog(...args) {
      if (!this.debugModals) return;
      // eslint-disable-next-line no-console
      console.log("[CNSC][ANNUAL-PLAN][modals]", ...args);
    },
    onModalEvent(modal, eventName) {
      this.debugLog("modal-event", modal, eventName);
    },
    async loadCatalogs() {
      const response = await axios.get("/api/maintenance/annual-plans/catalogs");
      this.catalogs = response.data;
    },
    async loadPlans(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/maintenance/annual-plans", {
          params: {
            page,
            search: this.search,
            ...this.filters,
          },
        });
        this.plans = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          total: response.data.total,
        };
      } catch (error) {
        this.error = error.response?.data?.message || error.message || "Error cargando plan anual";
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.debugLog("openCreate(click)");
      this.error = null;
      this.success = null;
      this.form = emptyForm();
      this.showModalPlan = true;
      this.debugLog("showModalPlan=true (create)");
    },
    editPlan(plan) {
      this.debugLog("editPlan(click)", { id: plan?.id });
      this.error = null;
      this.success = null;
      this.form = {
        ...emptyForm(),
        ...plan,
        maintenance_dependency_id: plan.maintenance_dependency_id || plan.dependency?.id || "",
        scheduled_date: plan.scheduled_date ? String(plan.scheduled_date).slice(0, 10) : "",
        completed_date: plan.completed_date ? String(plan.completed_date).slice(0, 10) : "",
      };
      this.showModalPlan = true;
      this.debugLog("showModalPlan=true (edit)");
    },
    async savePlan() {
      this.saving = true;
      this.error = null;
      this.success = null;
      try {
        const payload = { ...this.form };
        if (!payload.scheduled_date) payload.scheduled_date = null;
        if (!payload.completed_date) payload.completed_date = null;
        if (!payload.description) payload.description = null;
        if (!payload.notes) payload.notes = null;

        const response = this.isEditing
          ? await axios.put(`/api/maintenance/annual-plans/${payload.id}`, payload)
          : await axios.post("/api/maintenance/annual-plans", payload);

        this.success = response.data.message;
        this.showModalPlan = false;
        await this.loadPlans(this.pagination.current_page);
      } catch (error) {
        const errors = error.response?.data?.errors;
        this.error = errors ? Object.values(errors).flat().join(" ") : error.response?.data?.message || error.message;
      } finally {
        this.saving = false;
      }
    },
    async deletePlan(plan) {
      if (!confirm(`¿Eliminar la mantención "${plan.title}"?`)) return;

      this.error = null;
      this.success = null;
      try {
        const response = await axios.delete(`/api/maintenance/annual-plans/${plan.id}`);
        this.success = response.data.message;
        await this.loadPlans(this.pagination.current_page);
      } catch (error) {
        this.error = error.response?.data?.message || error.message;
      }
    },
    monthLabel(month) {
      return this.monthOptions.find((m) => m.value === Number(month))?.label || month;
    },
    dependencyLabel(dep) {
      if (!dep) return "-";
      return `${dep.code} · ${dep.name}`;
    },
  },
  watch: {
    showModalPlan(value) {
      this.debugLog("watch showModalPlan", value);
    },
  },
};
</script>

<template>
  <Layout>
    <BRow>
      <BCol cols="12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
          <h4 class="mb-0 font-size-18">Plan anual de mantención</h4>
          <div class="page-title-right">
            <ol class="breadcrumb m-0">
              <li class="breadcrumb-item"><a href="javascript: void(0);">Mantención</a></li>
              <li class="breadcrumb-item active">Plan anual</li>
            </ol>
          </div>
        </div>
      </BCol>
    </BRow>

    <BCard>
      <BCardBody>
        <BAlert v-if="success" show variant="success" class="mb-3">{{ success }}</BAlert>
        <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

        <BRow class="g-2 mb-3 align-items-end">
          <BCol md="4">
            <label class="form-label">Buscar</label>
            <input v-model="search" type="text" class="form-control" placeholder="Título, dependencia..." @keyup.enter="loadPlans(1)" />
          </BCol>
          <BCol md="2">
            <label class="form-label">Año</label>
            <input v-model.number="filters.planned_year" type="number" min="2000" max="2100" class="form-control" @change="loadPlans(1)" />
          </BCol>
          <BCol md="2">
            <label class="form-label">Mes</label>
            <select v-model="filters.planned_month" class="form-select" @change="loadPlans(1)">
              <option value="">Todos</option>
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </BCol>
          <BCol md="4" class="text-end">
            <button class="btn btn-outline-secondary me-2" type="button" @click="loadPlans(1)" :disabled="loading">
              {{ loading ? "Cargando..." : "Filtrar" }}
            </button>
            <button class="btn btn-primary" type="button" @click="openCreate">Agregar</button>
          </BCol>
        </BRow>

        <BRow class="g-2 mb-3">
          <BCol md="4">
            <label class="form-label">Dependencia</label>
            <select v-model="filters.dependency_id" class="form-select" @change="loadPlans(1)">
              <option value="">Todas</option>
              <option v-for="dep in catalogs.dependencies" :key="dep.id" :value="dep.id">{{ dep.code }} · {{ dep.name }}</option>
            </select>
          </BCol>
          <BCol md="2">
            <label class="form-label">Categoría</label>
            <select v-model="filters.category" class="form-select" @change="loadPlans(1)">
              <option value="">Todas</option>
              <option v-for="c in catalogs.categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </BCol>
          <BCol md="2">
            <label class="form-label">Responsable</label>
            <select v-model="filters.responsible" class="form-select" @change="loadPlans(1)">
              <option value="">Todos</option>
              <option v-for="p in catalogs.responsibles" :key="p" :value="p">{{ p }}</option>
            </select>
          </BCol>
          <BCol md="2">
            <label class="form-label">Frecuencia</label>
            <select v-model="filters.frequency" class="form-select" @change="loadPlans(1)">
              <option value="">Todas</option>
              <option v-for="f in catalogs.frequencies" :key="f" :value="f">{{ f }}</option>
            </select>
          </BCol>
          <BCol md="2">
            <label class="form-label">Estado</label>
            <select v-model="filters.status" class="form-select" @change="loadPlans(1)">
              <option value="">Todos</option>
              <option v-for="s in catalogs.statuses" :key="s" :value="s">{{ s }}</option>
            </select>
          </BCol>
        </BRow>

        <div class="table-responsive">
          <table class="table table-sm align-middle table-nowrap mb-0">
            <thead>
              <tr>
                <th>Periodo</th>
                <th>Dependencia</th>
                <th>Categoría</th>
                <th>Responsable</th>
                <th>Frecuencia</th>
                <th>Estado</th>
                <th>Título</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!loading && plans.length === 0">
                <td colspan="8" class="text-center text-muted py-4">Sin registros.</td>
              </tr>
              <tr v-for="plan in plans" :key="plan.id">
                <td>{{ plan.planned_year }} · {{ monthLabel(plan.planned_month) }}</td>
                <td>{{ dependencyLabel(plan.dependency) }}</td>
                <td>{{ plan.category }}</td>
                <td>{{ plan.responsible }}</td>
                <td>{{ plan.frequency }}</td>
                <td>{{ plan.status }}</td>
                <td class="text-truncate" style="max-width: 280px;">{{ plan.title }}</td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-secondary me-2" type="button" @click="editPlan(plan)">Editar</button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="deletePlan(plan)">Eliminar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex align-items-center justify-content-between mt-3">
          <span class="text-muted">Total: {{ pagination.total }}</span>
          <div class="btn-group">
            <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page <= 1" @click="loadPlans(pagination.current_page - 1)">
              Anterior
            </button>
            <button class="btn btn-outline-secondary" type="button" disabled>
              {{ pagination.current_page }} / {{ pagination.last_page }}
            </button>
            <button class="btn btn-outline-secondary" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadPlans(pagination.current_page + 1)">
              Siguiente
            </button>
          </div>
        </div>
      </BCardBody>
    </BCard>

    <BModal
      v-model="showModalPlan"
      :title="isEditing ? 'Editar mantención programada' : 'Nueva mantención programada'"
      title-class="font-18"
      body-class="p-3"
      size="lg"
      hide-footer
      centered
      scrollable
      teleport-to="body"
      lazy
      no-fade
      @show="onModalEvent('annual-plan', 'show')"
      @shown="onModalEvent('annual-plan', 'shown')"
      @hide="onModalEvent('annual-plan', 'hide')"
      @hidden="onModalEvent('annual-plan', 'hidden')"
    >
      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
      <form @submit.prevent="savePlan">
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
              <label class="form-label">Año</label>
              <input v-model.number="form.planned_year" type="number" min="2000" max="2100" class="form-control" required />
            </div>
          </BCol>
          <BCol md="4">
            <div class="mb-3">
              <label class="form-label">Mes</label>
              <select v-model.number="form.planned_month" class="form-select" required>
                <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
            </div>
          </BCol>
          <BCol md="4">
            <div class="mb-3">
              <label class="form-label">Categoría</label>
              <select v-model="form.category" class="form-select" required>
                <option v-for="c in catalogs.categories" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
          </BCol>
        </BRow>

        <BRow>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Frecuencia</label>
              <select v-model="form.frequency" class="form-select" required>
                <option v-for="f in catalogs.frequencies" :key="f" :value="f">{{ f }}</option>
              </select>
            </div>
          </BCol>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Estado</label>
              <select v-model="form.status" class="form-select" required>
                <option v-for="s in catalogs.statuses" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
          </BCol>
        </BRow>

        <div class="mb-3">
          <label class="form-label">Título</label>
          <input v-model="form.title" type="text" class="form-control" required />
        </div>

        <div class="mb-3">
          <label class="form-label">Descripción</label>
          <textarea v-model="form.description" class="form-control" rows="3" />
        </div>

        <BRow>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Fecha programada (opcional)</label>
              <input v-model="form.scheduled_date" type="date" class="form-control" />
            </div>
          </BCol>
          <BCol md="6">
            <div class="mb-3">
              <label class="form-label">Fecha cumplida (opcional)</label>
              <input v-model="form.completed_date" type="date" class="form-control" />
            </div>
          </BCol>
        </BRow>

        <div class="mb-3">
          <label class="form-label">Notas</label>
          <input v-model="form.notes" type="text" class="form-control" />
        </div>

        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-light" type="button" @click="showModalPlan = false">Cancelar</button>
          <button class="btn btn-primary" type="submit" :disabled="saving">{{ saving ? "Guardando..." : "Guardar" }}</button>
        </div>
      </form>
    </BModal>
  </Layout>
</template>
