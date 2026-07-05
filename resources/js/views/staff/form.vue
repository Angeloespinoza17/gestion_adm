<script>
import axios from "axios";
import Layout from "../../layouts/main.vue";
import Multiselect from "@vueform/multiselect";
import Swal from "sweetalert2";
import { getPdfMake } from "../../utils/pdfmake";

const maintenanceRoleCatalog = [
  { value: "encargado_mantencion", label: "Encargado/a de mantención" },
  { value: "auxiliar_mantenimiento", label: "Auxiliar de mantenimiento" },
  { value: "auxiliar_aseo", label: "Auxiliar de aseo" },
  { value: "apoyo_operativo", label: "Apoyo operativo" },
  { value: "otro", label: "Otro" },
];

const emptyForm = () => ({
  full_name: "",
  rut: "",
  birth_date: "",
  institutional_email: "",
  personal_email: "",
  phone: "",
  address: "",
  region_id: null,
  commune_id: null,
  cargo_id: null,
  contract_type: null,
  start_date: "",
  end_date: "",
  status: "activo",
  workday: null,
  contract_hours: "",
  professional_title: "",
  specialty: "",
  professional_registration: "",
  internal_notes: "",
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
  components: { Layout, Multiselect },
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
    "form.contract_type"(value) {
      if (value === "indefinido") {
        this.form.end_date = "";
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
            rut: this.staff.rut || "",
            birth_date: this.staff.birth_date || "",
            institutional_email: this.staff.institutional_email || "",
            personal_email: this.staff.personal_email || "",
            phone: this.staff.phone || "",
            address: this.staff.address || "",
            region_id: this.staff.region_id ?? this.staff.region_record?.id ?? null,
            commune_id: this.staff.commune_id ?? this.staff.commune_record?.id ?? null,
            cargo_id: this.staff.cargo_id ?? null,
            contract_type: this.staff.contract_type || null,
            start_date: this.staff.start_date || "",
            end_date: this.staff.end_date || "",
            status: this.staff.status || "activo",
            workday: this.staff.workday || null,
            contract_hours: this.staff.contract_hours || "",
            professional_title: this.staff.professional_title || "",
            specialty: this.staff.specialty || "",
            professional_registration: this.staff.professional_registration || "",
            internal_notes: this.staff.internal_notes || "",
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

      Object.entries(this.form).forEach(([key, value]) => {
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
    async save() {
      if (!this.canEdit) {
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
          this.$router.replace({ path: `/staff/${response.data.data.id}`, query: { created: 1 } });
          return;
        }

        payload.append("_method", "PUT");
        response = await axios.post(`/api/staff/${this.staff.id}`, payload);
        this.staff = response.data.data;
        this.success = "Funcionario actualizado correctamente.";
        this.showSuccessAlert("Cambios guardados", this.success);
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
        title: "Eliminar funcionario",
        text: `Se eliminará a ${this.staff.full_name}.`,
        confirmButtonText: "Sí, eliminar",
      });

      if (!result.isConfirmed) {
        return;
      }

      try {
        await axios.delete(`/api/staff/${this.staff.id}`);
        this.showSuccessAlert("Funcionario eliminado", "El funcionario fue eliminado correctamente.");
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
          ["Departamentos", (this.staff.departments || []).map((department) => department.name).join(", ") || "-"],
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
      });
    },
    showSuccessAlert(title, text) {
      return Swal.fire({
        title,
        text,
        icon: "success",
        timer: 1800,
        showConfirmButton: false,
      });
    },
    showErrorAlert(text) {
      return Swal.fire({
        title: "Error",
        text,
        icon: "error",
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
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h4 class="mb-0">{{ isNew ? "Nuevo funcionario" : "Ficha de funcionario" }}</h4>
        <div class="text-muted" v-if="staff">
          {{ staff.full_name }} · {{ staff.rut }}
        </div>
      </div>
      <div class="d-flex gap-2">
        <router-link to="/staff" class="btn btn-outline-secondary">Volver</router-link>
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
          Eliminar
        </BButton>
        <BButton v-if="canEdit" variant="primary" :disabled="saving" @click="save">
          {{ saving ? "Guardando..." : isNew ? "Crear funcionario" : "Guardar cambios" }}
        </BButton>
      </div>
    </div>

    <BAlert v-if="error" variant="danger" show class="mb-3">{{ error }}</BAlert>
    <BAlert v-if="success" variant="success" show class="mb-3">{{ success }}</BAlert>
    <BCard v-if="loading">Cargando...</BCard>

    <div v-else class="row g-3">
      <div class="col-lg-4">
        <BCard title="Foto y estado">
          <div v-if="currentPhotoUrl" class="mb-3 text-center">
            <img :src="currentPhotoUrl" class="img-fluid rounded border" alt="perfil" style="max-height: 280px" />
          </div>
          <div v-else class="text-muted mb-3">Sin foto de perfil.</div>

          <div class="mb-3">
            <label class="form-label">Foto de perfil</label>
            <input
              class="form-control"
              type="file"
              accept="image/*"
              :disabled="!canEdit"
              @change="onProfilePhoto"
            />
          </div>

          <div class="mb-3">
            <label class="form-label">Estado laboral</label>
            <Multiselect v-model="form.status" :options="statusOptions" :searchable="true" :disabled="!canEdit" />
          </div>

          <div class="mb-3">
            <BFormCheckbox v-model="form.active" :disabled="!canEdit">Registro activo</BFormCheckbox>
          </div>

          <div class="mb-3">
            <BFormCheckbox v-model="form.can_receive_maintenance_orders" :disabled="!canEdit">
              Puede recibir OT de mantención
            </BFormCheckbox>
            <div class="small text-muted mt-1">
              Activa esta opción para que aparezca en responsables de mantención.
            </div>
          </div>

          <div v-if="form.can_receive_maintenance_orders" class="mb-3">
            <label class="form-label">Rol operativo</label>
            <Multiselect
              v-model="form.maintenance_role"
              class="staff-role-multiselect"
              :options="maintenanceRoleOptions"
              :searchable="true"
              placeholder="Seleccionar rol operativo"
              :disabled="!canEdit"
            />
          </div>

          <div v-if="staff" class="small text-muted">
            <div>Creado: {{ formatDateTime(staff.created_at) }}</div>
            <div>Actualizado: {{ formatDateTime(staff.updated_at) }}</div>
            <div>Usuario sistema: {{ staff.user ? staff.user.email : "Sin asociación" }}</div>
          </div>
        </BCard>
      </div>

      <div class="col-lg-8">
        <BCard title="Datos personales">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Nombre completo</label>
              <BFormInput v-model="form.full_name" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">RUT</label>
              <BFormInput v-model="form.rut" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha de nacimiento</label>
              <BFormInput v-model="form.birth_date" type="date" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Correo institucional</label>
              <BFormInput v-model="form.institutional_email" type="email" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Correo personal</label>
              <BFormInput v-model="form.personal_email" type="email" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Teléfono</label>
              <BFormInput v-model="form.phone" :disabled="!canEdit" />
            </div>
            <div class="col-md-8">
              <label class="form-label">Dirección</label>
              <BFormInput v-model="form.address" :disabled="!canEdit" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Región</label>
              <Multiselect
                v-model="form.region_id"
                :options="regionOptions"
                :searchable="true"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-6">
              <label class="form-label">Comuna</label>
              <Multiselect
                v-model="form.commune_id"
                :options="communeOptions"
                :searchable="true"
                :disabled="!canEdit || !form.region_id"
              />
            </div>
          </div>
        </BCard>

        <BCard title="Datos laborales" class="mt-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Cargo</label>
              <Multiselect v-model="form.cargo_id" :options="cargoOptions" :searchable="true" :disabled="!canEdit" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipo de contrato</label>
              <Multiselect
                v-model="form.contract_type"
                :options="contractTypeOptions"
                :searchable="true"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha de ingreso</label>
              <BFormInput v-model="form.start_date" type="date" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Fecha de término</label>
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
              <label class="form-label">Jornada</label>
              <Multiselect v-model="form.workday" :options="workdayOptions" :searchable="true" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Horas de contrato</label>
              <BFormInput v-model="form.contract_hours" type="number" min="0" step="0.01" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Título profesional</label>
              <BFormInput v-model="form.professional_title" :disabled="!canEdit" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Especialidad</label>
              <BFormInput v-model="form.specialty" :disabled="!canEdit" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Registro profesional</label>
              <BFormInput v-model="form.professional_registration" :disabled="!canEdit" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Usuario asociado</label>
              <Multiselect
                v-model="form.associated_user_id"
                :options="userOptions"
                :searchable="true"
                :disabled="!canEdit"
              />
            </div>
            <div class="col-12">
              <label class="form-label">Observaciones internas</label>
              <BFormTextarea v-model="form.internal_notes" rows="3" :disabled="!canEdit" />
            </div>
          </div>
        </BCard>

        <BCard title="Departamentos asociados" class="mt-3">
          <label class="form-label">Asignación de departamentos</label>
          <Multiselect
            v-model="form.department_ids"
            :options="departmentOptions"
            mode="multiple"
            :close-on-select="false"
            :searchable="true"
            :disabled="!canEdit"
          />
          <div class="small text-muted mt-2">
            La asignación y remoción de departamentos se gestiona desde esta ficha.
          </div>
        </BCard>
      </div>

      <div v-if="staff" class="col-12">
        <BCard title="Resumen institucional">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="text-muted small">RUT</div>
              <div class="fw-semibold">{{ staff.rut }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted small">Ingreso</div>
              <div class="fw-semibold">{{ formatDate(staff.start_date) }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted small">Contrato</div>
              <div class="fw-semibold">{{ contractTypeOptions.find((item) => item.value === staff.contract_type)?.label || "-" }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted small">Usuario asociado</div>
              <div class="fw-semibold">{{ staff.user?.email || "-" }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted small">Mantención</div>
              <div class="fw-semibold">
                {{ staff.can_receive_maintenance_orders ? (staff.maintenance_role_label || "Responsable OT") : "No recibe OT" }}
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-muted small">Región</div>
              <div class="fw-semibold">{{ staff.region_record?.short_name || staff.region_record?.name || staff.region || "-" }}</div>
            </div>
            <div class="col-md-3">
              <div class="text-muted small">Comuna</div>
              <div class="fw-semibold">{{ staff.commune_record?.name || staff.commune || "-" }}</div>
            </div>
            <div class="col-12">
              <div class="text-muted small">Departamentos</div>
              <div class="fw-semibold">
                {{ (staff.departments || []).map((department) => department.name).join(", ") || "-" }}
              </div>
            </div>
          </div>
        </BCard>
      </div>

      <div v-if="staff" class="col-12">
        <BCard title="Historial de reservas" class="mb-3">
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
        <BCard title="Historial de contratos">
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
        <BCard title="Permisos">
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
        <BCard title="Documentos adjuntos">
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
  </Layout>
</template>

<style scoped>
:deep(.staff-role-multiselect) {
  --ms-radius: 0.7rem;
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
</style>
