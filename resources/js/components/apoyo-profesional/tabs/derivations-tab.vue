<script>
import axios from "axios";
import Swal from "sweetalert2";
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
      selectedDerivation: null,
      attentionOptions: [],
      showModal: false,
      form: this.emptyForm(),
      filters: {
        status: null,
        student_profile_id: null,
        destination_area_name: null,
        from: "",
        to: "",
      },
    };
  },
  computed: {
    statusOptions() {
      return normalizeOptions(this.catalogs.derivation_status_options, true);
    },
    urgencyOptions() {
      return normalizeOptions(this.catalogs.derivation_urgency_options);
    },
    confidentialityOptions() {
      return normalizeOptions(this.catalogs.confidentiality_options);
    },
    areaOptions() {
      return normalizeOptions(this.catalogs.area_options);
    },
    professionalOptions() {
      return [{ value: null, text: "Sin profesional específico" }].concat(
        (this.catalogs.professionals || []).map((item) => ({
          value: item.id,
          text: `${item.professional_role_name} · ${item.staff?.full_name || item.user?.name || item.area_name}`,
        }))
      );
    },
    canUploadDocuments() {
      return Boolean(
        this.selectedDerivation
          && (this.catalogs.capabilities?.can_create_derivation
            || this.catalogs.capabilities?.can_respond_derivation)
      );
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
        destination_professional_id: null,
        destination_user_id: null,
        destination_area_slug: "psicologia",
        destination_area_name: "Psicología",
        urgency_level: "media",
        confidentiality_level: "reservada",
        status: "enviada",
        reason: "",
        description: "",
        derived_at: new Date().toISOString().slice(0, 16),
      };
    },
    async loadAttentionOptions() {
      const response = await axios.get("/api/apoyo-profesional/attentions", {
        params: { per_page: 100 },
      });
      this.attentionOptions = (response.data.data || []).map((item) => ({
        value: item.id,
        text: `${item.student_full_name_snapshot} · ${item.reason_summary}`,
      }));
    },
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/apoyo-profesional/derivations", {
          params: this.filters,
        });
        this.items = response.data.data || [];

        if (this.selectedDerivation?.id) {
          const exists = this.items.find((item) => item.id === this.selectedDerivation.id);
          if (exists) {
            await this.openDerivation(this.selectedDerivation.id);
            return;
          }
        }

        if (this.items[0]) {
          await this.openDerivation(this.items[0].id);
        } else {
          this.selectedDerivation = null;
        }
      } catch (error) {
        this.error = formatSupportError(error, "No se pudieron cargar las derivaciones.");
      } finally {
        this.loading = false;
      }
    },
    async openDerivation(id) {
      try {
        const response = await axios.get(`/api/apoyo-profesional/derivations/${id}`);
        this.selectedDerivation = response.data.data;
      } catch (error) {
        this.error = formatSupportError(error, "No se pudo cargar la derivación.");
      }
    },
    openCreate(attentionId = null) {
      this.form = this.emptyForm();
      if (attentionId) {
        this.form.attention_id = attentionId;
      }
      this.showModal = true;
    },
    openEdit(item) {
      this.form = {
        id: item.id,
        attention_id: item.attention_id,
        destination_professional_id: item.destination_professional_id,
        destination_user_id: item.destination_user_id,
        destination_area_slug: item.destination_area_slug,
        destination_area_name: item.destination_area_name,
        urgency_level: item.urgency_level,
        confidentiality_level: item.confidentiality_level,
        status: item.status,
        reason: item.reason,
        description: item.description || "",
        derived_at: String(item.derived_at).replace(" ", "T").slice(0, 16),
      };
      this.showModal = true;
    },
    syncDestinationArea() {
      const found = (this.catalogs.area_options || []).find((item) => item.value === this.form.destination_area_slug);
      this.form.destination_area_name = found?.label || this.form.destination_area_name;
      const professional = (this.catalogs.professionals || []).find((item) => item.id === this.form.destination_professional_id);
      this.form.destination_user_id = professional?.user_id || null;
    },
    async save() {
      const confirmation = await confirmSupportAction({
        title: this.form.id ? "Editar derivación" : "Derivar estudiante",
        text: this.form.id ? "Se actualizará la derivación seleccionada." : "Se enviará la derivación al área de destino.",
        confirmButtonText: this.form.id ? "Guardar cambios" : "Derivar",
      });
      if (!confirmation.isConfirmed) return;

      this.saving = true;
      try {
        this.syncDestinationArea();
        if (this.form.id) {
          await axios.put(`/api/apoyo-profesional/derivations/${this.form.id}`, this.form);
        } else {
          await axios.post("/api/apoyo-profesional/derivations", this.form);
        }
        this.showModal = false;
        await this.load();
        if (this.form.id) {
          await this.openDerivation(this.form.id);
        }
        await showSupportSuccess(this.form.id ? "Derivación actualizada correctamente." : "Derivación registrada correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo guardar la derivación."));
      } finally {
        this.saving = false;
      }
    },
    async respond(item, status) {
      const result = await Swal.fire({
        title: "Responder derivación",
        input: "textarea",
        inputLabel: "Respuesta del profesional destino",
        inputPlaceholder: "Escribe una respuesta breve",
        showCancelButton: true,
        confirmButtonText: "Guardar respuesta",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      try {
        await axios.post(`/api/apoyo-profesional/derivations/${item.id}/respond`, {
          status,
          destination_response: result.value || "",
        });
        await this.load();
        await this.openDerivation(item.id);
        await showSupportSuccess("Respuesta registrada correctamente.");
      } catch (error) {
        await showSupportError(formatSupportError(error, "No se pudo responder la derivación."));
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
      <BButton variant="primary" @click="openCreate()">Nueva derivación</BButton>
      <SupportHelpButton
        title="Ayuda: derivaciones internas"
        text="En esta sección puedes derivar estudiantes entre áreas, registrar respuestas del profesional destino y cerrar la trazabilidad de la derivación."
      />
    </div>

    <BCard class="mb-3">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="filters.status" :options="statusOptions" /></div>
        <div class="col-md-3"><label class="form-label">Área destino</label><BFormInput v-model="filters.destination_area_name" placeholder="Psicología, PIE..." /></div>
        <div class="col-md-3"><label class="form-label">Desde</label><BFormInput v-model="filters.from" type="date" /></div>
        <div class="col-md-3"><label class="form-label">Hasta</label><BFormInput v-model="filters.to" type="date" /></div>
        <div class="col-12 d-flex gap-2">
          <BButton variant="primary" @click="load">Aplicar filtros</BButton>
          <BButton variant="outline-secondary" @click="filters = { status: null, student_profile_id: null, destination_area_name: null, from: '', to: '' }; load();">Limpiar</BButton>
        </div>
      </div>
    </BCard>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>
    <BCard v-if="loading"><LoadingState message="Cargando derivaciones..." compact /></BCard>

    <div v-else class="row g-3">
      <div class="col-xl-4">
        <BCard class="h-100">
          <template #header><div class="fw-semibold">Derivaciones registradas</div></template>
          <div v-if="!items.length" class="text-muted">No hay derivaciones para los filtros aplicados.</div>
          <div v-else class="d-grid gap-2">
            <button
              v-for="item in items"
              :key="item.id"
              type="button"
              class="btn btn-light text-start border"
              @click="openDerivation(item.id)"
            >
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold">{{ item.student?.full_name || "Estudiante" }}</div>
                  <div class="small">{{ item.destination_area_name }}</div>
                  <div class="small text-muted">{{ formatSupportDateTime(item.derived_at) }}</div>
                </div>
                <SupportStatusBadge :status="item.status" />
              </div>
            </button>
          </div>
        </BCard>
      </div>

      <div class="col-xl-8">
        <div v-if="!selectedDerivation" class="text-muted small">
          Selecciona una derivación para revisar su detalle y los adjuntos vinculados.
        </div>

        <div v-else class="d-grid gap-3">
          <BCard class="border-0 shadow-sm">
            <template #header>
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-semibold">Detalle de la derivación</div>
                <div class="d-flex gap-2 flex-wrap">
                  <BButton size="sm" variant="outline-secondary" @click="openEdit(selectedDerivation)">Editar</BButton>
                  <BButton size="sm" variant="outline-success" @click="respond(selectedDerivation, 'aceptada')">Aceptar</BButton>
                  <BButton size="sm" variant="outline-danger" @click="respond(selectedDerivation, 'rechazada')">Rechazar</BButton>
                  <BButton size="sm" variant="outline-primary" @click="respond(selectedDerivation, 'cerrada')">Cerrar</BButton>
                </div>
              </div>
            </template>

            <div class="row g-3">
              <div class="col-md-6">
                <div class="small text-muted">Estudiante</div>
                <div>{{ selectedDerivation.student?.full_name || "-" }}</div>
              </div>
              <div class="col-md-6">
                <div class="small text-muted">Atención origen</div>
                <div>{{ selectedDerivation.attention?.reason_summary || "-" }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Área origen</div>
                <div>{{ selectedDerivation.origin_area_name || "-" }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Área destino</div>
                <div>{{ selectedDerivation.destination_area_name || "-" }}</div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Profesional destino</div>
                <div>
                  {{
                    selectedDerivation.destinationProfessional?.staff?.full_name
                      || selectedDerivation.destinationUser?.name
                      || "Sin asignar"
                  }}
                </div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Urgencia</div>
                <div><SupportStatusBadge :status="selectedDerivation.urgency_level" /></div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Estado</div>
                <div><SupportStatusBadge :status="selectedDerivation.status" /></div>
              </div>
              <div class="col-md-4">
                <div class="small text-muted">Fecha derivación</div>
                <div>{{ formatSupportDateTime(selectedDerivation.derived_at) }}</div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Motivo de derivación</div>
                <div>{{ selectedDerivation.reason }}</div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Descripción</div>
                <div>{{ selectedDerivation.description || "Sin descripción adicional." }}</div>
              </div>
              <div class="col-12">
                <div class="small text-muted">Respuesta del profesional destino</div>
                <div>{{ selectedDerivation.destination_response || "Sin respuesta registrada." }}</div>
              </div>
            </div>
          </BCard>

          <SupportDocumentPanel
            :documents="selectedDerivation.documents || []"
            :categories="catalogs.document_categories"
            :student-id="selectedDerivation.student_profile_id"
            :upload-url="`/api/apoyo-profesional/derivations/${selectedDerivation.id}/documents`"
            :can-upload="canUploadDocuments"
            @refresh="openDerivation(selectedDerivation.id)"
          />
        </div>
      </div>
    </div>

    <BModal v-model="showModal" :title="form.id ? 'Editar derivación' : 'Nueva derivación'" hide-footer size="lg">
      <div class="row g-3">
        <div class="col-12"><label class="form-label">Atención origen</label><BFormSelect v-model="form.attention_id" :options="[{ value: null, text: 'Selecciona una atención' }].concat(attentionOptions)" /></div>
        <div class="col-md-6"><label class="form-label">Área destino</label><BFormSelect v-model="form.destination_area_slug" :options="areaOptions" @change="syncDestinationArea" /></div>
        <div class="col-md-6"><label class="form-label">Profesional destino</label><BFormSelect v-model="form.destination_professional_id" :options="professionalOptions" @change="syncDestinationArea" /></div>
        <div class="col-md-4"><label class="form-label">Fecha derivación</label><BFormInput v-model="form.derived_at" type="datetime-local" /></div>
        <div class="col-md-4"><label class="form-label">Urgencia</label><BFormSelect v-model="form.urgency_level" :options="urgencyOptions" /></div>
        <div class="col-md-4"><label class="form-label">Confidencialidad</label><BFormSelect v-model="form.confidentiality_level" :options="confidentialityOptions" /></div>
        <div class="col-12"><label class="form-label">Motivo de derivación</label><BFormTextarea v-model="form.reason" rows="2" /></div>
        <div class="col-12"><label class="form-label">Descripción</label><BFormTextarea v-model="form.description" rows="3" /></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BButton variant="outline-secondary" @click="cancelForm">Cancelar registro</BButton>
        <BButton variant="primary" :disabled="saving" @click="save">{{ saving ? "Guardando..." : "Guardar derivación" }}</BButton>
      </div>
    </BModal>
  </div>
</template>
