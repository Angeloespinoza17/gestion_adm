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
  <BCard class="convivencia-case-card border-0 shadow-sm">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="convivencia-case-card__icon"><i class="bx bx-folder-open"></i></div>
        <div>
          <div class="text-muted small">Folio</div>
          <div class="convivencia-case-card__folio">{{ item.folio || "Sin folio" }}</div>
        </div>
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
    <div v-if="$slots.default" class="convivencia-case-card__footer">
      <slot />
    </div>
  </BCard>
</template>

<style scoped>
.convivencia-case-card {
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.convivencia-case-card:hover {
  border-color: rgba(79, 99, 217, 0.32) !important;
  box-shadow: 0 14px 30px rgba(42, 48, 66, 0.09) !important;
  transform: translateY(-1px);
}

.convivencia-case-card__icon {
  display: grid;
  width: 42px;
  height: 42px;
  color: #4f63d9;
  font-size: 1.2rem;
  place-items: center;
  border-radius: 12px;
  background: rgba(79, 99, 217, 0.1);
}

.convivencia-case-card__folio {
  color: var(--bs-body-color);
  font-size: 1.05rem;
  font-weight: 750;
}

.convivencia-case-card__footer {
  margin: 1rem -1.25rem -1.25rem;
  padding: 0.85rem 1.25rem;
  border-top: 1px solid #e5eaf2;
  border-radius: 0 0 16px 16px;
  background: #f8f9fc;
}

@media (prefers-reduced-motion: reduce) {
  .convivencia-case-card {
    transition: none;
  }
}
</style>
