<script setup>defineProps({ count: { type: Number, default: 0 } })</script>
<template><span v-if="count" class="protection-badge" :title="`${count} medida(s) de protección vigente(s). Detalle sujeto a permisos.`"><i class="bx bx-shield-quarter"></i> Medida vigente <span class="visually-hidden">Cantidad:</span> {{ count }}</span></template>
<style scoped>.protection-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .5rem;border:1px solid #d5c6e8;border-radius:.35rem;background:#f5effc;color:#594174;font-size:.75rem;font-weight:600}</style>
