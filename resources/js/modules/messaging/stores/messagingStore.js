import { reactive, readonly } from "vue";
import api from "../api/messagingApi";

const state = reactive({
  conversations: [],
  activeConversation: null,
  messagesByConversation: {},
  summary: {},
  config: {},
  loading: { conversations: false, messages: false, sending: false },
  realtimeConnectionState: "polling",
  error: null,
});

let syncing = false;
let lastConversationParams = {};
const changeCursorByConversation = {};

const notifyCountersChanged = () => {
  if (typeof window !== "undefined") {
    window.dispatchEvent(new CustomEvent("internal-notifications:refresh"));
  }
};

const mergeMessages = (id, items, prepend = false) => {
  const current = state.messagesByConversation[id] || [];
  const ordered = prepend ? [...items, ...current] : [...current, ...items];
  const map = new Map(ordered.map((item) => [item.public_id, item]));
  state.messagesByConversation[id] = [...map.values()].sort((a, b) => String(a.sent_at).localeCompare(String(b.sent_at)));
};

export const messagingStore = {
  state: readonly(state),

  async loadConfig() {
    state.config = (await api.config()).data;
  },

  async loadSummary() {
    const previousUnread = Number(state.summary.unread_messages || 0);
    state.summary = (await api.summary()).data;
    if (previousUnread !== Number(state.summary.unread_messages || 0)) notifyCountersChanged();
  },

  async loadConversations(params = {}, silent = false) {
    if (!silent) {
      state.loading.conversations = true;
      lastConversationParams = { ...params };
    }
    try {
      const { data } = await api.conversations(params);
      state.conversations = data.data || [];
    } finally {
      if (!silent) state.loading.conversations = false;
    }
  },

  async markRead(id, throughMessageId) {
    if (!id || !throughMessageId) return;
    const { data } = await api.read(id, throughMessageId);
    if (state.activeConversation?.public_id === id) state.activeConversation.unread_count = 0;
    await Promise.all([
      this.loadConversations(lastConversationParams, true),
      this.loadSummary(),
    ]);
    notifyCountersChanged();
    return data.data;
  },

  async open(id) {
    state.loading.messages = true;
    state.error = null;
    try {
      const [conversation, messages] = await Promise.all([api.conversation(id), api.messages(id)]);
      state.activeConversation = conversation.data.data;
      state.messagesByConversation[id] = (messages.data.data || []).reverse();
      changeCursorByConversation[id] = messages.data.sync_cursor || new Date(Date.now() - 10000).toISOString();
      const last = state.messagesByConversation[id].at(-1);
      if (last) await this.markRead(id, last.public_id);
      return last;
    } catch (error) {
      state.error = error.response?.data?.message || "No fue posible abrir la conversación.";
      throw error;
    } finally {
      state.loading.messages = false;
    }
  },

  close() {
    state.activeConversation = null;
  },

  async refreshActiveConversation() {
    const id = state.activeConversation?.public_id;
    if (!id) return null;
    const { data } = await api.conversation(id);
    state.activeConversation = data.data;
    await this.loadConversations(lastConversationParams, true);
    return state.activeConversation;
  },

  async loadOlder() {
    const id = state.activeConversation?.public_id;
    const first = state.messagesByConversation[id]?.[0];
    if (!id || !first) return;
    const { data } = await api.messages(id, { before: first.public_id });
    mergeMessages(id, (data.data || []).reverse(), true);
  },

  async sync() {
    if (syncing || !navigator.onLine) return;
    syncing = true;

    try {
      const id = state.activeConversation?.public_id;
      const requests = [
        this.loadConversations(lastConversationParams, true),
        this.loadSummary(),
      ];

      let changedMessages = null;
      if (id) {
        const params = changeCursorByConversation[id]
          ? { updated_since: changeCursorByConversation[id], limit: 100 }
          : { limit: 40 };
        requests.push(api.messages(id, params).then(({ data }) => {
          changedMessages = (data.data || []).reverse();
          mergeMessages(id, changedMessages);
          changeCursorByConversation[id] = data.sync_cursor || new Date(Date.now() - 10000).toISOString();
        }));
      }

      await Promise.all(requests);

      const latest = id ? state.messagesByConversation[id]?.at(-1) : null;
      const activeListItem = state.conversations.find((conversation) => conversation.public_id === id);
      const isActivelyVisible = typeof document === "undefined" || document.visibilityState === "visible";
      if (id && latest && activeListItem?.unread_count > 0 && isActivelyVisible) {
        await this.markRead(id, latest.public_id);
      }
    } finally {
      syncing = false;
    }
  },

  async send(payload) {
    const id = state.activeConversation.public_id;
    state.loading.sending = true;
    state.error = null;
    const optimistic = {
      ...payload,
      public_id: `pending-${Date.now()}`,
      sender_id: payload.sender_id,
      sender: { name: "Tú" },
      sent_at: new Date().toISOString(),
      status: "sending",
      attachments: [],
      reactions: [],
    };
    mergeMessages(id, [optimistic]);

    try {
      const { data } = await api.send(id, payload);
      state.messagesByConversation[id] = state.messagesByConversation[id].filter((message) => message.public_id !== optimistic.public_id);
      mergeMessages(id, [data.data]);
      await this.loadConversations(lastConversationParams, true);
      return data.data;
    } catch (error) {
      optimistic.status = "error";
      mergeMessages(id, [optimistic]);
      state.error = error.response?.data?.message || "No fue posible enviar el mensaje. Reintentar.";
      throw error;
    } finally {
      state.loading.sending = false;
    }
  },

  async toggleReaction(message, reaction) {
    const id = state.activeConversation?.public_id;
    if (!id) return;
    const mine = message.reactions?.find((item) => item.reaction === reaction)?.mine;
    if (mine) await api.unreact(message.public_id, reaction);
    else await api.react(message.public_id, reaction);
    const { data } = await api.message(message.public_id);
    mergeMessages(id, [data.data]);
  },

  async refreshMessage(messageId) {
    const id = state.activeConversation?.public_id;
    if (!id || !messageId) return null;
    const { data } = await api.message(messageId);
    mergeMessages(id, [data.data]);
    return data.data;
  },

  addRealtimeMessage(message) {
    const id = state.activeConversation?.public_id;
    if (id && message.conversation_id === id) {
      mergeMessages(id, [message]);
      if (typeof document === "undefined" || document.visibilityState === "visible") {
        this.markRead(id, message.public_id).catch(() => {});
      }
    }
    this.loadConversations(lastConversationParams, true);
    this.loadSummary();
  },

  setRealtime(status) {
    state.realtimeConnectionState = status;
  },
};
