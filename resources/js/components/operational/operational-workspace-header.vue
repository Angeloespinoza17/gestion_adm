<script>
export default {
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: '' },
        icon: { type: String, default: 'bx bx-grid-alt' },
    },
    data() {
        return {
            tools: [
                { label: 'Traslados', description: 'Solicitudes y gestión', to: '/operational/transfers', icon: 'bx bx-bus' },
                { label: 'Ausencias', description: 'Permisos y saldos', to: '/human-resources/absences', icon: 'bx bx-calendar-check' },
                { label: 'Selección', description: 'Talento y entrevistas', to: '/human-resources/recruitment', icon: 'bx bx-group' },
            ],
        }
    },
    methods: {
        isActive(tool) {
            return this.$route.path === tool.to || this.$route.path.startsWith(`${tool.to}/`)
        },
    },
}
</script>

<template>
    <section class="op-workspace-hero">
        <span class="op-hero-orb op-hero-orb-one"></span>
        <span class="op-hero-orb op-hero-orb-two"></span>

        <div class="op-hero-content">
            <div class="op-hero-heading">
                <div class="op-hero-icon"><i :class="icon"></i></div>
                <div>
                    <div class="op-eyebrow"><span></span> Gestión Operativa</div>
                    <h2>{{ title }}</h2>
                    <p v-if="subtitle">{{ subtitle }}</p>
                </div>
            </div>
            <div v-if="$slots.actions" class="op-hero-actions">
                <slot name="actions"></slot>
            </div>
        </div>

        <nav class="op-tool-nav" aria-label="Herramientas de gestión operativa">
            <router-link
                v-for="tool in tools"
                :key="tool.to"
                :to="tool.to"
                class="op-tool-link"
                :class="{ active: isActive(tool) }"
            >
                <span class="op-tool-link-icon"><i :class="tool.icon"></i></span>
                <span>
                    <strong>{{ tool.label }}</strong>
                    <small>{{ tool.description }}</small>
                </span>
                <i class="bx bx-chevron-right op-tool-chevron"></i>
            </router-link>
        </nav>
    </section>
</template>

<style>
.operational-workspace {
    --op-ink: #172554;
    --op-muted: #64748b;
    --op-border: #e5eaf2;
    --op-soft: #f6f8fc;
    max-width: 1680px;
    margin: 0 auto;
}

.op-workspace-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    margin-bottom: 1.35rem;
    padding: 1.65rem 1.65rem 0;
    border-radius: 1.4rem;
    color: #fff;
    background:
        linear-gradient(122deg, rgba(18, 37, 91, .98), rgba(48, 66, 160, .95) 58%, rgba(78, 84, 200, .92));
    box-shadow: 0 18px 46px rgba(30, 47, 112, .2);
}

.op-hero-orb {
    position: absolute;
    z-index: -1;
    border-radius: 50%;
    pointer-events: none;
    filter: blur(1px);
}

.op-hero-orb-one {
    width: 300px;
    height: 300px;
    top: -210px;
    right: 8%;
    background: rgba(129, 140, 248, .3);
}

.op-hero-orb-two {
    width: 180px;
    height: 180px;
    left: 38%;
    bottom: -145px;
    background: rgba(56, 189, 248, .2);
}

.op-hero-content {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
}

.op-hero-heading {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    min-width: 0;
}

.op-hero-icon {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 3.4rem;
    height: 3.4rem;
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 1rem;
    background: rgba(255,255,255,.12);
    box-shadow: inset 0 1px rgba(255,255,255,.14);
    font-size: 1.65rem;
}

.op-eyebrow {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin-bottom: .35rem;
    color: rgba(255,255,255,.72);
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .13em;
    text-transform: uppercase;
}

.op-eyebrow span {
    width: 1.25rem;
    height: 2px;
    border-radius: 99px;
    background: #67e8f9;
}

.op-hero-heading h2 {
    margin: 0;
    color: #fff;
    font-size: clamp(1.45rem, 2.2vw, 2rem);
    font-weight: 750;
    letter-spacing: -.025em;
}

.op-hero-heading p {
    max-width: 750px;
    margin: .4rem 0 0;
    color: rgba(255,255,255,.72);
    line-height: 1.55;
}

.op-hero-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: .55rem;
}

.op-hero-actions .btn {
    border-radius: .7rem;
    font-weight: 600;
    box-shadow: none;
}

.op-hero-actions .btn-light {
    color: #27346f;
}

.op-hero-actions .btn-outline-light {
    border-color: rgba(255,255,255,.42);
}

.op-tool-nav {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .65rem;
    margin-top: 1.5rem;
    padding: .65rem;
    border: 1px solid rgba(255,255,255,.13);
    border-bottom: 0;
    border-radius: 1rem 1rem 0 0;
    background: rgba(9, 18, 56, .22);
    backdrop-filter: blur(12px);
}

.op-tool-link {
    display: flex;
    align-items: center;
    gap: .75rem;
    min-width: 0;
    padding: .75rem .85rem;
    border-radius: .75rem;
    color: rgba(255,255,255,.72);
    transition: background .18s ease, color .18s ease, transform .18s ease;
}

.op-tool-link:hover {
    color: #fff;
    background: rgba(255,255,255,.1);
    transform: translateY(-1px);
}

.op-tool-link.active {
    color: #25336f;
    background: #fff;
    box-shadow: 0 8px 20px rgba(8, 16, 53, .16);
}

.op-tool-link-icon {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    width: 2.15rem;
    height: 2.15rem;
    border-radius: .65rem;
    background: rgba(255,255,255,.1);
    font-size: 1.15rem;
}

.op-tool-link.active .op-tool-link-icon {
    color: #4857c8;
    background: #eef0ff;
}

