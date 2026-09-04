import { getPdfMake } from './pdfmake'

const text = (value) => {
  if (value == null || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'Sí' : 'No'
  if (Array.isArray(value)) return value.length ? value.map(text).join(', ') : '—'
  if (typeof value === 'object') return Object.entries(value).filter(([, item]) => item != null && item !== '').map(([key, item]) => `${String(key).replaceAll('_', ' ')}: ${text(item)}`).join(' · ') || '—'
  return String(value).replaceAll('_', ' ')
}
const date = (value, withTime = false) => {
  if (!value) return '—'
  const source = String(value)
  const parsed = new Date(source.length === 10 ? `${source}T12:00:00` : source)
  if (Number.isNaN(parsed.getTime())) return text(value)
  return new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium', ...(withTime ? { timeStyle: 'short' } : {}) }).format(parsed)
}
const studentName = (student) => student?.registered_name_resolved || student?.registered_name || [student?.first_name, student?.last_name].filter(Boolean).join(' ')
const interventionPeople = (item) => {
  const people = (item.participants || []).filter(person => person && typeof person === 'object')
  const participants = people.filter(person => (person.role || 'participant') === 'participant').map(person => person.name).filter(Boolean)
  const supports = people.filter(person => person.role === 'support').map(person => person.name).filter(Boolean)
  return [participants.length ? `Participan: ${participants.join(', ')}` : '', supports.length ? `Apoyan: ${supports.join(', ')}` : ''].filter(Boolean).join('\n') || '—'
}

const section = (number, title, detail = '') => ({ margin: [0, 9, 0, 7], columns: [
  { width: 26, text: number, style: 'sectionNumber' },
  { width: '*', stack: [{ text: title, style: 'sectionTitle' }, ...(detail ? [{ text: detail, style: 'sectionDetail' }] : [])] },
] })
const empty = message => ({ text: message, style: 'empty', margin: [0, 0, 0, 10] })
const record = (title, meta, rows, protectedContent = false) => ({
  unbreakable: true,
  table: { widths: ['*'], body: [[{ margin: [9, 8, 9, 8], fillColor: protectedContent ? '#fff9f3' : '#f8fafc', stack: [
    { columns: [{ text: title, style: 'recordTitle' }, { text: meta, style: 'recordMeta', alignment: 'right' }] },
    ...rows.filter(([, value]) => value != null && value !== '').map(([name, value]) => ({ margin: [0, 4, 0, 0], text: [{ text: `${name}: `, bold: true, color: '#526175' }, { text: text(value) }] })),
  ] }]] },
  layout: { hLineWidth: () => .65, vLineWidth: () => .65, hLineColor: () => protectedContent ? '#edd8c5' : '#dfe6ee', vLineColor: () => protectedContent ? '#edd8c5' : '#dfe6ee' },
  margin: [0, 0, 0, 7],
})

export function buildSocialCasePdfDefinition(caseData, generatedAt = new Date().toISOString()) {
  const interventions = caseData.interventions || []
  const statuses = caseData.status_history || []
  const alerts = caseData.alerts || []
  const reopenings = caseData.reopenings || []
  const referrals = caseData.referrals || []
  const protocols = caseData.protocols || []
  const reports = caseData.reports || []
  const documents = caseData.documents || []
  const commitments = caseData.commitments || []
  const requested = caseData.requested_information || []
  const assessments = caseData.risk_assessments || []
  const code = text(caseData.code)
  return {
    pageSize: 'A4', pageMargins: [38, 42, 38, 54],
    info: { title: `Ficha social ${code}`, subject: 'Ficha maestra de caso de Trabajo Social', author: 'CNSC Gestión' },
    watermark: { text: caseData.status === 'cerrado' ? 'CONFIDENCIAL' : 'BORRADOR CONFIDENCIAL', color: '#66527f', opacity: 0.055, bold: true },
    footer: (current, total) => ({ margin: [38, 10, 38, 0], stack: [
      { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 519, y2: 0, lineWidth: 0.6, lineColor: '#d8dee7' }] },
      { margin: [0, 6, 0, 0], columns: [{ text: `CNSC Gestión · ${code} · Emitido ${date(generatedAt, true)}`, fontSize: 6.8, color: '#68778a' }, { text: `Página ${current} de ${total}`, alignment: 'right', fontSize: 6.8, color: '#68778a' }] },
    ] }),
    content: [
      { table: { widths: ['*'], body: [[{ margin: [15, 13, 15, 13], fillColor: '#28364d', color: '#ffffff', columns: [
        { width: '*', stack: [{ text: 'TRABAJO SOCIAL', fontSize: 8, bold: true, characterSpacing: 1.4, color: '#c8d5e8' }, { text: 'FICHA MAESTRA DE CASO', fontSize: 17, bold: true, margin: [0, 3, 0, 0] }, { text: text(caseData.title), fontSize: 9, color: '#e5eaf1', margin: [0, 4, 0, 0] }] },
        { width: 112, alignment: 'right', stack: [{ text: code, fontSize: 11, bold: true }, { text: caseData.status === 'cerrado' ? 'DOCUMENTO CERRADO' : 'BORRADOR PARA REVISIÓN', fontSize: 6.5, color: '#f1cf86', margin: [0, 5, 0, 0] }] },
      ] }]] }, layout: 'noBorders', margin: [0, 0, 0, 12] },
      { table: { widths: ['*', '*', '*', '*'], body: [[
        { stack: [{ text: 'ALUMNA', style: 'metaLabel' }, { text: studentName(caseData.student), style: 'metaValue' }], style: 'metaCell' },
        { stack: [{ text: 'RUT / CURSO', style: 'metaLabel' }, { text: `${text(caseData.student?.rut)} · ${text(caseData.course_section?.display_name)}`, style: 'metaValue' }], style: 'metaCell' },
        { stack: [{ text: 'ESTADO', style: 'metaLabel' }, { text: text(caseData.status), style: 'metaValue' }], style: 'metaCell' },
        { stack: [{ text: 'RIESGO / PRIORIDAD', style: 'metaLabel' }, { text: `${text(caseData.risk_level)} · ${text(caseData.priority)}`, style: 'metaValue' }], style: 'metaCell' },
      ]] }, layout: { hLineWidth: () => .5, vLineWidth: () => .5, hLineColor: () => '#dbe2ea', vLineColor: () => '#dbe2ea' }, margin: [0, 0, 0, 15] },
      section('01', 'APERTURA Y ENCUADRE', 'Identificación, clasificación y antecedentes autorizados.'),
      { table: { widths: [105, '*'], body: [
        [{ text: 'Fecha de recepción', style: 'fieldLabel' }, date(caseData.received_on)],
        [{ text: 'Fecha de apertura', style: 'fieldLabel' }, date(caseData.opened_on)],
        [{ text: 'Responsable', style: 'fieldLabel' }, text(caseData.responsible?.name)],
        [{ text: 'Año académico', style: 'fieldLabel' }, text(caseData.academic_year?.name || caseData.academic_year?.year)],
        [{ text: 'Origen / tipo', style: 'fieldLabel' }, `${text(caseData.origin)} · ${text(caseData.case_type)}`],
        [{ text: 'Privacidad', style: 'fieldLabel' }, text(caseData.confidentiality)],
        [{ text: 'Motivo', style: 'fieldLabel' }, text(caseData.reason)],
        [{ text: 'Antecedentes iniciales', style: 'fieldLabel' }, text(caseData.initial_description)],
        [{ text: 'Protocolo inicial', style: 'fieldLabel' }, text(caseData.initial_protocol)],
        [{ text: 'Medidas de resguardo', style: 'fieldLabel' }, text(caseData.initial_safeguards)],
        [{ text: 'Próximo hito', style: 'fieldLabel' }, `${text(caseData.next_milestone)} · ${date(caseData.due_at)}`],
      ] }, layout: { hLineWidth: (i) => i === 0 ? 0 : .45, vLineWidth: () => 0, hLineColor: () => '#e1e6ed', fillColor: (row) => row % 2 ? '#fafbfc' : null }, margin: [0, 0, 0, 15] },
      section('02', 'PERSONAS VINCULADAS'),
      ...((caseData.students || []).length ? caseData.students.map(item => record(studentName(item), `${text(item.rut)} · ${item.pivot?.is_primary ? 'Titular' : 'Vinculada'}`, [['Relación', text(item.pivot?.relationship)], ['Contacto apoderado/a', [item.guardian_name, item.guardian_phone, item.guardian_email].filter(Boolean).join(' · ')]])) : [record(studentName(caseData.student), text(caseData.student?.rut), [['Curso', caseData.course_section?.display_name], ['Apoderado/a', caseData.student?.guardian_name], ['Contacto', [caseData.student?.guardian_phone, caseData.student?.guardian_email].filter(Boolean).join(' · ')]])]),
      section('03', 'TRAZABILIDAD DE ESTADOS Y REAPERTURAS'),
      ...(statuses.length ? statuses.map(item => record(`${text(item.from_status)} → ${text(item.to_status)}`, `${date(item.changed_at, true)} · ${text(item.user?.name)}`, [['Motivo', item.reason], ['Notas', item.notes]])) : [empty('Sin cambios de estado registrados.')]),
      ...(reopenings.map(item => record('Reapertura del caso', `${date(item.reopened_at, true)} · ${text(item.user?.name)}`, [['Motivo', item.reason], ['Conclusión previa preservada', item.previous_conclusion], ['Riesgo / prioridad', `${text(item.risk_level)} · ${text(item.priority)}`], ['Próxima acción', item.next_action]]))),
      section('04', 'INTERVENCIONES Y ATENCIONES', `${interventions.length} actuación(es) profesional(es).`),
      ...(interventions.length ? interventions.map((item, index) => record(`${index + 1}. ${text(item.kind)} · ${text(item.objective)}`, `${date(item.activity_date)} · ${text(item.status)}`, [
        ['Profesional responsable', item.responsible?.name], ['Horario', [item.starts_at, item.ends_at].filter(Boolean).join(' – ')], ['Modalidad / lugar', [text(item.modality), item.place].filter(Boolean).join(' · ')],
        ['Participantes / apoyos', interventionPeople(item)], ['Descripción', item.description], ['Observaciones profesionales', item.professional_observations], ['Contenido altamente confidencial autorizado', item.highly_confidential_notes], ['Resultado', item.result], ['Acuerdos', item.agreements], ['Próxima acción', [item.next_action, date(item.due_at, true)].filter(value => value && value !== '—').join(' · ')], ['Próxima intervención', date(item.next_intervention_at, true)], ['Compromisos', (item.commitments || []).map(commitment => `${text(commitment.description)} · ${date(commitment.due_at, true)} · ${text(commitment.status)}`)],
      ], Boolean(item.highly_confidential_notes))) : [empty('Sin intervenciones registradas.')]),
      section('05', 'COMPROMISOS, ANTECEDENTES SOLICITADOS Y RIESGOS'),
      ...(commitments.map(item => record(text(item.description), `${date(item.due_at, true)} · ${text(item.status)}`, [['Responsable', item.responsible?.name], ['Resultado', item.result]]))),
      ...(requested.map(item => record(`Antecedente solicitado · ${text(item.item)}`, `${date(item.requested_at, true)} · ${text(item.status)}`, [['Solicitado a', item.requested_from], ['Fecha límite', date(item.due_at, true)], ['Notas', item.notes]]))),
      ...(assessments.map(item => record(`Evaluación de riesgo · ${text(item.final_level)}`, `${date(item.assessed_at, true)}`, [['Período', `${date(item.period_from)} – ${date(item.period_to)}`], ['Indicadores', item.indicators], ['Fuentes', item.source_snapshot], ['Modificación profesional', item.manually_overridden], ['Justificación', item.override_justification], ['Notas', item.notes]], true))),
      ...(!commitments.length && !requested.length && !assessments.length ? [empty('Sin compromisos, antecedentes solicitados ni evaluaciones de riesgo registradas.')] : []),
      section('06', 'ALERTAS Y DERIVACIONES'),
      ...(alerts.length ? alerts.map(item => record(`${text(item.type)} · ${text(item.severity)}`, `${date(item.alerted_at, true)} · ${text(item.status)}`, [['Motivo', item.reason], ['Acción recomendada', item.recommended_action], ['Responsable', item.responsible?.name], ['Fecha límite', date(item.due_at, true)], ['Resolución', item.resolution]])) : [empty('Sin alertas vinculadas.')]),
      ...(referrals.map(item => record(`Derivación ${text(item.code || item.id)}`, `${date(item.referral_date)} · ${text(item.status)}`, [['Motivo', item.reason], ['Descripción', item.description], ['Asignada a', item.assigned_user?.name], ['Prioridad', item.priority]]))),
      section('07', 'PROTOCOLOS Y RECEPCIÓN INICIAL'),
      ...(caseData.protocol_zero ? [record('Protocolo cero', `${date(caseData.protocol_zero.received_at, true)} · ${text(caseData.protocol_zero.status)}`, [['Canal', caseData.protocol_zero.channel], ['Informante / relación', [caseData.protocol_zero.informant_name, caseData.protocol_zero.informant_relationship].filter(Boolean).join(' · ')], ['Relato inicial autorizado', caseData.protocol_zero.initial_account], ['Riesgo inmediato', caseData.protocol_zero.immediate_risk], ['Atención urgente', caseData.protocol_zero.urgent_attention], ['Resguardos iniciales', caseData.protocol_zero.initial_safeguards], ['Personas notificadas', caseData.protocol_zero.notified_people], ['Antecedentes pendientes', caseData.protocol_zero.pending_background]], true)] : []),
      ...(protocols.length ? protocols.map(item => record(`${text(item.protocol?.code)} · ${text(item.protocol?.name)}`, `${date(item.activated_at, true)} · ${text(item.status)}`, [['Motivo', item.reason], ['Etapa actual', item.current_step], ['Fecha límite', date(item.due_at, true)], ['Conclusión', item.conclusion], ['Versión preservada', item.version_snapshot], ['Registros vinculados', (item.step_links || []).length]])) : (!caseData.protocol_zero ? [empty('Sin protocolos activados.')] : [])),
      section('08', 'INFORMES Y DOCUMENTOS'),
      ...(reports.map(item => record(`${text(item.type)} · ${text(item.status)}`, `${date(item.issued_at, true)}`, [['Título', item.title], ['Período', `${date(item.period_from)} – ${date(item.period_to)}`], ['Versiones', (item.versions || []).map(version => `v${text(version.version)} · ${date(version.created_at, true)} · ${text(version.change_reason)}\n${text(version.content)}`)], ['Aprobación', date(item.approved_at, true)]]))),
      ...(documents.map(item => record(text(item.original_name || item.filename), `${text(item.category)} · ${text(item.status)}`, [['Descripción', item.description], ['Tipo / tamaño', `${text(item.mime_type)} · ${text(item.size_bytes)} bytes`], ['Etiquetas', item.tags], ['Vigencia', date(item.valid_until)], ['Fecha', date(item.created_at, true)]]))),
      ...(!reports.length && !documents.length ? [empty('Sin informes ni documentos adjuntos.')] : []),
      section('09', 'CIERRE PROFESIONAL'),
      { table: { widths: [105, '*'], body: [
        [{ text: 'Fecha de cierre', style: 'fieldLabel' }, date(caseData.closed_at, true)], [{ text: 'Resultado', style: 'fieldLabel' }, text(caseData.closure_result)], [{ text: 'Motivo', style: 'fieldLabel' }, text(caseData.closure_reason)], [{ text: 'Conclusión', style: 'fieldLabel' }, text(caseData.closure_conclusion)], [{ text: 'Riesgo final', style: 'fieldLabel' }, text(caseData.final_risk_level)], [{ text: 'Seguimiento posterior', style: 'fieldLabel' }, text(caseData.post_closure_follow_up)],
      ] }, layout: 'lightHorizontalLines', margin: [0, 0, 0, 12] },
      { columns: [
        { width: '46%', margin: [0, 42, 0, 0], stack: [{ canvas: [{ type: 'line', x1: 0, y1: 0, x2: 220, y2: 0, lineWidth: .7, lineColor: '#6d7887' }] }, { text: text(caseData.responsible?.name), alignment: 'center', fontSize: 7.5, margin: [0, 5, 0, 0] }, { text: 'Profesional responsable', alignment: 'center', fontSize: 6.5, color: '#788595' }] },
        { width: '8%', text: '' },
        { width: '46%', margin: [0, 42, 0, 0], stack: [{ canvas: [{ type: 'line', x1: 0, y1: 0, x2: 220, y2: 0, lineWidth: .7, lineColor: '#6d7887' }] }, { text: 'Revisión / visación', alignment: 'center', fontSize: 7.5, margin: [0, 5, 0, 0] }, { text: 'Nombre, firma y fecha', alignment: 'center', fontSize: 6.5, color: '#788595' }] },
      ] },
      { text: 'Documento confidencial. Contiene únicamente secciones autorizadas para la persona emisora; requiere revisión profesional y no constituye un diagnóstico automático.', style: 'notice', margin: [0, 20, 0, 0] },
    ],
    styles: {
      sectionNumber: { fontSize: 8, bold: true, color: '#665292', alignment: 'center', margin: [0, 3, 0, 3] },
      sectionTitle: { fontSize: 9, bold: true, color: '#5d497c', characterSpacing: .5 },
      sectionDetail: { fontSize: 6.8, color: '#788596', margin: [0, 2, 0, 0] },
      metaCell: { margin: [6, 6, 6, 6] }, metaLabel: { fontSize: 6.2, bold: true, color: '#788596' }, metaValue: { fontSize: 8.2, bold: true, color: '#2f3c4f', margin: [0, 3, 0, 0] },
      fieldLabel: { bold: true, color: '#4d5a6d', fontSize: 7.4 },
      recordTitle: { fontSize: 8.1, bold: true, color: '#3f4c60' }, recordMeta: { fontSize: 6.6, color: '#7a8797' },
      empty: { italics: true, color: '#7c8999', fontSize: 7.4 },
      notice: { fontSize: 7, italics: true, color: '#647184', alignment: 'center' },
    },
    defaultStyle: { fontSize: 7.7, lineHeight: 1.22, color: '#354256' },
  }
}

