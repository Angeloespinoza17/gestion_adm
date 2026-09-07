<script>
import axios from "axios";

const cnscLogo = "/brand/logo-cnsc.png";

export default {
  data() {
    return {
      auth: {
        email: "",
        password: "",
      },
      highlights: [
        {
          icon: "bx-buildings",
          title: "Operación conectada",
          description: "Espacios, inventario y mantención en un mismo entorno.",
        },
        {
          icon: "bx-group",
          title: "Acceso por perfil",
          description: "Cada funcionario visualiza solo los módulos autorizados.",
        },
        {
          icon: "bx-calendar-check",
          title: "Jornada organizada",
          description: "Tareas, comunicaciones y fechas relevantes siempre disponibles.",
        },
      ],
      cnscLogo,
      processing: false,
      showPassword: false,
      authError: null,
      isAuthError: false,
    };
  },
  beforeCreate() {
    if (localStorage.getItem("user")) {
      this.$router.push("/inicio");
    }
  },
  methods: {
    clearAuthError() {
      this.authError = null;
      this.isAuthError = false;
    },
    togglePassword() {
      this.showPassword = !this.showPassword;
    },
    async login() {
      this.processing = true;
      this.clearAuthError();

      await axios
        .post("/api/login", this.auth)
        .then(({ data }) => {
          if (data.success === true && data.message === "success") {
            const user = data.data.user;
            const token = data.data.token;

            if (token) {
              localStorage.setItem("token", token);
              localStorage.removeItem("permissions");
              localStorage.removeItem("impersonator_token");
              axios.defaults.headers.common.Authorization = `Bearer ${token}`;
              document.cookie = `cnsc_token=${encodeURIComponent(token)}; path=/; samesite=lax`;
            }

            const loggedUser = {
              login: true,
              user_id: user.id,
              name: user.name,
              email: user.email,
              user_type: user.user_type || null,
              staff_id: user.staff_id || null,
              student_id: user.student_id || null,
              guardian_id: user.guardian_id || null,
              is_staff: user.is_staff === true,
              profile_photo_url: user.profile_photo_url || null,
            };
            localStorage.setItem("user", JSON.stringify(loggedUser));
            this.$router.push("/inicio");
          } else if (data.data === 400) {
            this.authError = data.message;
            this.isAuthError = true;
          }
        })
        .catch((error) => {
          this.authError = error?.response?.data?.message || "No fue posible iniciar sesión.";
          this.isAuthError = true;
        })
        .finally(() => {
          this.processing = false;
        });
    },
  },
};
</script>

