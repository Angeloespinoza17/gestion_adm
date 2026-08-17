export const socialRiskMeta = {
  sin_evaluar: { label: 'Sin evaluar', icon: 'bx-help-circle' },
  bajo: { label: 'Bajo', icon: 'bx-check-circle' },
  medio: { label: 'Medio', icon: 'bx-error' },
  alto: { label: 'Alto', icon: 'bx-error-circle' },
  critico: { label: 'Crítico', icon: 'bx-shield-x' },
}

export const riskMeta = (level) => socialRiskMeta[level] || { label: level, icon: 'bx-help-circle' }
