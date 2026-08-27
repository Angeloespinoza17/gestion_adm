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
  event_type: "accidente",
  staff_id: null,
  involved_person_name: "",
  involved_person_identifier: "",
  location: "",
  description: "",
  injuries: "",
  injured_body_part: "",
  lost_days: 0,
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
      catalogs: { staff_members: [] },
      summary: { year: new Date().getFullYear(), total_cases: 0, lost_days: 0, accidents: 0, occupational_diseases: 0 },
      filters: {
        search: "",
        accident_type: "",
        event_type: "",
        case_status: "",
        from: "",
        to: "",
        summary_year: new Date().getFullYear(),
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
    isStaffCase() {
      return this.form.accident_type === "staff";
    },
  },
  async mounted() {
    await Promise.all([this.loadCatalogs(), this.loadItems()]);
  },
  methods: {
    formatRiskDateTime,
    async loadCatalogs() {
      const response = await axios.get("/api/risk-prevention/catalogs");
      this.catalogs = response.data || this.catalogs;
    },
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
        this.summary = response.data.summary || this.summary;
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
        event_type: item.event_type || "accidente",
        staff_id: item.staff_id || null,
        involved_person_name: item.involved_person_name || "",
        involved_person_identifier: item.involved_person_identifier || "",
        location: item.location || "",
        description: item.description || "",
        injuries: item.injuries || "",
        injured_body_part: item.injured_body_part || "",
        lost_days: Number(item.lost_days || 0),
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
    eventLabel(value) {
      return value === "enfermedad_profesional" ? "Enfermedad profesional" : "Accidente";
    },
    onAccidentTypeChange() {
      if (this.form.accident_type === "staff") return;
      this.form.staff_id = null;
      this.form.event_type = "accidente";
      this.form.lost_days = 0;
      this.form.injured_body_part = "";
    },
    onStaffChange() {
      const staff = (this.catalogs.staff_members || [])
        .find((item) => Number(item.id) === Number(this.form.staff_id));
      if (!staff) return;
      this.form.involved_person_name = staff.name || "";
      this.form.involved_person_identifier = staff.rut || "";
    },
    clearFilters() {
      this.filters = {
        search: "",
        accident_type: "",
        event_type: "",
        case_status: "",
        from: "",
        to: "",
        summary_year: new Date().getFullYear(),
      };
      this.loadItems();
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
    <section class="accident-hero mb-4">
      <div>
        <span>Salud ocupacional y seguridad</span>
        <h1>Accidentes y enfermedades profesionales</h1>
        <p>Seguimiento de casos, zonas lesionadas y días perdidos acumulados por funcionario.</p>
      </div>
      <div class="d-flex gap-2">
        <HelpButton
          title="Ayuda: registro de accidentes"
          text="Los días de licencia se registran como días perdidos. El acumulado anual se calcula automáticamente por funcionario e incluye accidentes laborales y enfermedades profesionales."
        />
        <BButton variant="primary" @click="openCreate">Nuevo accidente</BButton>
      </div>
    </section>

    <section class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3"><article class="accident-metric"><i class="bx bx-briefcase-alt-2 blue"></i><div><strong>{{ summary.total_cases }}</strong><span>Casos de funcionarios · {{ summary.year }}</span></div></article></div>
      <div class="col-sm-6 col-xl-3"><article class="accident-metric"><i class="bx bx-calendar-x red"></i><div><strong>{{ summary.lost_days }}</strong><span>Días perdidos acumulados</span></div></article></div>
      <div class="col-sm-6 col-xl-3"><article class="accident-metric"><i class="bx bx-first-aid green"></i><div><strong>{{ summary.accidents }}</strong><span>Accidentes laborales</span></div></article></div>
      <div class="col-sm-6 col-xl-3"><article class="accident-metric"><i class="bx bx-pulse violet"></i><div><strong>{{ summary.occupational_diseases }}</strong><span>Enfermedades profesionales</span></div></article></div>
    </section>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-3">
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
          <label class="form-label">Evento laboral</label>
          <BFormSelect v-model="filters.event_type" :options="[
            { value: '', text: 'Todos' },
            { value: 'accidente', text: 'Accidente' },
            { value: 'enfermedad_profesional', text: 'Enfermedad profesional' },
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
        <div class="col-md-2">
          <label class="form-label">Año resumen</label>
          <BFormInput v-model.number="filters.summary_year" type="number" min="2000" max="2100" />
        </div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="secondary" @click="loadItems">Filtrar</BButton>
          <BButton
            variant="outline-secondary"
            @click="clearFilters"
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
              <th>Tipo de evento</th>
              <th>Persona involucrada</th>
              <th>Lugar</th>
              <th>Zona lesionada</th>
              <th>Días perdidos</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>{{ formatRiskDateTime(item.occurred_at) }}</td>
              <td>
                <div class="fw-semibold">{{ item.accident_type === 'staff' ? eventLabel(item.event_type) : typeLabel(item.accident_type) }}</div>
                <div v-if="item.accident_type === 'staff'" class="small text-muted">Funcionario</div>
              </td>
              <td>
                <div class="fw-semibold">{{ item.involved_person_name }}</div>
                <div class="small text-muted">{{ item.involved_person_identifier || "-" }}</div>
              </td>
              <td>{{ item.location }}</td>
              <td><div>{{ item.injured_body_part || "-" }}</div><div class="small text-muted">{{ item.injuries || "Sin detalle" }}</div></td>
              <td>
                <strong>{{ item.lost_days || 0 }}</strong>
                <div v-if="item.accident_type === 'staff'" class="small text-muted">Acum. anual: {{ item.annual_lost_days || 0 }}</div>
              </td>
              <td><StatusBadge :status="item.case_status" /></td>
              <td class="text-end">
                <div class="d-flex justify-content-end gap-2">
                  <BButton size="sm" variant="outline-primary" @click="openEdit(item)">Ver / Editar</BButton>
                  <BButton size="sm" variant="outline-danger" @click="remove(item)">Eliminar</BButton>
                </div>
              </td>
            </tr>
            <tr v-if="!items.length">
              <td colspan="8" class="text-center text-muted py-4">No hay accidentes registrados.</td>
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
          ]" @change="onAccidentTypeChange" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.case_status" :options="[
            { value: 'abierto', text: 'Abierto' },
            { value: 'en_seguimiento', text: 'En seguimiento' },
            { value: 'cerrado', text: 'Cerrado' },
          ]" />
        </div>
        <div v-if="isStaffCase" class="col-md-6">
          <label class="form-label">Tipo de evento laboral</label>
          <BFormSelect v-model="form.event_type" :options="[
            { value: 'accidente', text: 'Accidente de funcionario' },
            { value: 'enfermedad_profesional', text: 'Enfermedad profesional' },
          ]" />
        </div>
        <div v-if="isStaffCase" class="col-md-6">
          <label class="form-label">Funcionario registrado</label>
          <BFormSelect
            v-model="form.staff_id"
            :options="[{ value: null, text: 'Registro histórico o externo' }].concat((catalogs.staff_members || []).map((item) => ({ value: item.id, text: `${item.name}${item.rut ? ` · ${item.rut}` : ''}` })))"
            @change="onStaffChange"
          />
        </div>
        <div class="col-md-6">
          <label class="form-label">Persona involucrada</label>
          <BFormInput v-model="form.involved_person_name" :disabled="Boolean(form.staff_id)" />
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ isStaffCase ? 'RUT / referencia exacta' : 'Referencia' }}</label>
          <BFormInput v-model="form.involved_person_identifier" :disabled="Boolean(form.staff_id)" placeholder="Curso, cargo u observación" />
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
          <label class="form-label">Descripción del evento</label>
          <BFormTextarea v-model="form.description" rows="3" />
        </div>
        <div v-if="isStaffCase" class="col-md-4">
          <label class="form-label">Parte del cuerpo lesionada</label>
          <BFormInput v-model="form.injured_body_part" list="injured-body-parts" placeholder="Ej: Mano derecha" />
          <datalist id="injured-body-parts">
            <option v-for="part in ['Cabeza', 'Ojos', 'Cuello', 'Espalda', 'Hombro', 'Brazo', 'Codo', 'Mano', 'Dedos', 'Cadera', 'Pierna', 'Rodilla', 'Tobillo', 'Pie', 'Vías respiratorias', 'Múltiples zonas']" :key="part" :value="part"></option>
          </datalist>
        </div>
        <div v-if="isStaffCase" class="col-md-4">
          <label class="form-label">Días de licencia / perdidos</label>
          <BFormInput v-model.number="form.lost_days" type="number" min="0" max="3650" />
          <small class="text-muted">Incluye licencias originadas por accidente o enfermedad profesional.</small>
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

