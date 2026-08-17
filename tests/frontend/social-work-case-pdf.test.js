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

  it('genera una ficha confidencial completa aun cuando no existen intervenciones', () => {
    downloadSocialCaseMaster({
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
})
