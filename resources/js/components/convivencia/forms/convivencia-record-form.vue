<script>
import ConvivenciaRemoteSelect from "../ui/convivencia-remote-select.vue";

const caseTextSuggestions = Object.freeze({
  initial_report: [
    { label: "Observación directa", text: "Hecho observado directamente por personal del establecimiento." },
    { label: "Informa estudiante", text: "Antecedente informado por una estudiante." },
    { label: "Informa persona adulta", text: "Antecedente informado por una persona adulta." },
    { label: "Pendiente de verificar", text: "Información pendiente de verificación." },
  ],
  background: [
    { label: "Sin antecedentes previos", text: "No se identifican antecedentes previos a la fecha." },
    { label: "Situación reiterada", text: "Se registran situaciones similares anteriores." },
    { label: "Registros consultados", text: "Se consultaron los registros institucionales disponibles." },
    { label: "Solicitar información", text: "Se solicitarán antecedentes complementarios." },
  ],
  immediate_measures: [
    { label: "Contención inicial", text: "Se realizó contención inicial." },
    { label: "Separación preventiva", text: "Se separó preventivamente a las personas involucradas." },
    { label: "Aviso a responsable", text: "Se informó al apoderado o adulto responsable." },
    { label: "Atención de salud", text: "Se coordinó atención de salud." },
    { label: "Resguardo de evidencia", text: "Se resguardaron los antecedentes o la evidencia disponible." },
  ],
  safeguarding_measures: [
    { label: "Adulto de acompañamiento", text: "Se designó una persona adulta responsable de acompañamiento." },
    { label: "Supervisión reforzada", text: "Se estableció supervisión reforzada." },
    { label: "Separación de espacios", text: "Se dispuso separación preventiva de espacios." },
    { label: "Resguardo de identidad", text: "Se resguardó la identidad y confidencialidad de las personas involucradas." },
    { label: "Apoyo psicosocial", text: "Se coordinó acompañamiento psicosocial." },
  ],
  internal_notes: [
    { label: "Programar entrevistas", text: "Coordinar entrevistas con las personas involucradas." },
    { label: "Consultar profesor/a jefe", text: "Solicitar antecedentes al profesor o profesora jefe." },
    { label: "Evaluar protocolo RICE", text: "Evaluar la activación del protocolo RICE correspondiente." },
    { label: "Revisar con el equipo", text: "Revisar avances en reunión del equipo de convivencia." },
    { label: "Informar a Dirección", text: "Informar los antecedentes pertinentes a Dirección." },
  ],
  report_text: [
    { label: "Relato directo", text: "La información fue recibida directamente de la persona denunciante." },
    { label: "Antecedentes adjuntos", text: "Se informó la existencia de antecedentes o documentos de respaldo." },
    { label: "Identidad protegida", text: "Se solicita resguardar la identidad de las personas involucradas." },
    { label: "Pendiente de verificar", text: "Los antecedentes se encuentran pendientes de verificación." },
  ],
  motive: [
    { label: "Evaluación especializada", text: "Se solicita evaluación especializada de la situación informada." },
    { label: "Acompañamiento", text: "Se solicita acompañamiento y seguimiento de las personas involucradas." },
    { label: "Activación de red", text: "Se requiere activar la red interna o externa correspondiente." },
    { label: "Medida de protección", text: "Se solicita evaluar medidas de protección y resguardo." },
  ],
  suggested_actions: [
    { label: "Acusar recibo", text: "Confirmar recepción de la derivación." },
    { label: "Contactar responsable", text: "Contactar a la persona responsable o apoderada." },
    { label: "Coordinar entrevista", text: "Coordinar entrevista con las personas pertinentes." },
    { label: "Informar avances", text: "Informar avances al equipo de convivencia." },
  ],
});

export default {
  name: "ConvivenciaRecordForm",
  components: { ConvivenciaRemoteSelect },
  props: {
    section: { type: String, required: true },
    form: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
  },
  emits: ["add", "remove"],
  data() {
    return {
      quickStudentId: null,
      quickStudentOption: null,
      quickStudentResetKey: 0,
      quickStudentPickerVisible: true,
      quickStudentRole: "afectado",
      quickStudentFeedback: null,
      quickProfessionalKey: null,
      quickProfessionalFeedback: null,
      caseTextSuggestions,
    };
  },
  computed: {
    specificObjectivesText: {
      get() { return (this.form.specific_objectives || []).join("\n"); },
      set(value) {
        this.form.specific_objectives = String(value || "").split("\n").map((item) => item.trim()).filter(Boolean);
      },
    },
    caseTypeChoices() {
      return this.withPlaceholder(this.list("caseTypes"), "Selecciona el tipo de expediente");
    },
    classificationChoices() {
      return this.withPlaceholder(this.list("classifications"), "Selecciona una clasificación");
    },
    subclassificationChoices() {
      const selected = Number(this.form.classification_item_id || 0);
      const items = this.list("subclassifications").filter((item) => {
        const parentId = Number(item.parentId || 0);
        return !parentId || parentId === selected;
      });
      return this.withPlaceholder(
        items,
        selected ? "Selecciona una subclasificación (opcional)" : "Selecciona primero una clasificación",
      );
    },
    criticalityChoices() {
      return this.withPlaceholder(this.list("criticalities"), "Selecciona la criticidad");
    },
    selectedClassification() {
      return this.list("classifications").find((item) => Number(item.value) === Number(this.form.classification_item_id)) || null;
    },
    selectedCriticality() {
      return this.list("criticalities").find((item) => Number(item.value) === Number(this.form.criticality_item_id)) || null;
    },
    selectedCaseResponsible() {
      return this.list("caseResponsibleUsers")
        .find((item) => Number(item.value) === Number(this.form.responsible_user_id)) || null;
    },
    classificationProtocolCodes() {
      return this.selectedClassification?.metadata?.rice_protocol_codes || [];
    },
    initialReportLength() {
      return String(this.form.initial_report || "").trim().length;
    },
    quickStudentRoleChoices() {
      return this.list("personRoles").filter((option) => !["profesional_apoyo", "responsable_seguimiento"].includes(option.value));
    },
    involvedPersonRoleChoices() {
      return this.list("personRoles").filter((option) => option.value !== "profesional_apoyo");
    },
    supportProfessionalChoices() {
      const professionals = this.list("supportProfessionals");
      if (!professionals.length) {
        return [{ value: null, text: "No hay profesionales de apoyo disponibles", disabled: true }];
      }

      return [{ value: null, text: "Selecciona un profesional", disabled: true }].concat(professionals);
    },
    supportTeamEntries() {
      return (this.form.people || [])
        .map((person, index) => ({ person, index }))
        .filter(({ person }) => person.role_type === "profesional_apoyo");
    },
    involvedPeopleEntries() {
      return (this.form.people || [])
        .map((person, index) => ({ person, index }))
        .filter(({ person }) => person.role_type !== "profesional_apoyo");
    },
    sociogramQuestionChoices() {
      return [{ value: null, text: "Selecciona una pregunta", disabled: true }].concat((this.form.questions || []).map((question, index) => ({
        value: index + 1,
        text: `${index + 1}. ${question.prompt || "Pregunta sin texto"}${question.active === false ? " · Inactiva" : ""}`,
        disabled: question.active === false,
      })));
    },
    sociogramStudentFilters() {
      return { course_section_id: this.form.course_section_id || undefined, limit: 60 };
    },
    sociogramAnsweredStudents() {
      return new Set((this.form.answers || []).map((answer) => Number(answer.respondent_student_id || 0)).filter(Boolean)).size;
    },
  },
  watch: {
    "form.classification_item_id"(value, previous) {
      if (value === previous || !this.form.subclassification_item_id) return;
      const stillBelongs = this.list("subclassifications").some((item) => (
        Number(item.value) === Number(this.form.subclassification_item_id)
        && (!item.parentId || Number(item.parentId) === Number(value))
      ));
      if (!stillBelongs) this.form.subclassification_item_id = null;
    },
    "form.scope"(value, previous) {
      if (this.section !== "derivaciones" || value === previous) return;
      if (value === "internal") {
        this.form.external_institution_id = null;
        this.form.external_contact_name = "";
        this.form.external_contact_email = "";
        this.form.external_contact_phone = "";
      } else if (value === "external") {
        this.form.destination_department_id = null;
        this.form.destination_user_id = null;
        this.form.destination_staff_id = null;
      }
    },
    "form.course_section_id"(value, previous) {
      if (this.section !== "sociogramas" || !previous || Number(value) === Number(previous) || !this.form.answers?.length) return;
      this.form.answers = [];
    },
  },
  methods: {
    fieldId(name) { return `convivencia-${this.section}-${name}`; },
    list(name) { return this.options[name] || []; },
    withPlaceholder(items, text) {
      return [{ value: null, text, disabled: true }].concat(items || []);
    },
    selectPrimaryStudent(option) {
      if (!option) return;
      if (option.course_section_id) this.form.course_section_id = option.course_section_id;
      if (option.academic_year_id) this.form.academic_year_id = option.academic_year_id;
    },
    selectComplaintStudent(option) {
      if (!option) return;
      if (option.course_section_id) this.form.course_section_id = option.course_section_id;
      if (option.academic_year_id) this.form.academic_year_id = option.academic_year_id;
    },
    selectDerivationStudent(option) {
      if (!option) return;
      if (option.course_section_id) this.form.course_section_id = option.course_section_id;
      if (option.academic_year_id) this.form.academic_year_id = option.academic_year_id;
    },
    selectComplaintPersonStudent(person, option) {
      if (!option) return;
      person.full_name = option.full_name || option.label || person.full_name;
      person.identifier = option.identifier || person.identifier;
      person.person_type = "estudiante";
    },
    handleAnonymousComplaint(value) {
      if (!value) return;
      this.form.complainant_name = "";
      this.form.contact_email = "";
      this.form.contact_phone = "";
    },
    selectCaseResponsible(userId) {
      const responsible = this.list("caseResponsibleUsers")
        .find((item) => Number(item.value) === Number(userId));
      this.form.responsible_staff_id = responsible?.staff_id || null;
    },
    hasCaseSuggestion(field, suggestion) {
      return String(this.form[field] || "").includes(suggestion.text);
    },
    appendCaseSuggestion(field, suggestion) {
      if (this.hasCaseSuggestion(field, suggestion)) return;
      const current = String(this.form[field] || "").trimEnd();
      this.form[field] = `${current ? `${current}\n` : ""}• ${suggestion.text}`;
    },
    selectCasePersonStudent(person, option) {
      if (!option) return;
      person.full_name = option.full_name || option.label || person.full_name;
      person.identifier = option.identifier || person.identifier;
      person.course_section_id = option.course_section_id || person.course_section_id;
    },
    selectQuickStudent(option) {
      this.quickStudentOption = option || null;
      this.quickStudentFeedback = null;
    },
    onQuickStudentChanged(value) {
      if (!value) this.quickStudentOption = null;
      this.quickStudentFeedback = null;
    },
    ensurePeople() {
      if (!Array.isArray(this.form.people)) this.form.people = [];
      return this.form.people;
    },
    addQuickStudent() {
      const option = this.quickStudentOption;
      const studentId = Number(option?.id || option?.value || this.quickStudentId || 0);
      if (!studentId || !option) {
        this.quickStudentFeedback = { type: "error", text: "Busca y selecciona una alumna antes de agregarla." };
        return;
      }

      const people = this.ensurePeople();
      if (people.some((person) => Number(person.student_profile_id) === studentId)) {
        this.quickStudentFeedback = { type: "warning", text: "Esta alumna ya está vinculada al caso." };
        return;
      }

      people.push({
        student_profile_id: studentId,
        user_id: null,
        staff_id: null,
        course_section_id: option.course_section_id || null,
        person_type: "estudiante",
        role_type: this.quickStudentRole || "afectado",
        full_name: option.full_name || option.label,
        identifier: option.identifier || "",
        relationship_label: "",
        contact_reference: "",
        notes: "",
        is_sensitive: Boolean(this.form.is_sensitive),
      });
      this.$refs.quickStudentSelect?.clearSelection?.();
      this.quickStudentPickerVisible = false;
      this.quickStudentId = null;
      this.quickStudentOption = null;
      this.quickStudentResetKey += 1;
      this.quickStudentFeedback = { type: "success", text: "Alumna incorporada a las personas vinculadas." };
      this.$nextTick(() => {
        this.quickStudentId = null;
        this.quickStudentOption = null;
        this.quickStudentResetKey += 1;
        this.quickStudentPickerVisible = true;
      });
    },
    addSupportProfessional() {
      const selected = this.list("supportProfessionals").find((professional) => professional.value === this.quickProfessionalKey);
      if (!selected) {
        this.quickProfessionalFeedback = { type: "error", text: "Selecciona un profesional de apoyo." };
        return;
      }

      const people = this.ensurePeople();
      const duplicate = people.some((person) => person.role_type === "profesional_apoyo" && (
        (selected.staff_id && Number(person.staff_id) === Number(selected.staff_id))
        || (!selected.staff_id && selected.user_id && Number(person.user_id) === Number(selected.user_id))
      ));
      if (duplicate) {
        this.quickProfessionalFeedback = { type: "warning", text: "Este profesional ya forma parte del equipo del caso." };
        return;
      }

      const descriptor = [selected.area_name, selected.professional_role_name]
        .filter(Boolean)
        .filter((value, index, values) => values.indexOf(value) === index)
        .join(" · ");
      people.push({
        student_profile_id: null,
        user_id: selected.user_id || null,
        staff_id: selected.staff_id || null,
        course_section_id: null,
        person_type: "funcionario",
        role_type: "profesional_apoyo",
        full_name: selected.full_name,
        identifier: "",
        relationship_label: descriptor,
        contact_reference: "",
        notes: "",
        is_sensitive: false,
      });
      this.quickProfessionalKey = null;
      this.quickProfessionalFeedback = { type: "success", text: "Profesional agregado al equipo de apoyo." };
    },
    sociogramQuestionFor(answer) {
      return (this.form.questions || [])[Number(answer?.question_order || 0) - 1] || null;
    },
    syncSociogramAnswerType(answer) {
      const question = this.sociogramQuestionFor(answer);
      answer.selection_type = question?.selection_type || "positiva";
    },
    syncSociogramQuestionAnswers(questionIndex) {
      (this.form.answers || []).forEach((answer) => {
        if (Number(answer.question_order) === questionIndex + 1) this.syncSociogramAnswerType(answer);
      });
    },
    addSociogramAnswer(questionOrder = 1, respondentId = null) {
      if (!Array.isArray(this.form.answers)) this.form.answers = [];
      const question = (this.form.questions || [])[questionOrder - 1];
      this.form.answers.push({
        question_order: questionOrder,
        respondent_student_id: respondentId,
        selected_student_id: null,
        selection_type: question?.selection_type || "positiva",
        notes: "",
      });
    },
  },
};
</script>

