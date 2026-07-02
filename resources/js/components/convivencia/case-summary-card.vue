<script>
import StatusBadge from "./status-badge.vue";
import CriticalityBadge from "./criticality-badge.vue";
import { formatConvivenciaDateTime } from "./module-utils";

export default {
  components: { StatusBadge, CriticalityBadge },
  props: {
    item: { type: Object, required: true },
  },
  methods: {
    formatDate(value) {
      return formatConvivenciaDateTime(value);
    },
  },
};
</script>

<template>
  <BCard class="border-0 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <div class="text-muted small">Folio</div>
        <div class="fw-semibold">{{ item.folio || "Sin folio" }}</div>
      </div>
      <div class="d-flex gap-2">
        <StatusBadge :status="item.status" />
        <CriticalityBadge :value="item.criticality_label" />
      </div>
    </div>
    <div class="row g-3 mt-1 small">
      <div class="col-md-4">
        <div class="text-muted">Clasificación</div>
        <div>{{ item.classification_label || "-" }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted">Apertura</div>
        <div>{{ formatDate(item.opened_at) }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted">Curso</div>
        <div>{{ item.course_section?.display_name || item.courseSection?.display_name || "-" }}</div>
      </div>
    </div>
  </BCard>
</template>
