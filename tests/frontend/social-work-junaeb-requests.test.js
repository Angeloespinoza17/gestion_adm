// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import JunaebWorkspace from '../../resources/js/components/social-work/junaeb-workspace.vue'

vi.mock('axios', () => ({ default: vi.fn() }))
vi.mock('../../resources/js/utils/social-work-export', () => ({ downloadJunaebDeliveryAct: vi.fn() }))

const student = {
  id: 41,
  first_name: 'Elena',
  last_name: 'Soto',
  rut: '21.111.222-3',
  current_enrollment: {
    course_section_id: 7,
    course_section: {
      display_name: '7° básico A',
      education_level: { name: 'Educación básica' },
    },
  },
  transport_pass_eligible: true,
}

const requestedUrls = () => axios.mock.calls.map(([config]) => config.url)

describe('Carga JUNAEB de Trabajo Social', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  beforeEach(() => {
    axios.mockReset()
    localStorage.setItem('permissions', JSON.stringify(['__superadmin__']))
    axios.mockImplementation(({ url }) => {
      if (url.includes('/junaeb/student-options')) {
        return Promise.resolve({ data: { data: [student], academic_year: { id: 1, year: 2026 } } })
      }

      if (url.includes('/support-matrix')) {
        const page = Number(new URL(url, 'https://local.test').searchParams.get('page'))
        return Promise.resolve({
          data: {
            data: [{ ...student, program_flags: { junaeb: true, pro_retencion: false, external: [] }, junaeb_benefits: [] }],
            current_page: page,
            last_page: 3,
            total: 201,
          },
        })
      }

      return Promise.resolve({ data: { data: [], current_page: 1, last_page: 1, total: 0 } })
    })
  })

  it('no descarga la matriz completa y usa un catálogo JUNAEB propio para todos los formularios', async () => {
    const wrapper = mount(JunaebWorkspace, {
      props: {
        catalogs: { academic_years: [{ id: 1, year: 2026, is_active: true }] },
      },
    })
    await flushPromises()

    expect(requestedUrls()).toHaveLength(6)
    expect(requestedUrls()).toContain('/api/social-work/junaeb/student-options')
    expect(requestedUrls().some(url => url.includes('/support-matrix'))).toBe(false)

    const passesTab = wrapper.findAll('.junaeb-tabs button').find(button => button.text().includes('Pases escolares'))
    await passesTab.trigger('click')

    expect(wrapper.text()).toContain('Elena Soto')
    expect(requestedUrls().some(url => url.includes('/support-matrix'))).toBe(false)
  })

  it('consulta solo la página visible de cobertura y pagina bajo demanda', async () => {
    const wrapper = mount(JunaebWorkspace, {
      props: {
        catalogs: { academic_years: [{ id: 1, year: 2026, is_active: true }] },
      },
    })
    await flushPromises()

    const coverageTab = wrapper.findAll('.junaeb-tabs button').find(button => button.text().includes('Cobertura de alumnas'))
    await coverageTab.trigger('click')
    await flushPromises()

    let matrixRequests = requestedUrls().filter(url => url.includes('/support-matrix'))
    expect(matrixRequests).toEqual(['/api/social-work/support-matrix?per_page=100&page=1&school_year=2026'])
    expect(wrapper.text()).toContain('Página 1 de 3')

    const nextButton = wrapper.findAll('.coverage-pager button').find(button => button.text() === 'Siguiente')
    await nextButton.trigger('click')
    await flushPromises()

    matrixRequests = requestedUrls().filter(url => url.includes('/support-matrix'))
    expect(matrixRequests).toEqual([
      '/api/social-work/support-matrix?per_page=100&page=1&school_year=2026',
      '/api/social-work/support-matrix?per_page=100&page=2&school_year=2026',
    ])
    expect(wrapper.text()).toContain('Página 2 de 3')
  })

  it('carga las alumnas en beneficio, pase, servicio médico y apoyo, y los beneficios en entrega', async () => {
    axios.mockImplementation(({ url }) => {
      if (url.includes('/junaeb/student-options')) {
        return Promise.resolve({ data: { data: [student], academic_year: { id: 1, year: 2026 } } })
      }
      if (url.includes('/junaeb/benefits?')) {
        return Promise.resolve({ data: { data: [{ id: 88, school_year: 2026, student, benefit_type: { code: 'utiles_escolares' } }] } })
      }
      return Promise.resolve({ data: { data: [] } })
    })

    const wrapper = mount(JunaebWorkspace, {
      props: {
        catalogs: {
          academic_years: [{ id: 1, year: 2026, is_active: true }],
          junaeb_benefit_types: [{ id: 3, name: 'Útiles escolares' }],
        },
      },
    })
    await flushPromises()

    const modalOptionCounts = async (buttonText) => {
      await wrapper.findAll('button').find(button => button.text().includes(buttonText)).trigger('click')
      await flushPromises()
      const counts = [...document.querySelectorAll('.modal-panel select')]
        .map(select => select.options.length)
      document.querySelector('.modal-panel header button').click()
      await flushPromises()
      return counts
    }

    expect(await modalOptionCounts('Incorporar beneficio')).toEqual([2, 2])
    expect(await modalOptionCounts('Registrar entrega')).toEqual([2])

    await wrapper.findAll('.junaeb-tabs button').find(button => button.text().includes('Pases escolares')).trigger('click')
    expect(await modalOptionCounts('Registrar pase')).toEqual([2, 5])

    await wrapper.findAll('.junaeb-tabs button').find(button => button.text().includes('Servicios médicos')).trigger('click')
    expect(await modalOptionCounts('Registrar servicio')).toEqual([2, 4])

    await wrapper.findAll('.junaeb-tabs button').find(button => button.text().includes('Lentes y apoyos')).trigger('click')
    expect(await modalOptionCounts('Registrar apoyo')).toEqual([2, 4, 4])

    wrapper.unmount()
  })
})
