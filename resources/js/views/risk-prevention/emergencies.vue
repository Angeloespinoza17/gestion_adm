<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import {
  confirmRiskAction,
  downloadRiskFile,
  formatRiskDate,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
} from "../../components/risk-prevention/module-utils";

const emptyPlan = () => ({
  id: null,
  record_type: "plan_evacuacion",
  title: "",
  emergency_type: "",
  last_updated_at: "",
  responsible_name: "",
  notes: "",
  active: true,
  document: null,
});

const emptyDrill = () => ({
  title: "",
  emergency_type: "",
  drill_date: "",
  responsible_name: "",
  participants_count: 0,
  findings: "",
  improvements: "",
  document: null,
});

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loading: false,
      savingPlan: false,
      savingDrill: false,
      error: null,
      items: [],
      catalogs: { emergency_types: [] },
      filters: {
        search: "",
        record_type: "",
        active: "",
      },
      showPlanModal: false,
      showDrillModal: false,
      planForm: emptyPlan(),
      drillForm: emptyDrill(),
      selectedPlan: null,
    };
  },
  computed: {
    isEditingPlan() {
      return Boolean(this.planForm.id);
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
        const response = await axios.get("/api/risk-prevention/emergency-plans", {
          params: {
            ...this.filters,
            per_page: 100,
          },
        });
        this.items = response.data.data || [];
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar emergencias y planes.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
    openCreatePlan() {
      this.planForm = {
        ...emptyPlan(),
        last_updated_at: new Date().toISOString().slice(0, 10),
      };
      this.showPlanModal = true;
    },
    openEditPlan(item) {
      this.planForm = {
        id: item.id,
        record_type: item.record_type || "plan_evacuacion",
        title: item.title || "",
        emergency_type: item.emergency_type || "",
        last_updated_at: item.last_updated_at || "",
        responsible_name: item.responsible_name || "",
        notes: item.notes || "",
        active: Boolean(item.active),
        document: null,
      };
      this.showPlanModal = true;
    },
    planTypeLabel(value) {
      return value === "plan_evacuacion" ? "Plan de evacuación" : "Protocolo";
    },
    openDrillModal(item) {
      this.selectedPlan = item;
      this.drillForm = {
        ...emptyDrill(),
        emergency_type: item.emergency_type || "",
        responsible_name: item.responsible_name || "",
        drill_date: new Date().toISOString().slice(0, 10),
      };
      this.showDrillModal = true;
    },
    buildPlanFormData() {
      const formData = new FormData();
      Object.entries(this.planForm).forEach(([key, value]) => {
        if (key === "id") return;
        if (key === "document") {
          if (value) formData.append("document", value);
          return;
        }
        if (typeof value === "boolean") {
          formData.append(key, value ? 1 : 0);
          return;
        }
        if (value !== null && value !== undefined) {
          formData.append(key, value);
        }
      });
      return formData;
    },
    buildDrillFormData() {
      const formData = new FormData();
      Object.entries(this.drillForm).forEach(([key, value]) => {
        if (key === "document") {
          if (value) formData.append("document", value);
          return;
        }
        if (value !== null && value !== undefined) {
          formData.append(key, value);
        }
      });
      return formData;
    },
    async savePlan() {
      this.savingPlan = true;
      this.error = null;
      try {
        const payload = this.buildPlanFormData();
        if (this.isEditingPlan) {
          payload.append("_method", "PUT");
          await axios.post(`/api/risk-prevention/emergency-plans/${this.planForm.id}`, payload);
          await showRiskSuccess("El registro fue actualizado correctamente.");
        } else {
          await axios.post("/api/risk-prevention/emergency-plans", payload);
          await showRiskSuccess("El registro fue creado correctamente.");
        }
        this.showPlanModal = false;
        this.loadItems();
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo guardar el registro.");
        showRiskError(this.error);
      } finally {
        this.savingPlan = false;
      }
    },
    async saveDrill() {
      if (!this.selectedPlan) return;
      this.savingDrill = true;
      try {
        await axios.post(
          `/api/risk-prevention/emergency-plans/${this.selectedPlan.id}/drills`,
          this.buildDrillFormData()
        );
        await showRiskSuccess("El simulacro fue registrado correctamente.");
        this.showDrillModal = false;
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo registrar el simulacro."));
      } finally {
        this.savingDrill = false;
      }
    },
    async removePlan(item) {
      const result = await confirmRiskAction({
        title: "Eliminar registro",
        text: `Se eliminará ${item.title}.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/emergency-plans/${item.id}`);
        await showRiskSuccess("El registro fue eliminado correctamente.");
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el registro."));
      }
    },
    async removeDrill(drill) {
      const result = await confirmRiskAction({
        title: "Eliminar simulacro",
        text: `Se eliminará ${drill.title}.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/emergency-drills/${drill.id}`);
        await showRiskSuccess("El simulacro fue eliminado correctamente.");
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el simulacro."));
      }
    },
    async downloadPlan(item) {
      try {
        await downloadRiskFile(`/api/risk-prevention/emergency-plans/${item.id}/download`, item.document_name || `${item.title}.txt`);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo descargar el documento asociado."));
      }
    },
    async downloadDrill(drill) {
      try {
        await downloadRiskFile(`/api/risk-prevention/emergency-drills/${drill.id}/download`, drill.document_name || `${drill.title}.txt`);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo descargar el respaldo del simulacro."));
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Emergencias y Planes</h4>
        <div class="text-muted">Planes de evacuación, protocolos y simulacros con respaldo documental.</div>
      </div>
      <div class="d-flex gap-2">
        <HelpButton
          title="Ayuda: emergencias y planes"
          text="Esta sección concentra planes de evacuación, protocolos y simulacros para mantener la preparación institucional."
        />
        <BButton variant="primary" @click="openCreatePlan">Nuevo registro</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Título, tipo de emergencia o responsable" @keyup.enter="loadItems" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Tipo de registro</label>
          <BFormSelect v-model="filters.record_type" :options="[
            { value: '', text: 'Todos' },
            { value: 'plan_evacuacion', text: 'Plan de evacuación' },
            { value: 'protocolo', text: 'Protocolo' },
          ]" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Activo</label>
          <BFormSelect v-model="filters.active" :options="[
            { value: '', text: 'Todos' },
            { value: '1', text: 'Sí' },
            { value: '0', text: 'No' },
          ]" />
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <BButton variant="secondary" class="w-100" @click="loadItems">Filtrar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <LoadingState v-if="loading" message="Cargando planes y emergencias..." />

    <div v-else class="row g-3">
      <div v-for="item in items" :key="item.id" class="col-12">
        <BCard class="shadow-sm border-0">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-2">
                <StatusBadge :status="item.active ? 'activo' : 'archivado'" :labels="{ activo: 'Activo', archivado: 'Inactivo' }" />
                <BBadge variant="info">{{ planTypeLabel(item.record_type) }}</BBadge>
              </div>
              <h5 class="mb-1">{{ item.title }}</h5>
              <div class="text-muted small">
                {{ item.emergency_type }} · Responsable: {{ item.responsible_name }} · Actualizado: {{ formatRiskDate(item.last_updated_at) }}
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <BButton size="sm" variant="outline-primary" @click="openEditPlan(item)">Editar</BButton>
              <BButton size="sm" variant="outline-secondary" @click="openDrillModal(item)">Agregar simulacro</BButton>
              <BButton size="sm" variant="outline-info" :disabled="!item.document_path" @click="downloadPlan(item)">Documento</BButton>
              <BButton size="sm" variant="outline-danger" @click="removePlan(item)">Eliminar</BButton>
            </div>
          </div>

          <div class="small mb-3">{{ item.notes || "Sin observaciones." }}</div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">Historial de simulacros</div>
            <HelpButton
              title="Ayuda: historial de simulacros"
              text="Registra cada simulacro asociado al plan o protocolo para dar seguimiento a hallazgos y mejoras."
            />
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Simulacro</th>
                  <th>Fecha</th>
                  <th>Participantes</th>
                  <th>Hallazgos</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="drill in item.drills || []" :key="drill.id">
                  <td>{{ drill.title }}</td>
                  <td>{{ formatRiskDate(drill.drill_date) }}</td>
                  <td>{{ drill.participants_count || 0 }}</td>
                  <td>{{ drill.findings || "-" }}</td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                      <BButton size="sm" variant="outline-info" :disabled="!drill.document_path" @click="downloadDrill(drill)">Descargar</BButton>
                      <BButton size="sm" variant="outline-danger" @click="removeDrill(drill)">Eliminar</BButton>
                    </div>
                  </td>
                </tr>
                <tr v-if="!(item.drills || []).length">
                  <td colspan="5" class="text-center text-muted py-3">Sin simulacros registrados.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCard>
      </div>
      <div v-if="!items.length" class="col-12">
        <BCard class="text-center text-muted py-4">No hay planes o protocolos registrados.</BCard>
      </div>
    </div>

    <BModal v-model="showPlanModal" size="lg" :title="isEditingPlan ? 'Editar registro' : 'Nuevo registro'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Define el plan o protocolo y adjunta respaldo si corresponde.</div>
        <HelpButton
          title="Ayuda del formulario"
          text="Registra planes o protocolos con su responsable, fecha de actualización y documento asociado."
        />
      </div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Tipo de registro</label>
          <BFormSelect v-model="planForm.record_type" :options="[
            { value: 'plan_evacuacion', text: 'Plan de evacuación' },
            { value: 'protocolo', text: 'Protocolo' },
          ]" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Título</label>
          <BFormInput v-model="planForm.title" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo de emergencia</label>
          <BFormInput v-model="planForm.emergency_type" list="risk-emergency-types" />
          <datalist id="risk-emergency-types">
            <option v-for="item in catalogs.emergency_types" :key="item" :value="item"></option>
          </datalist>
        </div>
        <div class="col-md-4">
          <label class="form-label">Última actualización</label>
          <BFormInput v-model="planForm.last_updated_at" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Responsable</label>
          <BFormInput v-model="planForm.responsible_name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Documento asociado</label>
          <BFormFile @change="planForm.document = $event.target.files[0] || null" />
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <BFormCheckbox v-model="planForm.active">Registro activo</BFormCheckbox>
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="planForm.notes" rows="3" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showPlanModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="savingPlan" @click="savePlan">{{ savingPlan ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>

    <BModal v-model="showDrillModal" size="lg" title="Registrar simulacro" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Asocia hallazgos y mejoras del simulacro al plan seleccionado.</div>
        <HelpButton
          title="Ayuda del formulario"
          text="Registra fecha, participantes, hallazgos, mejoras y documento del simulacro realizado."
        />
      </div>

      <BAlert show variant="light" class="mb-3">
        <strong>Plan asociado:</strong> {{ selectedPlan?.title || "-" }}
      </BAlert>

      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Título del simulacro</label>
          <BFormInput v-model="drillForm.title" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha</label>
          <BFormInput v-model="drillForm.drill_date" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo de emergencia</label>
          <BFormInput v-model="drillForm.emergency_type" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Responsable</label>
          <BFormInput v-model="drillForm.responsible_name" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Participantes</label>
          <BFormInput v-model="drillForm.participants_count" type="number" min="0" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Hallazgos</label>
          <BFormTextarea v-model="drillForm.findings" rows="3" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Mejoras</label>
          <BFormTextarea v-model="drillForm.improvements" rows="3" />
        </div>
        <div class="col-12">
          <label class="form-label">Documento de respaldo</label>
          <BFormFile @change="drillForm.document = $event.target.files[0] || null" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showDrillModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="savingDrill" @click="saveDrill">
          {{ savingDrill ? "Guardando..." : "Guardar simulacro" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
