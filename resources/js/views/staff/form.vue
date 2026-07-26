<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";
import StaffFieldLabel from "../../components/staff/field-label.vue";
import { normalizeStaffNullableFields } from "../../components/staff/staff-utils";
import "../../components/staff/staff-ui.css";

const maintenanceRoleCatalog = [
  { value: "encargado_mantencion", label: "Encargado/a de mantención" },
  { value: "auxiliar_mantenimiento", label: "Auxiliar de mantenimiento" },
  { value: "auxiliar_aseo", label: "Auxiliar de aseo" },
  { value: "apoyo_operativo", label: "Apoyo operativo" },
  { value: "otro", label: "Otro" },
];

const emptyForm = () => ({
  full_name: "",
  rut: null,
  birth_date: null,
  institutional_email: null,
  personal_email: null,
  phone: null,
  address: null,
  region_id: null,
  commune_id: null,
  cargo_id: null,
  contract_type: null,
  start_date: null,
  end_date: null,
  status: "activo",
  workday: null,
  contract_hours: null,
  professional_title: null,
  specialty: null,
  professional_registration: null,
  internal_notes: null,
  active: true,
  can_receive_maintenance_orders: false,
  maintenance_role: null,
  associated_user_id: null,
  department_ids: [],
});

const emptyPermissionWatcher = () => ({
  target_type: "manager",
  role_id: null,
  user_id: null,
  notify: true,
  can_view: true,
  active: true,
});

