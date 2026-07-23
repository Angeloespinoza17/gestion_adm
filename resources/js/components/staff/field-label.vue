<script>
export default {
  name: "StaffFieldLabel",
  props: {
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: true },
  },
};
</script>

<template>
  <label class="form-label staff-field-label">
    <span>{{ label }}</span>
    <span v-if="required" class="staff-field-label__required">Obligatorio</span>
    <span v-else-if="optional" class="staff-field-label__optional">Opcional</span>
  </label>
</template>

<style scoped>
.staff-field-label {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.staff-field-label__required,
.staff-field-label__optional {
  border-radius: 999px;
  font-size: 0.64rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 0.16rem 0.42rem;
  text-transform: uppercase;
}

.staff-field-label__required {
  background: rgba(244, 106, 106, 0.11);
  color: #d94c4c;
}

.staff-field-label__optional {
  background: #f0f2f7;
  color: #74788d;
}
</style>
