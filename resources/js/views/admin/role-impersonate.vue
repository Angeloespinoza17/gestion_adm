<script>
import axios from "axios";

export default {
  data() {
    return {
      loading: true,
      error: null,
    };
  },
  mounted() {
    this.impersonate();
  },
  methods: {
    async impersonate() {
      const roleSlug = this.$route.params.roleSlug;
      const currentToken = localStorage.getItem("token");
      const impersonatorToken = localStorage.getItem("impersonator_token") || currentToken;

      if (!impersonatorToken) {
        this.$router.replace({ name: "login", query: { redirectFrom: this.$route.fullPath } });
        return;
      }

      try {
        const auth = `Bearer ${impersonatorToken}`;
        const response = await axios.post(
          `/api/admin/impersonate/roles/${encodeURIComponent(roleSlug)}`,
          {},
          {
            headers: {
              Authorization: auth,
              "X-Authorization": auth,
              "X-Api-Token": impersonatorToken,
            },
          }
        );

        const payload = response.data.data;
        const token = payload.token;
        const user = payload.user;

        if (!localStorage.getItem("impersonator_token") && currentToken) {
          localStorage.setItem("impersonator_token", currentToken);
        }

        localStorage.setItem("token", token);
        localStorage.removeItem("permissions");
        localStorage.setItem(
          "user",
          JSON.stringify({
            login: true,
            user_id: user.id,
            name: user.name,
            email: user.email,
            profile_photo_url: user.profile_photo_url || null,
            impersonated_by: payload.impersonated_by?.email || null,
          })
        );

        axios.defaults.headers.common.Authorization = `Bearer ${token}`;
        document.cookie = `cnsc_token=${encodeURIComponent(token)}; path=/; samesite=lax`;
        this.$router.replace("/inicio");
      } catch (error) {
        this.error = error?.response?.data?.message || "No fue posible cambiar de usuario.";
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<template>
  <div class="impersonation-page">
    <div class="impersonation-panel">
      <i class="bx bx-transfer-alt"></i>
      <h4>{{ loading ? "Cambiando usuario" : "Cambio de usuario" }}</h4>
      <p v-if="loading">Validando permisos de superadmin...</p>
      <BAlert v-else-if="error" variant="danger" show class="mb-0">{{ error }}</BAlert>
    </div>
  </div>
</template>

<style scoped>
.impersonation-page {
  align-items: center;
  background: #f3f6f9;
  display: flex;
  min-height: 100vh;
  justify-content: center;
  padding: 1rem;
}

.impersonation-panel {
  background: #fff;
  border: 1px solid #eff2f7;
  border-radius: 8px;
  box-shadow: 0 12px 36px rgba(18, 38, 63, 0.08);
  max-width: 420px;
  padding: 2rem;
  text-align: center;
  width: 100%;
}

.impersonation-panel i {
  color: #556ee6;
  font-size: 2rem;
  margin-bottom: 0.75rem;
}

.impersonation-panel h4 {
  color: #2a3042;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.impersonation-panel p {
  color: #74788d;
  margin: 0;
}
</style>
