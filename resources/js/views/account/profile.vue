<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import avatar1 from "@/assets/images/users/avatar-1.jpg";
import { useAuthStore } from "@/state/pinia";

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      avatar1,
      auth: useAuthStore(),
      loading: true,
      savingProfile: false,
      savingPassword: false,
      profile: null,
      profileForm: {
        name: "",
        remove_photo: false,
      },
      passwordForm: {
        current_password: "",
        password: "",
        password_confirmation: "",
      },
      selectedPhoto: null,
      photoPreview: null,
      profileMessage: null,
      profileError: null,
      passwordMessage: null,
      passwordError: null,
    };
  },
  computed: {
    avatarUrl() {
      return this.photoPreview || this.profile?.profile_photo_url || this.avatar1;
    },
    userInitial() {
      return (this.profile?.name || "U").trim().charAt(0).toUpperCase();
    },
    roleNames() {
      return (this.profile?.roles || []).map((role) => role.name);
    },
  },
  async mounted() {
    await this.loadProfile();
  },
  beforeUnmount() {
    if (this.photoPreview) {
      URL.revokeObjectURL(this.photoPreview);
    }
  },
  methods: {
    async loadProfile() {
      this.loading = true;
      this.profileError = null;

      try {
        const response = await axios.get("/api/me/profile");
        this.setProfile(response.data.data);
      } catch (error) {
        this.profileError = error?.response?.data?.message || "No fue posible cargar tu ficha.";
      } finally {
        this.loading = false;
      }
    },
    setProfile(profile) {
      this.profile = profile;
      this.profileForm.name = profile?.name || "";
      this.profileForm.remove_photo = false;
      this.selectedPhoto = null;

      if (this.photoPreview) {
        URL.revokeObjectURL(this.photoPreview);
        this.photoPreview = null;
      }

      const stored = {
        ...(JSON.parse(localStorage.getItem("user") || "{}")),
        login: true,
        user_id: profile.id,
        name: profile.name,
        email: profile.email,
        profile_photo_url: profile.profile_photo_url || null,
      };

      localStorage.setItem("user", JSON.stringify(stored));
      this.auth.currentUser = stored;
    },
    onPhotoChange(event) {
      const file = event.target.files?.[0] || null;
      this.selectedPhoto = file;
      this.profileForm.remove_photo = false;

      if (this.photoPreview) {
        URL.revokeObjectURL(this.photoPreview);
        this.photoPreview = null;
      }

      if (file) {
        this.photoPreview = URL.createObjectURL(file);
      }
    },
    markRemovePhoto() {
      this.selectedPhoto = null;
      this.profileForm.remove_photo = true;

      if (this.$refs.photoInput) {
        this.$refs.photoInput.value = "";
      }

      if (this.photoPreview) {
        URL.revokeObjectURL(this.photoPreview);
        this.photoPreview = null;
      }
    },
    async saveProfile() {
      const result = await Swal.fire({
        title: "Guardar ficha",
        text: "Se actualizaran tu nombre visible y foto de perfil.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Guardar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#556ee6",
        cancelButtonColor: "#74788d",
      });

      if (!result.isConfirmed) {
        return;
      }

      this.savingProfile = true;
      this.profileMessage = null;
      this.profileError = null;

      const formData = new FormData();
      formData.append("name", this.profileForm.name);
      formData.append("remove_photo", this.profileForm.remove_photo ? "1" : "0");

      if (this.selectedPhoto) {
        formData.append("photo", this.selectedPhoto);
      }

      try {
        const response = await axios.post("/api/me/profile", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.setProfile(response.data.data);
        this.profileMessage = response.data.message || "Ficha actualizada correctamente.";
        await Swal.fire({
          title: "Ficha guardada",
          text: this.profileMessage,
          icon: "success",
          confirmButtonText: "Aceptar",
          confirmButtonColor: "#556ee6",
        });
      } catch (error) {
        this.profileError = error?.response?.data?.message || "No fue posible actualizar tu ficha.";
        await Swal.fire({
          title: "No se pudo guardar",
          text: this.profileError,
          icon: "error",
          confirmButtonText: "Entendido",
          confirmButtonColor: "#556ee6",
        });
      } finally {
        this.savingProfile = false;
      }
    },
    async savePassword() {
      this.savingPassword = true;
      this.passwordMessage = null;
      this.passwordError = null;

      try {
        const response = await axios.put("/api/me/password", this.passwordForm);
        this.passwordForm = {
          current_password: "",
          password: "",
          password_confirmation: "",
        };
        this.passwordMessage = response.data.message || "Contrasena actualizada correctamente.";
      } catch (error) {
        this.passwordError = error?.response?.data?.message || "No fue posible cambiar la contrasena.";
      } finally {
        this.savingPassword = false;
      }
    },
  },
};
</script>

