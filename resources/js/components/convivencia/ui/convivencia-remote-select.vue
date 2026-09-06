<script>
import axios from "axios";
import Multiselect from "@vueform/multiselect";
import { formatConvivenciaError } from "../module-utils";

export default {
  name: "ConvivenciaRemoteSelect",
  components: { Multiselect },
  props: {
    modelValue: { type: [Number, String], default: null },
    type: { type: String, required: true },
    placeholder: { type: String, default: "Buscar y seleccionar" },
    ariaLabel: { type: String, default: "Buscar referencia" },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
  },
  emits: ["update:modelValue", "change", "select"],
  computed: {
    isStudentType() {
      return this.type === "students";
    },
  },
  data() {
    return {
      requestController: null,
      requestSequence: 0,
      cachedOptions: [],
      lastPage: 1,
      error: "",
    };
  },
  beforeUnmount() {
    this.requestController?.abort();
  },
  methods: {
    studentName(option) {
      if (option && typeof option === "object") {
        return String(option.full_name || option.label || `Estudiante #${option.value || option.id}`);
      }

      return option ? `Estudiante #${option}` : "Estudiante";
    },
    studentCourse(option) {
      if (!option || typeof option !== "object") return "";
      if (option.course) return option.course;

      return String(option.secondary || "").split(" · ")[0] || "";
    },
    studentIdentifier(option) {
      if (!option || typeof option !== "object") return "";
      if (option.identifier) return option.identifier;

      return String(option.secondary || "").split(" · ")[1] || "";
    },
    formatRut(value) {
      const clean = String(value || "").replace(/[^0-9kK]/g, "");
      if (clean.length < 2) return String(value || "");
      const verifier = clean.slice(-1).toUpperCase();
      const body = clean.slice(0, -1).replace(/\B(?=(\d{3})+(?!\d))/g, ".");

      return `${body}-${verifier}`;
    },
    studentInitials(option) {
      const parts = this.studentName(option).trim().split(/\s+/).filter(Boolean);
      if (!parts.length) return "ES";

      return `${parts[0]?.[0] || ""}${parts.length > 1 ? parts.at(-1)?.[0] || "" : ""}`.toUpperCase();
    },
    async fetchOptions(search = "") {
      this.requestController?.abort();
      this.requestController = new AbortController();
      const sequence = ++this.requestSequence;
      this.error = "";
      try {
        const isStudentSearch = this.type === "students";
        const response = await axios.get(
          isStudentSearch ? "/api/convivencia/students" : `/api/convivencia/references/${this.type}`,
          {
          signal: this.requestController.signal,
          params: {
            ...this.filters,
            search: String(search || "").trim() || undefined,
            ...(!isStudentSearch ? { page: 1, per_page: 20 } : {}),
            selected_id: this.modelValue || undefined,
          },
          },
        );
        if (sequence !== this.requestSequence) return this.cachedOptions;
        const payload = response.data?.data?.data ? response.data.data : response.data || {};
        const records = Array.isArray(payload.data) ? payload.data : (Array.isArray(response.data?.data) ? response.data.data : []);
        this.lastPage = Number(payload.last_page || 1);
        this.cachedOptions = records.map((item) => ({
          ...item,
          value: item.id,
          label: item.label || `Registro #${item.id}`,
          status: item.status || null,
          secondary: item.secondary || null,
        }));
        return this.cachedOptions;
      } catch (error) {
        if (error?.code === "ERR_CANCELED" || error?.name === "CanceledError") return this.cachedOptions;
        this.error = formatConvivenciaError(error, "No se pudieron buscar las referencias.");
        return this.cachedOptions;
      }
    },
    updateValue(value) {
      this.$emit("update:modelValue", value || null);
      this.$emit("change", value || null);
    },
    clearSelection() {
      this.$refs.multiselect?.clear?.();
      this.cachedOptions = [];
      this.error = "";
    },
    selectOption(value, option) {
      const resolved = option || this.cachedOptions.find((item) => String(item.value) === String(value)) || null;
      this.$emit("select", resolved);
    },
  },
};
</script>

