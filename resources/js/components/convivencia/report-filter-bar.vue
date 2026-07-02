<script>
export default {
  props: {
    modelValue: { type: Object, required: true },
    catalogs: { type: Object, required: true },
    showSemester: { type: Boolean, default: false },
  },
  emits: ["update:modelValue", "submit"],
  computed: {
    value() {
      return this.modelValue;
    },
  },
  methods: {
    update(key, event) {
      const next = { ...this.modelValue, [key]: event?.target?.value ?? event };
      this.$emit("update:modelValue", next);
    },
    submit() {
      this.$emit("submit");
    },
  },
};
</script>

<template>
  <BCard class="border-0 shadow-sm">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Año académico</label>
        <BFormSelect :model-value="value.academic_year_id" @update:model-value="update('academic_year_id', $event)">
          <option :value="null">Todos</option>
          <option v-for="year in catalogs.academic_years || []" :key="year.id" :value="year.id">{{ year.name }}</option>
        </BFormSelect>
      </div>
      <div class="col-md-3">
        <label class="form-label">Curso</label>
        <BFormSelect :model-value="value.course_section_id" @update:model-value="update('course_section_id', $event)">
          <option :value="null">Todos</option>
          <option v-for="course in catalogs.courses || []" :key="course.id" :value="course.id">{{ course.display_name }}</option>
        </BFormSelect>
      </div>
      <div class="col-md-2">
        <label class="form-label">Desde</label>
        <BFormInput type="date" :model-value="value.from" @update:model-value="update('from', $event)" />
      </div>
      <div class="col-md-2">
        <label class="form-label">Hasta</label>
        <BFormInput type="date" :model-value="value.to" @update:model-value="update('to', $event)" />
      </div>
      <div v-if="showSemester" class="col-md-1">
        <label class="form-label">Sem.</label>
        <BFormSelect :model-value="value.semester" @update:model-value="update('semester', $event)">
          <option :value="null">-</option>
          <option value="1">1</option>
          <option value="2">2</option>
        </BFormSelect>
      </div>
      <div class="col-md-1">
        <BButton variant="primary" class="w-100" @click="submit">Filtrar</BButton>
      </div>
    </div>
  </BCard>
</template>
