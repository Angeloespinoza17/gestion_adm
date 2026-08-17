<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import Layout from "../../../layouts/main.vue";
import api from "../api/messagingApi";
import { messagingStore as store } from "../stores/messagingStore";
import { useMessagingRealtime } from "../composables/useMessagingRealtime";

const route = useRoute();
const router = useRouter();
const timeline = ref(null);
const composerInput = ref(null);
const currentUser = ref({});

const query = ref("");
const activeFilter = ref("all");
const body = ref("");
const subject = ref("");
const priority = ref("normal");
const formal = ref(false);
const requiresAck = ref(false);
const dueAt = ref("");
const commentRequired = ref(false);
const allowReplies = ref(true);
const replyingTo = ref(null);
const uploads = ref([]);
const uploadProgress = ref(0);
const uploadError = ref("");
const sendError = ref("");

const createOpen = ref(false);
const createMode = ref("direct");
const userQuery = ref("");
const users = ref([]);
const searching = ref(false);
const selectedUsers = ref([]);
const groupTitle = ref("");
const groupDescription = ref("");
const groupOnlyAdmins = ref(false);
const createSaving = ref(false);
const createError = ref("");

const groupEditOpen = ref(false);
const groupEditForm = ref({ title: "", description: "", only_admins_can_write: false, is_locked: false });
const groupEditSaving = ref(false);
const groupEditError = ref("");
const groupEditNotice = ref("");
const manageUserQuery = ref("");
const manageUsers = ref([]);
const manageSearching = ref(false);
const groupActionId = ref(null);

const ackMessage = ref(null);
const ackComment = ref("");
const ackSaving = ref(false);
const copiedMessage = ref(null);
let conversationSearchTimer;
let userSearchTimer;
let manageUserSearchTimer;
let copyTimer;

const activeId = computed(() => store.state.activeConversation?.public_id);
const activeConversation = computed(() => store.state.activeConversation);
const messages = computed(() => store.state.messagesByConversation[activeId.value] || []);
const participants = computed(() => activeConversation.value?.participants?.filter((item) => !item.left_at) || []);
const activeParticipant = computed(() => participants.value.find((item) => item.id === currentUser.value.id));
const canManageGroup = computed(() => activeConversation.value?.type === "group" && ["owner", "admin"].includes(activeParticipant.value?.role));
const isGroupOwner = computed(() => activeParticipant.value?.role === "owner");
const manageCandidates = computed(() => {
  const participantIds = new Set(participants.value.map((participant) => participant.id));
  return manageUsers.value.filter((user) => !participantIds.has(user.id));
});
const canSend = computed(() => {
  if (!activeConversation.value || activeConversation.value.is_locked) return false;
  if (!activeConversation.value.only_admins_can_write) return true;
  return ["owner", "admin"].includes(activeParticipant.value?.role);
});
const participantCaption = computed(() => {
  const count = participants.value.length;
  if (activeConversation.value?.type === "direct") return "Conversación privada";
  return `${count} ${count === 1 ? "participante" : "participantes"}`;
});
const attachmentAccept = computed(() => (store.state.config.attachments?.extensions || []).map((item) => `.${item}`).join(","));
const groupReady = computed(() => groupTitle.value.trim().length >= 2 && selectedUsers.value.length > 0 && !createSaving.value);
const pollSeconds = computed(() => Math.round(Number(store.state.config.realtime?.poll_interval_ms || 5000) / 1000));
const acknowledgementComplete = (message) => {
  const summary = message.acknowledgement_summary;
  return Number(summary?.total || 0) > 0 && Number(summary.acknowledged || 0) >= Number(summary.total || 0);
};
const acknowledgementNames = (message) => (message.acknowledgement_summary?.acknowledged_by || []).map((item) => item.name).filter(Boolean).join(", ");

const filters = [
  { id: "all", label: "Todas" },
  { id: "unread", label: "No leídas" },
  { id: "group", label: "Grupos" },
  { id: "announcement", label: "Comunicaciones" },
];

const typeMeta = (type) => ({
  direct: { label: "Directo", icon: "bx-user" },
  group: { label: "Grupo", icon: "bx-group" },
  announcement: { label: "Comunicado", icon: "bx-broadcast" },
}[type] || { label: "Conversación", icon: "bx-message-rounded" });