<template>
  <div class="login-page">
    <span class="login-page__glow login-page__glow--one" aria-hidden="true"></span>
    <span class="login-page__glow login-page__glow--two" aria-hidden="true"></span>

    <main class="login-shell" aria-labelledby="login-title">
      <section class="login-story" aria-label="Portal institucional CNSC">
        <div class="login-story__pattern" aria-hidden="true"></div>

        <header class="login-brand">
          <span class="login-brand__logo">
            <img
              :src="cnscLogo"
              alt="Colegio Nuestra Señora del Carmen"
              width="44"
              height="44"
              data-cnsc-auth-logo
            />
          </span>
          <span class="login-brand__name">
            <strong>CNSC</strong>
            <span>Gestión institucional</span>
          </span>
        </header>

        <div class="login-story__content">
          <span class="login-story__eyebrow"><i class="bx bx-shield-quarter"></i> Portal interno protegido</span>
          <h1>Un acceso.<br />Toda tu jornada.</h1>
          <p>
            El espacio de trabajo del Colegio Nuestra Señora del Carmen para coordinar,
            comunicar y gestionar con claridad.
          </p>

          <div class="login-capabilities">
            <article v-for="item in highlights" :key="item.title" class="login-capability">
              <span><i class="bx" :class="item.icon"></i></span>
              <div>
                <strong>{{ item.title }}</strong>
                <p>{{ item.description }}</p>
              </div>
            </article>
          </div>
        </div>

        <footer class="login-story__footer">
          <span><i class="bx bx-lock-alt"></i> Acceso seguro</span>
          <span><i class="bx bx-check-shield"></i> Permisos según perfil</span>
        </footer>
      </section>

      <section class="login-access">
        <header class="login-mobile-brand">
          <span class="login-brand__logo login-brand__logo--mobile">
            <img
              :src="cnscLogo"
              alt="Colegio Nuestra Señora del Carmen"
              width="44"
              height="44"
              data-cnsc-auth-logo
            />
          </span>
          <span>
            <strong>CNSC Gestión</strong>
            <small>Colegio Nuestra Señora del Carmen</small>
          </span>
        </header>

        <div class="login-form-wrap">
          <div class="login-form-heading">
            <span class="login-security-pill"><i class="bx bx-lock-alt"></i> Entorno institucional seguro</span>
            <h2 id="login-title">Bienvenido de vuelta</h2>
            <p>Ingresa con tu cuenta institucional para continuar a tu espacio de trabajo.</p>
          </div>

          <BAlert
            v-model="isAuthError"
            variant="danger"
            class="login-alert"
            dismissible
            aria-live="polite"
          >
            <i class="bx bx-error-circle"></i>
            <span>{{ authError }}</span>
          </BAlert>

          <BForm class="login-form" method="POST" aria-label="Formulario de acceso" @submit.prevent="login">
            <BFormGroup label="Correo institucional" label-for="login-email" class="login-field">
              <div class="login-input-shell">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                <BFormInput
                  id="login-email"
                  v-model.trim="auth.email"
                  name="email"
                  type="email"
                  inputmode="email"
                  autocomplete="email"
                  placeholder="nombre@cnscvaldivia.cl"
                  required
                  @input="clearAuthError"
                />
              </div>
            </BFormGroup>

            <BFormGroup label="Clave de acceso" label-for="login-password" class="login-field">
              <div class="login-input-shell">
                <i class="bx bx-key" aria-hidden="true"></i>
                <BFormInput
                  id="login-password"
                  v-model="auth.password"
                  name="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  placeholder="Ingresa tu clave"
                  required
                  @input="clearAuthError"
                />
                <button
                  type="button"
                  class="login-password-toggle"
                  :aria-label="showPassword ? 'Ocultar clave' : 'Mostrar clave'"
                  :aria-pressed="showPassword"
                  @click="togglePassword"
                >
                  <i class="bx" :class="showPassword ? 'bx-hide' : 'bx-show'"></i>
                </button>
              </div>
            </BFormGroup>

            <div class="login-form__meta">
              <span><i class="bx bx-shield-quarter"></i> Credenciales protegidas</span>
              <router-link to="/forget-password">¿Olvidaste tu clave?</router-link>
            </div>

            <BButton
              variant="primary"
              type="submit"
              :disabled="processing"
              :aria-busy="processing"
              class="login-submit"
            >
              <span>{{ processing ? "Validando acceso..." : "Ingresar al panel" }}</span>
              <i class="bx" :class="processing ? 'bx-loader-alt bx-spin' : 'bx-right-arrow-alt'"></i>
            </BButton>
          </BForm>

          <div class="login-access-note">
            <span><i class="bx bx-user-check"></i></span>
            <div>
              <strong>Acceso exclusivo para personal autorizado</strong>
              <p>Los módulos y datos disponibles se ajustan a los permisos de tu cuenta.</p>
            </div>
          </div>
        </div>

        <footer class="login-access__footer">
          <p>¿Necesitas acceso? Solicita tus credenciales a un administrador.</p>
          <span>© {{ new Date().getFullYear() }} CNSC Gestión</span>
        </footer>
      </section>
    </main>
  </div>
</template>