.op-tool-link strong,
.op-tool-link small { display: block; }
.op-tool-link strong { color: inherit; line-height: 1.2; }
.op-tool-link small { margin-top: .15rem; color: inherit; opacity: .7; font-size: .72rem; }
.op-tool-chevron { display: none; margin-left: auto; font-size: 1.2rem; }

.op-surface {
    border: 1px solid var(--op-border) !important;
    border-radius: 1.15rem !important;
    background: #fff;
    box-shadow: 0 10px 30px rgba(22, 34, 68, .055) !important;
}

.op-metric-card {
    position: relative;
    overflow: hidden;
    height: 100%;
    padding: 1.05rem 1.1rem;
    border: 1px solid var(--op-border);
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 8px 24px rgba(22, 34, 68, .045);
}

.op-metric-card::after {
    position: absolute;
    width: 72px;
    height: 72px;
    right: -26px;
    bottom: -30px;
    border-radius: 50%;
    background: var(--metric-soft, #eef2ff);
    content: '';
}

.op-metric-top { display: flex; align-items: center; justify-content: space-between; gap: .65rem; }
.op-metric-label { color: var(--op-muted); font-size: .78rem; font-weight: 600; }
.op-metric-value { margin: .55rem 0 .12rem; color: var(--op-ink); font-size: 1.75rem; font-weight: 750; letter-spacing: -.04em; }
.op-metric-note { color: #94a3b8; font-size: .7rem; }
.op-metric-icon { display: grid; place-items: center; width: 2.35rem; height: 2.35rem; border-radius: .75rem; color: var(--metric, #4f46e5); background: var(--metric-soft, #eef2ff); font-size: 1.15rem; }
.op-tone-sky { --metric: #0284c7; --metric-soft: #e0f2fe; }
.op-tone-indigo { --metric: #4f46e5; --metric-soft: #eef2ff; }
.op-tone-violet { --metric: #7c3aed; --metric-soft: #f3e8ff; }
.op-tone-emerald { --metric: #059669; --metric-soft: #d1fae5; }
.op-tone-amber { --metric: #d97706; --metric-soft: #fef3c7; }
.op-tone-rose { --metric: #e11d48; --metric-soft: #ffe4e6; }

.op-section-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    padding: .45rem;
    border: 1px solid var(--op-border);
    border-radius: .9rem;
    background: var(--op-soft);
}

.op-section-tabs .nav-link {
    display: flex;
    align-items: center;
    gap: .42rem;
    padding: .58rem .85rem;
    border: 0;
    border-radius: .65rem;
    color: #64748b;
    background: transparent;
    font-weight: 600;
    font-size: .82rem;
}

.op-section-tabs .nav-link:hover { color: #334155; background: rgba(255,255,255,.7); }
.op-section-tabs .nav-link.active { color: #3f4ab1; background: #fff; box-shadow: 0 3px 12px rgba(30, 41, 100, .1); }

.op-filter-panel {
    margin-bottom: 1rem;
    padding: 1rem;
    border: 1px solid #e9edf5;
    border-radius: .9rem;
    background: linear-gradient(180deg, #fbfcff, #f8faff);
}

.op-filter-label { display: block; margin-bottom: .38rem; color: #64748b; font-size: .7rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.op-filter-panel .form-control,
.op-filter-panel .form-select { min-height: 40px; border-color: #dde3ee; border-radius: .65rem; background-color: #fff; }

.op-data-table { margin-bottom: 0; }
.op-data-table thead th { padding: .75rem .8rem; border-bottom: 1px solid #e7ebf2; color: #64748b; background: #f8faff; font-size: .68rem; font-weight: 750; letter-spacing: .045em; text-transform: uppercase; white-space: nowrap; }
.op-data-table tbody td { padding: .85rem .8rem; border-color: #edf0f5; color: #334155; }
.op-data-table tbody tr:hover { background: #fbfcff; }
.op-data-table .badge { padding: .4rem .58rem; border-radius: 999px; font-weight: 650; }

.op-person { display: flex; align-items: center; gap: .7rem; min-width: 190px; }
.op-person-avatar { display: grid; flex: 0 0 auto; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: .7rem; color: #4653bd; background: #eef0ff; font-size: .75rem; font-weight: 750; }
.op-person strong { display: block; color: #1e293b; }
.op-empty-state { padding: 3rem 1rem !important; color: #94a3b8 !important; text-align: center; }
.op-empty-state i { display: block; margin-bottom: .55rem; color: #c7cfdd; font-size: 2.3rem; }

.op-modal-header { padding: 1rem 1.25rem; border: 0 !important; color: #fff; background: linear-gradient(120deg, #25346f, #4c56bd) !important; }
.op-modal-header h5 { color: #fff; }
.op-modal-header .btn-close { filter: brightness(0) invert(1); opacity: .8; }

@media (max-width: 991.98px) {
    .op-workspace-hero { padding: 1.25rem 1.25rem 0; }
    .op-hero-content { flex-direction: column; }
    .op-hero-actions { justify-content: flex-start; width: 100%; }
    .op-tool-link small { display: none; }
}

@media (max-width: 575.98px) {
    .op-workspace-hero { margin-inline: -.25rem; padding: 1rem 1rem 0; border-radius: 1rem; }
    .op-hero-heading { gap: .7rem; }
    .op-hero-icon { width: 2.7rem; height: 2.7rem; border-radius: .8rem; font-size: 1.25rem; }
    .op-hero-heading p { font-size: .82rem; }
    .op-tool-nav { grid-template-columns: 1fr; margin-top: 1rem; }
    .op-tool-link { padding: .58rem .7rem; }
    .op-tool-link:not(.active) { display: none; }
    .op-tool-chevron { display: block; }
    .op-hero-actions .btn { flex: 1 1 auto; }
}
</style>
