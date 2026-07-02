<script>
import axios from "axios";
import Multiselect from "@vueform/multiselect";

const emptyReminder = () => ({
  reminder_type: "before",
  days_before: 3,
  reminder_date: "",
  is_active: true,
});

const emptyStage = () => ({
  id: null,
  title: "",
  stage_key: "",
  responsible_user_id: null,
  start_date: "",
  end_date: "",
  start_time: "",
  end_time: "",
  priority: "media",
  status: "pendiente",
});

const emptyForm = () => ({
  creation_mode: "single",
  edit_scope: "single",
  title: "",
  description: "",
  process_type_id: null,
  institution_id: null,
  department_id: null,
  responsible_user_id: null,
  start_date: "",
  end_date: "",
  start_time: "",
  end_time: "",
  priority: "media",
  status: "pendiente",
  external_url: "",
  internal_observations: "",
  requires_submission: false,
  requires_payment: false,
  requires_signature: false,
  requires_review: false,
  requires_approval: false,
  participant_user_ids: [],
  informed_user_ids: [],
  reminders: [emptyReminder()],
  recurrence: {
    mode: "monthly",
    frequency: "monthly",
    interval: 1,
    weekdays: [],
    monthly_mode: "day_of_month",
    day_of_month: "",
    ends_on: "",
    auto_generate: true,
  },
  stages: [emptyStage()],
});

