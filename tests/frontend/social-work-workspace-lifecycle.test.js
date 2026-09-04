// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SocialWorkView from '../../resources/js/views/social-work/index.vue'

vi.mock('axios', () => ({ default: vi.fn() }))
vi.mock('../../resources/js/layouts/main.vue', () => ({ default: { template: '<main><slot /></main>' } }))
vi.mock('../../resources/js/utils/social-work-export', () => ({ downloadSocialCaseMaster: vi.fn(), downloadSocialCsv: vi.fn() }))
vi.mock('../../resources/js/components/social-work/junaeb-workspace.vue', () => ({
  default: {
    template: '<section data-testid="junaeb-workspace">JUNAEB estable</section>',
    mounted() { globalThis.__junaebMounts += 1 },
    unmounted() { globalThis.__junaebUnmounts += 1 },
  },
}))

describe('Ciclo de vida del espacio de Trabajo Social', () => {
  beforeEach(() => {
    axios.mockReset()
    globalThis.__junaebMounts = 0
    globalThis.__junaebUnmounts = 0
    localStorage.setItem('permissions', JSON.stringify([
      'social_work.dashboard.view',
      'social_work.students.view',
      'social_work.junaeb.manage',
    ]))
  })

  it('mantiene JUNAEB montado mientras el contenedor carga sus catálogos', async () => {
    const pending = []
    axios.mockImplementation(({ url }) => new Promise(resolve => pending.push({ url, resolve })))
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/social-work/junaeb', component: SocialWorkView }],
    })
    await router.push('/social-work/junaeb')
    await router.isReady()

    const wrapper = mount(SocialWorkView, { global: { plugins: [router] } })
    await flushPromises()

    expect(pending.map(request => request.url).sort()).toEqual([
      '/api/social-work/catalogs',
    ])
    expect(wrapper.find('[data-testid="junaeb-workspace"]').exists()).toBe(true)
    expect(wrapper.find('.sw-loading').exists()).toBe(true)
    expect(globalThis.__junaebMounts).toBe(1)
    expect(globalThis.__junaebUnmounts).toBe(0)

    pending.find(request => request.url.endsWith('/catalogs')).resolve({ data: { data: { case_statuses: [] } } })
    await flushPromises()

    expect(wrapper.find('[data-testid="junaeb-workspace"]').exists()).toBe(true)
    expect(wrapper.find('.sw-loading').exists()).toBe(false)
    expect(globalThis.__junaebMounts).toBe(1)
    expect(globalThis.__junaebUnmounts).toBe(0)
  })
})