<style scoped>
.login-page {
  --login-navy: #173e5a;
  --login-blue: #2b6682;
  --login-indigo: #5368dc;
  --login-ink: #202a3b;
  --login-muted: #738096;
  position: relative;
  display: grid;
  min-height: 100dvh;
  place-items: center;
  overflow: hidden;
  padding: clamp(1rem, 2vh, 2rem);
  background:
    radial-gradient(circle at 8% 12%, rgba(92, 153, 190, 0.18), transparent 25%),
    radial-gradient(circle at 92% 84%, rgba(104, 122, 218, 0.14), transparent 27%),
    linear-gradient(145deg, #f5f8fc 0%, #edf3f9 52%, #f7f8fc 100%);
  color: var(--login-ink);
}

.login-page__glow {
  position: absolute;
  border: 1px solid rgba(57, 99, 137, 0.08);
  border-radius: 50%;
  pointer-events: none;
}

.login-page__glow--one {
  top: -18rem;
  left: -12rem;
  width: 38rem;
  height: 38rem;
}

.login-page__glow--two {
  right: -15rem;
  bottom: -22rem;
  width: 44rem;
  height: 44rem;
}

.login-shell {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: minmax(0, 1.02fr) minmax(470px, 0.98fr);
  width: min(1180px, 100%);
  min-height: min(700px, calc(100dvh - 2rem));
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: clamp(1.35rem, 2.5vw, 2rem);
  background: #fff;
  box-shadow: 0 30px 80px rgba(31, 51, 79, 0.16);
}

.login-story {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
  padding: clamp(1.75rem, 3vw, 2.5rem);
  background:
    radial-gradient(circle at 88% 8%, rgba(148, 205, 218, 0.28), transparent 25%),
    radial-gradient(circle at 12% 88%, rgba(240, 181, 98, 0.16), transparent 28%),
    linear-gradient(150deg, #143a55 0%, #245d79 56%, #34798b 100%);
  color: #fff;
}

.login-story::after {
  position: absolute;
  right: -9rem;
  bottom: -11rem;
  width: 25rem;
  height: 25rem;
  border: 1px solid rgba(255, 255, 255, 0.11);
  border-radius: 50%;
  content: "";
}

.login-story__pattern {
  position: absolute;
  inset: 0;
  opacity: 0.16;
  background-image: radial-gradient(rgba(255, 255, 255, 0.72) 0.7px, transparent 0.7px);
  background-size: 23px 23px;
  mask-image: linear-gradient(135deg, #000, transparent 58%);
  pointer-events: none;
}

.login-brand {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.login-brand__logo {
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

.login-brand__logo img {
  display: block;
  width: 2.75rem;
  height: 2.75rem;
  object-fit: contain;
}

.login-brand__name {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}

.login-brand__name strong {
  color: #fff;
  font-size: 1.03rem;
  letter-spacing: 0.05em;
}

.login-brand__name span {
  margin-top: 0.2rem;
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.72rem;
}

.login-story__content {
  position: relative;
  z-index: 1;
  width: min(470px, 100%);
  margin: auto 0;
  padding: 2rem 0;
}

.login-story__eyebrow,
.login-security-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border-radius: 999px;
  font-size: 0.66rem;
  font-weight: 800;
  letter-spacing: 0.075em;
  text-transform: uppercase;
}

.login-story__eyebrow {
  padding: 0.45rem 0.72rem;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.88);
}

.login-story h1 {
  max-width: 480px;
  margin: 1.2rem 0 0;
  color: #fff;
  font-size: clamp(2.45rem, 4.1vw, 3.75rem);
  font-weight: 820;
  letter-spacing: -0.058em;
  line-height: 0.98;
}

.login-story__content > p {
  max-width: 450px;
  margin: 1rem 0 0;
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.9rem;
  line-height: 1.6;
}

.login-capabilities {
  display: grid;
  gap: 0.68rem;
  margin-top: 1.55rem;
}

.login-capability {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.78rem 0.9rem;
  border: 1px solid rgba(255, 255, 255, 0.11);
  border-radius: 0.95rem;
  background: rgba(255, 255, 255, 0.075);
  backdrop-filter: blur(8px);
}

.login-capability > span {
  display: inline-flex;
  flex: 0 0 2.3rem;
  align-items: center;
  justify-content: center;
  width: 2.3rem;
  height: 2.3rem;
  border-radius: 0.72rem;
  background: rgba(255, 255, 255, 0.12);
  color: #d9f1f5;
  font-size: 1.1rem;
}

.login-capability strong,
.login-capability p {
  display: block;
}

.login-capability strong {
  color: #fff;
  font-size: 0.78rem;
}

.login-capability p {
  margin: 0.12rem 0 0;
  color: rgba(255, 255, 255, 0.62);
  font-size: 0.67rem;
  line-height: 1.35;
}

.login-story__footer {
  position: relative;
  z-index: 1;
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem 1.15rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.11);
}

.login-story__footer span {
  display: inline-flex;
  align-items: center;
  gap: 0.36rem;
  color: rgba(255, 255, 255, 0.68);
  font-size: 0.67rem;
  font-weight: 650;
}

.login-access {
  display: flex;
  min-width: 0;
  flex-direction: column;
  padding: clamp(1.6rem, 3.4vw, 3rem);
  background:
    radial-gradient(circle at 100% 0, rgba(93, 121, 217, 0.065), transparent 28%),
    #fff;
}

.login-mobile-brand {
  display: none;
}

.login-form-wrap {
  width: min(430px, 100%);
  margin: auto;
}

.login-security-pill {
  padding: 0.43rem 0.66rem;
  border: 1px solid rgba(66, 145, 116, 0.13);
  background: #eef8f4;
  color: #377f65;
}

.login-form-heading h2 {
  margin: 1rem 0 0;
  color: var(--login-ink);
  font-size: clamp(2rem, 3.3vw, 2.65rem);
  font-weight: 820;
  letter-spacing: -0.055em;
  line-height: 1.05;
}

.login-form-heading p {
  margin: 0.65rem 0 0;
  color: var(--login-muted);
  font-size: 0.84rem;
  line-height: 1.55;
}

.login-alert {
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

.login-alert i {
  margin-top: 0.1rem;
  font-size: 1rem;
}

.login-form {
  margin-top: 1.45rem;
}

.login-field {
  margin-bottom: 1rem;
}

.login-field :deep(.form-label) {
  margin-bottom: 0.45rem;
  color: #354054;
  font-size: 0.73rem;
  font-weight: 760;
}

.login-input-shell {
  display: flex;
  align-items: center;
  min-height: 3.35rem;
  overflow: hidden;
  border: 1px solid #dfe5ed;
  border-radius: 0.9rem;
  background: #fbfcfe;
  transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.login-input-shell:focus-within {
  border-color: rgba(83, 104, 220, 0.62);
  background: #fff;
  box-shadow: 0 0 0 0.24rem rgba(83, 104, 220, 0.1);
}

.login-input-shell > i {
  flex: 0 0 auto;
  margin-left: 1rem;
  color: #8b97aa;
  font-size: 1.05rem;
}

.login-input-shell :deep(.form-control) {
  min-width: 0;
  min-height: 3.2rem;
  padding: 0.8rem 0.72rem;
  border: 0;
  background: transparent;
  color: #273246;
  font-size: 0.82rem;
  box-shadow: none !important;
}

.login-input-shell :deep(.form-control::placeholder) {
  color: #a0a9b7;
}

.login-password-toggle {
  display: inline-flex;
  flex: 0 0 2.65rem;
  align-items: center;
  justify-content: center;
  width: 2.65rem;
  height: 2.65rem;
  margin-right: 0.32rem;
  border: 0;
  border-radius: 0.7rem;
  background: transparent;
  color: #7a879a;
  font-size: 1.05rem;
  transition: background 150ms ease, color 150ms ease;
}

.login-password-toggle:hover,
.login-password-toggle:focus-visible {
  background: #eef1fb;
  color: var(--login-indigo);
  outline: none;
}

.login-form__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin: -0.05rem 0 1.2rem;
}

.login-form__meta span,
.login-form__meta a {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.69rem;
  font-weight: 680;
}

.login-form__meta span {
  color: #758298;
}

.login-form__meta a {
  color: #4e63ce;
}

.login-submit {
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

.login-submit:not(:disabled):hover,
.login-submit:not(:disabled):focus-visible {
  background: linear-gradient(120deg, #354cb7, #586ed6);
  box-shadow: 0 17px 32px rgba(68, 88, 194, 0.3);
  transform: translateY(-1px);
}

.login-submit i {
  font-size: 1.18rem;
}

.login-access-note {
  display: flex;
  gap: 0.72rem;
  margin-top: 1rem;
  padding: 0.82rem 0.9rem;
  border: 1px solid #e5eaf1;
  border-radius: 0.9rem;
  background: linear-gradient(135deg, #fbfcfe, #f7f9fc);
}

.login-access-note > span {
  display: inline-flex;
  flex: 0 0 2rem;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.65rem;
  background: #edf2ff;
  color: #5368d4;
}

.login-access-note strong {
  display: block;
  color: #414c60;
  font-size: 0.69rem;
}

.login-access-note p {
  margin: 0.18rem 0 0;
  color: #828d9e;
  font-size: 0.63rem;
  line-height: 1.4;
}

.login-access__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-top: 1.25rem;
  color: #8b95a5;
  font-size: 0.62rem;
}

.login-access__footer p {
  margin: 0;
}

.login-access__footer span {
  flex: 0 0 auto;
  font-weight: 700;
}

@media (max-width: 991.98px) {
  .login-page {
    align-items: start;
    overflow-y: auto;
  }

  .login-shell {
    display: block;
    width: min(620px, 100%);
    min-height: 0;
  }

  .login-story {
    display: none;
  }

  .login-access {
    min-height: calc(100dvh - 2rem);
  }

  .login-mobile-brand {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid #edf0f5;
  }

  .login-brand__logo--mobile {
    flex-basis: 3rem;
    width: 3rem;
    height: 3rem;
    border-color: #e6eaf0;
    border-radius: 0.85rem;
    box-shadow: 0 8px 18px rgba(34, 51, 77, 0.09);
  }

  .login-brand__logo--mobile img {
    width: 2.4rem;
    height: 2.4rem;
  }

  .login-mobile-brand > span:last-child {
    display: flex;
    flex-direction: column;
  }

  .login-mobile-brand strong {
    color: #263146;
    font-size: 0.84rem;
  }

  .login-mobile-brand small {
    margin-top: 0.16rem;
    color: #818c9d;
    font-size: 0.62rem;
  }
}

@media (max-width: 575.98px) {
  .login-page {
    display: block;
    min-height: 100dvh;
    padding: 0;
    background: #fff;
  }

  .login-page__glow {
    display: none;
  }

  .login-shell {
    min-height: 100dvh;
    border: 0;
    border-radius: 0;
    box-shadow: none;
  }

  .login-access {
    min-height: 100dvh;
    padding: 1.15rem;
  }

  .login-form-wrap {
    margin: auto 0;
    padding: 1.35rem 0;
  }

  .login-security-pill {
    font-size: 0.58rem;
  }

  .login-form-heading h2 {
    font-size: 2rem;
  }

  .login-form-heading p {
    font-size: 0.78rem;
  }

  .login-form {
    margin-top: 1.25rem;
  }

  .login-input-shell {
    min-height: 3.2rem;
  }

  .login-form__meta {
    align-items: flex-start;
  }

  .login-access-note {
    margin-top: 0.85rem;
  }

  .login-access__footer {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.25rem;
    padding-top: 1rem;
  }
}
</style>
