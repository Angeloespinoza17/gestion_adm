<script>
import SupportHelpButton from "./help-button.vue";
import SupportStatusBadge from "./status-badge.vue";
import { formatSupportDateTime } from "./module-utils";

export default {
  components: { SupportHelpButton, SupportStatusBadge },
  props: {
    student: {
      type: Object,
      default: null,
    },
    title: {
      type: String,
      default: "Ficha resumida del estudiante",
    },
  },
  methods: {
    formatSupportDateTime,
  },
};
</script>

<template>
  <BCard class="h-100">
    <template #header>
      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="fw-semibold">{{ title }}</div>
        <SupportHelpButton
          title="Ayuda: ficha resumida"
          text="Aquí se muestran los datos básicos del estudiante, el curso actual, alertas visibles y un resumen del historial de apoyo profesional."
        />
      </div>
    </template>

    <div v-if="!student" class="text-muted">
      Selecciona un estudiante para revisar su contexto de apoyo.
    </div>

    <div v-else>
      <div class="d-flex align-items-center gap-3 mb-3">
        <img
          :src="student.photo_url || '/build/images/users/user-dummy-img.jpg'"
          alt="Foto estudiante"
          class="rounded-circle object-fit-cover"
          style="width: 72px; height: 72px"
        />
        <div>
          <div class="fw-semibold fs-5">{{ student.full_name }}</div>
          <div class="text-muted small">{{ student.rut || "Sin RUT" }}</div>
          <div class="small text-muted">{{ student.course || "Sin curso" }} · {{ student.age ?? "-" }} años</div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="small text-muted">Profesor jefe</div>
          <div>{{ student.teacher_name || "-" }}</div>
        </div>
        <div class="col-md-6">
          <div class="small text-muted">Apoderado</div>
          <div>{{ student.guardian_name || "-" }}</div>
        </div>
      </div>

      <div class="mb-3">
        <div class="fw-semibold mb-2">Alertas visibles</div>
        <div v-if="!(student.alerts || []).length" class="text-muted small">Sin alertas visibles.</div>
        <div v-else class="d-flex flex-wrap gap-2">
          <SupportStatusBadge
            v-for="alert in student.alerts"
            :key="alert"
            status="reservada"
            :label="alert"
          />
        </div>
      </div>

      <div class="mb-3">
        <div class="fw-semibold mb-2">Profesionales vinculados</div>
        <div v-if="!(student.professionals || []).length" class="text-muted small">Sin intervenciones registradas.</div>
        <ul v-else class="mb-0 small">
          <li v-for="professional in student.professionals" :key="professional">{{ professional }}</li>
        </ul>
      </div>

      <div class="mb-3">
        <div class="fw-semibold mb-2">Resumen</div>
        <div class="row g-2">
          <div class="col-6">
            <div class="border rounded p-2">
              <div class="text-muted small">Atenciones</div>
              <div class="fw-semibold">{{ student.history_summary?.attentions_total || 0 }}</div>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded p-2">
              <div class="text-muted small">Casos activos</div>
              <div class="fw-semibold">{{ student.history_summary?.active_cases_total || 0 }}</div>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded p-2">
              <div class="text-muted small">Derivaciones</div>
              <div class="fw-semibold">{{ student.history_summary?.derivations_total || 0 }}</div>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded p-2">
              <div class="text-muted small">Planes activos</div>
              <div class="fw-semibold">{{ student.history_summary?.plans_active_total || 0 }}</div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <div class="fw-semibold mb-2">Últimas atenciones</div>
        <div v-if="!(student.recent_attentions || []).length" class="text-muted small">Sin atenciones recientes.</div>
        <div v-else class="d-grid gap-2">
          <div v-for="item in student.recent_attentions" :key="item.id" class="border rounded p-2">
            <div class="d-flex justify-content-between gap-2 align-items-center">
              <div class="fw-semibold">{{ item.professional_role_name || "Sin profesional" }}</div>
              <SupportStatusBadge :status="item.status" />
            </div>
            <div class="small">{{ item.reason_summary || "Sin motivo" }}</div>
            <div class="small text-muted">{{ formatSupportDateTime(item.attended_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </BCard>
</template>