export default {
  components: { Multiselect },
  props: {
    modelValue: { type: Boolean, default: false },
    catalogs: { type: Object, default: () => ({}) },
    eventRecord: { type: Object, default: null },
    defaultMode: { type: String, default: "single" },
  },
  emits: ["update:modelValue", "saved"],
  data() {
    return {
      form: emptyForm(),
      saving: false,
      error: null,
      files: [],
    };
  },
  computed: {
    showModal: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit("update:modelValue", value);
      },
    },
    processTypeOptions() {
      return [{ value: null, label: "Sin tipo" }].concat(
        (this.catalogs.process_types || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    institutionOptions() {
      return [{ value: null, label: "Sin institución" }].concat(
        (this.catalogs.institutions || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    departmentOptions() {
      return [{ value: null, label: "Sin departamento" }].concat(
        (this.catalogs.departments || []).map((item) => ({
          value: item.id,
          label: item.name,
        }))
      );
    },
    userOptions() {
      return [{ value: null, label: "Sin responsable" }].concat(
        (this.catalogs.responsible_users || []).map((item) => ({
          value: item.id,
          label: `${item.name} (${item.email})`,
        }))
      );
    },
    responsibleUserOptions() {
      return [{ value: null, label: "Sin responsable" }].concat(
        (this.catalogs.responsible_users || []).map((item) => ({
          value: item.id,
          label: `${item.name} (${item.email})`,
        }))
      );
    },
    priorityOptions() {
      return (this.catalogs.priorities || []).map((item) => ({ value: item.value, text: item.label }));
    },
    statusOptions() {
      return (this.catalogs.statuses || []).map((item) => ({ value: item.value, text: item.label }));
    },
    reminderTypeOptions() {
      return (this.catalogs.reminder_types || []).map((item) => ({ value: item.value, text: item.label }));
    },
    recurrenceOptions() {
      return (this.catalogs.recurrence_types || []).map((item) => ({ value: item.value, text: item.label }));
    },
    weekdayOptions() {
      return (this.catalogs.weekdays || []).map((item) => ({ value: item.value, label: item.label }));
    },
    stageTemplateOptions() {
      return [{ value: "", text: "Sin plantilla" }].concat(
        (this.catalogs.stage_templates || []).map((item) => ({
          value: item.key,
          text: item.title,
        }))
      );
    },
    isEditing() {
      return Boolean(this.eventRecord?.id);
    },
    isOccurrence() {
      return this.eventRecord?.event_kind === "occurrence";
    },
  },
  watch: {
    modelValue(value) {
      if (value) {
        this.resetForm();
      }
    },
    eventRecord: {
      handler() {
        if (this.modelValue) {
          this.resetForm();
        }
      },
      deep: true,
    },
  },
  methods: {
    resetForm() {
      this.error = null;
      this.files = [];

      if (this.eventRecord) {
        this.form = this.formFromEvent(this.eventRecord);
        return;
      }

      this.form = {
        ...emptyForm(),
        creation_mode: this.defaultMode || "single",
      };
    },
    formFromEvent(event) {
      const creationMode = event.event_kind === "series_master"
        ? "recurring"
        : event.event_kind === "process"
          ? "process"
          : "single";

      return {
        creation_mode: creationMode,
        edit_scope: event.event_kind === "occurrence" ? "this_occurrence" : "single",
        title: event.title || "",
        description: event.description || "",
        process_type_id: event.process_type_id ?? null,
        institution_id: event.institution_id ?? null,
        department_id: event.department_id ?? null,
        responsible_user_id: event.responsible_user_id ?? null,
        start_date: this.toInputDate(event.start_date),
        end_date: this.toInputDate(event.end_date),
        start_time: this.toInputTime(event.start_time),
        end_time: this.toInputTime(event.end_time),
        priority: event.priority || "media",
        status: event.status || "pendiente",
        external_url: event.external_url || "",
        internal_observations: event.internal_observations || "",
        requires_submission: Boolean(event.requires_submission),
        requires_payment: Boolean(event.requires_payment),
        requires_signature: Boolean(event.requires_signature),
        requires_review: Boolean(event.requires_review),
        requires_approval: Boolean(event.requires_approval),
        participant_user_ids: (event.event_users || []).filter((item) => item.role_in_event === "participant").map((item) => item.user_id),
        informed_user_ids: (event.event_users || []).filter((item) => item.role_in_event === "informed").map((item) => item.user_id),
        reminders: (event.reminders || []).length
          ? (event.reminders || []).map((reminder) => ({
              id: reminder.id,
              reminder_type: reminder.reminder_type,
              days_before: reminder.days_before ?? 0,
              reminder_date: this.toInputDate(reminder.reminder_date),
              is_active: Boolean(reminder.is_active),
            }))
          : [emptyReminder()],
        recurrence: {
          mode: event.recurrence_rule?.mode || "monthly",
          frequency: event.recurrence_rule?.frequency || "monthly",
          interval: event.recurrence_rule?.interval || 1,
          weekdays: event.recurrence_rule?.weekdays || [],
          monthly_mode: event.recurrence_rule?.monthly_mode || "day_of_month",
          day_of_month: event.recurrence_rule?.day_of_month || "",
          ends_on: this.toInputDate(event.recurrence_rule?.ends_on),
          auto_generate: Boolean(event.recurrence_rule?.auto_generate ?? event.auto_generate_occurrences),
        },
        stages: event.event_kind === "process" && (event.child_events || []).length
          ? event.child_events.map((stage) => ({
              id: stage.id,
              title: stage.title || "",
              stage_key: stage.stage_key || "",
              responsible_user_id: stage.responsible_user_id ?? null,
              start_date: this.toInputDate(stage.start_date),
              end_date: this.toInputDate(stage.end_date),
              start_time: this.toInputTime(stage.start_time),
              end_time: this.toInputTime(stage.end_time),
              priority: stage.priority || "media",
              status: stage.status || "pendiente",
            }))
          : [emptyStage()],
      };
    },
    setCreationMode(mode) {
      this.form.creation_mode = mode;
      if (mode !== "process" && !this.form.start_date) {
        this.form.start_date = new Date().toISOString().slice(0, 10);
        this.form.end_date = this.form.start_date;
      }
      if (mode !== "recurring") {
        this.form.recurrence = {
          mode: "monthly",
          frequency: "monthly",
          interval: 1,
          weekdays: [],
          monthly_mode: "day_of_month",
          day_of_month: "",
          ends_on: "",
          auto_generate: true,
        };
      }
    },
    addReminder() {
      this.form.reminders.push(emptyReminder());
    },
    removeReminder(index) {
      if (this.form.reminders.length === 1) {
        this.form.reminders = [emptyReminder()];
        return;
      }
      this.form.reminders.splice(index, 1);
    },
    addStage(templateKey = "") {
      const template = (this.catalogs.stage_templates || []).find((item) => item.key === templateKey);
      this.form.stages.push({
        ...emptyStage(),
        title: template?.title || "",
        stage_key: template?.key || "",
        priority: this.form.priority,
        status: this.form.status,
      });
    },
    applyStageTemplate(stage, key) {
      const template = (this.catalogs.stage_templates || []).find((item) => item.key === key);
      stage.stage_key = key;
      if (template && !stage.title) {
        stage.title = template.title;
      }
    },
    removeStage(index) {
      if (this.form.stages.length === 1) {
        this.form.stages = [emptyStage()];
        return;
      }
      this.form.stages.splice(index, 1);
    },
    onFiles(event) {
      this.files = Array.from(event?.target?.files || []);
    },
    sanitizeReminders(reminders) {
      return (reminders || [])
        .filter((item) => item?.reminder_type)
        .map((item) => ({
          id: item.id ?? null,
          reminder_type: item.reminder_type,
          days_before: item.reminder_type === "same_day" ? 0 : Number(item.days_before || 0),
          reminder_date: item.reminder_type === "fixed_date" ? (item.reminder_date || null) : null,
          is_active: item.is_active !== false,
        }));
    },
    buildPayload() {
      const payload = {
        creation_mode: this.form.creation_mode,
        edit_scope: this.form.edit_scope,
        title: this.form.title,
        description: this.form.description || null,
        process_type_id: this.form.process_type_id,
        institution_id: this.form.institution_id,
        department_id: this.form.department_id,
        responsible_user_id: this.form.responsible_user_id,
        start_date: this.form.start_date || null,
        end_date: this.form.end_date || this.form.start_date || null,
        start_time: this.form.start_time || null,
        end_time: this.form.end_time || null,
        priority: this.form.priority,
        status: this.form.status,
        external_url: this.form.external_url || null,
        internal_observations: this.form.internal_observations || null,
        requires_submission: this.form.requires_submission,
        requires_payment: this.form.requires_payment,
        requires_signature: this.form.requires_signature,
        requires_review: this.form.requires_review,
        requires_approval: this.form.requires_approval,
        participant_user_ids: this.form.participant_user_ids || [],
        informed_user_ids: this.form.informed_user_ids || [],
        reminders: this.sanitizeReminders(this.form.reminders),
      };

      if (this.form.creation_mode === "recurring") {
        payload.recurrence = {
          mode: this.form.recurrence.mode,
          frequency: this.form.recurrence.frequency,
          interval: Number(this.form.recurrence.interval || 1),
          weekdays: this.form.recurrence.weekdays || [],
          monthly_mode: this.form.recurrence.monthly_mode,
          day_of_month: this.form.recurrence.day_of_month ? Number(this.form.recurrence.day_of_month) : null,
          ends_on: this.form.recurrence.ends_on || null,
          auto_generate: this.form.recurrence.auto_generate,
        };
      }

      if (this.form.creation_mode === "process") {
        payload.stages = (this.form.stages || []).map((stage) => ({
          id: stage.id || null,
          title: stage.title,
          stage_key: stage.stage_key || null,
          responsible_user_id: stage.responsible_user_id || null,
          start_date: stage.start_date,
          end_date: stage.end_date || stage.start_date,
          start_time: stage.start_time || null,
          end_time: stage.end_time || null,
          priority: stage.priority || this.form.priority,
          status: stage.status || this.form.status,
        }));
      }

      return payload;
    },
    async save() {
      this.saving = true;
      this.error = null;

      try {
        const payload = this.buildPayload();
        let response;

        if (this.isEditing) {
          response = await axios.put(`/api/relevant-calendar/events/${this.eventRecord.id}`, payload);
        } else {
          response = await axios.post("/api/relevant-calendar/events", payload);
        }

        const saved = response.data.data;

        if (saved?.id && this.files.length) {
          for (const file of this.files) {
            const formData = new FormData();
            formData.append("document", file);
            await axios.post(`/api/relevant-calendar/events/${saved.id}/attachments`, formData);
          }
        }

        this.$emit("saved", saved);
        this.showModal = false;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    toInputDate(value) {
      return value ? String(value).slice(0, 10) : "";
    },
    toInputTime(value) {
      return value ? String(value).slice(0, 5) : "";
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "No se pudo guardar el evento."
      );
    },
  },
};
</script>

<template>
  <BModal v-model="showModal" :title="isEditing ? 'Editar evento' : 'Nuevo evento'" size="xl" hide-footer scrollable>
    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <BButton :variant="form.creation_mode === 'single' ? 'primary' : 'outline-primary'" @click="setCreationMode('single')">
        Fecha única
      </BButton>
      <BButton :variant="form.creation_mode === 'recurring' ? 'primary' : 'outline-primary'" @click="setCreationMode('recurring')">
        Fecha recurrente
      </BButton>
      <BButton :variant="form.creation_mode === 'process' ? 'primary' : 'outline-primary'" @click="setCreationMode('process')">
        Proceso con etapas
      </BButton>
    </div>

    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label">Título</label>
        <BFormInput v-model="form.title" />
      </div>
      <div class="col-md-4" v-if="isOccurrence">
        <label class="form-label">Al editar esta ocurrencia</label>
        <BFormSelect
          v-model="form.edit_scope"
          :options="[
            { value: 'this_occurrence', text: 'Solo esta ocurrencia' },
            { value: 'future', text: 'Esta y las futuras' },
            { value: 'all', text: 'Toda la serie' }
          ]"
        />
      </div>

      <div class="col-md-4">
        <label class="form-label">Tipo de proceso</label>
        <Multiselect v-model="form.process_type_id" :options="processTypeOptions" :searchable="true" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Institución</label>
        <Multiselect v-model="form.institution_id" :options="institutionOptions" :searchable="true" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Departamento</label>
        <Multiselect v-model="form.department_id" :options="departmentOptions" :searchable="true" />
      </div>

      <div class="col-md-4">
        <label class="form-label">Responsable principal</label>
        <Multiselect v-model="form.responsible_user_id" :options="responsibleUserOptions" :searchable="true" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Prioridad</label>
        <BFormSelect v-model="form.priority" :options="priorityOptions" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Estado</label>
        <BFormSelect v-model="form.status" :options="statusOptions" />
      </div>

      <template v-if="form.creation_mode !== 'process'">
        <div class="col-md-3">
          <label class="form-label">Fecha inicio</label>
          <BFormInput v-model="form.start_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fecha término</label>
          <BFormInput v-model="form.end_date" type="date" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Hora inicio</label>
          <BFormInput v-model="form.start_time" type="time" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Hora término</label>
          <BFormInput v-model="form.end_time" type="time" />
        </div>
      </template>

      <div class="col-md-6">
        <label class="form-label">Participantes</label>
        <Multiselect v-model="form.participant_user_ids" mode="tags" :options="userOptions.filter((item) => item.value)" :searchable="true" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Informados</label>
        <Multiselect v-model="form.informed_user_ids" mode="tags" :options="userOptions.filter((item) => item.value)" :searchable="true" />
      </div>

      <div class="col-md-12">
        <label class="form-label">Descripción</label>
        <BFormTextarea v-model="form.description" rows="3" />
      </div>

      <div class="col-md-6">
        <label class="form-label">Enlace externo</label>
        <BFormInput v-model="form.external_url" type="url" placeholder="https://..." />
      </div>
      <div class="col-md-6">
        <label class="form-label">Adjuntos iniciales</label>
        <BFormInput type="file" multiple @change="onFiles" />
      </div>

      <div class="col-md-12">
        <label class="form-label">Observaciones internas</label>
        <BFormTextarea v-model="form.internal_observations" rows="2" />
      </div>

      <div class="col-md-12">
        <div class="d-flex flex-wrap gap-3">
          <BFormCheckbox v-model="form.requires_submission">Requiere envío</BFormCheckbox>
          <BFormCheckbox v-model="form.requires_payment">Requiere pago</BFormCheckbox>
          <BFormCheckbox v-model="form.requires_signature">Requiere firma</BFormCheckbox>
          <BFormCheckbox v-model="form.requires_review">Requiere revisión</BFormCheckbox>
          <BFormCheckbox v-model="form.requires_approval">Requiere aprobación</BFormCheckbox>
        </div>
      </div>
    </div>

    <BCard v-if="form.creation_mode === 'recurring'" class="mt-4">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Recurrencia</label>
          <BFormSelect v-model="form.recurrence.mode" :options="recurrenceOptions.filter((item) => item.value !== 'none')" />
        </div>
        <div class="col-md-2" v-if="form.recurrence.mode === 'custom'">
          <label class="form-label">Frecuencia base</label>
          <BFormSelect
            v-model="form.recurrence.frequency"
            :options="[
              { value: 'daily', text: 'Diaria' },
              { value: 'weekly', text: 'Semanal' },
              { value: 'monthly', text: 'Mensual' },
              { value: 'yearly', text: 'Anual' }
            ]"
          />
        </div>
        <div class="col-md-2">
          <label class="form-label">Intervalo</label>
          <BFormInput v-model="form.recurrence.interval" type="number" min="1" max="36" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Fin de recurrencia</label>
          <BFormInput v-model="form.recurrence.ends_on" type="date" />
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <BFormCheckbox v-model="form.recurrence.auto_generate">Generar próximas ocurrencias</BFormCheckbox>
        </div>

        <div class="col-md-6" v-if="['weekly'].includes(form.recurrence.mode) || (form.recurrence.mode === 'custom' && form.recurrence.frequency === 'weekly')">
          <label class="form-label">Días de la semana</label>
          <Multiselect v-model="form.recurrence.weekdays" mode="tags" :options="weekdayOptions" :searchable="false" />
        </div>
        <template v-if="['monthly'].includes(form.recurrence.mode) || (form.recurrence.mode === 'custom' && form.recurrence.frequency === 'monthly')">
          <div class="col-md-4">
            <label class="form-label">Modo mensual</label>
            <BFormSelect
              v-model="form.recurrence.monthly_mode"
              :options="[
                { value: 'day_of_month', text: 'Día específico del mes' },
                { value: 'last_business_day', text: 'Último día hábil del mes' }
              ]"
            />
          </div>
          <div class="col-md-2" v-if="form.recurrence.monthly_mode === 'day_of_month'">
            <label class="form-label">Día del mes</label>
            <BFormInput v-model="form.recurrence.day_of_month" type="number" min="1" max="31" />
          </div>
        </template>
      </div>
    </BCard>

    <BCard v-if="form.creation_mode === 'process'" class="mt-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="mb-1">Etapas del proceso</h5>
          <div class="text-muted small">Cada etapa se registra como fecha relacionada del proceso.</div>
        </div>
        <div class="d-flex gap-2">
          <BButton size="sm" variant="outline-secondary" @click="addStage()">Agregar etapa</BButton>
          <BButton size="sm" variant="outline-primary" @click="addStage('submission_deadline')">Agregar etapa de envío</BButton>
        </div>
      </div>

      <div v-for="(stage, index) in form.stages" :key="`stage-${index}`" class="border rounded p-3 mb-3">
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Título de etapa</label>
            <BFormInput v-model="stage.title" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Plantilla</label>
            <BFormSelect v-model="stage.stage_key" :options="stageTemplateOptions" @change="applyStageTemplate(stage, stage.stage_key)" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Responsable</label>
            <Multiselect v-model="stage.responsible_user_id" :options="responsibleUserOptions" :searchable="true" />
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <BButton size="sm" variant="outline-danger" @click="removeStage(index)">Quitar</BButton>
          </div>

          <div class="col-md-3">
            <label class="form-label">Fecha inicio</label>
            <BFormInput v-model="stage.start_date" type="date" />
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha término</label>
            <BFormInput v-model="stage.end_date" type="date" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora inicio</label>
            <BFormInput v-model="stage.start_time" type="time" />
          </div>
          <div class="col-md-2">
            <label class="form-label">Hora término</label>
            <BFormInput v-model="stage.end_time" type="time" />
          </div>
          <div class="col-md-1">
            <label class="form-label">Prioridad</label>
            <BFormSelect v-model="stage.priority" :options="priorityOptions" />
          </div>
          <div class="col-md-1">
            <label class="form-label">Estado</label>
            <BFormSelect v-model="stage.status" :options="statusOptions" />
          </div>
        </div>
      </div>
    </BCard>

    <BCard class="mt-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="mb-1">Recordatorios</h5>
          <div class="text-muted small">Puedes combinar avisos previos, el mismo día y posteriores al vencimiento.</div>
        </div>
        <BButton size="sm" variant="outline-primary" @click="addReminder">Agregar recordatorio</BButton>
      </div>

      <div v-for="(reminder, index) in form.reminders" :key="`reminder-${index}`" class="row g-3 align-items-end mb-2">
        <div class="col-md-4">
          <label class="form-label">Tipo</label>
          <BFormSelect v-model="reminder.reminder_type" :options="reminderTypeOptions" />
        </div>
        <div class="col-md-3" v-if="['before', 'after_overdue'].includes(reminder.reminder_type)">
          <label class="form-label">Días</label>
          <BFormInput v-model="reminder.days_before" type="number" min="0" max="365" />
        </div>
        <div class="col-md-3" v-if="reminder.reminder_type === 'fixed_date'">
          <label class="form-label">Fecha específica</label>
          <BFormInput v-model="reminder.reminder_date" type="date" />
        </div>
        <div class="col-md-2">
          <BFormCheckbox v-model="reminder.is_active">Activo</BFormCheckbox>
        </div>
        <div class="col-md-2 text-end">
          <BButton size="sm" variant="outline-danger" @click="removeReminder(index)">Quitar</BButton>
        </div>
      </div>
    </BCard>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <BButton variant="secondary" @click="showModal = false">Cancelar</BButton>
      <BButton variant="primary" :disabled="saving" @click="save">
        {{ saving ? "Guardando..." : "Guardar" }}
      </BButton>
    </div>
  </BModal>
</template>
