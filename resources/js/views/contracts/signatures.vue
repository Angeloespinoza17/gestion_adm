<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import LoadingState from "../../components/ui/loading-state.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";

const emptyForm = () => ({
  id: null,
  name: "",
  rut: "",
  position: "",
  signer_type: "representante_legal",
  active: true,
  sort_order: 0,
  observations: "",
});

export default {
  components: { Layout, LoadingState, Multiselect },
  data() {
    return {
      loading: false,
      saving: false,
      error: null,
      signers: [],
      catalogs: { signer_types: [] },
      filters: {
        search: "",
        signer_type: null,
        active: null,
      },
      form: emptyForm(),
      signatureImage: null,
      previewUrl: null,
    };
  },
  computed: {
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canManage() {
      return this.permissions.includes("administrar_firmas_contrato");
    },
    signerTypeOptions() {
      return [{ value: null, label: "Todos" }].concat(
        (this.catalogs.signer_types || []).map((item) => ({
          value: item.value,
          label: item.label,
        }))
      );
    },
    activeOptions() {
      return [
        { value: null, label: "Todos" },
        { value: true, label: "Activas" },
        { value: false, label: "Inactivas" },
      ];
    },
  },
  mounted() {
    this.loadCatalogs();
    this.loadSigners();
  },
  methods: {
    async loadCatalogs() {
      const response = await axios.get("/api/contract-signers/catalogs");
      this.catalogs = response.data;
    },
    async loadSigners() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get("/api/contract-signers", {
          params: this.filters,
        });
        this.signers = response.data.data;
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.loading = false;
      }
    },
    edit(item) {
      this.form = {
        id: item.id,
        name: item.name || "",
        rut: item.rut || "",
        position: item.position || "",
        signer_type: item.signer_type || "representante_legal",
        active: Boolean(item.active),
        sort_order: item.sort_order ?? 0,
        observations: item.observations || "",
      };
      this.previewUrl = item.signature_image_url || null;
      this.signatureImage = null;
    },
    resetForm() {
      this.form = emptyForm();
      this.previewUrl = null;
      this.signatureImage = null;
    },
    onImage(event) {
      const file = event?.target?.files?.[0] || null;
      this.signatureImage = file;
      this.previewUrl = file ? URL.createObjectURL(file) : null;
    },
    async save() {
      this.saving = true;
      this.error = null;
      try {
        const formData = new FormData();
        Object.entries(this.form).forEach(([key, value]) => formData.append(key, value ?? ""));
        if (this.signatureImage) {
          formData.append("signature_image", this.signatureImage);
        }

        if (this.form.id) {
          await axios.post(`/api/contract-signers/${this.form.id}`, formData);
        } else {
          await axios.post("/api/contract-signers", formData);
        }
        this.resetForm();
        await this.loadSigners();
      } catch (error) {
        this.error = this.formatError(error);
      } finally {
        this.saving = false;
      }
    },
    async toggle(item) {
      await axios.put(`/api/contract-signers/${item.id}/active`, { active: !item.active });
      await this.loadSigners();
    },
    async remove(item) {
      const result = await Swal.fire({
        title: "Eliminar firma",
        text: "La firma será eliminada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
      });
      if (!result.isConfirmed) return;
      await axios.delete(`/api/contract-signers/${item.id}`);
      await this.loadSigners();
    },
    formatError(error) {
      const errors = error?.response?.data?.errors || null;
      return (
        (errors ? errors[Object.keys(errors)[0]]?.[0] : null) ||
        error?.response?.data?.message ||
        error?.message ||
        "Error desconocido"
      );
    },
  },
};
</script>

