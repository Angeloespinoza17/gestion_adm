<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import SupportHelpButton from "../help-button.vue";
import SupportStatusBadge from "../status-badge.vue";
import SupportStudentSearch from "../student-search.vue";
import SupportDocumentPanel from "../document-panel.vue";
import {
  confirmSupportAction,
  confirmSupportCancel,
  formatSupportDate,
  formatSupportError,
  humanizeSupportStatus,
  normalizeOptions,
  showSupportError,
  showSupportSuccess,
} from "../module-utils";

export default {
  components: {
    LoadingState,
    SupportHelpButton,
    SupportStatusBadge,
    SupportStudentSearch,
    SupportDocumentPanel,
  },
  props: {
    catalogs: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      items: [],
      selectedPlan: null,
      showModal: false,
      filters: {
        status: null,
        student_profile_id: null,
        area_name: null,
      },
      form: this.emptyForm(),
    };
  },
  computed: {
    statusOptions() {
      return normalizeOptions(this.catalogs.plan_status_options, true);
    },
    editStatusOptions() {
      return normalizeOptions(this.catalogs.plan_status_options);
    },
    areaOptions() {
      return normalizeOptions(this.catalogs.area_options);
    },
    filterAreaOptions() {
      return [{ value: null, text: "Todas" }].concat(
        (this.catalogs.area_options || []).map((item) => ({
          value: item.label,
          text: item.label,
        }))
      );
    },
    professionalOptions() {
      return [{ value: null, text: "Responsable actual" }].concat(
        (this.catalogs.professionals || []).map((item) => ({
          value: item.id,
          text: `${item.staff?.full_name || item.user?.name || item.professional_role_name} · ${item.area_name}`,
        }))
      );
    },
    confidentialityOptions() {
      return normalizeOptions(this.catalogs.confidentiality_options);
    },
    actionStatusOptions() {
      return [
        { value: "pendiente", text: "Pendiente" },
        { value: "en_proceso", text: "En proceso" },
        { value: "cerrada", text: "Cerrada" },
      ];
    },
    canUploadDocuments() {
      return Boolean(this.selectedPlan && this.catalogs.capabilities?.can_create_plan);
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    formatSupportDate,
    humanizeSupportStatus,
    emptyForm() {
      return {
        id: null,
        student_profile_id: null,
        student_label: "",
        responsible_professional_id: null,
        responsible_user_id: null,
        area_slug: "psicologia",
        area_name: "Psicología",
        motive: "",
        general_objective: "",
        specific_objectives_text: "",
        actions_summary: "",
        responsibles_summary: "",
        start_date: new Date().toISOString().slice(0, 10),
        end_date: "",
        indicators: "",
        status: "disenado",
        evidences: "",
        observations: "",
        confidentiality_level: "reservada",
        actions: [this.emptyAction()],
      };
    },
    emptyAction() {
      return {
        action_description: "",
        responsible_label: "",
        due_date: "",
        completed_at: "",
        status: "pendiente",
        observations: "",
      };
    },
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/plans", {
          params: this.filters,
        });
        this.items = response.data.data || [];

        if (this.selectedPlan?.id) {
          const exists = this.items.find((item) => item.id === this.selectedPlan.id);
          if (exists) {
            await this.openPlan(this.selectedPlan.id);
            return;
          }
        }

        if (this.items[0]) {
          await this.openPlan(this.items[0].id);
        } else {
          this.selectedPlan = null;
        }
      } catch (error) {
        this.error = formatSupportError(error, "No se pudieron cargar los planes de apoyo.");
      } finally {
        this.loading = false;
      }
    },
    async openPlan(id) {
      try {
        const response = await axios.get(`/api/apoyo-profesional/plans/${id}`);
        this.selectedPlan = response.data.data;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo cargar el plan seleccionado.");
      }
    },
    selectFilterStudent(student) {
      this.filters.student_profile_id = student.id;
      this.load();
    },
    selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(plan) {
      const source = plan?.actions ? plan : this.selectedPlan || plan;
      this.form = {
        id: source.id,
        student_profile_id: source.student_profile_id,
        student_label: source.student?.full_name || "",
        responsible_professional_id: source.responsible_professional_id,
        responsible_user_id: source.responsible_user_id,
        area_slug: source.area_slug || "otra",
        area_name: source.area_name || "Otra",
        motive: source.motive,
        general_objective: source.general_objective,
        specific_objectives_text: (source.specific_objectives || []).join("\n"),
        actions_summary: source.actions_summary || "",
        responsibles_summary: source.responsibles_summary || "",
        start_date: source.start_date || "",
        end_date: source.end_date || "",
        indicators: source.indicators || "",
        status: source.status,
        evidences: source.evidences || "",
        observations: source.observations || "",
        confidentiality_level: source.confidentiality_level || "reservada",
        actions: (source.actions || []).length
          ? source.actions.map((action) => ({
              action_description: action.action_description || "",
              responsible_label: action.responsible_label || "",
              due_date: action.due_date || "",
              completed_at: action.completed_at ? String(action.completed_at).slice(0, 10) : "",
              status: action.status || "pendiente",
              observations: action.observations || "",
            }))
          : [this.emptyAction()],
      };
      this.showModal = true;
    },
    syncProfessionalContext() {
      const professional = (this.catalogs.professionals || []).find(
        (item) => item.id === this.form.responsible_professional_id
      );

      if (!professional) {
        return;
      }

      this.form.responsible_user_id = professional.user_id || null;
      this.form.area_slug = professional.area_slug || this.form.area_slug;
      this.form.area_name = professional.area_name || this.form.area_name;
    },
    addAction() {
      this.form.actions.push(this.emptyAction());
    },
    removeAction(index) {
      if (this.form.actions.length === 1) {
        this.form.actions = [this.emptyAction()];
        return;
      }

      this.form.actions.splice(index, 1);
    },
    buildPayload() {
      return {
        student_profile_id: this.form.student_profile_id,
        responsible_professional_id: this.form.responsible_professional_id,
        responsible_user_id: this.form.responsible_user_id,
        area_slug: this.form.area_slug,
        area_name: this.form.area_name,
        motive: this.form.motive,
        general_objective: this.form.general_objective,
        specific_objectives: (this.form.specific_objectives_text || "")
          .split("\n")
          .map((item) => item.trim())
          .filter(Boolean),
        actions_summary: this.form.actions_summary,
        responsibles_summary: this.form.responsibles_summary,
        start_date: this.form.start_date,
        end_date: this.form.end_date || null,
        indicators: this.form.indicators,
        status: this.form.status,
        evidences: this.form.evidences,
        observations: this.form.observations,
        confidentiality_level: this.form.confidentiality_level,
        actions: (this.form.actions || []).map((action) => ({
          action_description: action.action_description,
          responsible_label: action.responsible_label || null,
          due_date: action.due_date || null,
          completed_at: action.completed_at || null,
          status: action.status || "pendiente",
          observations: action.observations || null,
        })),
      };
    },
    async save() {
      const confirmation = await confirmSupportAction({
        title: this.form.id ? "Editar plan de apoyo" : "Crear plan de apoyo",
        text: this.form.id
          ? "Se actualizará el plan de apoyo seleccionado."
          : "Se registrará un nuevo plan de apoyo para el estudiante.",
        confirmButtonText: this.form.id ? "Guardar cambios" : "Crear plan",
      });

      if (!confirmation.isConfirmed) return;

      this.saving = true;
      try {
        this.syncProfessionalContext();
        const payload = this.buildPayload();
        const response = this.form.id
          ? await axios.put(`/api/apoyo-profesional/plans/${this.form.id}`, payload)
          : await axios.post("/api/apoyo-profesional/plans", payload);

        this.showModal = false;
        await this.load();
        await this.openPlan(response.data.data.id);
        await showSupportSuccess(
          this.form.id ? "Plan actualizado correctamente." : "Plan registrado correctamente."
        );
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo guardar el plan de apoyo."));
      } finally {
        this.saving = false;
      }
    },
    async cancelForm() {
      const confirmation = await confirmSupportCancel("este formulario");
      if (confirmation.isConfirmed) {
        this.showModal = false;
      }
    },
  },
};
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
      <BButton variant="primary" @click="openCreate()">Nuevo plan de apoyo</BButton>
      <SupportHelpButton
        title="Ayuda: planes de apoyo"
        text="En esta sección se diseñan, ejecutan y monitorean planes de apoyo por estudiante, incluyendo objetivos, acciones, responsables, indicadores y evidencias."
      />
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-xl-5">
          <SupportStudentSearch button-label="Filtrar" @selected="selectFilterStudent" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="filters.status" :options="statusOptions" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Área</label>
          <BFormSelect v-model="filters.area_name" :options="filterAreaOptions" />
        </div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="primary" @click="load">Aplicar filtros</BButton>
          <BButton
            variant="outline-secondary"
            @click="filters = { status: null, student_profile_id: null, area_name: null }; load();"
          >
            Limpiar
          </BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading" class="border-0 shadow-sm">
      <LoadingState message="Cargando planes de apoyo..." compact />
    </BCard>

    <div v-else class="row g-3">
      <div class="col-xl-4">
        <BCard class="h-100">
          <template #header><div class="fw-semibold">Planes registrados</div></template>
          <div v-if="!items.length" class="text-muted">No hay planes de apoyo para los filtros seleccionados.</div>
          <div v-else class="d-grid gap-2">
            <button
              v-for="item in items"
              :key="item.id"
              type="button"
              class="btn btn-light text-start border"
              @click="openPlan(item.id)"
            >
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold">{{ item.student?.full_name || "Estudiante" }}</div>
                <SupportStatusBadge :status="item.status" />
              </div>
              <div class="small">{{ item.motive }}</div>
              <div class="small text-muted">{{ item.area_name || "Sin área" }} · {{ formatSupportDate(item.start_date) }}</div>
            </button>
          </div>
        </BCard>
      </div>

      <div class="col-xl-8">
        <div v-if="!selectedPlan" class="text-muted small">
          Selecciona un plan para revisar objetivos, acciones, evidencias y adjuntos.
        </div>

        <div v-else class="d-grid gap-3">
          <BCard>
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Detalle del plan</div>
                <div class="d-flex gap-2 flex-wrap">
                  <BButton size="sm" variant="outline-secondary" @click="openEdit(selectedPlan)">Editar</BButton>
                </div>
              </div>
            </template>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="small text-muted">Estudiante</div>
                <div>{{ selectedPlan.student?.full_name || "-" }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Responsable</div>
                <div>
                  {{
                    selectedPlan.responsibleProfessional?.staff?.full_name
                      || selectedPlan.responsibleUser?.name
                      || "-"
                  }}
                </div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Área</div>
                <div>{{ selectedPlan.area_name || "-" }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Estado</div>
                <div><SupportStatusBadge :status="selectedPlan.status" /></div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Confidencialidad</div>
                <div><SupportStatusBadge :status="selectedPlan.confidentiality_level" /></div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Motivo</div>
                <div>{{ selectedPlan.motive }}</div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Objetivo general</div>
                <div>{{ selectedPlan.general_objective }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Objetivos específicos</div>
                <ul class="mb-0 small ps-3">
                  <li v-for="(objective, index) in selectedPlan.specific_objectives || []" :key="index">
                    {{ objective }}
                  </li>
                </ul>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Indicadores</div>
                <div>{{ selectedPlan.indicators || "Sin indicadores definidos." }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Responsables</div>
                <div>{{ selectedPlan.responsibles_summary || "Sin responsables complementarios." }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Acciones resumidas</div>
                <div>{{ selectedPlan.actions_summary || "Sin resumen de acciones." }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Inicio</div>
                <div>{{ formatSupportDate(selectedPlan.start_date) }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Término</div>
                <div>{{ formatSupportDate(selectedPlan.end_date) }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Observaciones</div>
                <div>{{ selectedPlan.observations || "Sin observaciones." }}</div>
              </div>
            </div>
          </BCard>

          <BCard>
            <template #header><div class="fw-semibold">Acciones del plan</div></template>
            <div v-if="!(selectedPlan.actions || []).length" class="text-muted">
              Sin acciones detalladas registradas.
            </div>
            <div v-else class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Acción</th>
                    <th>Responsable</th>
                    <th>Vence</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="action in selectedPlan.actions" :key="action.id">
                    <td>{{ action.action_description }}</td>
                    <td>{{ action.responsible_label || "-" }}</td>
                    <td>{{ formatSupportDate(action.due_date) }}</td>
                    <td><SupportStatusBadge :status="action.status" /></td>
                    <td>{{ action.observations || "-" }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCard>

          <SupportDocumentPanel
            :documents="selectedPlan.documents || []"
            :categories="catalogs.document_categories"
            :student-id="selectedPlan.student_profile_id"
            :upload-url="`/api/apoyo-profesional/plans/${selectedPlan.id}/documents`"
            :can-upload="canUploadDocuments"
            @refresh="openPlan(selectedPlan.id)"
          />
        </div>
      </div>
    </div>

    <BModal
      v-model="showModal"
      :title="form.id ? 'Editar plan de apoyo' : 'Nuevo plan de apoyo'"
      hide-footer
      size="xl"
    >
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Estudiante</label>
          <SupportStudentSearch button-label="Seleccionar" @selected="selectFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-1">Seleccionado: {{ form.student_label }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Profesional responsable</label>
          <BFormSelect
            v-model="form.responsible_professional_id"
            :options="professionalOptions"
            @change="syncProfessionalContext"
          />
        </div>
        <div class="col-md-3">
          <label class="form-label">Área</label>
          <BFormSelect v-model="form.area_slug" :options="areaOptions" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <BFormSelect v-model="form.status" :options="editStatusOptions" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Motivo</label>
          <BFormInput v-model="form.motive" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Confidencialidad</label>
          <BFormSelect v-model="form.confidentiality_level" :options="confidentialityOptions" />
        </div>
        <div class="col-12">
          <label class="form-label">Objetivo general</label>
          <BFormTextarea v-model="form.general_objective" rows="3" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Objetivos específicos</label>
          <BFormTextarea
            v-model="form.specific_objectives_text"
            rows="4"
            placeholder="Un objetivo por línea"
          />
        </div>
        <div class="col-md-6">
          <label class="form-label">Indicadores</label>
          <BFormTextarea v-model="form.indicators" rows="4" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Acciones resumidas</label>
          <BFormTextarea v-model="form.actions_summary" rows="3" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Responsables</label>
          <BFormTextarea v-model="form.responsibles_summary" rows="3" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha inicio</label>
          <BFormInput v-model="form.start_date" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha término</label>
          <BFormInput v-model="form.end_date" type="date" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Evidencias</label>
          <BFormTextarea v-model="form.evidences" rows="2" />
        </div>
        <div class="col-12">
          <label class="form-label">Observaciones</label>
          <BFormTextarea v-model="form.observations" rows="2" />
        </div>
      </div>

      <hr class="my-4" />

      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div class="fw-semibold">Acciones del plan</div>
        <BButton size="sm" variant="outline-primary" @click="addAction">Agregar acción</BButton>
      </div>

      <div class="d-grid gap-3">
        <BCard v-for="(action, index) in form.actions" :key="index" class="border">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Acción</label>
              <BFormInput v-model="action.action_description" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Responsable</label>
              <BFormInput v-model="action.responsible_label" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Estado</label>
              <BFormSelect v-model="action.status" :options="actionStatusOptions" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha compromiso</label>
              <BFormInput v-model="action.due_date" type="date" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha cierre</label>
              <BFormInput v-model="action.completed_at" type="date" />
            </div>
            <div class="col-md-4 d-flex align-items-end justify-content-end">
              <BButton size="sm" variant="outline-danger" @click="removeAction(index)">Eliminar acción</BButton>
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="action.observations" rows="2" />
            </div>
          </div>
        </BCard>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BButton variant="outline-secondary" @click="cancelForm">Cancelar registro</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          {{ saving ? "Guardando..." : "Guardar plan" }}
        </BButton>
      </div>
    </BModal>
  </div>
</template>
