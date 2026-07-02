<script>
import axios from "axios";
import LoadingState from "../../ui/loading-state.vue";
import SupportHelpButton from "../help-button.vue";
import SupportStatusBadge from "../status-badge.vue";
import SupportDocumentPanel from "../document-panel.vue";
import {
  confirmSupportAction,
  confirmSupportCancel,
  formatSupportDateTime,
  formatSupportError,
  normalizeOptions,
  showSupportError,
  showSupportSuccess,
} from "../module-utils";

export default {
  components: {
    LoadingState,
    SupportHelpButton,
    SupportStatusBadge,
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
      selectedFollowUp: null,
      attentionOptions: [],
      showModal: false,
      form: this.emptyForm(),
      filters: {
        status: null,
        student_profile_id: null,
        from: "",
        to: "",
      },
    };
  },
  computed: {
    statusOptions() {
      return normalizeOptions(this.catalogs.follow_up_status_options, true);
    },
    editStatusOptions() {
      return normalizeOptions(this.catalogs.follow_up_status_options);
    },
    professionalOptions() {
      return [{ value: null, text: "Responsable automático" }].concat(
        (this.catalogs.professionals || []).map((item) => ({
          value: item.id,
          text: item.staff?.full_name || item.user?.name || item.professional_role_name,
        }))
      );
    },
    canUploadDocuments() {
      return Boolean(this.selectedFollowUp && this.catalogs.capabilities?.can_create_follow_up);
    },
  },
  async mounted() {
    await this.loadAttentionOptions();
    await this.load();
    const attentionId = this.$route.query.attention_id;
    if (attentionId) {
      this.openCreate(Number(attentionId));
    }
  },
  methods: {
    formatSupportDateTime,
    emptyForm() {
      return {
        id: null,
        attention_id: null,
        student_profile_id: null,
        responsible_professional_id: null,
        responsible_user_id: null,
        scheduled_at: new Date().toISOString().slice(0, 16),
        completed_at: "",
        comment: "",
        status: "pendiente",
        next_action: "",
        evidence_summary: "",
        result: "",
      };
    },
    async loadAttentionOptions() {
      const response = await axios.get("/api/apoyo-profesional/attentions", {
        params: { per_page: 100 },
      });
      this.attentionOptions = response.data.data || [];
    },
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/follow-ups", {
          params: this.filters,
        });
        this.items = response.data.data || [];

        if (this.selectedFollowUp?.id) {
          const exists = this.items.find((item) => item.id === this.selectedFollowUp.id);
          if (exists) {
            await this.openFollowUp(this.selectedFollowUp.id);
            return;
          }
        }

        if (this.items[0]) {
          await this.openFollowUp(this.items[0].id);
        } else {
          this.selectedFollowUp = null;
        }
      } catch (error) {
        this.error = formatSupportError(error, "No se pudieron cargar los seguimientos.");
      } finally {
        this.loading = false;
      }
    },
    async openFollowUp(id) {
      try {
        const response = await axios.get(`/api/apoyo-profesional/follow-ups/${id}`);
        this.selectedFollowUp = response.data.data;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo cargar el seguimiento.");
      }
    },
    openCreate(attentionId = null) {
      this.form = this.emptyForm();
      if (attentionId) {
        const attention = this.attentionOptions.find((item) => item.id === attentionId);
        this.form.attention_id = attentionId;
        this.form.student_profile_id = attention?.student_profile_id || null;
      }
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        attention_id: item.attention_id,
        student_profile_id: item.student_profile_id,
        responsible_professional_id: item.responsible_professional_id,
        responsible_user_id: item.responsible_user_id,
        scheduled_at: String(item.scheduled_at).replace(" ", "T").slice(0, 16),
        completed_at: item.completed_at ? String(item.completed_at).replace(" ", "T").slice(0, 16) : "",
        comment: item.comment,
        status: item.status,
        next_action: item.next_action || "",
        evidence_summary: item.evidence_summary || "",
        result: item.result || "",
      };
      this.showModal = true;
    },
    syncAttention() {
      const attention = this.attentionOptions.find((item) => item.id === this.form.attention_id);
      this.form.student_profile_id = attention?.student_profile_id || null;
    },
    syncResponsible() {
      const professional = (this.catalogs.professionals || []).find((item) => item.id === this.form.responsible_professional_id);
      this.form.responsible_user_id = professional?.user_id || null;
    },
    async save() {
      const confirmation = await confirmSupportAction({
        title: this.form.id ? "Editar seguimiento" : "Crear seguimiento",
        text: this.form.id ? "Se actualizará el seguimiento seleccionado." : "Se registrará un nuevo seguimiento.",
        confirmButtonText: this.form.id ? "Guardar cambios" : "Crear seguimiento",
      });
      if (!confirmation.isConfirmed) return;

      this.saving = true;
      try {
        this.syncAttention();
        this.syncResponsible();
        if (this.form.id) {
          await axios.put(`/api/apoyo-profesional/follow-ups/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/apoyo-profesional/follow-ups", this.form);
        }
        this.showModal = false;
        await this.load();
        if (this.form.id) {
          await this.openFollowUp(this.form.id);
        }
        await showSupportSuccess(this.form.id ? "Seguimiento actualizado correctamente." : "Seguimiento registrado correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo guardar el seguimiento."));
      } finally {
        this.saving = false;
      }
    },
    async remove(item) {
      const confirmation = await confirmSupportAction({
        title: "Eliminar seguimiento",
        text: "El seguimiento se eliminará definitivamente.",
        confirmButtonText: "Eliminar",
      });
      if (!confirmation.isConfirmed) return;

      try {
        await axios.delete(`/api/apoyo-profesional/follow-ups/${item.id}`);
        if (this.selectedFollowUp?.id === item.id) {
          this.selectedFollowUp = null;
        }
        await this.load();
        await showSupportSuccess("Seguimiento eliminado correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo eliminar el seguimiento."));
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
      <BButton variant="primary" @click="openCreate()">Nuevo seguimiento</BButton>
      <SupportHelpButton
        title="Ayuda: seguimientos"
        text="En esta sección se programan, actualizan y cierran los seguimientos posteriores a una atención profesional."
      />
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="statusOptions" /></div>
        <div class="col-md-4"><label class="form-label">Desde</label><BFormInput v-model="filters.from" type="date" /></div>
        <div class="col-md-4"><label class="form-label">Hasta</label><BFormInput v-model="filters.to" type="date" /></div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="primary" @click="load">Aplicar filtros</BButton>
          <BButton variant="outline-secondary" @click="filters = { status: null, student_profile_id: null, from: '', to: '' }; load();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading"><LoadingState message="Cargando seguimientos..." compact /></BCard>

    <div v-else class="row g-3">
      <div class="col-xl-4">
        <BCard class="h-100">
          <template #header><div class="fw-semibold">Seguimientos registrados</div></template>
          <div v-if="!items.length" class="text-muted">No hay seguimientos para los filtros aplicados.</div>
          <div v-else class="d-grid gap-2">
            <button
              v-for="item in items"
              :key="item.id"
              type="button"
              class="btn btn-light text-start border"
              @click="openFollowUp(item.id)"
            >
              <div class="d-flex justify-content-between gap-2 align-items-center">
                <div>
                  <div class="fw-semibold">{{ item.student?.full_name || "Estudiante" }}</div>
                  <div class="small">{{ item.comment }}</div>
                  <div class="small text-muted">{{ formatSupportDateTime(item.scheduled_at) }}</div>
                </div>
                <SupportStatusBadge :status="item.status" />
              </div>
            </button>
          </div>
        </BCard>
      </div>

      <div class="col-xl-8">
        <div v-if="!selectedFollowUp" class="text-muted small">
          Selecciona un seguimiento para revisar detalle, resultado y adjuntos.
        </div>

        <div v-else class="d-grid gap-3">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Detalle del seguimiento</div>
                <div class="d-flex gap-2 flex-wrap">
                  <BButton size="sm" variant="outline-secondary" @click="openEdit(selectedFollowUp)">Editar</BButton>
                  <BButton size="sm" variant="outline-danger" @click="remove(selectedFollowUp)">Eliminar</BButton>
                </div>
              </div>
            </template>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="small text-muted">Estudiante</div>
                <div>{{ selectedFollowUp.student?.full_name || "-" }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Atención asociada</div>
                <div>{{ selectedFollowUp.attention?.reason_summary || "-" }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Responsable</div>
                <div>
                  {{
                    selectedFollowUp.responsibleProfessional?.staff?.full_name
                      || selectedFollowUp.responsibleUser?.name
                      || "Sin responsable"
                  }}
                </div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Estado</div>
                <div><SupportStatusBadge :status="selectedFollowUp.status" /></div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Fecha programada</div>
                <div>{{ formatSupportDateTime(selectedFollowUp.scheduled_at) }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Fecha realizada</div>
                <div>{{ formatSupportDateTime(selectedFollowUp.completed_at) }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Próxima acción</div>
                <div>{{ selectedFollowUp.next_action || "Sin próxima acción." }}</div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Comentario</div>
                <div>{{ selectedFollowUp.comment }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Evidencia</div>
                <div>{{ selectedFollowUp.evidence_summary || "Sin evidencia registrada." }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Resultado</div>
                <div>{{ selectedFollowUp.result || "Sin resultado registrado." }}</div>
              </div>
            </div>
          </BCard>

          <SupportDocumentPanel
            :documents="selectedFollowUp.documents || []"
            :categories="catalogs.document_categories"
            :student-id="selectedFollowUp.student_profile_id"
            :upload-url="`/api/apoyo-profesional/follow-ups/${selectedFollowUp.id}/documents`"
            :can-upload="canUploadDocuments"
            @refresh="openFollowUp(selectedFollowUp.id)"
          />
        </div>
      </div>
    </div>

    <BModal v-model="showModal" :title="form.id ? 'Editar seguimiento' : 'Nuevo seguimiento'" hide-footer size="lg">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Atención asociada</label>
          <BFormSelect
            v-model="form.attention_id"
            :options="[{ value: null, text: 'Selecciona una atención' }].concat(attentionOptions.map((item) => ({ value: item.id, text: `${item.student_full_name_snapshot} · ${item.reason_summary}` })))"
            @change="syncAttention"
          />
        </div>
        <div class="col-md-6"><label class="form-label">Profesional responsable</label><BFormSelect v-model="form.responsible_professional_id" :options="professionalOptions" @change="syncResponsible" /></div>
        <div class="col-md-6"><label class="form-label">Estado</label><BFormSelect v-model="form.status" :options="editStatusOptions" /></div>
        <div class="col-md-6"><label class="form-label">Fecha programada</label><BFormInput v-model="form.scheduled_at" type="datetime-local" /></div>
        <div class="col-md-6"><label class="form-label">Fecha realizada</label><BFormInput v-model="form.completed_at" type="datetime-local" /></div>
        <div class="col-12"><label class="form-label">Comentario</label><BFormTextarea v-model="form.comment" rows="3" /></div>
        <div class="col-md-6"><label class="form-label">Próxima acción</label><BFormTextarea v-model="form.next_action" rows="2" /></div>
        <div class="col-md-6"><label class="form-label">Evidencia</label><BFormTextarea v-model="form.evidence_summary" rows="2" /></div>
        <div class="col-12"><label class="form-label">Resultado</label><BFormTextarea v-model="form.result" rows="2" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="outline-secondary" @click="cancelForm">Cancelar registro</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar seguimiento" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