export default {
  components: { Layout, Multiselect, StaffFieldLabel },
  data() {
    return {
      loading: false,
      saving: false,
      exportingPdf: false,
      uploadingDoc: false,
      error: null,
      success: null,
      catalogs: {
        cargos: [],
        departments: [],
        users: [],
        roles: [],
        regions: [],
        communes: [],
        statuses: [],
        contract_types: [],
        workdays: [],
        maintenance_roles: [],
      },
      staff: null,
      form: emptyForm(),
      profilePhoto: null,
      profilePhotoPreview: null,
      newDocFile: null,
      newDocType: "Otro",
      newDocObs: "",
      docError: null,
      permissionSummary: null,
      permissionWatchers: [],
      permissionWatcherForm: emptyPermissionWatcher(),
      savingPermissionWatchers: false,
    };
  },
  computed: {
    isNew() {
      return this.$route.path === "/staff/new";
    },
    pageTitle() {
      return this.isNew ? "Nuevo funcionario" : "Ficha de funcionario";
    },
    pageSubtitle() {
      if (this.isNew) {
        return "Crea la ficha con el nombre y completa los demás datos cuando estén disponibles.";
      }

      if (this.staff) {
        return `${this.staff.full_name || "Funcionario"} · ${this.staff.rut || "Sin RUT"}`;
      }

      return "Gestión de datos personales, laborales y documentos.";
    },
    primaryActionLabel() {
      if (this.saving) {
        return "Guardando...";
      }

      return this.isNew ? "Crear funcionario" : "Guardar cambios";
    },
    itemId() {
      return this.$route.params.id;
    },
    permissions() {
      try {
        return JSON.parse(localStorage.getItem("permissions") || "[]");
      } catch (error) {
        return [];
      }
    },
    canEdit() {
      return this.permissions.includes("gestionar_funcionarios");
    },
    canManageDocuments() {
      return this.permissions.includes("subir_documentos_funcionarios");
    },
    canViewContracts() {
      return this.permissions.includes("ver_contratos") || this.permissions.includes("gestionar_contratos");
    },
    canManageContracts() {
      return this.permissions.includes("gestionar_contratos");
    },
    canViewPermissionModule() {
      return this.permissions.includes("ver_permisos_personal");
    },
    canManagePermissionWatchers() {
      return (
        this.permissions.includes("administrar_destinatarios_permisos_personal") ||
        this.permissions.includes("administrar_tipos_permisos_personal")
      );
    },
    canDelete() {
      return this.permissions.includes("eliminar_funcionarios");
    },
    canExport() {
      return (
        this.permissions.includes("exportar_funcionarios") ||
        this.permissions.includes("gestionar_funcionarios") ||
        this.permissions.includes("ver_funcionarios")
      );
    },
    cargoOptions() {
      return [{ value: null, label: "Sin cargo" }].concat(
        (this.catalogs.cargos || []).map((cargo) => ({
          value: cargo.id,
          label: cargo.name,
        }))
      );
    },
    departmentOptions() {
      return (this.catalogs.departments || []).map((department) => ({
        value: department.id,
        label: department.name,
      }));
    },
    regionOptions() {
      return [{ value: null, label: "Sin región" }].concat(
        (this.catalogs.regions || []).map((region) => ({
          value: region.id,
          label: region.short_name || region.name,
        }))
      );
    },
    communeOptions() {
      const regionId = this.form.region_id;

      const communes = (this.catalogs.communes || []).filter(
        (commune) => !regionId || commune.region_id === regionId
      );

      return [{ value: null, label: "Sin comuna" }].concat(
        communes.map((commune) => ({
          value: commune.id,
          label: commune.name,
        }))
      );
    },
    userOptions() {
      if (this.isNew) {
        return [];
      }

      return [{ value: null, label: "Sin usuario asociado" }].concat(
        (this.catalogs.users || [])
          .filter((user) => !user.staff_id || user.staff_id === this.staff?.id)
          .map((user) => ({
            value: user.id,
            label: `${user.name} (${user.email})`,
          }))
      );
    },
    permissionWatcherRoleOptions() {
      return [{ value: null, label: "Seleccionar rol..." }].concat(
        (this.catalogs.roles || []).map((role) => ({
          value: role.id,
          label: role.name,
        }))
      );
    },
    permissionWatcherUserOptions() {
      return [{ value: null, label: "Seleccionar usuario..." }].concat(
        (this.catalogs.users || [])
          .filter((user) => user.active)
          .map((user) => ({
            value: user.id,
            label: `${user.name}${user.email ? ` (${user.email})` : ""}`,
          }))
      );
    },
    permissionWatcherTargetOptions() {
      return [
        { value: "manager", label: "Jefatura directa" },
        { value: "direction", label: "Dirección" },
        { value: "hr", label: "RRHH / Administración" },
        { value: "role", label: "Rol" },
        { value: "user", label: "Usuario específico" },
      ];
    },
    statusOptions() {
      return (this.catalogs.statuses || []).map((status) => ({
        value: status.value,
        label: status.label,
      }));
    },
    contractTypeOptions() {
      return (this.catalogs.contract_types || []).map((type) => ({
        value: type.value,
        label: type.label,
      }));
    },
    workdayOptions() {
      return (this.catalogs.workdays || []).map((workday) => ({
        value: workday.value,
        label: workday.label,
      }));
    },
    maintenanceRoleOptions() {
      const roles = Array.isArray(this.catalogs.maintenance_roles) && this.catalogs.maintenance_roles.length > 0
        ? this.catalogs.maintenance_roles
        : maintenanceRoleCatalog;

      return roles.map((role) => ({
        value: role.value,
        label: role.label,
      }));
    },
    currentPhotoUrl() {
      return this.profilePhotoPreview || this.staff?.profile_photo_url || null;
    },
    selectedCargoLabel() {
      return this.cargoOptions.find((item) => item.value === this.form.cargo_id)?.label || "Sin cargo asignado";
    },
    selectedStatusLabel() {
      return this.statusOptions.find((item) => item.value === this.form.status)?.label || this.form.status || "Sin estado";
    },
    profileInitials() {
      const words = String(this.form.full_name || "")
        .trim()
        .split(/\s+/)
        .filter(Boolean);

      if (!words.length) {
        return "F";
      }

      return words
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join("")
        .toUpperCase();
    },
    departmentSummary() {
      const departments = this.staff?.departments || [];

      if (!departments.length) {
        return "Sin equipo asignado";
      }

      if (departments.length === 1) {
        return departments[0].name;
      }

      return `${departments[0].name} +${departments.length - 1}`;
    },
    completionMessage() {
      if (this.formCompletionPercent === 100) {
        return "Información esencial completa";
      }

      return "Completa los datos esenciales de la ficha";
    },
    keyFieldStatus() {
      return [
        { key: "full_name", label: "Nombre", complete: Boolean(String(this.form.full_name || "").trim()) },
        { key: "rut", label: "RUT", complete: Boolean(String(this.form.rut || "").trim()) },
        { key: "institutional_email", label: "Correo", complete: Boolean(String(this.form.institutional_email || "").trim()) },
        { key: "cargo_id", label: "Cargo", complete: Boolean(this.form.cargo_id) },
        { key: "status", label: "Estado", complete: Boolean(this.form.status) },
      ];
    },
    completedKeyFieldCount() {
      return this.keyFieldStatus.filter((item) => item.complete).length;
    },
    formCompletionPercent() {
      if (!this.keyFieldStatus.length) {
        return 0;
      }

      return Math.round((this.completedKeyFieldCount / this.keyFieldStatus.length) * 100);
    },
    missingRequiredFields() {
      const fields = [
        { label: "Nombre completo", complete: Boolean(String(this.form.full_name || "").trim()) },
        { label: "Estado laboral", complete: Boolean(this.form.status) },
      ];

      return fields.filter((item) => !item.complete);
    },
    documentTypes() {
      return [
        "Contrato",
        "Anexo",
        "Certificado",
        "Título",
        "Informe",
        "Licencia",
        "Otro",
      ];
    },
    contractStatuses() {
      return [
        { value: "borrador", label: "Borrador" },
        { value: "generado", label: "Generado" },
        { value: "enviado_firma", label: "Enviado a firma" },
        { value: "firmado", label: "Firmado" },
        { value: "anulado", label: "Anulado" },
        { value: "vencido", label: "Vencido" },
      ];
    },
    isIndefiniteContract() {
      return this.form.contract_type === "indefinido";
    },
    reservationStatusVariant() {
      return (status) => {
        if (status === "aprobada") return "success";
        if (status === "pendiente") return "warning";
        if (status === "rechazada") return "danger";
        if (status === "cancelada") return "secondary";
        return "info";
      };
    },
  },
  watch: {
    "$route.path"() {
      this.load();
    },
    "form.contract_type"(value) {
      if (value === "indefinido") {
        this.form.end_date = null;
      }
    },
    "form.region_id"() {
      const found = this.communeOptions.some((option) => option.value === this.form.commune_id);

      if (!found) {
        this.form.commune_id = null;
      }
    },
    "form.can_receive_maintenance_orders"(value) {
      if (!value) {
        this.form.maintenance_role = null;
        return;
      }

      if (!this.form.maintenance_role) {
        this.form.maintenance_role = "auxiliar_mantenimiento";
      }
    },
  },
  mounted() {
    this.load();
    if (this.$route.query.created) {
      this.success = "Funcionario creado correctamente.";
      this.showSuccessAlert("Funcionario creado", this.success);
    }
    if (this.$route.query.saved) {
      this.success = "Funcionario actualizado correctamente.";
      this.showSuccessAlert("Funcionario actualizado", this.success);
    }
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = null;
      if (this.isNew) {
        this.staff = null;
        this.form = emptyForm();
        this.profilePhoto = null;
        this.profilePhotoPreview = null;
        this.permissionSummary = null;
        this.permissionWatchers = [];
      }

      try {
        const requests = [axios.get("/api/staff/catalogs")];
        if (!this.isNew) {
          requests.push(axios.get(`/api/staff/${this.itemId}`));
          if (this.canViewPermissionModule) {
            requests.push(axios.get(`/api/staff/${this.itemId}/permission-summary`));
          }
        }

        const responses = await Promise.all(requests);
        const catalogs = responses[0].data || {};
        this.catalogs = {
          ...this.catalogs,
          ...catalogs,
          maintenance_roles: Array.isArray(catalogs.maintenance_roles) && catalogs.maintenance_roles.length > 0
            ? catalogs.maintenance_roles
            : maintenanceRoleCatalog,
        };

        if (!this.isNew) {
          this.staff = responses[1].data.data;
          this.permissionSummary = this.canViewPermissionModule ? responses[2]?.data || null : null;
          this.permissionWatchers = (this.staff.permission_watchers || []).map((item) => ({
            id: item.id,
            target_type: item.target_type,
            role_id: item.role_id,
            user_id: item.user_id,
            notify: !!item.notify,
            can_view: !!item.can_view,
            active: !!item.active,
            role: item.role || null,
            user: item.user || null,
          }));
          this.form = {
            full_name: this.staff.full_name || "",
            rut: this.staff.rut ?? null,
            birth_date: this.staff.birth_date ?? null,
            institutional_email: this.staff.institutional_email ?? null,
            personal_email: this.staff.personal_email ?? null,
            phone: this.staff.phone ?? null,
            address: this.staff.address ?? null,
            region_id: this.staff.region_id ?? this.staff.region_record?.id ?? null,
            commune_id: this.staff.commune_id ?? this.staff.commune_record?.id ?? null,
            cargo_id: this.staff.cargo_id ?? null,
            contract_type: this.staff.contract_type || null,
            start_date: this.staff.start_date ?? null,
            end_date: this.staff.end_date ?? null,
            status: this.staff.status || "activo",
            workday: this.staff.workday || null,
            contract_hours: this.staff.contract_hours ?? null,
            professional_title: this.staff.professional_title ?? null,
            specialty: this.staff.specialty ?? null,
            professional_registration: this.staff.professional_registration ?? null,
            internal_notes: this.staff.internal_notes ?? null,
            active: Boolean(this.staff.active),
            can_receive_maintenance_orders: Boolean(this.staff.can_receive_maintenance_orders),
            maintenance_role: this.staff.maintenance_role || null,
            associated_user_id: this.staff.user?.id ?? null,
            department_ids: (this.staff.departments || []).map((department) => department.id),
          };
        }
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.loading = false;
      }
    },
    resetPermissionWatcherForm() {
      this.permissionWatcherForm = emptyPermissionWatcher();
    },
    permissionWatcherLabel(item) {
      if (item.target_type === "manager") return "Jefatura directa";
      if (item.target_type === "direction") return "Dirección";
      if (item.target_type === "hr") return "RRHH / Administración";
      if (item.target_type === "role") return `Rol: ${item.role?.name || "Sin rol"}`;
      if (item.target_type === "user") return `Usuario: ${item.user?.name || "Sin usuario"}`;
      return item.target_type;
    },
    addPermissionWatcher() {
      this.error = null;

      if (this.permissionWatcherForm.target_type === "role" && !this.permissionWatcherForm.role_id) {
        this.error = "Debes seleccionar un rol para el destinatario del funcionario.";
        return;
      }

      if (this.permissionWatcherForm.target_type === "user" && !this.permissionWatcherForm.user_id) {
        this.error = "Debes seleccionar un usuario para el destinatario del funcionario.";
        return;
      }

      const duplicate = this.permissionWatchers.some((item) =>
        item.target_type === this.permissionWatcherForm.target_type &&
        Number(item.role_id || 0) === Number(this.permissionWatcherForm.role_id || 0) &&
        Number(item.user_id || 0) === Number(this.permissionWatcherForm.user_id || 0)
      );

      if (duplicate) {
        this.error = "Ese destinatario ya está agregado para este funcionario.";
        return;
      }

      this.permissionWatchers.push({
        ...emptyPermissionWatcher(),
        ...this.permissionWatcherForm,
        role: (this.catalogs.roles || []).find((item) => Number(item.id) === Number(this.permissionWatcherForm.role_id)) || null,
        user: (this.catalogs.users || []).find((item) => Number(item.id) === Number(this.permissionWatcherForm.user_id)) || null,
      });

      this.resetPermissionWatcherForm();
    },
    removePermissionWatcher(index) {
      this.permissionWatchers.splice(index, 1);
    },
    async savePermissionWatchers() {
      if (!this.staff || !this.canManagePermissionWatchers) {
        return;
      }

      this.savingPermissionWatchers = true;
      this.error = null;

      try {
        await axios.put(`/api/staff/${this.staff.id}/permission-watchers`, {
          watchers: this.permissionWatchers.map((item) => ({
            target_type: item.target_type,
            role_id: item.target_type === "role" ? item.role_id : null,
            user_id: item.target_type === "user" ? item.user_id : null,
            notify: !!item.notify,
            can_view: !!item.can_view,
            active: !!item.active,
          })),
        });

        this.showSuccessAlert("Destinatarios guardados", "Los destinatarios del funcionario fueron actualizados correctamente.");
        await this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.savingPermissionWatchers = false;
      }
    },
    onProfilePhoto(event) {
      const file = event?.target?.files?.[0] || null;
      this.profilePhoto = file;
      this.profilePhotoPreview = file ? URL.createObjectURL(file) : null;
    },
    buildPayload() {
      const formData = new FormData();
      const normalizedForm = normalizeStaffNullableFields(this.form, [
        "rut",
        "birth_date",
        "institutional_email",
        "personal_email",
        "phone",
        "address",
        "region_id",
        "commune_id",
        "cargo_id",
        "contract_type",
        "start_date",
        "end_date",
        "workday",
        "contract_hours",
        "professional_title",
        "specialty",
        "professional_registration",
        "internal_notes",
        "maintenance_role",
        "associated_user_id",
      ]);

      Object.entries(normalizedForm).forEach(([key, value]) => {
        if (key === "department_ids") {
          formData.append(key, JSON.stringify(value || []));
          return;
        }

        if (typeof value === "boolean") {
          formData.append(key, value ? "1" : "0");
          return;
        }

        if (value === null || value === undefined || value === "") {
          formData.append(key, "");
          return;
        }

        formData.append(key, value);
      });

      if (this.profilePhoto) {
        formData.append("profile_photo", this.profilePhoto);
      }

      return formData;
    },
    validateBeforeSave() {
      if (!this.missingRequiredFields.length) {
        return true;
      }

      const items = this.missingRequiredFields
        .map((field) => `<li>${field.label}</li>`)
        .join("");

      Swal.fire({
        title: "Faltan datos obligatorios",
        html: `<div class="text-start"><p class="mb-2">Completa estos campos antes de guardar:</p><ul class="mb-0">${items}</ul></div>`,
        icon: "warning",
        confirmButtonText: "Revisar formulario",
        customClass: { popup: "staff-alert" },
      });

      return false;
    },
    async confirmSave() {
      const name = String(this.form.full_name || "").trim() || "este funcionario";

      const result = await Swal.fire({
        title: this.isNew ? "Crear funcionario" : "Guardar cambios",
        text: this.isNew
          ? `Se creará la ficha de ${name}.`
          : `Se actualizará la ficha de ${name}.`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: this.isNew ? "Sí, crear" : "Sí, guardar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        customClass: { popup: "staff-alert" },
      });

      return result.isConfirmed;
    },
    async save() {
      if (!this.canEdit || this.saving) {
        return;
      }

      if (!this.validateBeforeSave()) {
        return;
      }

      const confirmed = await this.confirmSave();
      if (!confirmed) {
        return;
      }

      this.saving = true;
      this.error = null;
      this.success = null;

      try {
        const payload = this.buildPayload();
        let response;

        if (this.isNew) {
          response = await axios.post("/api/staff", payload);
          this.success = "Funcionario creado correctamente.";
          this.profilePhoto = null;
          this.profilePhotoPreview = null;
          await this.showSuccessAlert("Funcionario creado", this.success);
          await this.$router.replace({ path: `/staff/${response.data.data.id}` });
          return;
        }

        payload.append("_method", "PUT");
        response = await axios.post(`/api/staff/${this.staff.id}`, payload);
        this.staff = response.data.data;
        this.success = "Funcionario actualizado correctamente.";
        await this.showSuccessAlert("Cambios guardados", this.success);
        this.profilePhoto = null;
        this.profilePhotoPreview = null;
        await this.load();
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      } finally {
        this.saving = false;
      }
    },
    onNewDoc(event) {
      this.newDocFile = event?.target?.files?.[0] || null;
    },
    async uploadDocument() {
      if (!this.staff || !this.canManageDocuments) {
        return;
      }

      this.docError = null;
      if (!this.newDocFile) {
        this.docError = "Selecciona un archivo.";
        return;
      }

      this.uploadingDoc = true;
      try {
        const formData = new FormData();
        formData.append("document", this.newDocFile);
        formData.append("document_type", this.newDocType);
        if (this.newDocObs) {
          formData.append("observations", this.newDocObs);
        }

        await axios.post(`/api/staff/${this.staff.id}/documents`, formData);
        this.newDocFile = null;
        this.newDocType = "Otro";
        this.newDocObs = "";
        this.showSuccessAlert("Documento cargado", "El documento se subió correctamente.");
        await this.load();
      } catch (error) {
        this.docError = this.formatError(error);
        this.showErrorAlert(this.docError);
      } finally {
        this.uploadingDoc = false;
      }
    },
    async deleteDocument(document) {
      const result = await this.confirmAction({
        title: "Eliminar documento",
        text: `Se eliminará ${document.original_name}.`,
        confirmButtonText: "Sí, eliminar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.delete(`/api/staff/documents/${document.id}`);
        this.showSuccessAlert("Documento eliminado", "El documento fue eliminado correctamente.");
        await this.load();
      } catch (error) {
        this.docError = this.formatError(error);
        this.showErrorAlert(this.docError);
      }
    },
    async removeStaff() {
      if (!this.staff || !this.canDelete) {
        return;
      }

      const result = await this.confirmAction({
        title: "Eliminar funcionario y cuenta",
        text: `Se eliminará a ${this.staff.full_name}, su cuenta de acceso y sus registros asociados, incluidas las reservas. Esta acción no se puede deshacer.`,
        confirmButtonText: "Sí, eliminar todo",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.delete(`/api/staff/${this.staff.id}`);
        this.showSuccessAlert("Eliminación completada", "El funcionario y su cuenta de acceso fueron eliminados correctamente.");
        this.$router.push("/staff");
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error);
      }
    },
    async toDataUrl(url) {
      const response = await fetch(url, { credentials: "same-origin" });
      const blob = await response.blob();
      return await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
      });
    },
    async exportPdf() {
      if (!this.staff) {
        return;
      }

      this.exportingPdf = true;
      this.error = null;

      try {
        const pdfMake = getPdfMake();
        let profileImage = null;

        if (this.staff.profile_photo_url) {
          try {
            profileImage = await this.toDataUrl(this.staff.profile_photo_url);
          } catch (error) {
            profileImage = null;
          }
        }

        const personalData = [
          ["Nombre completo", this.staff.full_name || "-"],
          ["RUT", this.staff.rut || "-"],
          ["Fecha de nacimiento", this.formatDate(this.staff.birth_date) || "-"],
          ["Correo institucional", this.staff.institutional_email || "-"],
          ["Correo personal", this.staff.personal_email || "-"],
          ["Teléfono", this.staff.phone || "-"],
          ["Dirección", this.staff.address || "-"],
          ["Región", this.staff.region_record?.short_name || this.staff.region_record?.name || this.staff.region || "-"],
          ["Comuna", this.staff.commune_record?.name || this.staff.commune || "-"],
        ];

        const laborData = [
          ["Cargo", this.staff.cargo?.name || "-"],
          ["Tipo de contrato", this.contractTypeOptions.find((item) => item.value === this.staff.contract_type)?.label || "-"],
          ["Fecha de ingreso", this.formatDate(this.staff.start_date) || "-"],
          ["Fecha de término", this.formatDate(this.staff.end_date) || "-"],
          ["Estado laboral", this.statusOptions.find((item) => item.value === this.staff.status)?.label || "-"],
          ["Jornada", this.workdayOptions.find((item) => item.value === this.staff.workday)?.label || "-"],
          ["Horas de contrato", this.staff.contract_hours ?? "-"],
          ["Título profesional", this.staff.professional_title || "-"],
          ["Especialidad", this.staff.specialty || "-"],
          ["Registro profesional", this.staff.professional_registration || "-"],
          ["Rol operativo mantención", this.staff.can_receive_maintenance_orders ? (this.staff.maintenance_role_label || "-") : "No recibe OT"],
        ];

        const institutionData = [
          ["Usuario asociado", this.staff.user ? `${this.staff.user.name} (${this.staff.user.email})` : "Sin acceso al sistema"],
          ["Equipos a los que pertenece", (this.staff.departments || []).map((department) => department.name).join(", ") || "-"],
          ["Departamentos a cargo", (this.staff.managed_departments || []).map((department) => department.name).join(", ") || "-"],
          ["Registro activo", this.staff.active ? "Sí" : "No"],
          ["Creado", this.formatDateTime(this.staff.created_at) || "-"],
          ["Actualizado", this.formatDateTime(this.staff.updated_at) || "-"],
          ["Observaciones internas", this.staff.internal_notes || "-"],
        ];

        const documentsTable =
          (this.staff.documents || []).length > 0
            ? {
                table: {
                  headerRows: 1,
                  widths: [120, "*", 110, 110],
                  body: [
                    [
                      { text: "Tipo", style: "tableHeader" },
                      { text: "Archivo", style: "tableHeader" },
                      { text: "Fecha", style: "tableHeader" },
                      { text: "Observaciones", style: "tableHeader" },
                    ],
                    ...(this.staff.documents || []).map((document) => [
                      document.document_type || "-",
                      document.original_name || "-",
                      this.formatDateTime(document.created_at) || "-",
                      document.observations || "-",
                    ]),
                  ],
                },
                layout: "lightHorizontalLines",
              }
            : { text: "Sin documentos adjuntos.", style: "muted" };

        const tableSection = (title, rows) => ([
          { text: title, style: "sectionTitle" },
          {
            table: {
              widths: [150, "*"],
              body: rows.map(([label, value]) => [
                { text: String(label), style: "tableHeader" },
                { text: String(value ?? "-") },
              ]),
            },
            layout: "lightHorizontalLines",
            margin: [0, 0, 0, 12],
          },
        ]);

        const docDefinition = {
          pageSize: "A4",
          pageMargins: [40, 50, 40, 50],
          content: [
            {
              columns: [
                [
                  { text: "Ficha de funcionario", style: "title" },
                  { text: this.staff.full_name || "-", style: "subtitle" },
                  { text: this.staff.rut || "-", style: "muted" },
                ],
                profileImage
                  ? { image: profileImage, width: 80, alignment: "right" }
                  : { text: "Sin foto", style: "muted", alignment: "right" },
              ],
            },
            { text: " ", margin: [0, 6] },
            ...tableSection("Datos personales", personalData),
            ...tableSection("Datos laborales", laborData),
            ...tableSection("Datos institucionales", institutionData),
            { text: "Documentos adjuntos", style: "sectionTitle" },
            documentsTable,
          ],
          styles: {
            title: { fontSize: 18, bold: true, color: "#2a3042" },
            subtitle: { fontSize: 12, bold: true, margin: [0, 2, 0, 2] },
            sectionTitle: { fontSize: 12, bold: true, color: "#495057", margin: [0, 8, 0, 6] },
            tableHeader: { bold: true, fillColor: "#f8f9fa", color: "#495057" },
            muted: { color: "#74788d", fontSize: 9 },
          },
          defaultStyle: {
            fontSize: 10,
          },
        };

        pdfMake.createPdf(docDefinition).download(`funcionario_${(this.staff.rut || this.staff.id || "ficha").toString().replace(/\s+/g, "_")}.pdf`);
      } catch (error) {
        this.error = this.formatError(error);
        this.showErrorAlert(this.error || "Error generando PDF.");
      } finally {
        this.exportingPdf = false;
      }
    },
    confirmAction({ title, text, confirmButtonText }) {
      return Swal.fire({
        title,
        text,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: "Cancelar",
        reverseButtons: true,
        customClass: { popup: "staff-alert" },
      });
    },
    showSuccessAlert(title, text) {
      return Swal.fire({
        title,
        text,
        icon: "success",
        timer: 1800,
        showConfirmButton: false,
        customClass: { popup: "staff-alert" },
      });
    },
    showErrorAlert(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
        customClass: { popup: "staff-alert" },
      });
    },
    formatDate(value) {
      if (!value) return "-";
      const normalized = String(value).trim().replace("T", " ");
      const datePart = normalized.split(" ")[0];
      const [year, month, day] = datePart.split("-");

      if (year && month && day) {
        return `${day}/${month}/${year}`;
      }

      return value;
    },
    formatDateTime(value) {
      if (!value) return "-";
      const normalized = String(value).trim().replace("T", " ").replace(/\.\d+Z?$/, "");
      const [datePart, timePart = ""] = normalized.split(" ");
      const [year, month, day] = (datePart || "").split("-");

      if (!(year && month && day)) {
        return value;
      }

      const [hours = "00", minutes = "00"] = timePart.split(":");

      return `${day}/${month}/${year} ${hours}:${minutes}`;
    },
    contractStatusLabel(value) {
      return this.contractStatuses.find((item) => item.value === value)?.label || value || "-";
    },
    contractStatusVariant(value) {
      if (value === "firmado") return "success";
      if (value === "anulado") return "danger";
      if (value === "vencido") return "secondary";
      if (value === "enviado_firma") return "warning";
      if (value === "generado") return "info";
      return "primary";
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
    <div class="staff-form-page">
      <div class="staff-form-hero mb-4">
        <div class="staff-form-hero__glow staff-form-hero__glow--top"></div>
        <div class="staff-form-hero__glow staff-form-hero__glow--bottom"></div>

        <div class="staff-form-hero__content">
          <div class="staff-hero-avatar" aria-hidden="true">
            <img v-if="currentPhotoUrl" :src="currentPhotoUrl" alt="" />
            <span v-else>{{ profileInitials }}</span>
          </div>
          <div class="min-w-0">
            <div class="staff-form-kicker">Gestión de funcionarios</div>
            <h2 class="staff-form-title">{{ pageTitle }}</h2>
            <div class="staff-form-subtitle">{{ pageSubtitle }}</div>
            <div v-if="!isNew" class="staff-hero-tags">
              <span class="staff-hero-tag staff-hero-tag--success">
                <i class="mdi mdi-check-decagram-outline"></i>
                {{ selectedStatusLabel }}
              </span>
              <span class="staff-hero-tag">
                <i class="mdi mdi-briefcase-outline"></i>
                {{ selectedCargoLabel }}
              </span>
              <span class="staff-hero-tag">
                <i class="mdi mdi-account-group-outline"></i>
                {{ departmentSummary }}
              </span>
            </div>
          </div>
        </div>

        <div class="staff-form-actions d-flex flex-wrap justify-content-xl-end gap-2">
          <router-link to="/staff" class="btn btn-outline-secondary">
            <i class="mdi mdi-arrow-left me-1"></i>
            Volver
          </router-link>
          <BButton
            v-if="staff && canExport"
            variant="outline-primary"
            :disabled="exportingPdf"
            @click="exportPdf"
          >
            <i class="mdi mdi-file-pdf-box me-1"></i>
            {{ exportingPdf ? "Generando PDF..." : "Exportar PDF" }}
          </BButton>
          <BButton v-if="canDelete && staff" variant="outline-danger" @click="removeStaff">
            <i class="mdi mdi-trash-can-outline me-1"></i>
            Eliminar
          </BButton>
          <BButton v-if="canEdit" variant="primary" :disabled="saving" @click="save">
            <i :class="saving ? 'mdi mdi-loading mdi-spin me-1' : 'mdi mdi-content-save-outline me-1'"></i>
            {{ primaryActionLabel }}
          </BButton>
        </div>

        <div v-if="isNew" class="staff-hero-new-state">
          <div class="staff-key-fields">
            <div
              v-for="field in keyFieldStatus"
              :key="field.key"
              class="staff-key-chip"
              :class="{ 'is-complete': field.complete }"
            >
              <i :class="field.complete ? 'mdi mdi-check-circle-outline' : 'mdi mdi-circle-outline'"></i>
              {{ field.label }}
            </div>
          </div>
          <div class="staff-nullable-note">
            <i class="bx bx-info-circle"></i>
            <span>
              Solo el nombre y el estado laboral son obligatorios. La cuenta de acceso se crea cuando completes RUT y correo institucional.
            </span>
          </div>
        </div>
      </div>

      <BAlert v-if="error" variant="danger" show class="staff-form-alert mb-3">{{ error }}</BAlert>
      <BAlert v-if="success" variant="success" show class="staff-form-alert mb-3">{{ success }}</BAlert>
      <BCard v-if="loading" class="staff-form-card">
        <div class="staff-loading">
          <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          Cargando ficha...
        </div>
      </BCard>

      <div v-else class="row g-4">
        <div class="col-xl-4">
          <div class="staff-sticky-column">
            <BCard class="staff-form-card staff-state-card">
              <div class="staff-section-heading">
                <div class="staff-section-icon staff-section-icon--violet">
                  <i class="mdi mdi-shield-account-outline"></i>
                </div>
                <div>
                  <div class="staff-section-eyebrow">Control de la ficha</div>
                  <h5>Estado y disponibilidad</h5>
                </div>
              </div>

              <div class="staff-completion">
                <div class="staff-completion__topline">
                  <div>
                    <span>Completitud</span>
                    <strong>{{ completionMessage }}</strong>
                  </div>
                  <div class="staff-completion__value">{{ formCompletionPercent }}%</div>
                </div>
                <div class="progress staff-progress">
                  <div
                    class="progress-bar"
                    role="progressbar"
                    :style="{ width: `${formCompletionPercent}%` }"
                    :aria-valuenow="formCompletionPercent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                  ></div>
                </div>
              </div>

              <div class="staff-control-group">
                <StaffFieldLabel label="Foto de perfil" />
                <div class="staff-file-control">
                  <input
                    class="form-control"
                    type="file"
                    accept="image/*"
                    :disabled="!canEdit"
                    @change="onProfilePhoto"
                  />
                </div>
                <div class="staff-field-help">
                  <strong>{{ profilePhoto ? profilePhoto.name : "Ningún archivo seleccionado" }}</strong>
                  · JPG o PNG para el perfil institucional.
                </div>
              </div>

              <div class="staff-control-group">
                <StaffFieldLabel label="Estado laboral" required />
                <Multiselect
                  v-model="form.status"
                  class="staff-multiselect"
                  :options="statusOptions"
                  :searchable="true"
                  :can-clear="false"
                  placeholder="Seleccionar estado laboral"
                  :disabled="!canEdit"
                />
              </div>

              <div class="staff-toggle-list">
                <div class="staff-toggle-card">
                  <span class="staff-toggle-card__icon staff-toggle-card__icon--success">
                    <i class="mdi mdi-account-check-outline"></i>
                  </span>
                  <span class="staff-toggle-card__copy">
                    <strong>Registro activo</strong>
                    <small>Disponible en directorios y procesos internos.</small>
                  </span>
                  <BFormCheckbox v-model="form.active" switch :disabled="!canEdit" />
                </div>

                <div class="staff-toggle-card">
                  <span class="staff-toggle-card__icon">
                    <i class="mdi mdi-tools"></i>
                  </span>
                  <span class="staff-toggle-card__copy">
                    <strong>Recibe OT de mantención</strong>
                    <small>Puede ser seleccionado como responsable operativo.</small>
                  </span>
                  <BFormCheckbox v-model="form.can_receive_maintenance_orders" switch :disabled="!canEdit" />
                </div>
              </div>

              <div v-if="form.can_receive_maintenance_orders" class="staff-control-group mt-3">
                <StaffFieldLabel label="Rol operativo" required />
                <Multiselect
                  v-model="form.maintenance_role"
                  class="staff-role-multiselect"
                  :options="maintenanceRoleOptions"
                  :searchable="true"
                  :can-clear="false"
                  placeholder="Seleccionar rol operativo"
                  :disabled="!canEdit"
                />
              </div>

              <div v-if="staff" class="staff-record-meta">
                <div>
                  <span>Última actualización</span>
                  <strong>{{ formatDateTime(staff.updated_at) }}</strong>
                </div>
                <div>
                  <span>Cuenta vinculada</span>
                  <strong>{{ staff.user ? staff.user.email : "Sin asociación" }}</strong>
                </div>
              </div>
            </BCard>
          </div>
        </div>

        <div class="col-xl-8">
          <BCard class="staff-form-card staff-section-card">
            <div class="staff-section-heading">
              <div class="staff-section-icon">
                <i class="mdi mdi-card-account-details-outline"></i>
              </div>
              <div>
                <div class="staff-section-eyebrow">Identidad y contacto</div>
                <h5>Datos personales</h5>
                <p>Información de identificación y canales de contacto del funcionario.</p>
              </div>
            </div>
          <div class="row g-3">
            <div class="col-md-8">
              <StaffFieldLabel label="Nombre completo" required />
              <BFormInput v-model="form.full_name" placeholder="Nombre y apellidos" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="RUT" />
              <BFormInput v-model="form.rut" placeholder="12.345.678-9" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Fecha de nacimiento" />
              <BFormInput v-model="form.birth_date" type="date" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Correo institucional" />
              <BFormInput
                v-model="form.institutional_email"
                type="email"
                placeholder="nombre@colegio.cl"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Correo personal" />
              <BFormInput
                v-model="form.personal_email"
                type="email"
                placeholder="correo personal"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Teléfono" />
              <BFormInput v-model="form.phone" placeholder="+569..." :disabled="!canEdit" />
            </div>
            <div class="col-md-8">
              <StaffFieldLabel label="Dirección" />
              <BFormInput v-model="form.address" placeholder="Calle, número, sector" :disabled="!canEdit" />
            </div>
            <div class="col-md-6">
              <StaffFieldLabel label="Región" />
              <Multiselect
                v-model="form.region_id"
                class="staff-multiselect"
                :options="regionOptions"
                :searchable="true"
                placeholder="Seleccionar región"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-6">
              <StaffFieldLabel label="Comuna" />
              <Multiselect
                v-model="form.commune_id"
                class="staff-multiselect"
                :options="communeOptions"
                :searchable="true"
                placeholder="Seleccionar comuna"
                :disabled="!canEdit || !form.region_id"
              />
            </div>
          </div>
          </BCard>

          <BCard class="staff-form-card staff-section-card mt-4">
            <div class="staff-section-heading">
              <div class="staff-section-icon staff-section-icon--amber">
                <i class="mdi mdi-briefcase-variant-outline"></i>
              </div>
              <div>
                <div class="staff-section-eyebrow">Trayectoria institucional</div>
                <h5>Datos laborales</h5>
                <p>Cargo, contrato, jornada y antecedentes profesionales.</p>
              </div>
            </div>
          <div class="row g-3">
            <div class="col-md-6">
              <StaffFieldLabel label="Cargo" />
              <Multiselect
                v-model="form.cargo_id"
                class="staff-multiselect"
                :options="cargoOptions"
                :searchable="true"
                placeholder="Seleccionar cargo"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-6">
              <StaffFieldLabel label="Tipo de contrato" />
              <Multiselect
                v-model="form.contract_type"
                class="staff-multiselect"
                :options="contractTypeOptions"
                :searchable="true"
                placeholder="Seleccionar tipo de contrato"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Fecha de ingreso" />
              <BFormInput v-model="form.start_date" type="date" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Fecha de término" />
              <BFormInput
                v-model="form.end_date"
                type="date"
                :disabled="!canEdit || isIndefiniteContract"
                :placeholder="isIndefiniteContract ? 'No aplica para contrato indefinido' : ''"
              />
              <div v-if="isIndefiniteContract" class="small text-muted mt-1">
                No se solicita fecha de término para contratos indefinidos.
              </div>
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Jornada" />
              <Multiselect
                v-model="form.workday"
                class="staff-multiselect"
                :options="workdayOptions"
                :searchable="true"
                placeholder="Seleccionar jornada"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Horas de contrato" />
              <BFormInput
                v-model="form.contract_hours"
                type="number"
                min="0"
                step="0.01"
                placeholder="44"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Título profesional" />
              <BFormInput v-model="form.professional_title" placeholder="Título o grado" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <StaffFieldLabel label="Especialidad" />
              <BFormInput v-model="form.specialty" placeholder="Especialidad" :disabled="!canEdit" />
            </div>
            <div :class="isNew ? 'col-12' : 'col-md-6'">
              <StaffFieldLabel label="Registro profesional" />
              <BFormInput
                v-model="form.professional_registration"
                placeholder="Número de registro"
                :disabled="!canEdit"
              />
            </div>
            <div v-if="!isNew" class="col-md-6">
              <StaffFieldLabel label="Usuario asociado" />
              <Multiselect
                v-model="form.associated_user_id"
                class="staff-multiselect"
                :options="userOptions"
                :searchable="true"
                placeholder="Seleccionar cuenta de acceso"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-12">
              <StaffFieldLabel label="Observaciones internas" />
              <BFormTextarea v-model="form.internal_notes" rows="3" :disabled="!canEdit" />
            </div>
          </div>
          </BCard>

          <BCard class="staff-form-card staff-section-card mt-4">
            <div class="staff-section-heading">
              <div class="staff-section-icon staff-section-icon--cyan">
                <i class="mdi mdi-account-group-outline"></i>
              </div>
              <div>
                <div class="staff-section-eyebrow">Organización</div>
                <h5>Equipos y responsabilidades</h5>
                <p>La pertenencia a un equipo es independiente de estar a cargo de un departamento.</p>
              </div>
            </div>
          <StaffFieldLabel label="Pertenencia a departamentos" />
          <Multiselect
            v-model="form.department_ids"
            class="staff-multiselect"
            :options="departmentOptions"
            mode="multiple"
            :close-on-select="false"
            :searchable="true"
            placeholder="Seleccionar uno o más equipos"
            :disabled="!canEdit"
          />
          <div class="small text-muted mt-2">
            Esto define los equipos que integra. Ser encargado de un departamento se configura por separado
            en el administrador de departamentos.
          </div>
          <div v-if="staff" class="staff-responsibility-summary mt-3">
            <div class="d-flex align-items-center justify-content-between gap-2">
              <div>
                <span class="text-muted small">Departamentos a cargo</span>
                <div class="fw-semibold">
                  {{ (staff.managed_departments || []).map((department) => department.name).join(", ") || "Ninguno" }}
                </div>
              </div>
              <router-link to="/staff/departments" class="btn btn-sm btn-outline-primary">
                Administrar
              </router-link>
            </div>
          </div>
        </BCard>
      </div>

      <div v-if="staff" class="col-12">
        <BCard class="staff-form-card staff-summary-card">
          <div class="staff-section-heading">
            <div class="staff-section-icon staff-section-icon--green">
              <i class="mdi mdi-office-building-outline"></i>
            </div>
            <div>
              <div class="staff-section-eyebrow">Vista consolidada</div>
              <h5>Resumen institucional</h5>
              <p>Información clave para consulta rápida, sin necesidad de editar la ficha.</p>
            </div>
          </div>
          <div class="staff-summary-grid">
            <div class="staff-summary-item">
              <i class="mdi mdi-card-account-details-outline"></i>
              <span>RUT</span>
              <strong>{{ staff.rut || "-" }}</strong>
            </div>
            <div class="staff-summary-item">
              <i class="mdi mdi-calendar-check-outline"></i>
              <span>Ingreso</span>
              <strong>{{ formatDate(staff.start_date) }}</strong>
            </div>
            <div class="staff-summary-item">
              <i class="mdi mdi-file-document-outline"></i>
              <span>Contrato</span>
              <strong>{{ contractTypeOptions.find((item) => item.value === staff.contract_type)?.label || "-" }}</strong>
            </div>
            <div class="staff-summary-item">
              <i class="mdi mdi-account-key-outline"></i>
              <span>Usuario asociado</span>
              <strong>{{ staff.user?.email || "-" }}</strong>
            </div>
            <div class="staff-summary-item">
              <i class="mdi mdi-tools"></i>
              <span>Mantención</span>
              <strong>
                {{ staff.can_receive_maintenance_orders ? (staff.maintenance_role_label || "Responsable OT") : "No recibe OT" }}
              </strong>
            </div>
            <div class="staff-summary-item">
              <i class="mdi mdi-map-marker-outline"></i>
              <span>Ubicación</span>
              <strong>
                {{ [staff.commune_record?.name || staff.commune, staff.region_record?.short_name || staff.region_record?.name || staff.region].filter(Boolean).join(", ") || "-" }}
              </strong>
            </div>
            <div class="staff-summary-item staff-summary-item--wide">
              <i class="mdi mdi-account-group-outline"></i>
              <span>Equipos a los que pertenece</span>
              <strong>{{ (staff.departments || []).map((department) => department.name).join(", ") || "-" }}</strong>
            </div>
            <div class="staff-summary-item staff-summary-item--wide">
              <i class="mdi mdi-account-tie-outline"></i>
              <span>Departamentos a cargo</span>
              <strong>{{ (staff.managed_departments || []).map((department) => department.name).join(", ") || "-" }}</strong>
            </div>
          </div>
        </BCard>
      </div>

      <div v-if="staff" class="col-12">
        <BCard title="Historial de reservas" class="staff-form-card staff-history-card">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted">Reservas realizadas por este funcionario.</div>
            <router-link to="/spaces/reservations" class="btn btn-sm btn-outline-primary">
              Ir a reservas
            </router-link>
          </div>

          <div v-if="(staff.dependency_reservations || []).length === 0" class="text-muted">
            Este funcionario no registra reservas.
          </div>
          <div v-else class="table-responsive">
            <BTable
              :items="staff.dependency_reservations"
              :fields="[
                { key: 'title', label: 'Reserva' },
                { key: 'dependency', label: 'Dependencia' },
                { key: 'start_date', label: 'Inicio' },
                { key: 'status', label: 'Estado' },
              ]"
              small
            >
              <template #cell(dependency)="{ item }">
                {{ item.dependency?.name || "-" }}
              </template>
              <template #cell(start_date)="{ item }">
                {{ formatDate(item.start_date) }} {{ item.start_time }}
              </template>
              <template #cell(status)="{ item }">
                <span :class="`badge rounded-pill badge-soft-${reservationStatusVariant(item.status)}`">
                  {{ item.status }}
                </span>
              </template>
            </BTable>
          </div>
        </BCard>
      </div>

      <div v-if="staff" class="col-12">
        <BCard title="Historial de contratos" class="staff-form-card staff-history-card">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted">Contratos generados para este funcionario.</div>
            <router-link
              v-if="canManageContracts"
              :to="`/contracts/new?staff_id=${staff.id}`"
              class="btn btn-sm btn-primary"
            >
              Nuevo contrato
            </router-link>
          </div>

          <div v-if="!canViewContracts" class="text-muted">
            No tienes permisos para visualizar contratos.
          </div>
          <div v-else-if="(staff.contracts || []).length === 0" class="text-muted">
            Este funcionario no tiene contratos registrados.
          </div>
          <div v-else class="table-responsive">
            <BTable
              :items="staff.contracts"
              :fields="[
                { key: 'template', label: 'Plantilla' },
                { key: 'contract_type', label: 'Tipo' },
                { key: 'start_date', label: 'Inicio' },
                { key: 'status', label: 'Estado' },
                { key: 'actions', label: 'Acciones' },
              ]"
              small
            >
              <template #cell(template)="{ item }">
                {{ item.template?.name || "-" }}
              </template>
              <template #cell(contract_type)="{ item }">
                {{ contractTypeOptions.find((option) => option.value === item.contract_type)?.label || "-" }}
              </template>
              <template #cell(start_date)="{ item }">
                {{ formatDate(item.start_date) }}
              </template>
              <template #cell(status)="{ item }">
                <span :class="`badge rounded-pill badge-soft-${contractStatusVariant(item.status)}`">
                  {{ contractStatusLabel(item.status) }}
                </span>
              </template>
              <template #cell(actions)="{ item }">
                <div class="d-flex gap-2">
                  <router-link :to="`/contracts/${item.id}`" class="btn btn-sm btn-outline-primary">
                    Ver
                  </router-link>
                  <a
                    v-if="item.exported_word_url"
                    class="btn btn-sm btn-outline-secondary"
                    :href="item.exported_word_url"
                    download
                  >
                    Word
                  </a>
                </div>
              </template>
            </BTable>
          </div>
        </BCard>
      </div>

      <div v-if="staff && (canViewPermissionModule || canManagePermissionWatchers)" class="col-12">
        <BCard title="Permisos" class="staff-form-card staff-history-card">
          <div v-if="canViewPermissionModule" class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-muted">Historial, estados y uso anual de permisos del funcionario.</div>
            <router-link :to="`/staff/permissions/reports?staff_id=${staff.id}`" class="btn btn-sm btn-outline-primary">
              Ver reportes
            </router-link>
          </div>

          <div v-if="canViewPermissionModule && !permissionSummary" class="text-muted">
            Sin información disponible.
          </div>
          <div v-else-if="canViewPermissionModule">
            <div class="row g-3 mb-3">
              <div class="col-md-2">
                <div class="text-muted small">Total</div>
                <div class="fw-semibold">{{ permissionSummary.summary?.total ?? 0 }}</div>
              </div>
              <div class="col-md-2">
                <div class="text-muted small">Aprobados</div>
                <div class="fw-semibold">{{ permissionSummary.summary?.aprobados ?? 0 }}</div>
              </div>
              <div class="col-md-2">
                <div class="text-muted small">Rechazados</div>
                <div class="fw-semibold">{{ permissionSummary.summary?.rechazados ?? 0 }}</div>
              </div>
              <div class="col-md-2">
                <div class="text-muted small">Pendientes</div>
                <div class="fw-semibold">{{ permissionSummary.summary?.pendientes ?? 0 }}</div>
              </div>
              <div class="col-md-2">
                <div class="text-muted small">Con goce</div>
                <div class="fw-semibold">{{ permissionSummary.summary?.con_goce ?? 0 }}</div>
              </div>
              <div class="col-md-2">
                <div class="text-muted small">Sin goce</div>
                <div class="fw-semibold">{{ permissionSummary.summary?.sin_goce ?? 0 }}</div>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-lg-6">
                <h6 class="mb-2">Historial anual por tipo</h6>
                <div v-if="!(permissionSummary.annual_by_type || []).length" class="text-muted">Sin uso anual registrado.</div>
                <div v-else class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Tipo</th>
                        <th>Días</th>
                        <th>Horas</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in permissionSummary.annual_by_type" :key="item.permission_type_id">
                        <td>{{ item.permission_type?.name || "-" }}</td>
                        <td>{{ item.used_days ?? 0 }}</td>
                        <td>{{ item.used_hours ?? 0 }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="col-lg-6">
                <h6 class="mb-2">Solicitudes recientes</h6>
                <div v-if="!(permissionSummary.recent || []).length" class="text-muted">Sin solicitudes registradas.</div>
                <div v-else class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Estado</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in permissionSummary.recent" :key="item.id">
                        <td>{{ item.permission_type?.name || "-" }}</td>
                        <td>{{ formatDate(item.start_date) }}</td>
                        <td>{{ item.status }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div v-if="canManagePermissionWatchers" class="border-top pt-3 mt-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h6 class="mb-1">Quiénes deben enterarse de este funcionario</h6>
                <div class="text-muted small">Se suman a los destinatarios configurados por tipo de permiso.</div>
              </div>
              <BButton variant="success" size="sm" :disabled="savingPermissionWatchers" @click="savePermissionWatchers">
                {{ savingPermissionWatchers ? "Guardando..." : "Guardar destinatarios" }}
              </BButton>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-lg-3">
                <label class="form-label">Tipo</label>
                <BFormSelect v-model="permissionWatcherForm.target_type" :options="permissionWatcherTargetOptions" />
              </div>
              <div v-if="permissionWatcherForm.target_type === 'role'" class="col-lg-3">
                <label class="form-label">Rol</label>
                <BFormSelect v-model="permissionWatcherForm.role_id" :options="permissionWatcherRoleOptions" />
              </div>
              <div v-if="permissionWatcherForm.target_type === 'user'" class="col-lg-4">
                <label class="form-label">Usuario</label>
                <BFormSelect v-model="permissionWatcherForm.user_id" :options="permissionWatcherUserOptions" />
              </div>
              <div class="col-lg-2 d-flex align-items-end">
                <BFormCheckbox v-model="permissionWatcherForm.notify">Avisar</BFormCheckbox>
              </div>
              <div class="col-lg-2 d-flex align-items-end">
                <BFormCheckbox v-model="permissionWatcherForm.can_view">Puede ver</BFormCheckbox>
              </div>
              <div class="col-lg-2 d-flex align-items-end">
                <BFormCheckbox v-model="permissionWatcherForm.active">Activo</BFormCheckbox>
              </div>
              <div class="col-lg-2 d-flex align-items-end gap-2">
                <BButton variant="outline-primary" size="sm" @click="addPermissionWatcher">Agregar</BButton>
                <BButton variant="outline-secondary" size="sm" @click="resetPermissionWatcherForm">Limpiar</BButton>
              </div>
            </div>

            <div v-if="!permissionWatchers.length" class="text-muted">Sin destinatarios específicos configurados.</div>
            <div v-else class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Destinatario</th>
                    <th>Aviso</th>
                    <th>Puede ver</th>
                    <th>Activo</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in permissionWatchers" :key="`${item.target_type}-${item.role_id || 0}-${item.user_id || 0}-${index}`">
                    <td>{{ permissionWatcherLabel(item) }}</td>
                    <td><BFormCheckbox v-model="item.notify" switch /></td>
                    <td><BFormCheckbox v-model="item.can_view" switch /></td>
                    <td><BFormCheckbox v-model="item.active" switch /></td>
                    <td class="text-end">
                      <BButton size="sm" variant="outline-danger" @click="removePermissionWatcher(index)">Quitar</BButton>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </BCard>
      </div>

      <div v-if="staff" class="col-12">
        <BCard title="Documentos adjuntos" class="staff-form-card staff-history-card">
          <div v-if="canManageDocuments" class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">Archivo</label>
              <input class="form-control" type="file" @change="onNewDoc" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Tipo</label>
              <BFormSelect v-model="newDocType" :options="documentTypes" />
            </div>
            <div class="col-md-5">
              <label class="form-label">Observaciones</label>
              <BFormInput v-model="newDocObs" />
            </div>
            <div class="col-12">
              <div v-if="docError" class="text-danger small mb-2">{{ docError }}</div>
              <BButton variant="primary" :disabled="uploadingDoc" @click="uploadDocument">
                {{ uploadingDoc ? "Subiendo..." : "Subir documento" }}
              </BButton>
            </div>
          </div>

          <div v-if="(staff.documents || []).length === 0" class="text-muted">
            Sin documentos cargados.
          </div>
          <div v-else class="table-responsive">
            <BTable
              :items="staff.documents"
              :fields="[
                { key: 'document_type', label: 'Tipo' },
                { key: 'original_name', label: 'Archivo' },
                { key: 'created_at', label: 'Fecha' },
                { key: 'actions', label: 'Acciones' },
              ]"
              small
            >
              <template #cell(created_at)="{ item }">
                {{ formatDateTime(item.created_at) }}
              </template>
              <template #cell(actions)="{ item }">
                <div class="d-flex gap-2">
                  <a class="btn btn-sm btn-outline-secondary" :href="item.file_url" target="_blank" rel="noreferrer">
                    Ver
                  </a>
                  <BButton
                    v-if="canManageDocuments"
                    size="sm"
                    variant="outline-danger"
                    @click="deleteDocument(item)"
                  >
                    Eliminar
                  </BButton>
                </div>
              </template>
            </BTable>
          </div>
        </BCard>
      </div>
    </div>
    </div>
  </Layout>
</template>

<style scoped>
.staff-form-hero {
  background: #fff;
  border: 1px solid #e6ebf5;
  border-radius: 8px;
  box-shadow: 0 12px 28px rgba(42, 48, 66, 0.06);
  padding: 1.25rem;
}

.staff-form-kicker {
  color: #556ee6;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0;
  margin-bottom: 0.35rem;
  text-transform: uppercase;
}

.staff-form-actions .btn {
  align-items: center;
  border-radius: 8px;
  display: inline-flex;
  font-weight: 600;
  min-height: 2.6rem;
}

.staff-key-fields {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.staff-key-chip {
  align-items: center;
  background: #f6f8fc;
  border: 1px solid #e3e8f4;
  border-radius: 999px;
  color: #6b7280;
  display: inline-flex;
  font-size: 0.82rem;
  font-weight: 600;
  gap: 0.35rem;
  min-height: 2rem;
  padding: 0.35rem 0.75rem;
}

.staff-key-chip.is-complete {
  background: #e8f7ef;
  border-color: #c9ecd8;
  color: #16855b;
}

.staff-nullable-note {
  align-items: center;
  background: linear-gradient(135deg, rgba(85, 110, 230, 0.08), rgba(52, 195, 143, 0.06));
  border: 1px solid rgba(85, 110, 230, 0.14);
  border-radius: 10px;
  color: #586174;
  display: flex;
  font-size: 0.84rem;
  gap: 0.6rem;
  padding: 0.7rem 0.85rem;
}

.staff-nullable-note i {
  color: #556ee6;
  font-size: 1.15rem;
}

.staff-form-card {
  border: 1px solid #e6ebf5;
  border-radius: 8px;
  box-shadow: 0 10px 26px rgba(42, 48, 66, 0.05);
}

:deep(.staff-form-card > .card-body) {
  padding: 1.25rem;
}

:deep(.staff-form-card .card-title) {
  color: #2a3042;
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 1rem;
}

:deep(.staff-form-card .form-label) {
  color: #4b5563;
  font-size: 0.86rem;
  font-weight: 700;
  margin-bottom: 0.45rem;
}

:deep(.staff-form-card .form-control),
:deep(.staff-form-card .form-select) {
  border-color: #dfe6f5;
  border-radius: 8px;
  min-height: 2.65rem;
}

:deep(.staff-form-card .form-control:focus),
:deep(.staff-form-card .form-select:focus) {
  border-color: #8ea0ff;
  box-shadow: 0 0 0 0.18rem rgba(85, 110, 230, 0.12);
}

.staff-loading {
  align-items: center;
  color: #55606f;
  display: flex;
  font-weight: 600;
  gap: 0.65rem;
}

.staff-profile-panel {
  align-items: center;
  background: #f8fafc;
  border: 1px solid #edf1f7;
  border-radius: 8px;
  display: flex;
  gap: 0.9rem;
  margin-bottom: 1rem;
  padding: 0.85rem;
}

.staff-photo-frame {
  align-items: center;
  background: #fff;
  border: 1px solid #dfe6f5;
  border-radius: 8px;
  display: flex;
  flex: 0 0 4.5rem;
  height: 4.5rem;
  justify-content: center;
  overflow: hidden;
  width: 4.5rem;
}

.staff-photo-frame img {
  height: 100%;
  object-fit: cover;
  width: 100%;
}

.staff-photo-empty {
  align-items: center;
  color: #6b7280;
  display: flex;
  font-size: 2rem;
  height: 100%;
  justify-content: center;
  width: 100%;
}

.staff-profile-name {
  color: #2a3042;
  font-size: 1.05rem;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.staff-profile-meta {
  color: #74788d;
  font-size: 0.9rem;
  margin-top: 0.15rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.staff-completion {
  color: #4b5563;
  font-size: 0.88rem;
  margin-bottom: 1.25rem;
}

.staff-progress {
  background: #edf1f7;
  border-radius: 999px;
  height: 0.55rem;
}

.staff-progress .progress-bar {
  background: #34c38f;
  border-radius: 999px;
}

.staff-status-pill {
  background: #e8f7ef;
  border-radius: 999px;
  color: #16855b;
  display: inline-flex;
  font-size: 0.82rem;
  font-weight: 700;
  padding: 0.25rem 0.7rem;
}

.staff-responsibility-summary {
  padding: 0.85rem;
  border: 1px solid #dce5ff;
  border-radius: 10px;
  background: #f7f9ff;
}

.staff-required-dot {
  color: #f46a6a;
  margin-left: 0.15rem;
}

.min-w-0 {
  min-width: 0;
}

:deep(.staff-multiselect) {
  --ms-radius: 8px;
  --ms-border-color: #dfe6f5;
  --ms-bg: #fff;
  --ms-font-size: 0.95rem;
  width: 100%;
}

:deep(.staff-multiselect .multiselect-wrapper) {
  min-height: 2.65rem;
}

:deep(.staff-multiselect .multiselect-placeholder),
:deep(.staff-multiselect .multiselect-single-label) {
  color: #465161;
  font-weight: 500;
}

:deep(.staff-multiselect .multiselect-dropdown) {
  z-index: 3000;
  border-color: #dfe6f5;
  box-shadow: 0 0.75rem 1.75rem rgba(31, 41, 55, 0.14);
}

:deep(.staff-multiselect .multiselect-option) {
  color: #364154;
  font-weight: 500;
}

:deep(.staff-role-multiselect) {
  --ms-radius: 8px;
  --ms-border-color: #dfe6f5;
  --ms-bg: #fff;
  --ms-font-size: 0.95rem;
  width: 100%;
}

:deep(.staff-role-multiselect .multiselect-wrapper) {
  min-height: 2.75rem;
}

:deep(.staff-role-multiselect .multiselect-placeholder),
:deep(.staff-role-multiselect .multiselect-single-label) {
  color: #465161;
  font-weight: 500;
}

:deep(.staff-role-multiselect .multiselect-dropdown) {
  z-index: 3000;
  border-color: #dfe6f5;
  box-shadow: 0 0.75rem 1.75rem rgba(31, 41, 55, 0.14);
}

:deep(.staff-role-multiselect .multiselect-option) {
  color: #364154;
  font-weight: 500;
}

.staff-form-page {
  --staff-border: #e2e8f4;
  --staff-ink: #202638;
  --staff-muted: #6f7890;
  --staff-primary: #556ee6;
  --staff-primary-soft: #eef1ff;
  margin: 0 auto;
  max-width: 1480px;
}

.staff-form-hero {
  align-items: center;
  background:
    radial-gradient(circle at 76% -20%, rgba(111, 134, 246, 0.22), transparent 36%),
    linear-gradient(135deg, #ffffff 0%, #f8faff 60%, #f4f8ff 100%);
  border: 1px solid rgba(206, 216, 239, 0.86);
  border-radius: 20px;
  box-shadow: 0 18px 42px rgba(45, 57, 94, 0.09);
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  justify-content: space-between;
  min-height: 142px;
  overflow: hidden;
  padding: 1.45rem 1.55rem;
  position: relative;
}

.staff-form-hero__glow {
  border-radius: 999px;
  pointer-events: none;
  position: absolute;
}

.staff-form-hero__glow--top {
  background: rgba(85, 110, 230, 0.09);
  height: 210px;
  right: -70px;
  top: -130px;
  width: 210px;
}

.staff-form-hero__glow--bottom {
  background: rgba(52, 195, 143, 0.07);
  bottom: -125px;
  height: 180px;
  left: 40%;
  width: 180px;
}

.staff-form-hero__content {
  align-items: center;
  display: flex;
  flex: 1 1 340px;
  gap: 1rem;
  min-width: 0;
  position: relative;
  z-index: 1;
}

.staff-hero-avatar {
  align-items: center;
  background: linear-gradient(145deg, #5b71e8, #7286ed);
  border: 4px solid rgba(255, 255, 255, 0.92);
  border-radius: 18px;
  box-shadow: 0 12px 25px rgba(85, 110, 230, 0.22);
  color: #fff;
  display: flex;
  flex: 0 0 4.7rem;
  font-size: 1.35rem;
  font-weight: 800;
  height: 4.7rem;
  justify-content: center;
  letter-spacing: 0.02em;
  overflow: hidden;
}

.staff-hero-avatar img {
  height: 100%;
  object-fit: cover;
  width: 100%;
}

.staff-form-kicker {
  color: var(--staff-primary);
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.09em;
  margin-bottom: 0.3rem;
  text-transform: uppercase;
}

.staff-form-title {
  color: var(--staff-ink);
  font-size: clamp(1.35rem, 2vw, 1.75rem);
  font-weight: 750;
  letter-spacing: -0.025em;
  margin: 0;
  white-space: nowrap;
}

.staff-form-subtitle {
  color: var(--staff-muted);
  font-size: 0.9rem;
  margin-top: 0.25rem;
}

.staff-hero-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-top: 0.8rem;
}

.staff-hero-tag {
  align-items: center;
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid #dfe5f3;
  border-radius: 999px;
  color: #576074;
  display: inline-flex;
  font-size: 0.73rem;
  font-weight: 700;
  gap: 0.35rem;
  max-width: 260px;
  min-height: 1.85rem;
  overflow: hidden;
  padding: 0.3rem 0.65rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.staff-hero-tag i {
  color: var(--staff-primary);
  font-size: 0.95rem;
}

.staff-hero-tag--success {
  background: #eaf8f2;
  border-color: #ccebdd;
  color: #197a58;
}

.staff-hero-tag--success i {
  color: #20a675;
}

.staff-form-actions {
  flex: 0 1 auto;
  position: relative;
  z-index: 1;
}

.staff-form-actions .btn {
  border-radius: 11px;
  justify-content: center;
  min-height: 2.85rem;
  padding-inline: 1rem;
}

.staff-form-actions .btn-primary {
  background: linear-gradient(135deg, #556ee6, #7185ea);
  border-color: transparent;
  box-shadow: 0 10px 20px rgba(85, 110, 230, 0.22);
}

.staff-hero-new-state {
  border-top: 1px solid rgba(215, 222, 239, 0.9);
  display: grid;
  flex: 1 0 100%;
  gap: 0.75rem;
  grid-column: 1 / -1;
  margin-top: 1.2rem;
  padding-top: 1rem;
  position: relative;
  width: 100%;
  z-index: 1;
}

.staff-form-alert {
  border: 0;
  border-radius: 14px;
  box-shadow: 0 10px 26px rgba(42, 48, 66, 0.07);
}

.staff-form-card {
  border: 1px solid var(--staff-border);
  border-radius: 18px;
  box-shadow: 0 12px 34px rgba(37, 48, 82, 0.065);
  overflow: visible;
}

:deep(.staff-form-card > .card-body) {
  padding: 1.35rem;
}

.staff-section-card {
  background: rgba(255, 255, 255, 0.97);
}

.staff-section-heading {
  align-items: flex-start;
  display: flex;
  gap: 0.8rem;
  margin-bottom: 1.25rem;
}

.staff-section-heading h5 {
  color: var(--staff-ink);
  font-size: 1.04rem;
  font-weight: 750;
  letter-spacing: -0.01em;
  margin: 0;
}

.staff-section-heading p {
  color: var(--staff-muted);
  font-size: 0.8rem;
  line-height: 1.45;
  margin: 0.22rem 0 0;
}

.staff-section-eyebrow {
  color: #8a94a8;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.075em;
  margin-bottom: 0.15rem;
  text-transform: uppercase;
}

.staff-section-icon {
  align-items: center;
  background: #edf1ff;
  border-radius: 11px;
  color: #556ee6;
  display: flex;
  flex: 0 0 2.65rem;
  font-size: 1.25rem;
  height: 2.65rem;
  justify-content: center;
}

.staff-section-icon--violet {
  background: #f2edff;
  color: #7a59d7;
}

.staff-section-icon--amber {
  background: #fff5e4;
  color: #c78016;
}

.staff-section-icon--cyan {
  background: #e9f8fb;
  color: #208ca2;
}

.staff-section-icon--green {
  background: #eaf8f2;
  color: #1c966b;
}

.staff-sticky-column {
  position: sticky;
  top: 88px;
}

.staff-state-card {
  background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
}

.staff-completion {
  background: #f7f9fd;
  border: 1px solid #e7ecf6;
  border-radius: 14px;
  margin-bottom: 1.25rem;
  padding: 0.9rem;
}

.staff-completion__topline {
  align-items: center;
  display: flex;
  gap: 0.75rem;
  justify-content: space-between;
  margin-bottom: 0.7rem;
}

.staff-completion__topline span,
.staff-completion__topline strong {
  display: block;
}

.staff-completion__topline span {
  color: #8992a5;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.staff-completion__topline strong {
  color: #465064;
  font-size: 0.78rem;
  margin-top: 0.15rem;
}

.staff-completion__value {
  color: #1d8d66;
  font-size: 1.05rem;
  font-weight: 800;
}

.staff-progress {
  background: #e3e9f4;
  height: 0.48rem;
}

.staff-progress .progress-bar {
  background: linear-gradient(90deg, #27b884, #52c9a0);
}

.staff-control-group + .staff-control-group {
  margin-top: 1rem;
}

.staff-field-help {
  color: #8992a5;
  font-size: 0.7rem;
  line-height: 1.4;
  margin-top: 0.4rem;
}

.staff-file-control {
  align-items: center;
  display: flex;
  position: relative;
}

.staff-file-control .form-control {
  color: transparent;
  overflow: hidden;
}

.staff-file-control .form-control::file-selector-button {
  color: #465064;
}

.staff-field-help strong {
  color: #697389;
  font-weight: 700;
}

.staff-toggle-list {
  display: grid;
  gap: 0.65rem;
  margin-top: 1.1rem;
}

.staff-toggle-card {
  align-items: center;
  background: #f9fafe;
  border: 1px solid #e7ebf4;
  border-radius: 13px;
  display: grid;
  gap: 0.65rem;
  grid-template-columns: auto minmax(0, 1fr) auto;
  padding: 0.75rem;
}

.staff-toggle-card__icon {
  align-items: center;
  background: #edf1ff;
  border-radius: 10px;
  color: #556ee6;
  display: flex;
  font-size: 1.05rem;
  height: 2.25rem;
  justify-content: center;
  width: 2.25rem;
}

.staff-toggle-card__icon--success {
  background: #eaf8f2;
  color: #1e986d;
}

.staff-toggle-card__copy {
  min-width: 0;
}

.staff-toggle-card__copy strong,
.staff-toggle-card__copy small {
  display: block;
}

.staff-toggle-card__copy strong {
  color: #394257;
  font-size: 0.78rem;
}

.staff-toggle-card__copy small {
  color: #818ba0;
  font-size: 0.67rem;
  line-height: 1.35;
  margin-top: 0.12rem;
}

:deep(.staff-toggle-card .form-check) {
  margin: 0;
  min-height: auto;
  padding-left: 2.25rem;
}

.staff-record-meta {
  border-top: 1px solid #e8ecf4;
  display: grid;
  gap: 0.7rem;
  margin-top: 1.2rem;
  padding-top: 1rem;
}

.staff-record-meta span,
.staff-record-meta strong {
  display: block;
}

.staff-record-meta span {
  color: #9098a9;
  font-size: 0.66rem;
  font-weight: 750;
  letter-spacing: 0.045em;
  text-transform: uppercase;
}

.staff-record-meta strong {
  color: #4c566b;
  font-size: 0.76rem;
  margin-top: 0.15rem;
  overflow-wrap: anywhere;
}

:deep(.staff-form-card .form-label) {
  color: #3d4659;
  font-size: 0.79rem;
  font-weight: 750;
  margin-bottom: 0.5rem;
}

:deep(.staff-form-card .form-control),
:deep(.staff-form-card .form-select) {
  background-color: #fbfcff;
  border: 1px solid #dce3f0;
  border-radius: 11px;
  color: #30394d;
  font-size: 0.86rem;
  min-height: 2.9rem;
  padding-inline: 0.85rem;
  transition: border-color 0.16s ease, box-shadow 0.16s ease, background-color 0.16s ease;
}

:deep(.staff-form-card textarea.form-control) {
  min-height: 6.6rem;
  padding-block: 0.75rem;
  resize: vertical;
}

:deep(.staff-form-card .form-control::placeholder) {
  color: #a2aabd;
  opacity: 1;
}

:deep(.staff-form-card .form-control:hover:not(:disabled)),
:deep(.staff-form-card .form-select:hover:not(:disabled)) {
  border-color: #bdc8df;
}

:deep(.staff-form-card .form-control:focus),
:deep(.staff-form-card .form-select:focus) {
  background: #fff;
  border-color: #7185ea;
  box-shadow: 0 0 0 0.2rem rgba(85, 110, 230, 0.13);
}

:deep(.staff-form-card .form-control:disabled),
:deep(.staff-form-card .form-select:disabled) {
  background: #f1f3f7;
  color: #7e8799;
  opacity: 1;
}

:deep(.staff-multiselect),
:deep(.staff-role-multiselect) {
  --ms-bg: #fbfcff;
  --ms-border-color: #dce3f0;
  --ms-border-color-active: #7185ea;
  --ms-border-width: 1px;
  --ms-font-size: 0.86rem;
  --ms-option-bg-selected: #556ee6;
  --ms-option-bg-selected-pointed: #4f64d1;
  --ms-option-color-selected: #fff;
  --ms-option-color-selected-pointed: #fff;
  --ms-option-bg-pointed: #eef1ff;
  --ms-option-color-pointed: #3446a7;
  --ms-placeholder-color: #a2aabd;
  --ms-radius: 11px;
  --ms-ring-color: rgba(85, 110, 230, 0.13);
  --ms-ring-width: 3px;
  min-height: 2.9rem;
}

:deep(.staff-multiselect .multiselect-wrapper),
:deep(.staff-role-multiselect .multiselect-wrapper) {
  min-height: 2.85rem;
  padding-inline: 0.15rem;
}

:deep(.staff-multiselect .multiselect-single-label),
:deep(.staff-role-multiselect .multiselect-single-label) {
  color: #30394d;
  font-weight: 550;
  max-width: calc(100% - 3rem);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:deep(.staff-multiselect .multiselect-placeholder),
:deep(.staff-role-multiselect .multiselect-placeholder) {
  color: #a2aabd;
  font-weight: 500;
}

:deep(.staff-multiselect .multiselect-caret),
:deep(.staff-role-multiselect .multiselect-caret) {
  background-color: #758096;
  margin-right: 0.2rem;
}

:deep(.staff-multiselect .multiselect-dropdown),
:deep(.staff-role-multiselect .multiselect-dropdown) {
  border: 1px solid #dce3f0;
  border-radius: 12px;
  box-shadow: 0 18px 42px rgba(32, 42, 70, 0.17);
  margin-top: 0.38rem;
  max-height: 18rem;
  overflow: auto;
  z-index: 5000;
}

:deep(.staff-multiselect .multiselect-option),
:deep(.staff-role-multiselect .multiselect-option) {
  border-radius: 8px;
  color: #354056;
  font-size: 0.82rem;
  line-height: 1.35;
  margin: 0.18rem 0.3rem;
  min-height: 2.45rem;
  padding: 0.55rem 0.7rem;
}

:deep(.staff-multiselect.is-disabled),
:deep(.staff-role-multiselect.is-disabled) {
  --ms-bg: #f1f3f7;
  --ms-border-color: #e0e4ec;
  opacity: 1;
}

:deep(.staff-multiselect .multiselect-tags) {
  gap: 0.3rem;
  margin: 0.25rem 0;
}

:deep(.staff-multiselect .multiselect-tag) {
  background: #e9edff;
  border-radius: 999px;
  color: #4358c6;
  font-size: 0.7rem;
  font-weight: 700;
}

.staff-responsibility-summary {
  background: linear-gradient(135deg, #f7f9ff, #fbfcff);
  border-color: #dce5ff;
  border-radius: 13px;
  padding: 0.9rem;
}

.staff-summary-card {
  background: linear-gradient(135deg, #ffffff, #fbfcff);
}

.staff-summary-grid {
  display: grid;
  gap: 0.7rem;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.staff-summary-item {
  background: #f8fafd;
  border: 1px solid #e7ebf3;
  border-radius: 13px;
  min-width: 0;
  padding: 0.85rem;
}

.staff-summary-item > i {
  color: #667bec;
  display: block;
  font-size: 1.15rem;
  margin-bottom: 0.5rem;
}

.staff-summary-item span,
.staff-summary-item strong {
  display: block;
}

.staff-summary-item span {
  color: #8b94a7;
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.055em;
  text-transform: uppercase;
}

.staff-summary-item strong {
  color: #3d4659;
  font-size: 0.79rem;
  line-height: 1.4;
  margin-top: 0.2rem;
  overflow-wrap: anywhere;
}

.staff-summary-item--wide {
  grid-column: span 2;
}

.staff-history-card {
  overflow: hidden;
}

:deep(.staff-history-card .card-title) {
  border-bottom: 1px solid #edf0f6;
  font-size: 1rem;
  margin: -0.15rem -0.05rem 1rem;
  padding-bottom: 0.9rem;
}

:deep(.staff-history-card .table) {
  --bs-table-bg: transparent;
  margin-bottom: 0;
}

:deep(.staff-history-card .table > thead > tr > th) {
  background: #f6f8fc;
  border-bottom: 0;
  color: #7a8499;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.055em;
  padding-block: 0.75rem;
  text-transform: uppercase;
}

:deep(.staff-history-card .table > tbody > tr > td) {
  border-color: #edf0f5;
  color: #4a5366;
  font-size: 0.8rem;
  padding-block: 0.78rem;
}

@media (max-width: 1399.98px) {
  .staff-form-hero {
    align-items: flex-start;
    flex-direction: column;
  }

  .staff-form-hero__content {
    flex: 0 1 auto;
    width: 100%;
  }

  .staff-form-actions {
    width: 100%;
  }

  .staff-sticky-column {
    position: static;
  }
}

@media (max-width: 991.98px) {
  .staff-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 575.98px) {
  .staff-form-hero {
    border-radius: 16px;
    padding: 1rem;
  }

  .staff-form-actions {
    width: 100%;
  }

  .staff-form-actions .btn {
    flex: 1 1 100%;
    justify-content: center;
  }

  .staff-form-hero__content {
    align-items: flex-start;
  }

  .staff-hero-avatar {
    border-radius: 14px;
    flex-basis: 3.65rem;
    font-size: 1.05rem;
    height: 3.65rem;
  }

  .staff-form-title {
    white-space: normal;
  }

  .staff-hero-tags {
    align-items: flex-start;
    flex-direction: column;
  }

  .staff-hero-tag {
    max-width: 100%;
  }

  .staff-summary-grid {
    grid-template-columns: 1fr;
  }

  .staff-summary-item--wide {
    grid-column: auto;
  }

  .staff-toggle-card {
    grid-template-columns: auto minmax(0, 1fr);
  }

  :deep(.staff-toggle-card .form-check) {
    grid-column: 2;
  }

  :deep(.staff-form-card > .card-body) {
    padding: 1rem;
  }
}
</style>
