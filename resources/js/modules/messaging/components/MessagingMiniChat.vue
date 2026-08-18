<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../api/messagingApi";
import { useMessagingRealtime } from "../composables/useMessagingRealtime";
import { messagingStore as store } from "../stores/messagingStore";

const route = useRoute();
const router = useRouter();
const expanded = ref(false);
const miniView = ref("list");
const available = ref(false);
const initializing = ref(false);
const initialized = ref(false);
const opening = ref(false);
const body = ref("");
const query = ref("");
const currentUser = ref({});
const error = ref("");
const acknowledgementMessage = ref(null);
const acknowledgementComment = ref("");
const acknowledgementSaving = ref(false);
const timeline = ref(null);
let titleTimer = null;
let baseDocumentTitle = "CNSC Gestión";

const hidden = computed(() => route.path.startsWith("/mensajeria"));
const activeId = computed(() => store.state.activeConversation?.public_id);
const messages = computed(() => store.state.messagesByConversation[activeId.value] || []);
const unreadCount = computed(() => Number(store.state.summary.unread_messages || 0));
const connectionLabel = computed(() => ({
  connected: "En tiempo real",
  connecting: "Conectando…",
  reconnecting: "Reconectando…",
  offline: "Sin conexión",
  unavailable: "Conexión no disponible",
}[store.state.realtimeConnectionState] || "Conectando…"));
const conversations = computed(() => {
  const term = query.value.trim().toLocaleLowerCase("es");
  if (!term) return store.state.conversations;
  return store.state.conversations.filter((conversation) => {
    const content = `${conversation.title} ${conversation.last_message?.body || ""} ${conversation.last_message?.subject || ""}`;
    return content.toLocaleLowerCase("es").includes(term);
  });
});

const initials = (name = "") => name.trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join("").toUpperCase() || "?";
const avatarTone = (name = "") => {
  const tones = ["mini-indigo", "mini-violet", "mini-cyan", "mini-emerald", "mini-coral"];
  const seed = [...name].reduce((sum, character) => sum + character.charCodeAt(0), 0);
  return tones[seed % tones.length];
};
const typeIcon = (type) => type === "group" ? "bx-group" : type === "announcement" ? "bx-broadcast" : "bx-user";
const conversationPhoto = (conversation) => conversation?.type === "direct" ? conversation.participants?.[0]?.photo : null;
const time = (date) => new Intl.DateTimeFormat("es-CL", { hour: "2-digit", minute: "2-digit" }).format(new Date(date));
const listTime = (date) => {
  if (!date) return "";
  const value = new Date(date);
  return value.toDateString() === new Date().toDateString()
    ? time(value)
    : new Intl.DateTimeFormat("es-CL", { day: "2-digit", month: "short" }).format(value);
};
const preview = (conversation) => conversation.last_message?.subject || conversation.last_message?.body || "Sin mensajes todavía";
const acknowledgementComplete = (message) => {
  const summary = message.acknowledgement_summary;
  return Number(summary?.total || 0) > 0 && Number(summary.acknowledged || 0) >= Number(summary.total || 0);
};

useMessagingRealtime(activeId);

async function initialize() {
  if (initialized.value || initializing.value || hidden.value) return;
  initializing.value = true;
  try {
    await Promise.all([store.loadConfig(), store.loadSummary()]);
    currentUser.value = store.state.config.user || {};
    available.value = Boolean(store.state.config.enabled ?? true);
    initialized.value = true;
  } catch (requestError) {
    available.value = false;
    if (requestError.response?.status !== 401) console.warn("No fue posible iniciar el chat flotante.", requestError);
  } finally {
    initializing.value = false;
  }
}

function updateTabAlert() {
  if (titleTimer) window.clearInterval(titleTimer);
  titleTimer = null;
  const count = unreadCount.value;
  if (!count) {
    document.title = baseDocumentTitle;
    return;
  }

  const alertTitle = `🔴 ${count} ${count === 1 ? "mensaje nuevo" : "mensajes nuevos"}`;
  if (document.visibilityState === "visible") {
    document.title = `(${count}) ${baseDocumentTitle}`;
    return;
  }

  let showingAlert = true;
  document.title = alertTitle;
  titleTimer = window.setInterval(() => {
    showingAlert = !showingAlert;
    document.title = showingAlert ? alertTitle : baseDocumentTitle;
  }, 1200);
}