<style scoped>
.accident-hero { display:flex; align-items:center; justify-content:space-between; gap:1.5rem; padding:1.65rem 1.8rem; color:#fff; border-radius:22px; background:radial-gradient(circle at 85% 10%,rgba(139,92,246,.28),transparent 33%),linear-gradient(135deg,#172a46,#1d5f73 65%,#287e79); box-shadow:0 16px 42px rgba(23,42,70,.18); }
.accident-hero>div:first-child>span { color:#a9ece2; font-size:.72rem; font-weight:800; letter-spacing:.11em; text-transform:uppercase; }
.accident-hero h1 { margin:.35rem 0 .25rem; font-size:clamp(1.55rem,2.8vw,2.25rem); font-weight:800; }
.accident-hero p { margin:0; color:#dceff1; }
.accident-metric { display:flex; align-items:center; gap:.85rem; height:100%; padding:1.05rem; border:1px solid #e3eaef; border-radius:16px; background:#fff; box-shadow:0 7px 22px rgba(32,54,78,.06); }
.accident-metric>i { display:grid; place-items:center; width:44px; height:44px; border-radius:13px; font-size:1.25rem; }
.accident-metric i.blue{color:#2168b4;background:#e8f2ff}.accident-metric i.red{color:#c24753;background:#ffedef}.accident-metric i.green{color:#087f5b;background:#e3f8ef}.accident-metric i.violet{color:#6b4bc4;background:#f0ecff}
.accident-metric div { display:flex; flex-direction:column; }.accident-metric strong{color:#18324d;font-size:1.35rem;line-height:1.1}.accident-metric span{color:#718096;font-size:.78rem}
@media (max-width:767px){.accident-hero{align-items:flex-start;flex-direction:column}.accident-hero>div:last-child{width:100%;justify-content:space-between}}
</style>
