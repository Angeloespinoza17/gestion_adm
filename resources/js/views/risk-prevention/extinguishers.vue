<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import {
  confirmRiskAction,
  formatRiskDate,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
  showRiskWarning,
} from "../../components/risk-prevention/module-utils";

const emptyForm = () => ({
  id: null,
  code: "",
  extinguisher_type: "",
  building: "",
  floor: "",
  dependency_name: "",
  installed_at: "",
  expires_at: "",
  status: "vigente",
  notes: "",
});

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loading: false,
      saving: false,
      warningShown: false,
      error: null,
      items: [],
      catalogs: { extinguisher_types: [] },
      filters: {
        search: "",
        status: "",
      },
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
    this.loadCatalogs();
    this.loadItems();
  },
  methods: {
    formatRiskDate,
    async loadCatalogs() {
      const response = await axios.get("/api/risk-prevention/catalogs");
      this.catalogs = response.data;
    },
    async loadItems() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/risk-prevention/extinguishers", {
          params: {
            ...this.filters,
            per_page: 100,
          },
        });
        this.items = response.data.data || [];
        this.maybeShowAlerts();
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar la gestión de extintores.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
    async maybeShowAlerts() {
      if (this.warningShown) return;
      const due = this.items.filter((item) => ["por_vencer", "vencido"].includes(item.current_status)).length;
      if (!due) return;
      this.warningShown = true;
      await showRiskWarning(`Hay ${due} extintores próximos a vencer o vencidos.`, "Vencimientos de extintores");
    },
    openCreate() {
      this.form = emptyForm();
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        code: item.code || "",
        extinguisher_type: item.extinguisher_type || "",
        building: item.building || "",
        floor: item.floor || "",
        dependency_name: item.dependency_name || "",
        installed_at: item.installed_at || "",
        expires_at: item.expires_at || "",
        status: item.status || "vigente",
        notes: item.notes || "",
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        const payload = { ...this.form };
        delete payload.id;

        if (this.isEditing) {
          await axios.put(`/api/risk-prevention/extinguishers/${this.form.id}`, payload);
          await showRiskSuccess("El extintor fue actualizado correctamente.");
        } else {
          await axios.post("/api/risk-prevention/extinguishers", payload);
          await showRiskSuccess("El extintor fue registrado correctamente.");
        }

        this.showModal = false;
        this.loadItems();
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo guardar el extintor.");
        showRiskError(this.error);
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      const result = await confirmRiskAction({
        title: "Eliminar extintor",
        text: `Se eliminará el registro ${item.code}.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/extinguishers/${item.id}`);
        await showRiskSuccess("El extintor fue eliminado correctamente.");
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el extintor."));
      }
    },
    alertLabel(item) {
      if (!item.alert_level) return "-";
      return `${item.alert_level} días`;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Gestión de Extintores</h4>
        <div class="text-muted">Registro, ubicación y control de vencimientos de extintores del colegio.</div>
      </div>
      <div class="d-flex gap-2">
        <HelpButton
          title="Ayuda: gestión de extintores"
          text="Este módulo permite registrar y controlar los extintores del establecimiento, alertando sobre vencimientos."
        />
        <BButton variant="primary" @click="openCreate">Nuevo extintor</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Código, tipo o ubicación" @keyup.enter="loadItems" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="[
            { value: '', text: 'Todos' },
            { value: 'vigente', text: 'Vigente' },
            { value: 'por_vencer', text: 'Por vencer' },
            { value: 'vencido', text: 'Vencido' },
            { value: 'dado_baja', text: 'Dado de baja' },
          ]" />
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
          <BButton variant="secondary" @click="loadItems">Buscar</BButton>
          <BButton variant="outline-secondary" @click="filters = { search: '', status: '' }; loadItems()">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <BCard>
      <LoadingState v-if="loading" message="Cargando extintores..." />
      <div v-else class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Código</th>
              <th>Tipo</th>
              <th>Ubicación</th>
              <th>Instalación</th>
              <th>Vencimiento</th>
              <th>Alerta</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="fw-semibold">{{ item.code }}</td>
              <td>{{ item.extinguisher_type }}</td>
              <td>{{ item.location_label }}</td>
              <td>{{ formatRiskDate(item.installed_at) }}</td>
              <td>{{ formatRiskDate(item.expires_at) }}</td>
              <td>
                <BBadge v-if="item.alert_level" :variant="item.alert_level <= 7 ? 'danger' : 'warning'">
                  {{ alertLabel(item) }}
                </BBadge>
                <span v-else class="text-muted">Sin alerta</span>
              </td>
              <td><StatusBadge :status="item.current_status" /></td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2">
                  <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
                  <BButton size="sm" variant="outline-danger" @click="remove(item)">Eliminar</BButton>
                </div>
              </td>
            </tr>
            <tr v-if="!items.length">
              <td colspan="8" class="text-center text-muted py-4">No hay extintores registrados.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <BModal v-model="showModal" size="lg" :title="isEditing ? 'Editar extintor' : 'Nuevo extintor'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Completa los datos para mantener trazabilidad y alertas automáticas.</div>
        <HelpButton
          title="Ayuda del formulario"
          text="Registra código, tipo, ubicación y fechas del extintor para controlar su vigencia."
        />
      </div>

      <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Código</label>
          <BFormInput v-model="form.code" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormInput v-model="form.extinguisher_type" list="risk-extinguisher-types" />
          <datalist id="risk-extinguisher-types">
            <option v-for="item in catalogs.extinguisher_types" :key="item" :value="item"></option>
          </datalist>
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.status" :options="[
            { value: 'vigente', text: 'Vigente' },
            { value: 'por_vencer', text: 'Por vencer' },
            { value: 'vencido', text: 'Vencido' },
            { value: 'dado_baja', text: 'Dado de baja' },
          ]" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Edificio</label>
          <BFormInput v-model="form.building" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Piso</label>
          <BFormInput v-model="form.floor" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Dependencia</label>
          <BFormInput v-model="form.dependency_name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de instalación</label>
          <BFormInput v-model="form.installed_at" type="date" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha de vencimiento</label>
          <BFormInput v-model="form.expires_at" type="date" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.notes" rows="3" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>
  </Layout>
</template>