function handleVisibilityChange() {
  updateTabAlert();
}

async function openPanel() {
  await initialize();
  if (!available.value) return;
  expanded.value = true;
  miniView.value = "list";
  store.close();
  await Promise.all([store.loadConversations({}, true), store.loadSummary()]);
}

function minimize() {
  expanded.value = false;
  miniView.value = "list";
  body.value = "";
  error.value = "";
  acknowledgementMessage.value = null;
  acknowledgementComment.value = "";
  if (!hidden.value) store.close();
}

async function openConversation(id) {
  opening.value = true;
  error.value = "";
  acknowledgementMessage.value = null;
  acknowledgementComment.value = "";
  try {
    await store.open(id);
    miniView.value = "thread";
    scrollBottom();
  } catch (requestError) {
    error.value = requestError.response?.data?.message || "No fue posible abrir la conversación.";
  } finally {
    opening.value = false;
  }
}

async function backToList() {
  miniView.value = "list";
  acknowledgementMessage.value = null;
  acknowledgementComment.value = "";
  store.close();
  await Promise.all([store.loadConversations({}, true), store.loadSummary()]);
}

async function send() {
  if (!body.value.trim() || !activeId.value || store.state.loading.sending) return;
  const content = body.value;
  body.value = "";
  error.value = "";
  try {
    await store.send({
      body: content,
      priority: "normal",
      formal: false,
      requires_acknowledgement: false,
      upload_tokens: [],
      sender_id: currentUser.value.id,
    });
    scrollBottom();
  } catch (requestError) {
    body.value = content;
    error.value = requestError.response?.data?.message || "No fue posible enviar el mensaje.";
  }
}

function onKeydown(event) {
  if (event.isComposing) return;
  if (event.key === "Enter" && !event.shiftKey) {
    event.preventDefault();
    send();
  }
}

async function toggleReaction(message, reaction) {
  try { await store.toggleReaction(message, reaction); } catch (_) { error.value = "No fue posible actualizar la reacción."; }
}

function requestAcknowledgement(message) {
  acknowledgementMessage.value = message;
  acknowledgementComment.value = "";
  error.value = "";
}

async function acknowledge() {
  const message = acknowledgementMessage.value;
  if (!message || acknowledgementSaving.value) return;
  if (message.acknowledgement_comment_required && !acknowledgementComment.value.trim()) return;
  acknowledgementSaving.value = true;
  error.value = "";
  try {
    await api.acknowledge(message.public_id, acknowledgementComment.value.trim() || null);
    await Promise.all([store.refreshMessage(message.public_id), store.loadSummary()]);
    acknowledgementMessage.value = null;
    acknowledgementComment.value = "";
  } catch (requestError) {
    error.value = requestError.response?.data?.message || "No fue posible registrar el acuse de recibo.";
  } finally {
    acknowledgementSaving.value = false;
  }
}

function scrollBottom() {
  nextTick(() => {
    if (timeline.value) timeline.value.scrollTop = timeline.value.scrollHeight;
  });
}

async function openFullMessaging() {
  const destination = activeId.value ? `/mensajeria/${activeId.value}` : "/mensajeria";
  expanded.value = false;
  await router.push(destination);
}

watch(() => messages.value.at(-1)?.public_id, scrollBottom);
watch(unreadCount, updateTabAlert);
watch(() => route.fullPath, () => nextTick(updateTabAlert));
watch(hidden, (isHidden) => {
  if (isHidden) {
    expanded.value = false;
    miniView.value = "list";
    return;
  }
  store.close();
  initialize();
}, { immediate: true });

onMounted(() => {
  baseDocumentTitle = document.title.replace(/^\(\d+\)\s*/, "") || "CNSC Gestión";
  updateTabAlert();
  document.addEventListener("visibilitychange", handleVisibilityChange);
});

onBeforeUnmount(() => {
  if (titleTimer) window.clearInterval(titleTimer);
  document.title = baseDocumentTitle;
  document.removeEventListener("visibilitychange", handleVisibilityChange);
});
</script>