export async function downloadSocialCaseMaster(payload) {
  const caseData = payload?.data || payload
  const definition = buildSocialCasePdfDefinition(caseData, payload?.generated_at)
  ;(await getPdfMake()).createPdf(definition).download(`ficha-social-${caseData.code}.pdf`)
}

export function downloadSocialCsv(rows, name = 'trabajo-social') {
  const safeRows = rows.map(row => ({ id: row.id, codigo: row.code || '', estudiante: studentName(row.student || row), rut: row.student?.rut || row.rut || '', estado: row.status || '', riesgo: row.risk_level || row.latest_social_case?.risk_level || '', fecha: row.opened_on || row.alerted_at || row.referral_date || '' }))
  const keys = Object.keys(safeRows[0] || { id: '', codigo: '', estudiante: '', rut: '', estado: '', riesgo: '', fecha: '' })
  const csv = [keys, ...safeRows.map(row => keys.map(key => row[key]))].map(line => line.map(value => `"${String(value ?? '').replaceAll('"', '""')}"`).join(';')).join('\n')
  const url = URL.createObjectURL(new Blob([`\ufeff${csv}`], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a'); link.href = url; link.download = `${name}-${new Date().toISOString().slice(0, 10)}.csv`; link.click(); URL.revokeObjectURL(url)
}

export async function downloadJunaebDeliveryAct(delivery) {
  const benefit = delivery.benefit || {}
  const student = benefit.student || {}
  const items = (delivery.items || []).map(item => [text(item.name), text(item.quantity), text(item.unit || 'unidad')])
  const definition = {
    pageSize: 'A4',
    pageMargins: [48, 56, 48, 56],
    watermark: { text: 'ACTA DE ENTREGA', color: '#5e4a82', opacity: 0.045, bold: true },
    footer: (current, total) => ({ margin: [48, 12], columns: [{ text: `Folio ${text(delivery.folio)} · Documento generado ${new Date().toLocaleString('es-CL')}`, fontSize: 7, color: '#69768a' }, { text: `Página ${current} de ${total}`, alignment: 'right', fontSize: 7 }] }),
    content: [
      { text: 'ACTA DE ENTREGA DE ÚTILES ESCOLARES', style: 'title' },
      { text: 'Programa JUNAEB · Registro institucional', style: 'subtitle' },
      { table: { widths: ['25%', '*'], body: [
        [{ text: 'Folio', bold: true }, text(delivery.folio)],
        [{ text: 'Alumna', bold: true }, studentName(student)],
        [{ text: 'RUT', bold: true }, text(student.rut)],
        [{ text: 'Curso', bold: true }, text(benefit.course_section?.display_name)],
        [{ text: 'Beneficio', bold: true }, text(benefit.benefit_type?.name)],
        [{ text: 'Fecha de entrega', bold: true }, date(delivery.delivered_on)],
      ] }, layout: 'lightHorizontalLines', margin: [0, 16, 0, 18] },
      { text: 'Detalle de artículos entregados', style: 'heading' },
      { table: { headerRows: 1, widths: ['*', 70, 90], body: [['Artículo', 'Cantidad', 'Unidad'], ...(items.length ? items : [['Sin detalle de artículos', '—', '—']])] }, layout: 'lightHorizontalLines', margin: [0, 7, 0, 20] },
      { text: `Declaro haber recibido conforme los artículos individualizados en esta acta para la estudiante indicada.\n\nPersona receptora: ${text(delivery.receiver_name)}\nRelación con la alumna: ${text(delivery.receiver_relationship)}\nObservaciones: ${text(delivery.notes)}`, lineHeight: 1.35 },
      { columns: [
        { width: '45%', margin: [0, 58, 0, 0], stack: [{ text: '_______________________________', alignment: 'center' }, { text: 'Firma persona receptora', alignment: 'center', fontSize: 8 }] },
        { width: '10%', text: '' },
        { width: '45%', margin: [0, 58, 0, 0], stack: [{ text: '_______________________________', alignment: 'center' }, { text: `Responsable: ${text(delivery.responsible?.name)}`, alignment: 'center', fontSize: 8 }] },
      ] },
      { text: 'Documento emitido desde el módulo de Trabajo Social. Verifique firmas y respaldo institucional antes de archivar.', style: 'notice', margin: [0, 28, 0, 0] },
    ],
    styles: {
      title: { fontSize: 16, bold: true, color: '#2d3b50', alignment: 'center' },
      subtitle: { fontSize: 9, color: '#695683', alignment: 'center', margin: [0, 4, 0, 0] },
      heading: { fontSize: 10, bold: true, color: '#55446f' },
      notice: { fontSize: 7.5, italics: true, color: '#68778a' },
    },
    defaultStyle: { fontSize: 9, lineHeight: 1.25 },
  };
  (await getPdfMake()).createPdf(definition).download(`acta-entrega-${delivery.folio || delivery.id}.pdf`)
}
