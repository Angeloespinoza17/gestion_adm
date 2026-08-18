import { reactive, readonly } from "vue";
import api from "../api/messagingApi";

const state = reactive({
  conversations: [],
  activeConversation: null,
  messagesByConversation: {},
  hasOlderByConversation: {},
  summary: {},
  config: {},
  loading: { conversations: false, messages: false, older: false, sending: false },
  realtimeConnectionState: "disconnected",
  error: null,
});

let recovering = false;
let lastConversationParams = {};
const readRequests = new Map();
const processedChanges = new Set();

const notifyCountersChanged = () => {
  if (typeof window !== "undefined") window.dispatchEvent(new CustomEvent("internal-notifications:refresh"));
};

const sortConversations = () => {
  state.conversations.sort((a, b) => {
    if (Boolean(a.pinned) !== Boolean(b.pinned)) return a.pinned ? -1 : 1;
    return String(b.last_message_at || "").localeCompare(String(a.last_message_at || ""));
  });
};

const mergeMessages = (id, items, prepend = false) => {
  const current = state.messagesByConversation[id] || [];
  const ordered = prepend ? [...items, ...current] : [...current, ...items];
  const map = new Map();
  ordered.forEach((item) => {
    const existing = map.get(item.public_id);
    map.set(item.public_id, existing ? { ...existing, ...item } : item);
  });
  state.messagesByConversation[id] = [...map.values()].sort((a, b) => String(a.sent_at).localeCompare(String(b.sent_at)));
};

const currentUserId = () => Number(state.config?.user?.id || 0);

const normalizeRealtimeMessage = (message) => {
  if (!message) return message;
  const normalized = { ...message };
  if (normalized.requires_acknowledgement && Number(normalized.sender_id) !== currentUserId() && normalized.acknowledgement_status === "not_requested") {
    normalized.acknowledgement_status = normalized.acknowledgement_due_at && new Date(normalized.acknowledgement_due_at) < new Date() ? "overdue" : "pending";
  }
  return normalized;
};

const recoverConversationMessages = async (id, afterId) => {
  const limit = Number(state.config?.realtime?.recovery_limit || 100);
  let cursor = afterId;
  let hasMore = true;

  while (hasMore) {
    const { data } = await api.messages(id, { after_id: cursor, limit });
    const items = (data.data || []).map(normalizeRealtimeMessage);
    mergeMessages(id, items);
    const nextCursor = items.at(-1)?.public_id;
    hasMore = Boolean(data.has_more && nextCursor && nextCursor !== cursor);
    if (nextCursor) cursor = nextCursor;
  }
};

const replaceConversation = (conversation, preserveUnread = true) => {
  const index = state.conversations.findIndex((item) => item.public_id === conversation.public_id);
  if (index >= 0) {
    const unread = state.conversations[index].unread_count;
    state.conversations[index] = { ...state.conversations[index], ...conversation };
    if (preserveUnread && !Number(conversation.unread_count)) state.conversations[index].unread_count = unread;
  } else {
    state.conversations.push(conversation);
  }
  if (state.activeConversation?.public_id === conversation.public_id) {
    state.activeConversation = { ...state.activeConversation, ...conversation };
  }
  sortConversations();
};

