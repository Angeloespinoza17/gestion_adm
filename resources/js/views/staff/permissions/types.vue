<script>
import axios from "axios";
import Layout from "../../../layouts/main.vue";
import LoadingState from "../../../components/ui/loading-state.vue";
import EmptyState from "../../../components/staff/permissions/empty-state.vue";
import MetricCard from "../../../components/staff/permissions/metric-card.vue";
import PageHeader from "../../../components/staff/permissions/page-header.vue";
import StatusBadge from "../../../components/staff/permissions/status-badge.vue";
import Swal from "sweetalert2";

const emptyForm = () => ({
  name: "",
  description: "",
  requires_attachment: false,
  allows_with_pay: true,
  allows_without_pay: true,
  allows_hourly: false,
  allows_half_day: false,
  requires_manager_approval: true,
  requires_direction_approval: false,
  requires_hr_approval: false,
  max_days: "",
  minimum_notice_days: "",
  allows_retroactive: false,
  affects_salary: false,
  affects_attendance: true,
  requires_replacement: false,
  active: true,
});

export default {
  components: { Layout, LoadingState, EmptyState, MetricCard, PageHeader, StatusBadge },
  data() {
    return {
      loading: false,
      saving: false,
      showTypeModal: false,
      error: null,
      items: [],
      form: emptyForm(),
      editingId: null,
    };
  },
  mounted() {
    this.load();
  },
  computed: {
    summaryCards() {
      return [
        {
          label: "Tipos",
          value: this.items.length,
          hint: "Configurados",
          icon: "bx-category",
          variant: "primary",
        },
        {
          label: "Activos",
          value: this.items.filter((item) => item.active).length,
          hint: "Disponibles para solicitar",
          icon: "bx-check-circle",
          variant: "success",
        },
        {
          label: "Con adjunto",
          value: this.items.filter((item) => item.requires_attachment).length,
          hint: "Exigen respaldo",
          icon: "bx-paperclip",
          variant: "info",
        },
        {
          label: "Con reemplazo",
          value: this.items.filter((item) => item.requires_replacement).length,
          hint: "Requieren cobertura",
          icon: "bx-transfer-alt",
          variant: "warning",
        },
      ];
    },
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/staff/permission-types");
        this.items = response.data.data || [];
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.reset();
      this.showTypeModal = true;
    },
    edit(item) {
      this.editingId = item.id;
      this.form = {
        ...emptyForm(),
        ...item,
        max_days: item.max_days ?? "",
        minimum_notice_days: item.minimum_notice_days ?? "",
      };
      this.showTypeModal = true;
    },
    reset() {
      this.editingId = null;
      this.form = emptyForm();
    },
    async save() {
      if (!String(this.form.name || "").trim()) {
        await Swal.fire({
          title: "Nombre requerido",
          text: "Ingresa un nombre para el tipo de permiso.",
          icon: "warning",
          confirmButtonText: "Entendido",
        });
        return;
      }

      const result = await Swal.fire({
        title: this.editingId ? "Actualizar tipo" : "Crear nuevo tipo",
        text: this.editingId ? "Se guardarán los cambios del tipo de permiso." : "El nuevo tipo quedará disponible según su configuración.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: this.editingId ? "Actualizar" : "Crear tipo",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
      });

      if (!result.isConfirmed) return;

      this.saving = true;
      this.error = null;
      try {
        if (this.editingId) {
          await axios.put(`/api/staff/permission-types/${this.editingId}`, this.form);
        } else {
          await axios.post("/api/staff/permission-types", this.form);
        }

        const wasEditing = Boolean(this.editingId);
        this.showTypeModal = false;
        this.reset();
        await this.load();
        await Swal.fire({
          title: wasEditing ? "Tipo actualizado" : "Tipo creado",
          text: "La configuración fue guardada correctamente.",
          icon: "success",
          timer: 1800,
          showConfirmButton: false,
        });
      } catch (error) {
        const message = this.formatError(error);
        this.error = message;
        await Swal.fire({
          title: "No se pudo guardar",
          text: message,
          icon: "error",
          confirmButtonText: "Entendido",
        });
      } finally {
        this.saving = false;
      }
    },
    async toggleActive(item) {
      const result = await Swal.fire({
        title: item.active ? "Desactivar tipo" : "Activar tipo",
        text: item.name,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: item.active ? "Desactivar" : "Activar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) return;

      await axios.put(`/api/staff/permission-types/${item.id}/active`, {
        active: !item.active,
      });
      await this.load();
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (errors ? errors[Object.keys(errors)[0]]?.[0] : null) || error?.response?.data?.message || error?.message || "Error desconocido";
    },
    ruleBadges(item) {
      return [
        { label: "Jefatura", active: item.requires_manager_approval },
        { label: "Dirección", active: item.requires_direction_approval },
        { label: "RRHH", active: item.requires_hr_approval },
        { label: "Horas", active: item.allows_hourly },
        { label: "Media jornada", active: item.allows_half_day },
        { label: "Adjunto", active: item.requires_attachment },
        { label: "Reemplazo", active: item.requires_replacement },
      ];
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader
      title="Tipos de permiso"
      subtitle="Configuración del flujo, restricciones y efectos administrativos."
      icon="bx-category"
    >
      <template #actions>
        <BButton variant="outline-primary" @click="load">
          <i class="bx bx-refresh me-1"></i>Actualizar
        </BButton>
      </template>
    </PageHeader>

    <BAlert v-if="error" variant="danger" show>{{ error }}</BAlert>

    <div class="row g-3 mb-3">
      <div v-for="card in summaryCards" :key="card.label" class="col-md-6 col-xl-3">
        <MetricCard
          :label="card.label"
          :value="card.value"
          :hint="card.hint"
          :icon="card.icon"
          :variant="card.variant"
        />
      </div>
    </div>

    <BCard class="permission-card">
      <template #header>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="permission-section-title mb-0">
            <i class="bx bx-list-ul"></i>
            <span>Listado</span>
          </div>
          <BButton size="sm" variant="primary" @click="openCreate">
            <i class="bx bx-plus me-1"></i>Nuevo tipo
          </BButton>
        </div>
      </template>
      <LoadingState v-if="loading" message="Cargando tipos..." compact />
      <EmptyState
        v-else-if="!items.length"
        icon="bx-category"
        title="Sin tipos configurados"
        text="Crea el primer tipo de permiso para habilitar solicitudes."
      />
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle permission-table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Reglas activas</th>
              <th>Activo</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <div class="fw-semibold">{{ item.name }}</div>
                <div class="text-muted small">{{ item.description || "Sin descripción" }}</div>
              </td>
              <td>
                <div class="permission-chip-list">
                  <span
                    v-for="rule in ruleBadges(item).filter((rule) => rule.active)"
                    :key="`${item.id}-${rule.label}`"
                    class="permission-chip"
                  >
                    <i class="bx bx-check"></i>{{ rule.label }}
                  </span>
                  <span v-if="!ruleBadges(item).some((rule) => rule.active)" class="text-muted small">Sin reglas activas</span>
                </div>
              </td>
              <td>
                <StatusBadge :status="item.active ? 'activo' : 'inactivo'" />
              </td>
              <td class="text-end">
                <div class="permission-row-actions">
                  <BButton
                    class="permission-action-button permission-action-button--edit"
                    variant="outline-light"
                    title="Editar tipo"
                    aria-label="Editar tipo"
                    @click="edit(item)"
                  >
                    <i class="bx bx-edit"></i>
                  </BButton>
                  <BButton
                    class="permission-action-button"
                    :class="item.active ? 'permission-action-button--cancel' : 'permission-action-button--view'"
                    variant="outline-light"
                    :title="item.active ? 'Desactivar tipo' : 'Activar tipo'"
                    :aria-label="item.active ? 'Desactivar tipo' : 'Activar tipo'"
                    @click="toggleActive(item)"
                  >
                    <i :class="item.active ? 'bx bx-pause-circle' : 'bx bx-check-circle'"></i>
                  </BButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCard>

    <BModal
      v-model="showTypeModal"
      :title="editingId ? 'Editar tipo de permiso' : 'Nuevo tipo de permiso'"
      size="lg"
      hide-footer
      centered
      scrollable
      modal-class="permission-detail-modal permission-type-modal"
      @hidden="reset"
    >
      <div class="permission-type-form">
        <section class="permission-type-panel">
          <div class="permission-section-title mb-3">
            <i class="bx bx-id-card"></i>
            <span>Datos del tipo</span>
          </div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nombre</label>
              <BFormInput v-model="form.name" placeholder="Ej: Permiso administrativo" />
            </div>
            <div class="col-12">
              <label class="form-label">Descripción</label>
              <BFormTextarea
                v-model="form.description"
                rows="3"
                placeholder="Describe cuándo aplica este permiso."
              />
            </div>
          </div>
        </section>

        <section
          class="permission-type-status-card"
          :class="{ 'permission-type-status-card--inactive': !form.active }"
        >
          <div class="permission-type-status-card__content">
            <div class="permission-type-status-card__title">
              <i :class="form.active ? 'bx bx-check-circle' : 'bx bx-pause-circle'"></i>
              <span>Estado del tipo</span>
            </div>
            <p>
              {{
                form.active
                  ? "Activo: los funcionarios podrán seleccionar este permiso al crear solicitudes."
                  : "Inactivo: se conserva la configuración, pero no estará disponible para nuevas solicitudes."
              }}
            </p>
          </div>
          <BFormCheckbox v-model="form.active" switch class="permission-type-status-switch">
            {{ form.active ? "Activo" : "Inactivo" }}
          </BFormCheckbox>
        </section>

        <section class="permission-type-panel">
          <div class="permission-section-title mb-3">
            <i class="bx bx-time-five"></i>
            <span>Límites de solicitud</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Máximo de duración</label>
              <div class="input-group permission-unit-input">
                <BFormInput v-model="form.max_days" type="number" min="0" step="0.5" placeholder="Sin límite" />
                <span class="input-group-text">días</span>
              </div>
              <div class="form-text">Cantidad máxima que puede durar una solicitud de este tipo.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Anticipación mínima</label>
              <div class="input-group permission-unit-input">
                <BFormInput v-model="form.minimum_notice_days" type="number" min="0" step="1" placeholder="0" />
                <span class="input-group-text">días antes</span>
              </div>
              <div class="form-text">Días previos requeridos entre la creación y la fecha de inicio. Usa 0 para permitir el mismo día.</div>
            </div>
          </div>
        </section>

        <section class="permission-type-panel">
          <div class="permission-section-title mb-3">
            <i class="bx bx-slider-alt"></i>
            <span>Reglas</span>
          </div>
          <div class="permission-rule-groups">
            <div class="permission-rule-group">
              <div class="permission-rule-group__title">Solicitud</div>
              <div class="permission-option-grid">
                <div class="permission-option-card"><BFormCheckbox v-model="form.requires_attachment">Requiere adjunto</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.allows_hourly">Permite horas</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.allows_half_day">Permite media jornada</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.allows_retroactive">Permite retroactivo</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.requires_replacement">Requiere reemplazo</BFormCheckbox></div>
              </div>
            </div>

            <div class="permission-rule-group">
              <div class="permission-rule-group__title">Remuneración y asistencia</div>
              <div class="permission-option-grid">
                <div class="permission-option-card"><BFormCheckbox v-model="form.allows_with_pay">Permite con goce</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.allows_without_pay">Permite sin goce</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.affects_salary">Afecta remuneración</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.affects_attendance">Afecta asistencia</BFormCheckbox></div>
              </div>
            </div>

            <div class="permission-rule-group">
              <div class="permission-rule-group__title">Flujo de aprobación</div>
              <div class="permission-option-grid">
                <div class="permission-option-card"><BFormCheckbox v-model="form.requires_manager_approval">Aprueba jefatura</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.requires_direction_approval">Aprueba Dirección</BFormCheckbox></div>
                <div class="permission-option-card"><BFormCheckbox v-model="form.requires_hr_approval">Aprueba RRHH</BFormCheckbox></div>
              </div>
            </div>
          </div>
        </section>
      </div>
      <div class="permission-type-modal__footer">
        <BButton variant="outline-secondary" :disabled="saving" @click="showTypeModal = false">
          Cerrar
        </BButton>
        <BButton variant="primary" :disabled="saving" @click="save">
          <i :class="saving ? 'bx bx-loader-alt bx-spin me-1' : 'bx bx-save me-1'"></i>
          {{ saving ? "Guardando..." : editingId ? "Actualizar" : "Crear tipo" }}
        </BButton>
      </div>
    </BModal>
  </Layout>
</template>
