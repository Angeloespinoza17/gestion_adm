<script>
import PorterStatusBadge from "./status-badge.vue";

export default {
  components: { PorterStatusBadge },
  props: {
    student: {
      type: Object,
      default: null,
    },
  },
  emits: ["register-withdrawal"],
  methods: {
    alertVariant(priority) {
      if (priority === "high") return "danger";
      if (priority === "medium") return "warning";
      return "info";
    },
  },
};
</script>

<template>
  <BCard v-if="student" title="Ficha operativa">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <h5 class="mb-1">{{ student.full_name }}</h5>
        <div class="text-muted">{{ student.rut || "Sin RUT" }}</div>
      </div>
      <BButton variant="primary" size="sm" @click="$emit('register-withdrawal', student)">Registrar retiro</BButton>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-6">
        <div class="text-muted small">Curso actual</div>
        <div class="fw-semibold">{{ student.current_enrollment?.course_name || "-" }}</div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Año académico</div>
        <div class="fw-semibold">{{ student.current_enrollment?.academic_year_name || "-" }}</div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Estado matrícula</div>
        <div><PorterStatusBadge :value="student.current_enrollment?.enrollment_status" :label="student.current_enrollment?.enrollment_status || '-'" /></div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Apoderado principal</div>
        <div class="fw-semibold">{{ student.guardian_name || "-" }}</div>
        <div class="small text-muted">{{ student.guardian_phone || "Sin teléfono" }}</div>
      </div>
    </div>

    <div v-if="(student.alerts || []).length" class="mt-3">
      <h6 class="mb-2">Alertas</h6>
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

    <div class="mt-3">
      <h6 class="mb-2">Personas autorizadas</h6>
      <div v-if="!(student.authorized_pickup_people || []).length" class="text-muted">No hay personas autorizadas adicionales registradas.</div>
      <ul v-else class="list-unstyled mb-0">
        <li v-for="(person, index) in student.authorized_pickup_people" :key="index" class="mb-2">
          <div class="fw-semibold">{{ person.name }}</div>
          <div class="small text-muted">
            {{ person.relationship || "Sin relación" }}<span v-if="person.rut"> · {{ person.rut }}</span><span v-if="person.phone"> · {{ person.phone }}</span>
          </div>
        </li>
      </ul>
    </div>
  </BCard>
  <BCard v-else>
    <div class="text-muted">Selecciona una estudiante para ver la ficha operativa de portería.</div>
  </BCard>
</template>