export const messagingStore = {
  state: readonly(state),

  async loadConfig() {
    state.config = (await api.config()).data;
    return state.config;
  },

  async loadSummary() {
    const previousUnread = Number(state.summary.unread_messages || 0);
    state.summary = (await api.summary()).data;
    if (previousUnread !== Number(state.summary.unread_messages || 0)) notifyCountersChanged();
    return state.summary;
  },

  async loadConversations(params = {}, silent = false) {
    if (!silent) {
      state.loading.conversations = true;
      lastConversationParams = { ...params };
    }
    try {
      const { data } = await api.conversations(params);
      state.conversations = data.data || [];
      sortConversations();
      return state.conversations;
    } finally {
      if (!silent) state.loading.conversations = false;
    }
  },

  async markRead(id, throughMessageId) {
    if (!id || !throughMessageId) return null;
    const key = `${id}:${throughMessageId}`;
    if (readRequests.has(key)) return readRequests.get(key);
    const conversation = state.conversations.find((item) => item.public_id === id);
    const previousUnread = Number(conversation?.unread_count || state.activeConversation?.unread_count || 0);

    const request = api.read(id, throughMessageId).then(({ data }) => {
      const updated = Number(data.data?.updated || 0);
      if (conversation) conversation.unread_count = 0;
      if (state.activeConversation?.public_id === id) state.activeConversation.unread_count = 0;
      state.summary.unread_messages = Math.max(0, Number(state.summary.unread_messages || 0) - Math.max(previousUnread, updated));
      if (previousUnread > 0) state.summary.unread_conversations = Math.max(0, Number(state.summary.unread_conversations || 0) - 1);
      notifyCountersChanged();
      return data.data;
    }).finally(() => readRequests.delete(key));

    readRequests.set(key, request);
    return request;
  },

  async open(id) {
    state.loading.messages = true;
    state.error = null;
    try {
      const [conversation, messages] = await Promise.all([api.conversation(id), api.messages(id)]);
      state.activeConversation = conversation.data.data;
      state.messagesByConversation[id] = (messages.data.data || []).reverse();
      state.hasOlderByConversation[id] = Boolean(messages.data.has_more);
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
    replaceConversation(data.data);
    return state.activeConversation;
  },

  async loadOlder() {
    const id = state.activeConversation?.public_id;
    const first = state.messagesByConversation[id]?.[0];
    if (!id || !first || state.loading.older || state.hasOlderByConversation[id] === false) return 0;
    state.loading.older = true;
    try {
      const { data } = await api.messages(id, { before: first.public_id });
      const items = (data.data || []).reverse();
      mergeMessages(id, items, true);
      state.hasOlderByConversation[id] = Boolean(data.has_more);
      return items.length;
    } finally {
      state.loading.older = false;
    }
  },

  async recoverAfterReconnect() {
    if (recovering || !navigator.onLine) return;
    recovering = true;
    try {
      const id = state.activeConversation?.public_id;
      const last = id ? (state.messagesByConversation[id] || []).filter((message) => !String(message.public_id).startsWith("pending-")).at(-1) : null;
      const requests = [this.loadConversations(lastConversationParams, true), this.loadSummary()];
      if (id && last) {
        requests.push(recoverConversationMessages(id, last.public_id));
      }
      await Promise.all(requests);
      const latest = id ? state.messagesByConversation[id]?.at(-1) : null;
      if (id && latest && document.visibilityState === "visible") await this.markRead(id, latest.public_id);
    } finally {
      recovering = false;
    }
  },

  async send(payload) {
    const id = state.activeConversation.public_id;
    state.loading.sending = true;
    state.error = null;
    const optimistic = {
      ...payload,
      conversation_id: id,
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
      const sent = { ...data.data, conversation_id: id };
      mergeMessages(id, [sent]);
      const conversation = state.conversations.find((item) => item.public_id === id);
      if (conversation) {
        conversation.last_message = {
          public_id: sent.public_id,
          body: sent.body,
          subject: sent.subject,
          sender: sent.sender?.name,
          priority: sent.priority,
          sent_at: sent.sent_at,
        };
        conversation.last_message_at = sent.sent_at;
        sortConversations();
      }
      return sent;
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
    const mine = Boolean(message.reactions?.find((item) => item.reaction === reaction)?.mine);
    this.applyReactionChange({ message_id: message.public_id, user_id: currentUserId(), reaction, active: !mine });
    try {
      if (mine) await api.unreact(message.public_id, reaction);
      else await api.react(message.public_id, reaction);
    } catch (error) {
      this.applyReactionChange({ message_id: message.public_id, user_id: currentUserId(), reaction, active: mine });
      throw error;
    }
  },

  async refreshMessage(messageId) {
    const id = state.activeConversation?.public_id;
    if (!id || !messageId) return null;
    const { data } = await api.message(messageId);
    mergeMessages(id, [data.data]);
    return data.data;
  },

  addRealtimeMessage(message) {
    const normalized = normalizeRealtimeMessage(message);
    const id = state.activeConversation?.public_id;
    if (id && normalized?.conversation_id === id) mergeMessages(id, [normalized]);
  },

  updateRealtimeMessage(message) {
    const id = state.activeConversation?.public_id;
    if (!id || message?.conversation_id !== id) return;
    const current = state.messagesByConversation[id]?.find((item) => item.public_id === message.public_id);
    const reactions = (message.reactions || []).map((reaction) => ({
      ...reaction,
      mine: current?.reactions?.find((item) => item.reaction === reaction.reaction)?.mine || false,
    }));
    mergeMessages(id, [normalizeRealtimeMessage({ ...message, reactions })]);
  },

  removeRealtimeMessage({ conversation_id: conversationId, message_id: messageId }) {
    if (!conversationId || !state.messagesByConversation[conversationId]) return;
    state.messagesByConversation[conversationId] = state.messagesByConversation[conversationId].filter((message) => message.public_id !== messageId);
  },

  applyReactionChange({ message_id: messageId, user_id: userId, reaction, active }) {
    const id = state.activeConversation?.public_id;
    const message = id ? state.messagesByConversation[id]?.find((item) => item.public_id === messageId) : null;
    if (!message) return;
    const reactions = [...(message.reactions || [])];
    const index = reactions.findIndex((item) => item.reaction === reaction);
    if (active) {
      if (index >= 0) {
        reactions[index] = { ...reactions[index], count: Number(reactions[index].count || 0) + 1, mine: reactions[index].mine || Number(userId) === currentUserId() };
      } else {
        reactions.push({ reaction, count: 1, mine: Number(userId) === currentUserId() });
      }
    } else if (index >= 0) {
      const count = Math.max(0, Number(reactions[index].count || 0) - 1);
      if (count) reactions[index] = { ...reactions[index], count, mine: Number(userId) === currentUserId() ? false : reactions[index].mine };
      else reactions.splice(index, 1);
    }
    mergeMessages(id, [{ ...message, reactions }]);
  },

  applyReadReceipt(receipt) {
    const id = state.activeConversation?.public_id;
    if (!id || receipt?.conversation_id !== id) return;
    state.activeConversation.last_read_receipt = receipt;
  },

  applyAcknowledgement(acknowledgement) {
    const id = state.activeConversation?.public_id;
    const message = id ? state.messagesByConversation[id]?.find((item) => item.public_id === acknowledgement?.message_id) : null;
    if (!message) return;
    const patch = { ...message };
    if (Number(acknowledgement.user_id) === currentUserId()) patch.acknowledgement_status = "acknowledged";
    if (Number(message.sender_id) === currentUserId()) patch.acknowledgement_summary = acknowledgement.summary;
    mergeMessages(id, [patch]);
  },

  async applyConversationChange(change) {
    if (!change?.conversation_id) return;
    const changeKey = `${change.action}:${change.conversation_id}:${change.occurred_at}`;
    if (processedChanges.has(changeKey)) return;
    processedChanges.add(changeKey);
    if (processedChanges.size > 500) processedChanges.delete(processedChanges.values().next().value);

    const userId = currentUserId();
    if (change.action === "participant_removed" && Number(change.removed_user_id) === userId) {
      state.conversations = state.conversations.filter((item) => item.public_id !== change.conversation_id);
      if (state.activeConversation?.public_id === change.conversation_id) this.close();
      return;
    }

    let conversation = state.conversations.find((item) => item.public_id === change.conversation_id);
    if (!conversation) {
      await Promise.all([this.loadConversations(lastConversationParams, true), this.loadSummary()]);
      conversation = state.conversations.find((item) => item.public_id === change.conversation_id);
      if (!conversation) return;
    }

    if (Object.prototype.hasOwnProperty.call(change, "is_locked")) conversation.is_locked = change.is_locked;
    if (Object.prototype.hasOwnProperty.call(change, "last_message")) conversation.last_message = change.last_message;
    if (change.last_message?.sent_at) conversation.last_message_at = change.last_message.sent_at;
    if (change.last_message_at !== undefined) conversation.last_message_at = change.last_message_at;

    if (change.action === "message_created" && Number(change.actor_id) !== userId) {
      conversation.unread_count = Number(conversation.unread_count || 0) + Number(change.unread_delta || 1);
      state.summary.unread_messages = Number(state.summary.unread_messages || 0) + Number(change.unread_delta || 1);
      if (conversation.unread_count === Number(change.unread_delta || 1)) state.summary.unread_conversations = Number(state.summary.unread_conversations || 0) + 1;
      notifyCountersChanged();
      if (state.activeConversation?.public_id === change.conversation_id && document.visibilityState === "visible" && change.last_message?.public_id) {
        this.markRead(change.conversation_id, change.last_message.public_id).catch(() => {});
      }
    }

    if (change.action === "messages_read" && Number(change.reader_id) === userId) {
      const previous = Number(conversation.unread_count || 0);
      conversation.unread_count = Number(change.unread_count || 0);
      state.summary.unread_messages = Math.max(0, Number(state.summary.unread_messages || 0) - Math.max(previous, Number(change.read_count || 0)));
      if (previous > 0) state.summary.unread_conversations = Math.max(0, Number(state.summary.unread_conversations || 0) - 1);
      notifyCountersChanged();
    }

    if (change.action === "message_acknowledged" && Number(change.actor_id) === userId) {
      state.summary.pending_acknowledgements = Math.max(0, Number(state.summary.pending_acknowledgements || 0) - 1);
    }

    if (["conversation_created", "conversation_updated", "participants_updated", "ownership_transferred"].includes(change.action)) {
      try {
        const { data } = await api.conversation(change.conversation_id);
        replaceConversation(data.data);
      } catch (error) {
        if (error.response?.status === 403 || error.response?.status === 404) {
          state.conversations = state.conversations.filter((item) => item.public_id !== change.conversation_id);
        }
      }
    }

    if (change.action === "message_deleted") this.loadSummary().catch(() => {});
    sortConversations();
  },

  setRealtime(status) {
    state.realtimeConnectionState = status;
  },
};