<template>
  <div
    class="convivencia-remote-select"
    :class="{ 'has-error': error, 'is-student-select': isStudentType }"
  >
    <Multiselect
      ref="multiselect"
      :model-value="modelValue"
      :options="fetchOptions"
      value-prop="value"
      label="label"
      :track-by="['label', 'secondary']"
      :searchable="true"
      :filter-results="false"
      :delay="280"
      :min-chars="isStudentType ? 2 : 0"
      :clear-on-search="true"
      :can-clear="!required"
      :can-deselect="!required"
      :disabled="disabled"
      :required="required"
      :allow-absent="true"
      :placeholder="placeholder"
      :aria="{ 'aria-label': ariaLabel }"
      :no-options-text="isStudentType ? 'Escribe al menos 2 caracteres para buscar' : 'No hay registros disponibles'"
      :no-results-text="isStudentType ? 'No encontramos estudiantes con esa búsqueda' : 'No encontramos coincidencias'"
      @change="updateValue"
      @select="selectOption"
    >
      <template #singlelabel="{ value }">
        <div v-if="isStudentType" class="multiselect-single-label convivencia-student-selected">
          <span class="convivencia-student-avatar" aria-hidden="true">{{ studentInitials(value) }}</span>
          <span class="convivencia-student-selected__copy">
            <b>{{ studentName(value) }}</b>
            <small v-if="studentCourse(value)">{{ studentCourse(value) }}</small>
          </span>
        </div>
        <div v-else class="multiselect-single-label">
          <span class="multiselect-single-label-text convivencia-remote-select__selected">{{ value.label || value }}</span>
        </div>
      </template>
      <template #option="{ option, isSelected }">
        <span v-if="isStudentType" class="convivencia-student-option">
          <span class="convivencia-student-avatar" aria-hidden="true">{{ studentInitials(option) }}</span>
          <span class="convivencia-student-option__copy">
            <b>{{ studentName(option) }}</b>
            <span class="convivencia-student-option__meta">
              <small v-if="studentCourse(option)" class="is-course"><i class="bx bx-chalkboard"></i>{{ studentCourse(option) }}</small>
              <small v-if="studentIdentifier(option)" class="is-rut"><i class="bx bx-id-card"></i>RUT {{ formatRut(studentIdentifier(option)) }}</small>
            </span>
          </span>
          <i v-if="isSelected(option)" class="bx bx-check-circle convivencia-student-option__check" aria-hidden="true"></i>
        </span>
        <span v-else class="convivencia-remote-select__option">
          <b>{{ option.label }}</b>
          <small v-if="option.secondary || option.status">{{ [option.secondary, option.status].filter(Boolean).join(' · ') }}</small>
        </span>
      </template>
      <template #nooptions>
        <div v-if="isStudentType" class="convivencia-student-empty">
          <i class="bx bx-search-alt" aria-hidden="true"></i>
          <b>Busca por nombre o RUT</b>
          <span>Escribe al menos 2 caracteres para comenzar.</span>
        </div>
        <span v-else>No hay registros disponibles</span>
      </template>
      <template #noresults>
        <div v-if="isStudentType" class="convivencia-student-empty">
          <i class="bx bx-user-x" aria-hidden="true"></i>
          <b>Sin coincidencias</b>
          <span>Revisa el nombre o RUT e intenta nuevamente.</span>
        </div>
        <span v-else>No encontramos coincidencias</span>
      </template>
      <template #afterlist><small v-if="lastPage > 1" class="convivencia-remote-select__hint">Hay más resultados. Escribe parte del nombre o folio para acotar la búsqueda.</small></template>
    </Multiselect>
    <small v-if="error" class="convivencia-remote-select__error" role="alert">{{ error }}</small>
  </div>
</template>

<style scoped>
.convivencia-remote-select {
  min-width: 0;
  --ms-option-bg-pointed: #f1f4ff;
  --ms-option-color-pointed: #233465;
  --ms-option-bg-selected: #e9eeff;
  --ms-option-color-selected: #233465;
  --ms-option-bg-selected-pointed: #dfe6ff;
  --ms-option-color-selected-pointed: #1f357f;
}

.convivencia-remote-select :deep(.multiselect) {
  min-height: 40px;
  color: #35435d;
  font-size: .7rem;
  border-color: #dce2ed;
  border-radius: 9px;
}

.convivencia-remote-select :deep(.multiselect.is-active) {
  border-color: #7687e9;
  box-shadow: 0 0 0 3px rgba(80,100,217,.12);
}

.convivencia-remote-select :deep(.multiselect-dropdown) {
  overflow: hidden;
  border: 1px solid #dfe4f2;
  border-radius: 12px;
  box-shadow: 0 16px 35px rgba(38,52,105,.16), 0 3px 9px rgba(38,52,105,.08);
}

.convivencia-remote-select :deep(.multiselect-options) {
  max-height: 18rem;
  padding: .3rem;
}

.convivencia-remote-select :deep(.multiselect-option) {
  min-height: 42px;
  margin: .12rem 0;
  padding: .5rem .62rem;
  border-radius: 9px;
}

