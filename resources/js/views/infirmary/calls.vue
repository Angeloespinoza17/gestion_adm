<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import InfirmaryHelpButton from "../../components/infirmary/help-button.vue";
import InfirmaryStatusBadge from "../../components/infirmary/status-badge.vue";
import InfirmaryStudentSearch from "../../components/infirmary/student-search.vue";
import {
  confirmInfirmaryAction,
  confirmInfirmaryCancel,
  formatInfirmaryDateTime,
  formatInfirmaryError,
  normalizeOptions,
  showInfirmaryError,
  showInfirmarySuccess,
  showInfirmaryWarning,
  toInputDateTime,
} from "../../components/infirmary/module-utils";

export default {
  components: {
    Layout,
    LoadingState,
    InfirmaryHelpButton,
    InfirmaryStatusBadge,
    InfirmaryStudentSearch,
  },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      catalogs: {
        call_status_options: [],
        users: [],
      },
      filters: {
        search: "",
        call_status: null,
        from: "",
        to: "",
        student_profile_id: null,
      },
      calls: [],
      pagination: { current_page: 1, total: 0, per_page: 15 },
      selectedCall: null,
      showModal: false,
      form: this.emptyForm(),
    };
  },
  computed: {
    callStatusOptions() {
      return normalizeOptions(this.catalogs.call_status_options, true);
    },
    userOptions() {
      return [{ value: null, text: "Automático" }].concat(
        (this.catalogs.users || []).map((item) => ({
          value: item.id,
          text: item.name,
        }))
      );
    },
    isEditing() {
      return Boolean(this.form.id);
    },
  },
  async mounted() {
    await this.loadCatalogs();
    await this.loadCalls();
  },
  methods: {
    formatInfirmaryDateTime,
    normalizeOptions,
    emptyForm() {
      return {
        id: null,
        student_profile_id: null,
        student_label: "",
        attention_id: null,
        called_at: toInputDateTime(new Date().toISOString()),
        person_contacted: "",
        relationship: "",
        phone_number: "",
        call_status: "pendiente",
        reason: "",
        conversation_summary: "",
        commitments: "",
        estimated_arrival_at: "",
        duration_minutes: 5,
        called_by_user_id: null,
      };
    },
    async loadCatalogs() {
      const response = await axios.get("/api/infirmary/catalogs");
      this.catalogs = response.data;
    },
    async loadCalls(page = 1) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/infirmary/calls", {
          params: {
            page,
            ...this.filters,
          },
        });
        this.calls = response.data.data || [];
        this.pagination = {
          current_page: response.data.current_page || 1,
          total: response.data.total || 0,
          per_page: response.data.per_page || 15,
        };
        this.selectedCall = this.calls[0] || null;
      } catch (error) {
        this.error = formatInfirmaryError(error, "No se pudo cargar el registro de llamados.");
      } finally {
        this.loading = false;
      }
    },
    selectStudent(student) {
      this.filters.student_profile_id = student.id;
      this.loadCalls(1);
    },
    selectFormStudent(student) {
      this.form.student_profile_id = student.id;
      this.form.student_label = student.full_name;
    },
    resetFilters() {
      this.filters = {
        search: "",
        call_status: null,
        from: "",
        to: "",
        student_profile_id: null,
      };
      this.loadCalls(1);
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(call) {
      this.form = {
        id: call.id,
        student_profile_id: call.student_profile_id,
        student_label: call.student ? `${call.student.first_name} ${call.student.last_name}` : "",
        attention_id: call.attention_id,
        called_at: toInputDateTime(call.called_at),
        person_contacted: call.person_contacted || "",
        relationship: call.relationship || "",
        phone_number: call.phone_number || "",
        call_status: call.call_status || "pendiente",
        reason: call.reason || "",
        conversation_summary: call.conversation_summary || "",
        commitments: call.commitments || "",
        estimated_arrival_at: toInputDateTime(call.estimated_arrival_at),
        duration_minutes: call.duration_minutes || 5,
        called_by_user_id: call.called_by_user_id || null,
      };
      this.showModal = true;
    },
    async cancelModal() {
      const result = await confirmInfirmaryCancel("los cambios del llamado");
      if (result.isConfirmed) {
        this.showModal = false;
      }
    },
    async save() {
      if (!this.form.student_profile_id) {
        await showInfirmaryWarning("Debes seleccionar una estudiante.");
        return;
      }

      this.saving = true;
      try {
        const payload = {
          student_profile_id: this.form.student_profile_id,
          attention_id: this.form.attention_id || null,
          called_at: this.form.called_at,
          person_contacted: this.form.person_contacted,
          relationship: this.form.relationship || null,
          phone_number: this.form.phone_number || null,
          call_status: this.form.call_status,
          reason: this.form.reason || null,
          conversation_summary: this.form.conversation_summary || null,
          commitments: this.form.commitments || null,
          estimated_arrival_at: this.form.estimated_arrival_at || null,
          duration_minutes: this.form.duration_minutes || null,
          called_by_user_id: this.form.called_by_user_id || null,
        };

        if (this.isEditing) {
          await axios.put(`/api/infirmary/calls/${this.form.id}`, payload);
        } else {
          await axios.post("/api/infirmary/calls", payload);
        }

        this.showModal = false;
        await this.loadCalls(this.pagination.current_page || 1);
        await showInfirmarySuccess(this.isEditing ? "Llamado actualizado correctamente." : "Llamado registrado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo guardar el llamado."));
      } finally {
        this.saving = false;
      }
    },
    async remove(call) {
      const confirmation = await confirmInfirmaryAction({
        title: "Eliminar llamado",
        text: `Se eliminará el registro de llamada a ${call.person_contacted}.`,
        confirmButtonText: "Eliminar",
      });

      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/infirmary/calls/${call.id}`);
        await this.loadCalls(this.pagination.current_page || 1);
        await showInfirmarySuccess("Llamado eliminado correctamente.");
      } catch (error) {
        await showInfirmaryError(formatInfirmaryError(error, "No se pudo eliminar el llamado."));
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Registro de llamados</h4>
        <div class="text-muted">
          Bitácora de contacto con apoderados, compromisos y seguimiento telefónico asociado a enfermería.
        </div>
      </div>
      <div class="d-flex gap-2">
        <InfirmaryHelpButton
          title="Ayuda: registro de llamados"
          text="En esta pantalla se registran todos los llamados a apoderados, indicando resultado, motivo, duración, compromisos y vínculo con la atención."
        />
        <BButton variant="primary" @click="openCreate">Nuevo llamado</BButton>
      </div>
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-xl-6">
          <InfirmaryStudentSearch @selected="selectStudent" />
        </div>
        <div class="col-xl-6 d-flex align-items-center">
          <div class="text-muted small">Puedes filtrar los llamados por estudiante desde el buscador global.</div>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-3">
      <div class="col-xl-8">
        <BCard class="mb-3">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Filtros</div>
              <InfirmaryHelpButton
                title="Ayuda: filtros de llamados"
                text="Utiliza estos filtros para revisar llamados por estudiante, estado, rango de fecha o texto libre."
              />
            </div>
          </template>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" placeholder="Apoderado, teléfono o motivo" @keyup.enter="loadCalls(1)" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Resultado</label>
              <BFormSelect v-model="filters.call_status" :options="callStatusOptions" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Desde</label>
              <BFormInput v-model="filters.from" type="date" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Hasta</label>
              <BFormInput v-model="filters.to" type="date" />
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <BButton variant="primary" class="w-100" @click="loadCalls(1)">Ir</BButton>
            </div>
            <div class="col-12 d-flex gap-2">
              <BButton variant="outline-secondary" @click="resetFilters">Limpiar</BButton>
            </div>
          </div>
        </BCard>

        <BCard>
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Llamados registrados</div>
              <InfirmaryHelpButton
                title="Ayuda: bandeja de llamados"
                text="Aquí se listan todos los llamados con fecha, estado y vínculo con la estudiante atendida."
              />
            </div>
          </template>

          <LoadingState v-if="loading" message="Cargando llamados..." compact />

          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Estudiante</th>
                  <th>Contacto</th>
                  <th>Resultado</th>
                  <th>Motivo</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="call in calls"
                  :key="call.id"
                  :class="{ 'table-active': selectedCall?.id === call.id }"
                  role="button"
                  @click="selectedCall = call"
                >
                  <td>{{ formatInfirmaryDateTime(call.called_at) }}</td>
                  <td>{{ call.student?.first_name }} {{ call.student?.last_name }}</td>
                  <td>
                    <div>{{ call.person_contacted }}</div>
                    <div class="small text-muted">{{ call.phone_number || "Sin teléfono" }}</div>
                  </td>
                  <td><InfirmaryStatusBadge :status="call.call_status" /></td>
                  <td>{{ call.reason || "-" }}</td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <BButton variant="outline-primary" @click.stop="openEdit(call)">Editar</BButton>
                      <BButton variant="outline-danger" @click.stop="remove(call)">Eliminar</BButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end mt-3">
            <BPagination
              v-model="pagination.current_page"
              :total-rows="pagination.total"
              :per-page="pagination.per_page"
              @update:model-value="loadCalls"
            />
          </div>
        </BCard>
      </div>

      <div class="col-xl-4">
        <BCard class="h-100">
          <template #header>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <div class="fw-semibold">Detalle del llamado</div>
              <InfirmaryHelpButton
                title="Ayuda: detalle del llamado"
                text="Aquí se revisa la conversación, compromisos y trazabilidad del llamado seleccionado."
              />
            </div>
          </template>

          <div v-if="!selectedCall" class="text-muted">Selecciona un llamado para ver su detalle.</div>
          <div v-else>
            <div class="fw-semibold">{{ selectedCall.person_contacted }}</div>
            <div class="text-muted small mb-3">{{ selectedCall.relationship || "Sin relación indicada" }}</div>
            <div class="mb-2"><span class="text-muted">Estudiante:</span> {{ selectedCall.student?.first_name }} {{ selectedCall.student?.last_name }}</div>
            <div class="mb-2"><span class="text-muted">Número:</span> {{ selectedCall.phone_number || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Resultado:</span> <InfirmaryStatusBadge :status="selectedCall.call_status" /></div>
            <div class="mb-2"><span class="text-muted">Fecha:</span> {{ formatInfirmaryDateTime(selectedCall.called_at) }}</div>
            <div class="mb-2"><span class="text-muted">Duración:</span> {{ selectedCall.duration_minutes || 0 }} min</div>
            <div class="mb-2"><span class="text-muted">Funcionario:</span> {{ selectedCall.called_by?.name || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Motivo:</span> {{ selectedCall.reason || "-" }}</div>
            <div class="mb-2"><span class="text-muted">Resumen:</span> {{ selectedCall.conversation_summary || "Sin resumen." }}</div>
            <div class="mb-2"><span class="text-muted">Compromisos:</span> {{ selectedCall.commitments || "Sin compromisos." }}</div>
            <div><span class="text-muted">Llegada estimada:</span> {{ formatInfirmaryDateTime(selectedCall.estimated_arrival_at) }}</div>
          </div>
        </BCard>
      </div>
    </div>

    <BModal v-model="showModal" :title="isEditing ? 'Editar llamado' : 'Nuevo llamado'" size="lg" hide-footer>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Buscar estudiante</label>
          <InfirmaryStudentSearch button-label="Seleccionar" @selected="selectFormStudent" />
          <div v-if="form.student_label" class="small text-muted mt-2">Seleccionada: {{ form.student_label }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Fecha y hora</label>
          <BFormInput v-model="form.called_at" type="datetime-local" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Funcionario que llamó</label>
          <BFormSelect v-model="form.called_by_user_id" :options="userOptions" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Persona contactada</label>
          <BFormInput v-model="form.person_contacted" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Relación</label>
          <BFormInput v-model="form.relationship" placeholder="Madre, padre, tutor..." />
        </div>
        <div class="col-md-6">
          <label class="form-label">Número telefónico</label>
          <BFormInput v-model="form.phone_number" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Resultado</label>
          <BFormSelect v-model="form.call_status" :options="normalizeOptions(catalogs.call_status_options)" />
        </div>
        <div class="col-md-8">
          <label class="form-label">Motivo</label>
          <BFormInput v-model="form.reason" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Duración aproximada</label>
          <BFormInput v-model="form.duration_minutes" type="number" min="1" max="240" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Atención asociada</label>
          <BFormInput v-model="form.attention_id" type="number" min="1" placeholder="Opcional" />
        </div>
        <div class="col-md-6">
          <label class="form-label">Hora estimada de llegada</label>
          <BFormInput v-model="form.estimated_arrival_at" type="datetime-local" />
        </div>
        <div class="col-12">
          <label class="form-label">Resumen de la conversación</label>
          <BFormTextarea v-model="form.conversation_summary" rows="3" />
        </div>
        <div class="col-12">
          <label class="form-label">Compromisos</label>
          <BFormTextarea v-model="form.commitments" rows="2" />
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <BButton variant="outline-secondary" @click="cancelModal">Cancelar</BButton>
          <BButton variant="primary" :disabled="saving" @click="save">
            {{ saving ? "Guardando..." : isEditing ? "Guardar cambios" : "Registrar llamado" }}
          </BButton>
        </div>
      </div>
    </BModal>
  </Layout>
</template>
