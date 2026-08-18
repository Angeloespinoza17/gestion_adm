<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from "vue";

defineProps({
    eyebrow: { type: String, default: "Registro confidencial" },
    title: { type: String, required: true },
    size: { type: String, default: "large" },
});

const emit = defineEmits(["close"]);
const closeButton = ref(null);
let previousBodyOverflow = "";

function handleKeydown(event) {
    if (event.key === "Escape") emit("close");
}

onMounted(async () => {
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", handleKeydown);
    await nextTick();
    closeButton.value?.focus();
});

onBeforeUnmount(() => {
    document.body.style.overflow = previousBodyOverflow;
    document.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="psi-modal" appear>
            <div
                class="psi-modal-overlay"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                @click.self="emit('close')"
            >
                <article class="psi-modal-panel" :class="`is-${size}`">
                    <header>
                        <div>
                            <span class="psi-modal-eyebrow">{{ eyebrow }}</span>
                            <h2>{{ title }}</h2>
                        </div>
                        <button
                            ref="closeButton"
                            type="button"
                            aria-label="Cerrar"
                            @click="emit('close')"
                        >
                            <i class="bx bx-x"></i>
                        </button>
                    </header>
                    <div class="psi-modal-content">
                        <slot />
                    </div>
                    <footer v-if="$slots.footer">
                        <slot name="footer" />
                    </footer>
                </article>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.psi-modal-overlay {
    position: fixed;
    z-index: 1095;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.62);
    backdrop-filter: blur(6px);
}
.psi-modal-panel {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    width: min(940px, 97vw);
    max-height: 95vh;
    overflow: hidden;
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 30px 90px rgba(15, 23, 42, 0.35);
}
.psi-modal-panel.is-small {
    width: min(620px, 96vw);
}
.psi-modal-panel.is-xlarge {
    width: min(1180px, 98vw);
}
.psi-modal-panel > header {
    position: sticky;
    z-index: 3;
    top: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.2rem 1.4rem;
    background: #fff;
    border-bottom: 1px solid #e8edf3;
}
.psi-modal-panel h2 {
    margin: 0.18rem 0 0;
    color: #24324a;
    font-size: 1.22rem;
}
.psi-modal-eyebrow {
    color: #735c9a;
    font-size: 0.67rem;
    font-weight: 800;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}
.psi-modal-panel > header button {
    display: grid;
    width: 37px;
    height: 37px;
    place-items: center;
    padding: 0;
    background: #f1f5f9;
    border: 0;
    border-radius: 50%;
    font-size: 1.35rem;
}
.psi-modal-content {
    overflow-y: auto;
    padding: 1.35rem;
    overscroll-behavior: contain;
}
.psi-modal-panel > footer {
    position: sticky;
    z-index: 3;
    bottom: 0;
    display: flex;
    justify-content: flex-end;
    gap: 0.6rem;
    padding: 1rem 1.35rem;
    background: #fff;
    border-top: 1px solid #e8edf3;
}
.psi-modal-enter-active,
.psi-modal-leave-active {
    transition: opacity 0.18s ease;
}
.psi-modal-enter-active .psi-modal-panel,
.psi-modal-leave-active .psi-modal-panel {
    transition: transform 0.18s ease, opacity 0.18s ease;
}
.psi-modal-enter-from,
.psi-modal-leave-to {
    opacity: 0;
}
.psi-modal-enter-from .psi-modal-panel,
.psi-modal-leave-to .psi-modal-panel {
    opacity: 0;
    transform: translateY(12px) scale(0.985);
}

@media (max-width: 800px) {
    .psi-modal-overlay {
        padding: 0.25rem;
    }
    .psi-modal-panel {
        max-height: 98vh;
    }
}
</style>
