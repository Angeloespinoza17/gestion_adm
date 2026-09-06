import { getPdfMake } from "../../../utils/pdfmake";
import {
  buildConvivenciaPdfDefinition,
  pdfArray,
  pdfDate,
  pdfEmpty,
  pdfFacts,
  pdfLabel,
  pdfRecord,
  pdfSection,
  pdfStructuredText,
  pdfText,
  safePdfFilePart,
} from "./convivencia-pdf-theme";

const relation = (record, snake, camel) => record?.[snake] ?? record?.[camel] ?? null;
const personName = (record, fallback = "-") => record?.registered_name_resolved
  || record?.registered_name
  || record?.full_name
  || record?.name
  || fallback;
const firstPersonName = (...records) => records
  .map((record) => personName(record, ""))
  .find(Boolean) || "-";
const yesNo = (value) => value === true ? "Sí" : value === false ? "No" : "-";
const bytes = (value) => {
  const size = Number(value || 0);
  if (!size) return "-";
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const attachmentsText = (records) => pdfArray(records).map((attachment) => [
  attachment.original_name || "Archivo sin nombre",
  pdfLabel(attachment.category),
  pdfLabel(attachment.confidentiality_level),
  bytes(attachment.file_size),
].filter((value) => value && value !== "-").join(" · ")).join("\n");

const statusLogsText = (records) => pdfArray(records).map((entry) => [
  pdfDate(entry.changed_at || entry.created_at, true),
  `${pdfLabel(entry.previous_status)} → ${pdfLabel(entry.new_status)}`,
  entry.comment,
  `Por ${personName(relation(entry, "changed_by", "changedBy"))}`,
].filter((value) => value && value !== "-").join(" | ")).join("\n");

const participantSummary = (participants) => pdfArray(participants).map((participant) => [
  participant.full_name || firstPersonName(participant.student, participant.staff, participant.user),
  pdfLabel(participant.participant_type),
  participant.participant_role,
  participant.contact_reference,
  participant.notes,
].filter(Boolean).join(" · ")).join("\n");

const runtimePartSummary = (parts) => pdfArray(parts).map((part) => [
  part.title || part.code || "Componente",
  pdfLabel(part.category),
  pdfLabel(part.status),
  part.is_required === true ? "Obligatorio" : "Opcional",
  part.completed_at ? `Completado ${pdfDate(part.completed_at, true)}` : null,
  part.notes,
  part.evidence_summary,
  part.outcome,
  pdfStructuredText(part.data, ""),
  pdfStructuredText(part.snapshot, ""),
].filter(Boolean).join(" | ")).join("\n");

const activationSteps = (activation) => pdfArray(relation(activation, "runtime_steps", "runtimeSteps"));

export const resolveConvivenciaCase = (payload) => payload?.data || payload?.case || payload || {};

export function buildConvivenciaCasePdfDefinition(payload, generatedAt = null) {
  const record = resolveConvivenciaCase(payload);
  const student = record.student || {};
  const course = relation(record, "course_section", "courseSection") || {};
  const academicYear = relation(record, "academic_year", "academicYear") || {};
  const responsibleUser = relation(record, "responsible_user", "responsibleUser") || {};
  const responsibleStaff = relation(record, "responsible_staff", "responsibleStaff") || {};
  const closedBy = relation(record, "closed_by", "closedBy") || {};
  const people = pdfArray(record.people);
  const supportTeam = people.filter((person) => person.role_type === "profesional_apoyo");
  const involvedPeople = people.filter((person) => person.role_type !== "profesional_apoyo");
  const followUps = pdfArray(relation(record, "follow_ups", "followUps"));
  const derivations = pdfArray(record.derivations);
  const interviews = pdfArray(record.interviews);
  const measures = pdfArray(record.measures);
  const complaints = pdfArray(record.complaints);
  const dailyLogs = pdfArray(relation(record, "daily_logs", "dailyLogs"));
  const activations = pdfArray(relation(record, "protocol_activations", "protocolActivations"));
  const attachments = pdfArray(record.attachments);
  const statusLogs = pdfArray(relation(record, "status_logs", "statusLogs"));
  const code = record.folio || `CASO-${record.id || "CONVIVENCIA"}`;
  const studentLabel = personName(student) === "-" ? "Caso sin estudiante principal" : personName(student);
  const caseType = record.case_type_label || relation(record, "case_type", "caseType")?.name;
  const classification = record.classification_label || record.classification?.name;
  const subclassification = record.subclassification_label || record.subclassification?.name;
  const criticality = record.criticality_label || record.criticality?.name;

  const content = [
    pdfFacts([
      ["Estado", pdfLabel(record.status)],
      ["Criticidad", criticality],
      ["Clasificación", classification],
      ["Subclasificación", subclassification],
      ["Tipo de caso", caseType],
      ["Documento reservado", yesNo(record.is_sensitive)],
    ]),
    pdfSection(1, "IDENTIFICACIÓN DEL EXPEDIENTE", "Datos de apertura, contexto escolar y responsables vigentes."),
    pdfFacts([
      ["Folio", code],
      ["Año académico", academicYear.name || academicYear.year || record.academic_year_id],
      ["Estudiante principal", studentLabel],
      ["RUT / identificador", student.rut],
      ["Curso", course.display_name],
      ["Nivel educativo", relation(course, "education_level", "educationLevel")?.name],
      ["Apoderado registrado", student.guardian_name],
      ["Contacto de apoderado", [student.guardian_phone, student.guardian_email].filter(Boolean).join(" · ")],
      ["Responsable operativo", responsibleUser.name],
      ["Correo institucional", responsibleUser.email],
      ["Ficha funcionaria vinculada", responsibleStaff.full_name],
      ["Origen", pdfLabel(record.origin)],
      ["Lugar", record.place],
      ["Fecha de apertura", pdfDate(record.opened_at, true)],
      ["Fecha del hecho", pdfDate(record.happened_at, true)],
      ["Próximo seguimiento", pdfDate(record.follow_up_due_at, true)],
      ["Fecha de cierre", pdfDate(record.closed_at, true)],
      ["Cerrado por", closedBy.name],
      ["Creado por", personName(relation(record, "created_by", "createdBy"))],
      ["Última edición por", personName(relation(record, "updated_by", "updatedBy"))],
    ]),
    pdfSection(2, "RELATO, ANTECEDENTES Y RESGUARDOS", "Registro textual íntegro consignado en el expediente."),
    pdfRecord("Relato inicial objetivo", pdfDate(record.opened_at, true), [["Contenido", record.initial_report]], "active", false),
    pdfRecord("Antecedentes relevantes", "Contexto", [["Contenido", record.background]], "default", false),
    pdfRecord("Acciones inmediatas realizadas", "Respuesta inicial", [["Contenido", record.immediate_measures]], "protected", false),
    pdfRecord("Medidas de resguardo adoptadas", "Protección", [["Contenido", record.safeguarding_measures]], "protected", false),
    pdfRecord("Notas internas de coordinación", "Uso interno", [["Contenido", record.internal_notes]], "warning", false),
    pdfSection(3, "PERSONAS VINCULADAS Y EQUIPO DE APOYO", `${people.length} persona(s) asociada(s) al expediente.`),
    ...(supportTeam.length ? supportTeam.map((person) => pdfRecord(
      person.full_name || firstPersonName(person.staff, person.user),
      "Profesional de apoyo",
      [
        ["Rol", person.relationship_label || pdfLabel(person.role_type)],
        ["Tipo", pdfLabel(person.person_type)],
        ["Identificador", person.identifier],
        ["Curso", relation(person, "course_section", "courseSection")?.display_name],
        ["Contacto", person.contact_reference],
        ["Notas", person.notes],
        ["Registro sensible", yesNo(person.is_sensitive)],
      ],
      "protected",
      false,
    )) : [pdfEmpty("No hay profesionales de apoyo registrados.")]),
    ...(involvedPeople.length ? involvedPeople.map((person) => pdfRecord(
      person.full_name || firstPersonName(person.student, person.staff, person.user),
      person.relationship_label || pdfLabel(person.role_type),
      [
        ["Tipo", pdfLabel(person.person_type)],
        ["Rol en el caso", pdfLabel(person.role_type)],
        ["Identificador", person.identifier],
        ["Curso", relation(person, "course_section", "courseSection")?.display_name],
        ["Contacto", person.contact_reference],
        ["Notas", person.notes],
        ["Registro sensible", yesNo(person.is_sensitive)],
      ],
      "default",
      false,
    )) : [pdfEmpty("No hay otras personas vinculadas.")]),
    pdfSection(4, "SEGUIMIENTOS Y TRAZABILIDAD", `${followUps.length} seguimiento(s) y ${statusLogs.length} cambio(s) de estado.`),
    ...(followUps.length ? followUps.map((followUp) => pdfRecord(
      followUp.title || (followUp.entry_type ? pdfLabel(followUp.entry_type) : "Seguimiento"),
      pdfDate(followUp.follow_up_at, true),
      [
        ["Estado", pdfLabel(followUp.status)],
        ["Responsable", personName(relation(followUp, "responsible_user", "responsibleUser"))],
        ["Notas", followUp.notes],
        ["Próximo seguimiento", pdfDate(followUp.next_follow_up_at, true)],
      ],
      "active",
      true,
    )) : [pdfEmpty("No hay seguimientos registrados.")]),
    ...(statusLogs.length ? [pdfRecord("Historial de estados", `${statusLogs.length} evento(s)`, [["Trazabilidad", statusLogsText(statusLogs)]], "default", false)] : [pdfEmpty("No hay cambios de estado registrados.")]),
    pdfSection(5, "DENUNCIAS Y REGISTROS DE ORIGEN", `${complaints.length} denuncia(s) y ${dailyLogs.length} registro(s) de bitácora relacionados.`),
    ...(complaints.length ? complaints.map((complaint) => pdfRecord(
      complaint.folio || "Denuncia asociada",
      `${pdfLabel(complaint.status)} · ${pdfDate(complaint.received_at, true)}`,
      [
        ["Denunciante", complaint.is_anonymous ? "Anónimo" : complaint.complainant_name],
        ["Tipo de denunciante", pdfLabel(complaint.complainant_type)],
        ["Contacto", [complaint.contact_email, complaint.contact_phone].filter(Boolean).join(" · ")],
        ["Lugar / fecha del hecho", [complaint.place, pdfDate(complaint.happened_at, true)].filter((value) => value && value !== "-").join(" · ")],
        ["Relato", complaint.report_text],
        ["Personas informadas", pdfStructuredText(complaint.involved_snapshot, "")],
        ["Admisibilidad", complaint.admissibility_result],
        ["Adjuntos", attachmentsText(complaint.attachments)],
        ["Cambios de estado", statusLogsText(relation(complaint, "status_logs", "statusLogs"))],
      ],
      "warning",
      false,
    )) : [pdfEmpty("No hay denuncias relacionadas visibles.")]),
    ...(dailyLogs.length ? dailyLogs.map((entry) => pdfRecord(
      entry.daily_log_type_label || entry.type?.name || "Registro de bitácora",
      `${pdfDate(entry.happened_at, true)} · ${pdfLabel(entry.status)}`,
      [
        ["Lugar", entry.place],
        ["Descripción", entry.description],
        ["Acción inmediata", entry.immediate_action],
        ["Personas informadas", pdfStructuredText(entry.involved_snapshot, "")],
        ["Apoderado informado", yesNo(entry.guardian_informed)],
        ["Constancia de contacto", entry.guardian_contact_note],
        ["Responsable", personName(relation(entry, "inspector_user", "inspectorUser"))],
        ["Adjuntos", attachmentsText(entry.attachments)],
        ["Cambios de estado", statusLogsText(relation(entry, "status_logs", "statusLogs"))],
      ],
      "default",
      false,
    )) : [pdfEmpty("No hay registros de bitácora relacionados visibles.")]),
    pdfSection(6, "ENTREVISTAS", `${interviews.length} entrevista(s) relacionada(s).`),
    ...(interviews.length ? interviews.map((interview) => pdfRecord(
      interview.interview_type_label || interview.type?.name || "Entrevista",
      `${pdfDate(interview.interview_at, true)} · ${pdfLabel(interview.follow_up_status)}`,
      [
        ["Responsable", personName(relation(interview, "responsible_user", "responsibleUser"))],
        ["Motivo", interview.motive],
        ["Temas tratados", interview.topics],
        ["Participantes", participantSummary(interview.participants)],
        ["Acuerdos", interview.agreements],
        ["Compromisos", interview.commitments],
        ["Fecha de seguimiento", pdfDate(interview.follow_up_date)],
        ["Notas internas", interview.internal_notes],
        ["Adjuntos", attachmentsText(interview.attachments)],
        ["Cambios de estado", statusLogsText(relation(interview, "status_logs", "statusLogs"))],
      ],
      "active",
      false,
    )) : [pdfEmpty("No hay entrevistas relacionadas visibles.")]),
    pdfSection(7, "DERIVACIONES", `${derivations.length} derivación(es) interna(s) o externa(s).`),
    ...(derivations.length ? derivations.map((derivation) => pdfRecord(
      derivation.destination_label
        || relation(derivation, "destination_department", "destinationDepartment")?.name
        || relation(derivation, "external_institution", "externalInstitution")?.name
        || "Derivación",
      `${pdfLabel(derivation.scope)} · ${pdfLabel(derivation.status)} · ${pdfLabel(derivation.priority_level)}`,
      [
        ["Responsable", personName(relation(derivation, "responsible_user", "responsibleUser"))],
        ["Profesional destino", personName(relation(derivation, "destination_staff", "destinationStaff"))],
        ["Usuario destino", personName(relation(derivation, "destination_user", "destinationUser"))],
        ["Confidencialidad", pdfLabel(derivation.confidentiality_level)],
        ["Fecha de derivación", pdfDate(derivation.derived_at, true)],
        ["Enviada", pdfDate(derivation.sent_at, true)],
        ["Plazo de respuesta", pdfDate(derivation.response_due_at, true)],
        ["Respondida", pdfDate(derivation.responded_at, true)],
        ["Cerrada", pdfDate(derivation.closed_at, true)],
        ["Motivo", derivation.motive],
        ["Antecedentes enviados", derivation.narrative],
        ["Acciones sugeridas", derivation.suggested_actions],
        ["Respuesta", derivation.response_text],
        ["Seguimiento", derivation.follow_up_notes],
        ["Contacto externo", [derivation.external_contact_name, derivation.external_contact_email, derivation.external_contact_phone].filter(Boolean).join(" · ")],
        ["Adjuntos", attachmentsText(derivation.attachments)],
        ["Cambios de estado", statusLogsText(relation(derivation, "status_logs", "statusLogs"))],
      ],
      derivation.priority_level === "urgente" ? "warning" : "default",
      false,
    )) : [pdfEmpty("No hay derivaciones relacionadas visibles.")]),
    pdfSection(8, "MEDIDAS FORMATIVAS, REPARATORIAS Y DE RESGUARDO", `${measures.length} medida(s) asociada(s).`),
    ...(measures.length ? measures.map((measure) => pdfRecord(
      measure.measure_type_label || measure.type?.name || "Medida",
      `${pdfLabel(measure.status)} · ${pdfDate(measure.assigned_at, true)}`,
      [
        ["Responsable", personName(relation(measure, "responsible_user", "responsibleUser"))],
        ["Descripción", measure.description],
        ["Objetivo formativo", measure.training_objective],
        ["Fecha de cumplimiento", pdfDate(measure.due_at, true)],
        ["Evidencia", measure.evidence_summary],
        ["Reflexión del estudiante", measure.student_reflection],
        ["Acción reparatoria", measure.repair_action],
        ["Notas del responsable", measure.responsible_notes],
        ["Cierre", measure.closure_notes],
        ["Fecha de cierre", pdfDate(measure.closed_at, true)],
        ["Adjuntos", attachmentsText(measure.attachments)],
        ["Cambios de estado", statusLogsText(relation(measure, "status_logs", "statusLogs"))],
      ],
      "protected",
      false,
    )) : [pdfEmpty("No hay medidas relacionadas visibles.")]),
    pdfSection(9, "PROTOCOLOS ACTIVADOS", `${activations.length} protocolo(s) asociado(s) al expediente.`),
    ...(activations.length ? activations.flatMap((activation) => {
      const protocol = activation.protocol || {};
      const snapshot = activation.protocol_snapshot || {};
      const steps = activationSteps(activation);
      const globalParts = pdfArray(relation(activation, "runtime_parts", "runtimeParts"))
        .filter((part) => !part.activation_step_id);
      return [
        pdfRecord(
          protocol.name || activation.protocol_snapshot?.name || "Protocolo activado",
          `${pdfLabel(activation.status)} · ${Number(activation.progress_percentage || 0)}%`,
          [
            ["Activado", pdfDate(activation.activated_at, true)],
            ["Activado por", personName(relation(activation, "activated_by", "activatedBy"))],
            ["Etapa actual", activation.current_stage_name || relation(activation, "current_activation_step", "currentActivationStep")?.stage_name],
            ["Vencimiento", pdfDate(activation.due_at, true)],
            ["Versión activada", [protocol.code || snapshot.code, protocol.version_label || snapshot.version_label].filter(Boolean).join(" · ")],
            ["Fuente reglamentaria", snapshot.regulatory_source],
            ["Referencia legal", snapshot.legal_reference],
            ["Documentos requeridos", snapshot.required_documents],
            ["Resguardos del protocolo", snapshot.safeguard_measures],
            ["Acciones mínimas", snapshot.minimal_actions],
            ["Personas involucradas al activar", pdfStructuredText(activation.involved_snapshot, "")],
            ["Acciones realizadas", activation.actions_taken],
            ["Medidas adoptadas", activation.measures_adopted],
            ["Resumen de cierre", activation.closing_summary],
            ["Fecha de cierre", pdfDate(activation.closed_at, true)],
            ["Componentes generales", runtimePartSummary(globalParts)],
            ["Adjuntos", attachmentsText(activation.attachments)],
            ["Bitácora de activación", pdfArray(activation.logs).map((entry) => `${pdfDate(entry.created_at, true)} | ${pdfLabel(entry.action_type)} | ${entry.stage_name || ""} | ${entry.notes || ""}`).join("\n")],
          ],
          "active",
          false,
        ),
        ...(steps.length ? steps.map((step) => pdfRecord(
          `${Number(step.step_order || 0)}. ${step.stage_name || step.code || "Etapa"}`,
          `${pdfLabel(step.status)} · vence ${pdfDate(step.due_at, true)}`,
          [
            ["Responsable", step.responsible_label],
            ["Descripción", step.description],
            ["Inicio", pdfDate(step.started_at, true)],
            ["Término", pdfDate(step.completed_at, true)],
            ["Notas", step.notes],
            ["Resultado", step.outcome],
            ["Evidencia", step.evidence_summary],
            ["Criterios y datos", pdfStructuredText(step.data, "")],
            ["Instantánea de la etapa", pdfStructuredText(step.snapshot, "")],
            ["Componentes", runtimePartSummary(step.parts)],
          ],
          "default",
          false,
        )) : [pdfEmpty("Esta activación no tiene etapas materializadas visibles.")]),
      ];
    }) : [pdfEmpty("No hay protocolos activados relacionados.")]),
    pdfSection(10, "DOCUMENTOS DEL CASO", `${attachments.length} archivo(s) directo(s) visible(s).`),
    ...(attachments.length ? attachments.map((attachment) => pdfRecord(
      attachment.original_name || "Archivo adjunto",
      `${pdfLabel(attachment.category)} · ${bytes(attachment.file_size)}`,
      [
        ["Tipo MIME", attachment.mime_type],
        ["Confidencialidad", pdfLabel(attachment.confidentiality_level)],
        ["Archivo sensible", yesNo(attachment.is_sensitive)],
        ["Notas", attachment.notes],
        ["Subido por", personName(relation(attachment, "uploaded_by", "uploadedBy"))],
        ["Fecha", pdfDate(attachment.created_at, true)],
      ],
      attachment.requires_sensitive_access ? "warning" : "default",
      false,
    )) : [pdfEmpty("No hay documentos directos visibles en el expediente.")]),
    { ...pdfSection(11, "RESOLUCIÓN Y CIERRE", "Conclusiones formales y control final del expediente."), pageBreak: "before" },
    pdfFacts([
      ["Resolución", record.resolution],
      ["Conclusión", record.conclusion],
      ["Estado final", pdfLabel(record.status)],
      ["Fecha de cierre", pdfDate(record.closed_at, true)],
      ["Cerrado por", closedBy.name],
      ["Última actualización", pdfDate(record.updated_at, true)],
    ]),
  ];

  return buildConvivenciaPdfDefinition({
    title: "Expediente de caso",
    kicker: "Convivencia Escolar | Ficha integral",
    code,
    status: record.status,
    subtitle: `${studentLabel} · ${classification || "Caso de convivencia escolar"}`,
    sensitive: record.is_sensitive !== false,
    generatedAt: generatedAt || payload?.generated_at || new Date().toISOString(),
    content,
    info: {
      subject: `Expediente integral de Convivencia Escolar ${code}`,
      keywords: "convivencia escolar, caso, expediente, seguimiento, entrevistas, medidas, protocolos",
    },
  });
}

export async function downloadConvivenciaCasePdf(payload) {
  const record = resolveConvivenciaCase(payload);
  const definition = buildConvivenciaCasePdfDefinition(payload, payload?.generated_at);
  const folio = safePdfFilePart(record.folio || record.id || "caso");
  (await getPdfMake()).createPdf(definition).download(`expediente-convivencia-${folio}.pdf`);
}
