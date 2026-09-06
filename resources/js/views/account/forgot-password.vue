<script>
import axios from "axios";

const cnscLogo = "/brand/logo-cnsc.png";

export default {
  data() {
    return {
      email: "",
      error: "",
      status: "",
      isResetError: false,
      tryingToReset: false,
      processing: false,
      cnscLogo,
      recoverySteps: [
        {
          number: "01",
          title: "Ingresa tu correo",
          description: "Usa la dirección asociada a tu cuenta institucional.",
        },
        {
          number: "02",
          title: "Revisa tu bandeja",
          description: "Recibirás un enlace seguro para restablecer tu clave.",
        },
        {
          number: "03",
          title: "Crea una nueva clave",
          description: "El enlace te llevará al último paso de recuperación.",
        },
      ],
    };
  },
  methods: {
    clearError() {
      this.error = "";
      this.isResetError = false;
    },
    async forget() {
      if (this.processing) {
        return;
      }

      const email = this.email.trim();

      if (!email) {
        this.error = "Ingresa tu correo institucional para continuar.";
        this.isResetError = true;
        return;
      }

      this.email = email;
      this.processing = true;
      this.clearError();

      try {
        const { data } = await axios.post("/api/forget-password", { email });

        if (data.success === true && data.message === "success") {
          this.status = `Enviamos las instrucciones de recuperación a ${email}.`;
          this.tryingToReset = true;
          return;
        }

        this.error = data.message || "No fue posible solicitar el enlace de recuperación.";
        this.isResetError = true;
      } catch (error) {
        this.error = error?.response?.data?.message
          || "No fue posible conectar con el servicio. Inténtalo nuevamente.";
        this.isResetError = true;
      } finally {
        this.processing = false;
      }
    },
  },
};
</script>

