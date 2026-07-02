<script>
import StatusBadge from "./status-badge.vue";
import { formatConvivenciaDateTime, humanizeConvivenciaStatus } from "./module-utils";

export default {
  components: { StatusBadge },
  props: {
    items: { type: Array, default: () => [] },
    emptyText: { type: String, default: "No hay registros para mostrar." },
  },
  methods: {
    formatDate(value) {
      return formatConvivenciaDateTime(value);
    },
    humanize(value) {
      return humanizeConvivenciaStatus(value);
    },
    resolvedDate(item) {
      return item.changed_at || item.follow_up_at || item.created_at || item.activated_at || null;
    },
  },
};
</script>

<template>
  <div v-if="items.length" class="convivencia-timeline">
    <div v-for="(item, index) in items" :key="item.id || index" class="timeline-entry">
      <div class="timeline-marker"></div>
      <div class="timeline-card border rounded p-3 bg-light-subtle">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
          <div>
            <div class="fw-semibold">{{ item.title || item.stage_name || item.event_type || "Registro" }}</div>
            <div class="small text-muted">{{ formatDate(resolvedDate(item)) }}</div>
          </div>
          <StatusBadge v-if="item.status || item.new_status" :status="item.status || item.new_status" />
        </div>
        <div v-if="item.notes || item.comment" class="small">{{ item.notes || item.comment }}</div>
        <div v-if="item.previous_status || item.new_status" class="small text-muted mt-2">
          {{ humanize(item.previous_status) }}<span v-if="item.previous_status || item.new_status"> → </span>{{ humanize(item.new_status) }}
        </div>
      </div>
    </div>
  </div>
  <div v-else class="text-muted small">{{ emptyText }}</div>
</template>

<style scoped>
.convivencia-timeline {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.timeline-entry {
  position: relative;
  padding-left: 1.5rem;
}

.timeline-entry:not(:last-child)::before {
  content: "";
  position: absolute;
  left: 0.4rem;
  top: 0.9rem;
  bottom: -1rem;
  width: 2px;
  background: #d9e2ef;
}

.timeline-marker {
  position: absolute;
  left: 0;
  top: 0.35rem;
  width: 0.8rem;
  height: 0.8rem;
  border-radius: 999px;
  background: #556ee6;
  box-shadow: 0 0 0 4px rgba(85, 110, 230, 0.14);
}
</style>