<template>
  <div v-if="!hidden && (available || initializing)" class="mini-chat-host">
    <Transition name="mini-panel">
      <section v-if="expanded" class="mini-chat-panel" aria-label="Chat flotante">
        <header class="mini-chat-header">
          <template v-if="miniView === 'thread' && store.state.activeConversation">
            <button type="button" aria-label="Volver a conversaciones" @click="backToList"><i class="bx bx-left-arrow-alt"></i></button>
            <span class="mini-avatar" :class="avatarTone(store.state.activeConversation.title)">
              <img v-if="conversationPhoto(store.state.activeConversation)" :src="conversationPhoto(store.state.activeConversation)" :alt="store.state.activeConversation.title">
              <i v-else-if="store.state.activeConversation.type !== 'direct'" class="bx" :class="typeIcon(store.state.activeConversation.type)"></i>
              <span v-else>{{ initials(store.state.activeConversation.title) }}</span>
            </span>
            <div class="mini-header-copy"><strong>{{ store.state.activeConversation.title }}</strong><small><span></span> {{ connectionLabel }}</small></div>
          </template>
          <template v-else>
            <span class="mini-brand"><i class="bx bx-message-square-dots"></i></span>
            <div class="mini-header-copy"><strong>Mensajes</strong><small>{{ unreadCount ? `${unreadCount} sin leer` : 'Todo al día' }}</small></div>
          </template>
          <div class="mini-header-actions">
            <button type="button" title="Abrir mensajería completa" aria-label="Abrir mensajería completa" @click="openFullMessaging"><i class="bx bx-fullscreen"></i></button>
            <button type="button" title="Minimizar" aria-label="Minimizar chat" @click="minimize"><i class="bx bx-minus"></i></button>
          </div>
        </header>

        <template v-if="miniView === 'list'">
          <div class="mini-search"><i class="bx bx-search"></i><input v-model="query" aria-label="Buscar conversaciones" placeholder="Buscar conversaciones"><button v-if="query" type="button" @click="query = ''"><i class="bx bx-x"></i></button></div>
          <div class="mini-list">
            <button v-for="conversation in conversations" :key="conversation.public_id" type="button" class="mini-conversation" @click="openConversation(conversation.public_id)">
              <span class="mini-avatar" :class="avatarTone(conversation.title)">
                <img v-if="conversationPhoto(conversation)" :src="conversationPhoto(conversation)" :alt="conversation.title">
                <i v-else-if="conversation.type !== 'direct'" class="bx" :class="typeIcon(conversation.type)"></i>
                <span v-else>{{ initials(conversation.title) }}</span>
              </span>
              <span class="mini-conversation-copy"><span><strong>{{ conversation.title }}</strong><time>{{ listTime(conversation.last_message_at) }}</time></span><span><small>{{ preview(conversation) }}</small><b v-if="conversation.unread_count">{{ conversation.unread_count > 99 ? '99+' : conversation.unread_count }}</b></span></span>
            </button>

            <div v-if="opening" class="mini-state"><span class="spinner-border spinner-border-sm"></span> Abriendo conversación…</div>
            <div v-else-if="!conversations.length" class="mini-empty"><span><i class="bx bx-message-rounded"></i></span><strong>{{ query ? 'Sin resultados' : 'No hay conversaciones' }}</strong><small>{{ query ? 'Prueba con otro término.' : 'Abre la mensajería completa para comenzar.' }}</small></div>
          </div>
          <footer class="mini-list-footer"><button type="button" @click="openFullMessaging"><i class="bx bx-plus"></i> Nueva conversación</button><span>{{ connectionLabel }}</span></footer>
        </template>

        <template v-else-if="store.state.activeConversation">
          <div ref="timeline" class="mini-timeline">
            <div class="mini-thread-intro"><i class="bx bx-lock-alt"></i> Conversación privada y segura</div>
            <article v-for="message in messages" :key="message.public_id" class="mini-message" :class="{ own: message.sender_id === currentUser.id }">
              <span v-if="message.sender_id !== currentUser.id" class="mini-message-sender">{{ message.sender?.name }}</span>
              <div class="mini-bubble">
                <strong v-if="message.subject">{{ message.subject }}</strong>
                <p>{{ message.body }}</p>
                <a v-for="attachment in message.attachments" :key="attachment.public_id" :href="attachment.download_url"><i class="bx bx-file"></i><span>{{ attachment.name }}</span><i class="bx bx-download"></i></a>
                <div
                  v-if="message.requires_acknowledgement"
                  class="mini-acknowledgement"
                  :class="{ complete: message.acknowledgement_status === 'acknowledged' || (message.sender_id === currentUser.id && acknowledgementComplete(message)), overdue: message.acknowledgement_status === 'overdue' }"
                >
                  <i :class="message.acknowledgement_status === 'acknowledged' || (message.sender_id === currentUser.id && acknowledgementComplete(message)) ? 'bx bx-check-shield' : 'bx bx-time-five'"></i>
                  <span v-if="message.sender_id === currentUser.id">
                    <strong>{{ acknowledgementComplete(message) ? 'Acuse completado' : 'Acuses pendientes' }}</strong>
                    <small>{{ message.acknowledgement_summary?.acknowledged || 0 }} de {{ message.acknowledgement_summary?.total || 0 }} confirmados</small>
                  </span>
                  <span v-else-if="message.acknowledgement_status === 'acknowledged'">
                    <strong>Acuse registrado</strong><small>Recepción confirmada</small>
                  </span>
                  <span v-else>
                    <strong>{{ message.acknowledgement_status === 'overdue' ? 'Acuse vencido' : 'Acuse pendiente' }}</strong><small>Confirma la recepción</small>
                  </span>
                  <button v-if="message.sender_id !== currentUser.id && message.acknowledgement_status !== 'acknowledged'" type="button" @click="requestAcknowledgement(message)">Confirmar</button>
                </div>
                <footer>
                  <button v-if="store.state.config.reactions?.length" type="button" title="Reaccionar" @click="toggleReaction(message, store.state.config.reactions[0])"><i class="bx bx-smile"></i></button>
                  <time>{{ time(message.sent_at) }}</time><i v-if="message.sender_id === currentUser.id" class="bx bx-check-double"></i>
                </footer>
              </div>
              <div v-if="message.reactions?.length" class="mini-reactions">
                <button v-for="reaction in message.reactions" :key="reaction.reaction" type="button" :class="{ mine: reaction.mine }" @click="toggleReaction(message, reaction.reaction)">{{ reaction.reaction }} <span>{{ reaction.count }}</span></button>
              </div>
            </article>
          </div>

          <section v-if="acknowledgementMessage" class="mini-ack-sheet" aria-label="Confirmar acuse de recibo">
            <header><span><i class="bx bx-check-shield"></i></span><div><strong>Acuse de recibo</strong><small>Confirma que recibiste y revisaste el mensaje.</small></div></header>
            <textarea v-if="acknowledgementMessage.acknowledgement_comment_required" v-model="acknowledgementComment" rows="2" placeholder="Comentario obligatorio"></textarea>
            <div><button type="button" @click="acknowledgementMessage = null; acknowledgementComment = ''">Cancelar</button><button type="button" :disabled="acknowledgementSaving || (acknowledgementMessage.acknowledgement_comment_required && !acknowledgementComment.trim())" @click="acknowledge"><i v-if="acknowledgementSaving" class="bx bx-loader-alt bx-spin"></i>{{ acknowledgementSaving ? 'Registrando…' : 'Acusar recibo' }}</button></div>
          </section>

          <form class="mini-composer" @submit.prevent="send">
            <div v-if="error" class="mini-error"><i class="bx bx-error-circle"></i>{{ error }}</div>
            <div v-if="store.state.activeConversation.is_locked" class="mini-locked"><i class="bx bx-lock-alt"></i> Conversación bloqueada</div>
            <div v-else class="mini-composer-box"><textarea v-model="body" rows="1" placeholder="Escribe un mensaje…" @keydown="onKeydown"></textarea><button type="submit" :disabled="!body.trim() || store.state.loading.sending"><i class="bx" :class="store.state.loading.sending ? 'bx-loader-alt bx-spin' : 'bx-send'"></i></button></div>
          </form>
        </template>
      </section>
    </Transition>

    <button v-if="!expanded" class="mini-chat-launcher" type="button" :aria-label="unreadCount ? `Abrir chat, ${unreadCount} mensajes sin leer` : 'Abrir chat'" :aria-expanded="expanded" @click="openPanel">
      <span class="launcher-ripple" :class="{ active: unreadCount }"></span>
      <span class="mini-launcher-glyph"><i v-if="initializing" class="bx bx-loader-alt bx-spin"></i><i v-else class="bx bx-message-rounded-dots"></i></span>
      <b v-if="unreadCount">{{ unreadCount > 99 ? '99+' : unreadCount }}</b>
    </button>
  </div>