<template>
  <div class="recovery-page">
    <span class="recovery-page__glow recovery-page__glow--one" aria-hidden="true"></span>
    <span class="recovery-page__glow recovery-page__glow--two" aria-hidden="true"></span>

    <main class="recovery-shell" aria-labelledby="recovery-title">
      <section class="recovery-guide" aria-label="Pasos para recuperar la clave">
        <div class="recovery-guide__pattern" aria-hidden="true"></div>

        <header class="recovery-brand">
          <span class="recovery-brand__logo">
            <img :src="cnscLogo" alt="Colegio Nuestra Señora del Carmen" />
          </span>
          <span class="recovery-brand__name">
            <strong>CNSC</strong>
            <span>Gestión institucional</span>
          </span>
        </header>

        <div class="recovery-guide__content">
          <span class="recovery-guide__eyebrow">
            <i class="bx bx-shield-quarter" aria-hidden="true"></i>
            Recuperación segura
          </span>
          <h1>Volver a tu cuenta es simple.</h1>
          <p>
            Te acompañamos paso a paso para que recuperes el acceso a tu espacio de trabajo.
          </p>

          <ol class="recovery-steps">
            <li v-for="step in recoverySteps" :key="step.number" class="recovery-step">
              <span>{{ step.number }}</span>
              <div>
                <strong>{{ step.title }}</strong>
                <p>{{ step.description }}</p>
              </div>
            </li>
          </ol>
        </div>

        <footer class="recovery-guide__footer">
          <i class="bx bx-lock-alt" aria-hidden="true"></i>
          <span>El enlace de recuperación es personal y de uso único.</span>
        </footer>
      </section>

      <section class="recovery-access">
        <header class="recovery-mobile-brand">
          <span class="recovery-brand__logo recovery-brand__logo--mobile">
            <img :src="cnscLogo" alt="Colegio Nuestra Señora del Carmen" />
          </span>
          <span>
            <strong>CNSC Gestión</strong>
            <small>Colegio Nuestra Señora del Carmen</small>
          </span>
        </header>

        <div class="recovery-form-wrap">
          <template v-if="!tryingToReset">
            <div class="recovery-icon" aria-hidden="true">
              <i class="bx bx-key"></i>
              <span><i class="bx bx-check"></i></span>
            </div>

            <div class="recovery-form-heading">
              <span class="recovery-security-pill">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                Enlace por correo
              </span>
              <h2 id="recovery-title">Recupera tu clave</h2>
              <p>
                Escribe tu correo institucional y te enviaremos las instrucciones para crear una nueva clave.
              </p>
            </div>

            <BAlert
              v-model="isResetError"
              variant="danger"
              class="recovery-alert"
              dismissible
              aria-live="assertive"
            >
              <i class="bx bx-error-circle" aria-hidden="true"></i>
              <span>{{ error }}</span>
            </BAlert>

            <BForm
              class="recovery-form"
              method="POST"
              aria-label="Formulario de recuperación de clave"
              @submit.prevent="forget"
            >
              <BFormGroup
                label="Correo institucional"
                label-for="recovery-email"
                class="recovery-field"
              >
                <div class="recovery-input-shell">
                  <i class="bx bx-envelope" aria-hidden="true"></i>
                  <BFormInput
                    id="recovery-email"
                    v-model.trim="email"
                    name="email"
                    type="email"
                    inputmode="email"
                    autocomplete="email"
                    placeholder="nombre@cnscvaldivia.cl"
                    required
                    :disabled="processing"
                    @input="clearError"
                  />
                </div>
              </BFormGroup>

              <BButton
                variant="primary"
                type="submit"
                :disabled="processing"
                :aria-busy="processing"
                class="recovery-submit"
              >
                <span>{{ processing ? "Enviando instrucciones..." : "Enviar enlace de recuperación" }}</span>
                <i
                  class="bx"
                  :class="processing ? 'bx-loader-alt bx-spin' : 'bx-right-arrow-alt'"
                  aria-hidden="true"
                ></i>
              </BButton>
            </BForm>

            <div class="recovery-help">
              <span><i class="bx bx-info-circle" aria-hidden="true"></i></span>
              <p>
                Si no reconoces el correo de tu cuenta, solicita ayuda a un administrador del sistema.
              </p>
            </div>
          </template>

          <div v-else class="recovery-success" aria-live="polite">
            <div class="recovery-success__icon" aria-hidden="true">
              <i class="bx bx-mail-send"></i>
              <span><i class="bx bx-check"></i></span>
            </div>
            <span class="recovery-success__eyebrow">Solicitud enviada</span>
            <h2 id="recovery-title">Revisa tu correo</h2>
            <p>{{ status }}</p>

            <div class="recovery-success__note">
              <i class="bx bx-time-five" aria-hidden="true"></i>
              <div>
                <strong>¿No aparece el mensaje?</strong>
                <span>Revisa la carpeta de correo no deseado antes de volver a intentarlo.</span>
              </div>
            </div>

            <router-link to="/login" class="recovery-success__action">
              <i class="bx bx-left-arrow-alt" aria-hidden="true"></i>
              Volver a iniciar sesión
            </router-link>
          </div>
        </div>

        <footer class="recovery-access__footer">
          <router-link to="/login">
            <i class="bx bx-left-arrow-alt" aria-hidden="true"></i>
            Volver al inicio de sesión
          </router-link>
          <span>© {{ new Date().getFullYear() }} CNSC Gestión</span>
        </footer>
      </section>
    </main>
  </div>
</template>

<style scoped>
.recovery-page {
  --recovery-navy: #173e5a;
  --recovery-blue: #2b6682;
  --recovery-indigo: #5368dc;
  --recovery-ink: #202a3b;
  --recovery-muted: #738096;
  position: relative;
  display: grid;
  min-height: 100dvh;
  place-items: center;
  overflow: hidden;
  padding: clamp(1rem, 2vh, 2rem);
  background:
    radial-gradient(circle at 10% 14%, rgba(77, 148, 180, 0.18), transparent 25%),
    radial-gradient(circle at 91% 86%, rgba(101, 119, 214, 0.14), transparent 28%),
    linear-gradient(145deg, #f5f8fc 0%, #edf3f9 52%, #f8f9fc 100%);
  color: var(--recovery-ink);
}

.recovery-page__glow {
  position: absolute;
  border: 1px solid rgba(57, 99, 137, 0.08);
  border-radius: 50%;
  pointer-events: none;
}

.recovery-page__glow--one {
  top: -20rem;
  left: -13rem;
  width: 40rem;
  height: 40rem;
}

.recovery-page__glow--two {
  right: -16rem;
  bottom: -23rem;
  width: 46rem;
  height: 46rem;
}

.recovery-shell {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: minmax(390px, 0.88fr) minmax(460px, 1.12fr);
  width: min(1080px, 100%);
  min-height: min(650px, calc(100dvh - 2rem));
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.84);
  border-radius: clamp(1.35rem, 2.5vw, 2rem);
  background: #fff;
  box-shadow: 0 30px 80px rgba(31, 51, 79, 0.16);
}

