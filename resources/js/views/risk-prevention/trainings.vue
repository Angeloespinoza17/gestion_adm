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
  id: null,
  staff_id: "",
  employee_name: "",
  compliance_status: "pendiente",
  issued_on: "",
  expires_on: "",
  notes: "",
});

const emptyTraining = () => ({
  id: null,
  name: "",
  training_type: "induccion",
  training_date: "",
  modality: "Presencial",
  is_requirement: true,
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
      selectedDepartmentId: "",
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
    selectedParticipantCount() {
      return this.form.participants.filter((participant) => (
        participant.staff_id || String(participant.employee_name || "").trim()
      )).length;
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
      this.selectedDepartmentId = "";
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        name: item.name || "",
        training_type: item.training_type || "induccion",
        training_date: item.training_date || "",
        modality: item.modality || "Presencial",
        is_requirement: item.is_requirement !== false,
        observations: item.observations || "",
        evidence: null,
        participants: (item.participants || []).length
          ? (item.participants || []).map((participant) => ({
              id: participant.id,
              staff_id: participant.staff_id || "",
              employee_name: participant.employee_name || "",
              compliance_status: participant.compliance_status || "pendiente",
              issued_on: participant.issued_on || item.training_date || "",
              expires_on: participant.expires_on || "",
              notes: participant.notes || "",
            }))
          : [emptyParticipant()],
      };
      this.selectedDepartmentId = "";
      this.showModal = true;
    },
    addParticipant() {
      this.form.participants.push(emptyParticipant());
    },
    removeParticipant(index) {
      if (this.form.participants.length === 1) return;
      this.form.participants.splice(index, 1);
    },
    participantFromStaff(staff) {
      return {
        ...emptyParticipant(),
        staff_id: staff.id,
        employee_name: staff.name,
      };
    },
    addStaffParticipants(staffMembers) {
      const selectedIds = new Set(
        this.form.participants
          .map((participant) => Number(participant.staff_id))
          .filter(Boolean),
      );
      const additions = staffMembers
        .filter((staff) => !selectedIds.has(Number(staff.id)))
        .map((staff) => this.participantFromStaff(staff));

      if (!additions.length) return;

      const existingParticipants = this.form.participants.filter((participant) => (
        participant.id
        || participant.staff_id
        || String(participant.employee_name || "").trim()
      ));
      this.form.participants = [...existingParticipants, ...additions];
    },
    addAllParticipants() {
      this.addStaffParticipants(this.catalogs.staff_members || []);
    },
    addDepartmentParticipants() {
      const departmentId = Number(this.selectedDepartmentId);
      if (!departmentId) return;

      const staffMembers = (this.catalogs.staff_members || []).filter((staff) => (
        (staff.department_ids || []).some((id) => Number(id) === departmentId)
      ));
      this.addStaffParticipants(staffMembers);
    },
    applyPlannedDateToAllParticipants() {
      if (!this.form.training_date || !this.selectedParticipantCount) return;

      this.form.participants.forEach((participant) => {
        if (!participant.staff_id && !String(participant.employee_name || "").trim()) return;

        participant.issued_on = this.form.training_date;
        participant.expires_on = this.form.training_date;
      });
    },
    buildFormData() {
      const formData = new FormData();
      formData.append("name", this.form.name);
      formData.append("training_type", this.form.training_type);
      formData.append("training_date", this.form.training_date);
      formData.append("modality", this.form.modality);
      formData.append("is_requirement", this.form.is_requirement ? "1" : "0");
      formData.append("observations", this.form.observations || "");
      if (this.form.evidence) {
        formData.append("evidence", this.form.evidence);
      }
      this.form.participants.forEach((participant, index) => {
        if (participant.id) formData.append(`participants[${index}][id]`, participant.id);
        if (participant.staff_id) formData.append(`participants[${index}][staff_id]`, participant.staff_id);
        formData.append(`participants[${index}][employee_name]`, participant.employee_name || "");
        formData.append(`participants[${index}][compliance_status]`, participant.compliance_status || "pendiente");
        formData.append(`participants[${index}][issued_on]`, participant.issued_on || "");
        formData.append(`participants[${index}][expires_on]`, participant.expires_on || "");
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
    staffOptions(participant) {
      const options = [{ value: "", text: "Seleccionar funcionario" }];
      const selectedIds = new Set(
        this.form.participants
          .filter((item) => item !== participant)
          .map((item) => Number(item.staff_id))
          .filter(Boolean),
      );
      const members = (this.catalogs.staff_members || [])
        .filter((item) => !selectedIds.has(Number(item.id)))
        .map((item) => ({
          value: item.id,
          text: `${item.name}${item.rut ? ` · ${item.rut}` : ""}`,
        }));
      if (participant.staff_id && !members.some((item) => Number(item.value) === Number(participant.staff_id))) {
        options.push({ value: participant.staff_id, text: participant.employee_name || "Funcionario inactivo" });
      }
      return [...options, ...members];
    },
    onParticipantStaffChange(participant) {
      const staff = (this.catalogs.staff_members || [])
        .find((item) => Number(item.id) === Number(participant.staff_id));
      if (staff) participant.employee_name = staff.name;
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
        <div class="col-md-3">
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
              <BBadge :variant="item.is_requirement ? 'primary' : 'secondary'" class="mt-2">
                {{ item.is_requirement ? "Es requisito" : "No es requisito" }}
              </BBadge>
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
                  <th>Vencimiento</th>
                  <th>Observación</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="participant in item.participants || []" :key="participant.id">
                  <td>{{ participant.employee_name }}</td>
                  <td><StatusBadge :status="participant.compliance_status" /></td>
                  <td>{{ formatRiskDate(participant.expires_on) }}</td>
                  <td>{{ participant.notes || "-" }}</td>
                </tr>
                <tr v-if="!(item.participants || []).length">
                  <td colspan="4" class="text-center text-muted py-3">Sin participantes registrados.</td>
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
        <div class="col-md-3">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="form.training_type" :options="[
            { value: 'induccion', text: 'Inducción' },
            { value: 'actualizacion', text: 'Actualización' },
            { value: 'obligatoria', text: 'Obligatoria' },
          ]" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha</label>
          <BFormInput v-model="form.training_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Modalidad</label>
          <BFormSelect
            v-model="form.modality"
            :options="(catalogs.training_modalities || []).map((item) => ({ value: item, text: item }))"
          />
        </div>
        <div class="col-md-9">
          <label class="form-label">Evidencia documental</label>
          <BFormFile @change="form.evidence = $event.target.files[0] || null" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="3" />
        </div>
        <div class="col-12">
          <div class="border rounded p-3 bg-light">
            <BFormCheckbox v-model="form.is_requirement" switch>
              <strong>Es requisito para los funcionarios</strong>
            </BFormCheckbox>
            <div class="small text-muted mt-1">
              Activado por defecto. Si se desmarca, esta capacitación no generará alertas pendientes en la gestión del personal.
            </div>
          </div>
        </div>
      </div>

      <BCard class="mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <h5 class="mb-0">Participantes</h5>
            <div class="small text-muted">
              Agrega funcionarios individualmente, por departamento o de forma masiva.
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <BButton size="sm" variant="outline-primary" @click="addParticipant">
              Agregar individual
            </BButton>
            <BButton size="sm" variant="outline-primary" @click="addAllParticipants">
              Agregar todos
            </BButton>
          </div>
        </div>

        <div class="row g-2 align-items-end bg-light border rounded p-2 mb-3">
          <div class="col-md-8">
            <label class="form-label mb-1">Agregar por departamento</label>
            <BFormSelect
              v-model="selectedDepartmentId"
              :options="[
                { value: '', text: 'Seleccionar departamento' },
                ...(catalogs.staff_departments || []).map((item) => ({
                  value: item.id,
                  text: `${item.name} (${item.staff_count} ${Number(item.staff_count) === 1 ? 'funcionario' : 'funcionarios'})`,
                })),
              ]"
            />
          </div>
          <div class="col-md-4">
            <BButton
              class="w-100"
              variant="outline-secondary"
              :disabled="!selectedDepartmentId"
              @click="addDepartmentParticipants"
            >
              Agregar departamento
            </BButton>
          </div>
        </div>

        <div class="small text-muted mb-2">
          {{ selectedParticipantCount }}
          {{ selectedParticipantCount === 1 ? "participante seleccionado" : "participantes seleccionados" }}
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border rounded p-2 mb-3">
          <div class="small text-muted">
            Copia la fecha planificada en realización y vencimiento. Luego podrás modificar cada persona.
          </div>
          <BButton
            size="sm"
            variant="outline-success"
            :disabled="!form.training_date || !selectedParticipantCount"
            @click="applyPlannedDateToAllParticipants"
          >
            <i class="bx bx-calendar-check"></i>
            Usar fecha planificada para todos
          </BButton>
        </div>

        <div v-for="(participant, index) in form.participants" :key="index" class="row g-3 align-items-end border rounded p-2 mb-2">
          <div class="col-md-4">
            <label class="form-label">Funcionario</label>
            <BFormSelect
              v-model="participant.staff_id"
              :options="staffOptions(participant)"
              @change="onParticipantStaffChange(participant)"
            />
            <BFormInput
              v-if="!participant.staff_id"
              v-model="participant.employee_name"
              class="mt-2"
              placeholder="Nombre histórico o externo"
              list="risk-training-employees"
            />
          </div>
          <div class="col-md-2">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="participant.compliance_status" :options="[
              { value: 'cumplido', text: 'Cumplido' },
              { value: 'pendiente', text: 'Pendiente' },
              { value: 'no_asiste', text: 'No asiste' },
            ]" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Realización</label>
            <BFormInput v-model="participant.issued_on" type="date" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Vencimiento</label>
            <BFormInput v-model="participant.expires_on" type="date" />
          </div>
          <div class="col-md-1">
            <BButton size="sm" variant="outline-danger" class="w-100" @click="removeParticipant(index)">X</BButton>
          </div>
          <div class="col-12">
            <label class="form-label">Observación individual</label>
            <BFormInput v-model="participant.notes" />
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
