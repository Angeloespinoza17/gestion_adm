<script>
export default {
  emits: ["change"],
  props: {
    pagination: {
      type: Object,
      default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }),
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },
  methods: {
    change(page) {
      if (this.loading || page < 1 || page > this.pagination.last_page || page === this.pagination.current_page) return;
      this.$emit("change", page);
    },
  },
};
</script>

<template>
  <div v-if="pagination.total" class="pme-pagination" role="navigation" aria-label="Paginación de resultados">
    <span>Mostrando <strong>{{ pagination.from }}–{{ pagination.to }}</strong> de <strong>{{ pagination.total }}</strong></span>
    <div v-if="pagination.last_page > 1">
      <button type="button" :disabled="loading || pagination.current_page <= 1" aria-label="Página anterior" @click="change(pagination.current_page - 1)"><i class="bx bx-chevron-left"></i></button>
      <span>Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
      <button type="button" :disabled="loading || pagination.current_page >= pagination.last_page" aria-label="Página siguiente" @click="change(pagination.current_page + 1)"><i class="bx bx-chevron-right"></i></button>
    </div>
  </div>
</template>

<style scoped>
.pme-pagination{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:.75rem;color:#788498;font-size:.62rem}.pme-pagination>div{display:flex;align-items:center;gap:.45rem}.pme-pagination button{display:grid;place-items:center;width:30px;height:30px;border:1px solid #d7dee8;border-radius:7px;background:#fff;color:#3156a6}.pme-pagination button:disabled{cursor:not-allowed;opacity:.4}.pme-pagination button:not(:disabled):hover{border-color:#9fb0cd;background:#f2f6fc}.pme-pagination strong{color:#4e5c71}@media(max-width:520px){.pme-pagination{align-items:flex-start;flex-direction:column}.pme-pagination>div{width:100%;justify-content:space-between}}
</style>
