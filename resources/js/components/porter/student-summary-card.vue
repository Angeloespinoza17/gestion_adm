<script>
import PorterStatusBadge from "./status-badge.vue";

export default {
  components: { PorterStatusBadge },
  props: {
    student: {
      type: Object,
      default: null,
    },
    framed: {
      type: Boolean,
      default: true,
    },
    showWithdrawalAction: {
      type: Boolean,
      default: true,
    },
  },
  emits: ["register-withdrawal"],
  computed: {
    wrapperComponent() {
      return this.framed ? "BCard" : "div";
    },
    wrapperAttrs() {
      return this.framed ? { title: "Ficha operativa" } : {};
    },
    authorizedPeople() {
      return this.student?.authorized_pickup_people || [];
    },
    withdrawals() {
      return this.student?.withdrawal_history || this.student?.recent_withdrawals || [];
    },
    hasBackupGuardian() {
      return Boolean(
        this.student?.guardian_backup_name ||
          this.student?.guardian_backup_rut ||
          this.student?.guardian_backup_phone ||
          this.student?.guardian_backup_email
      );
    },
    healthRows() {
      if (!this.student) return [];

      return [
        { label: "Previsión", value: this.student.health_insurance },
        {
          label: "Enfermedad crónica",
          value: this.student.has_chronic_illness ? this.student.chronic_illness_details || "Sí" : "No",
        },
        {
          label: "Alergias a medicamentos",
          value: this.student.has_medication_allergies ? this.student.medication_allergies_details || "Sí" : "No",
        },
        {
          label: "Restricciones físicas",
          value: this.student.has_physical_restrictions ? this.student.physical_restrictions_details || "Sí" : "No",
        },
      ];
    },
  },
  methods: {
    alertVariant(priority) {
      if (priority === "high") return "danger";
      if (priority === "medium") return "warning";
      return "info";
    },
    valueOrDash(value) {
      return value || "-";
    },
    formatDate(value) {
      if (!value) return "-";
      const [year, month, day] = String(value).split("-");
      return year && month && day ? `${day}/${month}/${year}` : value;
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).replace("T", " ");
      const [datePart, timePart] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");
      return year && month && day ? `${day}/${month}/${year} ${String(timePart || "").slice(0, 5)}` : value;
    },
    sourceLabel(source) {
      const labels = {
        apoderado_titular: "Titular",
        apoderado_suplente: "Suplente",
        lista_porteria: "Autorizado",
      };

      return labels[source] || "Autorizado";
    },
  },
};
</script>

