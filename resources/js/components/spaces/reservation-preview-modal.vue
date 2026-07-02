<script>
import axios from "axios";
import Swal from "sweetalert2";

export default {
  props: {
    modelValue: { type: Boolean, default: false },
    reservation: { type: Object, default: null },
  },
  emits: ["update:modelValue", "saved", "edit", "reschedule"],
  data() {
    return {
      processing: false,
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
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    currentUserId() {
      try {
        return Number(JSON.parse(localStorage.getItem("user") || "{}").user_id || 0) || null;
      } catch (error) {
        return null;
      }
    },
    isAssignedManager() {
      return Boolean(
        this.currentUserId &&
          (this.reservation?.dependency?.approvers || []).some((item) => item.id === this.currentUserId)
      );
    },
    canEdit() {
      return this.permissions.includes("editar_reservas") || this.permissions.includes("administrar_calendario");
    },
    canReschedule() {
      return this.permissions.includes("crear_reservas") || this.permissions.includes("administrar_calendario");
    },
    canCancel() {
      return this.permissions.includes("cancelar_reservas");
    },
    canApprove() {
      return this.permissions.includes("administrar_calendario") || this.permissions.includes("aprobar_reservas") || this.isAssignedManager;
    },
    canReject() {
      return this.permissions.includes("administrar_calendario") || this.permissions.includes("rechazar_reservas") || this.isAssignedManager;
    },
    collaborators() {
      return this.reservation?.collaborators || [];
    },
    managers() {
      return this.reservation?.dependency?.approvers || [];
    },
    statusVariant() {
      const status = this.reservation?.status;
      if (status === "aprobada") return "success";
      if (status === "pendiente") return "warning";
      if (status === "rechazada") return "danger";
      if (status === "cancelada") return "secondary";
      return "info";
    },
    statusSoftClass() {
      const status = this.reservation?.status;
      if (status === "aprobada") return "badge-soft-success";
      if (status === "pendiente") return "badge-soft-warning";
      if (status === "rechazada") return "badge-soft-danger";
      if (status === "cancelada") return "badge-soft-secondary";
      return "badge-soft-info";
    },
    moderationLabel() {
      const status = this.reservation?.status;

      if (status === "aprobada") return "Aprobada por";
      if (status === "rechazada") return "Rechazada por";
      if (status === "cancelada") return "Cancelada por";

      return "Aprobación / rechazo";
    },
    moderationActorName() {
      const status = this.reservation?.status;

      if (status === "aprobada" || status === "rechazada") {
        return this.reservation?.approvedBy?.name || "-";
      }

      if (status === "cancelada") {
        return this.reservation?.cancelledBy?.name || "-";
      }

      return "Pendiente";
    },
    scheduleLabel() {
      if (!this.reservation) {
        return "-";
      }

      return `${this.formatDate(this.reservation.start_date)} ${this.reservation.start_time} - ${this.formatDate(this.reservation.end_date)} ${this.reservation.end_time}`;
    },
  },
  methods: {
    formatDate(value) {
      const raw = String(value || "").trim();
      const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);

      if (!match) {
        return raw || "-";
      }

      return `${match[3]}-${match[2]}-${match[1]}`;
    },
    async runAction(url, title, successTitle) {
      const result = await Swal.fire({
        title,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Confirmar",
        cancelButtonText: "Cancelar",
      });

      if (!result.isConfirmed) {
        return;
      }

      this.processing = true;

      try {
        const response = await axios.put(url);
        await Swal.fire({
          title: successTitle,
          text: response.data.message || "Operación realizada correctamente.",
          icon: "success",
          timer: 1800,
          showConfirmButton: false,
        });
        this.$emit("saved", response.data.data);
        this.showModal = false;
      } catch (error) {
        await Swal.fire({
          title: "Error",
          text:
            error?.response?.data?.message ||
            error?.response?.data?.errors?.[Object.keys(error?.response?.data?.errors || {})[0]]?.[0] ||
            error?.message ||
            "Error desconocido",
          icon: "error",
        });
      } finally {
        this.processing = false;
      }
    },
  },
};
</script>

