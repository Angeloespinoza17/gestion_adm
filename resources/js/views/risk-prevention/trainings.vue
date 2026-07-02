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

const emptyParticipant = () => ({
  employee_name: "",
  compliance_status: "pendiente",
  notes: "",
});

const emptyTraining = () => ({
  id: null,
  name: "",
  training_type: "induccion",
  training_date: "",
  modality: "Presencial",
  observations: "",
  evidence: null,
  participants: [emptyParticipant()],
});

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      catalogs: { employees: [], training_modalities: [] },
      filters: { search: "", training_type: "", compliance_status: "" },
      items: [],
      showModal: false,
      form: emptyTraining(),
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
      try {
        const response = await axios.get("/api/risk-prevention/trainings", {
          params: { ...this.filters, per_page: 100 },
        });
        this.items = response.data.data || [];
      } catch (error) {
        this.error = formatRiskError(error, "No se pudieron cargar las capacitaciones.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = {
        ...emptyTraining(),
        training_date: new Date().toISOString().slice(0, 10),
      };
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        name: item.name || "",
        training_type: item.training_type || "induccion",
        training_date: item.training_date || "",
        modality: item.modality || "Presencial",
        observations: item.observations || "",
        evidence: null,
        participants: (item.participants || []).length
          ? (item.participants || []).map((participant) => ({
              employee_name: participant.employee_name || "",
              compliance_status: participant.compliance_status || "pendiente",
              notes: participant.notes || "",
            }))
          : [emptyParticipant()],
      };
      this.showModal = true;
    },
    addParticipant() {
      this.form.participants.push(emptyParticipant());
    },
    removeParticipant(index) {
      if (this.form.participants.length === 1) return;
      this.form.participants.splice(index, 1);
    },
    buildFormData() {
      const formData = new FormData();
      formData.append("name", this.form.name);
      formData.append("training_type", this.form.training_type);
      formData.append("training_date", this.form.training_date);
      formData.append("modality", this.form.modality);
      formData.append("observations", this.form.observations || "");
      if (this.form.evidence) {
        formData.append("evidence", this.form.evidence);
      }
      this.form.participants.forEach((participant, index) => {
        formData.append(`participants[${index}][employee_name]`, participant.employee_name || "");
        formData.append(`participants[${index}][compliance_status]`, participant.compliance_status || "pendiente");
        formData.append(`participants[${index}][notes]`, participant.notes || "");
      });
      return formData;
    },
    async save() {
      this.saving = true;
      try {
        const payload = this.buildFormData();
        if (this.isEditing) {
          payload.append("_method", "PUT");
          await axios.post(`/api/risk-prevention/trainings/${this.form.id}`, payload);
          await showRiskSuccess("La capacitación fue actualizada correctamente.");
        } else {
          await axios.post("/api/risk-prevention/trainings", payload);
          await showRiskSuccess("La capacitación fue registrada correctamente.");
        }
        this.showModal = false;
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo guardar la capacitación."));
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      const result = await confirmRiskAction({
        title: "Eliminar capacitación",
        text: `Se eliminará ${item.name}.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/trainings/${item.id}`);
        await showRiskSuccess("La capacitación fue eliminada correctamente.");
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar la capacitación."));
      }
    },
    async downloadEvidence(item) {
      try {
        await downloadRiskFile(`/api/risk-prevention/trainings/${item.id}/evidence`, item.evidence_name || `${item.name}.txt`);
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo descargar la evidencia."));
      }
    },
    pendingCount(item) {
      return (item.participants || []).filter((participant) => participant.compliance_status === "pendiente").length;
    },
    completedCount(item) {
      return (item.participants || []).filter((participant) => participant.compliance_status === "cumplido").length;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Capacitaciones e Inducciones</h4>
        <div class="text-muted">Registro de capacitaciones obligatorias y cumplimiento por funcionario.</div>
      </div>
      <div class="d-flex gap-2">
        <HelpButton
          title="Ayuda: capacitaciones e inducciones"
          text="Permite registrar capacitaciones obligatorias, su modalidad, participantes y evidencia documental."
        />
        <BButton variant="primary" @click="openCreate">Nueva capacitación</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-5">
          <BFormInput v-model="filters.search" placeholder="Buscar capacitación" @keyup.enter="loadItems" />
        </div>
        <div class="col-md-3">
          <BFormSelect v-model="filters.training_type" :options="[
            { value: '', text: 'Todos los tipos' },
            { value: 'induccion', text: 'Inducción' },
            { value: 'actualizacion', text: 'Actualización' },
            { value: 'obligatoria', text: 'Obligatoria' },
          ]" />
        </div>
        <div class="col-md-2">
          <BFormSelect v-model="filters.compliance_status" :options="[
            { value: '', text: 'Todos los estados' },
            { value: 'cumplido', text: 'Cumplido' },
            { value: 'pendiente', text: 'Pendiente' },
            { value: 'no_asiste', text: 'No asiste' },
          ]" />
        </div>
        <div class="col-md-2">
          <BButton variant="secondary" class="w-100" @click="loadItems">Filtrar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <LoadingState v-if="loading" message="Cargando capacitaciones..." />

    <div v-else class="row g-3">
      <div v-for="item in items" :key="item.id" class="col-12">
        <BCard class="shadow-sm border-0">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
              <h5 class="mb-1">{{ item.name }}</h5>
              <div class="small text-muted">
                {{ formatRiskDate(item.training_date) }} · {{ item.modality }} · {{ item.training_type }}
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <BBadge variant="success">Cumplidos: {{ completedCount(item) }}</BBadge>
              <BBadge variant="warning">Pendientes: {{ pendingCount(item) }}</BBadge>
            </div>
          </div>

          <div class="small mb-3">{{ item.observations || "Sin observaciones." }}</div>

          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Funcionario</th>
                  <th>Estado</th>
                  <th>Observación</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="participant in item.participants || []" :key="participant.id">
                  <td>{{ participant.employee_name }}</td>
                  <td><StatusBadge :status="participant.compliance_status" /></td>
                  <td>{{ participant.notes || "-" }}</td>
                </tr>
                <tr v-if="!(item.participants || []).length">
                  <td colspan="3" class="text-center text-muted py-3">Sin participantes registrados.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end gap-2">
            <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Editar</BButton>
            <BButton size="sm" variant="outline-info" :disabled="!item.evidence_path" @click="downloadEvidence(item)">Evidencia</BButton>
            <BButton size="sm" variant="outline-danger" @click="remove(item)">Eliminar</BButton>
          </div>
        </BCard>
      </div>
      <div v-if="!items.length" class="col-12">
        <BCard class="text-center text-muted py-4">No hay capacitaciones registradas.</BCard>
      </div>
    </div>

    <BModal v-model="showModal" size="xl" :title="isEditing ? 'Editar capacitación' : 'Nueva capacitación'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Registra la actividad y define el cumplimiento por funcionario.</div>
        <HelpButton
          title="Ayuda del formulario"
          text="Registra la capacitación, su evidencia y el estado de cumplimiento por funcionario."
        />
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <BFormInput v-model="form.name" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="form.training_type" :options="[
            { value: 'induccion', text: 'Inducción' },
            { value: 'actualizacion', text: 'Actualización' },
            { value: 'obligatoria', text: 'Obligatoria' },
          ]" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Fecha</label>
          <BFormInput v-model="form.training_date" type="date" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Modalidad</label>
          <BFormSelect
            v-model="form.modality"
            :options="(catalogs.training_modalities || []).map((item) => ({ value: item, text: item }))"
          />
        </div>
        <div class="col-md-6">
          <label class="form-label">Evidencia documental</label>
          <BFormFile @change="form.evidence = $event.target.files[0] || null" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
      </div>

      <BCard class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">Participantes</h5>
            <div class="small text-muted">Estado de cumplimiento por funcionario.</div>
          </div>
          <BButton size="sm" variant="outline-primary" @click="addParticipant">Agregar participante</BButton>
        </div>

        <div v-for="(participant, index) in form.participants" :key="index" class="row g-3 align-items-end border rounded p-2 mb-2">
          <div class="col-md-5">
            <label class="form-label">Funcionario</label>
            <BFormInput v-model="participant.employee_name" list="risk-training-employees" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="participant.compliance_status" :options="[
              { value: 'cumplido', text: 'Cumplido' },
              { value: 'pendiente', text: 'Pendiente' },
              { value: 'no_asiste', text: 'No asiste' },
            ]" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Observación</label>
            <BFormInput v-model="participant.notes" />
          </div>
          <div class="col-md-1">
            <BButton size="sm" variant="outline-danger" class="w-100" @click="removeParticipant(index)">X</BButton>
          </div>
        </div>

        <datalist id="risk-training-employees">
          <option v-for="item in catalogs.employees || []" :key="item" :value="item"></option>
        </datalist>
      </BCard>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>
    </BModal>
  </Layout>
</template>
