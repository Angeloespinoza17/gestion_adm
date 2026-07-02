<script>
import axios from "axios";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = (draft = {}) => ({
  maintenance_dependency_id: draft.maintenance_dependency_id ?? null,
  staff_id: draft.staff_id ?? null,
  department_id: draft.department_id ?? null,
  title: draft.title ?? "",
  activity: draft.activity ?? "",
  start_date: draft.start_date ?? "",
  start_time: draft.start_time ?? "08:00",
  end_date: draft.end_date ?? draft.start_date ?? "",
  end_time: draft.end_time ?? "09:00",
  repetition_type: draft.repetition_type ?? "none",
  repetition_until: draft.repetition_until ?? "",
  observations: draft.observations ?? "",
  estimated_attendees: draft.estimated_attendees ?? "",
  special_requirements: draft.special_requirements ?? "",
  collaborator_staff_ids: draft.collaborator_staff_ids ?? [],
  collaborator_external_emails: draft.collaborator_external_emails ?? [],
});

export default {
  components: { Multiselect },
  props: {
    modelValue: { type: Boolean, default: false },
    catalogs: { type: Object, default: () => ({}) },
    reservation: { type: Object, default: null },
    draftSelection: { type: Object, default: () => ({}) },
  },
  emits: ["update:modelValue", "saved"],
  data() {
    return {
      saving: false,
      error: null,
      externalCollaboratorInput: "",
      form: emptyForm(),
    };
  },
  computed: {
    showModal: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit("update:modelValue", value);
      },
    },
    isEditing() {
      return Boolean(this.reservation?.id);
    },
    permissions() {
      return JSON.parse(localStorage.getItem("permissions") || "[]");
    },
    canSubmit() {
      if (this.isEditing) {
        return this.permissions.includes("editar_reservas") || this.permissions.includes("administrar_calendario");
      }

      return this.permissions.includes("crear_reservas");
    },
    dependencyOptions() {
      return (this.catalogs.dependencies || []).map((item) => ({
        value: item.id,
        label: `${item.name}${item.type?.name ? ` · ${item.type.name}` : ""}`,
      }));
    },
    staffOptions() {
      return (this.catalogs.staff || []).map((item) => ({
        value: item.id,
        label: `${item.full_name}${item.rut ? ` (${item.rut})` : ""}`,
      }));
    },
    collaboratorStaffOptions() {
      return (this.catalogs.staff || []).map((item) => ({
        value: item.id,
        label: `${item.full_name}${item.rut ? ` (${item.rut})` : ""}`,
      }));
    },
    departmentOptions() {
      return [{ value: null, label: "Sin departamento" }].concat(
        (this.catalogs.departments || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    repetitionOptions() {
      return (this.catalogs.repetition_types || []).map((item) => ({
        value: item.value,
        label: item.label,
      }));
    },
    selectedDependency() {
      return (this.catalogs.dependencies || []).find(
        (item) => item.id === this.form.maintenance_dependency_id
      ) || null;
    },
    dependencyManagers() {
      return this.selectedDependency?.approvers || [];
    },
    collaboratorsSummary() {
      return (
        (this.form.collaborator_staff_ids?.length || 0) +
        (this.form.collaborator_external_emails?.length || 0)
      );
    },
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(value) {
        if (value) {
          this.resetForm();
        }
      },
    },
    reservation: {
      deep: true,
      handler() {
        if (this.modelValue) {
          this.resetForm();
        }
      },
    },
  },
  methods: {
    resetForm() {
      const source = this.reservation
        ? {
            maintenance_dependency_id: this.reservation.maintenance_dependency_id,
            staff_id: this.reservation.staff_id,
            department_id: this.reservation.department_id,
            title: this.reservation.title,
            activity: this.reservation.activity,
            start_date: this.reservation.start_date,
            start_time: this.reservation.start_time,
            end_date: this.reservation.end_date,
            end_time: this.reservation.end_time,
            repetition_type: this.reservation.repetition_type || "none",
            repetition_until: this.reservation.repetition_until || "",
            observations: this.reservation.observations,
            estimated_attendees: this.reservation.estimated_attendees,
            special_requirements: this.reservation.special_requirements,
            collaborator_staff_ids: (this.reservation.collaborators || [])
              .filter((item) => item.staff_id)
              .map((item) => item.staff_id),
            collaborator_external_emails: (this.reservation.collaborators || [])
              .filter((item) => item.external_email)
              .map((item) => item.external_email),
          }
        : this.draftSelection;

      this.form = emptyForm(source || {});
      this.externalCollaboratorInput = "";
      this.error = null;
    },
    addExternalCollaborator() {
      const email = this.externalCollaboratorInput.trim().toLowerCase();
      if (!email) {
        return;
      }

      const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (!isValid) {
        this.showErrorAlert("Ingresa un correo válido para el colaborador externo.");
        return;
      }

      if (!this.form.collaborator_external_emails.includes(email)) {
        this.form.collaborator_external_emails = [...this.form.collaborator_external_emails, email];
      }

      this.externalCollaboratorInput = "";
    },
    removeExternalCollaborator(email) {
      this.form.collaborator_external_emails = this.form.collaborator_external_emails.filter(
        (item) => item !== email
      );
    },
    async save() {
      if (!this.canSubmit) {
        return;
      }

      this.saving = true;
      this.error = null;

      try {
        const payload = {
          ...this.form,
          estimated_attendees:
            this.form.estimated_attendees === "" || this.form.estimated_attendees === null
              ? null
              : Number(this.form.estimated_attendees),
          department_id: this.form.department_id || null,
          repetition_until: this.form.repetition_type === "none" ? null : this.form.repetition_until || null,
          collaborator_staff_ids: this.form.collaborator_staff_ids || [],
          collaborator_external_emails: this.form.collaborator_external_emails || [],
        };

        const response = this.isEditing
          ? await axios.put(`/api/spaces/reservations/${this.reservation.id}`, payload)
          : await axios.post("/api/spaces/reservations", payload);

        this.showSuccessAlert(
          this.isEditing ? "Reserva actualizada" : "Reserva creada",
          response.data.message || "Operación realizada correctamente."
        );
        this.$emit("saved", response.data);
        this.showModal = false;
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.saving = false;
      }
    },
    showSuccessAlert(title, text) {
      return Swal.fire({
        title,
        text,
        icon: "success",
        timer: 1800,
        showConfirmButton: false,
      });
    },
    showErrorAlert(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
      });
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <BModal
    :model-value="showModal"
    :title="isEditing ? 'Editar reserva' : 'Nueva reserva'"
    size="xl"
    hide-footer
    @update:model-value="showModal = $event"
  >
    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="border rounded-3 bg-light-subtle p-3 mb-3">
      <div class="row g-3 align-items-center">
        <div class="col-lg-8">
          <div class="fw-semibold mb-1">{{ isEditing ? "Actualización de reserva" : "Planificación de reserva" }}</div>
          <div class="text-muted small">
            Completa los datos generales, agenda y participantes relevantes del evento.
          </div>
        </div>
        <div class="col-lg-4 text-lg-end">
          <BBadge variant="info" class="me-2">{{ collaboratorsSummary }} colaboradores</BBadge>
          <BBadge :variant="selectedDependency?.requires_approval ? 'warning' : 'success'">
            {{ selectedDependency?.requires_approval ? "Requiere aprobación" : "Aprobación automática" }}
          </BBadge>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-7">
        <div class="border rounded-3 p-3 h-100">
          <div class="fw-semibold mb-3">Datos del evento</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Dependencia</label>
              <Multiselect v-model="form.maintenance_dependency_id" :options="dependencyOptions" :searchable="true" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Funcionario solicitante</label>
              <Multiselect v-model="form.staff_id" :options="staffOptions" :searchable="true" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Departamento asociado</label>
              <Multiselect v-model="form.department_id" :options="departmentOptions" :searchable="true" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Título de la reserva</label>
              <BFormInput v-model="form.title" />
            </div>
            <div class="col-12">
              <label class="form-label">Motivo o actividad</label>
              <BFormTextarea v-model="form.activity" rows="3" />
            </div>
            <div v-if="selectedDependency?.requires_approval" class="col-12">
              <BAlert variant="warning" show class="mb-0">
                <div class="fw-semibold mb-1">Esta dependencia requiere aprobación previa.</div>
                <div v-if="dependencyManagers.length" class="small">
                  Gestores asignados:
                  {{ dependencyManagers.map((item) => item.staff?.full_name || item.name).join(", ") }}.
                </div>
                <div v-else class="small">
                  No hay gestores asignados; la aprobación quedará disponible para usuarios autorizados del módulo.
                </div>
              </BAlert>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="border rounded-3 p-3 h-100">
          <div class="fw-semibold mb-3">Agenda y aforo</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Fecha inicio</label>
              <BFormInput v-model="form.start_date" type="date" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Hora inicio</label>
              <BFormInput v-model="form.start_time" type="time" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Fecha término</label>
              <BFormInput v-model="form.end_date" type="date" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Hora término</label>
              <BFormInput v-model="form.end_time" type="time" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Repetición</label>
              <Multiselect v-model="form.repetition_type" :options="repetitionOptions" :searchable="false" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Hasta</label>
              <BFormInput v-model="form.repetition_until" type="date" :disabled="form.repetition_type === 'none'" />
            </div>
            <div class="col-12">
              <label class="form-label">Asistentes estimados</label>
              <BFormInput v-model="form.estimated_attendees" type="number" min="0" />
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="border rounded-3 p-3 h-100">
          <div class="fw-semibold mb-3">Colaboradores</div>
          <div class="mb-3">
            <label class="form-label">Funcionarios colaboradores</label>
            <Multiselect
              v-model="form.collaborator_staff_ids"
              :options="collaboratorStaffOptions"
              mode="multiple"
              :close-on-select="false"
              :searchable="true"
              placeholder="Selecciona funcionarios relevantes"
            />
          </div>
          <div>
            <label class="form-label">Correo de colaborador externo</label>
            <div class="d-flex gap-2">
              <BFormInput
                v-model="externalCollaboratorInput"
                type="email"
                placeholder="persona@externo.cl"
                @keyup.enter="addExternalCollaborator"
              />
              <BButton variant="outline-primary" @click="addExternalCollaborator">Agregar</BButton>
            </div>
            <div v-if="form.collaborator_external_emails.length" class="d-flex flex-wrap gap-2 mt-3">
              <span
                v-for="email in form.collaborator_external_emails"
                :key="email"
                class="badge rounded-pill bg-light text-dark border d-inline-flex align-items-center gap-2 px-3 py-2"
              >
                {{ email }}
                <button type="button" class="btn btn-sm p-0 border-0 bg-transparent" @click="removeExternalCollaborator(email)">
                  ×
                </button>
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="border rounded-3 p-3 h-100">
          <div class="fw-semibold mb-3">Detalles operativos</div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Requerimientos especiales</label>
              <BFormTextarea v-model="form.special_requirements" rows="3" />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="form.observations" rows="3" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
      <BButton variant="primary" :disabled="saving || !canSubmit" @click="save">
        {{ saving ? "Guardando..." : isEditing ? "Actualizar reserva" : "Guardar reserva" }}
      </BButton>
    </div>
  </BModal>
</template>