<template>
  <BModal
    :model-value="showModal"
    title="Detalle de reserva"
    size="xl"
    hide-footer
    @update:model-value="showModal = $event"
  >
    <div v-if="reservation" class="reservation-preview">
      <div class="reservation-preview__header">
        <div class="row g-3 align-items-start">
          <div class="col-lg-8">
            <div class="text-muted small text-uppercase fw-semibold mb-2">Reserva</div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <h4 class="mb-0">{{ reservation.title }}</h4>
              <span :class="['badge', 'rounded-pill', statusSoftClass]">{{ reservation.status }}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge rounded-pill badge-soft-light text-body">{{ reservation.dependency?.name || "Sin dependencia" }}</span>
              <span class="badge rounded-pill badge-soft-light text-body">{{ reservation.department?.name || "Sin departamento" }}</span>
              <span class="badge rounded-pill badge-soft-light text-body">{{ reservation.staff?.full_name || "Sin solicitante" }}</span>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="reservation-preview__meta-box">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Horario</div>
              <div class="fw-semibold text-dark">{{ scheduleLabel }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card shadow-none border h-100 mb-0">
            <div class="card-body">
              <h5 class="card-title mb-3">Información general</h5>
              <div class="reservation-preview__field-list">
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Dependencia</span>
                  <span class="fw-medium text-dark">{{ reservation.dependency?.name || "-" }}</span>
                </div>
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Solicitado por</span>
                  <span class="fw-medium text-dark">{{ reservation.staff?.full_name || "-" }}</span>
                </div>
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Área o departamento</span>
                  <span class="fw-medium text-dark">{{ reservation.department?.name || "-" }}</span>
                </div>
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Asistentes esperados</span>
                  <span class="fw-medium text-dark">{{ reservation.estimated_attendees ?? "-" }}</span>
                </div>
              </div>

              <div class="reservation-preview__content-block">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Motivo de la reserva</div>
                <div class="text-dark fw-medium">{{ reservation.activity || "-" }}</div>
              </div>

              <div class="reservation-preview__content-block">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Observaciones</div>
                <div class="text-dark">{{ reservation.observations || "Sin observaciones registradas." }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-none border h-100 mb-0">
            <div class="card-body">
              <h5 class="card-title mb-3">Gestión y seguimiento</h5>
              <div class="reservation-preview__field-list">
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Registrada por</span>
                  <span class="fw-medium text-dark">{{ reservation.created_by?.name || reservation.createdBy?.name || "-" }}</span>
                </div>
                <div class="reservation-preview__field-row">
                  <span class="text-muted">{{ moderationLabel }}</span>
                  <span class="fw-medium text-dark">{{ moderationActorName }}</span>
                </div>
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Estado actual</span>
                  <span :class="['badge', 'rounded-pill', statusSoftClass]">{{ reservation.status }}</span>
                </div>
              </div>

              <div class="reservation-preview__content-block">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Gestores asignados</div>
                <div v-if="managers.length" class="d-flex flex-wrap gap-2">
                  <span
                    v-for="manager in managers"
                    :key="manager.id"
                    class="badge rounded-pill badge-soft-light text-body"
                  >
                    {{ manager.staff?.full_name || manager.name }}
                  </span>
                </div>
                <div v-else class="text-muted">Sin gestores asignados.</div>
              </div>

              <div class="reservation-preview__content-block mb-0">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Requerimientos especiales</div>
                <div class="text-dark">{{ reservation.special_requirements || "Sin requerimientos especiales." }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-none border h-100 mb-0">
            <div class="card-body">
              <h5 class="card-title mb-3">Colaboradores</h5>
              <div v-if="collaborators.length" class="reservation-preview__stack">
                <div
                  v-for="(collaborator, index) in collaborators"
                  :key="`${collaborator.staff_id || collaborator.external_email || index}`"
                  class="reservation-preview__list-item"
                >
                  <div class="fw-medium text-dark">
                    {{ collaborator.staff?.full_name || collaborator.external_email || "-" }}
                  </div>
                  <div class="text-muted small">
                    {{ collaborator.staff ? "Funcionario colaborador" : "Colaborador externo" }}
                  </div>
                </div>
              </div>
              <div v-else class="text-muted">No hay colaboradores registrados para esta reserva.</div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-none border h-100 mb-0">
            <div class="card-body">
              <h5 class="card-title mb-3">Resumen operativo</h5>
              <div class="reservation-preview__field-list">
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Fecha y hora de inicio</span>
                  <span class="fw-medium text-dark">{{ formatDate(reservation.start_date) }} {{ reservation.start_time }}</span>
                </div>
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Fecha y hora de término</span>
                  <span class="fw-medium text-dark">{{ formatDate(reservation.end_date) }} {{ reservation.end_time }}</span>
                </div>
                <div class="reservation-preview__field-row">
                  <span class="text-muted">Estado de la reserva</span>
                  <span :class="['badge', 'rounded-pill', statusSoftClass]">{{ reservation.status }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="reservation-preview__footer">
        <div class="d-flex flex-wrap justify-content-end gap-2">
          <BButton
            v-if="canEdit && reservation.status !== 'cancelada'"
            variant="outline-primary"
            @click="$emit('edit', reservation)"
          >
            Editar
          </BButton>
          <BButton
            v-if="canReschedule && reservation.status === 'cancelada'"
            variant="outline-secondary"
            @click="$emit('reschedule', reservation)"
          >
            Volver a agendar
          </BButton>
          <BButton
            v-if="canCancel && reservation.status !== 'cancelada' && reservation.status !== 'finalizada'"
            variant="warning"
            :disabled="processing"
            @click="runAction(`/api/spaces/reservations/${reservation.id}/cancel`, 'Cancelar reserva', 'Reserva cancelada')"
          >
            Cancelar
          </BButton>
          <BButton
            v-if="canApprove && reservation.status === 'pendiente'"
            variant="success"
            :disabled="processing"
            @click="runAction(`/api/spaces/reservations/${reservation.id}/approve`, 'Aprobar reserva', 'Reserva aprobada')"
          >
            Aprobar
          </BButton>
          <BButton
            v-if="canReject && reservation.status === 'pendiente'"
            variant="danger"
            :disabled="processing"
            @click="runAction(`/api/spaces/reservations/${reservation.id}/reject`, 'Rechazar reserva', 'Reserva rechazada')"
          >
            Rechazar
          </BButton>
        </div>
      </div>
    </div>
  </BModal>
</template>

<style scoped>
.reservation-preview__header {
  padding-bottom: 1.25rem;
  margin-bottom: 1.25rem;
  border-bottom: 1px solid #eff2f7;
}

.reservation-preview__meta-box {
  padding: 1rem 1.1rem;
  border: 1px solid #eff2f7;
  border-radius: 0.75rem;
  background: #fbfcff;
}

.reservation-preview__field-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.reservation-preview__field-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #f5f6f8;
}

.reservation-preview__field-row:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

.reservation-preview__content-block {
  margin-top: 1.25rem;
  padding-top: 1.1rem;
  border-top: 1px solid #f5f6f8;
}

.reservation-preview__stack {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.reservation-preview__list-item {
  padding: 0.9rem 1rem;
  border: 1px solid #eff2f7;
  border-radius: 0.75rem;
  background: #fff;
}

.reservation-preview__footer {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid #eff2f7;
}

@media (max-width: 991.98px) {
  .reservation-preview__field-row {
    flex-direction: column;
    gap: 0.25rem;
  }
}
</style>
