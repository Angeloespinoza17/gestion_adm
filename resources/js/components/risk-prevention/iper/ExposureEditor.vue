<script setup>
import { computed } from "vue";
const props = defineProps({ modelValue: { type: Array, default: () => [] }, categories: { type: Array, default: () => [] }, justification: { type: String, default: "" } });
const emit = defineEmits(["update:modelValue", "update:justification"]);
const rows = computed(() => props.categories.map((category) => ({ category, exposure: props.modelValue.find((item) => Number(item.exposure_category_id) === Number(category.id)) || { exposure_category_id: category.id, count: 0 } })));
const total = computed(() => props.modelValue.reduce((sum, row) => sum + Number(row.count || 0), 0));
function update(categoryId, count) {
  const next = props.categories.map((category) => ({ exposure_category_id: category.id, count: category.id === categoryId ? Math.max(0, Number(count || 0)) : Number(props.modelValue.find((item) => Number(item.exposure_category_id) === Number(category.id))?.count || 0) }));
  emit("update:modelValue", next);
}
</script>

<template>
  <div class="exposure-editor">
    <label v-for="row in rows" :key="row.category.id"><span>{{ row.category.name }}</span><input type="number" min="0" step="1" class="form-control" :value="row.exposure.count" @input="update(row.category.id, $event.target.value)" /></label>
    <div class="exposure-total"><span>Total expuesto</span><strong>{{ total }}</strong></div>
    <label v-if="total === 0" class="exposure-reason"><span>Justificación obligatoria sin exposición</span><textarea class="form-control" rows="2" :value="justification" @input="emit('update:justification', $event.target.value)"></textarea></label>
  </div>
</template>

<style scoped>
.exposure-editor{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.65rem}.exposure-editor label span,.exposure-total span{display:block;margin-bottom:.3rem;color:#667085;font-size:.63rem;font-weight:700}.exposure-total{display:flex;flex-direction:column;justify-content:center;padding:.6rem .8rem;border:1px solid #d8e3ec;border-radius:8px;background:#f4f8fb}.exposure-total strong{color:#17324d;font-size:1.1rem}.exposure-reason{grid-column:1/-1}
</style>
