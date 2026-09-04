import { beforeEach, describe, expect, it, vi } from 'vitest'

const pdfState = vi.hoisted(() => ({ definition: null, filename: '' }))

vi.mock('../../resources/js/utils/pdfmake', () => ({
  getPdfMake: () => ({
    createPdf: definition => {
      pdfState.definition = definition
      return { download: filename => { pdfState.filename = filename } }
    },
  }),
}))

import { downloadSocialCaseMaster } from '../../resources/js/utils/social-work-export'

describe('Ficha PDF de casos sociales', () => {
  beforeEach(() => { pdfState.definition = null; pdfState.filename = '' })

  it('genera una ficha confidencial completa aun cuando no existen intervenciones', async () => {
    await downloadSocialCaseMaster({
      code: 'TS-2026-00001', title: 'Acompañamiento familiar', status: 'borrador',
      student: { first_name: 'Elena', last_name: 'Soto', rut: '11.111.111-1' },
      course_section: { display_name: '5° básico A' }, priority: 'media', risk_level: 'sin_evaluar',
      reason: 'Solicitud de acompañamiento', opened_on: '2026-08-16', responsible: { name: 'Trabajadora Social' },
      initial_description: 'Antecedentes iniciales objetivos.', initial_safeguards: 'Contacto con apoderada.',
      interventions: [], status_history: [], alerts: [], reopen_count: 0,
    })

    expect(pdfState.definition.pageSize).toBe('A4')
    expect(pdfState.definition.watermark.text).toContain('CONFIDENCIAL')
    expect(JSON.stringify(pdfState.definition.content)).toContain('FICHA MAESTRA DE CASO')
    expect(JSON.stringify(pdfState.definition.content)).toContain('Sin intervenciones registradas')
    expect(pdfState.filename).toBe('ficha-social-TS-2026-00001.pdf')
  })

  it('incluye participantes y funcionarios de apoyo de cada atención', async () => {
    await downloadSocialCaseMaster({
      code: 'TS-2026-00002', title: 'Seguimiento', status: 'borrador',
      student: { first_name: 'Elena', last_name: 'Soto' }, responsible: { name: 'Trabajadora Social' },
      interventions: [{
        activity_date: '2026-08-17', kind: 'entrevista', objective: 'Coordinar apoyo', result: 'Acuerdos adoptados',
        participants: [
          { type: 'guardian', role: 'participant', name: 'María Soto' },
          { type: 'staff', role: 'participant', name: 'Profesor Carlos' },
          { type: 'staff', role: 'support', name: 'Orientadora Ana' },
        ],
      }],
      status_history: [], alerts: [],
    })

    const serialized = JSON.stringify(pdfState.definition.content)
    expect(serialized).toContain('Participantes / apoyos')
    expect(serialized).toContain('Participan: María Soto, Profesor Carlos')
    expect(serialized).toContain('Apoyan: Orientadora Ana')
  })

  it('incorpora la trazabilidad completa del expediente autorizado', async () => {
    await downloadSocialCaseMaster({
      code: 'TS-2026-00003', title: 'Caso integral', status: 'abierto',
      student: { first_name: 'Elena', last_name: 'Soto' }, responsible: { name: 'Trabajadora Social' },
      interventions: [], status_history: [], alerts: [],
      reopenings: [{ reopened_at: '2026-08-20', reason: 'Nuevos antecedentes' }],
      requested_information: [{ item: 'Informe de asistencia', status: 'recibido' }],
      risk_assessments: [{ final_level: 'alto', notes: 'Revisión profesional' }],
      protocol_zero: { status: 'recibido', initial_account: 'Relato autorizado' },
      protocols: [{ status: 'activo', protocol: { code: 'PRO-01', name: 'Protección' } }],
      reports: [{ type: 'informe_social', status: 'borrador', versions: [{ version: 1, content: 'Contenido revisable' }] }],
      documents: [{ original_name: 'respaldo.pdf', category: 'antecedente' }],
    })

    const serialized = JSON.stringify(pdfState.definition.content)
    expect(serialized).toContain('TRAZABILIDAD DE ESTADOS Y REAPERTURAS')
    expect(serialized).toContain('Antecedente solicitado')
    expect(serialized).toContain('Evaluación de riesgo')
    expect(serialized).toContain('Protocolo cero')
    expect(serialized).toContain('INFORMES Y DOCUMENTOS')
    expect(serialized).toContain('respaldo.pdf')
  })
})