.recovery-guide {
  position: relative;
  display: flex;
  min-width: 0;
  flex-direction: column;
  overflow: hidden;
  padding: clamp(1.7rem, 3vw, 2.5rem);
  background:
    radial-gradient(circle at 88% 9%, rgba(148, 205, 218, 0.28), transparent 25%),
    radial-gradient(circle at 10% 90%, rgba(240, 181, 98, 0.15), transparent 29%),
    linear-gradient(150deg, #143a55 0%, #245d79 57%, #34798b 100%);
  color: #fff;
}

.recovery-guide::after {
  position: absolute;
  right: -10rem;
  bottom: -12rem;
  width: 27rem;
  height: 27rem;
  border: 1px solid rgba(255, 255, 255, 0.11);
  border-radius: 50%;
  content: "";
}

.recovery-guide__pattern {
  position: absolute;
  inset: 0;
  opacity: 0.16;
  background-image: radial-gradient(rgba(255, 255, 255, 0.72) 0.7px, transparent 0.7px);
  background-size: 23px 23px;
  mask-image: linear-gradient(135deg, #000, transparent 58%);
  pointer-events: none;
}

.recovery-brand {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.recovery-brand__logo {
  display: inline-flex;
  flex: 0 0 3.4rem;
  align-items: center;
  justify-content: center;
  width: 3.4rem;
  height: 3.4rem;
  border: 1px solid rgba(255, 255, 255, 0.72);
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 12px 24px rgba(6, 30, 48, 0.18);
}

.recovery-brand__logo img {
  display: block;
  width: 2.75rem;
  height: 2.75rem;
  object-fit: contain;
}

.recovery-brand__name {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}

.recovery-brand__name strong {
  color: #fff;
  font-size: 1.03rem;
  letter-spacing: 0.05em;
}

.recovery-brand__name span {
  margin-top: 0.2rem;
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.72rem;
}

.recovery-guide__content {
  position: relative;
  z-index: 1;
  margin: auto 0;
  padding: 2rem 0;
}

.recovery-guide__eyebrow,
.recovery-security-pill,
.recovery-success__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.66rem;
  font-weight: 800;
  letter-spacing: 0.075em;
  text-transform: uppercase;
}

.recovery-guide__eyebrow {
  padding: 0.45rem 0.72rem;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.88);
}

.recovery-guide h1 {
  max-width: 360px;
  margin: 1.2rem 0 0;
  color: #fff;
  font-size: clamp(2.3rem, 3.6vw, 3.35rem);
  font-weight: 820;
  letter-spacing: -0.055em;
  line-height: 1;
}

.recovery-guide__content > p {
  max-width: 360px;
  margin: 0.9rem 0 0;
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.82rem;
  line-height: 1.55;
}

.recovery-steps {
  display: grid;
  gap: 0.62rem;
  margin: 1.5rem 0 0;
  padding: 0;
  list-style: none;
}

.recovery-step {
  display: flex;
  align-items: center;
  gap: 0.78rem;
  padding: 0.72rem 0.82rem;
  border: 1px solid rgba(255, 255, 255, 0.11);
  border-radius: 0.9rem;
  background: rgba(255, 255, 255, 0.075);
  backdrop-filter: blur(8px);
}

.recovery-step > span {
  display: inline-flex;
  flex: 0 0 2.2rem;
  align-items: center;
  justify-content: center;
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 0.7rem;
  background: rgba(255, 255, 255, 0.12);
  color: #d9f1f5;
  font-size: 0.67rem;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.recovery-step strong {
  display: block;
  color: #fff;
  font-size: 0.75rem;
}

.recovery-step p {
  margin: 0.12rem 0 0;
  color: rgba(255, 255, 255, 0.61);
  font-size: 0.63rem;
  line-height: 1.35;
}

.recovery-guide__footer {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.11);
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.66rem;
  font-weight: 650;
}

.recovery-access {
  display: flex;
  min-width: 0;
  flex-direction: column;
  padding: clamp(1.6rem, 4vw, 3.6rem);
  background:
    radial-gradient(circle at 100% 0, rgba(93, 121, 217, 0.07), transparent 29%),
    #fff;
}

.recovery-mobile-brand {
  display: none;
}

.recovery-form-wrap {
  width: min(430px, 100%);
  margin: auto;
}

.recovery-icon,
.recovery-success__icon {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 4.15rem;
  height: 4.15rem;
  margin-bottom: 1.2rem;
  border: 1px solid #e0e6f8;
  border-radius: 1.3rem;
  background: linear-gradient(145deg, #f5f7ff, #ecf0ff);
  color: var(--recovery-indigo);
  box-shadow: 0 14px 28px rgba(77, 96, 190, 0.12);
}

.recovery-icon > i,
.recovery-success__icon > i {
  font-size: 1.75rem;
}

.recovery-icon > span,
.recovery-success__icon > span {
  position: absolute;
  right: -0.28rem;
  bottom: -0.28rem;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.55rem;
  height: 1.55rem;
  border: 3px solid #fff;
  border-radius: 50%;
  background: #43a47c;
  color: #fff;
  font-size: 0.82rem;
}

.recovery-security-pill {
  padding: 0.43rem 0.66rem;
  border: 1px solid rgba(66, 145, 116, 0.13);
  border-radius: 999px;
  background: #eef8f4;
  color: #377f65;
}

.recovery-form-heading h2,
.recovery-success h2 {
  margin: 0.9rem 0 0;
  color: var(--recovery-ink);
  font-size: clamp(2rem, 3.3vw, 2.65rem);
  font-weight: 820;
  letter-spacing: -0.055em;
  line-height: 1.05;
}

.recovery-form-heading p,
.recovery-success > p {
  margin: 0.65rem 0 0;
  color: var(--recovery-muted);
  font-size: 0.84rem;
  line-height: 1.55;
}

.recovery-alert {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  margin: 1.15rem 0 0;
  border: 1px solid #f3cbd1;
  border-radius: 0.85rem;
  background: #fff3f5;
  color: #a83f4d;
  font-size: 0.76rem;
}

.recovery-alert i {
  margin-top: 0.1rem;
  font-size: 1rem;
}

.recovery-form {
  margin-top: 1.4rem;
}

.recovery-field {
  margin-bottom: 1.05rem;
}

.recovery-field :deep(.form-label) {
  margin-bottom: 0.45rem;
  color: #354054;
  font-size: 0.73rem;
  font-weight: 760;
}

.recovery-input-shell {
  display: flex;
  align-items: center;
  min-height: 3.35rem;
  overflow: hidden;
  border: 1px solid #dfe5ed;
  border-radius: 0.9rem;
  background: #fbfcfe;
  transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.recovery-input-shell:focus-within {
  border-color: rgba(83, 104, 220, 0.62);
  background: #fff;
  box-shadow: 0 0 0 0.24rem rgba(83, 104, 220, 0.1);
}

.recovery-input-shell > i {
  flex: 0 0 auto;
  margin-left: 1rem;
  color: #8b97aa;
  font-size: 1.05rem;
}

.recovery-input-shell :deep(.form-control) {
  min-width: 0;
  min-height: 3.2rem;
  padding: 0.8rem;
  border: 0;
  background: transparent;
  color: #273246;
  font-size: 0.82rem;
  box-shadow: none !important;
}

.recovery-input-shell :deep(.form-control::placeholder) {
  color: #a0a9b7;
}

.recovery-submit {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  min-height: 3.35rem;
  padding: 0.8rem 1.15rem;
  border: 0;
  border-radius: 0.92rem;
  background: linear-gradient(120deg, #4058c3, #6479df);
  box-shadow: 0 14px 28px rgba(68, 88, 194, 0.23);
  font-size: 0.78rem;
  font-weight: 780;
  letter-spacing: 0.01em;
  transition: transform 160ms ease, box-shadow 160ms ease;
}

.recovery-submit:not(:disabled):hover,
.recovery-submit:not(:disabled):focus-visible {
  background: linear-gradient(120deg, #354cb7, #586ed6);
  box-shadow: 0 17px 32px rgba(68, 88, 194, 0.3);
  transform: translateY(-1px);
}

.recovery-submit i {
  font-size: 1.18rem;
}

.recovery-help,
.recovery-success__note {
  display: flex;
  gap: 0.72rem;
  margin-top: 1rem;
  padding: 0.82rem 0.9rem;
  border: 1px solid #e5eaf1;
  border-radius: 0.9rem;
  background: linear-gradient(135deg, #fbfcfe, #f7f9fc);
}

.recovery-help > span,
.recovery-success__note > i {
  display: inline-flex;
  flex: 0 0 2rem;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.65rem;
  background: #edf2ff;
  color: #5368d4;
  font-size: 1rem;
}

.recovery-help p {
  align-self: center;
  margin: 0;
  color: #7c8799;
  font-size: 0.65rem;
  line-height: 1.45;
}

.recovery-success {
  padding: 0.5rem 0;
}

.recovery-success__icon {
  width: 4.8rem;
  height: 4.8rem;
  border-color: #d8eee6;
  background: linear-gradient(145deg, #f3fbf8, #e8f7f1);
  color: #3b9671;
  box-shadow: 0 14px 28px rgba(57, 143, 108, 0.12);
}

.recovery-success__icon > i {
  font-size: 2rem;
}

.recovery-success__eyebrow {
  color: #3c8d6d;
}

.recovery-success__note {
  margin-top: 1.35rem;
}

.recovery-success__note > div {
  display: flex;
  flex-direction: column;
}

.recovery-success__note strong {
  color: #414c60;
  font-size: 0.69rem;
}

.recovery-success__note span {
  margin-top: 0.18rem;
  color: #828d9e;
  font-size: 0.63rem;
  line-height: 1.4;
}

.recovery-success__action {
  display: inline-flex;
  align-items: center;
  gap: 0.38rem;
  margin-top: 1.35rem;
  color: #4e63ce;
  font-size: 0.75rem;
  font-weight: 760;
}

.recovery-success__action:hover {
  color: #344bb9;
}

.recovery-access__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-top: 1.25rem;
  color: #8b95a5;
  font-size: 0.62rem;
}

.recovery-access__footer a {
  display: inline-flex;
  align-items: center;
  gap: 0.28rem;
  color: #637086;
  font-weight: 700;
}

.recovery-access__footer a:hover {
  color: #4e63ce;
}

.recovery-access__footer span {
  flex: 0 0 auto;
  font-weight: 700;
}

@media (max-width: 991.98px) {
  .recovery-page {
    align-items: start;
    overflow-y: auto;
  }

  .recovery-shell {
    display: block;
    width: min(620px, 100%);
    min-height: 0;
  }

  .recovery-guide {
    display: none;
  }

  .recovery-access {
    min-height: calc(100dvh - 2rem);
  }

  .recovery-mobile-brand {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #edf0f5;
  }

  .recovery-brand__logo--mobile {
    flex-basis: 3rem;
    width: 3rem;
    height: 3rem;
    border-color: #e6eaf0;
    border-radius: 0.85rem;
    box-shadow: 0 8px 18px rgba(34, 51, 77, 0.09);
  }

  .recovery-brand__logo--mobile img {
    width: 2.4rem;
    height: 2.4rem;
  }

  .recovery-mobile-brand > span:last-child {
    display: flex;
    flex-direction: column;
  }

  .recovery-mobile-brand strong {
    color: #263146;
    font-size: 0.84rem;
  }

  .recovery-mobile-brand small {
    margin-top: 0.16rem;
    color: #818c9d;
    font-size: 0.62rem;
  }
}

@media (max-width: 575.98px) {
  .recovery-page {
    display: block;
    min-height: 100dvh;
    padding: 0;
    background: #fff;
  }

  .recovery-page__glow {
    display: none;
  }

  .recovery-shell {
    min-height: 100dvh;
    border: 0;
    border-radius: 0;
    box-shadow: none;
  }

  .recovery-access {
    min-height: 100dvh;
    padding: 1.15rem;
  }

  .recovery-form-wrap {
    margin: auto 0;
    padding: 1.35rem 0;
  }

  .recovery-icon,
  .recovery-success__icon {
    width: 3.8rem;
    height: 3.8rem;
    margin-bottom: 1rem;
    border-radius: 1.1rem;
  }

  .recovery-security-pill,
  .recovery-success__eyebrow {
    font-size: 0.58rem;
  }

  .recovery-form-heading h2,
  .recovery-success h2 {
    font-size: 2rem;
  }

  .recovery-form-heading p,
  .recovery-success > p {
    font-size: 0.78rem;
  }

  .recovery-form {
    margin-top: 1.25rem;
  }

  .recovery-input-shell {
    min-height: 3.2rem;
  }

  .recovery-access__footer {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.3rem;
    padding-top: 1rem;
  }
}
</style>
