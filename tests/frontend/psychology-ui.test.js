// @vitest-environment jsdom

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import axios from 'axios'
import PsychologyBadge from '../../resources/js/components/psychology/PsychologyBadge.vue'
import { priorityLabels, psychologyStatusLabels, usePsychology } from '../../resources/js/composables/usePsychology'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn() } }))

describe('Psychology UI foundations', () => {
  it('renders accessible labels for workflow states and critical priority', () => {
    const status = mount(PsychologyBadge, { props: { value: 'information_requested' } })
    const priority = mount(PsychologyBadge, { props: { value: 'critical', priority: true } })
    expect(status.text()).toBe('Requiere antecedentes')
    expect(priority.text()).toBe('Crítica')
    expect(priority.classes()).toContain('psi-badge--critical')
    expect(psychologyStatusLabels.closed).toBe('Cerrado')
    expect(priorityLabels.high).toBe('Alta')
  })

  it('uses the paginated API and exposes uniform errors', async () => {
    axios.get.mockResolvedValueOnce({ data: { data: [], meta: { current_page: 1 } } })
    const psychology = usePsychology()
    const response = await psychology.get('/api/psychology/referrals', { page: 1 })
    expect(axios.get).toHaveBeenCalledWith('/api/psychology/referrals', { params: { page: 1 } })
    expect(response.meta.current_page).toBe(1)

    axios.post.mockRejectedValueOnce({ response: { data: { errors: { observed_facts: ['Describe hechos observables.'] } } } })
    await expect(psychology.post('/api/psychology/referrals', {})).rejects.toBeTruthy()
    expect(psychology.error.value).toBe('Describe hechos observables.')
    expect(psychology.loading.value).toBe(false)
  })

  it('never labels private notes as referral feedback', () => {
    expect(psychologyStatusLabels.private_note).toBeUndefined()
    expect(Object.keys(psychologyStatusLabels)).not.toContain('diagnosis')
  })
})
