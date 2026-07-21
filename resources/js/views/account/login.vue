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
        "Porteria, inventario, mantencion y espacios en un panel operativo.",
        "Usuarios, roles y permisos ordenados por perfil de trabajo.",
        "Seguimiento de noticias, eventos y solicitudes internas del colegio."
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
            localStorage.removeItem('impersonator_token');
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            document.cookie = `cnsc_token=${encodeURIComponent(token)}; path=/; samesite=lax`;
          }

          const logged_user = {
            login: true,
            user_id: user.id,
            name: user.name,
            email: user.email,
            profile_photo_url: user.profile_photo_url || null,
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
                    <span class="dashboard-hero__eyebrow bg-white text-primary">Portal interno CNSC</span>
                    <h2 class="mt-4 mb-3 text-white">Gestion institucional</h2>
                    <p class="mb-0 text-white-50">
                      Accede a las herramientas de trabajo del colegio con tu
                      cuenta institucional autorizada.
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
                    <h3 class="mb-2">Acceso al panel CNSC</h3>
                    <p class="text-muted mb-0">
                      Ingresa con tus credenciales para continuar con tus modulos asignados.
                    </p>
                  </div>

                  <BAlert v-model="isAuthError" variant="danger" class="mb-4" dismissible>{{ authError }}</BAlert>

                  <BForm class="auth-premium-form" action="javascript:void(0)" method="POST" @submit.prevent="login">
                    <slot />
                    <BFormGroup id="input-group-1" label="Correo institucional" label-for="input-1" class="mb-3">
                      <BFormInput id="input-1" name="email" v-model="auth.email" type="text" placeholder="nombre@cnscvaldivia.cl"></BFormInput>
                    </BFormGroup>

                    <BFormGroup id="input-group-2" label="Clave de acceso" label-for="input-2" class="mb-3">
                      <BFormInput id="input-2" v-model="auth.password" name="password" type="password" placeholder="Ingresa tu clave"></BFormInput>
                    </BFormGroup>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                      <BFormCheckbox id="customControlInline" name="checkbox-1" value="accepted" unchecked-value="not_accepted">
                        Mantener sesion
                      </BFormCheckbox>
                      <router-link to="/forget-password" class="text-muted">
                        <i class="mdi mdi-lock me-1"></i>Recuperar clave
                      </router-link>
                    </div>

                    <div class="d-grid">
                      <BButton variant="primary" type="submit" :disabled="processing" class="premium-button btn-block">
                        {{ processing ? "Validando acceso..." : "Ingresar al panel" }}
                      </BButton>
                    </div>

                    <div class="mt-4 text-center">
                      <h6 class="text-muted mb-1">Solo cuentas institucionales</h6>
                      <p class="text-muted mb-0">
                        El ingreso esta reservado para funcionarios autorizados.
                      </p>
                    </div>
                  </BForm>
                </BCardBody>
              </BCol>
            </BRow>
          </BCard>

          <div class="mt-4 text-center">
            <p>
              Necesitas acceso al panel?
              <router-link to="/auth/register" class="fw-medium text-primary">Solicitar credenciales</router-link>
            </p>
            <p>
              © {{ new Date().getFullYear() }} CNSC Gestion. Portal interno del Colegio Nuestra Senora del Carmen.
            </p>
          </div>
        </BCol>
      </BRow>
    </BContainer>
  </div>
</template>