</template>

<style scoped>
.mini-chat-host { --mini-brand:#5755e8; --mini-ink:#17213a; --mini-muted:#7b8598; --mini-line:#e7e9f0; position:fixed; right:22px; bottom:86px; z-index:1045; color:var(--mini-ink); font-family:inherit; }
.mini-chat-launcher { position:relative; width:54px; height:54px; display:flex; align-items:center; justify-content:center; padding:0; color:#fff; background:linear-gradient(145deg,#706ef3,#4845ca); border:1px solid rgba(255,255,255,.32); border-radius:50%; box-shadow:0 12px 30px rgba(70,67,195,.32),inset 0 1px 0 rgba(255,255,255,.2); transition:transform .18s ease,box-shadow .18s ease; }
.mini-launcher-glyph { width:28px; height:28px; display:flex; align-items:center; justify-content:center; line-height:1; transform:translateY(-1px); }
.mini-launcher-glyph i { display:block; font-size:27px; line-height:1; }
.mini-chat-launcher:hover { transform:translateY(-2px); box-shadow:0 18px 40px rgba(70,67,195,.45); }
.mini-chat-launcher b { position:absolute; top:-7px; right:-7px; min-width:22px; height:22px; display:grid; place-items:center; padding:0 5px; color:#fff; background:#ec5263; border:3px solid #fff; border-radius:12px; font-size:9px; }
.launcher-ripple.active { position:absolute; inset:-6px; border:2px solid rgba(87,85,232,.25); border-radius:23px; animation:mini-pulse 2s infinite; }
.mini-chat-panel { width:min(376px,calc(100vw - 28px)); height:min(540px,calc(100vh - 130px)); display:flex; flex-direction:column; overflow:hidden; background:#fff; border:1px solid rgba(225,228,237,.95); border-radius:19px; box-shadow:0 22px 64px rgba(18,28,52,.22); transform-origin:bottom right; }
.mini-chat-header { flex:0 0 62px; display:flex; align-items:center; gap:9px; padding:0 12px; color:#fff; background:linear-gradient(135deg,#625fe9,#4946cc); }
.mini-chat-header > button,.mini-header-actions button { width:32px; height:32px; display:grid; place-items:center; padding:0; color:rgba(255,255,255,.88); background:rgba(255,255,255,.1); border:0; border-radius:9px; font-size:18px; }
.mini-brand { width:38px; height:38px; display:grid; place-items:center; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.13); border-radius:12px; font-size:20px; }
.mini-avatar { flex:0 0 auto; width:39px; height:39px; display:grid; place-items:center; overflow:hidden; color:#fff; border-radius:12px; font-size:10px; font-weight:800; }
.mini-avatar img { width:100%; height:100%; object-fit:cover; }
.mini-avatar > i { font-size:18px; }
.mini-indigo { background:linear-gradient(145deg,#6a67ec,#4947c9); }.mini-violet{background:linear-gradient(145deg,#9b6de3,#7043ba)}.mini-cyan{background:linear-gradient(145deg,#39abd9,#267eae)}.mini-emerald{background:linear-gradient(145deg,#39b98a,#238862)}.mini-coral{background:linear-gradient(145deg,#ef7e73,#c9574f)}
.mini-header-copy { flex:1; min-width:0; display:flex; flex-direction:column; }
.mini-header-copy strong { overflow:hidden; font-size:12px; text-overflow:ellipsis; white-space:nowrap; }
.mini-header-copy small { display:flex; align-items:center; gap:4px; color:rgba(255,255,255,.7); font-size:8px; }
.mini-header-copy small span { width:5px; height:5px; background:#61dfad; border-radius:50%; }
.mini-header-actions { display:flex; gap:4px; }
.mini-search { flex:0 0 48px; display:flex; align-items:center; gap:7px; margin:11px 12px 5px; padding:0 11px; color:#9aa3b4; background:#f5f6f9; border:1px solid transparent; border-radius:11px; }
.mini-search:focus-within { background:#fff; border-color:#cfd0f6; }
.mini-search input { flex:1; min-width:0; color:var(--mini-ink); background:transparent; border:0; outline:0; font-size:10px; }
.mini-search button { display:grid; place-items:center; padding:0; color:#919bad; background:transparent; border:0; font-size:16px; }
.mini-list { flex:1; overflow-y:auto; padding:5px 7px; scrollbar-width:thin; }
.mini-conversation { width:100%; display:flex; align-items:center; gap:9px; padding:9px; text-align:left; background:#fff; border:0; border-radius:12px; transition:background .15s ease; }
.mini-conversation:hover { background:#f4f4fc; }
.mini-conversation-copy { flex:1; min-width:0; }
.mini-conversation-copy > span { display:flex; align-items:center; justify-content:space-between; gap:7px; }
.mini-conversation-copy strong,.mini-conversation-copy small { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.mini-conversation-copy strong { font-size:10.5px; }.mini-conversation-copy small{flex:1;color:var(--mini-muted);font-size:8.5px}.mini-conversation-copy time{flex:0 0 auto;color:#9aa3b3;font-size:8px}
.mini-conversation-copy b { min-width:18px; height:18px; display:grid; place-items:center; padding:0 4px; color:#fff; background:var(--mini-brand); border-radius:9px; font-size:8px; }
.mini-state { display:flex; align-items:center; justify-content:center; gap:7px; padding:14px; color:var(--mini-muted); font-size:9px; }
.mini-empty { height:100%; min-height:290px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; }
.mini-empty > span { width:49px; height:49px; display:grid; place-items:center; color:var(--mini-brand); background:#eeefff; border-radius:15px; font-size:23px; }.mini-empty strong{margin-top:10px;font-size:11px}.mini-empty small{margin-top:3px;color:var(--mini-muted);font-size:8.5px}
.mini-list-footer { flex:0 0 48px; display:flex; align-items:center; justify-content:space-between; padding:0 13px; border-top:1px solid var(--mini-line); }
.mini-list-footer button { display:flex; align-items:center; gap:4px; padding:6px 8px; color:var(--mini-brand); background:#eeefff; border:0; border-radius:8px; font-size:8.5px; font-weight:750; }.mini-list-footer span{color:#9ba4b4;font-size:7.5px}
.mini-timeline { flex:1; overflow-x:hidden; overflow-y:auto; padding:11px 12px 18px; background:#f6f7fa; scrollbar-width:thin; }
.mini-thread-intro { display:flex; align-items:center; justify-content:center; gap:4px; margin:3px 0 15px; color:#939cad; font-size:7.5px; }
.mini-message { position:relative; max-width:76%; margin:8px 0; }.mini-message.own{margin-left:auto}
.mini-message-sender { display:block; margin:0 0 3px 3px; color:#727d91; font-size:8px; font-weight:750; }
.mini-bubble { padding:8px 10px 6px; background:#fff; border:1px solid #e4e7ee; border-radius:5px 13px 13px; box-shadow:0 3px 10px rgba(24,35,62,.04); }.mini-message.own .mini-bubble{color:#fff;background:linear-gradient(145deg,#6462e9,#504dd5);border-color:transparent;border-radius:13px 5px 13px 13px}
.mini-bubble > strong { display:block; margin-bottom:4px; font-size:10px; }.mini-bubble p{margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font-size:9.5px;line-height:1.45}
.mini-bubble a { display:flex; align-items:center; gap:6px; margin-top:6px; padding:6px; color:inherit; background:rgba(125,132,155,.1); border-radius:8px; font-size:8px; }.mini-bubble a span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mini-acknowledgement { display:flex; align-items:center; gap:6px; margin-top:7px; padding:7px; color:#765719; background:#fff7e3; border:1px solid #ecd79c; border-radius:9px; }.mini-message.own .mini-acknowledgement{color:#fff;background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.2)}
.mini-acknowledgement > i{flex:0 0 auto;font-size:16px}.mini-acknowledgement > span{flex:1;min-width:0;display:flex;flex-direction:column}.mini-acknowledgement strong{margin:0;font-size:8px}.mini-acknowledgement small{font-size:7px;opacity:.8}.mini-acknowledgement button{padding:4px 6px;color:#fff;background:#86631d;border:0;border-radius:6px;font-size:7px;font-weight:750}.mini-acknowledgement.complete{color:#247553;background:#e9f8f1;border-color:#bfe4d3}.mini-message.own .mini-acknowledgement.complete{color:#fff;background:rgba(51,214,153,.2);border-color:rgba(167,245,215,.32)}.mini-acknowledgement.overdue{color:#9d3f49;background:#fff0f2;border-color:#f0c8ce}
.mini-bubble footer { display:flex; align-items:center; justify-content:flex-end; gap:3px; margin-top:3px; color:#929bac; font-size:7px; }.mini-message.own .mini-bubble footer{color:rgba(255,255,255,.68)}.mini-bubble footer i{font-size:11px}.mini-bubble footer button{width:19px;height:17px;display:grid;place-items:center;margin-right:auto;padding:0;color:inherit;background:transparent;border:0;border-radius:5px;font-size:12px}.mini-bubble footer button:hover{background:rgba(125,132,155,.12)}.mini-message.own .mini-bubble footer button:hover{background:rgba(255,255,255,.12)}
.mini-reactions { display:flex; gap:3px; margin-top:3px; }.mini-message.own .mini-reactions{justify-content:flex-end}.mini-reactions button{padding:2px 5px;background:#fff;border:1px solid #dfe2e9;border-radius:9px;font-size:8px}.mini-reactions button.mine{background:#ebecff;border-color:#cfd0f7}.mini-reactions span{font-size:7px}
.mini-ack-sheet{flex:0 0 auto;padding:10px 11px;background:#fbfbfe;border-top:1px solid var(--mini-line);box-shadow:0 -8px 20px rgba(23,33,58,.04)}.mini-ack-sheet header{display:flex;align-items:center;gap:8px}.mini-ack-sheet header>span{width:29px;height:29px;display:grid;place-items:center;color:#4d4ad1;background:#e9eaff;border-radius:9px;font-size:15px}.mini-ack-sheet header>div{display:flex;flex-direction:column}.mini-ack-sheet header strong{font-size:9px}.mini-ack-sheet header small{color:var(--mini-muted);font-size:7.5px}.mini-ack-sheet textarea{width:100%;margin-top:8px;padding:7px 8px;resize:none;color:var(--mini-ink);background:#fff;border:1px solid #dfe2e9;border-radius:8px;outline:0;font-size:8.5px}.mini-ack-sheet>div{display:flex;justify-content:flex-end;gap:5px;margin-top:8px}.mini-ack-sheet>div button{padding:6px 8px;color:#6e788c;background:#eef0f4;border:0;border-radius:7px;font-size:8px;font-weight:700}.mini-ack-sheet>div button:last-child{display:flex;align-items:center;gap:4px;color:#fff;background:var(--mini-brand)}.mini-ack-sheet>div button:disabled{opacity:.45}
.mini-composer { flex:0 0 auto; padding:9px; background:#fff; border-top:1px solid var(--mini-line); }
.mini-composer-box { display:flex; align-items:flex-end; gap:6px; padding:5px 5px 5px 10px; border:1px solid #dfe2e9; border-radius:13px; }.mini-composer-box:focus-within{border-color:#bbbdf2;box-shadow:0 0 0 3px rgba(87,85,232,.06)}
.mini-composer textarea { flex:1; min-width:0; max-height:80px; resize:none; padding:7px 0; color:var(--mini-ink); border:0; outline:0; font-size:9.5px; }.mini-composer button{width:34px;height:34px;display:grid;place-items:center;color:#fff;background:var(--mini-brand);border:0;border-radius:10px;font-size:16px}.mini-composer button:disabled{opacity:.45}
.mini-error,.mini-locked { display:flex; align-items:center; justify-content:center; gap:5px; margin-bottom:6px; padding:7px; border-radius:8px; font-size:8px; }.mini-error{color:#aa3d4a;background:#fff0f2}.mini-locked{color:#826326;background:#fff7e8}
.mini-panel-enter-active,.mini-panel-leave-active{transition:opacity .18s ease,transform .18s ease}.mini-panel-enter-from,.mini-panel-leave-to{opacity:0;transform:translateY(12px) scale(.96)}
@keyframes mini-pulse{0%,100%{transform:scale(.96);opacity:.8}50%{transform:scale(1.1);opacity:0}}
@media(max-width:575px){.mini-chat-host{right:12px;bottom:74px}.mini-chat-panel{width:calc(100vw - 24px);height:min(560px,calc(100vh - 104px));border-radius:18px}.mini-chat-launcher{width:50px;height:50px}.mini-launcher-glyph i{font-size:25px}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
