<script>
export default {
  name: "ConvivenciaDataTable",
  props: {
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
    icon: { type: String, default: "bx-list-ul" },
    count: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
    empty: { type: Boolean, default: false },
    emptyTitle: { type: String, default: "No hay registros" },
    emptyText: { type: String, default: "Ajusta los filtros o crea un nuevo registro." },
    minWidth: { type: String, default: "760px" },
  },
};
</script>

<template>
  <section class="convivencia-data-table" :aria-busy="loading">
    <header class="convivencia-data-table__header">
      <div class="convivencia-data-table__title">
        <span aria-hidden="true"><i class="bx" :class="icon"></i></span>
        <div><h3>{{ title }}</h3><p v-if="subtitle">{{ subtitle }}</p></div>
      </div>
      <div class="convivencia-data-table__header-actions">
        <span class="convivencia-data-table__count">{{ count }} {{ count === 1 ? "registro" : "registros" }}</span>
        <slot name="actions" />
      </div>
    </header>

    <div v-if="$slots.toolbar" class="convivencia-data-table__toolbar"><slot name="toolbar" /></div>

    <div v-if="loading" class="convivencia-data-table__state" role="status">
      <span class="spinner-border text-primary" aria-hidden="true"></span><b>Cargando información…</b><small>Estamos preparando el listado.</small>
    </div>
    <div v-else-if="empty" class="convivencia-data-table__state">
      <span class="is-empty" aria-hidden="true"><i class="bx bx-inbox"></i></span><b>{{ emptyTitle }}</b><small>{{ emptyText }}</small>
      <slot name="empty-action" />
    </div>
    <div v-else class="convivencia-data-table__scroll" :style="{ '--table-min-width': minWidth }">
      <slot />
    </div>

    <footer v-if="$slots.footer" class="convivencia-data-table__footer"><slot name="footer" /></footer>
  </section>
</template>

<style scoped>
.convivencia-data-table{overflow:hidden;border:1px solid #dfe5ef;border-radius:18px;background:#fff;box-shadow:0 12px 28px rgba(35,48,80,.07)}
.convivencia-data-table__header{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.9rem 1rem;border-bottom:1px solid #e5e9f1;background:linear-gradient(100deg,#fff,#f7f9ff)}.convivencia-data-table__title{display:flex;min-width:0;align-items:center;gap:.65rem}.convivencia-data-table__title>span{display:grid;width:38px;height:38px;flex:0 0 38px;color:#4f63d9;font-size:1.05rem;place-items:center;border-radius:12px;background:#eef1ff}.convivencia-data-table__title h3{margin:0;color:#263466;font-size:.88rem;font-weight:800}.convivencia-data-table__title p{margin:.15rem 0 0;color:#7b8799;font-size:.66rem}.convivencia-data-table__header-actions{display:flex;align-items:center;gap:.55rem}.convivencia-data-table__count{padding:.24rem .55rem;color:#59667b;font-size:.64rem;font-weight:750;white-space:nowrap;border:1px solid #dde3ed;border-radius:999px;background:#fff}
.convivencia-data-table__toolbar{padding:.75rem 1rem;border-bottom:1px solid #e8ebf2;background:#fbfcff}.convivencia-data-table__scroll{overflow-x:auto}.convivencia-data-table__scroll :deep(table){min-width:var(--table-min-width);margin:0}.convivencia-data-table__scroll :deep(thead th){padding:.7rem .75rem;color:#6b768b;font-size:.61rem;font-weight:800;letter-spacing:.055em;text-transform:uppercase;white-space:nowrap;border-bottom:1px solid #dfe4ee;background:#f7f8fc}.convivencia-data-table__scroll :deep(tbody td){padding:.72rem .75rem;color:#425069;font-size:.69rem;vertical-align:middle;border-color:#edf0f5}.convivencia-data-table__scroll :deep(tbody tr:hover){background:#fafbff}.convivencia-data-table__scroll :deep(tbody tr:last-child td){border-bottom:0}.convivencia-data-table__state{display:flex;min-height:220px;align-items:center;justify-content:center;flex-direction:column;gap:.35rem;padding:2rem;text-align:center;color:#718097}.convivencia-data-table__state>span.is-empty{display:grid;width:54px;height:54px;color:#687be3;font-size:1.55rem;place-items:center;border-radius:17px;background:#eff2ff}.convivencia-data-table__state b{color:#344267;font-size:.8rem}.convivencia-data-table__state small{max-width:420px;font-size:.67rem}.convivencia-data-table__footer{padding:.7rem 1rem;border-top:1px solid #e8ebf2;background:#fbfcff}
@media(max-width:767.98px){.convivencia-data-table__header{align-items:flex-start}.convivencia-data-table__header-actions{align-items:flex-end;flex-direction:column}.convivencia-data-table__title p{display:none}.convivencia-data-table__scroll{overflow:visible;padding:.65rem}.convivencia-data-table__scroll :deep(table){display:block;width:100%!important;min-width:0!important;max-width:100%}.convivencia-data-table__scroll :deep(tbody),.convivencia-data-table__scroll :deep(tr),.convivencia-data-table__scroll :deep(td){display:block;width:100%;min-width:0}.convivencia-data-table__scroll :deep(thead){position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}.convivencia-data-table__scroll :deep(tbody){display:grid;gap:.65rem}.convivencia-data-table__scroll :deep(tbody tr){padding:.35rem .7rem;border:1px solid #e1e6ef;border-radius:14px;background:#fff;box-shadow:0 5px 14px rgba(42,54,86,.05)}.convivencia-data-table__scroll :deep(tbody td){display:grid;width:auto;grid-template-columns:minmax(92px,36%) minmax(0,1fr);gap:.5rem;padding:.48rem 0;text-align:right!important;overflow-wrap:anywhere;border-bottom:1px dashed #e7ebf2}.convivencia-data-table__scroll :deep(tbody td>*),.convivencia-data-table__scroll :deep(tbody td>div){min-width:0;max-width:100%}.convivencia-data-table__scroll :deep(tbody td:last-child){border-bottom:0}.convivencia-data-table__scroll :deep(tbody td::before){content:attr(data-label);color:#7b8698;font-size:.58rem;font-weight:800;letter-spacing:.035em;text-align:left;text-transform:uppercase}.convivencia-data-table__scroll :deep(tbody td[data-label="Acciones"]){display:block;width:100%;padding-top:.6rem}.convivencia-data-table__scroll :deep(tbody td[data-label="Acciones"]::before){display:none}}
</style>