<template>
  <div class="convivencia-record-form">
    <template v-if="section === 'planes'">
      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-info-circle"></i>Identificación y vigencia</legend>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label" :for="fieldId('year')">Año académico</label><BFormSelect :id="fieldId('year')" v-model="form.academic_year_id" :options="list('academicYears')" required /></div>
          <div class="col-md-4"><label class="form-label" :for="fieldId('responsible')">Responsable</label><BFormSelect :id="fieldId('responsible')" v-model="form.responsible_user_id" :options="list('users')" required /></div>
          <div class="col-md-4"><label class="form-label" :for="fieldId('status')">Estado</label><BFormSelect :id="fieldId('status')" v-model="form.status" :options="list('planStatuses')" required /></div>
          <div class="col-md-6"><label class="form-label" :for="fieldId('name')">Nombre del plan</label><BFormInput :id="fieldId('name')" v-model="form.name" required /></div>
          <div class="col-md-3"><label class="form-label" :for="fieldId('start')">Inicio</label><BFormInput :id="fieldId('start')" v-model="form.starts_on" type="date" /></div>
          <div class="col-md-3"><label class="form-label" :for="fieldId('end')">Término</label><BFormInput :id="fieldId('end')" v-model="form.ends_on" type="date" /></div>
          <div class="col-12"><label class="form-label" :for="fieldId('objective')">Objetivo general</label><BFormTextarea :id="fieldId('objective')" v-model="form.general_objective" rows="3" /></div>
          <div class="col-md-6"><label class="form-label">Objetivos específicos <small>(uno por línea)</small></label><BFormTextarea v-model="specificObjectivesText" rows="3" /></div>
          <div class="col-md-6"><label class="form-label">Recursos requeridos</label><BFormTextarea v-model="form.resources_required" rows="3" /></div>
          <div class="col-md-6"><label class="form-label">Resumen de indicadores</label><BFormTextarea v-model="form.indicators_summary" rows="2" /></div>
          <div class="col-md-6"><label class="form-label">Medios de verificación</label><BFormTextarea v-model="form.verification_means_summary" rows="2" /></div>
          <div class="col-md-3"><label class="form-label">Avance general (%)</label><BFormInput v-model="form.advance_percentage" type="number" min="0" max="100" /></div>
          <div class="col-md-9 d-flex align-items-end"><BFormCheckbox v-model="form.is_sensitive">Plan con información sensible</BFormCheckbox></div>
          <div class="col-12"><label class="form-label" :for="fieldId('notes')">Observaciones</label><BFormTextarea :id="fieldId('notes')" v-model="form.observations" rows="2" /></div>
          <div class="col-12"><label class="form-label">Evaluación final</label><BFormTextarea v-model="form.final_evaluation" rows="2" /></div>
        </div>
      </fieldset>
      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-list-check"></i>Acciones del plan <button type="button" class="btn btn-sm btn-outline-primary" @click="$emit('add', 'actions')"><i class="bx bx-plus"></i>Agregar acción</button></legend>
        <div v-if="!form.actions?.length" class="convivencia-record-form__empty">Agrega acciones con fechas, responsables y objetivos verificables.</div>
        <article v-for="(action, index) in form.actions || []" :key="index" class="convivencia-record-form__repeat">
          <div class="convivencia-record-form__repeat-head"><b>Acción {{ index + 1 }}</b><button type="button" class="btn btn-sm btn-outline-danger" :aria-label="`Quitar acción ${index + 1}`" @click="$emit('remove', 'actions', index)"><i class="bx bx-trash"></i>Quitar</button></div>
          <div class="row g-2">
            <div class="col-md-3"><label class="form-label">Tipo</label><BFormSelect v-model="action.action_type" :options="list('planActionTypes')" /></div>
            <div class="col-md-5"><label class="form-label">Título</label><BFormInput v-model="action.title" /></div>
            <div class="col-md-2"><label class="form-label">Inicio</label><BFormInput v-model="action.starts_on" type="date" /></div>
            <div class="col-md-2"><label class="form-label">Término</label><BFormInput v-model="action.ends_on" type="date" /></div>
            <div class="col-md-7"><label class="form-label">Descripción</label><BFormTextarea v-model="action.description" rows="2" /></div>
            <div class="col-md-3"><label class="form-label">Estado</label><BFormSelect v-model="action.status" :options="list('planStatuses')" /></div>
            <div class="col-md-2"><label class="form-label">Avance (%)</label><BFormInput v-model="action.advance_percentage" type="number" min="0" max="100" /></div>
            <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="action.responsible_user_id" :options="list('usersOptional')" /></div>
            <div class="col-md-4"><label class="form-label">Funcionario responsable</label><BFormSelect v-model="action.responsible_staff_id" :options="list('staffOptional')" /></div>
            <div class="col-md-4"><label class="form-label">Departamento responsable</label><BFormSelect v-model="action.responsible_department_id" :options="list('departmentsOptional')" /></div>
            <div class="col-md-4"><label class="form-label">Dimensión</label><BFormSelect v-model="action.dimension_item_id" :options="list('planDimensions')" /></div>
            <div class="col-md-4"><label class="form-label">Etiqueta de dimensión</label><BFormInput v-model="action.dimension_label" /></div>
            <div class="col-md-4"><label class="form-label">Responsable descriptivo</label><BFormInput v-model="action.responsible_label" /></div>
            <div class="col-md-6"><label class="form-label">Recursos de la acción</label><BFormTextarea v-model="action.required_resources" rows="2" /></div>
            <div class="col-md-6"><label class="form-label">Indicador</label><BFormTextarea v-model="action.indicator_summary" rows="2" /></div>
            <div class="col-md-6"><label class="form-label">Medios de verificación</label><BFormTextarea v-model="action.verification_means" rows="2" /></div>
            <div class="col-md-6"><label class="form-label">Evidencia</label><BFormTextarea v-model="action.evidence_summary" rows="2" /></div>
            <div class="col-12"><label class="form-label">Observaciones de la acción</label><BFormTextarea v-model="action.observations" rows="2" /></div>
          </div>
        </article>
      </fieldset>
    </template>

    <template v-else-if="section === 'casos'">
      <div class="case-intake-guide">
        <div class="case-intake-guide__icon"><i class="bx bx-shield-quarter"></i></div>
        <div>
          <b>Apertura guiada y trazable</b>
          <p>Registra el contexto, clasifica según el RICE y deja constancia de los resguardos iniciales. Los campos con <span>*</span> son obligatorios.</p>
        </div>
        <div class="case-intake-guide__steps" aria-label="Etapas del formulario">
          <span><b>1</b> Contexto</span><span><b>2</b> Clasificación</span><span><b>3</b> Resguardo</span>
        </div>
      </div>

      <fieldset class="convivencia-record-form__section convivencia-record-form__section--accent">
        <legend><span class="case-step">1</span><i class="bx bx-folder-open"></i>Contexto del expediente</legend>
        <p class="convivencia-record-form__section-help">El caso puede involucrar a estudiantes, funcionarios, apoderados o personas externas. El estudiante y el curso son opcionales.</p>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label" :for="fieldId('year')">Año académico <span class="required-mark">*</span></label><BFormSelect :id="fieldId('year')" v-model="form.academic_year_id" :options="list('academicYears')" required /></div>
          <div class="col-md-4"><label class="form-label" :for="fieldId('case-type')">Tipo de caso <span class="required-mark">*</span></label><BFormSelect :id="fieldId('case-type')" v-model="form.case_type_item_id" :options="caseTypeChoices" required /></div>
          <div class="col-md-5 case-responsible-field">
            <label class="form-label" :for="fieldId('responsible')">Responsable de la gestión <span class="required-mark">*</span></label>
            <BFormSelect :id="fieldId('responsible')" v-model="form.responsible_user_id" :options="list('caseResponsibleUsers')" required @change="selectCaseResponsible" />
            <small v-if="selectedCaseResponsible?.staff_name" class="case-responsible-field__linked"><i class="bx bx-badge-check"></i>Ficha institucional vinculada automáticamente: {{ selectedCaseResponsible.staff_name }}</small>
            <small v-else class="field-help">Persona encargada de coordinar, actualizar y dar seguimiento al expediente.</small>
          </div>
          <div class="col-md-8">
            <label class="form-label" :for="fieldId('student')">Estudiante principal <small>(opcional)</small></label>
            <ConvivenciaRemoteSelect
              :id="fieldId('student')"
              v-model="form.student_profile_id"
              type="students"
              placeholder="Escribe nombre o RUT para buscar"
              aria-label="Buscar estudiante principal del caso"
              @select="selectPrimaryStudent"
            />
            <small class="field-help">Al seleccionarlo, se completa su curso actual cuando está disponible.</small>
          </div>
          <div class="col-md-4"><label class="form-label" :for="fieldId('course')">Curso <small>(opcional)</small></label><BFormSelect :id="fieldId('course')" v-model="form.course_section_id" :options="list('coursesOptional')" /></div>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section convivencia-record-form__section--classification">
        <legend><span class="case-step">2</span><i class="bx bx-category-alt"></i>Clasificación RICE y prioridad</legend>
        <p class="convivencia-record-form__section-help">La subclasificación se filtra según la categoría principal. La criticidad indica la urgencia de respuesta; no determina por sí sola una sanción.</p>
        <div class="row g-3">
          <div class="col-lg-4">
            <label class="form-label" :for="fieldId('classification')">Clasificación <span class="required-mark">*</span></label>
            <BFormSelect :id="fieldId('classification')" v-model="form.classification_item_id" :options="classificationChoices" required />
          </div>
          <div class="col-lg-4">
            <label class="form-label" :for="fieldId('subclassification')">Subclasificación <small>(opcional)</small></label>
            <BFormSelect :id="fieldId('subclassification')" v-model="form.subclassification_item_id" :options="subclassificationChoices" :disabled="!form.classification_item_id" />
            <small class="field-help">{{ form.classification_item_id ? `${Math.max(0, subclassificationChoices.length - 1)} opciones relacionadas` : "Disponible después de clasificar" }}</small>
          </div>
          <div class="col-lg-4">
            <label class="form-label" :for="fieldId('criticality')">Criticidad <span class="required-mark">*</span></label>
            <BFormSelect :id="fieldId('criticality')" v-model="form.criticality_item_id" :options="criticalityChoices" required />
          </div>
        </div>
        <div v-if="selectedClassification || selectedCriticality" class="case-classification-summary">
          <div v-if="selectedClassification">
            <span class="case-classification-summary__dot" :style="{ backgroundColor: selectedClassification.color || '#4f63d9' }"></span>
            <div><b>{{ selectedClassification.text }}</b><small>{{ selectedClassification.description }}</small><em v-if="classificationProtocolCodes.length">Protocolos relacionados: {{ classificationProtocolCodes.join(', ') }}</em></div>
          </div>
          <div v-if="selectedCriticality" class="case-criticality-guidance" :style="{ '--criticality-color': selectedCriticality.color || '#6c757d' }">
            <i class="bx bx-pulse"></i><div><b>Nivel {{ selectedCriticality.text }}</b><small>{{ selectedCriticality.description }}</small><em v-if="selectedCriticality.metadata?.response">Respuesta sugerida: {{ selectedCriticality.metadata.response }}</em></div>
          </div>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section">
        <legend><span class="case-step">3</span><i class="bx bx-time-five"></i>Hecho, apertura y seguimiento</legend>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label" :for="fieldId('opened-at')">Fecha y hora de apertura <span class="required-mark">*</span></label><BFormInput :id="fieldId('opened-at')" v-model="form.opened_at" type="datetime-local" required /></div>
          <div class="col-md-3"><label class="form-label" :for="fieldId('happened-at')">Fecha y hora del hecho</label><BFormInput :id="fieldId('happened-at')" v-model="form.happened_at" type="datetime-local" :max="form.opened_at || undefined" /></div>
          <div class="col-md-3"><label class="form-label" :for="fieldId('origin')">Origen <span class="required-mark">*</span></label><BFormSelect :id="fieldId('origin')" v-model="form.origin" :options="list('caseOrigins')" required /></div>
          <div class="col-md-3"><label class="form-label" :for="fieldId('follow-up')">Próximo seguimiento</label><BFormInput :id="fieldId('follow-up')" v-model="form.follow_up_due_at" type="datetime-local" :min="form.opened_at || undefined" /></div>
          <div class="col-md-8"><label class="form-label" :for="fieldId('place')">Lugar o canal donde ocurrió</label><BFormInput :id="fieldId('place')" v-model="form.place" maxlength="160" placeholder="Ej.: sala 4°A, patio, mensajería o actividad externa" /></div>
          <div v-if="form.id" class="col-md-4"><label class="form-label" :for="fieldId('status')">Estado operativo</label><BFormSelect :id="fieldId('status')" v-model="form.status" :options="list('caseStatuses')" /></div>
          <div v-else class="col-md-4"><label class="form-label">Estado inicial</label><div class="case-readonly-status"><i class="bx bx-radio-circle-marked"></i> Abierto</div></div>
          <div class="col-12">
            <div class="case-sensitive-switch">
              <BFormCheckbox v-model="form.is_sensitive" switch><b>Expediente con información sensible</b></BFormCheckbox>
              <small>Actívalo si contiene antecedentes de salud, vulneración, vida privada u otra información que requiera acceso reforzado.</small>
            </div>
          </div>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-detail"></i>Relato, antecedentes y resguardos</legend>
        <div class="row g-3">
          <div class="col-12">
            <div class="d-flex justify-content-between gap-2"><label class="form-label" :for="fieldId('initial-report')">Relato inicial objetivo <span class="required-mark">*</span></label><small class="field-counter" :class="{ 'is-short': initialReportLength < 10 }">{{ initialReportLength }} caracteres</small></div>
            <BFormTextarea :id="fieldId('initial-report')" v-model="form.initial_report" rows="4" minlength="10" required placeholder="Describe hechos observables: qué ocurrió, quién informó, participantes, secuencia y antecedentes disponibles. Evita conclusiones no verificadas." />
            <div class="case-text-assist" aria-label="Opciones para agregar al relato inicial"><span><i class="bx bx-plus-circle"></i>Agregar al texto</span><button v-for="suggestion in caseTextSuggestions.initial_report" :key="suggestion.label" type="button" :class="{ 'is-added': hasCaseSuggestion('initial_report', suggestion) }" :disabled="hasCaseSuggestion('initial_report', suggestion)" @click="appendCaseSuggestion('initial_report', suggestion)"><i class="bx" :class="hasCaseSuggestion('initial_report', suggestion) ? 'bx-check' : 'bx-plus'"></i>{{ suggestion.label }}</button></div>
          </div>
          <div class="col-md-6"><label class="form-label" :for="fieldId('background')">Antecedentes relevantes</label><BFormTextarea :id="fieldId('background')" v-model="form.background" rows="3" placeholder="Registros previos, reiteración, contexto y fuentes consultadas" /><div class="case-text-assist" aria-label="Opciones para agregar a los antecedentes"><span><i class="bx bx-plus-circle"></i>Agregar al texto</span><button v-for="suggestion in caseTextSuggestions.background" :key="suggestion.label" type="button" :class="{ 'is-added': hasCaseSuggestion('background', suggestion) }" :disabled="hasCaseSuggestion('background', suggestion)" @click="appendCaseSuggestion('background', suggestion)"><i class="bx" :class="hasCaseSuggestion('background', suggestion) ? 'bx-check' : 'bx-plus'"></i>{{ suggestion.label }}</button></div></div>
          <div class="col-md-6"><label class="form-label" :for="fieldId('immediate-measures')">Acciones inmediatas realizadas</label><BFormTextarea :id="fieldId('immediate-measures')" v-model="form.immediate_measures" rows="3" placeholder="Contención, separación preventiva, atención de salud, aviso a responsables u otras acciones" /><div class="case-text-assist" aria-label="Opciones para agregar a las acciones inmediatas"><span><i class="bx bx-plus-circle"></i>Agregar al texto</span><button v-for="suggestion in caseTextSuggestions.immediate_measures" :key="suggestion.label" type="button" :class="{ 'is-added': hasCaseSuggestion('immediate_measures', suggestion) }" :disabled="hasCaseSuggestion('immediate_measures', suggestion)" @click="appendCaseSuggestion('immediate_measures', suggestion)"><i class="bx" :class="hasCaseSuggestion('immediate_measures', suggestion) ? 'bx-check' : 'bx-plus'"></i>{{ suggestion.label }}</button></div></div>
          <div class="col-12">
            <label class="form-label" :for="fieldId('safeguarding')">Medidas de resguardo adoptadas</label>
            <BFormTextarea :id="fieldId('safeguarding')" v-model="form.safeguarding_measures" rows="3" placeholder="Indica cómo se protege a las personas involucradas, responsable y duración de cada medida" />
            <div class="case-text-assist" aria-label="Opciones para agregar a las medidas de resguardo"><span><i class="bx bx-plus-circle"></i>Agregar al texto</span><button v-for="suggestion in caseTextSuggestions.safeguarding_measures" :key="suggestion.label" type="button" :class="{ 'is-added': hasCaseSuggestion('safeguarding_measures', suggestion) }" :disabled="hasCaseSuggestion('safeguarding_measures', suggestion)" @click="appendCaseSuggestion('safeguarding_measures', suggestion)"><i class="bx" :class="hasCaseSuggestion('safeguarding_measures', suggestion) ? 'bx-check' : 'bx-plus'"></i>{{ suggestion.label }}</button></div>
            <small v-if="selectedCriticality?.metadata?.requires_immediate_safeguard" class="field-alert"><i class="bx bx-shield-quarter"></i>Para criticidad {{ selectedCriticality.text.toLowerCase() }}, deja constancia de los resguardos inmediatos y evalúa la activación del protocolo relacionado.</small>
          </div>
          <div class="col-12"><label class="form-label" :for="fieldId('internal-notes')">Notas internas de coordinación</label><BFormTextarea :id="fieldId('internal-notes')" v-model="form.internal_notes" rows="2" placeholder="Información de uso interno que no forma parte del relato inicial" /><div class="case-text-assist" aria-label="Opciones para agregar a las notas internas"><span><i class="bx bx-plus-circle"></i>Agregar al texto</span><button v-for="suggestion in caseTextSuggestions.internal_notes" :key="suggestion.label" type="button" :class="{ 'is-added': hasCaseSuggestion('internal_notes', suggestion) }" :disabled="hasCaseSuggestion('internal_notes', suggestion)" @click="appendCaseSuggestion('internal_notes', suggestion)"><i class="bx" :class="hasCaseSuggestion('internal_notes', suggestion) ? 'bx-check' : 'bx-plus'"></i>{{ suggestion.label }}</button></div></div>
          <template v-if="form.id">
            <div class="col-md-6"><label class="form-label" :for="fieldId('resolution')">Resolución</label><BFormTextarea :id="fieldId('resolution')" v-model="form.resolution" rows="3" /></div>
            <div class="col-md-6"><label class="form-label" :for="fieldId('conclusion')">Conclusión</label><BFormTextarea :id="fieldId('conclusion')" v-model="form.conclusion" rows="3" /></div>
          </template>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section convivencia-record-form__section--support">
        <legend><i class="bx bx-plus-medical"></i>Profesionales de apoyo</legend>
        <p class="convivencia-record-form__section-help">Conforma el equipo que acompañará el caso. Se utilizan las fichas vigentes de Apoyo Profesional y el directorio institucional autorizado.</p>
        <div class="case-support-picker">
          <div>
            <label class="form-label" :for="fieldId('support-professional')">Profesional</label>
            <BFormSelect
              :id="fieldId('support-professional')"
              v-model="quickProfessionalKey"
              :options="supportProfessionalChoices"
              :disabled="!list('supportProfessionals').length"
            />
          </div>
          <button
            type="button"
            class="btn btn-primary case-support-picker__button"
            :disabled="!quickProfessionalKey"
            @click="addSupportProfessional"
          ><i class="bx bx-user-plus"></i>Agregar al equipo</button>
        </div>
        <div v-if="quickProfessionalFeedback" class="case-quick-feedback" :class="`is-${quickProfessionalFeedback.type}`" role="status">{{ quickProfessionalFeedback.text }}</div>
        <div v-if="supportTeamEntries.length" class="case-support-team">
          <article v-for="entry in supportTeamEntries" :key="`support-${entry.index}`" class="case-support-member">
            <span class="case-support-member__icon"><i class="bx bx-support"></i></span>
            <div><b>{{ entry.person.full_name }}</b><small>{{ entry.person.relationship_label || "Profesional de apoyo" }}</small></div>
            <button type="button" class="btn" :aria-label="`Quitar a ${entry.person.full_name} del equipo de apoyo`" @click="$emit('remove', 'people', entry.index)"><i class="bx bx-x"></i></button>
          </article>
        </div>
        <div v-else class="convivencia-record-form__empty case-support-empty"><i class="bx bx-support"></i><b>Equipo aún sin profesionales</b><span>Puedes incorporarlos ahora o completar el equipo durante el seguimiento.</span></div>
      </fieldset>

      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-group"></i>Personas involucradas <button type="button" class="btn btn-sm btn-outline-primary" @click="$emit('add', 'people')"><i class="bx bx-plus"></i>Agregar otra persona</button></legend>
        <p class="convivencia-record-form__section-help">Añade rápidamente alumnas desde el registro institucional. Para apoderados, funcionarios o personas externas utiliza “Agregar otra persona”.</p>
        <div class="case-quick-students">
          <div class="case-quick-students__heading">
            <span><i class="bx bxs-zap"></i></span>
            <div><b>Incorporación rápida de alumnas</b><small>Busca, define su rol y agrégala sin completar datos manualmente.</small></div>
          </div>
          <div class="case-quick-students__controls">
            <div>
              <label class="form-label">Alumna</label>
              <ConvivenciaRemoteSelect
                v-if="quickStudentPickerVisible"
                :key="`quick-student-${quickStudentResetKey}`"
                ref="quickStudentSelect"
                v-model="quickStudentId"
                type="students"
                placeholder="Buscar alumna por nombre o RUT"
                aria-label="Buscar alumna para agregar rápidamente"
                @change="onQuickStudentChanged"
                @select="selectQuickStudent"
              />
            </div>
            <div>
              <label class="form-label" :for="fieldId('quick-student-role')">Rol en el caso</label>
              <BFormSelect :id="fieldId('quick-student-role')" v-model="quickStudentRole" :options="quickStudentRoleChoices" />
            </div>
            <button type="button" class="btn btn-primary" :disabled="!quickStudentOption" @click="addQuickStudent"><i class="bx bx-plus"></i>Agregar alumna</button>
          </div>
          <div v-if="quickStudentFeedback" class="case-quick-feedback" :class="`is-${quickStudentFeedback.type}`" role="status">{{ quickStudentFeedback.text }}</div>
        </div>
        <div v-if="!involvedPeopleEntries.length" class="convivencia-record-form__empty"><i class="bx bx-user-plus"></i><b>Aún no agregas personas</b><span>El caso se puede abrir sin estudiante y completar esta sección cuando existan antecedentes suficientes.</span></div>
        <article v-for="(entry, displayIndex) in involvedPeopleEntries" :key="`person-${entry.index}`" class="convivencia-record-form__repeat case-person-card">
          <div class="convivencia-record-form__repeat-head"><b><span>{{ displayIndex + 1 }}</span> Persona involucrada</b><button type="button" class="btn btn-sm btn-outline-danger" :aria-label="`Quitar persona ${displayIndex + 1}`" @click="$emit('remove', 'people', entry.index)"><i class="bx bx-trash"></i>Quitar</button></div>
          <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Tipo <span class="required-mark">*</span></label><BFormSelect v-model="entry.person.person_type" :options="list('personTypes')" required /></div>
            <div class="col-md-3"><label class="form-label">Rol en el caso <span class="required-mark">*</span></label><BFormSelect v-model="entry.person.role_type" :options="involvedPersonRoleChoices" required /></div>
            <div v-if="entry.person.person_type === 'estudiante'" class="col-md-6"><label class="form-label">Vincular estudiante</label><ConvivenciaRemoteSelect v-model="entry.person.student_profile_id" type="students" placeholder="Buscar por nombre o RUT" aria-label="Buscar estudiante involucrado" @select="(option) => selectCasePersonStudent(entry.person, option)" /></div>
            <div v-if="entry.person.person_type === 'funcionario'" class="col-md-3"><label class="form-label">Usuario vinculado</label><BFormSelect v-model="entry.person.user_id" :options="list('staffUsersOptional')" /></div>
            <div v-if="entry.person.person_type === 'funcionario'" class="col-md-3"><label class="form-label">Funcionario vinculado</label><BFormSelect v-model="entry.person.staff_id" :options="list('staffOptional')" /></div>
            <div class="col-md-5"><label class="form-label">Nombre completo <span class="required-mark">*</span></label><BFormInput v-model="entry.person.full_name" maxlength="191" required /></div>
            <div class="col-md-3"><label class="form-label">RUT u otro identificador</label><BFormInput v-model="entry.person.identifier" maxlength="80" /></div>
            <div class="col-md-4"><label class="form-label">Curso vinculado</label><BFormSelect v-model="entry.person.course_section_id" :options="list('coursesOptional')" /></div>
            <div class="col-md-4"><label class="form-label">Relación o vínculo</label><BFormInput v-model="entry.person.relationship_label" maxlength="120" placeholder="Ej.: apoderada, docente jefe, compañera" /></div>
            <div class="col-md-4"><label class="form-label">Contacto de referencia</label><BFormInput v-model="entry.person.contact_reference" maxlength="191" placeholder="Teléfono, correo u otro canal" /></div>
            <div class="col-md-8"><label class="form-label">Notas sobre su participación</label><BFormTextarea v-model="entry.person.notes" rows="2" /></div>
            <div class="col-md-4 d-flex align-items-end"><BFormCheckbox v-model="entry.person.is_sensitive" switch>Datos sensibles de esta persona</BFormCheckbox></div>
          </div>
        </article>
      </fieldset>
    </template>

    <template v-else-if="section === 'denuncias'">
      <div class="record-kind-guide record-kind-guide--complaint">
        <span><i class="bx bx-message-square-error"></i></span>
        <div><b>Recepción de denuncia</b><p>Registra la información recibida sin alterar el relato. Luego podrás revisar su admisibilidad o convertirla en caso.</p></div>
        <em>Folio automático</em>
      </div>

      <fieldset class="convivencia-record-form__section convivencia-record-form__section--accent">
        <legend><span class="case-step">1</span><i class="bx bx-link-alt"></i>Contexto y vinculación</legend>
        <p class="convivencia-record-form__section-help">Puedes asociarla a un caso existente o registrarla como antecedente independiente. Estudiante y curso son opcionales.</p>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Año académico</label><BFormSelect v-model="form.academic_year_id" :options="list('academicYears')" /></div>
          <div v-if="options.capabilities?.can_view_cases === true" class="col-md-4"><label class="form-label">Caso existente <small>(opcional)</small></label><ConvivenciaRemoteSelect v-model="form.case_id" type="cases" placeholder="Buscar por folio o estudiante" aria-label="Buscar caso asociado a la denuncia" /></div>
          <div :class="options.capabilities?.can_view_cases === true ? 'col-md-5' : 'col-md-9'"><label class="form-label">Estudiante afectado <small>(opcional)</small></label><ConvivenciaRemoteSelect v-model="form.affected_student_id" type="students" placeholder="Buscar por nombre o RUT" aria-label="Buscar estudiante afectado" @select="selectComplaintStudent" /></div>
          <div class="col-md-4"><label class="form-label">Curso <small>(opcional)</small></label><BFormSelect v-model="form.course_section_id" :options="list('coursesOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Tipo de situación <small>(opcional)</small></label><BFormSelect v-model="form.situation_type_item_id" :options="[{ value: null, text: 'Pendiente de clasificar' }].concat(list('situationTypes'))" /></div>
          <div class="col-md-4"><label class="form-label">Lugar</label><BFormInput v-model="form.place" /></div>
          <div class="col-md-6"><label class="form-label">Fecha y hora del hecho</label><BFormInput v-model="form.happened_at" type="datetime-local" /></div>
          <div class="col-md-6"><label class="form-label">Fecha y hora de recepción</label><BFormInput v-model="form.received_at" type="datetime-local" /></div>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section">
        <legend><span class="case-step">2</span><i class="bx bx-user-voice"></i>Persona denunciante y recepción</legend>
        <div class="complaint-identity-toggle"><BFormCheckbox v-model="form.is_anonymous" switch @update:model-value="handleAnonymousComplaint"><b>Denuncia anónima</b></BFormCheckbox><small>Al activarla no se guardarán nombre ni datos de contacto.</small></div>
        <div class="row g-3 mt-0">
          <div class="col-md-4"><label class="form-label">Tipo de denunciante <span class="required-mark">*</span></label><BFormSelect v-model="form.complainant_type" :options="list('complaintTypes')" required /></div>
          <template v-if="!form.is_anonymous">
            <div class="col-md-4"><label class="form-label">Nombre del denunciante</label><BFormInput v-model="form.complainant_name" maxlength="191" autocomplete="name" /></div>
            <div class="col-md-4"><label class="form-label">Teléfono</label><BFormInput v-model="form.contact_phone" type="tel" maxlength="80" autocomplete="tel" /></div>
            <div class="col-md-6"><label class="form-label">Correo electrónico</label><BFormInput v-model="form.contact_email" type="email" maxlength="191" autocomplete="email" /></div>
          </template>
          <div class="col-md-6"><label class="form-label">Responsable de revisión</label><BFormSelect v-model="form.responsible_user_id" :options="list('usersOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Estado <span class="required-mark">*</span></label><BFormSelect v-model="form.status" :options="list('complaintStatuses')" required /></div>
          <div class="col-md-8 complaint-checks"><BFormCheckbox v-model="form.truth_declaration_accepted">Se dejó constancia de la declaración de veracidad</BFormCheckbox><BFormCheckbox v-model="form.is_sensitive">Contiene información sensible</BFormCheckbox></div>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section">
        <legend><span class="case-step">3</span><i class="bx bx-detail"></i>Relato y admisibilidad</legend>
        <div class="row g-3">
          <div class="col-12"><label class="form-label">Relato recibido <span class="required-mark">*</span></label><BFormTextarea v-model="form.report_text" rows="5" minlength="10" required placeholder="Registra hechos, personas mencionadas, fechas y contexto usando las palabras de quien denuncia." /><div class="case-text-assist"><span><i class="bx bx-plus-circle"></i>Agregar al texto</span><button v-for="suggestion in caseTextSuggestions.report_text" :key="suggestion.label" type="button" :class="{ 'is-added': hasCaseSuggestion('report_text', suggestion) }" :disabled="hasCaseSuggestion('report_text', suggestion)" @click="appendCaseSuggestion('report_text', suggestion)"><i class="bx" :class="hasCaseSuggestion('report_text', suggestion) ? 'bx-check' : 'bx-plus'"></i>{{ suggestion.label }}</button></div></div>
          <div class="col-12"><label class="form-label">Resultado de admisibilidad</label><BFormTextarea v-model="form.admissibility_result" rows="3" placeholder="Fundamentos de admisión, inadmisión o antecedentes pendientes." /></div>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-group"></i>Otras personas involucradas <button type="button" class="btn btn-sm btn-outline-primary" @click="$emit('add', 'involved_snapshot')"><i class="bx bx-plus"></i>Agregar persona</button></legend>
        <div v-if="!form.involved_snapshot?.length" class="convivencia-record-form__empty">No se registraron otras personas en la denuncia.</div>
        <article v-for="(person, index) in form.involved_snapshot || []" :key="index" class="convivencia-record-form__repeat">
          <div class="convivencia-record-form__repeat-head"><b>Persona {{ index + 1 }}</b><button type="button" class="btn btn-sm btn-outline-danger" @click="$emit('remove', 'involved_snapshot', index)"><i class="bx bx-trash"></i>Quitar</button></div>
          <div class="row g-2">
            <div class="col-md-5"><label class="form-label">Buscar estudiante <small>(opcional)</small></label><ConvivenciaRemoteSelect v-model="person.student_profile_id" type="students" placeholder="Buscar y completar datos" aria-label="Buscar estudiante involucrado en la denuncia" @select="(option) => selectComplaintPersonStudent(person, option)" /></div>
            <div class="col-md-3"><label class="form-label">Tipo de persona</label><BFormSelect v-model="person.person_type" :options="list('personTypes')" /></div>
            <div class="col-md-4"><label class="form-label">Rol en los hechos</label><BFormSelect v-model="person.role_type" :options="list('personRoles')" /></div>
            <div class="col-md-5"><label class="form-label">Nombre completo <span class="required-mark">*</span></label><BFormInput v-model="person.full_name" maxlength="191" required /></div>
            <div class="col-md-3"><label class="form-label">RUT u otro identificador</label><BFormInput v-model="person.identifier" maxlength="80" /></div>
            <div class="col-md-4"><label class="form-label">Contacto de referencia</label><BFormInput v-model="person.contact_reference" maxlength="191" /></div>
          </div>
        </article>
      </fieldset>
    </template>

    <template v-else-if="section === 'derivaciones'">
      <div class="record-kind-guide record-kind-guide--derivation">
        <span><i class="bx bx-transfer-alt"></i></span>
        <div><b>Derivación con seguimiento</b><p>Define origen, destino, prioridad y plazo. Los campos cambian automáticamente según sea interna o externa.</p></div>
        <em>{{ form.scope === "external" ? "Red externa" : "Equipo interno" }}</em>
      </div>

      <fieldset class="convivencia-record-form__section convivencia-record-form__section--accent">
        <legend><span class="case-step">1</span><i class="bx bx-link-alt"></i>Origen y contexto</legend>
        <div class="row g-3">
          <div v-if="options.capabilities?.can_view_cases === true" class="col-md-4"><label class="form-label">Caso asociado</label><ConvivenciaRemoteSelect v-model="form.case_id" type="cases" placeholder="Buscar caso por folio" aria-label="Buscar caso para la derivación" /></div>
          <div class="col-md-4"><label class="form-label">Estudiante <small>(opcional)</small></label><ConvivenciaRemoteSelect v-model="form.student_profile_id" type="students" placeholder="Buscar por nombre o RUT" aria-label="Buscar estudiante para la derivación" @select="selectDerivationStudent" /></div>
          <div :class="options.capabilities?.can_view_cases === true ? 'col-md-4' : 'col-md-8'"><label class="form-label">Curso</label><BFormSelect v-model="form.course_section_id" :options="list('coursesOptional')" /></div>
          <div class="col-md-3"><label class="form-label">Año académico</label><BFormSelect v-model="form.academic_year_id" :options="list('academicYears')" /></div>
          <div class="col-md-5"><label class="form-label">Responsable de seguimiento</label><BFormSelect v-model="form.responsible_user_id" :options="list('usersOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Alcance <span class="required-mark">*</span></label><BFormSelect v-model="form.scope" :options="list('derivationScopes')" required /></div>
          <div class="col-md-4"><label class="form-label">Prioridad <span class="required-mark">*</span></label><BFormSelect v-model="form.priority_level" :options="list('derivationPriorities')" required /></div>
          <div class="col-md-4"><label class="form-label">Confidencialidad <span class="required-mark">*</span></label><BFormSelect v-model="form.confidentiality_level" :options="list('confidentialityLevels')" required /></div>
          <div class="col-md-4"><label class="form-label">Estado <span class="required-mark">*</span></label><BFormSelect v-model="form.status" :options="list('derivationStatuses')" required /></div>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section">
        <legend><span class="case-step">2</span><i class="bx bx-time-five"></i>Fechas y control de respuesta</legend>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Fecha de derivación <span class="required-mark">*</span></label><BFormInput v-model="form.derived_at" type="datetime-local" required /></div>
          <div class="col-md-6"><label class="form-label">Fecha límite de respuesta</label><BFormInput v-model="form.response_due_at" type="datetime-local" :min="form.derived_at || undefined" /></div>
          <div class="col-md-4"><label class="form-label">Enviada el</label><BFormInput v-model="form.sent_at" type="datetime-local" /></div>
          <div class="col-md-4"><label class="form-label">Respondida el</label><BFormInput v-model="form.responded_at" type="datetime-local" /></div>
          <div class="col-md-4"><label class="form-label">Cerrada el</label><BFormInput v-model="form.closed_at" type="datetime-local" /></div>
        </div>
      </fieldset>

      <fieldset class="convivencia-record-form__section">
        <legend><span class="case-step">3</span><i class="bx bx-navigation"></i>Destino y fundamento</legend>
        <p class="convivencia-record-form__section-help">{{ form.scope === "external" ? "Selecciona una institución de la red externa y registra un contacto cuando esté disponible." : "Selecciona un departamento, usuario o funcionario del establecimiento." }}</p>
        <div class="row g-3">
          <template v-if="form.scope === 'internal'">
            <div class="col-md-4"><label class="form-label">Departamento destino</label><BFormSelect v-model="form.destination_department_id" :options="list('departmentsOptional')" /></div>
            <div class="col-md-4"><label class="form-label">Usuario receptor</label><BFormSelect v-model="form.destination_user_id" :options="list('usersOptional')" /></div>
            <div class="col-md-4"><label class="form-label">Funcionario receptor</label><BFormSelect v-model="form.destination_staff_id" :options="list('staffOptional')" /></div>
          </template>
          <template v-else>
            <div class="col-md-6"><label class="form-label">Institución externa <small>(opcional si indicas el destino abajo)</small></label><BFormSelect v-model="form.external_institution_id" :options="list('institutionsOptional')" /></div>
            <div class="col-md-6"><label class="form-label">Nombre del contacto externo</label><BFormInput v-model="form.external_contact_name" maxlength="160" /></div>
            <div class="col-md-6"><label class="form-label">Correo externo</label><BFormInput v-model="form.external_contact_email" type="email" maxlength="191" /></div>
            <div class="col-md-6"><label class="form-label">Teléfono externo</label><BFormInput v-model="form.external_contact_phone" type="tel" maxlength="80" /></div>
          </template>
          <div class="col-12"><label class="form-label">Destino complementario <small>(obligatorio si no seleccionas un destinatario)</small></label><BFormInput v-model="form.destination_label" maxlength="191" placeholder="Nombre de unidad, red o profesional cuando no aparece en los selectores" /></div>
          <div class="col-md-6"><label class="form-label">Motivo de la derivación <span class="required-mark">*</span></label><BFormTextarea v-model="form.motive" rows="4" required /><div class="case-text-assist"><button v-for="suggestion in caseTextSuggestions.motive" :key="suggestion.label" type="button" :class="{ 'is-added': hasCaseSuggestion('motive', suggestion) }" :disabled="hasCaseSuggestion('motive', suggestion)" @click="appendCaseSuggestion('motive', suggestion)"><i class="bx" :class="hasCaseSuggestion('motive', suggestion) ? 'bx-check' : 'bx-plus'"></i>{{ suggestion.label }}</button></div></div>
          <div class="col-md-6"><label class="form-label">Antecedentes que se comunican</label><BFormTextarea v-model="form.narrative" rows="4" /></div>
          <div class="col-md-6"><label class="form-label">Acciones sugeridas</label><BFormTextarea v-model="form.suggested_actions" rows="3" /><div class="case-text-assist"><button v-for="suggestion in caseTextSuggestions.suggested_actions" :key="suggestion.label" type="button" :class="{ 'is-added': hasCaseSuggestion('suggested_actions', suggestion) }" :disabled="hasCaseSuggestion('suggested_actions', suggestion)" @click="appendCaseSuggestion('suggested_actions', suggestion)"><i class="bx" :class="hasCaseSuggestion('suggested_actions', suggestion) ? 'bx-check' : 'bx-plus'"></i>{{ suggestion.label }}</button></div></div>
          <div class="col-md-6"><label class="form-label">Respuesta recibida</label><BFormTextarea v-model="form.response_text" rows="3" /></div>
          <div class="col-12"><label class="form-label">Seguimiento y acuerdos</label><BFormTextarea v-model="form.follow_up_notes" rows="3" /></div>
          <div class="col-12"><div class="case-sensitive-switch"><BFormCheckbox v-model="form.is_sensitive" switch><b>Derivación con información sensible</b></BFormCheckbox><small>Restringe el acceso cuando se comunican antecedentes protegidos o de la vida privada.</small></div></div>
        </div>
      </fieldset>
    </template>

    <template v-else-if="section === 'entrevistas'">
      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-conversation"></i>Contexto de la entrevista</legend>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Caso asociado</label><ConvivenciaRemoteSelect v-model="form.case_id" type="cases" placeholder="Buscar caso por folio" aria-label="Buscar caso para la entrevista" /></div>
          <div class="col-md-4"><label class="form-label">Estudiante</label><BFormSelect v-model="form.student_profile_id" :options="list('studentsOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="form.course_section_id" :options="list('coursesOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Tipo de entrevista</label><BFormSelect v-model="form.interview_type_item_id" :options="list('interviewTypes')" /></div>
          <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="form.responsible_user_id" :options="list('users')" /></div>
          <div class="col-md-4"><label class="form-label">Fecha y hora</label><BFormInput v-model="form.interview_at" type="datetime-local" required /></div>
          <div class="col-md-6"><label class="form-label">Motivo</label><BFormTextarea v-model="form.motive" rows="2" /></div>
          <div class="col-md-6"><label class="form-label">Temas tratados</label><BFormTextarea v-model="form.topics" rows="2" /></div>
          <div class="col-md-6"><label class="form-label">Acuerdos</label><BFormTextarea v-model="form.agreements" rows="2" /></div>
          <div class="col-md-6"><label class="form-label">Compromisos</label><BFormTextarea v-model="form.commitments" rows="2" /></div>
          <div class="col-md-4"><label class="form-label">Fecha de seguimiento</label><BFormInput v-model="form.follow_up_date" type="date" /></div>
          <div class="col-md-4"><label class="form-label">Estado del seguimiento</label><BFormSelect v-model="form.follow_up_status" :options="list('interviewStatuses')" /></div>
          <div class="col-md-4 d-flex align-items-end"><BFormCheckbox v-model="form.is_sensitive">Información sensible</BFormCheckbox></div>
          <div class="col-12"><label class="form-label">Notas internas</label><BFormTextarea v-model="form.internal_notes" rows="2" /></div>
        </div>
      </fieldset>
      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-group"></i>Participantes <button type="button" class="btn btn-sm btn-outline-primary" @click="$emit('add', 'participants')"><i class="bx bx-plus"></i>Agregar participante</button></legend>
        <article v-for="(participant, index) in form.participants || []" :key="index" class="convivencia-record-form__repeat">
          <div class="convivencia-record-form__repeat-head"><b>Participante {{ index + 1 }}</b><button type="button" class="btn btn-sm btn-outline-danger" @click="$emit('remove', 'participants', index)"><i class="bx bx-trash"></i>Quitar</button></div>
          <div class="row g-2"><div class="col-md-3"><label class="form-label">Tipo</label><BFormSelect v-model="participant.participant_type" :options="list('participantTypes')" /></div><div class="col-md-3"><label class="form-label">Rol</label><BFormInput v-model="participant.participant_role" /></div><div class="col-md-3"><label class="form-label">Nombre completo</label><BFormInput v-model="participant.full_name" /></div><div class="col-md-3"><label class="form-label">Estudiante vinculado</label><BFormSelect v-model="participant.student_profile_id" :options="list('studentsOptional')" /></div><div class="col-md-4"><label class="form-label">Usuario vinculado</label><BFormSelect v-model="participant.user_id" :options="list('usersOptional')" /></div><div class="col-md-4"><label class="form-label">Funcionario vinculado</label><BFormSelect v-model="participant.staff_id" :options="list('staffOptional')" /></div><div class="col-md-4"><label class="form-label">Contacto</label><BFormInput v-model="participant.contact_reference" /></div><div class="col-12"><label class="form-label">Notas</label><BFormInput v-model="participant.notes" /></div></div>
        </article>
      </fieldset>
      <fieldset v-if="form.id" class="convivencia-record-form__section correction-reason-section">
        <legend><i class="bx bx-history"></i>Trazabilidad de la corrección</legend>
        <p class="convivencia-record-form__section-help">El acta anterior y la versión corregida se conservarán cifradas. Indica el motivo para dejar una trazabilidad clara.</p>
        <label class="form-label">Motivo de la corrección <span class="required-mark">*</span></label>
        <BFormTextarea v-model="form.change_reason" rows="2" minlength="5" maxlength="1000" required placeholder="Ej.: Se corrige el acuerdo después de revisar el acta con las personas participantes." />
      </fieldset>
    </template>

    <template v-else-if="section === 'medidas'">
      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-check-shield"></i>Asignación de la medida</legend>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Caso asociado</label><ConvivenciaRemoteSelect v-model="form.case_id" type="cases" placeholder="Buscar caso por folio" aria-label="Buscar caso para la medida" /></div>
          <div class="col-md-4"><label class="form-label">Estudiante</label><BFormSelect v-model="form.student_profile_id" :options="list('studentsOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="form.course_section_id" :options="list('coursesOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Tipo de medida</label><BFormSelect v-model="form.measure_type_item_id" :options="list('measureTypes')" required /></div>
          <div class="col-md-4"><label class="form-label">Responsable</label><BFormSelect v-model="form.responsible_user_id" :options="list('users')" /></div>
          <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="form.status" :options="list('measureStatuses')" /></div>
          <div class="col-md-4"><label class="form-label">Validada por</label><BFormSelect v-model="form.validated_by" :options="list('usersOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Asignación</label><BFormInput v-model="form.assigned_at" type="datetime-local" /></div>
          <div class="col-md-4"><label class="form-label">Cumplimiento esperado</label><BFormInput v-model="form.due_at" type="datetime-local" /></div>
          <div class="col-md-4 d-flex align-items-end"><BFormCheckbox v-model="form.is_sensitive">Información sensible</BFormCheckbox></div>
        </div>
      </fieldset>
      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-target-lock"></i>Propósito y seguimiento</legend>
        <div class="row g-3"><div class="col-md-6"><label class="form-label">Descripción</label><BFormTextarea v-model="form.description" rows="3" /></div><div class="col-md-6"><label class="form-label">Objetivo formativo</label><BFormTextarea v-model="form.training_objective" rows="3" /></div><div class="col-md-6"><label class="form-label">Acción de reparación</label><BFormTextarea v-model="form.repair_action" rows="2" /></div><div class="col-md-6"><label class="form-label">Observaciones del responsable</label><BFormTextarea v-model="form.responsible_notes" rows="2" /></div><div class="col-md-6"><label class="form-label">Resumen de evidencia</label><BFormTextarea v-model="form.evidence_summary" rows="2" /></div><div class="col-md-6"><label class="form-label">Reflexión del estudiante</label><BFormTextarea v-model="form.student_reflection" rows="2" /></div><div class="col-md-8"><label class="form-label">Notas de cierre</label><BFormTextarea v-model="form.closure_notes" rows="2" /></div><div class="col-md-4"><label class="form-label">Fecha de cierre</label><BFormInput v-model="form.closed_at" type="datetime-local" /></div></div>
      </fieldset>
    </template>

    <template v-else-if="section === 'bitacora'">
      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-notepad"></i>Registro diario</legend>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Caso asociado</label><ConvivenciaRemoteSelect v-model="form.case_id" type="cases" placeholder="Buscar caso por folio" aria-label="Buscar caso para la bitácora" /></div>
          <div class="col-md-4"><label class="form-label">Año académico</label><BFormSelect v-model="form.academic_year_id" :options="list('academicYears')" /></div>
          <div class="col-md-4"><label class="form-label">Estudiante</label><BFormSelect v-model="form.student_profile_id" :options="list('studentsOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Curso</label><BFormSelect v-model="form.course_section_id" :options="list('coursesOptional')" /></div>
          <div class="col-md-4"><label class="form-label">Inspectora o responsable</label><BFormSelect v-model="form.inspector_user_id" :options="list('users')" required /></div>
          <div class="col-md-4"><label class="form-label">Tipo de hecho</label><BFormSelect v-model="form.daily_log_type_item_id" :options="list('dailyLogTypes')" required /></div>
          <div class="col-md-4"><label class="form-label">Fecha y hora</label><BFormInput v-model="form.happened_at" type="datetime-local" required /></div>
          <div class="col-md-4"><label class="form-label">Estado</label><BFormSelect v-model="form.status" :options="list('dailyLogStatuses')" /></div>
          <div class="col-md-6"><label class="form-label">Lugar</label><BFormInput v-model="form.place" /></div>
          <div class="col-md-6 d-flex align-items-end"><BFormCheckbox v-model="form.is_sensitive">Información sensible</BFormCheckbox></div>
          <div class="col-md-6"><label class="form-label">Descripción</label><BFormTextarea v-model="form.description" rows="3" required /></div>
          <div class="col-md-6"><label class="form-label">Acción inmediata</label><BFormTextarea v-model="form.immediate_action" rows="3" /></div>
          <div class="col-md-4 d-flex align-items-end"><BFormCheckbox v-model="form.guardian_informed">Apoderado informado</BFormCheckbox></div>
          <div class="col-md-8"><label class="form-label">Observación de contacto</label><BFormInput v-model="form.guardian_contact_note" /></div>
        </div>
      </fieldset>
      <fieldset class="convivencia-record-form__section">
        <legend><i class="bx bx-group"></i>Personas mencionadas <button type="button" class="btn btn-sm btn-outline-primary" @click="$emit('add', 'involved_snapshot')"><i class="bx bx-plus"></i>Agregar persona</button></legend>
        <div v-if="!form.involved_snapshot?.length" class="convivencia-record-form__empty">No hay otras personas mencionadas en este registro.</div>
        <article v-for="(person, index) in form.involved_snapshot || []" :key="index" class="convivencia-record-form__repeat"><div class="convivencia-record-form__repeat-head"><b>Persona {{ index + 1 }}</b><button type="button" class="btn btn-sm btn-outline-danger" @click="$emit('remove', 'involved_snapshot', index)"><i class="bx bx-trash"></i>Quitar</button></div><label class="form-label">Nombre completo</label><BFormInput v-model="person.full_name" /></article>
      </fieldset>
    </template>

    <template v-else-if="section === 'sociogramas'">
      <div class="sociogram-form-guide">
        <span><i class="bx bx-network-chart"></i></span>
        <div><b>Captura sociométrica guiada</b><p>Define preguntas claras, registra nominaciones dentro del curso y luego abre la vista gráfica para analizar reciprocidad, participación y agrupaciones.</p></div>
        <em><i class="bx bx-lock-alt"></i>Datos protegidos</em>
      </div>
      <fieldset class="convivencia-record-form__section convivencia-record-form__section--accent">
        <legend><span class="case-step">1</span><i class="bx bx-calendar-check"></i>Aplicación y confidencialidad</legend>
        <p class="convivencia-record-form__section-help">El curso determina la nómina disponible para responder y ser seleccionada. Al cambiarlo se limpian las respuestas para evitar cruces entre cursos.</p>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label" :for="fieldId('sociogram-year')">Año académico</label><BFormSelect :id="fieldId('sociogram-year')" v-model="form.academic_year_id" :options="list('academicYears')" required /></div>
          <div class="col-md-4"><label class="form-label" :for="fieldId('sociogram-course')">Curso <span class="required-mark">*</span></label><BFormSelect :id="fieldId('sociogram-course')" v-model="form.course_section_id" :options="list('coursesRequired')" required /></div>
          <div class="col-md-3"><label class="form-label" :for="fieldId('sociogram-date')">Aplicado el <span class="required-mark">*</span></label><BFormInput :id="fieldId('sociogram-date')" v-model="form.applied_on" type="date" required /></div>
          <div class="col-md-2"><label class="form-label">Estado</label><BFormSelect v-model="form.status" :options="list('sociogramStatuses')" required /></div>
          <div class="col-md-7"><label class="form-label" :for="fieldId('sociogram-title')">Título <span class="required-mark">*</span></label><BFormInput :id="fieldId('sociogram-title')" v-model="form.title" placeholder="Ej.: Diagnóstico de vínculos del primer semestre" required /></div>
          <div class="col-md-3"><label class="form-label">Confidencialidad</label><BFormSelect v-model="form.confidentiality_level" :options="list('confidentialityLevels')" required /></div>
          <div class="col-md-2 d-flex align-items-end"><div class="sociogram-sensitive"><BFormCheckbox v-model="form.is_sensitive" switch>Información sensible</BFormCheckbox></div></div>
        </div>
      </fieldset>
      <fieldset class="convivencia-record-form__section sociogram-question-section">
        <legend><span class="case-step">2</span><i class="bx bx-question-mark"></i>Preguntas sociométricas <button type="button" class="btn btn-sm btn-outline-primary" @click="$emit('add', 'questions')"><i class="bx bx-plus"></i>Agregar pregunta</button></legend>
        <p class="convivencia-record-form__section-help">Usa criterios concretos y limita las elecciones. Las preguntas positivas y las que requieren revisión se muestran por separado en el análisis.</p>
        <div v-if="!form.questions?.length" class="convivencia-record-form__empty"><i class="bx bx-question-mark"></i><b>Agrega al menos una pregunta</b><span>Ejemplo: ¿Con quién prefieres trabajar en equipo?</span></div>
        <article v-for="(question, index) in form.questions || []" :key="index" class="convivencia-record-form__repeat sociogram-question-card" :class="`is-${question.selection_type}`">
          <div class="convivencia-record-form__repeat-head"><b><span>{{ index + 1 }}</span> Criterio sociométrico</b><button type="button" class="btn btn-sm btn-outline-danger" :disabled="form.questions.length <= 1" @click="$emit('remove', 'questions', index)"><i class="bx bx-trash"></i>Quitar</button></div>
          <div class="row g-2">
            <div class="col-lg-7"><label class="form-label">Pregunta <span class="required-mark">*</span></label><BFormInput v-model="question.prompt" placeholder="Formula una pregunta breve y observable" required /></div>
            <div class="col-lg-3"><label class="form-label">Sentido de la nominación</label><BFormSelect v-model="question.selection_type" :options="list('selectionTypes')" @change="syncSociogramQuestionAnswers(index)" /></div>
            <div class="col-lg-2"><label class="form-label">Máximo por estudiante</label><BFormInput v-model="question.max_choices" type="number" min="1" max="10" /></div>
            <div class="col-12"><BFormCheckbox v-model="question.active" switch>Pregunta activa para la aplicación</BFormCheckbox></div>
          </div>
        </article>
      </fieldset>
      <fieldset class="convivencia-record-form__section sociogram-answer-section">
        <legend><span class="case-step">3</span><i class="bx bx-check-square"></i>Nominaciones registradas <button type="button" class="btn btn-sm btn-primary" :disabled="!form.course_section_id || !form.questions?.length" @click="addSociogramAnswer()"><i class="bx bx-plus"></i>Agregar nominación</button></legend>
        <div class="sociogram-capture-summary"><span><i class="bx bx-list-check"></i><b>{{ form.answers?.length || 0 }}</b> nominaciones</span><span><i class="bx bx-user-check"></i><b>{{ sociogramAnsweredStudents }}</b> estudiantes que respondieron</span><small>La estudiante que responde y la seleccionada deben pertenecer al curso.</small></div>
        <div v-if="!form.course_section_id" class="convivencia-record-form__empty"><i class="bx bx-chalkboard"></i><b>Selecciona primero el curso</b><span>Así se limitará la búsqueda a la nómina correspondiente.</span></div>
        <div v-else-if="!form.answers?.length" class="convivencia-record-form__empty"><i class="bx bx-user-plus"></i><b>Aún no hay nominaciones</b><span>Usa “Agregar nominación” para comenzar la captura.</span></div>
        <article v-for="(answer, index) in form.answers || []" :key="index" class="convivencia-record-form__repeat sociogram-answer-card">
          <div class="convivencia-record-form__repeat-head"><b>Nominación {{ index + 1 }}</b><span v-if="sociogramQuestionFor(answer)" :class="`sociogram-type is-${answer.selection_type}`">{{ sociogramQuestionFor(answer).selection_type }}</span><button type="button" class="btn btn-sm btn-outline-danger" @click="$emit('remove', 'answers', index)"><i class="bx bx-trash"></i>Quitar</button></div>
          <div class="row g-2">
            <div class="col-lg-4"><label class="form-label">Pregunta <span class="required-mark">*</span></label><BFormSelect v-model="answer.question_order" :options="sociogramQuestionChoices" required @change="syncSociogramAnswerType(answer)" /></div>
            <div class="col-lg-4"><label class="form-label">Quién responde <span class="required-mark">*</span></label><ConvivenciaRemoteSelect v-model="answer.respondent_student_id" type="students" :filters="sociogramStudentFilters" placeholder="Buscar dentro del curso" aria-label="Buscar estudiante que responde" required /></div>
            <div class="col-lg-4"><label class="form-label">A quién selecciona <span class="required-mark">*</span></label><ConvivenciaRemoteSelect v-model="answer.selected_student_id" type="students" :filters="sociogramStudentFilters" placeholder="Buscar dentro del curso" aria-label="Buscar estudiante seleccionada" required /></div>
            <div class="col-12"><label class="form-label">Nota contextual <small>(opcional)</small></label><BFormInput v-model="answer.notes" placeholder="Solo antecedentes pertinentes para la interpretación profesional" /></div>
          </div>
        </article>
      </fieldset>
      <fieldset class="convivencia-record-form__section convivencia-record-form__section--support">
        <legend><span class="case-step">4</span><i class="bx bx-notepad"></i>Interpretación profesional</legend>
        <p class="convivencia-record-form__section-help">Registra una lectura prudente del grupo. El sistema entrega indicadores descriptivos, pero no reemplaza la observación, entrevistas ni juicio profesional.</p>
        <BFormTextarea v-model="form.interpretation" rows="4" placeholder="Describe patrones de integración, reciprocidad, subgrupos y necesidades de acompañamiento, evitando etiquetas diagnósticas." />
      </fieldset>
    </template>
  </div>
</template>

<style scoped>
.convivencia-record-form{display:flex;flex-direction:column;gap:.85rem}.convivencia-record-form__section{min-width:0;padding:.95rem;margin:0;border:1px solid #dfe5ef;border-radius:15px;background:#fff}.convivencia-record-form__section--accent{border-color:#d9def8;background:linear-gradient(145deg,#fff 0%,#fafbff 100%)}.convivencia-record-form__section--classification{border-color:#dce3f7;background:linear-gradient(145deg,#fff 0%,#f8faff 100%)}.convivencia-record-form__section legend{display:flex;width:100%;align-items:center;gap:.38rem;padding:0;margin:0 0 .45rem;color:#28376e;font-size:.76rem;font-weight:800}.convivencia-record-form__section legend i{color:#4f63d9;font-size:1.05rem}.convivencia-record-form__section legend .btn{display:inline-flex;align-items:center;gap:.25rem;margin-left:auto;font-size:.62rem}.convivencia-record-form__section-help{margin:0 0 .85rem;color:#748096;font-size:.63rem;line-height:1.55}.convivencia-record-form :deep(.form-label){margin-bottom:.28rem;color:#526078;font-size:.63rem;font-weight:750}.convivencia-record-form :deep(.form-control),.convivencia-record-form :deep(.form-select){min-height:40px;font-size:.7rem;border-color:#dce2ed;border-radius:9px}.convivencia-record-form :deep(.form-control::placeholder){color:#a2abbb}.convivencia-record-form :deep(.form-control:focus),.convivencia-record-form :deep(.form-select:focus){border-color:#7687e9;box-shadow:0 0 0 3px rgba(80,100,217,.1)}.convivencia-record-form :deep(textarea.form-control){min-height:auto;line-height:1.55}.convivencia-record-form__repeat{padding:.8rem;margin-bottom:.55rem;border:1px solid #e0e5ee;border-radius:12px;background:#fafbfe}.convivencia-record-form__repeat:last-child{margin-bottom:0}.convivencia-record-form__repeat-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:.65rem;color:#526078;font-size:.66rem}.convivencia-record-form__repeat-head .btn{display:inline-flex;align-items:center;gap:.2rem;font-size:.58rem}.convivencia-record-form__empty{display:grid;justify-items:center;gap:.18rem;padding:1.15rem;color:#7c879a;font-size:.68rem;text-align:center;border:1px dashed #d8dfeb;border-radius:12px;background:#fafbfe}.convivencia-record-form__empty i{color:#7b8be1;font-size:1.35rem}.convivencia-record-form__empty b{color:#53617a}.convivencia-record-form__empty span{font-size:.61rem}.required-mark{color:#d95366}.field-help{display:block;margin-top:.25rem;color:#8490a4;font-size:.57rem;line-height:1.35}.field-counter{color:#718096;font-size:.58rem}.field-counter.is-short{color:#c23a4b}.field-alert{display:flex;align-items:flex-start;gap:.3rem;margin-top:.35rem;padding:.5rem .65rem;color:#9b4d0d;font-size:.6rem;line-height:1.45;border:1px solid #ffd8a8;border-radius:9px;background:#fff8ef}.field-alert i{margin-top:.05rem;font-size:.82rem}.correction-reason-section{border-color:#d7e2f2;background:linear-gradient(135deg,#f3f8ff,#f8f3ff)}.correction-reason-section legend i{color:#287d6a}.case-step{display:inline-grid;width:1.35rem;height:1.35rem;place-items:center;color:#fff;font-size:.58rem;border-radius:50%;background:linear-gradient(135deg,#4f63d9,#7b5bd6);box-shadow:0 4px 10px rgba(79,99,217,.22)}.case-intake-guide{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.8rem;padding:.85rem 1rem;color:#fff;border-radius:15px;background:radial-gradient(circle at 90% 10%,rgba(255,255,255,.18),transparent 32%),linear-gradient(135deg,#3349b8,#6b55c7);box-shadow:0 8px 22px rgba(51,73,184,.18)}.case-intake-guide__icon{display:grid;width:2.45rem;height:2.45rem;place-items:center;font-size:1.3rem;border:1px solid rgba(255,255,255,.25);border-radius:12px;background:rgba(255,255,255,.12)}.case-intake-guide b{font-size:.73rem}.case-intake-guide p{margin:.15rem 0 0;color:rgba(255,255,255,.82);font-size:.6rem;line-height:1.45}.case-intake-guide p span{color:#ffd8a8;font-weight:800}.case-intake-guide__steps{display:flex;gap:.38rem}.case-intake-guide__steps span{display:flex;align-items:center;gap:.25rem;padding:.32rem .48rem;white-space:nowrap;font-size:.55rem;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(255,255,255,.1)}.case-intake-guide__steps b{display:grid;width:1rem;height:1rem;place-items:center;font-size:.5rem;border-radius:50%;background:rgba(255,255,255,.18)}.case-classification-summary{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-top:.8rem}.case-classification-summary>div{display:grid;grid-template-columns:auto 1fr;align-items:start;gap:.55rem;padding:.65rem .7rem;border:1px solid #e0e5ef;border-radius:11px;background:#fff}.case-classification-summary__dot{width:.72rem;height:.72rem;margin-top:.16rem;border-radius:50%;box-shadow:0 0 0 4px rgba(79,99,217,.08)}.case-classification-summary div div{display:grid;gap:.12rem}.case-classification-summary b{color:#35435e;font-size:.64rem}.case-classification-summary small{color:#758197;font-size:.57rem;line-height:1.4}.case-classification-summary em{color:#5869ca;font-size:.54rem;font-style:normal;font-weight:700}.case-criticality-guidance{border-left:3px solid var(--criticality-color)!important}.case-criticality-guidance>i{color:var(--criticality-color);font-size:1rem}.case-readonly-status{display:flex;min-height:40px;align-items:center;gap:.35rem;padding:.45rem .7rem;color:#28784a;font-size:.67rem;font-weight:750;border:1px solid #cfe8d9;border-radius:9px;background:#f1fbf5}.case-sensitive-switch{display:grid;gap:.15rem;padding:.65rem .75rem;border:1px solid #e2e6ef;border-radius:10px;background:#f9faff}.case-sensitive-switch :deep(.form-check-label){color:#43516b;font-size:.65rem}.case-sensitive-switch small{padding-left:2.15rem;color:#7c879a;font-size:.56rem;line-height:1.4}.case-person-card{border-color:#dce3f4;background:linear-gradient(145deg,#fbfcff,#f7f9fd)}.case-person-card .convivencia-record-form__repeat-head b{display:flex;align-items:center;gap:.35rem}.case-person-card .convivencia-record-form__repeat-head b span{display:grid;width:1.2rem;height:1.2rem;place-items:center;color:#fff;font-size:.54rem;border-radius:50%;background:#5d6fd3}
.convivencia-record-form__section--support{border-color:#cfe7df;background:linear-gradient(145deg,#fff 0%,#f5fcf9 100%)}.convivencia-record-form__section--support legend i{color:#27856c}.case-support-picker{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:.6rem}.case-support-picker__button,.case-quick-students__controls>.btn{display:inline-flex;min-height:40px;align-items:center;justify-content:center;gap:.28rem;padding-inline:.85rem;font-size:.64rem;font-weight:750;border:0;border-radius:9px;background:linear-gradient(135deg,#4f63d9,#6c5bd3);box-shadow:0 5px 12px rgba(79,99,217,.18)}.case-support-team{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem;margin-top:.65rem}.case-support-member{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.5rem;padding:.55rem .6rem;border:1px solid #d7e9e3;border-radius:11px;background:#fff;box-shadow:0 4px 10px rgba(39,133,108,.06)}.case-support-member__icon{display:grid;width:1.9rem;height:1.9rem;place-items:center;color:#fff;font-size:.88rem;border-radius:9px;background:linear-gradient(135deg,#2d977b,#4eaf98)}.case-support-member div{display:grid;min-width:0;gap:.08rem}.case-support-member b{overflow:hidden;color:#304a45;font-size:.64rem;text-overflow:ellipsis;white-space:nowrap}.case-support-member small{overflow:hidden;color:#70847f;font-size:.55rem;text-overflow:ellipsis;white-space:nowrap}.case-support-member .btn{display:grid;width:1.7rem;height:1.7rem;padding:0;place-items:center;color:#8c6470;border-radius:7px;background:#fff3f5}.case-support-member .btn:hover{color:#b23d52;background:#ffe5e9}.case-support-empty{margin-top:.65rem;padding:.8rem}.case-quick-students{display:grid;gap:.6rem;padding:.75rem;margin-bottom:.7rem;border:1px solid #dbe2fa;border-radius:13px;background:linear-gradient(135deg,#f8faff,#f4f7ff)}.case-quick-students__heading{display:flex;align-items:center;gap:.5rem}.case-quick-students__heading>span{display:grid;width:1.8rem;height:1.8rem;place-items:center;color:#fff;font-size:.85rem;border-radius:8px;background:linear-gradient(135deg,#efad34,#f07b43);box-shadow:0 4px 10px rgba(239,144,52,.2)}.case-quick-students__heading div{display:grid;gap:.05rem}.case-quick-students__heading b{color:#35456c;font-size:.66rem}.case-quick-students__heading small{color:#78849a;font-size:.56rem}.case-quick-students__controls{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(150px,.7fr) auto;align-items:end;gap:.55rem}.case-quick-feedback{padding:.42rem .55rem;font-size:.58rem;font-weight:700;border-radius:8px}.case-quick-feedback.is-success{color:#267159;background:#e8f8f1}.case-quick-feedback.is-warning{color:#8a5c16;background:#fff5dc}.case-quick-feedback.is-error{color:#a33b4b;background:#fff0f2}
.case-responsible-field__linked{display:flex;align-items:center;gap:.25rem;margin-top:.3rem;color:#28785f;font-size:.57rem;font-weight:700}.case-responsible-field__linked i{font-size:.78rem}.case-text-assist{display:flex;flex-wrap:wrap;align-items:center;gap:.32rem;margin-top:.42rem}.case-text-assist>span{display:inline-flex;align-items:center;gap:.2rem;margin-right:.08rem;color:#7b879b;font-size:.55rem;font-weight:750}.case-text-assist>span i{color:#5368d5;font-size:.72rem}.case-text-assist button{display:inline-flex;min-height:26px;align-items:center;gap:.16rem;padding:.25rem .48rem;color:#5264bd;font-size:.54rem;font-weight:750;line-height:1.2;border:1px solid #dbe1fa;border-radius:999px;background:#f7f8ff;transition:transform .12s ease,border-color .12s ease,background-color .12s ease}.case-text-assist button:hover:not(:disabled){color:#3348a8;border-color:#aebaf1;background:#eef1ff;transform:translateY(-1px)}.case-text-assist button:focus-visible{outline:2px solid #7384e3;outline-offset:2px}.case-text-assist button.is-added{color:#2b7c64;border-color:#cbe8de;background:#effaf6;opacity:1}.case-text-assist button i{font-size:.68rem}
.record-kind-guide{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.7rem;padding:.8rem .9rem;color:#fff;border-radius:14px;box-shadow:0 8px 20px rgba(38,55,130,.14)}.record-kind-guide--complaint{background:radial-gradient(circle at 90% 10%,rgba(255,255,255,.18),transparent 32%),linear-gradient(135deg,#7b4bb0,#5368d5)}.record-kind-guide--derivation{background:radial-gradient(circle at 90% 10%,rgba(255,255,255,.18),transparent 32%),linear-gradient(135deg,#226f73,#3d8f83)}.record-kind-guide>span{display:grid;width:2.35rem;height:2.35rem;font-size:1.15rem;place-items:center;border:1px solid rgba(255,255,255,.25);border-radius:11px;background:rgba(255,255,255,.12)}.record-kind-guide>div{display:grid;gap:.12rem}.record-kind-guide b{font-size:.72rem}.record-kind-guide p{margin:0;color:rgba(255,255,255,.8);font-size:.58rem;line-height:1.4}.record-kind-guide em{padding:.3rem .48rem;font-size:.54rem;font-style:normal;font-weight:800;white-space:nowrap;border:1px solid rgba(255,255,255,.22);border-radius:999px;background:rgba(255,255,255,.12)}.complaint-identity-toggle{display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.58rem .68rem;margin-bottom:.2rem;border:1px solid #e2e6ef;border-radius:10px;background:#f9faff}.complaint-identity-toggle :deep(.form-check-label){color:#40506f;font-size:.65rem}.complaint-identity-toggle small{color:#7b8799;font-size:.56rem}.complaint-checks{display:flex;align-items:center;justify-content:flex-start;flex-wrap:wrap;gap:1rem;padding-top:1.25rem}.complaint-checks :deep(.form-check-label){font-size:.61rem}
.sociogram-form-guide{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.7rem;padding:.85rem .95rem;color:#fff;border-radius:15px;background:radial-gradient(circle at 88% 8%,rgba(255,255,255,.18),transparent 34%),linear-gradient(135deg,#293f9f,#6657c2 70%,#278c78);box-shadow:0 9px 22px rgba(45,61,146,.18)}.sociogram-form-guide>span{display:grid;width:2.5rem;height:2.5rem;place-items:center;font-size:1.25rem;border:1px solid rgba(255,255,255,.25);border-radius:12px;background:rgba(255,255,255,.12)}.sociogram-form-guide b{font-size:.72rem}.sociogram-form-guide p{margin:.1rem 0 0;color:rgba(255,255,255,.82);font-size:.58rem;line-height:1.45}.sociogram-form-guide em{display:inline-flex;align-items:center;gap:.25rem;padding:.35rem .5rem;font-size:.54rem;font-style:normal;font-weight:800;border:1px solid rgba(255,255,255,.2);border-radius:99px;background:rgba(255,255,255,.11)}.sociogram-sensitive{display:flex;min-height:40px;align-items:center;padding:.45rem .6rem;border:1px solid #dfe4ed;border-radius:9px;background:#fafbfe}.sociogram-question-section{border-color:#d9def4;background:linear-gradient(145deg,#fff,#fafaff)}.sociogram-question-card{position:relative;overflow:hidden;padding-left:.95rem}.sociogram-question-card::before{position:absolute;top:0;bottom:0;left:0;width:4px;content:"";background:#269177}.sociogram-question-card.is-negativa::before{background:#d05d72}.sociogram-question-card.is-neutra::before{background:#8290a5}.sociogram-question-card .convivencia-record-form__repeat-head b span{display:inline-grid;width:1.2rem;height:1.2rem;margin-right:.25rem;place-items:center;color:#fff;font-size:.52rem;border-radius:50%;background:#586bd0}.sociogram-answer-section{border-color:#d5e7e2;background:linear-gradient(145deg,#fff,#f7fcfa)}.sociogram-capture-summary{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem;margin-bottom:.65rem}.sociogram-capture-summary>span{display:inline-flex;align-items:center;gap:.26rem;padding:.34rem .48rem;color:#395b55;font-size:.55rem;border:1px solid #d6e9e3;border-radius:99px;background:#f1faf7}.sociogram-capture-summary>span i{color:#278c78;font-size:.75rem}.sociogram-capture-summary>span b{font-size:.65rem}.sociogram-capture-summary>small{margin-left:auto;color:#7c8798;font-size:.54rem}.sociogram-answer-card{border-color:#dce7e3;background:#fff}.sociogram-answer-card .convivencia-record-form__repeat-head{justify-content:flex-start}.sociogram-answer-card .convivencia-record-form__repeat-head .btn{margin-left:auto}.sociogram-type{padding:.2rem .4rem;font-size:.5rem;font-weight:800;text-transform:capitalize;border-radius:99px}.sociogram-type.is-positiva{color:#23765f;background:#e4f6ef}.sociogram-type.is-negativa{color:#a44155;background:#fdecef}.sociogram-type.is-neutra{color:#657286;background:#eef1f5}
@media(max-width:991.98px){.case-intake-guide{grid-template-columns:auto minmax(0,1fr)}.case-intake-guide__steps{grid-column:1/-1;flex-wrap:wrap}.case-classification-summary{grid-template-columns:1fr}.case-quick-students__controls{grid-template-columns:minmax(0,1fr) minmax(150px,.65fr)}.case-quick-students__controls>.btn{grid-column:1/-1}.case-support-team{grid-template-columns:1fr}}
@media(max-width:575.98px){.convivencia-record-form__section{padding:.75rem}.convivencia-record-form__section legend{align-items:flex-start;flex-wrap:wrap}.convivencia-record-form__section legend .btn{width:100%;justify-content:center;margin:.25rem 0 0}.convivencia-record-form__repeat-head .btn span{display:none}.case-intake-guide{grid-template-columns:1fr;padding:.8rem}.case-intake-guide__icon{display:none}.case-intake-guide__steps{grid-column:auto}.case-intake-guide__steps span{flex:1;justify-content:center}.case-sensitive-switch small{padding-left:0}.case-support-picker,.case-quick-students__controls{grid-template-columns:1fr}.case-support-picker__button,.case-quick-students__controls>.btn{grid-column:auto;width:100%}.case-quick-students__heading{align-items:flex-start}.case-quick-students__heading small{line-height:1.35}.case-support-member b,.case-support-member small{white-space:normal}.record-kind-guide{grid-template-columns:1fr}.record-kind-guide>span{display:none}.record-kind-guide em{justify-self:start}.complaint-identity-toggle{align-items:flex-start;flex-direction:column}.complaint-checks{align-items:flex-start;flex-direction:column;gap:.45rem;padding-top:.2rem}}
@media(max-width:991.98px){.sociogram-form-guide{grid-template-columns:auto 1fr}.sociogram-form-guide em{display:none}}
@media(max-width:575.98px){.sociogram-form-guide{grid-template-columns:1fr}.sociogram-form-guide>span{display:none}.sociogram-capture-summary{align-items:flex-start;flex-direction:column}.sociogram-capture-summary>small{margin-left:0}}
</style>
