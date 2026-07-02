<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import HelpButton from "../../components/risk-prevention/help-button.vue";
import StatusBadge from "../../components/risk-prevention/status-badge.vue";
import {
  confirmRiskAction,
  formatRiskDateTime,
  formatRiskError,
  showRiskError,
  showRiskSuccess,
} from "../../components/risk-prevention/module-utils";

const emptyForm = () => ({
  id: null,
  occurred_at: "",
  accident_type: "student",
  involved_person_name: "",
  involved_person_identifier: "",
  location: "",
  description: "",
  injuries: "",
  measures_taken: "",
  referrals: "",
  case_status: "abierto",
  responsible_name: "",
  followUps: [],
});

const emptyFollowUp = () => ({
  followed_at: "",
  status: "en_seguimiento",
  notes: "",
  next_actions: "",
});

export default {
  components: { Layout, LoadingState, HelpButton, StatusBadge },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      items: [],
      filters: {
        search: "",
        accident_type: "",
        case_status: "",
        from: "",
        to: "",
      },
      showModal: false,
      form: emptyForm(),
      followUpForm: emptyFollowUp(),
    };
  },
  computed: {
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  mounted() {
    this.loadItems();
  },
  methods: {
    formatRiskDateTime,
    async loadItems() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/risk-prevention/accidents", {
          params: {
            ...this.filters,
            per_page: 100,
          },
        });
        this.items = response.data.data || [];
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo cargar el registro de accidentes.");
        showRiskError(this.error);
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = {
        ...emptyForm(),
        occurred_at: this.toLocalDateTime(new Date()),
      };
      this.followUpForm = emptyFollowUp();
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        occurred_at: this.toLocalDateTime(item.occurred_at),
        accident_type: item.accident_type || "student",
        involved_person_name: item.involved_person_name || "",
        involved_person_identifier: item.involved_person_identifier || "",
        location: item.location || "",
        description: item.description || "",
        injuries: item.injuries || "",
        measures_taken: item.measures_taken || "",
        referrals: item.referrals || "",
        case_status: item.case_status || "abierto",
        responsible_name: item.responsible_name || "",
        followUps: item.follow_ups || item.followUps || [],
      };
      this.followUpForm = {
        ...emptyFollowUp(),
        followed_at: this.toLocalDateTime(new Date()),
      };
      this.showModal = true;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        const payload = { ...this.form, occurred_at: this.fromLocalDateTime(this.form.occurred_at) };
        delete payload.id;
        delete payload.followUps;

        if (this.isEditing) {
          await axios.put(`/api/risk-prevention/accidents/${this.form.id}`, payload);
          await showRiskSuccess("El accidente fue actualizado correctamente.");
        } else {
          await axios.post("/api/risk-prevention/accidents", payload);
          await showRiskSuccess("El accidente fue registrado correctamente.");
        }

        this.showModal = false;
        this.loadItems();
      } catch (error) {
        this.error = formatRiskError(error, "No se pudo guardar el accidente.");
        showRiskError(this.error);
      } finally {
        this.saving = false;
      }
    },
    async saveFollowUp() {
      if (!this.form.id) return;

      try {
        const response = await axios.post(`/api/risk-prevention/accidents/${this.form.id}/follow-ups`, {
          ...this.followUpForm,
          followed_at: this.fromLocalDateTime(this.followUpForm.followed_at),
        });

        this.form.followUps = response.data.data.follow_ups || response.data.data.followUps || [];
        this.form.case_status = response.data.data.case_status;
        this.followUpForm = {
          ...emptyFollowUp(),
          followed_at: this.toLocalDateTime(new Date()),
        };
        await showRiskSuccess("El seguimiento fue registrado correctamente.");
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo registrar el seguimiento."));
      }
    },
    async remove(item) {
      const result = await confirmRiskAction({
        title: "Eliminar accidente",
        text: `Se eliminará el caso de ${item.involved_person_name}.`,
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        await axios.delete(`/api/risk-prevention/accidents/${item.id}`);
        await showRiskSuccess("El accidente fue eliminado correctamente.");
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el accidente."));
      }
    },
    async removeFollowUp(followUp) {
      const result = await confirmRiskAction({
        title: "Eliminar seguimiento",
        text: "Se eliminará este historial de seguimiento.",
        confirmButtonText: "Sí, eliminar",
      });
      if (!result.isConfirmed) return;

      try {
        const response = await axios.delete(`/api/risk-prevention/accident-follow-ups/${followUp.id}`);
        this.form.followUps = response.data.data.follow_ups || response.data.data.followUps || [];
        await showRiskSuccess("El seguimiento fue eliminado correctamente.");
        this.loadItems();
      } catch (error) {
        showRiskError(formatRiskError(error, "No se pudo eliminar el seguimiento."));
      }
    },
    typeLabel(value) {
      const labels = {
        student: "Estudiante",
        staff: "Funcionario",
        visit: "Visita",
      };
      return labels[value] || value;
    },
    toLocalDateTime(value) {
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return "";
      const offset = date.getTimezoneOffset();
      const local = new Date(date.getTime() - offset * 60000);
      return local.toISOString().slice(0, 16);
    },
    fromLocalDateTime(value) {
      return value ? new Date(value).toISOString().slice(0, 19).replace("T", " ") : null;
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Registro de Accidentes</h4>
        <div class="text-muted">Accidentes laborales, escolares y de visitas con seguimiento del caso.</div>
      </div>
      <div class="d-flex gap-2">
        <HelpButton
          title="Ayuda: registro de accidentes"
          text="Permite documentar accidentes, medidas adoptadas, derivaciones y seguimiento hasta el cierre del caso."
        />
        <BButton variant="primary" @click="openCreate">Nuevo accidente</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <BFormInput v-model="filters.search" placeholder="Persona, lugar o descripción" @keyup.enter="loadItems" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="filters.accident_type" :options="[
            { value: '', text: 'Todos' },
            { value: 'student', text: 'Estudiante' },
            { value: 'staff', text: 'Funcionario' },
            { value: 'visit', text: 'Visita' },
          ]" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.case_status" :options="[
            { value: '', text: 'Todos' },
            { value: 'abierto', text: 'Abierto' },
            { value: 'en_seguimiento', text: 'En seguimiento' },
            { value: 'cerrado', text: 'Cerrado' },
          ]" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Desde</label>
          <BFormInput v-model="filters.from" type="date" />
        </div>
        <div class="col-md-2">
          <label class="form-label">Hasta</label>
          <BFormInput v-model="filters.to" type="date" />
        </div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="secondary" @click="loadItems">Filtrar</BButton>
          <BButton
            variant="outline-secondary"
            @click="filters = { search: '', accident_type: '', case_status: '', from: '', to: '' }; loadItems()"
          >
            Limpiar
          </BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <BCard>
      <LoadingState v-if="loading" message="Cargando accidentes..." />
      <div v-else class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Persona involucrada</th>
              <th>Lugar</th>
              <th>Lesiones</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>{{ formatRiskDateTime(item.occurred_at) }}</td>
              <td>{{ typeLabel(item.accident_type) }}</td>
              <td>
                <div class="fw-semibold">{{ item.involved_person_name }}</div>
                <div class="small text-muted">{{ item.involved_person_identifier || "-" }}</div>
              </td>
              <td>{{ item.location }}</td>
              <td>{{ item.injuries || "-" }}</td>
              <td><StatusBadge :status="item.case_status" /></td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2">
                  <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Ver / Editar</BButton>
                  <BButton size="sm" variant="outline-danger" @click="remove(item)">Eliminar</BButton>
                </div>
              </td>
            </tr>
            <tr v-if="!items.length">
              <td colspan="7" class="text-center text-muted py-4">No hay accidentes registrados.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <BModal v-model="showModal" size="xl" :title="isEditing ? 'Detalle de accidente' : 'Nuevo accidente'" hide-footer>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">Registra hechos, lesiones, medidas y responsables para evitar casos sin trazabilidad.</div>
        <HelpButton
          title="Ayuda del formulario"
          text="Completa fecha, persona involucrada, descripción, lesiones y medidas adoptadas. Si el caso ya existe, agrega seguimientos."
        />
      </div>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Fecha y hora</label>
          <BFormInput v-model="form.occurred_at" type="datetime-local" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="form.accident_type" :options="[
            { value: 'student', text: 'Estudiante' },
            { value: 'staff', text: 'Funcionario' },
            { value: 'visit', text: 'Visita' },
          ]" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.case_status" :options="[
            { value: 'abierto', text: 'Abierto' },
            { value: 'en_seguimiento', text: 'En seguimiento' },
            { value: 'cerrado', text: 'Cerrado' },
          ]" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Persona involucrada</label>
          <BFormInput v-model="form.involved_person_name" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Referencia</label>
          <BFormInput v-model="form.involved_person_identifier" placeholder="Curso, cargo u observación" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Lugar</label>
          <BFormInput v-model="form.location" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Responsable del caso</label>
          <BFormInput v-model="form.responsible_name" />
        </div>
        <div class="col-12">
          <label class="form-label">Descripción del accidente</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Lesiones</label>
          <BFormTextarea v-model="form.injuries" rows="3" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Medidas adoptadas</label>
          <BFormTextarea v-model="form.measures_taken" rows="3" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Derivaciones</label>
          <BFormTextarea v-model="form.referrals" rows="3" />
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="secondary" @click="showModal = false">Cerrar</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar" }}</BButton>
      </div>

      <BCard v-if="isEditing" class="mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h5 class="mb-0">Historial de seguimiento</h5>
            <div class="small text-muted">Registro cronológico del tratamiento del caso.</div>
          </div>
          <HelpButton
            title="Ayuda: historial de seguimiento"
            text="Agrega hitos de seguimiento para mantener medidas, derivaciones y cierre documentado del caso."
          />
        </div>

        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Notas</th>
                <th>Próximo paso</th>
                <th class="text-end">Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="followUp in form.followUps" :key="followUp.id">
                <td>{{ formatRiskDateTime(followUp.followed_at) }}</td>
                <td><StatusBadge :status="followUp.status" /></td>
                <td>{{ followUp.notes }}</td>
                <td>{{ followUp.next_actions || "-" }}</td>
                <td class="text-end">
                  <BButton size="sm" variant="outline-danger" @click="removeFollowUp(followUp)">Eliminar</BButton>
                </td>
              </tr>
              <tr v-if="!form.followUps.length">
                <td colspan="5" class="text-center text-muted py-3">Sin seguimientos registrados.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Fecha y hora</label>
            <BFormInput v-model="followUpForm.followed_at" type="datetime-local" />
          </div>
          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="followUpForm.status" :options="[
              { value: 'abierto', text: 'Abierto' },
              { value: 'en_seguimiento', text: 'En seguimiento' },
              { value: 'cerrado', text: 'Cerrado' },
            ]" />
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <BButton variant="outline-primary" class="w-100" @click="saveFollowUp">Agregar seguimiento</BButton>
          </div>
          <div class="col-md-6">
            <label class="form-label">Notas</label>
            <BFormTextarea v-model="followUpForm.notes" rows="3" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Próximas acciones</label>
            <BFormTextarea v-model="followUpForm.next_actions" rows="3" />
          </div>
        </div>
      </BCard>
    </BModal>
  </Layout>
</template>
