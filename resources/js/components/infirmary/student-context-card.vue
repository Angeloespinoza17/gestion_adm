<script>
import InfirmaryHelpButton from "./help-button.vue";
import InfirmaryStatusBadge from "./status-badge.vue";
import { formatInfirmaryDateTime } from "./module-utils";

export default {
  components: { InfirmaryHelpButton, InfirmaryStatusBadge },
  props: {
    student: {
      type: Object,
      default: null,
    },
    title: {
      type: String,
      default: "Contexto clínico",
    },
    helpText: {
      type: String,
      default: "Aquí se muestra el resumen clínico del estudiante, incluyendo contactos, alertas de salud, medicamentos permanentes e historial reciente.",
    },
    showHistoryAction: {
      type: Boolean,
      default: false,
    },
  },
  emits: ["open-history"],
  methods: {
    formatInfirmaryDateTime,
  },
};
</script>

<template>
  <BCard class="h-100">
    <template #header>
      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div class="fw-semibold">{{ title }}</div>
        <InfirmaryHelpButton title="Ayuda: contexto del estudiante" :text="helpText" />
      </div>
    </template>

    <div v-if="!student" class="text-muted">
      Selecciona una estudiante para visualizar su ficha clínica resumida.
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
          <div class="small text-muted">Previsión de salud</div>
          <div>{{ student.health_insurance || "-" }}</div>
        </div>
      </div>

      <div class="mb-3">
        <div class="fw-semibold mb-2">Alertas médicas</div>
        <div class="small mb-1"><span class="text-muted">Alergias:</span> {{ student.allergies || "Sin registro" }}</div>
        <div class="small mb-1"><span class="text-muted">Enfermedades crónicas:</span> {{ student.chronic_illness || "Sin registro" }}</div>
        <div class="small"><span class="text-muted">Restricciones:</span> {{ student.physical_restrictions || "Sin registro" }}</div>
      </div>

      <div class="mb-3">
        <div class="fw-semibold mb-2">Contactos de emergencia</div>
        <div v-if="!(student.emergency_contacts || []).length" class="text-muted small">Sin contactos registrados.</div>
        <div v-else class="d-grid gap-2">
          <div v-for="(contact, index) in student.emergency_contacts" :key="index" class="border rounded p-2">
            <div class="fw-semibold">{{ contact.name }}</div>
            <div class="small text-muted">{{ contact.relationship || "-" }}</div>
            <div class="small">{{ contact.phone || "Sin teléfono" }}</div>
            <div class="small text-muted">{{ contact.email || "Sin correo" }}</div>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <div class="fw-semibold mb-2">Medicamentos permanentes</div>
        <div v-if="!(student.permanent_medications || []).length" class="text-muted small">Sin medicamentos permanentes vigentes.</div>
        <div v-else class="d-grid gap-2">
          <div v-for="medication in student.permanent_medications" :key="medication.id" class="border rounded p-2">
            <div class="d-flex justify-content-between gap-2 align-items-center">
              <div class="fw-semibold">{{ medication.medication_name }}</div>
              <InfirmaryStatusBadge :status="medication.status" />
            </div>
            <div class="small text-muted">{{ medication.dose }} · {{ medication.frequency || "Sin frecuencia" }}</div>
            <div class="small text-muted">{{ medication.schedule_text || "Sin horario" }}</div>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <div class="fw-semibold mb-2">Historial resumido</div>
        <div class="row g-2">
          <div class="col-6">
            <div class="border rounded p-2">
              <div class="text-muted small">Atenciones</div>
              <div class="fw-semibold">{{ student.history_summary?.attentions_total || 0 }}</div>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded p-2">
              <div class="text-muted small">Accidentes</div>
              <div class="fw-semibold">{{ student.history_summary?.accidents_total || 0 }}</div>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded p-2">
              <div class="text-muted small">Administraciones</div>
              <div class="fw-semibold">{{ student.history_summary?.administrations_total || 0 }}</div>
            </div>
          </div>
          <div class="col-6">
            <div class="border rounded p-2">
              <div class="text-muted small">Última atención</div>
              <div class="fw-semibold small">
                {{ formatInfirmaryDateTime(student.history_summary?.last_attention_at) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <div class="fw-semibold">Últimas atenciones</div>
          <BButton v-if="showHistoryAction" size="sm" variant="outline-primary" @click="$emit('open-history', student)">
            Ver ficha completa
          </BButton>
        </div>
        <div v-if="!(student.recent_attentions || []).length" class="text-muted small">Sin atenciones recientes.</div>
        <div v-else class="d-grid gap-2">
          <div v-for="item in student.recent_attentions" :key="item.id" class="border rounded p-2">
            <div class="d-flex justify-content-between gap-2 align-items-center">
              <div class="fw-semibold">{{ item.consultation_reason || item.attention_category }}</div>
              <InfirmaryStatusBadge :status="item.status" />
            </div>
            <div class="small text-muted">{{ formatInfirmaryDateTime(item.attended_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </BCard>
</template>
