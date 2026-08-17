import { describe, expect, it } from 'vitest'
import { riskMeta } from '../../resources/js/utils/social-work-risk'

describe('Trabajo Social risk badge', () => {
  it('communicates critical risk with icon and text, not color alone', () => {
    expect(riskMeta('critico')).toEqual({ label: 'Crítico', icon: 'bx-shield-x' })
  })
})
