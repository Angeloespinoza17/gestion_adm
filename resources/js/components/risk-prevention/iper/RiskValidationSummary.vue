<script setup>
import { computed } from "vue";
const props = defineProps({ issues: { type: Array, default: () => [] } });
const emit = defineEmits(["navigate"]);
const grouped = computed(() => ({ block: props.issues.filter((i) => i.severity === "block"), error: props.issues.filter((i) => i.severity === "error"), warning: props.issues.filter((i) => i.severity === "warning") }));
</script>

<template>
  <div class="validation-shell" :class="{ 'validation-shell--ok': !issues.length }">
    <div class="validation-head"><i class="bx" :class="issues.length ? 'bx-error-circle' : 'bx-check-shield'"></i><div><strong>{{ issues.length ? `${issues.length} observaciones de consistencia` : 'Matriz consistente' }}</strong><span>{{ issues.length ? 'Resuelve los bloqueos antes de avanzar.' : 'No se detectaron problemas pendientes.' }}</span></div></div>
    <div v-for="severity in ['block','error','warning']" :key="severity" class="issue-group">
      <button v-for="issue in grouped[severity]" :key="`${issue.code}-${issue.path}`" type="button" :class="`issue issue--${severity}`" @click="emit('navigate', issue)">
        <i class="bx" :class="severity === 'warning' ? 'bx-info-circle' : 'bx-x-circle'"></i><span>{{ issue.message }}</span><small>{{ issue.path }}</small><i class="bx bx-right-arrow-alt"></i>
      </button>
    </div>
  </div>
</template>

<style scoped>
.validation-shell{padding:1rem;border:1px solid #fed7aa;border-radius:10px;background:#fffbeb}.validation-shell--ok{border-color:#b7e4c7;background:#f0fdf4}.validation-head{display:flex;align-items:center;gap:.65rem}.validation-head>i{font-size:1.5rem;color:#d97706}.validation-shell--ok .validation-head>i{color:#15803d}.validation-head strong,.validation-head span{display:block}.validation-head span{color:#667085;font-size:.68rem}.issue-group{display:grid;gap:.35rem;margin-top:.55rem}.issue{display:grid;grid-template-columns:auto 1fr auto auto;align-items:center;gap:.45rem;padding:.48rem .6rem;border:0;border-left:3px solid #dc6803;background:#fff;color:#344054;text-align:left;font-size:.68rem}.issue--block,.issue--error{border-left-color:#b42318}.issue small{color:#98a2b3}
</style>
