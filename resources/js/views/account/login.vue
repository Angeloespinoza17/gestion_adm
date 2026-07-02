<script>
import profileImg from '../../../images/profile-img.png';
import logo from '../../../images/logo.svg';
import axios from 'axios';

/**
 * Login component
 */
export default {
  data() {
    return {
      auth: {
        email: "",
        password: ""
      },
      highlights: [
        "Acceso centralizado para equipos administrativos y de gestion.",
        "Interfaz mas clara para revisar modulos, permisos y seguimiento.",
        "Experiencia visual moderna sin alterar el flujo de autenticacion."
      ],
      profileImg, logo,
      processing: false,
      authError: null,
      isAuthError: false,
    }
  },
  beforeCreate() {
    if (localStorage.getItem('user')) {
      this.$router.push('/inicio');
    }
  },
  methods: {
    async login() {
      this.processing = true
      await axios.post('/api/login', this.auth).then(({ data }) => {
        if (data.success == true && data.message == 'success') {
          const user = data.data.user;
          const token = data.data.token;

          if (token) {
            localStorage.setItem('token', token);
            localStorage.removeItem('permissions');
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            document.cookie = `cnsc_token=${encodeURIComponent(token)}; path=/; samesite=lax`;
          }

          const logged_user = {
            login: true,
            user_id: user.id,
            name: user.name,
            email: user.email,
          }
          localStorage.setItem('user', JSON.stringify(logged_user));
          this.$router.push('/inicio');
        } else {
          if (data.data == 400) {
            this.authError = data.message;
            this.isAuthError = true;
          }
        }
      }).catch((error) => {
        this.authError = error?.response?.data?.message || "No fue posible iniciar sesion.";
        this.isAuthError = true;
      }).finally(() => {
        this.processing = false
      })
    },
  }
};
</script>

<template>
  <div class="account-pages auth-premium-page aurora-bg">
    <BContainer>
      <BRow class="justify-content-center align-items-center">
        <BCol md="10" lg="9" xl="8">
          <BCard no-body class="auth-premium-card glass-card border-0">
            <BRow class="g-0">
              <BCol lg="5" class="d-none d-lg-block">
                <div class="auth-premium-aside h-100 d-flex flex-column justify-content-between">
                  <div>
                    <span class="dashboard-hero__eyebrow bg-white text-primary">Acceso seguro</span>
                    <h2 class="mt-4 mb-3 text-white">Bienvenido de vuelta</h2>
                    <p class="mb-0 text-white-50">
                      Ingresa al panel administrativo con una experiencia visual mas limpia,
                      moderna y consistente con Skote.
                    </p>
                  </div>

                  <div class="text-center my-4">
                    <img :src="profileImg" alt class="img-fluid" />
                  </div>

                  <div class="auth-premium-list">
                    <div
                      v-for="item in highlights"
                      :key="item"
                      class="auth-premium-list-item"
                    >
                      {{ item }}
                    </div>
                  </div>
                </div>
              </BCol>

              <BCol lg="7">
                <BCardBody class="auth-premium-body">
                  <router-link to="/" class="d-inline-flex mb-4">
                    <span class="auth-premium-brand">
                      <img :src="logo" alt height="30" />
                    </span>
                  </router-link>

                  <div class="mb-4">
                    <h3 class="mb-2">Iniciar sesion</h3>
                    <p class="text-muted mb-0">
                      Accede para continuar con la gestion diaria del sistema.
                    </p>
                  </div>

                  <BAlert v-model="isAuthError" variant="danger" class="mb-4" dismissible>{{ authError }}</BAlert>

                  <BForm class="auth-premium-form" action="javascript:void(0)" method="POST" @submit.prevent="login">
                    <slot />
                    <BFormGroup id="input-group-1" label="Correo electronico" label-for="input-1" class="mb-3">
                      <BFormInput id="input-1" name="email" v-model="auth.email" type="text" placeholder="Ingresa tu correo"></BFormInput>
                    </BFormGroup>

                    <BFormGroup id="input-group-2" label="Contrasena" label-for="input-2" class="mb-3">
                      <BFormInput id="input-2" v-model="auth.password" name="password" type="password" placeholder="Ingresa tu contrasena"></BFormInput>
                    </BFormGroup>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                      <BFormCheckbox id="customControlInline" name="checkbox-1" value="accepted" unchecked-value="not_accepted">
                        Recordarme
                      </BFormCheckbox>
                      <router-link to="/forget-password" class="text-muted">
                        <i class="mdi mdi-lock me-1"></i>Recuperar acceso
                      </router-link>
                    </div>

                    <div class="d-grid">
                      <BButton variant="primary" type="submit" :disabled="processing" class="premium-button btn-block">
                        {{ processing ? "Ingresando..." : "Entrar al sistema" }}
                      </BButton>
                    </div>

                    <div class="mt-4 text-center">
                      <h6 class="text-muted mb-3">Acceso social</h6>
                      <ul class="list-inline mb-0">
                        <li class="list-inline-item">
                          <BLink href="javascript: void(0);" class="social-list-item bg-primary text-white border-primary">
                            <i class="mdi mdi-facebook"></i>
                          </BLink>
                        </li>
                        <li class="list-inline-item">
                          <BLink href="javascript: void(0);" class="social-list-item bg-info text-white border-info">
                            <i class="mdi mdi-twitter"></i>
                          </BLink>
                        </li>
                        <li class="list-inline-item">
                          <BLink href="javascript: void(0);" class="social-list-item bg-danger text-white border-danger">
                            <i class="mdi mdi-google"></i>
                          </BLink>
                        </li>
                      </ul>
                    </div>
                  </BForm>
                </BCardBody>
              </BCol>
            </BRow>
          </BCard>

          <div class="mt-4 text-center">
            <p>
              No tienes una cuenta?
              <router-link to="/auth/register" class="fw-medium text-primary">Solicitar acceso</router-link>
            </p>
            <p>
              © {{ new Date().getFullYear() }} Skote. Interfaz administrativa modernizada sobre la base existente.
            </p>
          </div>
        </BCol>
      </BRow>
    </BContainer>
  </div>
</template>

