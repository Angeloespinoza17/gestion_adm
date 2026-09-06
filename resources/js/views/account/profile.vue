<script>
import axios from "axios";
import Swal from "sweetalert2";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import { useAuthStore } from "@/state/pinia";

const MAX_PHOTO_SIZE = 5 * 1024 * 1024;
const ACCEPTED_PHOTO_TYPES = ["image/jpeg", "image/png", "image/webp"];

export default {
  components: { Layout, LoadingState },
  data() {
    return {
      auth: useAuthStore(),
      loading: true,
      savingProfile: false,
      savingPassword: false,
      dragActive: false,
      profile: null,
      profileForm: { name: "", remove_photo: false },
      passwordForm: { current_password: "", password: "", password_confirmation: "" },
      passwordVisibility: { current_password: false, password: false, password_confirmation: false },
      selectedPhoto: null,
      photoPreview: null,
      profileMessage: null,
      profileError: null,
      profileErrors: {},
      passwordMessage: null,
      passwordError: null,
      passwordErrors: {},
    };
  },
  computed: {
    avatarUrl() {
      if (this.photoPreview) return this.photoPreview;
      if (this.profileForm.remove_photo) return this.profile?.staff?.profile_photo_url || null;
      return this.profile?.profile_photo_url || null;
    },
    userInitials() {
      return (this.profile?.name || "Usuario").trim().split(/\s+/).filter(Boolean)
        .slice(0, 2).map((word) => word.charAt(0).toUpperCase()).join("");
    },
    roleNames() {
      return (this.profile?.roles || []).map((role) => role.name);
    },
    primaryRole() {
      return this.roleNames[0] || this.profile?.cargo?.name || "Usuario institucional";
    },
    displayUserType() {
      return { staff: "Funcionario", student: "Estudiante", guardian: "Apoderado", admin: "Administrativo" }[this.profile?.user_type]
        || this.profile?.user_type || "Usuario";
    },
    accountPosition() {
      return this.profile?.cargo?.name || this.profile?.staff?.position || "Sin cargo asignado";
    },
    profileDirty() {
      return this.profileForm.name.trim() !== (this.profile?.name || "").trim()
        || Boolean(this.selectedPhoto) || this.profileForm.remove_photo;
    },
    canSaveProfile() {
      return this.profileDirty && Boolean(this.profileForm.name.trim()) && !this.savingProfile;
    },
    photoSourceLabel() {
      if (this.selectedPhoto) return "Nueva foto lista";
      if (this.profileForm.remove_photo) {
        return this.profile?.staff?.profile_photo_url ? "Se restaurará la foto institucional" : "Se quitará la foto";
      }
      if (this.profile?.profile_photo_source === "user") return "Foto personalizada";
      if (this.profile?.profile_photo_source === "staff") return "Foto institucional";
      return "Sin foto cargada";
    },
    passwordRequirements() {
      return [
        { key: "length", label: "Mínimo 8 caracteres", valid: this.passwordForm.password.length >= 8 },
        {
          key: "different",
          label: "Distinta de la clave actual",
          valid: Boolean(this.passwordForm.password) && this.passwordForm.password !== this.passwordForm.current_password,
        },
        {
          key: "match",
          label: "Ambas claves coinciden",
          valid: Boolean(this.passwordForm.password_confirmation)
            && this.passwordForm.password === this.passwordForm.password_confirmation,
        },
      ];
    },
    passwordStrength() {
      const password = this.passwordForm.password;
      if (!password) return { score: 0, label: "Sin evaluar", tone: "empty" };
      let score = password.length >= 8 ? 1 : 0;
      score += password.length >= 12 ? 1 : 0;
      score += /[a-z]/.test(password) && /[A-Z]/.test(password) ? 1 : 0;
      score += /\d/.test(password) ? 1 : 0;
      score += /[^A-Za-z0-9]/.test(password) ? 1 : 0;
      if (score <= 1) return { score: Math.max(score, 1), label: "Básica", tone: "weak" };
      if (score <= 3) return { score, label: "Buena", tone: "medium" };
      return { score: Math.min(score, 5), label: "Sólida", tone: "strong" };
    },
    canSavePassword() {
      return Boolean(this.passwordForm.current_password)
        && this.passwordForm.password.length >= 8
        && this.passwordForm.password !== this.passwordForm.current_password
        && this.passwordForm.password === this.passwordForm.password_confirmation
        && !this.savingPassword;
    },
  },
  async mounted() {
    await this.loadProfile();
  },
  beforeUnmount() {
    this.releasePhotoPreview();
  },
  methods: {
    async loadProfile() {
      this.loading = true;
      this.profileError = null;
      this.profileErrors = {};
      try {
        const response = await axios.get("/api/me/profile");
        this.setProfile(response.data.data);
      } catch (error) {
        this.profile = null;
        this.profileError = error?.response?.data?.message || "No fue posible cargar tu perfil.";
      } finally {
        this.loading = false;
      }
    },
    setProfile(profile) {
      this.profile = profile;
      this.profileForm.name = profile?.name || "";
      this.profileForm.remove_photo = false;
      this.selectedPhoto = null;
      this.releasePhotoPreview();
      const stored = {
        ...(JSON.parse(localStorage.getItem("user") || "{}")),
        login: true,
        user_id: profile.id,
        name: profile.name,
        email: profile.email,
        user_type: profile.user_type || null,
        staff_id: profile.staff?.id || null,
        is_staff: profile.is_staff === true || profile.user_type === "staff",
        profile_photo_url: profile.profile_photo_url || null,
      };
      localStorage.setItem("user", JSON.stringify(stored));
      this.auth.currentUser = stored;
    },
    releasePhotoPreview() {
      if (this.photoPreview) {
        URL.revokeObjectURL(this.photoPreview);
        this.photoPreview = null;
      }
    },
    openPhotoPicker() {
      this.$refs.photoInput?.click();
    },
    onPhotoChange(event) {
      this.selectPhoto(event.target.files?.[0] || null);
    },
    onPhotoDrop(event) {
      this.dragActive = false;
      this.selectPhoto(event.dataTransfer?.files?.[0] || null);
    },
    selectPhoto(file) {
      this.profileMessage = null;
      this.profileError = null;
      this.profileErrors = {};
      if (!file) return;
      if (!ACCEPTED_PHOTO_TYPES.includes(file.type)) {
        this.resetPhotoInput();
        this.profileErrors = { photo: ["Usa una imagen JPG, PNG o WEBP."] };
        this.profileError = this.profileErrors.photo[0];
        return;
      }
      if (file.size > MAX_PHOTO_SIZE) {
        this.resetPhotoInput();
        this.profileErrors = { photo: ["La imagen no puede superar los 5 MB."] };
        this.profileError = this.profileErrors.photo[0];
        return;
      }
      this.releasePhotoPreview();
      this.selectedPhoto = file;
      this.profileForm.remove_photo = false;
      this.photoPreview = URL.createObjectURL(file);
    },
    resetPhotoInput() {
      this.selectedPhoto = null;
      if (this.$refs.photoInput) this.$refs.photoInput.value = "";
      this.releasePhotoPreview();
    },
    markRemovePhoto() {
      this.resetPhotoInput();
      this.profileForm.remove_photo = true;
      this.profileMessage = null;
      this.profileError = null;
      this.profileErrors = {};
    },
    restorePhotoSelection() {
      this.resetPhotoInput();
      this.profileForm.remove_photo = false;
      this.profileError = null;
      this.profileErrors = {};
    },
    firstError(errors, field) {
      const messages = errors?.[field];
      return Array.isArray(messages) ? messages[0] : messages || null;
    },
    clearProfileField(field) {
      if (this.profileErrors[field]) {
        const errors = { ...this.profileErrors };
        delete errors[field];
        this.profileErrors = errors;
      }
      this.profileError = null;
      this.profileMessage = null;
    },
    clearPasswordField(field) {
      if (this.passwordErrors[field]) {
        const errors = { ...this.passwordErrors };
        delete errors[field];
        this.passwordErrors = errors;
      }
      this.passwordError = null;
      this.passwordMessage = null;
    },
    async saveProfile() {
      if (!this.canSaveProfile) return;
      const result = await Swal.fire({
        title: "Guardar cambios",
        text: "Se actualizarán tu nombre visible y la foto seleccionada.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, guardar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#3858d6",
        cancelButtonColor: "#74788d",
      });
      if (!result.isConfirmed) return;
      this.savingProfile = true;
      this.profileMessage = null;
      this.profileError = null;
      this.profileErrors = {};
      const formData = new FormData();
      formData.append("name", this.profileForm.name.trim());
      formData.append("remove_photo", this.profileForm.remove_photo ? "1" : "0");
      if (this.selectedPhoto) formData.append("photo", this.selectedPhoto);
      try {
        const response = await axios.post("/api/me/profile", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        this.setProfile(response.data.data);
        this.profileMessage = response.data.message || "Perfil actualizado correctamente.";
        await Swal.fire({
          title: "Cambios guardados",
          text: this.profileMessage,
          icon: "success",
          confirmButtonText: "Aceptar",
          confirmButtonColor: "#3858d6",
        });
      } catch (error) {
        this.profileErrors = error?.response?.data?.errors || {};
        this.profileError = error?.response?.data?.message || "No fue posible actualizar tu perfil.";
      } finally {
        this.savingProfile = false;
      }
    },
    togglePasswordVisibility(field) {
      this.passwordVisibility[field] = !this.passwordVisibility[field];
    },
    passwordInputType(field) {
      return this.passwordVisibility[field] ? "text" : "password";
    },
    resetPasswordForm() {
      this.passwordForm = { current_password: "", password: "", password_confirmation: "" };
      this.passwordVisibility = { current_password: false, password: false, password_confirmation: false };
      this.passwordErrors = {};
    },
    async savePassword() {
      if (!this.canSavePassword) return;
      const result = await Swal.fire({
        title: "Actualizar contraseña",
        text: "Confirma que deseas reemplazar tu contraseña actual.",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Actualizar contraseña",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#3858d6",
        cancelButtonColor: "#74788d",
      });
      if (!result.isConfirmed) return;
      this.savingPassword = true;
      this.passwordMessage = null;
      this.passwordError = null;
      this.passwordErrors = {};
      try {
        const response = await axios.put("/api/me/password", this.passwordForm);
        this.resetPasswordForm();
        this.passwordMessage = response.data.message || "Contraseña actualizada correctamente.";
      } catch (error) {
        this.passwordErrors = error?.response?.data?.errors || {};
        this.passwordError = error?.response?.data?.message || "No fue posible cambiar la contraseña.";
      } finally {
        this.savingPassword = false;
      }
    },
    scrollToSecurity() {
      this.$refs.securitySection?.scrollIntoView({ behavior: "smooth", block: "start" });
    },
  },
};
</script>

<template>
  <Layout>
    <main class="account-profile-page">
      <section class="profile-hero" aria-labelledby="profile-page-title">
        <div class="profile-hero__content">
          <span class="profile-eyebrow"><i class="bx bx-user-circle" aria-hidden="true"></i> Cuenta personal</span>
          <h1 id="profile-page-title">Mi perfil</h1>
          <p>Gestiona cómo te identificas en la plataforma y mantén segura tu cuenta.</p>
          <div v-if="profile" class="profile-hero__meta">
            <span><i class="bx bx-badge-check" aria-hidden="true"></i>{{ primaryRole }}</span>
            <span><i class="bx bx-envelope" aria-hidden="true"></i>{{ profile.email }}</span>
          </div>
        </div>
        <div class="profile-hero__mark" aria-hidden="true"><i class="bx bx-shield-quarter"></i></div>
      </section>

      <LoadingState v-if="loading" message="Cargando tu perfil..." />

      <section v-else-if="!profile" class="profile-load-error" role="alert">
        <span class="profile-load-error__icon"><i class="bx bx-cloud-lightning" aria-hidden="true"></i></span>
        <div><h2>No pudimos cargar tu perfil</h2><p>{{ profileError }}</p></div>
        <BButton variant="primary" type="button" @click="loadProfile"><i class="bx bx-refresh"></i> Reintentar</BButton>
      </section>

      <div v-else class="profile-layout">
        <aside class="profile-identity-card" aria-label="Resumen de la cuenta">
          <div class="profile-identity-card__cover"></div>
          <div class="profile-identity-card__body">
            <div class="profile-avatar-wrap">
              <div class="profile-avatar">
                <img v-if="avatarUrl" :src="avatarUrl" :alt="`Foto de perfil de ${profile.name}`" />
                <span v-else aria-hidden="true">{{ userInitials }}</span>
              </div>
              <span class="profile-avatar__status" title="Cuenta activa"><i class="bx bx-check"></i></span>
            </div>
            <div class="profile-identity-card__heading">
              <h2>{{ profile.name }}</h2>
              <p>{{ profile.email }}</p>
              <span class="profile-source-pill"><i class="bx bx-image"></i>{{ photoSourceLabel }}</span>
            </div>
            <dl class="profile-facts">
              <div><dt><i class="bx bx-id-card"></i> Tipo de cuenta</dt><dd>{{ displayUserType }}</dd></div>
              <div><dt><i class="bx bx-briefcase-alt-2"></i> Cargo</dt><dd>{{ accountPosition }}</dd></div>
              <div v-if="profile.staff"><dt><i class="bx bx-link-alt"></i> Ficha vinculada</dt><dd>{{ profile.staff.full_name || "Funcionario institucional" }}</dd></div>
            </dl>
            <div v-if="roleNames.length" class="profile-roles">
              <span class="profile-roles__label">Roles asignados</span>
              <div><span v-for="role in roleNames" :key="role" class="profile-role-badge">{{ role }}</span></div>
            </div>
            <div class="profile-managed-note">
              <i class="bx bx-lock-alt"></i>
              <p><strong>Datos institucionales protegidos</strong>El correo, cargo y roles se administran centralmente.</p>
            </div>
            <button type="button" class="profile-security-link" @click="scrollToSecurity">
              <i class="bx bx-shield-quarter"></i><span><strong>Seguridad de la cuenta</strong><small>Revisar contraseña</small></span><i class="bx bx-right-arrow-alt"></i>
            </button>
          </div>
        </aside>

        <div class="profile-content">
          <section class="profile-panel" aria-labelledby="identity-title">
            <header class="profile-panel__header">
              <span class="profile-panel__icon profile-panel__icon--blue"><i class="bx bx-user"></i></span>
              <div><span class="profile-panel__kicker">Identidad visible</span><h2 id="identity-title">Información de perfil</h2><p>Estos datos te identifican en menús, mensajes y registros internos.</p></div>
            </header>
            <div class="profile-panel__body">
              <BAlert v-if="profileMessage" show variant="success" class="profile-alert" aria-live="polite"><i class="bx bx-check-circle"></i>{{ profileMessage }}</BAlert>
              <BAlert v-if="profileError" show variant="danger" class="profile-alert" aria-live="assertive"><i class="bx bx-error-circle"></i>{{ profileError }}</BAlert>
              <BForm class="profile-form" @submit.prevent="saveProfile">
                <div class="profile-form-grid">
                  <BFormGroup label="Nombre visible" label-for="profile-name" class="profile-field">
                    <div class="profile-input-wrap">
                      <i class="bx bx-user"></i>
                      <BFormInput id="profile-name" v-model="profileForm.name" required maxlength="255" autocomplete="name" :state="firstError(profileErrors, 'name') ? false : null" @input="clearProfileField('name')" />
                    </div>
                    <BFormInvalidFeedback :state="!firstError(profileErrors, 'name')">{{ firstError(profileErrors, "name") }}</BFormInvalidFeedback>
                    <small>Así aparecerá tu nombre en la plataforma.</small>
                  </BFormGroup>
                  <BFormGroup label="Correo electrónico" label-for="profile-email" class="profile-field">
                    <div class="profile-input-wrap profile-input-wrap--locked">
                      <i class="bx bx-envelope"></i><BFormInput id="profile-email" :model-value="profile.email" disabled /><i class="bx bx-lock-alt profile-input-lock"></i>
                    </div>
                    <small>Dato institucional no editable desde esta vista.</small>
                  </BFormGroup>
                </div>
                <div class="profile-divider"></div>
                <div class="photo-editor">
                  <div class="photo-editor__copy"><span>Foto de perfil</span><h3>Una imagen clara y reconocible</h3><p>Formatos JPG, PNG o WEBP. Tamaño máximo de 5 MB.</p></div>
                  <div
                    class="photo-dropzone"
                    :class="{ 'is-dragging': dragActive, 'has-error': firstError(profileErrors, 'photo') }"
                    role="button"
                    tabindex="0"
                    aria-label="Seleccionar una foto de perfil"
                    @click="openPhotoPicker"
                    @keydown.enter.prevent="openPhotoPicker"
                    @keydown.space.prevent="openPhotoPicker"
                    @dragenter.prevent="dragActive = true"
                    @dragover.prevent="dragActive = true"
                    @dragleave.prevent="dragActive = false"
                    @drop.prevent="onPhotoDrop"
                  >
                    <input id="profile-photo" ref="photoInput" type="file" class="visually-hidden" accept="image/jpeg,image/png,image/webp" @click.stop @change="onPhotoChange" />
                    <div class="photo-dropzone__preview"><img v-if="avatarUrl" :src="avatarUrl" alt="Vista previa de la foto" /><span v-else>{{ userInitials }}</span><i class="bx bx-camera"></i></div>
                    <div class="photo-dropzone__text"><strong>{{ selectedPhoto ? selectedPhoto.name : "Selecciona o arrastra una imagen" }}</strong><span>{{ selectedPhoto ? "La nueva foto se aplicará al guardar." : "Haz clic para buscar en tu equipo." }}</span></div>
                    <span class="photo-dropzone__action">Elegir foto</span>
                  </div>
                  <p v-if="firstError(profileErrors, 'photo')" class="photo-error" role="alert"><i class="bx bx-error-circle"></i>{{ firstError(profileErrors, "photo") }}</p>
                  <div v-if="profileForm.remove_photo" class="photo-pending-note" role="status"><i class="bx bx-undo"></i><span>{{ photoSourceLabel }}</span><button type="button" @click="restorePhotoSelection">Deshacer</button></div>
                </div>
                <footer class="profile-form__footer">
                  <span v-if="profileDirty"><i class="bx bx-info-circle"></i>Tienes cambios sin guardar.</span>
                  <span v-else><i class="bx bx-check-circle"></i>Tu perfil está actualizado.</span>
                  <div>
                    <BButton v-if="profile.profile_photo_source === 'user' || selectedPhoto" type="button" variant="outline-secondary" :disabled="savingProfile" @click="markRemovePhoto">
                      <i class="bx bx-undo"></i>{{ profile.staff?.profile_photo_url ? "Restaurar institucional" : "Quitar foto" }}
                    </BButton>
                    <BButton type="submit" variant="primary" :disabled="!canSaveProfile">
                      <span v-if="savingProfile" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-save"></i>{{ savingProfile ? "Guardando..." : "Guardar cambios" }}
                    </BButton>
                  </div>
                </footer>
              </BForm>
            </div>
          </section>

          <section ref="securitySection" class="profile-panel profile-panel--security" aria-labelledby="security-title">
            <header class="profile-panel__header">
              <span class="profile-panel__icon profile-panel__icon--violet"><i class="bx bx-shield-quarter"></i></span>
              <div><span class="profile-panel__kicker">Acceso y seguridad</span><h2 id="security-title">Cambiar contraseña</h2><p>Confirma tu contraseña actual antes de definir una nueva.</p></div>
              <span class="security-badge"><i class="bx bx-lock-alt"></i>Protegido</span>
            </header>
            <div class="profile-panel__body">
              <BAlert v-if="passwordMessage" show variant="success" class="profile-alert" aria-live="polite"><i class="bx bx-check-circle"></i>{{ passwordMessage }}</BAlert>
              <BAlert v-if="passwordError" show variant="danger" class="profile-alert" aria-live="assertive"><i class="bx bx-error-circle"></i>{{ passwordError }}</BAlert>
              <BForm class="password-form" @submit.prevent="savePassword">
                <div class="password-form__current">
                  <BFormGroup label="Contraseña actual" label-for="current-password" class="profile-field mb-0">
                    <div class="profile-input-wrap"><i class="bx bx-key"></i>
                      <BFormInput id="current-password" v-model="passwordForm.current_password" :type="passwordInputType('current_password')" required autocomplete="current-password" :state="firstError(passwordErrors, 'current_password') ? false : null" @input="clearPasswordField('current_password')" />
                      <button type="button" class="password-toggle" :aria-label="passwordVisibility.current_password ? 'Ocultar contraseña actual' : 'Mostrar contraseña actual'" @click="togglePasswordVisibility('current_password')"><i class="bx" :class="passwordVisibility.current_password ? 'bx-hide' : 'bx-show'"></i></button>
                    </div>
                    <BFormInvalidFeedback :state="!firstError(passwordErrors, 'current_password')">{{ firstError(passwordErrors, "current_password") }}</BFormInvalidFeedback>
                  </BFormGroup>
                </div>
                <div class="password-form__new">
                  <BFormGroup label="Nueva contraseña" label-for="new-password" class="profile-field mb-0">
                    <div class="profile-input-wrap"><i class="bx bx-lock-open-alt"></i>
                      <BFormInput id="new-password" v-model="passwordForm.password" :type="passwordInputType('password')" required minlength="8" autocomplete="new-password" :state="firstError(passwordErrors, 'password') ? false : null" @input="clearPasswordField('password')" />
                      <button type="button" class="password-toggle" :aria-label="passwordVisibility.password ? 'Ocultar nueva contraseña' : 'Mostrar nueva contraseña'" @click="togglePasswordVisibility('password')"><i class="bx" :class="passwordVisibility.password ? 'bx-hide' : 'bx-show'"></i></button>
                    </div>
                    <BFormInvalidFeedback :state="!firstError(passwordErrors, 'password')">{{ firstError(passwordErrors, "password") }}</BFormInvalidFeedback>
                  </BFormGroup>
                  <BFormGroup label="Confirmar nueva contraseña" label-for="password-confirmation" class="profile-field mb-0">
                    <div class="profile-input-wrap"><i class="bx bx-check-shield"></i>
                      <BFormInput id="password-confirmation" v-model="passwordForm.password_confirmation" :type="passwordInputType('password_confirmation')" required minlength="8" autocomplete="new-password" @input="clearPasswordField('password_confirmation')" />
                      <button type="button" class="password-toggle" :aria-label="passwordVisibility.password_confirmation ? 'Ocultar confirmación' : 'Mostrar confirmación'" @click="togglePasswordVisibility('password_confirmation')"><i class="bx" :class="passwordVisibility.password_confirmation ? 'bx-hide' : 'bx-show'"></i></button>
                    </div>
                  </BFormGroup>
                </div>
                <div class="password-health" :class="`password-health--${passwordStrength.tone}`">
                  <div class="password-health__heading"><span>Fortaleza de la contraseña</span><strong>{{ passwordStrength.label }}</strong></div>
                  <div class="password-health__meter" aria-hidden="true"><span v-for="index in 5" :key="index" :class="{ active: index <= passwordStrength.score }"></span></div>
                  <div class="password-requirements">
                    <span v-for="requirement in passwordRequirements" :key="requirement.key" :class="{ valid: requirement.valid }"><i class="bx" :class="requirement.valid ? 'bx-check-circle' : 'bx-circle'"></i>{{ requirement.label }}</span>
                  </div>
                </div>
                <footer class="profile-form__footer profile-form__footer--security">
                  <span><i class="bx bx-shield"></i>Nunca compartas tu contraseña con otras personas.</span>
                  <BButton type="submit" variant="primary" :disabled="!canSavePassword"><span v-if="savingPassword" class="spinner-border spinner-border-sm"></span><i v-else class="bx bx-lock-alt"></i>{{ savingPassword ? "Actualizando..." : "Actualizar contraseña" }}</BButton>
                </footer>
              </BForm>
            </div>
          </section>
        </div>
      </div>
    </main>
  </Layout>
</template>

<style scoped>
/* Profile view visual system. */
.account-profile-page{--p:#3858d6;--pd:#2947bd;--ink:#17233d;--muted:#6b7891;--border:#e4e9f3;--surface:#fff;max-width:1320px;margin:0 auto;padding-bottom:2rem;color:var(--ink)}
.profile-hero{position:relative;display:flex;align-items:center;justify-content:space-between;min-height:12.5rem;margin-bottom:1.5rem;padding:2.2rem 2.5rem;overflow:hidden;border:1px solid rgba(255,255,255,.72);border-radius:1.5rem;background:radial-gradient(circle at 80% 10%,rgba(130,222,255,.38),transparent 31%),radial-gradient(circle at 48% 120%,rgba(139,109,246,.2),transparent 42%),linear-gradient(125deg,#eef7ff 0%,#f4f2ff 54%,#edf6ff 100%);box-shadow:0 18px 45px rgba(45,67,126,.09)}
.profile-hero:before,.profile-hero:after{position:absolute;content:"";border:1px solid rgba(63,91,214,.1);border-radius:50%}.profile-hero:before{width:15rem;height:15rem;right:5rem;top:-8rem}.profile-hero:after{width:9rem;height:9rem;right:8rem;bottom:-5rem}.profile-hero__content{position:relative;z-index:1;max-width:48rem}
.profile-eyebrow,.profile-panel__kicker{display:inline-flex;align-items:center;gap:.4rem;color:var(--p);font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.profile-eyebrow{padding:.45rem .7rem;border:1px solid rgba(56,88,214,.12);border-radius:999px;background:rgba(255,255,255,.62)}
.profile-hero h1{margin:.8rem 0 .4rem;color:#162445;font-size:clamp(2rem,4vw,3.15rem);font-weight:800;letter-spacing:-.045em}.profile-hero p{margin:0;color:#65738d;font-size:1.03rem}.profile-hero__meta{display:flex;flex-wrap:wrap;gap:.65rem;margin-top:1.25rem}.profile-hero__meta span{display:inline-flex;align-items:center;gap:.45rem;padding:.5rem .7rem;border:1px solid rgba(82,104,155,.12);border-radius:.7rem;background:rgba(255,255,255,.58);color:#52607a;font-size:.8rem;font-weight:600}.profile-hero__meta i{color:var(--p);font-size:1rem}
.profile-hero__mark{position:relative;z-index:1;display:grid;place-items:center;flex:0 0 7rem;width:7rem;height:7rem;margin-right:2.5rem;border:1px solid rgba(255,255,255,.78);border-radius:2rem;background:rgba(255,255,255,.5);color:var(--p);box-shadow:0 18px 36px rgba(53,78,163,.12);transform:rotate(4deg);backdrop-filter:blur(14px)}.profile-hero__mark i{font-size:3.6rem;transform:rotate(-4deg)}
.profile-layout{display:grid;grid-template-columns:minmax(17rem,.34fr) minmax(0,.66fr);gap:1.5rem;align-items:start}.profile-identity-card,.profile-panel,.profile-load-error{border:1px solid var(--border);border-radius:1.35rem;background:var(--surface);box-shadow:0 14px 38px rgba(32,47,84,.07)}.profile-identity-card{position:sticky;top:1.25rem;overflow:hidden}.profile-identity-card__cover{height:6.2rem;background:radial-gradient(circle at 25% 10%,rgba(255,255,255,.38),transparent 28%),linear-gradient(125deg,#3d5bd8 0%,#6d63df 54%,#4998e6 100%)}.profile-identity-card__body{padding:0 1.35rem 1.35rem}
.profile-avatar-wrap{position:relative;width:max-content;margin:-3.7rem auto 0}.profile-avatar{display:grid;place-items:center;width:7.4rem;height:7.4rem;overflow:hidden;border:.36rem solid #fff;border-radius:2.15rem;background:linear-gradient(145deg,#e9edff,#d9e5ff);color:var(--p);box-shadow:0 14px 30px rgba(40,59,120,.22)}.profile-avatar img,.photo-dropzone__preview img{width:100%;height:100%;object-fit:cover}.profile-avatar>span{font-size:2.2rem;font-weight:800;letter-spacing:-.04em}.profile-avatar__status{position:absolute;right:-.25rem;bottom:.45rem;display:grid;place-items:center;width:1.8rem;height:1.8rem;border:.22rem solid #fff;border-radius:50%;background:#20b486;color:#fff;font-size:.95rem}
.profile-identity-card__heading{padding:1rem 0 1.25rem;text-align:center}.profile-identity-card__heading h2{margin:0;color:var(--ink);font-size:1.25rem;font-weight:800}.profile-identity-card__heading p{margin:.25rem 0 .75rem;overflow:hidden;color:var(--muted);font-size:.82rem;text-overflow:ellipsis}.profile-source-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .55rem;border-radius:999px;background:#f0f3ff;color:var(--p);font-size:.7rem;font-weight:700}
.profile-facts{display:grid;gap:.6rem;margin:0}.profile-facts>div{padding:.8rem .9rem;border:1px solid #edf0f6;border-radius:.85rem;background:#fafbfe}.profile-facts dt{display:flex;align-items:center;gap:.4rem;margin-bottom:.18rem;color:var(--muted);font-size:.68rem;font-weight:600}.profile-facts dt i{color:#7890dc;font-size:.9rem}.profile-facts dd{margin:0;color:#263450;font-size:.85rem;font-weight:700}.profile-roles{margin-top:1.1rem}.profile-roles__label{display:block;margin-bottom:.5rem;color:var(--muted);font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.profile-roles>div{display:flex;flex-wrap:wrap;gap:.4rem}.profile-role-badge{padding:.38rem .55rem;border:1px solid #dfe5f6;border-radius:.55rem;background:#f6f8fd;color:#506080;font-size:.68rem;font-weight:700}
.profile-managed-note{display:flex;align-items:flex-start;gap:.6rem;margin-top:1.15rem;padding:.8rem;border-radius:.8rem;background:#f5f7fb;color:#697690}.profile-managed-note>i{margin-top:.05rem;color:#7a87a2;font-size:1rem}.profile-managed-note p{display:grid;gap:.12rem;margin:0;font-size:.68rem;line-height:1.45}.profile-managed-note strong{color:#4c5b76;font-size:.72rem}.profile-security-link{display:flex;align-items:center;gap:.7rem;width:100%;margin-top:.8rem;padding:.75rem;border:0;border-radius:.8rem;background:transparent;color:var(--p);text-align:left;transition:.2s}.profile-security-link:hover,.profile-security-link:focus-visible{background:#f1f4ff;transform:translateY(-1px)}.profile-security-link>i:first-child{font-size:1.3rem}.profile-security-link>i:last-child{margin-left:auto;font-size:1.2rem}.profile-security-link span{display:grid}.profile-security-link strong{color:#374b80;font-size:.75rem}.profile-security-link small{color:#7e8aa2;font-size:.66rem}
.profile-content{display:grid;gap:1.5rem;min-width:0}.profile-panel{overflow:hidden;scroll-margin-top:1rem}.profile-panel__header{display:flex;align-items:flex-start;gap:.9rem;padding:1.35rem 1.5rem 1.2rem;border-bottom:1px solid #edf0f6;background:linear-gradient(180deg,#fff 0%,#fdfdff 100%)}.profile-panel__header>div{min-width:0}.profile-panel__header h2{margin:.12rem 0 .2rem;color:var(--ink);font-size:1.12rem;font-weight:800}.profile-panel__header p{margin:0;color:var(--muted);font-size:.78rem}.profile-panel__icon{display:grid;place-items:center;flex:0 0 2.65rem;width:2.65rem;height:2.65rem;border-radius:.82rem;font-size:1.25rem}.profile-panel__icon--blue{background:#ebf1ff;color:#4164dc}.profile-panel__icon--violet{background:#f2edff;color:#7053d6}.security-badge{display:inline-flex;align-items:center;gap:.35rem;margin-left:auto;padding:.42rem .62rem;border-radius:999px;background:#eef9f5;color:#21896d;font-size:.68rem;font-weight:700}.profile-panel__body{padding:1.5rem}.profile-alert{display:flex;align-items:center;gap:.5rem;border:0;border-radius:.8rem;font-size:.8rem}.profile-alert i{font-size:1.1rem}
.profile-form-grid,.password-form__new{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.profile-field :deep(.form-label){margin-bottom:.42rem;color:#3e4a61;font-size:.74rem;font-weight:700}.profile-field>small,.profile-field :deep(small){display:block;margin-top:.38rem;color:#8a95aa;font-size:.67rem}.profile-input-wrap{position:relative}.profile-input-wrap>i:first-child{position:absolute;z-index:2;left:.85rem;top:50%;color:#8b98b1;font-size:1rem;transform:translateY(-50%);pointer-events:none}.profile-input-wrap :deep(.form-control){min-height:2.8rem;padding-left:2.45rem;border-color:#dfe4ee;border-radius:.75rem;color:#27344d;font-size:.8rem;box-shadow:none;transition:.2s}.profile-input-wrap :deep(.form-control:focus){border-color:#758eea;box-shadow:0 0 0 .2rem rgba(56,88,214,.1)}.profile-input-wrap--locked :deep(.form-control:disabled){padding-right:2.5rem;background:#f5f7fa;color:#727f95;opacity:1}.profile-input-lock{position:absolute;right:.85rem;top:50%;color:#9ba6b8;transform:translateY(-50%)}.profile-divider{height:1px;margin:1.45rem 0;background:#edf0f6}
.photo-editor__copy>span{color:#35435c;font-size:.74rem;font-weight:700}.photo-editor__copy h3{margin:.18rem 0;color:var(--ink);font-size:.95rem;font-weight:800}.photo-editor__copy p{margin:0 0 .9rem;color:var(--muted);font-size:.72rem}.photo-dropzone{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.85rem;padding:.85rem;border:1.5px dashed #cdd5e6;border-radius:1rem;background:#fafbfe;cursor:pointer;outline:none;transition:.2s}.photo-dropzone:hover,.photo-dropzone:focus-visible,.photo-dropzone.is-dragging{border-color:#6c86e3;background:#f3f6ff;transform:translateY(-1px)}.photo-dropzone.has-error{border-color:#e3616c;background:#fff8f8}.photo-dropzone__preview{position:relative;display:grid;place-items:center;width:4rem;height:4rem;overflow:hidden;border-radius:.95rem;background:linear-gradient(145deg,#e9edff,#d9e5ff);color:var(--p);font-size:1.15rem;font-weight:800}.photo-dropzone__preview>i{position:absolute;right:.18rem;bottom:.18rem;display:grid;place-items:center;width:1.35rem;height:1.35rem;border:2px solid #fff;border-radius:50%;background:var(--p);color:#fff;font-size:.7rem}.photo-dropzone__text{display:grid;gap:.18rem;min-width:0}.photo-dropzone__text strong{overflow:hidden;color:#34425c;font-size:.76rem;text-overflow:ellipsis;white-space:nowrap}.photo-dropzone__text span{color:#8590a5;font-size:.68rem}.photo-dropzone__action{padding:.52rem .7rem;border:1px solid #d8dfee;border-radius:.65rem;background:#fff;color:var(--p);font-size:.7rem;font-weight:700;box-shadow:0 4px 10px rgba(30,45,80,.04)}
.photo-error,.photo-pending-note{display:flex;align-items:center;gap:.4rem;margin:.55rem 0 0;font-size:.7rem}.photo-error{color:#d04451}.photo-pending-note{padding:.62rem .75rem;border-radius:.7rem;background:#fff8e8;color:#8f6a13}.photo-pending-note button{margin-left:auto;padding:0;border:0;background:transparent;color:#72530b;font-size:.7rem;font-weight:800}
.profile-form__footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:1.45rem -1.5rem -1.5rem;padding:1rem 1.5rem;border-top:1px solid #edf0f6;background:#fbfcfe}.profile-form__footer>span{display:inline-flex;align-items:center;gap:.4rem;color:#748096;font-size:.7rem}.profile-form__footer>span i{color:var(--p);font-size:1rem}.profile-form__footer>div{display:flex;gap:.55rem}.profile-form__footer :deep(.btn){display:inline-flex;align-items:center;justify-content:center;gap:.42rem;min-height:2.55rem;padding:.55rem .9rem;border-radius:.7rem;font-size:.72rem;font-weight:700}.profile-form__footer :deep(.btn-primary){border-color:var(--p);background:var(--p);box-shadow:0 8px 18px rgba(56,88,214,.2)}.profile-form__footer :deep(.btn-primary:hover:not(:disabled)){border-color:var(--pd);background:var(--pd)}.profile-form__footer :deep(.btn:disabled){box-shadow:none;opacity:.58}
.password-form{display:grid;gap:1rem}.password-form__current{max-width:calc(50% - .5rem)}.password-toggle{position:absolute;z-index:2;right:.45rem;top:50%;display:grid;place-items:center;width:2rem;height:2rem;padding:0;border:0;border-radius:.55rem;background:transparent;color:#76849d;transform:translateY(-50%)}.password-toggle:hover,.password-toggle:focus-visible{background:#eef2fb;color:var(--p)}.password-form .profile-input-wrap :deep(.form-control){padding-right:2.75rem}.password-health{padding:.85rem 1rem;border:1px solid #e7eaf2;border-radius:.9rem;background:#fafbfe}.password-health__heading{display:flex;justify-content:space-between;color:#5c687d;font-size:.7rem}.password-health__heading strong{color:#7d8798}.password-health--weak .password-health__heading strong{color:#d65059}.password-health--medium .password-health__heading strong{color:#bd8018}.password-health--strong .password-health__heading strong{color:#15956e}.password-health__meter{display:grid;grid-template-columns:repeat(5,1fr);gap:.35rem;margin:.55rem 0 .7rem}.password-health__meter span{height:.25rem;border-radius:999px;background:#e2e6ee}.password-health--weak .password-health__meter span.active{background:#e66b73}.password-health--medium .password-health__meter span.active{background:#e5a83f}.password-health--strong .password-health__meter span.active{background:#28b68c}.password-requirements{display:flex;flex-wrap:wrap;gap:.55rem 1rem}.password-requirements span{display:inline-flex;align-items:center;gap:.3rem;color:#8b95a8;font-size:.66rem}.password-requirements span.valid{color:#248d70}.password-requirements i{font-size:.85rem}.profile-form__footer--security{margin-top:.3rem}
.profile-load-error{display:flex;align-items:center;gap:1rem;padding:1.4rem}.profile-load-error__icon{display:grid;place-items:center;flex:0 0 3.2rem;width:3.2rem;height:3.2rem;border-radius:1rem;background:#fff0f1;color:#d5525e;font-size:1.5rem}.profile-load-error h2{margin:0 0 .2rem;font-size:1rem;font-weight:800}.profile-load-error p{margin:0;color:var(--muted);font-size:.78rem}.profile-load-error :deep(.btn){display:inline-flex;align-items:center;gap:.4rem;margin-left:auto;border-radius:.7rem}
@media(max-width:1199.98px){.profile-layout{grid-template-columns:18rem minmax(0,1fr)}.profile-hero__mark{margin-right:0}}
@media(max-width:991.98px){.profile-layout{grid-template-columns:1fr}.profile-identity-card{position:static}.profile-identity-card__body{display:grid;grid-template-columns:auto minmax(0,1fr);gap:0 1.2rem;align-items:start;padding:1.2rem}.profile-identity-card__cover{height:3.2rem}.profile-avatar-wrap{grid-row:1/3;margin:-2.8rem 0 0}.profile-avatar{width:6.4rem;height:6.4rem;border-radius:1.8rem}.profile-identity-card__heading{padding:0 0 1rem;text-align:left}.profile-facts,.profile-roles,.profile-managed-note,.profile-security-link{grid-column:1/-1}.profile-facts{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:767.98px){.profile-hero{min-height:auto;padding:1.5rem}.profile-hero__mark{display:none}.profile-hero__meta span:last-child{max-width:100%;overflow:hidden;text-overflow:ellipsis}.profile-form-grid,.password-form__new{grid-template-columns:1fr}.password-form__current{max-width:none}.profile-facts{grid-template-columns:1fr}.profile-form__footer{align-items:stretch;flex-direction:column}.profile-form__footer>div,.profile-form__footer :deep(.btn){flex:1}.profile-form__footer--security :deep(.btn){width:100%}}
@media(max-width:575.98px){.account-profile-page{padding-bottom:1rem}.profile-hero{margin:-.25rem -.25rem 1rem;padding:1.25rem;border-radius:1.15rem}.profile-hero h1{font-size:2rem}.profile-hero p{font-size:.86rem}.profile-hero__meta{display:grid}.profile-layout,.profile-content{gap:1rem}.profile-identity-card,.profile-panel,.profile-load-error{border-radius:1.05rem}.profile-identity-card__body{grid-template-columns:1fr;justify-items:center}.profile-avatar-wrap{grid-row:auto}.profile-identity-card__heading{padding-top:.8rem;text-align:center}.profile-facts,.profile-roles,.profile-managed-note,.profile-security-link{width:100%}.profile-panel__header,.profile-panel__body{padding:1.1rem}.security-badge{display:none}.photo-dropzone{grid-template-columns:auto minmax(0,1fr)}.photo-dropzone__action{display:none}.profile-form__footer{margin:1.2rem -1.1rem -1.1rem;padding:1rem 1.1rem}.profile-form__footer>div{flex-direction:column-reverse}.profile-load-error{align-items:flex-start;flex-wrap:wrap}.profile-load-error :deep(.btn){width:100%;margin-left:0}}
:global([data-bs-theme="dark"]) .account-profile-page{--ink:#eef2ff;--muted:#a7b1c7;--border:#303a50;--surface:#222a3a}:global([data-bs-theme="dark"]) .profile-hero{border-color:#35415a;background:radial-gradient(circle at 80% 10%,rgba(58,149,202,.22),transparent 31%),radial-gradient(circle at 48% 120%,rgba(124,92,218,.18),transparent 42%),linear-gradient(125deg,#253248 0%,#292b4a 54%,#22364c 100%)}:global([data-bs-theme="dark"]) .profile-hero h1,:global([data-bs-theme="dark"]) .profile-panel__header h2,:global([data-bs-theme="dark"]) .photo-editor__copy h3,:global([data-bs-theme="dark"]) .profile-identity-card__heading h2{color:#f1f4ff}:global([data-bs-theme="dark"]) .profile-eyebrow,:global([data-bs-theme="dark"]) .profile-hero__meta span,:global([data-bs-theme="dark"]) .profile-hero__mark,:global([data-bs-theme="dark"]) .profile-panel__header{border-color:#3a465e;background:rgba(38,47,65,.84)}:global([data-bs-theme="dark"]) .profile-facts>div,:global([data-bs-theme="dark"]) .profile-managed-note,:global([data-bs-theme="dark"]) .photo-dropzone,:global([data-bs-theme="dark"]) .password-health,:global([data-bs-theme="dark"]) .profile-form__footer{border-color:#364157;background:#272f40}:global([data-bs-theme="dark"]) .profile-facts dd,:global([data-bs-theme="dark"]) .photo-dropzone__text strong,:global([data-bs-theme="dark"]) .photo-editor__copy>span,:global([data-bs-theme="dark"]) .profile-field :deep(.form-label){color:#e0e6f5}:global([data-bs-theme="dark"]) .profile-input-wrap :deep(.form-control){border-color:#3a455d;background:#1f2736;color:#e6ebf7}:global([data-bs-theme="dark"]) .profile-input-wrap--locked :deep(.form-control:disabled){background:#252d3d;color:#9ba7bc}
</style>