<template>
  <component :is="wrapperComponent" v-if="student" v-bind="wrapperAttrs" class="porter-student-summary">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <h5 class="mb-1">{{ student.full_name }}</h5>
        <div class="text-muted">{{ student.rut || "Sin RUT" }}</div>
        <div v-if="student.registered_name && student.registered_name !== student.full_name" class="small text-muted">
          Nombre registrado: {{ student.registered_name }}
        </div>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
        <PorterStatusBadge :value="student.general_status" :label="student.general_status || '-'" />
        <BButton v-if="showWithdrawalAction" variant="primary" size="sm" @click="$emit('register-withdrawal', student)">Registrar retiro</BButton>
      </div>
    </div>

    <div v-if="(student.alerts || []).length" class="mt-3">
      <BAlert
        v-for="(alert, index) in student.alerts"
        :key="index"
        :variant="alertVariant(alert.priority)"
        show
        class="py-2 mb-2"
      >
        <div class="fw-semibold">{{ alert.label }}</div>
        <div class="small">{{ alert.detail }}</div>
      </BAlert>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-4">
        <div class="student-detail-box">
          <div class="text-muted small">Curso actual</div>
          <div class="fw-semibold">{{ student.current_enrollment?.course_name || "-" }}</div>
          <div class="small text-muted">{{ student.current_enrollment?.education_level_name || "-" }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="student-detail-box">
          <div class="text-muted small">Año académico</div>
          <div class="fw-semibold">{{ student.current_enrollment?.academic_year_name || "-" }}</div>
          <div class="small text-muted">Sección {{ student.current_enrollment?.section_name || "-" }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="student-detail-box">
          <div class="text-muted small">Estado matrícula</div>
          <div>
            <PorterStatusBadge
              :value="student.current_enrollment?.enrollment_status"
              :label="student.current_enrollment?.enrollment_status || '-'"
            />
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="student-detail-box">
          <div class="text-muted small">Fecha de nacimiento</div>
          <div class="fw-semibold">{{ formatDate(student.birthdate) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="student-detail-box">
          <div class="text-muted small">Teléfono estudiante</div>
          <div class="fw-semibold">{{ valueOrDash(student.phone) }}</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="student-detail-box">
          <div class="text-muted small">Correo estudiante</div>
          <div class="fw-semibold text-break">{{ valueOrDash(student.email) }}</div>
        </div>
      </div>
      <div class="col-12">
        <div class="student-detail-box">
          <div class="text-muted small">Dirección</div>
          <div class="fw-semibold">{{ valueOrDash(student.address) }}</div>
        </div>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-lg-6">
        <h6 class="mb-2">Apoderado titular</h6>
        <div class="student-detail-box">
          <div class="fw-semibold">{{ valueOrDash(student.guardian_name) }}</div>
          <div class="small text-muted">{{ valueOrDash(student.guardian_relationship) }}</div>
          <div class="mt-2 small">RUT: {{ valueOrDash(student.guardian_rut) }}</div>
          <div class="small">Teléfono: {{ valueOrDash(student.guardian_phone) }}</div>
          <div class="small text-break">Correo: {{ valueOrDash(student.guardian_email) }}</div>
          <div class="small">Dirección: {{ valueOrDash(student.guardian_address) }}</div>
        </div>
      </div>
      <div class="col-lg-6">
        <h6 class="mb-2">Apoderado suplente</h6>
        <div class="student-detail-box">
          <template v-if="hasBackupGuardian">
            <div class="fw-semibold">{{ valueOrDash(student.guardian_backup_name) }}</div>
            <div class="small text-muted">{{ valueOrDash(student.guardian_backup_relationship) }}</div>
            <div class="mt-2 small">RUT: {{ valueOrDash(student.guardian_backup_rut) }}</div>
            <div class="small">Teléfono: {{ valueOrDash(student.guardian_backup_phone) }}</div>
            <div class="small text-break">Correo: {{ valueOrDash(student.guardian_backup_email) }}</div>
            <div class="small">Dirección: {{ valueOrDash(student.guardian_backup_address) }}</div>
          </template>
          <div v-else class="text-muted">Sin apoderado suplente registrado.</div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0">Personas autorizadas</h6>
        <BBadge variant="secondary">{{ authorizedPeople.length }}</BBadge>
      </div>
      <div v-if="!authorizedPeople.length" class="text-muted">
        No hay personas autorizadas registradas.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0 student-detail-table">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Relación</th>
              <th>RUT</th>
              <th>Teléfono</th>
              <th>Origen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(person, index) in authorizedPeople" :key="`${person.name}-${index}`">
              <td class="fw-semibold">{{ person.name }}</td>
              <td>{{ valueOrDash(person.relationship) }}</td>
              <td>{{ valueOrDash(person.rut) }}</td>
              <td>{{ valueOrDash(person.phone) }}</td>
              <td><BBadge variant="light">{{ sourceLabel(person.source) }}</BBadge></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-lg-6">
        <h6 class="mb-2">Salud</h6>
        <div class="student-detail-box">
          <div v-for="row in healthRows" :key="row.label" class="mb-2">
            <div class="text-muted small">{{ row.label }}</div>
            <div class="fw-semibold">{{ valueOrDash(row.value) }}</div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <h6 class="mb-2">Observaciones</h6>
        <div class="student-detail-box">
          <div class="mb-2">
            <div class="text-muted small">Restricción de retiro</div>
            <div class="fw-semibold">{{ student.pickup_restriction ? "Sí" : "No" }}</div>
            <div v-if="student.pickup_restriction_notes" class="small">{{ student.pickup_restriction_notes }}</div>
          </div>
          <div class="mb-2">
            <div class="text-muted small">Nota de portería</div>
            <div>{{ valueOrDash(student.porter_alert_notes) }}</div>
          </div>
          <div>
            <div class="text-muted small">Observaciones generales</div>
            <div>{{ valueOrDash(student.observations) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <h6 class="mb-2">Historial reciente de retiros</h6>
      <div v-if="!withdrawals.length" class="text-muted">No hay retiros recientes registrados.</div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0 student-detail-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Persona</th>
              <th>Estado</th>
              <th>Motivo</th>
              <th>Registro</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="withdrawal in withdrawals" :key="withdrawal.id">
              <td>{{ formatDateTime(withdrawal.withdrawn_at) }}</td>
              <td class="fw-semibold">{{ valueOrDash(withdrawal.person_name) }}</td>
              <td><PorterStatusBadge :value="withdrawal.status" :label="withdrawal.status || '-'" /></td>
              <td>{{ valueOrDash(withdrawal.reason) }}</td>
              <td>
                <div class="small">{{ withdrawal.registered_by || "-" }}</div>
                <div v-if="withdrawal.authorized_by" class="small text-muted">Autorizó: {{ withdrawal.authorized_by }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </component>
  <component :is="wrapperComponent" v-else v-bind="wrapperAttrs" class="porter-student-summary">
    <div class="text-muted">Selecciona una estudiante para ver la ficha operativa de portería.</div>
  </component>
</template>

<style scoped>
.student-detail-box {
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  height: 100%;
  padding: 0.875rem;
}

.student-detail-table th {
  color: var(--bs-secondary-color);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  white-space: nowrap;
}
</style>
