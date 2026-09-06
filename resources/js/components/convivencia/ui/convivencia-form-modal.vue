<script>
import {
  acquireConvivenciaModalLock,
  releaseConvivenciaModalLock,
  trapConvivenciaModalFocus,
} from "./modal-lifecycle";

let modalSequence = 0;

export default {
  name: "ConvivenciaFormModal",
  props: {
    modelValue: { type: Boolean, default: false },
    title: { type: String, required: true },
    eyebrow: { type: String, default: "Gestión de convivencia" },
    description: { type: String, default: "" },
    icon: { type: String, default: "bx-edit-alt" },
    size: { type: String, default: "xl" },
    busy: { type: Boolean, default: false },
    submitLabel: { type: String, default: "Guardar cambios" },
    cancelLabel: { type: String, default: "Cancelar" },
    hideSubmit: { type: Boolean, default: false },
    hideFooter: { type: Boolean, default: false },
    submitDisabled: { type: Boolean, default: false },
    closeOnBackdrop: { type: Boolean, default: false },
    formless: { type: Boolean, default: false },
  },
  emits: ["update:modelValue", "submit", "close"],
  data() {
    modalSequence += 1;
    return {
      modalId: `convivencia-form-modal-${modalSequence}`,
      previousActiveElement: null,
      ownsBodyLock: false,
    };
  },
  watch: {
    modelValue: {
      immediate: true,
      handler(open) {
        if (open) this.onOpen();
        else this.onClosed();
      },
    },
  },
  beforeUnmount() {
    this.releaseBodyLock();
    this.restoreFocus();
  },
  methods: {
    onOpen() {
      this.previousActiveElement = document.activeElement;
      if (!this.ownsBodyLock) {
        acquireConvivenciaModalLock(this.modalId);
        this.ownsBodyLock = true;
      }
      this.$nextTick(() => {
        const focusTarget = this.$refs.dialog?.querySelector("[autofocus], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])");
        focusTarget?.focus();
      });
    },
    onClosed() {
      // El bloqueo y el foco se liberan en after-leave, cuando el diálogo ya salió del DOM.
    },
    afterLeave() {
      this.releaseBodyLock();
      this.restoreFocus();
    },
    releaseBodyLock() {
      if (this.ownsBodyLock) {
        releaseConvivenciaModalLock(this.modalId);
        this.ownsBodyLock = false;
      }
    },
    restoreFocus() {
      this.previousActiveElement?.focus?.();
      this.previousActiveElement = null;
    },
    requestClose() {
      if (this.busy) return;
      this.$emit("update:modelValue", false);
      this.$emit("close");
    },
    onBackdrop() {
      if (this.closeOnBackdrop) this.requestClose();
    },
    onKeydown(event) {
      if (event.key === "Escape") {
        if (event.target?.closest?.(".multiselect.is-active")) return;
        event.preventDefault();
        this.requestClose();
        return;
      }
      trapConvivenciaModalFocus(event, this.$refs.dialog);
    },
    submit() {
      if (!this.busy && !this.submitDisabled) this.$emit("submit");
    },
    onNativeSubmit(event) {
      if (this.formless) return;
      event.preventDefault();
      this.submit();
    },
  },
};
</script>

<template>
  <Teleport to="body">
    <Transition name="convivencia-modal-fade" @after-leave="afterLeave">
      <div v-if="modelValue" class="convivencia-form-modal" @mousedown.self="onBackdrop">
        <section
          ref="dialog"
          class="convivencia-form-modal__dialog"
          :class="`is-${size}`"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="`${modalId}-title`"
          :aria-describedby="description ? `${modalId}-description` : undefined"
          tabindex="-1"
          @keydown="onKeydown"
        >
          <component :is="formless ? 'div' : 'form'" class="convivencia-form-modal__form" @submit="onNativeSubmit">
            <header class="convivencia-form-modal__header">
              <span class="convivencia-form-modal__icon" aria-hidden="true"><i class="bx" :class="icon"></i></span>
              <div class="convivencia-form-modal__heading">
                <span>{{ eyebrow }}</span>
                <h2 :id="`${modalId}-title`">{{ title }}</h2>
                <p v-if="description" :id="`${modalId}-description`">{{ description }}</p>
              </div>
              <button type="button" class="convivencia-form-modal__close" :disabled="busy" aria-label="Cerrar ventana" @click="requestClose">
                <i class="bx bx-x" aria-hidden="true"></i>
              </button>
            </header>

            <div class="convivencia-form-modal__body">
              <slot />
            </div>

            <footer v-if="!hideFooter" class="convivencia-form-modal__footer">
              <div class="convivencia-form-modal__footer-note">
                <i class="bx bx-lock-alt" aria-hidden="true"></i>
                <span>La información queda protegida por los permisos y la trazabilidad del módulo.</span>
              </div>
              <div class="convivencia-form-modal__footer-actions">
                <slot name="footer-actions">
                  <button type="button" class="btn btn-outline-secondary" :disabled="busy" @click="requestClose">{{ cancelLabel }}</button>
                  <button v-if="!hideSubmit" type="submit" class="btn btn-primary" :disabled="busy || submitDisabled">
                    <span v-if="busy" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    <i v-else class="bx bx-check" aria-hidden="true"></i>
                    {{ busy ? "Guardando…" : submitLabel }}
                  </button>
                </slot>
              </div>
            </footer>
          </component>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.convivencia-form-modal{position:fixed;inset:0;z-index:10900;display:flex;align-items:center;justify-content:center;padding:1.25rem;background:rgba(20,29,58,.66);backdrop-filter:blur(7px)}
