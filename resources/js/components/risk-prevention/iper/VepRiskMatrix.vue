<script setup>
import { computed } from "vue";

const props = defineProps({ cells: { type: Array, default: () => [] }, interactive: { type: Boolean, default: true } });
const emit = defineEmits(["select"]);
const values = [4, 2, 1];
const byKey = computed(() => new Map(props.cells.map((cell) => [`${cell.probability}-${cell.consequence}`, cell])));
const fallback = (p, c) => {
  const score = p * c;
  if (score <= 2) return { level_code: "tolerable", level_label: "Tolerable", color: "#2e7d32", total: 0 };
  if (score === 4) return { level_code: "moderate", level_label: "Moderado", color: "#d97706", total: 0 };
  if (score === 8) return { level_code: "important", level_label: "Importante", color: "#dc6803", total: 0 };
  return { level_code: "intolerable", level_label: "Intolerable", color: "#b42318", total: 0 };
};
const cell = (p, c) => ({ ...fallback(p, c), ...(byKey.value.get(`${p}-${c}`) || {}) });
</script>

<template>
  <div class="vep-shell" role="group" aria-label="Matriz de calor VEP de probabilidad por consecuencia">
    <div class="axis axis--y">Probabilidad</div>
    <div class="vep-grid">
      <template v-for="probability in values" :key="probability">
        <div class="tick tick--y">{{ probability }}</div>
        <button v-for="consequence in [1,2,4]" :key="`${probability}-${consequence}`" type="button" class="vep-cell" :disabled="!interactive" :style="{ '--cell-color': cell(probability, consequence).color }" :aria-label="`Probabilidad ${probability}, consecuencia ${consequence}: ${cell(probability, consequence).level_label}, ${cell(probability, consequence).total || 0} riesgos`" @click="emit('select', { probability, consequence, ...cell(probability, consequence) })">
          <strong>{{ cell(probability, consequence).total || 0 }}</strong><span>{{ cell(probability, consequence).level_label }}</span><small>VEP {{ probability * consequence }}</small>
        </button>
      </template>
      <span></span><div v-for="consequence in [1,2,4]" :key="`x-${consequence}`" class="tick">{{ consequence }}</div>
    </div>
    <div class="axis axis--x">Consecuencia</div>
  </div>
</template>

<style scoped>
.vep-shell{position:relative;padding:0 0 1.7rem 1.8rem}.vep-grid{display:grid;grid-template-columns:24px repeat(3,minmax(88px,1fr));gap:.42rem}.vep-cell{min-height:82px;padding:.55rem;border:1px solid color-mix(in srgb,var(--cell-color) 35%,#fff);border-radius:9px;background:linear-gradient(145deg,color-mix(in srgb,var(--cell-color) 13%,#fff),#fff);color:#344054;text-align:left;transition:.18s ease}.vep-cell:not(:disabled):hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(16,24,40,.1)}.vep-cell strong,.vep-cell span,.vep-cell small{display:block}.vep-cell strong{color:var(--cell-color);font-size:1.15rem}.vep-cell span{font-size:.66rem;font-weight:700}.vep-cell small{color:#667085;font-size:.56rem}.tick{align-self:center;color:#667085;font-size:.62rem;font-weight:700;text-align:center}.axis{position:absolute;color:#667085;font-size:.58rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.axis--y{left:-1.2rem;top:44%;transform:rotate(-90deg)}.axis--x{right:32%;bottom:0}
</style>
