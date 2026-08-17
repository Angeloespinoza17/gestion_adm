<script setup>
import { riskMeta } from '../../utils/social-work-risk'
defineProps({ level: { type: String, default: 'sin_evaluar' } })
</script>
<template><span class="sw-risk" :class="`sw-risk--${level}`" :title="`Nivel de riesgo: ${riskMeta(level).label}`"><i class="bx" :class="riskMeta(level).icon" aria-hidden="true"></i>{{ riskMeta(level).label }}</span></template>
<style scoped>.sw-risk{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .55rem;border:1px solid #dce3ea;border-radius:.35rem;font-size:.78rem;font-weight:600;background:#f8fafc;color:#394b59}.sw-risk--bajo{background:#edf8f1;color:#28623d}.sw-risk--medio{background:#fff8df;color:#7c5d00}.sw-risk--alto{background:#fff0e5;color:#914a18}.sw-risk--critico{background:#fdebed;color:#8b2635}</style>