.convivencia-form-modal__dialog{width:min(100%,920px);max-height:calc(100vh - 2.5rem);overflow:hidden;border:1px solid rgba(255,255,255,.46);border-radius:22px;background:#fff;box-shadow:0 32px 90px rgba(16,27,64,.36)}
.convivencia-form-modal__dialog.is-sm{width:min(100%,560px)}.convivencia-form-modal__dialog.is-lg{width:min(100%,760px)}.convivencia-form-modal__dialog.is-xl{width:min(100%,1120px)}.convivencia-form-modal__dialog.is-xxl{width:min(100%,1380px)}
.convivencia-form-modal__form{display:flex;max-height:inherit;flex-direction:column}
.convivencia-form-modal__header{display:flex;align-items:flex-start;gap:.9rem;padding:1rem 1.15rem;color:#fff;background:radial-gradient(circle at 90% 15%,rgba(49,194,160,.35),transparent 28%),linear-gradient(125deg,#202f70,#4e62d3 70%,#258773)}
.convivencia-form-modal__icon{display:grid;width:44px;height:44px;flex:0 0 44px;font-size:1.25rem;place-items:center;border:1px solid rgba(255,255,255,.24);border-radius:14px;background:rgba(255,255,255,.13)}
.convivencia-form-modal__heading{min-width:0;flex:1}.convivencia-form-modal__heading>span{display:block;margin-bottom:.15rem;font-size:.62rem;font-weight:800;letter-spacing:.11em;text-transform:uppercase;opacity:.76}.convivencia-form-modal__heading h2{margin:0;font-size:1.08rem;font-weight:800}.convivencia-form-modal__heading p{max-width:760px;margin:.25rem 0 0;font-size:.72rem;line-height:1.45;opacity:.8}
.convivencia-form-modal__close{display:grid;width:36px;height:36px;flex:0 0 36px;color:#fff;font-size:1.4rem;place-items:center;border:0;border-radius:11px;background:rgba(255,255,255,.12)}.convivencia-form-modal__close:hover{background:rgba(255,255,255,.22)}
.convivencia-form-modal__body{min-height:0;overflow:auto;padding:1.15rem;background:linear-gradient(180deg,#f8faff 0,#fff 82px)}
.convivencia-form-modal__footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 1.15rem;border-top:1px solid #e4e8f1;background:#fff}.convivencia-form-modal__footer-note{display:flex;max-width:520px;align-items:center;gap:.4rem;color:#778297;font-size:.67rem}.convivencia-form-modal__footer-note i{color:#438d7d;font-size:1rem}.convivencia-form-modal__footer-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.5rem}.convivencia-form-modal__footer .btn{display:inline-flex;align-items:center;gap:.35rem;min-width:116px;justify-content:center}
.convivencia-modal-fade-enter-active,.convivencia-modal-fade-leave-active{transition:opacity .18s ease}.convivencia-modal-fade-enter-active .convivencia-form-modal__dialog,.convivencia-modal-fade-leave-active .convivencia-form-modal__dialog{transition:transform .18s ease,opacity .18s ease}.convivencia-modal-fade-enter-from,.convivencia-modal-fade-leave-to{opacity:0}.convivencia-modal-fade-enter-from .convivencia-form-modal__dialog,.convivencia-modal-fade-leave-to .convivencia-form-modal__dialog{opacity:0;transform:translateY(12px) scale(.985)}
@media(max-width:767.98px){.convivencia-form-modal{align-items:flex-end;padding:0}.convivencia-form-modal__dialog,.convivencia-form-modal__dialog.is-sm,.convivencia-form-modal__dialog.is-lg,.convivencia-form-modal__dialog.is-xl,.convivencia-form-modal__dialog.is-xxl{width:100%;max-height:94vh;border-radius:22px 22px 0 0}.convivencia-form-modal__header{padding:.85rem}.convivencia-form-modal__body{padding:.9rem}.convivencia-form-modal__footer{align-items:stretch;flex-direction:column}.convivencia-form-modal__footer-note{display:none}.convivencia-form-modal__footer-actions{display:grid;grid-template-columns:1fr 1fr}.convivencia-form-modal__footer .btn{min-width:0}}
@media(prefers-reduced-motion:reduce){.convivencia-modal-fade-enter-active,.convivencia-modal-fade-leave-active,.convivencia-modal-fade-enter-active .convivencia-form-modal__dialog,.convivencia-modal-fade-leave-active .convivencia-form-modal__dialog{transition:none}}
</style>

<style>
body.convivencia-modal-open{overflow:hidden}.swal2-container{z-index:20000!important}
</style>