<template>
  <Layout>
    <div class="d-sm-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="mb-0">Firmas institucionales</h4>
        <div class="text-muted">Representantes y firmantes disponibles para contratos.</div>
      </div>
    </div>

    <BAlert v-if="error" show variant="danger" class="mb-3">{{ error }}</BAlert>

    <div class="row g-4">
      <div class="col-xl-4">
        <BCard>
          <h5 class="mb-3">{{ form.id ? "Editar firma" : "Nueva firma" }}</h5>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nombre</label>
              <BFormInput v-model="form.name" />
            </div>
            <div class="col-md-6">
              <label class="form-label">RUT</label>
              <BFormInput v-model="form.rut" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Orden</label>
              <BFormInput v-model="form.sort_order" type="number" min="0" />
            </div>
            <div class="col-12">
              <label class="form-label">Cargo</label>
              <BFormInput v-model="form.position" />
            </div>
            <div class="col-12">
              <label class="form-label">Tipo de firmante</label>
              <Multiselect v-model="form.signer_type" :options="catalogs.signer_types.map((item) => ({ value: item.value, label: item.label }))" :searchable="true" />
            </div>
            <div class="col-12">
              <label class="form-label">Imagen de firma</label>
              <BFormInput type="file" accept="image/*" @change="onImage" />
            </div>
            <div class="col-12" v-if="previewUrl">
              <img :src="previewUrl" alt="Firma" class="img-fluid border rounded p-2 bg-white" />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones</label>
              <BFormTextarea v-model="form.observations" rows="3" />
            </div>
            <div class="col-12">
              <BFormCheckbox v-model="form.active">Firma activa</BFormCheckbox>
            </div>
          </div>
          <div class="d-flex gap-2 mt-4">
            <BButton variant="primary" :disabled="saving || !canManage" @click="save">
              {{ saving ? "Guardando..." : "Guardar" }}
            </BButton>
            <BButton variant="light" @click="resetForm">Limpiar</BButton>
          </div>
        </BCard>
      </div>

      <div class="col-xl-8">
        <BCard class="mb-4">
          <div class="row g-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label">Buscar</label>
              <BFormInput v-model="filters.search" @keyup.enter="loadSigners" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Tipo</label>
              <Multiselect v-model="filters.signer_type" :options="signerTypeOptions" :searchable="true" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Estado</label>
              <Multiselect v-model="filters.active" :options="activeOptions" />
            </div>
            <div class="col-md-2">
              <BButton variant="primary" class="w-100" @click="loadSigners">Filtrar</BButton>
            </div>
          </div>
        </BCard>

        <BCard no-body>
          <div class="table-responsive">
            <BTableSimple class="table align-middle table-nowrap mb-0">
              <BThead class="table-light">
                <BTr>
                  <BTh>Firmante</BTh>
                  <BTh>Tipo</BTh>
                  <BTh>Estado</BTh>
                  <BTh>Orden</BTh>
                  <BTh class="text-end">Acciones</BTh>
                </BTr>
              </BThead>
              <BTbody>
                <BTr v-if="loading">
                  <BTd colspan="5" class="text-center py-4">
                    <LoadingState message="Cargando firmantes..." compact />
                  </BTd>
                </BTr>
                <BTr v-else-if="signers.length === 0">
                  <BTd colspan="5" class="text-center py-4 text-muted">Sin firmantes registrados.</BTd>
                </BTr>
                <BTr v-for="item in signers" :key="item.id">
                  <BTd>
                    <div class="d-flex align-items-center gap-3">
                      <img
                        v-if="item.signature_image_url"
                        :src="item.signature_image_url"
                        alt="Firma"
                        class="rounded border bg-white p-1"
                        style="width: 72px; height: 48px; object-fit: contain;"
                      />
                      <div>
                        <div class="fw-semibold">{{ item.name }}</div>
                        <small class="text-muted">{{ item.position || "-" }} · {{ item.rut || "Sin RUT" }}</small>
                      </div>
                    </div>
                  </BTd>
                  <BTd>{{ catalogs.signer_types.find((opt) => opt.value === item.signer_type)?.label || item.signer_type }}</BTd>
                  <BTd>
                    <span :class="`badge rounded-pill badge-soft-${item.active ? 'success' : 'secondary'}`">
                      {{ item.active ? "Activa" : "Inactiva" }}
                    </span>
                  </BTd>
                  <BTd>{{ item.sort_order }}</BTd>
                  <BTd class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                      <BButton size="sm" variant="outline-primary" @click="edit(item)">Editar</BButton>
                      <BButton size="sm" :variant="item.active ? 'warning' : 'success'" @click="toggle(item)">
                        {{ item.active ? "Desactivar" : "Activar" }}
                      </BButton>
                      <BButton v-if="canManage" size="sm" variant="danger" @click="remove(item)">Eliminar</BButton>
                    </div>
                  </BTd>
                </BTr>
              </BTbody>
            </BTableSimple>
          </div>
        </BCard>
      </div>
    </div>
  </Layout>
</template>