<template>
  <Layout>
    <div class="account-profile-page">
      <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div>
          <h4 class="mb-1">Mi ficha</h4>
          <p class="text-muted mb-0">Administra tu foto, nombre visible y clave de acceso.</p>
        </div>
      </div>

      <LoadingState v-if="loading" message="Cargando ficha..." compact />

      <BRow v-else class="g-4">
        <BCol xl="4">
          <BCard no-body class="profile-summary-card">
            <BCardBody>
              <div class="profile-avatar mx-auto mb-3">
                <img :src="avatarUrl" alt="" />
                <span>{{ userInitial }}</span>
              </div>
              <div class="text-center">
                <h5 class="mb-1">{{ profile.name }}</h5>
                <p class="text-muted mb-3">{{ profile.email }}</p>
              </div>

              <div class="profile-readonly-list">
                <div>
                  <span>Tipo</span>
                  <strong>{{ profile.user_type || "Usuario" }}</strong>
                </div>
                <div>
                  <span>Cargo</span>
                  <strong>{{ profile.cargo?.name || profile.staff?.position || "Sin cargo asignado" }}</strong>
                </div>
                <div v-if="profile.staff">
                  <span>Funcionario</span>
                  <strong>{{ profile.staff.full_name || "Ficha vinculada" }}</strong>
                </div>
              </div>

              <div v-if="roleNames.length" class="profile-role-list mt-4">
                <span v-for="role in roleNames" :key="role" class="badge bg-primary-subtle text-primary">
                  {{ role }}
                </span>
              </div>
            </BCardBody>
          </BCard>
        </BCol>

        <BCol xl="8">
          <BCard no-body>
            <BCardBody>
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <div>
                  <h5 class="mb-1">Datos visibles</h5>
                  <p class="text-muted mb-0">Solo puedes actualizar tu nombre visible y foto.</p>
                </div>
              </div>

              <BAlert v-if="profileMessage" show variant="success">{{ profileMessage }}</BAlert>
              <BAlert v-if="profileError" show variant="danger">{{ profileError }}</BAlert>

              <BForm @submit.prevent="saveProfile">
                <BRow>
                  <BCol md="6">
                    <BFormGroup label="Nombre visible" label-for="profile-name" class="mb-3">
                      <BFormInput id="profile-name" v-model="profileForm.name" required maxlength="255" />
                    </BFormGroup>
                  </BCol>
                  <BCol md="6">
                    <BFormGroup label="Correo electronico" label-for="profile-email" class="mb-3">
                      <BFormInput id="profile-email" :model-value="profile.email" disabled />
                    </BFormGroup>
                  </BCol>
                </BRow>

                <BFormGroup label="Foto de perfil" label-for="profile-photo" class="mb-3">
                  <input
                    id="profile-photo"
                    ref="photoInput"
                    type="file"
                    class="form-control"
                    accept="image/*"
                    @change="onPhotoChange"
                  />
                </BFormGroup>

                <div class="d-flex flex-wrap gap-2">
                  <BButton type="submit" variant="primary" :disabled="savingProfile">
                    {{ savingProfile ? "Guardando..." : "Guardar ficha" }}
                  </BButton>
                  <BButton
                    v-if="profile.profile_photo_url || photoPreview"
                    type="button"
                    variant="outline-secondary"
                    :disabled="savingProfile"
                    @click="markRemovePhoto"
                  >
                    Quitar foto
                  </BButton>
                </div>
              </BForm>
            </BCardBody>
          </BCard>

          <BCard no-body class="mt-4">
            <BCardBody>
              <h5 class="mb-1">Cambiar clave</h5>
              <p class="text-muted mb-4">Requiere tu clave actual para confirmar el cambio.</p>

              <BAlert v-if="passwordMessage" show variant="success">{{ passwordMessage }}</BAlert>
              <BAlert v-if="passwordError" show variant="danger">{{ passwordError }}</BAlert>

              <BForm @submit.prevent="savePassword">
                <BRow>
                  <BCol md="4">
                    <BFormGroup label="Clave actual" label-for="current-password" class="mb-3">
                      <BFormInput id="current-password" v-model="passwordForm.current_password" type="password" required />
                    </BFormGroup>
                  </BCol>
                  <BCol md="4">
                    <BFormGroup label="Nueva clave" label-for="new-password" class="mb-3">
                      <BFormInput id="new-password" v-model="passwordForm.password" type="password" required minlength="8" />
                    </BFormGroup>
                  </BCol>
                  <BCol md="4">
                    <BFormGroup label="Confirmar nueva clave" label-for="password-confirmation" class="mb-3">
                      <BFormInput id="password-confirmation" v-model="passwordForm.password_confirmation" type="password" required minlength="8" />
                    </BFormGroup>
                  </BCol>
                </BRow>

                <BButton type="submit" variant="primary" :disabled="savingPassword">
                  {{ savingPassword ? "Actualizando..." : "Actualizar clave" }}
                </BButton>
              </BForm>
            </BCardBody>
          </BCard>
        </BCol>
      </BRow>
    </div>
  </Layout>
</template>

<style scoped>
.account-profile-page {
  max-width: 1180px;
}

.profile-summary-card {
  position: sticky;
  top: 1rem;
}

.profile-avatar {
  position: relative;
  width: 7rem;
  height: 7rem;
  border-radius: 50%;
  overflow: hidden;
  background: #eef2f7;
  box-shadow: 0 16px 32px rgba(52, 58, 64, 0.12);
}

.profile-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-avatar span {
  position: absolute;
  inset: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: transparent;
  font-size: 2rem;
  font-weight: 700;
}

.profile-readonly-list {
  display: grid;
  gap: 0.75rem;
}

.profile-readonly-list > div {
  display: grid;
  gap: 0.2rem;
  padding: 0.8rem 0.9rem;
  border: 1px solid rgba(85, 110, 230, 0.12);
  border-radius: 0.8rem;
  background: rgba(255, 255, 255, 0.56);
}

.profile-readonly-list span {
  color: #74788d;
  font-size: 0.75rem;
}

.profile-readonly-list strong {
  color: #343a40;
  font-size: 0.92rem;
}

.profile-role-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}
</style>