const initials = (name = "") => name.trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join("").toUpperCase() || "?";
const avatarTone = (name = "") => {
  const tones = ["tone-indigo", "tone-violet", "tone-cyan", "tone-emerald", "tone-coral", "tone-blue"];
  const seed = [...name].reduce((sum, character) => sum + character.charCodeAt(0), 0);
  return tones[seed % tones.length];
};
const dateKey = (date) => new Date(date).toDateString();
const dateLabel = (date) => {
  const value = new Date(date);
  const today = new Date();
  const yesterday = new Date();
  yesterday.setDate(today.getDate() - 1);
  if (value.toDateString() === today.toDateString()) return "Hoy";
  if (value.toDateString() === yesterday.toDateString()) return "Ayer";
  return new Intl.DateTimeFormat("es-CL", { day: "numeric", month: "long", year: "numeric" }).format(value);
};
const time = (date) => new Intl.DateTimeFormat("es-CL", { hour: "2-digit", minute: "2-digit" }).format(new Date(date));
const compactDate = (date) => {
  if (!date) return "";
  const value = new Date(date);
  return dateKey(value) === dateKey(new Date())
    ? time(value)
    : new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "short" }).format(value);
};
const fileSize = (bytes = 0) => bytes < 1024 * 1024 ? `${Math.ceil(bytes / 1024)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
const conversationPhoto = (conversation) => conversation.type === "direct" ? conversation.participants?.[0]?.photo : null;
const messagePreview = (conversation) => {
  const message = conversation.last_message;
  if (!message) return "Inicia la conversación";
  const content = message.subject || message.body || "Archivo adjunto";
  return `${message.sender ? `${message.sender}: ` : ""}${content}`;
};

useMessagingRealtime(activeId);

const scrollBottom = () => nextTick(() => {
  if (timeline.value) timeline.value.scrollTop = timeline.value.scrollHeight;
});

watch(() => messages.value.at(-1)?.public_id, async () => {
  const element = timeline.value;
  const followsLatest = !element || element.scrollHeight - element.scrollTop - element.clientHeight < 180;
  await nextTick();
  if (followsLatest && timeline.value) timeline.value.scrollTop = timeline.value.scrollHeight;
});

watch(query, () => {
  clearTimeout(conversationSearchTimer);
  conversationSearchTimer = setTimeout(loadConversations, 280);
});

watch(userQuery, () => {
  clearTimeout(userSearchTimer);
  if (userQuery.value.trim().length < 2) {
    users.value = [];
    return;
  }
  userSearchTimer = setTimeout(searchUsers, 280);
});

watch(manageUserQuery, () => {
  clearTimeout(manageUserSearchTimer);
  if (manageUserQuery.value.trim().length < 2) {
    manageUsers.value = [];
    return;
  }
  manageUserSearchTimer = setTimeout(searchManageUsers, 280);
});

async function loadConversations() {
  const params = {};
  if (query.value.trim()) params.search = query.value.trim();
  if (activeFilter.value === "unread") params.unread = 1;
  if (["group", "announcement"].includes(activeFilter.value)) params.type = activeFilter.value;
  await store.loadConversations(params);
}

async function setFilter(filter) {
  activeFilter.value = filter;
  await loadConversations();
}

async function openConversation(id, replace = false) {
  await store.open(id);
  const destination = { path: `/mensajeria/${id}`, query: route.query };
  if (route.params.conversationId !== id) await router[replace ? "replace" : "push"](destination);
  scrollBottom();
}

function closeMobileThread() {
  store.close();
  router.push("/mensajeria");
}

function openCreate(mode = "direct") {
  createMode.value = mode;
  createOpen.value = true;
  createError.value = "";
  userQuery.value = "";
  users.value = [];
}

function closeCreate() {
  createOpen.value = false;
  createError.value = "";
  groupTitle.value = "";
  groupDescription.value = "";
  groupOnlyAdmins.value = false;
  selectedUsers.value = [];
  userQuery.value = "";
  users.value = [];
}

async function searchUsers() {
  searching.value = true;
  createError.value = "";
  try {
    users.value = (await api.users(userQuery.value.trim())).data.data;
  } catch (error) {
    createError.value = error.response?.data?.message || "No fue posible buscar usuarios.";
  } finally {
    searching.value = false;
  }
}

function isSelected(user) {
  return selectedUsers.value.some((item) => item.id === user.id);
}

function toggleSelectedUser(user) {
  if (isSelected(user)) selectedUsers.value = selectedUsers.value.filter((item) => item.id !== user.id);
  else selectedUsers.value.push(user);
}

async function startDirect(user) {
  createSaving.value = true;
  createError.value = "";
  try {
    const { data } = await api.direct(user.id);
    closeCreate();
    await store.loadConversations();
    await openConversation(data.data.public_id);
  } catch (error) {
    createError.value = error.response?.data?.message || "No fue posible iniciar la conversación.";
  } finally {
    createSaving.value = false;
  }
}

async function createGroup() {
  if (!groupReady.value) return;
  createSaving.value = true;
  createError.value = "";
  try {
    const { data } = await api.group({
      title: groupTitle.value.trim(),
      description: groupDescription.value.trim() || null,
      user_ids: selectedUsers.value.map((user) => user.id),
      only_admins_can_write: groupOnlyAdmins.value,
    });
    closeCreate();
    await store.loadConversations();
    await openConversation(data.data.public_id);
  } catch (error) {
    createError.value = error.response?.data?.message || "No fue posible crear el grupo.";
  } finally {
    createSaving.value = false;
  }
}

function openGroupManager() {
  if (!canManageGroup.value) return;
  groupEditForm.value = {
    title: activeConversation.value.title || "",
    description: activeConversation.value.description || "",
    only_admins_can_write: Boolean(activeConversation.value.only_admins_can_write),
    is_locked: Boolean(activeConversation.value.is_locked),
  };
  groupEditError.value = "";
  groupEditNotice.value = "";
  manageUserQuery.value = "";
  manageUsers.value = [];
  groupEditOpen.value = true;
}

function closeGroupManager() {
  groupEditOpen.value = false;
  groupEditError.value = "";
  groupEditNotice.value = "";
  manageUserQuery.value = "";
  manageUsers.value = [];
}

async function searchManageUsers() {
  manageSearching.value = true;
  groupEditError.value = "";
  try {
    manageUsers.value = (await api.users(manageUserQuery.value.trim())).data.data || [];
  } catch (error) {
    groupEditError.value = error.response?.data?.message || "No fue posible buscar usuarios.";
  } finally {
    manageSearching.value = false;
  }
}

async function refreshManagedGroup(message = "") {
  await store.refreshActiveConversation();
  groupEditNotice.value = message;
}

async function saveGroupSettings() {
  if (!groupEditForm.value.title.trim()) return;
  groupEditSaving.value = true;
  groupEditError.value = "";
  groupEditNotice.value = "";
  try {
    const id = activeId.value;
    await api.updateConversation(id, {
      title: groupEditForm.value.title.trim(),
      description: groupEditForm.value.description.trim() || null,
      only_admins_can_write: groupEditForm.value.only_admins_can_write,
    });
    if (groupEditForm.value.is_locked !== Boolean(activeConversation.value.is_locked)) {
      await api.setLock(id, groupEditForm.value.is_locked);
    }
    await refreshManagedGroup("Los cambios del grupo fueron guardados.");
  } catch (error) {
    groupEditError.value = error.response?.data?.message || "No fue posible actualizar el grupo.";
  } finally {
    groupEditSaving.value = false;
  }
}

async function addGroupParticipant(user) {
  groupActionId.value = `add-${user.id}`;
  groupEditError.value = "";
  groupEditNotice.value = "";
  try {
    await api.addParticipants(activeId.value, [user.id]);
    manageUserQuery.value = "";
    manageUsers.value = [];
    await refreshManagedGroup(`${user.name} fue agregado al grupo.`);
  } catch (error) {
    groupEditError.value = error.response?.data?.message || "No fue posible agregar al integrante.";
  } finally {
    groupActionId.value = null;
  }
}

async function toggleGroupAdmin(participant) {
  groupActionId.value = `role-${participant.id}`;
  groupEditError.value = "";
  groupEditNotice.value = "";
  const role = participant.role === "admin" ? "member" : "admin";
  try {
    await api.updateParticipant(activeId.value, participant.id, { role });
    await refreshManagedGroup(role === "admin" ? `${participant.name} ahora es administrador.` : `${participant.name} ahora es integrante.`);
  } catch (error) {
    groupEditError.value = error.response?.data?.message || "No fue posible cambiar el rol.";
  } finally {
    groupActionId.value = null;
  }
}

async function removeGroupParticipant(participant) {
  if (!window.confirm(`¿Retirar a ${participant.name} de este grupo?`)) return;
  groupActionId.value = `remove-${participant.id}`;
  groupEditError.value = "";
  groupEditNotice.value = "";
  try {
    await api.removeParticipant(activeId.value, participant.id);
    await refreshManagedGroup(`${participant.name} fue retirado del grupo.`);
  } catch (error) {
    groupEditError.value = error.response?.data?.message || "No fue posible retirar al integrante.";
  } finally {
    groupActionId.value = null;
  }
}

async function transferGroupOwnership(participant) {
  if (!window.confirm(`¿Transferir la propiedad del grupo a ${participant.name}?`)) return;
  groupActionId.value = `owner-${participant.id}`;
  groupEditError.value = "";
  groupEditNotice.value = "";
  try {
    await api.transferOwnership(activeId.value, participant.id);
    await refreshManagedGroup(`${participant.name} ahora es propietario del grupo.`);
  } catch (error) {
    groupEditError.value = error.response?.data?.message || "No fue posible transferir la propiedad.";
  } finally {
    groupActionId.value = null;
  }
}

async function uploadFiles(event) {
  uploadError.value = "";
  try {
    for (const file of [...event.target.files]) {
      const { data } = await api.upload(file, (progress) => {
        uploadProgress.value = Math.round((progress.loaded / progress.total) * 100);
      });
      uploads.value.push(data.data);
    }
  } catch (error) {
    uploadError.value = error.response?.status === 429
      ? "Se alcanzó temporalmente el límite de cargas. Espera unos segundos e inténtalo nuevamente."
      : error.response?.data?.message || "No fue posible adjuntar el archivo.";
  } finally {
    uploadProgress.value = 0;
    event.target.value = "";
  }
}

async function removeUpload(upload) {
  uploads.value = uploads.value.filter((item) => item.token !== upload.token);
  try { await api.removeUpload(upload.token); } catch (_) { /* El token expira automáticamente. */ }
}

async function send() {
  if ((!body.value.trim() && !uploads.value.length) || !canSend.value) return;
  sendError.value = "";
  const messageBody = body.value;
  const payload = {
    body: messageBody,
    subject: subject.value || null,
    priority: priority.value,
    formal: formal.value,
    requires_acknowledgement: requiresAck.value,
    acknowledgement_due_at: requiresAck.value ? dueAt.value : null,
    acknowledgement_comment_required: commentRequired.value,
    allow_replies: allowReplies.value,
    reply_to_id: replyingTo.value?.public_id || null,
    upload_tokens: uploads.value.map((item) => item.token),
    sender_id: currentUser.value.id,
  };
  try {
    await store.send(payload);
    body.value = "";
    subject.value = "";
    uploads.value = [];
    requiresAck.value = false;
    formal.value = false;
    replyingTo.value = null;
    if (composerInput.value) composerInput.value.style.height = "auto";
    scrollBottom();
  } catch (error) {
    body.value = messageBody;
    sendError.value = error.response?.data?.message || "El mensaje no pudo enviarse. Inténtalo nuevamente.";
  }
}

function onComposerKey(event) {
  if (event.isComposing) return;
  if (event.key === "Enter" && !event.shiftKey) {
    event.preventDefault();
    send();
  }
}

function resizeComposer(event) {
  event.target.style.height = "auto";
  event.target.style.height = `${Math.min(event.target.scrollHeight, 140)}px`;
}

function replyTo(message) {
  replyingTo.value = message;
  nextTick(() => composerInput.value?.focus());
}

async function toggleReaction(message, reaction) {
  await store.toggleReaction(message, reaction);
}

async function copyMessage(message) {
  await navigator.clipboard.writeText(message.body || message.subject || "");
  copiedMessage.value = message.public_id;
  clearTimeout(copyTimer);
  copyTimer = setTimeout(() => { copiedMessage.value = null; }, 1400);
}

async function acknowledge() {
  if (ackMessage.value.acknowledgement_comment_required && !ackComment.value.trim()) return;
  ackSaving.value = true;
  try {
    await api.acknowledge(ackMessage.value.public_id, ackComment.value);
    await store.refreshMessage(ackMessage.value.public_id);
    ackMessage.value = null;
    ackComment.value = "";
    await store.loadSummary();
  } finally {
    ackSaving.value = false;
  }
}

onMounted(async () => {
  await Promise.all([store.loadConfig(), store.loadSummary(), store.loadConversations()]);
  try {
    currentUser.value = (await import("axios").then((module) => module.default.get("/api/me/profile"))).data.data || {};
  } catch (_) { /* La sesión ya está validada por la ruta. */ }
  const id = route.params.conversationId || store.state.conversations[0]?.public_id;
  if (id) await openConversation(id, true);
});

onBeforeUnmount(() => {
  clearTimeout(conversationSearchTimer);
  clearTimeout(userSearchTimer);
  clearTimeout(manageUserSearchTimer);
  clearTimeout(copyTimer);
});
</script>

<template>
  <Layout>
    <div class="messaging-page">
      <header class="messaging-hero">
        <div class="hero-copy">
          <span class="hero-kicker"><i class="bx bx-shield-quarter"></i> Centro de comunicaciones</span>
          <h1>Mensajería</h1>
          <p>Conecta equipos, comparte archivos y gestiona comunicaciones importantes.</p>
        </div>

        <div class="hero-actions">
          <div class="connection-state" :class="store.state.realtimeConnectionState">
            <span class="connection-dot"></span>
            {{ store.state.realtimeConnectionState === "connected" ? "En tiempo real" : `Actualización cada ${pollSeconds} s` }}
          </div>
          <button class="create-group-button" type="button" @click="openCreate('group')">
            <i class="bx bx-group"></i>
            <span>Crear grupo</span>
          </button>
          <button class="new-message-button" type="button" @click="openCreate('direct')">
            <i class="bx bx-plus"></i>
            <span>Nuevo mensaje</span>
          </button>
        </div>
      </header>

      <div v-if="store.state.error" class="messaging-alert messaging-alert--danger" role="alert">
        <i class="bx bx-error-circle"></i><span>{{ store.state.error }}</span>
      </div>

      <section class="messenger-shell">
        <aside class="conversation-panel" :class="{ 'mobile-hidden': activeId }">
          <div class="panel-overview">
            <div>
              <span>Bandeja de entrada</span>
              <strong>{{ store.state.summary.unread_messages || 0 }} sin leer</strong>
            </div>
            <button type="button" title="Crear conversación" @click="openCreate('direct')"><i class="bx bx-edit"></i></button>
          </div>

          <div class="conversation-tools">
            <label class="conversation-search">
              <i class="bx bx-search"></i>
              <input v-model="query" aria-label="Buscar conversaciones" placeholder="Buscar por nombre o mensaje">
              <button v-if="query" type="button" aria-label="Limpiar búsqueda" @click="query = ''"><i class="bx bx-x"></i></button>
            </label>
            <nav class="conversation-filters" aria-label="Filtros de conversaciones">
              <button
                v-for="filter in filters"
                :key="filter.id"
                type="button"
                :class="{ active: activeFilter === filter.id }"
                @click="setFilter(filter.id)"
              >{{ filter.label }}</button>
            </nav>
          </div>

          <div class="conversation-list">
            <div v-if="store.state.loading.conversations" class="conversation-skeletons" aria-label="Cargando conversaciones">
              <span v-for="number in 6" :key="number"></span>
            </div>

            <button
              v-for="conversation in store.state.conversations"
              v-else
              :key="conversation.public_id"
              type="button"
              class="conversation-card"
              :class="{ selected: conversation.public_id === activeId, unread: conversation.unread_count }"
              @click="openConversation(conversation.public_id)"
            >
              <span class="conversation-avatar" :class="avatarTone(conversation.title)">
                <img v-if="conversationPhoto(conversation)" :src="conversationPhoto(conversation)" :alt="conversation.title">
                <i v-else-if="conversation.type !== 'direct'" class="bx" :class="typeMeta(conversation.type).icon"></i>
                <span v-else>{{ initials(conversation.title) }}</span>
                <span v-if="conversation.type === 'direct'" class="presence-dot"></span>
              </span>
              <span class="conversation-content">
                <span class="conversation-line">
                  <strong>{{ conversation.title }}</strong>
                  <time>{{ compactDate(conversation.last_message_at) }}</time>
                </span>
                <span class="conversation-line conversation-line--preview">
                  <small>{{ messagePreview(conversation) }}</small>
                  <b v-if="conversation.unread_count">{{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}</b>
                  <i v-else-if="conversation.pinned" class="bx bx-pin"></i>
                </span>
                <span v-if="conversation.type !== 'direct'" class="conversation-type">
                  <i class="bx" :class="typeMeta(conversation.type).icon"></i>{{ typeMeta(conversation.type).label }}
                </span>
              </span>
            </button>

            <div v-if="!store.state.loading.conversations && !store.state.conversations.length" class="empty-conversations">
              <span><i class="bx bx-message-square-add"></i></span>
              <strong>{{ query ? 'Sin resultados' : 'Tu bandeja está lista' }}</strong>
              <p>{{ query ? 'Prueba con otro nombre o término.' : 'Inicia una conversación directa o crea un grupo para tu equipo.' }}</p>
              <button type="button" @click="openCreate('direct')"><i class="bx bx-plus"></i> Nueva conversación</button>
            </div>
          </div>
        </aside>

        <main class="thread-panel" :class="{ 'mobile-hidden': !activeId }">
          <template v-if="activeConversation">
            <header class="thread-header">
              <button class="thread-back" type="button" aria-label="Volver a conversaciones" @click="closeMobileThread">
                <i class="bx bx-left-arrow-alt"></i>
              </button>
              <span class="conversation-avatar conversation-avatar--header" :class="avatarTone(activeConversation.title)">
                <img v-if="conversationPhoto(activeConversation)" :src="conversationPhoto(activeConversation)" :alt="activeConversation.title">
                <i v-else-if="activeConversation.type !== 'direct'" class="bx" :class="typeMeta(activeConversation.type).icon"></i>
                <span v-else>{{ initials(activeConversation.title) }}</span>
                <span v-if="activeConversation.type === 'direct'" class="presence-dot"></span>
              </span>
              <div class="thread-identity">
                <h2>{{ activeConversation.title }}</h2>
                <span><i class="bx" :class="typeMeta(activeConversation.type).icon"></i>{{ participantCaption }}</span>
              </div>
              <div class="thread-header-actions">
                <span v-if="activeConversation.is_locked" class="locked-pill"><i class="bx bx-lock-alt"></i> Bloqueado</span>
                <button v-if="canManageGroup" class="manage-group-button" type="button" aria-label="Administrar grupo" title="Administrar grupo" @click="openGroupManager"><i class="bx bx-edit-alt"></i></button>
                <button type="button" aria-label="Información de la conversación" title="Información"><i class="bx bx-info-circle"></i></button>
              </div>
            </header>

            <div ref="timeline" class="message-timeline" aria-live="polite">
              <div class="timeline-intro">
                <span class="conversation-avatar conversation-avatar--intro" :class="avatarTone(activeConversation.title)">
                  <i v-if="activeConversation.type !== 'direct'" class="bx" :class="typeMeta(activeConversation.type).icon"></i>
                  <span v-else>{{ initials(activeConversation.title) }}</span>
                </span>
                <h3>{{ activeConversation.title }}</h3>
                <p>{{ activeConversation.description || `Este es el comienzo de ${activeConversation.type === 'direct' ? 'esta conversación privada' : 'este espacio de colaboración'}.` }}</p>
                <span class="privacy-note"><i class="bx bx-lock-alt"></i> Solo los participantes pueden ver este contenido</span>
              </div>

              <button v-if="messages.length >= 40" class="load-older" type="button" @click="store.loadOlder">
                <i class="bx bx-history"></i> Ver mensajes anteriores
              </button>

              <template v-for="(message, index) in messages" :key="message.public_id">
                <div v-if="!index || dateKey(messages[index - 1].sent_at) !== dateKey(message.sent_at)" class="date-divider">
                  <span>{{ dateLabel(message.sent_at) }}</span>
                </div>

                <article
                  :id="`message-${message.public_id}`"
                  class="message-item"
                  :class="{ own: message.sender_id === currentUser.id, formal: message.kind === 'notice' }"
                >
                  <span v-if="message.sender_id !== currentUser.id" class="message-avatar" :class="avatarTone(message.sender?.name)">
                    <img v-if="message.sender?.photo" :src="message.sender.photo" :alt="message.sender.name">
                    <span v-else>{{ initials(message.sender?.name) }}</span>
                  </span>

                  <div class="message-stack">
                    <span v-if="message.sender_id !== currentUser.id" class="message-sender">{{ message.sender?.name }}</span>
                    <div class="message-bubble">
                      <div v-if="message.kind === 'notice'" class="formal-heading">
                        <span><i class="bx bx-file-blank"></i> Comunicación formal</span>
                        <b :class="`priority-tag priority-tag--${message.priority}`">{{ message.priority }}</b>
                      </div>

                      <div v-if="message.reply_to" class="reply-preview">
                        <strong>{{ message.reply_to.sender }}</strong>
                        <span>{{ message.reply_to.body }}</span>
                      </div>

                      <strong v-if="message.subject" class="message-subject">{{ message.subject }}</strong>
                      <p v-if="message.body">{{ message.body }}</p>

                      <div v-if="message.attachments?.length" class="message-attachments">
                        <a v-for="attachment in message.attachments" :key="attachment.public_id" :href="attachment.download_url">
                          <span><i class="bx bx-file"></i></span>
                          <span><strong>{{ attachment.name }}</strong><small>{{ fileSize(attachment.size) }}</small></span>
                          <i class="bx bx-download"></i>
                        </a>
                      </div>

                      <div v-if="message.requires_acknowledgement" class="acknowledgement-card" :class="[message.acknowledgement_status, { acknowledged: message.sender_id === currentUser.id && acknowledgementComplete(message) }]">
                        <i :class="message.acknowledgement_status === 'acknowledged' || (message.sender_id === currentUser.id && acknowledgementComplete(message)) ? 'bx bx-check-shield' : 'bx bx-time-five'"></i>
                        <span v-if="message.sender_id === currentUser.id">
                          <strong>{{ acknowledgementComplete(message) ? 'Acuse completado' : 'Seguimiento de acuses' }}</strong>
                          <small>{{ message.acknowledgement_summary?.acknowledged || 0 }} de {{ message.acknowledgement_summary?.total || 0 }} confirmados<span v-if="acknowledgementNames(message)"> · {{ acknowledgementNames(message) }}</span></small>
                        </span>
                        <span v-else-if="message.acknowledgement_status === 'acknowledged'">
                          <strong>Acuse registrado</strong>
                          <small>Recepción y revisión confirmadas.</small>
                        </span>
                        <span v-else>
                          <strong>{{ message.acknowledgement_status === 'overdue' ? 'Acuse vencido' : 'Acuse pendiente' }}</strong>
                          <small>Confirma que recibiste y revisaste esta comunicación.</small>
                        </span>
                        <button v-if="message.sender_id !== currentUser.id && message.acknowledgement_status !== 'acknowledged'" type="button" @click="ackMessage = message">Confirmar</button>
                      </div>

                      <footer class="message-meta">
                        <span v-if="message.edited_at">Editado</span>
                        <time>{{ time(message.sent_at) }}</time>
                        <i v-if="message.sender_id === currentUser.id && message.status !== 'error'" class="bx bx-check-double"></i>
                        <span v-if="message.status === 'sending'">Enviando…</span>
                        <span v-if="message.status === 'error'" class="message-error">No enviado</span>
                      </footer>
                    </div>

                    <div v-if="message.reactions?.length" class="reaction-list">
                      <button
                        v-for="reaction in message.reactions"
                        :key="reaction.reaction"
                        type="button"
                        :class="{ mine: reaction.mine }"
                        @click="toggleReaction(message, reaction.reaction)"
                      >{{ reaction.reaction }} <span>{{ reaction.count }}</span></button>
                    </div>
                  </div>

                  <div class="message-actions">
                    <button type="button" title="Responder" @click="replyTo(message)"><i class="bx bx-reply"></i></button>
                    <button type="button" :title="copiedMessage === message.public_id ? 'Copiado' : 'Copiar'" @click="copyMessage(message)">
                      <i class="bx" :class="copiedMessage === message.public_id ? 'bx-check' : 'bx-copy'"></i>
                    </button>
                    <button
                      v-for="reaction in (store.state.config.reactions || []).slice(0, 1)"
                      :key="reaction"
                      type="button"
                      title="Reaccionar"
                      @click="toggleReaction(message, reaction)"
                    >{{ reaction }}</button>
                  </div>
                </article>
              </template>
            </div>

            <form class="composer" @submit.prevent="send">
              <div v-if="replyingTo" class="composer-reply">
                <i class="bx bx-reply"></i>
                <span><strong>Respondiendo a {{ replyingTo.sender?.name }}</strong><small>{{ replyingTo.body }}</small></span>
                <button type="button" aria-label="Cancelar respuesta" @click="replyingTo = null"><i class="bx bx-x"></i></button>
              </div>

              <div v-if="uploads.length" class="composer-uploads">
                <span v-for="upload in uploads" :key="upload.token">
                  <i class="bx bx-file"></i>
                  <span><strong>{{ upload.name }}</strong><small>Listo para enviar</small></span>
                  <button type="button" aria-label="Quitar archivo" @click="removeUpload(upload)"><i class="bx bx-x"></i></button>
                </span>
              </div>

              <div v-if="formal || requiresAck" class="formal-settings">
                <label class="setting-check"><input v-model="formal" type="checkbox"><span><i class="bx bx-file-blank"></i> Comunicación formal</span></label>
                <label class="setting-check"><input v-model="requiresAck" type="checkbox"><span><i class="bx bx-check-shield"></i> Solicitar acuse</span></label>
                <template v-if="requiresAck">
                  <label class="due-field"><span>Fecha límite</span><input v-model="dueAt" type="datetime-local" required></label>
                  <label class="mini-check"><input v-model="commentRequired" type="checkbox"> Comentario obligatorio</label>
                  <label class="mini-check"><input v-model="allowReplies" type="checkbox"> Permitir respuestas</label>
                </template>
              </div>

              <div v-if="uploadError || sendError" class="composer-error">
                <i class="bx bx-error-circle"></i>{{ uploadError || sendError }}
              </div>

              <div v-if="!canSend" class="composer-locked">
                <i class="bx bx-lock-alt"></i>
                <span>{{ activeConversation.is_locked ? 'Esta conversación está bloqueada temporalmente.' : 'Solo los administradores pueden enviar mensajes en este grupo.' }}</span>
              </div>

              <div v-else class="composer-box">
                <textarea
                  ref="composerInput"
                  v-model="body"
                  rows="1"
                  :maxlength="store.state.config.messages?.max_length"
                  placeholder="Escribe un mensaje…"
                  @input="resizeComposer"
                  @keydown="onComposerKey"
                ></textarea>
                <div class="composer-toolbar">
                  <div class="composer-tools">
                    <label title="Adjuntar archivo">
                      <i class="bx bx-paperclip"></i>
                      <input type="file" multiple class="visually-hidden" :accept="attachmentAccept" @change="uploadFiles">
                    </label>
                    <button type="button" :class="{ active: formal || requiresAck }" title="Opciones formales" @click="formal = !formal"><i class="bx bx-file-blank"></i></button>
                    <select v-model="priority" aria-label="Prioridad del mensaje" title="Prioridad">
                      <option value="normal">Prioridad normal</option>
                      <option value="high">Prioridad alta</option>
                      <option value="urgent">Prioridad urgente</option>
                    </select>
                  </div>
                  <div class="send-tools">
                    <small>Enter para enviar</small>
                    <button type="submit" :disabled="store.state.loading.sending || (!body.trim() && !uploads.length)" aria-label="Enviar mensaje">
                      <i class="bx" :class="store.state.loading.sending ? 'bx-loader-alt bx-spin' : 'bx-send'"></i>
                    </button>
                  </div>
                </div>
                <div v-if="uploadProgress" class="upload-progress"><span :style="{ width: `${uploadProgress}%` }"></span></div>
              </div>
            </form>
          </template>

          <div v-else class="thread-welcome">
            <div class="welcome-visual">
              <span class="orb orb--one"></span><span class="orb orb--two"></span>
              <i class="bx bx-message-square-dots"></i>
            </div>
            <span class="hero-kicker">Tu espacio de trabajo</span>
            <h2>Comunícate con claridad</h2>
            <p>Selecciona una conversación o crea un grupo para mantener a tu equipo conectado.</p>
            <div class="welcome-actions">
              <button type="button" @click="openCreate('direct')"><i class="bx bx-message-rounded-add"></i> Nuevo mensaje</button>
              <button type="button" @click="openCreate('group')"><i class="bx bx-group"></i> Crear grupo</button>
            </div>
          </div>
        </main>

        <aside v-if="activeConversation" class="details-panel">
          <div class="details-hero">
            <span class="conversation-avatar conversation-avatar--details" :class="avatarTone(activeConversation.title)">
              <img v-if="conversationPhoto(activeConversation)" :src="conversationPhoto(activeConversation)" :alt="activeConversation.title">
              <i v-else-if="activeConversation.type !== 'direct'" class="bx" :class="typeMeta(activeConversation.type).icon"></i>
              <span v-else>{{ initials(activeConversation.title) }}</span>
            </span>
            <h3>{{ activeConversation.title }}</h3>
            <span class="details-type"><i class="bx" :class="typeMeta(activeConversation.type).icon"></i>{{ typeMeta(activeConversation.type).label }}</span>
            <p>{{ activeConversation.description || 'Conversación institucional segura.' }}</p>
            <button v-if="canManageGroup" class="details-manage-button" type="button" @click="openGroupManager"><i class="bx bx-cog"></i> Administrar grupo</button>
          </div>

          <div class="details-stats">
            <div><span><i class="bx bx-envelope"></i></span><strong>{{ store.state.summary.unread_messages || 0 }}</strong><small>Sin leer</small></div>
            <div><span><i class="bx bx-check-shield"></i></span><strong>{{ store.state.summary.pending_acknowledgements || 0 }}</strong><small>Acuses</small></div>
          </div>

          <section class="participant-section">
            <header><h4>Participantes</h4><span>{{ participants.length }}</span></header>
            <div class="participant-list">
              <div v-for="participant in participants.slice(0, 10)" :key="participant.id" class="participant-row">
                <span class="participant-avatar" :class="avatarTone(participant.name)">
                  <img v-if="participant.photo" :src="participant.photo" :alt="participant.name">
                  <span v-else>{{ initials(participant.name) }}</span>
                </span>
                <span><strong>{{ participant.id === currentUser.id ? 'Tú' : participant.name }}</strong><small>{{ participant.role === 'owner' ? 'Propietario' : participant.role === 'admin' ? 'Administrador' : 'Integrante' }}</small></span>
                <i v-if="['owner', 'admin'].includes(participant.role)" class="bx bx-shield-quarter"></i>
              </div>
            </div>
          </section>

          <div class="details-security"><i class="bx bx-lock-alt"></i><span><strong>Conversación protegida</strong><small>Acceso limitado a participantes.</small></span></div>
        </aside>
      </section>

      <div v-if="createOpen" class="modal-layer" @click.self="closeCreate">
        <section class="create-dialog" role="dialog" aria-modal="true" aria-labelledby="create-title">
          <button class="dialog-close" type="button" aria-label="Cerrar" @click="closeCreate"><i class="bx bx-x"></i></button>
          <header class="create-heading">
            <span class="create-icon"><i class="bx" :class="createMode === 'group' ? 'bx-group' : 'bx-message-rounded-add'"></i></span>
            <div>
              <span class="hero-kicker">Nueva conversación</span>
              <h2 id="create-title">{{ createMode === 'group' ? 'Crea un grupo de trabajo' : 'Inicia una conversación' }}</h2>
              <p>{{ createMode === 'group' ? 'Reúne a las personas adecuadas en un espacio compartido.' : 'Busca a una persona para comenzar a conversar.' }}</p>
            </div>
          </header>

          <div class="create-mode-switch">
            <button type="button" :class="{ active: createMode === 'direct' }" @click="createMode = 'direct'; selectedUsers = []; createError = ''">
              <i class="bx bx-user"></i><span><strong>Mensaje directo</strong><small>Conversación uno a uno</small></span>
            </button>
            <button type="button" :class="{ active: createMode === 'group' }" @click="createMode = 'group'; createError = ''">
              <i class="bx bx-group"></i><span><strong>Nuevo grupo</strong><small>Colaboración en equipo</small></span>
            </button>
          </div>

          <form v-if="createMode === 'group'" class="group-form" @submit.prevent="createGroup">
            <div class="field-grid">
              <label class="form-field">
                <span>Nombre del grupo <b>*</b></span>
                <div><i class="bx bx-hash"></i><input v-model="groupTitle" maxlength="255" autofocus placeholder="Ej. Equipo de coordinación"></div>
              </label>
              <label class="form-field">
                <span>Descripción <small>Opcional</small></span>
                <textarea v-model="groupDescription" rows="2" maxlength="2000" placeholder="¿Cuál es el propósito de este grupo?"></textarea>
              </label>
            </div>

            <div class="member-picker">
              <div class="picker-label"><span>Integrantes <b>*</b></span><small>{{ selectedUsers.length }} seleccionados</small></div>
              <div v-if="selectedUsers.length" class="selected-members">
                <span v-for="user in selectedUsers" :key="user.id">
                  <span class="participant-avatar" :class="avatarTone(user.name)">{{ initials(user.name) }}</span>
                  {{ user.name }}
                  <button type="button" :aria-label="`Quitar a ${user.name}`" @click="toggleSelectedUser(user)"><i class="bx bx-x"></i></button>
                </span>
              </div>
              <label class="user-search-field">
                <i class="bx bx-search"></i>
                <input v-model="userQuery" placeholder="Buscar por nombre o correo…">
                <i v-if="searching" class="bx bx-loader-alt bx-spin"></i>
              </label>
            </div>

            <div v-if="userQuery.trim().length >= 2" class="people-results">
              <button v-for="user in users" :key="user.id" type="button" :class="{ selected: isSelected(user) }" @click="toggleSelectedUser(user)">
                <span class="participant-avatar" :class="avatarTone(user.name)"><img v-if="user.photo" :src="user.photo" :alt="user.name"><span v-else>{{ initials(user.name) }}</span></span>
                <span><strong>{{ user.name }}</strong><small>{{ user.email }}</small></span>
                <i class="bx" :class="isSelected(user) ? 'bxs-check-circle' : 'bx-plus-circle'"></i>
              </button>
              <p v-if="!searching && !users.length"><i class="bx bx-user-x"></i> No encontramos usuarios con esa búsqueda.</p>
            </div>

            <label class="admin-setting">
              <span><i class="bx bx-shield-quarter"></i></span>
              <span><strong>Solo administradores pueden escribir</strong><small>Los integrantes podrán leer, pero no enviar mensajes.</small></span>
              <input v-model="groupOnlyAdmins" type="checkbox" class="switch-input">
            </label>

            <div v-if="createError" class="dialog-error"><i class="bx bx-error-circle"></i>{{ createError }}</div>
            <footer class="create-footer">
              <span><i class="bx bx-info-circle"></i> Serás propietario y podrás administrar integrantes.</span>
              <div><button type="button" class="cancel-button" @click="closeCreate">Cancelar</button><button type="submit" class="confirm-button" :disabled="!groupReady"><i class="bx" :class="createSaving ? 'bx-loader-alt bx-spin' : 'bx-group'"></i>{{ createSaving ? 'Creando…' : 'Crear grupo' }}</button></div>
            </footer>
          </form>

          <div v-else class="direct-picker">
            <label class="user-search-field user-search-field--large">
              <i class="bx bx-search"></i>
              <input v-model="userQuery" autofocus placeholder="Buscar por nombre o correo…">
              <i v-if="searching" class="bx bx-loader-alt bx-spin"></i>
            </label>
            <div class="people-results people-results--direct">
              <button v-for="user in users" :key="user.id" type="button" :disabled="createSaving" @click="startDirect(user)">
                <span class="participant-avatar" :class="avatarTone(user.name)"><img v-if="user.photo" :src="user.photo" :alt="user.name"><span v-else>{{ initials(user.name) }}</span></span>
                <span><strong>{{ user.name }}</strong><small>{{ user.email }}</small></span>
                <i class="bx bx-chevron-right"></i>
              </button>
              <div v-if="userQuery.trim().length < 2" class="search-prompt"><span><i class="bx bx-user-voice"></i></span><strong>Encuentra a una persona</strong><p>Escribe al menos dos caracteres para buscar.</p></div>
              <p v-else-if="!searching && !users.length"><i class="bx bx-user-x"></i> No encontramos usuarios con esa búsqueda.</p>
            </div>
            <div v-if="createError" class="dialog-error"><i class="bx bx-error-circle"></i>{{ createError }}</div>
          </div>
        </section>
      </div>

      <div v-if="groupEditOpen" class="modal-layer" @click.self="closeGroupManager">
        <section class="create-dialog group-manage-dialog" role="dialog" aria-modal="true" aria-labelledby="group-manage-title">
          <button class="dialog-close" type="button" aria-label="Cerrar" @click="closeGroupManager"><i class="bx bx-x"></i></button>
          <header class="create-heading group-manage-heading">
            <span class="create-icon"><i class="bx bx-group"></i></span>
            <div>
              <span class="hero-kicker">Administración del grupo</span>
              <h2 id="group-manage-title">Configura {{ activeConversation.title }}</h2>
              <p>Actualiza la identidad, permisos e integrantes del espacio.</p>
            </div>
          </header>

          <div class="group-manage-grid">
            <form class="group-settings-card" @submit.prevent="saveGroupSettings">
              <header><span><i class="bx bx-slider-alt"></i></span><div><strong>Información y permisos</strong><small>Define cómo funciona el grupo.</small></div></header>

              <label class="manage-field">
                <span>Nombre del grupo</span>
                <div><i class="bx bx-hash"></i><input v-model="groupEditForm.title" maxlength="255" required></div>
              </label>
              <label class="manage-field">
                <span>Descripción <small>Opcional</small></span>
                <textarea v-model="groupEditForm.description" rows="3" maxlength="2000" placeholder="Describe el propósito de este grupo"></textarea>
              </label>

              <label class="manage-toggle-row">
                <span><i class="bx bx-shield-quarter"></i></span>
                <span><strong>Solo administradores escriben</strong><small>Los integrantes conservarán acceso de lectura.</small></span>
                <input v-model="groupEditForm.only_admins_can_write" type="checkbox" class="switch-input">
              </label>
              <label class="manage-toggle-row manage-toggle-row--warning">
                <span><i class="bx bx-lock-alt"></i></span>
                <span><strong>Bloquear temporalmente</strong><small>Nadie podrá enviar mensajes hasta desbloquearlo.</small></span>
                <input v-model="groupEditForm.is_locked" type="checkbox" class="switch-input">
              </label>

              <button class="save-group-button" type="submit" :disabled="groupEditSaving || !groupEditForm.title.trim()"><i class="bx" :class="groupEditSaving ? 'bx-loader-alt bx-spin' : 'bx-save'"></i>{{ groupEditSaving ? 'Guardando…' : 'Guardar cambios' }}</button>
            </form>

            <section class="group-members-card">
              <header><span><i class="bx bx-user-plus"></i></span><div><strong>Integrantes</strong><small>{{ participants.length }} personas en el grupo.</small></div></header>

              <label class="user-search-field group-member-search">
                <i class="bx bx-search"></i>
                <input v-model="manageUserQuery" placeholder="Agregar por nombre o correo…">
                <i v-if="manageSearching" class="bx bx-loader-alt bx-spin"></i>
              </label>

              <div v-if="manageUserQuery.trim().length >= 2" class="manage-candidates">
                <button v-for="user in manageCandidates" :key="user.id" type="button" :disabled="groupActionId === `add-${user.id}`" @click="addGroupParticipant(user)">
                  <span class="participant-avatar" :class="avatarTone(user.name)"><img v-if="user.photo" :src="user.photo" :alt="user.name"><span v-else>{{ initials(user.name) }}</span></span>
                  <span><strong>{{ user.name }}</strong><small>{{ user.email }}</small></span>
                  <i class="bx" :class="groupActionId === `add-${user.id}` ? 'bx-loader-alt bx-spin' : 'bx-plus-circle'"></i>
                </button>
                <p v-if="!manageSearching && !manageCandidates.length"><i class="bx bx-user-check"></i> No hay usuarios nuevos con esa búsqueda.</p>
              </div>

              <div class="managed-participant-list">
                <article v-for="participant in participants" :key="participant.id">
                  <span class="participant-avatar" :class="avatarTone(participant.name)">
                    <img v-if="participant.photo" :src="participant.photo" :alt="participant.name">
                    <span v-else>{{ initials(participant.name) }}</span>
                  </span>
                  <span class="managed-participant-copy"><strong>{{ participant.id === currentUser.id ? `${participant.name} (Tú)` : participant.name }}</strong><small>{{ participant.role === 'owner' ? 'Propietario' : participant.role === 'admin' ? 'Administrador' : 'Integrante' }}</small></span>
                  <div v-if="participant.role !== 'owner' && participant.id !== currentUser.id" class="participant-actions">
                    <button type="button" :disabled="groupActionId === `role-${participant.id}`" :title="participant.role === 'admin' ? 'Quitar administración' : 'Hacer administrador'" @click="toggleGroupAdmin(participant)"><i class="bx" :class="participant.role === 'admin' ? 'bx-user-minus' : 'bx-shield-quarter'"></i></button>
                    <button v-if="isGroupOwner" type="button" :disabled="groupActionId === `owner-${participant.id}`" title="Transferir propiedad" @click="transferGroupOwnership(participant)"><i class="bx bx-crown"></i></button>
                    <button class="participant-remove" type="button" :disabled="groupActionId === `remove-${participant.id}`" title="Retirar del grupo" @click="removeGroupParticipant(participant)"><i class="bx bx-trash"></i></button>
                  </div>
                  <i v-else-if="participant.role === 'owner'" class="owner-crown bx bx-crown" title="Propietario"></i>
                </article>
              </div>
            </section>
          </div>

          <div v-if="groupEditError" class="dialog-error"><i class="bx bx-error-circle"></i>{{ groupEditError }}</div>
          <div v-if="groupEditNotice" class="dialog-success"><i class="bx bx-check-circle"></i>{{ groupEditNotice }}</div>
          <footer class="group-manage-footer"><span><i class="bx bx-info-circle"></i> Los cambios se aplican inmediatamente.</span><button type="button" class="cancel-button" @click="closeGroupManager">Cerrar</button></footer>
        </section>
      </div>

      <div v-if="ackMessage" class="modal-layer" @click.self="ackMessage = null">
        <section class="ack-dialog" role="alertdialog" aria-modal="true">
          <span class="ack-dialog-icon"><i class="bx bx-check-shield"></i></span>
          <span class="hero-kicker">Confirmación explícita</span>
          <h2>¿Confirmas que recibiste y revisaste esta comunicación?</h2>
          <p>Esta acción registra fecha, versión y hash del contenido. No representa aceptación ni firma electrónica.</p>
          <label v-if="ackMessage.acknowledgement_comment_required">Comentario obligatorio<textarea v-model="ackComment" rows="3"></textarea></label>
          <div class="ack-dialog-actions"><button type="button" @click="ackMessage = null">Cancelar</button><button type="button" :disabled="ackSaving || (ackMessage.acknowledgement_comment_required && !ackComment.trim())" @click="acknowledge">Acusar recibo</button></div>
        </section>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.messaging-page {
  --msg-brand: #5654e8;
  --msg-brand-dark: #403dbf;
  --msg-brand-soft: #eeefff;
  --msg-ink: #16213a;
  --msg-muted: #748096;
  --msg-line: #e8ebf2;
  --msg-canvas: #f6f7fb;
  min-width: 0;
  color: var(--msg-ink);
}

button, input, textarea, select { font: inherit; }
button { -webkit-tap-highlight-color: transparent; }

.messaging-hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 20px; }
.hero-copy h1 { margin: 5px 0 4px; font-size: clamp(27px, 2.3vw, 36px); line-height: 1.1; font-weight: 750; letter-spacing: -.035em; }
.hero-copy p { margin: 0; color: var(--msg-muted); font-size: 14px; }
.hero-kicker { display: inline-flex; align-items: center; gap: 6px; color: var(--msg-brand); font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.hero-actions { display: flex; align-items: center; gap: 10px; }
.connection-state { display: inline-flex; align-items: center; gap: 8px; padding: 9px 12px; color: #647087; background: #fff; border: 1px solid var(--msg-line); border-radius: 12px; font-size: 11px; font-weight: 650; box-shadow: 0 3px 14px rgba(25, 35, 60, .04); }
.connection-dot { width: 7px; height: 7px; background: #f5a524; border-radius: 50%; box-shadow: 0 0 0 4px rgba(245, 165, 36, .12); }
.connection-state.connected .connection-dot { background: #25b47e; box-shadow: 0 0 0 4px rgba(37, 180, 126, .12); }
.create-group-button, .new-message-button { height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 16px; border-radius: 12px; font-weight: 700; font-size: 13px; transition: transform .18s ease, box-shadow .18s ease, background .18s ease; }
.create-group-button { color: #434d62; background: #fff; border: 1px solid var(--msg-line); }
.new-message-button { color: #fff; background: linear-gradient(135deg, #6664ee, #4c49d7); border: 0; box-shadow: 0 9px 22px rgba(86, 84, 232, .24); }
.create-group-button:hover, .new-message-button:hover { transform: translateY(-1px); }
.new-message-button:hover { box-shadow: 0 12px 28px rgba(86, 84, 232, .32); }

.messaging-alert { display: flex; gap: 9px; align-items: center; margin-bottom: 12px; padding: 11px 14px; border-radius: 12px; font-size: 13px; }
.messaging-alert--danger { color: #a83a47; background: #fff0f2; border: 1px solid #f5cdd3; }

.messenger-shell { display: grid; grid-template-columns: 350px minmax(440px, 1fr) 310px; height: calc(100vh - 184px); min-height: 620px; overflow: hidden; background: #fff; border: 1px solid rgba(222, 226, 237, .9); border-radius: 22px; box-shadow: 0 20px 55px rgba(25, 35, 60, .09); }
.conversation-panel, .thread-panel, .details-panel { min-width: 0; min-height: 0; }
.conversation-panel { display: flex; flex-direction: column; border-right: 1px solid var(--msg-line); background: #fff; }
.panel-overview { display: flex; align-items: center; justify-content: space-between; padding: 19px 20px 13px; }
.panel-overview > div { display: flex; flex-direction: column; }
.panel-overview span { color: var(--msg-muted); font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.panel-overview strong { margin-top: 2px; font-size: 16px; }
.panel-overview button { display: grid; place-items: center; width: 36px; height: 36px; color: var(--msg-brand); background: var(--msg-brand-soft); border: 0; border-radius: 11px; font-size: 18px; }
.conversation-tools { padding: 0 15px 13px; border-bottom: 1px solid var(--msg-line); }
.conversation-search, .user-search-field { display: flex; align-items: center; gap: 9px; padding: 0 12px; color: #929caf; background: #f7f8fb; border: 1px solid transparent; border-radius: 12px; transition: border .18s ease, background .18s ease, box-shadow .18s ease; }
.conversation-search { height: 42px; }
.conversation-search:focus-within, .user-search-field:focus-within { background: #fff; border-color: #c9cbfb; box-shadow: 0 0 0 3px rgba(86, 84, 232, .08); }
.conversation-search input, .user-search-field input { flex: 1; min-width: 0; height: 100%; color: var(--msg-ink); background: transparent; border: 0; outline: 0; font-size: 12px; }
.conversation-search button { display: grid; place-items: center; padding: 0; color: #9aa4b7; background: transparent; border: 0; font-size: 18px; }
.conversation-filters { display: flex; gap: 3px; margin-top: 12px; overflow-x: auto; scrollbar-width: none; }
.conversation-filters::-webkit-scrollbar { display: none; }
.conversation-filters button { flex: 0 0 auto; padding: 6px 9px; color: #798397; background: transparent; border: 0; border-radius: 8px; font-size: 10px; font-weight: 700; }
.conversation-filters button.active { color: var(--msg-brand-dark); background: var(--msg-brand-soft); }
.conversation-list { flex: 1; overflow-y: auto; padding: 8px; scrollbar-color: #d7dbe5 transparent; scrollbar-width: thin; }
.conversation-card { position: relative; width: 100%; display: flex; gap: 12px; padding: 12px; text-align: left; background: transparent; border: 1px solid transparent; border-radius: 14px; transition: background .16s ease, border .16s ease, transform .16s ease; }
.conversation-card:hover { background: #f8f8fc; }
.conversation-card.selected { background: linear-gradient(135deg, #f0f1ff 0%, #f8f7ff 100%); border-color: #e0e0fc; }
.conversation-card.selected::before { position: absolute; top: 18px; bottom: 18px; left: -8px; width: 3px; content: ""; background: var(--msg-brand); border-radius: 0 5px 5px 0; }
.conversation-avatar, .message-avatar, .participant-avatar { position: relative; flex: 0 0 auto; display: grid; place-items: center; overflow: hidden; color: #fff; border-radius: 14px; font-weight: 750; letter-spacing: -.02em; }
.conversation-avatar { width: 47px; height: 47px; font-size: 14px; }
.conversation-avatar > img, .message-avatar > img, .participant-avatar > img { width: 100%; height: 100%; object-fit: cover; }
.conversation-avatar > i { font-size: 21px; }
.tone-indigo { background: linear-gradient(145deg, #6a67ec, #4947c9); }
.tone-violet { background: linear-gradient(145deg, #9b6de3, #7748c0); }
.tone-cyan { background: linear-gradient(145deg, #38a9d8, #247cae); }
.tone-emerald { background: linear-gradient(145deg, #39b98a, #238862); }
.tone-coral { background: linear-gradient(145deg, #ef7e73, #cf574e); }
.tone-blue { background: linear-gradient(145deg, #5489e8, #3567bd); }
.presence-dot { position: absolute; right: 1px; bottom: 1px; width: 10px; height: 10px; background: #2cc389; border: 2px solid #fff; border-radius: 50%; }
.conversation-content { flex: 1; min-width: 0; }
.conversation-line { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.conversation-line strong { overflow: hidden; font-size: 12.5px; font-weight: 720; text-overflow: ellipsis; white-space: nowrap; }
.conversation-line time { flex: 0 0 auto; color: #9aa3b4; font-size: 9px; }
.conversation-line--preview { margin-top: 4px; }
.conversation-line--preview small { flex: 1; min-width: 0; overflow: hidden; color: var(--msg-muted); font-size: 10.5px; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
.conversation-line--preview b { min-width: 19px; height: 19px; display: grid; place-items: center; padding: 0 5px; color: #fff; background: var(--msg-brand); border-radius: 10px; font-size: 9px; }
.conversation-line--preview i { color: var(--msg-brand); font-size: 13px; }
.conversation-card.unread .conversation-line strong { font-weight: 800; }
.conversation-type { display: inline-flex; align-items: center; gap: 3px; margin-top: 5px; color: #8a91a1; font-size: 8.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.conversation-skeletons span { display: block; height: 72px; margin: 4px; background: linear-gradient(90deg, #f3f4f7 25%, #fafafd 45%, #f3f4f7 65%); background-size: 220% 100%; border-radius: 14px; animation: shimmer 1.4s infinite linear; }
.empty-conversations { height: 100%; min-height: 340px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 28px; text-align: center; }
.empty-conversations > span { width: 58px; height: 58px; display: grid; place-items: center; color: var(--msg-brand); background: var(--msg-brand-soft); border-radius: 18px; font-size: 27px; }
.empty-conversations strong { margin-top: 14px; font-size: 15px; }
.empty-conversations p { max-width: 240px; margin: 6px 0 14px; color: var(--msg-muted); font-size: 11px; line-height: 1.6; }
.empty-conversations button { padding: 8px 12px; color: var(--msg-brand); background: transparent; border: 1px solid #dcdcf8; border-radius: 9px; font-size: 11px; font-weight: 700; }

.thread-panel { display: flex; flex-direction: column; background: var(--msg-canvas); border-right: 1px solid var(--msg-line); }
.thread-header { z-index: 3; flex: 0 0 72px; display: flex; align-items: center; gap: 11px; padding: 0 18px; background: rgba(255, 255, 255, .96); border-bottom: 1px solid var(--msg-line); backdrop-filter: blur(14px); }
.conversation-avatar--header { width: 43px; height: 43px; border-radius: 13px; }
.thread-identity { min-width: 0; }
.thread-identity h2 { max-width: 360px; margin: 0; overflow: hidden; font-size: 14px; font-weight: 750; text-overflow: ellipsis; white-space: nowrap; }
.thread-identity span { display: flex; align-items: center; gap: 4px; margin-top: 3px; color: var(--msg-muted); font-size: 10px; }
.thread-header-actions { margin-left: auto; display: flex; align-items: center; gap: 7px; }
.thread-header-actions > button, .thread-back { width: 36px; height: 36px; display: grid; place-items: center; color: #7b8598; background: #f5f6f9; border: 0; border-radius: 11px; font-size: 20px; }
.thread-back { display: none; }
.locked-pill { display: inline-flex; align-items: center; gap: 5px; padding: 6px 8px; color: #a06418; background: #fff6e5; border-radius: 8px; font-size: 9px; font-weight: 750; }
.message-timeline { flex: 1; overflow-x: hidden; overflow-y: auto; padding: 0 clamp(16px, 3vw, 38px) 28px; background-color: #f7f8fb; background-image: radial-gradient(circle at 12% 18%, rgba(86, 84, 232, .035) 0, transparent 24%), radial-gradient(circle at 88% 64%, rgba(37, 180, 170, .035) 0, transparent 20%); scrollbar-color: #d2d7e2 transparent; scrollbar-width: thin; }
.timeline-intro { max-width: 470px; margin: 34px auto 26px; text-align: center; }
.conversation-avatar--intro { width: 58px; height: 58px; margin: 0 auto 12px; border-radius: 18px; font-size: 18px; box-shadow: 0 10px 25px rgba(42, 50, 90, .14); }
.conversation-avatar--intro i { font-size: 26px; }
.timeline-intro h3 { margin: 0; font-size: 17px; font-weight: 760; }
.timeline-intro p { margin: 5px auto 9px; color: var(--msg-muted); font-size: 11px; line-height: 1.55; }
.privacy-note { display: inline-flex; align-items: center; gap: 5px; color: #8992a4; font-size: 9px; }
.load-older { display: flex; align-items: center; gap: 6px; margin: 0 auto 18px; padding: 7px 11px; color: #69758b; background: #fff; border: 1px solid var(--msg-line); border-radius: 10px; font-size: 10px; font-weight: 700; }
.date-divider { display: flex; align-items: center; gap: 12px; margin: 21px 0; color: #8d96a7; font-size: 9px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
.date-divider::before, .date-divider::after { flex: 1; height: 1px; content: ""; background: #e4e7ee; }
.date-divider span { padding: 5px 10px; background: #eef0f5; border-radius: 20px; }
.message-item { position: relative; display: flex; align-items: flex-end; gap: 9px; margin: 9px 0; }
.message-item.own { flex-direction: row-reverse; }
.message-avatar { width: 31px; height: 31px; border-radius: 10px; font-size: 9px; }
.message-stack { max-width: min(72%, 650px); min-width: 0; }
.message-sender { display: block; margin: 0 0 4px 3px; color: #6d778b; font-size: 9.5px; font-weight: 750; }
.message-bubble { position: relative; min-width: 90px; padding: 10px 12px 8px; background: #fff; border: 1px solid #e5e8ef; border-radius: 6px 16px 16px; box-shadow: 0 4px 14px rgba(27, 39, 67, .045); }
.message-item.own .message-bubble { color: #fff; background: linear-gradient(145deg, #6462e9, #504dd5); border-color: transparent; border-radius: 16px 6px 16px 16px; box-shadow: 0 7px 20px rgba(73, 70, 198, .16); }
.message-bubble p { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 12px; line-height: 1.5; }
.message-meta { display: flex; align-items: center; justify-content: flex-end; gap: 4px; min-height: 13px; margin-top: 4px; color: #959eae; font-size: 8.5px; }
.message-item.own .message-meta { color: rgba(255, 255, 255, .7); }
.message-meta i { font-size: 13px; }
.message-error { color: #ffd2d6; font-weight: 700; }
.message-subject { display: block; margin-bottom: 6px; font-size: 14px; }
.message-item.formal .message-stack { max-width: min(84%, 720px); }
.message-item.formal .message-bubble { padding: 14px; color: var(--msg-ink); background: #fff; border-top: 3px solid var(--msg-brand); border-radius: 8px 15px 15px; }
.formal-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 11px; color: var(--msg-brand); font-size: 9px; font-weight: 850; letter-spacing: .08em; text-transform: uppercase; }
.formal-heading span { display: flex; align-items: center; gap: 5px; }
.priority-tag { padding: 4px 7px; color: #687286; background: #f0f2f5; border-radius: 8px; font-size: 8px; }
.priority-tag--high { color: #9b6518; background: #fff3db; }
.priority-tag--urgent { color: #bd3d4b; background: #ffeaed; }
.reply-preview { display: flex; flex-direction: column; margin: -2px 0 8px; padding: 7px 9px; color: #657086; background: #f3f4f8; border-left: 3px solid #9795ef; border-radius: 7px; }
.message-item.own:not(.formal) .reply-preview { color: rgba(255,255,255,.78); background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.65); }
.reply-preview strong { font-size: 9px; }
.reply-preview span { overflow: hidden; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.message-attachments { display: grid; gap: 6px; margin-top: 9px; }
.message-attachments a { display: flex; align-items: center; gap: 8px; min-width: 235px; padding: 8px; color: var(--msg-ink); background: #f6f7fa; border: 1px solid #eceef3; border-radius: 10px; }
.message-item.own:not(.formal) .message-attachments a { color: #fff; background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.14); }
.message-attachments a > span:first-child { width: 32px; height: 32px; display: grid; place-items: center; color: var(--msg-brand); background: #e8e9ff; border-radius: 8px; font-size: 17px; }
.message-attachments a > span:nth-child(2) { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.message-attachments strong { overflow: hidden; font-size: 9.5px; text-overflow: ellipsis; white-space: nowrap; }
.message-attachments small { color: #8b95a7; font-size: 8px; }
.message-attachments > a > i { color: #929aac; font-size: 16px; }
.acknowledgement-card { display: flex; align-items: center; gap: 8px; margin-top: 10px; padding: 9px; color: #7c5a19; background: #fff8e7; border: 1px solid #f1dfad; border-radius: 10px; }
.acknowledgement-card > i { font-size: 22px; }
.acknowledgement-card > span { flex: 1; display: flex; flex-direction: column; }
.acknowledgement-card strong { font-size: 9.5px; }
.acknowledgement-card small { font-size: 8.5px; }
.acknowledgement-card button { padding: 5px 8px; color: #fff; background: #936914; border: 0; border-radius: 7px; font-size: 8.5px; font-weight: 750; }
.acknowledgement-card.acknowledged { color: #277954; background: #eaf8f2; border-color: #c5e9d9; }
.reaction-list { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.message-item.own .reaction-list { justify-content: flex-end; }
.reaction-list button { padding: 3px 7px; color: #657086; background: #fff; border: 1px solid #e3e6ed; border-radius: 12px; font-size: 10px; box-shadow: 0 2px 6px rgba(25, 35, 60, .04); }
.reaction-list button.mine { color: var(--msg-brand); background: #eeefff; border-color: #cfd0f8; }
.reaction-list span { font-size: 8px; font-weight: 700; }
.message-actions { align-self: center; display: flex; gap: 2px; padding: 3px; visibility: hidden; opacity: 0; background: #fff; border: 1px solid var(--msg-line); border-radius: 9px; box-shadow: 0 7px 18px rgba(25,35,60,.09); transition: opacity .15s ease; }
.message-item:hover .message-actions, .message-actions:focus-within { visibility: visible; opacity: 1; }
.message-actions button { width: 27px; height: 27px; display: grid; place-items: center; padding: 0; color: #707b8f; background: transparent; border: 0; border-radius: 6px; font-size: 14px; }
.message-actions button:hover { color: var(--msg-brand); background: var(--msg-brand-soft); }

.composer { flex: 0 0 auto; padding: 10px 14px 13px; background: #fff; border-top: 1px solid var(--msg-line); }
.composer-box { position: relative; overflow: hidden; background: #fff; border: 1px solid #dfe3eb; border-radius: 15px; box-shadow: 0 5px 18px rgba(28, 39, 65, .055); transition: border .18s ease, box-shadow .18s ease; }
.composer-box:focus-within { border-color: #bfc0f5; box-shadow: 0 5px 18px rgba(65, 63, 183, .09), 0 0 0 3px rgba(86,84,232,.06); }
.composer textarea { width: 100%; min-height: 43px; max-height: 140px; display: block; resize: none; padding: 12px 14px 5px; color: var(--msg-ink); background: transparent; border: 0; outline: 0; font-size: 12px; line-height: 1.45; }
.composer-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 6px 8px; }
.composer-tools, .send-tools { display: flex; align-items: center; gap: 3px; }
.composer-tools > button, .composer-tools > label { width: 31px; height: 31px; display: grid; place-items: center; margin: 0; color: #778297; background: transparent; border: 0; border-radius: 8px; cursor: pointer; font-size: 18px; }
.composer-tools > button:hover, .composer-tools > label:hover, .composer-tools > button.active { color: var(--msg-brand); background: var(--msg-brand-soft); }
.composer-tools select { height: 29px; max-width: 120px; padding: 0 24px 0 7px; color: #758095; background-color: #f6f7fa; border: 0; border-radius: 7px; outline: 0; font-size: 9px; }
.send-tools small { margin-right: 6px; color: #9da5b4; font-size: 8.5px; }
.send-tools button { width: 34px; height: 34px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #6664ee, #4d4ad3); border: 0; border-radius: 10px; font-size: 17px; box-shadow: 0 6px 14px rgba(86,84,232,.24); }
.send-tools button:disabled { opacity: .45; box-shadow: none; }
.composer-reply { display: flex; align-items: center; gap: 9px; margin-bottom: 7px; padding: 8px 10px; color: var(--msg-brand); background: #f2f2ff; border-radius: 10px; }
.composer-reply > i { font-size: 19px; }
.composer-reply > span { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.composer-reply strong { font-size: 9.5px; }
.composer-reply small { overflow: hidden; color: #7a8397; font-size: 8.5px; text-overflow: ellipsis; white-space: nowrap; }
.composer-reply button, .composer-uploads button { padding: 0; color: #8b94a5; background: transparent; border: 0; font-size: 17px; }
.composer-uploads { display: flex; gap: 7px; margin-bottom: 7px; overflow-x: auto; }
.composer-uploads > span { flex: 0 0 auto; display: flex; align-items: center; gap: 7px; min-width: 190px; max-width: 270px; padding: 7px 9px; color: #637087; background: #f6f7fa; border: 1px solid #e9ebf0; border-radius: 10px; }
.composer-uploads > span > i { color: var(--msg-brand); font-size: 19px; }
.composer-uploads > span > span { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.composer-uploads strong { overflow: hidden; font-size: 9px; text-overflow: ellipsis; white-space: nowrap; }
.composer-uploads small { color: #38a378; font-size: 8px; }
.formal-settings { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; margin-bottom: 7px; padding: 8px 10px; background: #f6f7fa; border: 1px solid #e9ebf0; border-radius: 10px; }
.setting-check, .mini-check { display: flex; align-items: center; gap: 5px; margin: 0; color: #606c81; font-size: 9px; }
.setting-check span { display: flex; align-items: center; gap: 4px; font-weight: 700; }
.due-field { display: flex; align-items: center; gap: 5px; margin: 0; color: #606c81; font-size: 9px; }
.due-field input { height: 27px; padding: 0 6px; color: #606c81; background: #fff; border: 1px solid #dfe2e9; border-radius: 6px; font-size: 8.5px; }
.composer-error, .composer-locked { display: flex; align-items: center; gap: 7px; margin-bottom: 7px; padding: 8px 10px; border-radius: 9px; font-size: 9.5px; }
.composer-error { color: #aa3b49; background: #fff0f2; }
.composer-locked { justify-content: center; margin: 0; padding: 13px; color: #7e6a44; background: #fff8e9; border: 1px solid #f2e2bc; }
.upload-progress { position: absolute; right: 0; bottom: 0; left: 0; height: 2px; background: #e9eafb; }
.upload-progress span { display: block; height: 100%; background: var(--msg-brand); transition: width .2s ease; }

.thread-welcome { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; text-align: center; background: radial-gradient(circle at 50% 44%, #fff 0, #f7f8fb 55%); }
.welcome-visual { position: relative; width: 116px; height: 116px; display: grid; place-items: center; margin-bottom: 20px; color: var(--msg-brand); background: linear-gradient(145deg, #fff, #eeefff); border: 1px solid #e1e2fa; border-radius: 35px; box-shadow: 0 22px 50px rgba(68,65,180,.13); transform: rotate(-4deg); }
.welcome-visual > i { font-size: 52px; transform: rotate(4deg); }
.orb { position: absolute; width: 20px; height: 20px; background: #61cbb2; border: 4px solid #fff; border-radius: 50%; box-shadow: 0 5px 14px rgba(44,161,134,.25); }
.orb--one { top: -7px; right: 4px; }
.orb--two { bottom: 8px; left: -9px; width: 15px; height: 15px; background: #f5a55a; }
.thread-welcome h2 { margin: 7px 0 6px; font-size: 23px; font-weight: 760; letter-spacing: -.025em; }
.thread-welcome p { max-width: 410px; margin: 0; color: var(--msg-muted); font-size: 12px; line-height: 1.6; }
.welcome-actions { display: flex; gap: 8px; margin-top: 20px; }
.welcome-actions button { display: flex; align-items: center; gap: 6px; padding: 9px 13px; color: #fff; background: var(--msg-brand); border: 0; border-radius: 10px; font-size: 10px; font-weight: 750; }
.welcome-actions button:last-child { color: var(--msg-brand); background: #fff; border: 1px solid #d9daf8; }

.details-panel { overflow-y: auto; padding: 25px 18px; background: #fff; scrollbar-width: thin; }
.details-hero { text-align: center; }
.conversation-avatar--details { width: 68px; height: 68px; margin: 0 auto 12px; border-radius: 21px; font-size: 20px; box-shadow: 0 11px 28px rgba(36,47,76,.15); }
.conversation-avatar--details i { font-size: 29px; }
.details-hero h3 { margin: 0 0 6px; overflow-wrap: anywhere; font-size: 16px; font-weight: 760; }
.details-type { display: inline-flex; align-items: center; gap: 4px; padding: 4px 7px; color: var(--msg-brand); background: var(--msg-brand-soft); border-radius: 7px; font-size: 8px; font-weight: 800; text-transform: uppercase; }
.details-hero p { margin: 11px auto 0; color: var(--msg-muted); font-size: 10px; line-height: 1.55; }
.details-manage-button { display:inline-flex;align-items:center;gap:5px;margin-top:12px;padding:7px 10px;color:var(--msg-brand);background:var(--msg-brand-soft);border:1px solid #dfe0fa;border-radius:9px;font-size:9px;font-weight:750; }
.details-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 22px 0; }
.details-stats > div { display: grid; grid-template-columns: 31px 1fr; grid-template-rows: auto auto; column-gap: 7px; padding: 10px; text-align: left; background: #f7f8fb; border: 1px solid #eceef3; border-radius: 12px; }
.details-stats > div > span { grid-row: 1 / 3; width: 31px; height: 31px; display: grid; place-items: center; color: var(--msg-brand); background: #eaebff; border-radius: 9px; font-size: 16px; }
.details-stats strong { font-size: 13px; line-height: 1; }
.details-stats small { color: var(--msg-muted); font-size: 8px; }
.participant-section { padding-top: 18px; border-top: 1px solid var(--msg-line); }
.participant-section > header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 11px; }
.participant-section h4 { margin: 0; font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.participant-section header > span { min-width: 20px; height: 20px; display: grid; place-items: center; color: var(--msg-brand); background: var(--msg-brand-soft); border-radius: 8px; font-size: 8px; font-weight: 800; }
.participant-row { display: flex; align-items: center; gap: 8px; padding: 7px 3px; }
.participant-avatar { width: 32px; height: 32px; border-radius: 10px; font-size: 9px; }
.participant-row > span:nth-child(2) { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.participant-row strong { overflow: hidden; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
.participant-row small { color: var(--msg-muted); font-size: 8px; }
.participant-row > i { color: var(--msg-brand); font-size: 14px; }
.details-security { display: flex; align-items: center; gap: 8px; margin-top: 18px; padding: 10px; color: #58806f; background: #eff9f5; border: 1px solid #d8eee5; border-radius: 11px; }
.details-security > i { font-size: 18px; }
.details-security > span { display: flex; flex-direction: column; }
.details-security strong { font-size: 9px; }
.details-security small { font-size: 8px; }

.modal-layer { position: fixed; z-index: 1060; inset: 0; display: grid; place-items: center; padding: 18px; background: rgba(15, 23, 43, .62); backdrop-filter: blur(7px); }
.create-dialog, .ack-dialog { position: relative; width: min(680px, 100%); max-height: min(780px, calc(100vh - 36px)); overflow-y: auto; padding: 27px; background: #fff; border: 1px solid rgba(255,255,255,.5); border-radius: 22px; box-shadow: 0 30px 90px rgba(9,16,34,.3); }
.dialog-close { position: absolute; z-index: 2; top: 16px; right: 16px; width: 34px; height: 34px; display: grid; place-items: center; color: #7f899b; background: #f4f5f8; border: 0; border-radius: 10px; font-size: 20px; }
.create-heading { display: flex; align-items: flex-start; gap: 13px; padding-right: 34px; }
.create-icon { flex: 0 0 auto; width: 48px; height: 48px; display: grid; place-items: center; color: #fff; background: linear-gradient(145deg, #6866ec, #4c49ce); border-radius: 15px; font-size: 23px; box-shadow: 0 8px 20px rgba(86,84,232,.22); }
.create-heading h2 { margin: 3px 0 3px; font-size: 21px; font-weight: 760; letter-spacing: -.02em; }
.create-heading p { margin: 0; color: var(--msg-muted); font-size: 10.5px; }
.create-mode-switch { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin: 22px 0 18px; padding: 5px; background: #f3f4f7; border-radius: 14px; }
.create-mode-switch button { display: flex; align-items: center; gap: 9px; padding: 10px 12px; color: #788397; text-align: left; background: transparent; border: 1px solid transparent; border-radius: 10px; }
.create-mode-switch button > i { font-size: 20px; }
.create-mode-switch button > span { display: flex; flex-direction: column; }
.create-mode-switch strong { font-size: 10px; }
.create-mode-switch small { font-size: 8px; }
.create-mode-switch button.active { color: var(--msg-brand); background: #fff; border-color: #e5e6ed; box-shadow: 0 3px 10px rgba(25,35,60,.06); }
.field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-field { display: flex; flex-direction: column; gap: 6px; margin: 0; }
.form-field > span, .picker-label > span { color: #535f74; font-size: 9px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.form-field b, .picker-label b { color: #df5261; }
.form-field > span small { color: #9ba3b1; font-size: 8px; font-weight: 500; text-transform: none; }
.form-field > div { height: 42px; display: flex; align-items: center; gap: 7px; padding: 0 11px; color: #9aa3b4; border: 1px solid #dfe3eb; border-radius: 10px; }
.form-field input, .form-field textarea { width: 100%; color: var(--msg-ink); border: 0; outline: 0; font-size: 10.5px; }
.form-field textarea { height: 42px; resize: none; padding: 8px 10px; border: 1px solid #dfe3eb; border-radius: 10px; }
.member-picker { margin-top: 16px; }
.picker-label { display: flex; justify-content: space-between; margin-bottom: 7px; }
.picker-label small { color: var(--msg-brand); font-size: 8.5px; font-weight: 700; }
.selected-members { display: flex; gap: 6px; margin-bottom: 7px; padding-bottom: 2px; overflow-x: auto; }
.selected-members > span { flex: 0 0 auto; display: flex; align-items: center; gap: 5px; padding: 4px 6px 4px 4px; color: #536077; background: #eff0ff; border: 1px solid #dddffc; border-radius: 10px; font-size: 8.5px; font-weight: 700; }
.selected-members .participant-avatar { width: 24px; height: 24px; border-radius: 7px; font-size: 7px; }
.selected-members button { display: grid; place-items: center; padding: 0; color: #878fa0; background: transparent; border: 0; font-size: 14px; }
.user-search-field { height: 42px; border-color: #dfe3eb; background: #fff; }
.user-search-field--large { height: 48px; }
.people-results { max-height: 190px; display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-top: 7px; overflow-y: auto; }
.people-results > button { display: flex; align-items: center; gap: 8px; min-width: 0; padding: 7px; text-align: left; background: #fff; border: 1px solid #eceef3; border-radius: 10px; }
.people-results > button.selected { background: #f1f2ff; border-color: #d2d3f9; }
.people-results > button > span:nth-child(2) { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.people-results strong, .people-results small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.people-results strong { font-size: 9.5px; }
.people-results small { color: var(--msg-muted); font-size: 8px; }
.people-results > button > i { color: #9ba3b3; font-size: 17px; }
.people-results > button.selected > i { color: var(--msg-brand); }
.people-results > p { grid-column: 1 / -1; margin: 12px 0; color: #8b95a7; text-align: center; font-size: 9px; }
.people-results--direct { max-height: 320px; grid-template-columns: 1fr; margin-top: 10px; }
.people-results--direct > button { padding: 9px; }
.people-results--direct .participant-avatar { width: 39px; height: 39px; }
.search-prompt { min-height: 210px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--msg-muted); text-align: center; }
.search-prompt > span { width: 50px; height: 50px; display: grid; place-items: center; color: var(--msg-brand); background: var(--msg-brand-soft); border-radius: 16px; font-size: 24px; }
.search-prompt strong { margin-top: 10px; color: var(--msg-ink); font-size: 11px; }
.search-prompt p { margin: 3px 0; font-size: 9px; }
.admin-setting { display: flex; align-items: center; gap: 9px; margin: 14px 0 0; padding: 10px; background: #f8f8fc; border: 1px solid #eaecf2; border-radius: 11px; }
.admin-setting > span:first-child { width: 34px; height: 34px; display: grid; place-items: center; color: var(--msg-brand); background: var(--msg-brand-soft); border-radius: 9px; font-size: 17px; }
.admin-setting > span:nth-child(2) { flex: 1; display: flex; flex-direction: column; }
.admin-setting strong { font-size: 9.5px; }
.admin-setting small { color: var(--msg-muted); font-size: 8px; }
.switch-input { position: relative; width: 34px; height: 19px; appearance: none; background: #cfd4df; border-radius: 20px; cursor: pointer; transition: background .18s ease; }
.switch-input::after { position: absolute; top: 3px; left: 3px; width: 13px; height: 13px; content: ""; background: #fff; border-radius: 50%; transition: transform .18s ease; }
.switch-input:checked { background: var(--msg-brand); }
.switch-input:checked::after { transform: translateX(15px); }
.dialog-error { display: flex; align-items: center; gap: 6px; margin-top: 10px; padding: 8px 10px; color: #aa3e4b; background: #fff0f2; border-radius: 9px; font-size: 9px; }
.dialog-success { display:flex;align-items:center;gap:6px;margin-top:10px;padding:9px 11px;color:#267653;background:#eaf8f2;border:1px solid #cdebdc;border-radius:9px;font-size:9px; }
.create-footer { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-top: 17px; padding-top: 14px; border-top: 1px solid var(--msg-line); }
.create-footer > span { display: flex; align-items: center; gap: 5px; color: #8a94a6; font-size: 8px; }
.create-footer > div { display: flex; gap: 7px; }
.cancel-button, .confirm-button { height: 36px; padding: 0 13px; border-radius: 9px; font-size: 9.5px; font-weight: 750; }
.cancel-button { color: #687388; background: #fff; border: 1px solid #dfe2e9; }
.confirm-button { display: flex; align-items: center; gap: 6px; color: #fff; background: var(--msg-brand); border: 0; box-shadow: 0 6px 15px rgba(86,84,232,.2); }
.confirm-button:disabled { opacity: .45; box-shadow: none; }
.ack-dialog { width: min(500px, 100%); text-align: center; }
.ack-dialog-icon { width: 58px; height: 58px; display: grid; place-items: center; margin: 0 auto 12px; color: #27845c; background: #e7f8f1; border-radius: 18px; font-size: 28px; }
.ack-dialog h2 { margin: 8px 0; font-size: 20px; }
.ack-dialog p { color: var(--msg-muted); font-size: 10px; line-height: 1.6; }
.ack-dialog label { display: block; color: #657086; text-align: left; font-size: 9px; font-weight: 700; }
.ack-dialog textarea { width: 100%; margin-top: 5px; padding: 9px; border: 1px solid #dfe2e9; border-radius: 9px; outline: 0; }
.ack-dialog-actions { display: flex; justify-content: flex-end; gap: 7px; margin-top: 16px; }
.ack-dialog-actions button { padding: 8px 12px; color: #687388; background: #fff; border: 1px solid #dfe2e9; border-radius: 9px; font-size: 9px; font-weight: 750; }
.ack-dialog-actions button:last-child { color: #fff; background: var(--msg-brand); border-color: var(--msg-brand); }

.group-manage-dialog { width:min(900px,100%); }
.group-manage-heading { padding-bottom:18px;border-bottom:1px solid var(--msg-line); }
.group-manage-grid { display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:14px;margin-top:16px; }
.group-settings-card,.group-members-card { min-width:0;padding:15px;background:#f8f9fc;border:1px solid #e8eaf1;border-radius:15px; }
.group-settings-card > header,.group-members-card > header { display:flex;align-items:center;gap:9px;margin-bottom:14px; }
.group-settings-card > header > span,.group-members-card > header > span { width:35px;height:35px;display:grid;place-items:center;color:var(--msg-brand);background:#e9eaff;border-radius:10px;font-size:17px; }
.group-settings-card > header > div,.group-members-card > header > div { display:flex;flex-direction:column; }
.group-settings-card > header strong,.group-members-card > header strong { font-size:10.5px; }
.group-settings-card > header small,.group-members-card > header small { color:var(--msg-muted);font-size:8px; }
.manage-field { display:flex;flex-direction:column;gap:5px;margin:0 0 11px; }
.manage-field > span { color:#5f6a7f;font-size:8.5px;font-weight:800;letter-spacing:.04em;text-transform:uppercase; }
.manage-field > span small { color:#9ba3b2;font-weight:500;text-transform:none; }
.manage-field > div { height:39px;display:flex;align-items:center;gap:6px;padding:0 10px;color:#9aa3b4;background:#fff;border:1px solid #dfe2e9;border-radius:9px; }
.manage-field input,.manage-field textarea { width:100%;color:var(--msg-ink);background:#fff;border:0;outline:0;font-size:9.5px; }
.manage-field textarea { padding:9px 10px;resize:none;border:1px solid #dfe2e9;border-radius:9px; }
.manage-toggle-row { display:flex;align-items:center;gap:8px;margin:8px 0 0;padding:9px;background:#fff;border:1px solid #e4e7ed;border-radius:10px; }
.manage-toggle-row > span:first-child { width:31px;height:31px;display:grid;place-items:center;color:var(--msg-brand);background:var(--msg-brand-soft);border-radius:8px;font-size:15px; }
.manage-toggle-row > span:nth-child(2) { flex:1;display:flex;flex-direction:column; }
.manage-toggle-row strong { font-size:8.5px; }.manage-toggle-row small{color:var(--msg-muted);font-size:7.5px;line-height:1.35}
.manage-toggle-row--warning > span:first-child { color:#a46e1d;background:#fff4df; }
.save-group-button { width:100%;height:37px;display:flex;align-items:center;justify-content:center;gap:6px;margin-top:12px;color:#fff;background:linear-gradient(135deg,#6563ea,#504dd4);border:0;border-radius:10px;font-size:9px;font-weight:800;box-shadow:0 7px 16px rgba(86,84,232,.18); }
.save-group-button:disabled { opacity:.5;box-shadow:none; }
.group-member-search { background:#fff; }
.manage-candidates { max-height:126px;margin-top:7px;overflow-y:auto;background:#fff;border:1px solid #e4e7ed;border-radius:10px; }
.manage-candidates button { width:100%;display:flex;align-items:center;gap:7px;padding:7px;text-align:left;background:#fff;border:0;border-bottom:1px solid #eff1f4; }
.manage-candidates button:last-of-type{border-bottom:0}.manage-candidates button:hover{background:#f4f4fc}
.manage-candidates button > span:nth-child(2){flex:1;min-width:0;display:flex;flex-direction:column}.manage-candidates strong,.manage-candidates small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.manage-candidates strong{font-size:9px}.manage-candidates small{color:var(--msg-muted);font-size:7.5px}.manage-candidates button>i{color:var(--msg-brand);font-size:17px}
.manage-candidates p { display:flex;align-items:center;justify-content:center;gap:5px;margin:0;padding:13px;color:var(--msg-muted);font-size:8px; }
.managed-participant-list { max-height:265px;margin-top:10px;overflow-y:auto;padding-right:2px;scrollbar-width:thin; }
.managed-participant-list article { display:flex;align-items:center;gap:8px;padding:7px 4px;border-bottom:1px solid #e9ebf0; }
.managed-participant-list article:last-child{border-bottom:0}.managed-participant-copy{flex:1;min-width:0;display:flex;flex-direction:column}.managed-participant-copy strong{overflow:hidden;font-size:9px;text-overflow:ellipsis;white-space:nowrap}.managed-participant-copy small{color:var(--msg-muted);font-size:7.5px}
.participant-actions { display:flex;gap:3px; }
.participant-actions button { width:27px;height:27px;display:grid;place-items:center;padding:0;color:#6e798d;background:#fff;border:1px solid #dfe2e9;border-radius:7px;font-size:13px; }
.participant-actions button:hover{color:var(--msg-brand);background:var(--msg-brand-soft);border-color:#d3d4f7}.participant-actions .participant-remove:hover{color:#bd4351;background:#fff0f2;border-color:#f0ccd1}.participant-actions button:disabled{opacity:.45}
.owner-crown { color:#d69a29;font-size:17px; }
.group-manage-footer { display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:13px;padding-top:12px;border-top:1px solid var(--msg-line); }
.group-manage-footer > span { display:flex;align-items:center;gap:5px;color:var(--msg-muted);font-size:8px; }

@keyframes shimmer { to { background-position: -220% 0; } }

@media (max-width: 1350px) {
  .messenger-shell { grid-template-columns: 330px minmax(420px, 1fr); }
  .details-panel { display: none; }
  .thread-panel { border-right: 0; }
}

@media (max-width: 900px) {
  .messenger-shell { grid-template-columns: 300px minmax(380px, 1fr); }
  .connection-state { display: none; }
  .message-stack { max-width: 82%; }
}

@media (max-width: 767px) {
  .messaging-hero { align-items: flex-start; margin-bottom: 13px; }
  .hero-copy p { display: none; }
  .hero-copy h1 { font-size: 25px; }
  .create-group-button { display: none; }
  .new-message-button { width: 40px; height: 40px; padding: 0; }
  .new-message-button span { display: none; }
  .messenger-shell { display: block; height: calc(100vh - 137px); min-height: 520px; border-radius: 17px; }
  .conversation-panel, .thread-panel { width: 100%; height: 100%; border: 0; }
  .mobile-hidden { display: none; }
  .thread-back { display: grid; }
  .thread-header { padding: 0 10px; }
  .thread-header-actions > button { display: none; }
  .message-timeline { padding-right: 12px; padding-left: 12px; }
  .message-stack, .message-item.formal .message-stack { max-width: 88%; }
  .message-attachments a { min-width: 0; }
  .message-actions { display: none; }
  .composer { padding: 8px; }
  .send-tools small { display: none; }
  .composer-tools select { width: 31px; color: transparent; }
  .field-grid, .people-results { grid-template-columns: 1fr; }
  .create-dialog { padding: 20px 16px; border-radius: 18px; }
  .create-heading { padding-right: 30px; }
  .create-icon { width: 42px; height: 42px; }
  .create-heading h2 { font-size: 18px; }
  .create-mode-switch button { padding: 8px; }
  .create-mode-switch small { display: none; }
  .create-footer { align-items: flex-end; }
  .create-footer > span { max-width: 180px; }
  .manage-group-button { display:grid!important; }
  .group-manage-grid { grid-template-columns:1fr; }
  .group-manage-dialog { padding:20px 15px; }
  .managed-participant-list { max-height:220px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { scroll-behavior: auto !important; animation: none !important; transition: none !important; }
}
</style>
