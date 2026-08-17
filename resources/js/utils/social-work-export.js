import { getPdfMake } from './pdfmake'

const text = (value) => value == null || value === '' ? '—' : String(value).replaceAll('_', ' ')
const date = (value) => value ? new Intl.DateTimeFormat('es-CL', { dateStyle: 'medium' }).format(new Date(value)) : '—'
const studentName = (student) => student?.registered_name_resolved || student?.registered_name || [student?.first_name, student?.last_name].filter(Boolean).join(' ')

export function downloadSocialCaseMaster(caseData) {
  const interventions = (caseData.interventions || []).map(item => [date(item.activity_date), text(item.kind), text(item.objective), text(item.result || item.description), text(item.next_action)])
  const statuses = (caseData.status_history || []).map(item => [date(item.changed_at), text(item.from_status), text(item.to_status), text(item.reason)])
  const alerts = (caseData.alerts || []).map(item => [date(item.alerted_at), text(item.severity), text(item.type), text(item.reason), text(item.status)])
  const code = text(caseData.code)
  const definition = {
    pageSize: 'A4', pageMargins: [38, 42, 38, 54],
    info: { title: `Ficha social ${code}`, subject: 'Ficha maestra de caso de Trabajo Social', author: 'CNSC Gestión' },
    watermark: { text: caseData.status === 'cerrado' ? 'CONFIDENCIAL' : 'BORRADOR CONFIDENCIAL', color: '#66527f', opacity: 0.055, bold: true },
    footer: (current, total) => ({ margin: [38, 10, 38, 0], stack: [
      { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 519, y2: 0, lineWidth: 0.6, lineColor: '#d8dee7' }] },
      { margin: [0, 6, 0, 0], columns: [{ text: `CNSC Gestión · ${code} · Emitido ${new Date().toLocaleString('es-CL')}`, fontSize: 6.8, color: '#68778a' }, { text: `Página ${current} de ${total}`, alignment: 'right', fontSize: 6.8, color: '#68778a' }] },
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
      { text: '01  APERTURA Y ENCUADRE', style: 'sectionTitle' },
      { table: { widths: [105, '*'], body: [
        [{ text: 'Fecha de apertura', style: 'fieldLabel' }, date(caseData.opened_on)],
        [{ text: 'Responsable', style: 'fieldLabel' }, text(caseData.responsible?.name)],
        [{ text: 'Origen / tipo', style: 'fieldLabel' }, `${text(caseData.origin)} · ${text(caseData.case_type)}`],
        [{ text: 'Motivo', style: 'fieldLabel' }, text(caseData.reason)],
        [{ text: 'Antecedentes iniciales', style: 'fieldLabel' }, text(caseData.initial_description)],
        [{ text: 'Medidas de resguardo', style: 'fieldLabel' }, text(caseData.initial_safeguards)],
        [{ text: 'Próximo hito', style: 'fieldLabel' }, `${text(caseData.next_milestone)} · ${date(caseData.due_at)}`],
      ] }, layout: { hLineWidth: (i) => i === 0 ? 0 : .45, vLineWidth: () => 0, hLineColor: () => '#e1e6ed', fillColor: (row) => row % 2 ? '#fafbfc' : null }, margin: [0, 0, 0, 15] },
      { text: '02  TRAZABILIDAD DE ESTADOS', style: 'sectionTitle' },
      { table: { headerRows: 1, widths: [68, 70, 70, '*'], body: [['Fecha', 'Estado anterior', 'Nuevo estado', 'Motivo'], ...(statuses.length ? statuses : [['—', '—', text(caseData.status), 'Sin cambios de estado registrados']])] }, layout: 'lightHorizontalLines', margin: [0, 0, 0, 15] },
      { text: '03  INTERVENCIONES Y ATENCIONES', style: 'sectionTitle' },
      { table: { headerRows: 1, widths: [55, 62, 96, '*', 82], body: [['Fecha', 'Tipo', 'Objetivo', 'Resultado / resumen', 'Próxima acción'], ...(interventions.length ? interventions : [['—', '—', '—', 'Sin intervenciones registradas', '—']])] }, layout: 'lightHorizontalLines', margin: [0, 0, 0, 15] },
      { text: '04  ALERTAS VINCULADAS', style: 'sectionTitle' },
      { table: { headerRows: 1, widths: [55, 52, 75, '*', 58], body: [['Fecha', 'Severidad', 'Tipo', 'Motivo', 'Estado'], ...(alerts.length ? alerts : [['—', '—', '—', 'Sin alertas vinculadas', '—']])] }, layout: 'lightHorizontalLines', margin: [0, 0, 0, 15] },
      { text: '05  CIERRE PROFESIONAL', style: 'sectionTitle' },
      { table: { widths: ['*'], body: [[{ text: text(caseData.closure_conclusion), margin: [9, 9, 9, 9], fillColor: '#f7f8fb' }]] }, layout: { hLineWidth: () => .6, vLineWidth: () => .6, hLineColor: () => '#dce2e9', vLineColor: () => '#dce2e9' } },
      { columns: [
        { width: '46%', margin: [0, 42, 0, 0], stack: [{ canvas: [{ type: 'line', x1: 0, y1: 0, x2: 220, y2: 0, lineWidth: .7, lineColor: '#6d7887' }] }, { text: text(caseData.responsible?.name), alignment: 'center', fontSize: 7.5, margin: [0, 5, 0, 0] }, { text: 'Profesional responsable', alignment: 'center', fontSize: 6.5, color: '#788595' }] },
        { width: '8%', text: '' },
        { width: '46%', margin: [0, 42, 0, 0], stack: [{ canvas: [{ type: 'line', x1: 0, y1: 0, x2: 220, y2: 0, lineWidth: .7, lineColor: '#6d7887' }] }, { text: 'Revisión / visación', alignment: 'center', fontSize: 7.5, margin: [0, 5, 0, 0] }, { text: 'Nombre, firma y fecha', alignment: 'center', fontSize: 6.5, color: '#788595' }] },
      ] },
      { text: 'Documento confidencial. Contiene únicamente secciones autorizadas para la persona emisora; requiere revisión profesional y no constituye un diagnóstico automático.', style: 'notice', margin: [0, 20, 0, 0] },
    ],
    styles: {
      sectionTitle: { fontSize: 9, bold: true, color: '#5d497c', characterSpacing: .5, margin: [0, 3, 0, 7] },
      metaCell: { margin: [6, 6, 6, 6] }, metaLabel: { fontSize: 6.2, bold: true, color: '#788596' }, metaValue: { fontSize: 8.2, bold: true, color: '#2f3c4f', margin: [0, 3, 0, 0] },
      fieldLabel: { bold: true, color: '#4d5a6d', fontSize: 7.4 },
      notice: { fontSize: 7, italics: true, color: '#647184', alignment: 'center' },
    },
    defaultStyle: { fontSize: 7.7, lineHeight: 1.22, color: '#354256' },
  }
  getPdfMake().createPdf(definition).download(`ficha-social-${caseData.code}.pdf`)
}

export function downloadSocialCsv(rows, name = 'trabajo-social') {
  const safeRows = rows.map(row => ({ id: row.id, codigo: row.code || '', estudiante: studentName(row.student || row), rut: row.student?.rut || row.rut || '', estado: row.status || '', riesgo: row.risk_level || row.latest_social_case?.risk_level || '', fecha: row.opened_on || row.alerted_at || row.referral_date || '' }))
  const keys = Object.keys(safeRows[0] || { id: '', codigo: '', estudiante: '', rut: '', estado: '', riesgo: '', fecha: '' })
  const csv = [keys, ...safeRows.map(row => keys.map(key => row[key]))].map(line => line.map(value => `"${String(value ?? '').replaceAll('"', '""')}"`).join(';')).join('\n')
  const url = URL.createObjectURL(new Blob([`\ufeff${csv}`], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a'); link.href = url; link.download = `${name}-${new Date().toISOString().slice(0, 10)}.csv`; link.click(); URL.revokeObjectURL(url)
}

export function downloadJunaebDeliveryAct(delivery) {
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
  }
  getPdfMake().createPdf(definition).download(`acta-entrega-${delivery.folio || delivery.id}.pdf`)
}