.convivencia-remote-select__selected {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.convivencia-remote-select__option {
  display: grid;
  gap: .08rem;
}

.convivencia-remote-select__option b { font-size: .68rem; }
.convivencia-remote-select__option small { color: #7c8798; font-size: .57rem; }

.is-student-select :deep(.multiselect) {
  min-height: 48px;
  border-color: #d8def0;
  border-radius: 12px;
  background: #fff;
}

.is-student-select :deep(.multiselect-search) {
  padding-left: .75rem;
  color: #263451;
  font-size: .76rem;
  font-weight: 600;
}

.is-student-select :deep(.multiselect-placeholder) {
  color: #8792a7;
  font-size: .68rem;
}

.is-student-select :deep(.multiselect-option) {
  min-height: 62px;
  color: #293955;
  border: 1px solid transparent;
  transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
}

.is-student-select :deep(.multiselect-option.is-pointed) {
  border-color: #dbe2ff;
  transform: translateX(2px);
}

.is-student-select :deep(.multiselect-option.is-selected) {
  border-color: #cdd7ff;
}

.convivencia-student-selected {
  display: flex !important;
  min-width: 0;
  align-items: center;
  gap: .5rem;
  padding-right: 4.2rem !important;
}

.convivencia-student-selected__copy {
  display: grid;
  min-width: 0;
  gap: .02rem;
}

.convivencia-student-selected__copy b {
  overflow: hidden;
  color: #293955;
  font-size: .68rem;
  font-weight: 800;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.convivencia-student-selected__copy small {
  overflow: hidden;
  color: #75829a;
  font-size: .55rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.convivencia-student-avatar {
  display: inline-grid;
  width: 2rem;
  height: 2rem;
  flex: 0 0 2rem;
  place-items: center;
  color: #fff;
  font-size: .62rem;
  font-weight: 850;
  letter-spacing: .02em;
  border-radius: 10px;
  background: linear-gradient(135deg,#5369dd,#7b5bd6);
  box-shadow: 0 5px 12px rgba(79,99,217,.2);
}

.convivencia-student-option {
  display: flex;
  width: 100%;
  min-width: 0;
  align-items: center;
  gap: .65rem;
}

.convivencia-student-option__copy {
  display: grid;
  min-width: 0;
  flex: 1;
  gap: .25rem;
}

.convivencia-student-option__copy > b {
  overflow: hidden;
  color: inherit;
  font-size: .72rem;
  font-weight: 820;
  line-height: 1.25;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.convivencia-student-option__meta {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  gap: .28rem;
}

.convivencia-student-option__meta small {
  display: inline-flex;
  max-width: 100%;
  align-items: center;
  gap: .2rem;
  padding: .15rem .34rem;
  overflow: hidden;
  color: #5f6f8b;
  font-size: .55rem;
  font-weight: 720;
  line-height: 1.25;
  text-overflow: ellipsis;
  white-space: nowrap;
  border: 1px solid #e0e5f0;
  border-radius: 999px;
  background: rgba(255,255,255,.78);
}

.convivencia-student-option__meta small.is-course {
  color: #3f579f;
  border-color: #d7dff9;
  background: #f7f8ff;
}

.convivencia-student-option__meta i { font-size: .72rem; }

.convivencia-student-option__check {
  flex: 0 0 auto;
  color: #4f63d9;
  font-size: 1.05rem;
}

.convivencia-student-empty {
  display: grid;
  justify-items: center;
  gap: .16rem;
  padding: 1rem .75rem;
  color: #7a879c;
  text-align: center;
}

.convivencia-student-empty i { color: #7183df; font-size: 1.25rem; }
.convivencia-student-empty b { color: #41506d; font-size: .66rem; }
.convivencia-student-empty span { font-size: .56rem; }

.convivencia-remote-select__hint {
  display: block;
  padding: .55rem .7rem;
  color: #68758a;
  font-size: .57rem;
  line-height: 1.4;
  background: #f7f9fc;
}

.convivencia-remote-select__error {
  display: block;
  margin-top: .25rem;
  color: #b13e4b;
  font-size: .58rem;
}

.convivencia-remote-select.has-error :deep(.multiselect) { border-color: #dc8e98; }

@media (max-width: 575.98px) {
  .is-student-select :deep(.multiselect-option) {
    min-height: 66px;
    padding: .55rem;
  }

  .convivencia-student-avatar {
    width: 2.15rem;
    height: 2.15rem;
    flex-basis: 2.15rem;
  }

  .convivencia-student-option__copy > b { white-space: normal; }
}
</style>
