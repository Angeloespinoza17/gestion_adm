<script>
export default {
  props: {
    context: { type: Object, default: null },
    loading: { type: Boolean, default: false },
  },
  computed: {
    alerts() {
      return this.context?.medical_alerts || [];
    },
    medications() {
      return this.context?.permanent_medications || [];
    },
  },
  methods: {
    value(value, fallback = "Sin información") {
      return value === null || value === undefined || value === "" ? fallback : value;
    },
    alertClass(level) {
      return `medical-alert--${level || "info"}`;
    },
    medicationLabel(item) {
      const dose = [item.dose_amount, item.dose_unit].filter(Boolean).join(" ") || item.dose;
      return [item.medication_name, dose, item.frequency || item.schedule_text].filter(Boolean).join(" · ");
    },
  },
};
</script>

<template>
  <section class="medical-summary" aria-live="polite">
    <div v-if="loading" class="medical-summary__loading">
      <i class="bx bx-loader-alt bx-spin"></i> Cargando ficha médica…
    </div>
    <template v-else-if="context">
      <header class="medical-summary__header">
        <div>
          <span>Ficha médica precargada</span>
          <strong>{{ context.full_name }}</strong>
          <small>{{ context.rut || "Sin RUT" }} · {{ context.course || "Sin curso" }} · {{ context.age ?? "-" }} años</small>
        </div>
        <div class="medical-summary__status" :class="{ 'has-alerts': alerts.length }">
          <i :class="alerts.length ? 'bx bxs-error-circle' : 'bx bxs-check-shield'"></i>
          {{ alerts.length ? `${alerts.length} alerta${alerts.length === 1 ? '' : 's'}` : "Sin alertas registradas" }}
        </div>
      </header>

      <div v-if="alerts.length" class="medical-alerts">
        <article v-for="(alert, index) in alerts" :key="`${alert.label}-${index}`" class="medical-alert" :class="alertClass(alert.level)">
          <i class="bx bxs-error-alt"></i>
          <div><strong>{{ alert.label }}</strong><span>{{ value(alert.detail, "Revisar ficha médica") }}</span></div>
        </article>
      </div>

      <div class="medical-summary__grid">
        <div><span>Grupo sanguíneo</span><strong>{{ value(context.blood_type) }}</strong></div>
        <div><span>Previsión</span><strong>{{ value(context.health_insurance) }}</strong></div>
        <div><span>Prestador</span><strong>{{ value(context.healthcare_provider) }}</strong></div>
        <div><span>Educación Física</span><strong>{{ context.fit_for_physical_education === false ? "No apta" : context.fit_for_physical_education === true ? "Apta" : "Sin información" }}</strong></div>
      </div>

      <div v-if="medications.length" class="medical-summary__medications">
        <span>Medicación vigente</span>
        <ul><li v-for="item in medications" :key="item.id">{{ medicationLabel(item) }}</li></ul>
      </div>

      <div v-if="context.health_observations" class="medical-summary__observations">
        <span>Observaciones de salud</span><p>{{ context.health_observations }}</p>
      </div>
    </template>
  </section>
</template>

<style scoped>
.medical-summary { border: 1px solid #dbe3ef; border-radius: 8px; background: #f8fafc; overflow: hidden; }
.medical-summary__loading { padding: 18px; color: #6b7484; }
.medical-summary__header { display: flex; justify-content: space-between; gap: 16px; padding: 15px 17px; background: #eef4fc; border-bottom: 1px solid #dbe3ef; }
.medical-summary__header span, .medical-summary__grid span, .medical-summary__medications > span, .medical-summary__observations > span { display: block; color: #687386; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.medical-summary__header strong { display: block; margin-top: 2px; font-size: 16px; }
.medical-summary__header small { color: #687386; }
.medical-summary__status { align-self: center; padding: 7px 10px; border-radius: 999px; background: #e6f5ef; color: #197252; font-size: 12px; font-weight: 700; white-space: nowrap; }
.medical-summary__status.has-alerts { background: #fde8e7; color: #b7342e; }
.medical-alerts { display: grid; gap: 8px; padding: 12px 16px 0; }
.medical-alert { display: grid; grid-template-columns: 22px 1fr; gap: 8px; padding: 9px 11px; border-left: 4px solid #d89a2b; border-radius: 5px; background: #fff7e8; color: #704c10; }
.medical-alert--critical { border-color: #d8463f; background: #fff0ef; color: #912923; }
.medical-alert--info { border-color: #3a75ca; background: #edf5ff; color: #28568f; }
.medical-alert strong, .medical-alert span { display: block; }
.medical-alert span { margin-top: 2px; font-size: 12px; }
.medical-summary__grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px; margin: 12px 16px; background: #dfe6ef; border: 1px solid #dfe6ef; }
.medical-summary__grid > div { padding: 10px; background: #fff; }
.medical-summary__grid strong { display: block; margin-top: 3px; font-size: 13px; }
.medical-summary__medications, .medical-summary__observations { margin: 0 16px 12px; padding: 11px; border-radius: 5px; background: #fff; border: 1px solid #e1e7ef; }
.medical-summary__medications ul { margin: 6px 0 0; padding-left: 18px; font-size: 12px; }
.medical-summary__observations p { margin: 5px 0 0; font-size: 12px; white-space: pre-wrap; }
@media (max-width: 767px) { .medical-summary__header { flex-direction: column; } .medical-summary__status { align-self: flex-start; } .medical-summary__grid { grid-template-columns: repeat(2, 1fr); } }
</style>
