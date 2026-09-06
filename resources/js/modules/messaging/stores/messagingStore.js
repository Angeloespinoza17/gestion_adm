import { reactive, readonly } from "vue";
import api from "../api/messagingApi";

const state = reactive({
    conversations: [],
    activeConversation: null,
    messagesByConversation: {},
    hasOlderByConversation: {},
    summary: {},
    config: {},
    loading: {
        conversations: false,
        messages: false,
        older: false,
        sending: false,
    },
    pollingConnectionState: "disconnected",
    error: null,
});

let recovering = false;
let lastConversationParams = {};
const readRequests = new Map();
const processedChanges = new Set();
const processedAcknowledgements = new Set();
const syncCursorByConversation = new Map();
const confirmedReadWatermarkByConversation = new Map();
const conversationRequests = new Map();
const activePollRequests = new Map();
const conversationDetailRequests = new Map();
let configRequest = null;
let configLoaded = false;
let summaryRequest = null;
let conversationRequestSequence = 0;
let appliedConversationRequestSequence = 0;
let openRequestSequence = 0;
let realtimeGlobalRefreshRequest = null;
let realtimeGlobalRefreshNeedsSummary = false;
let accessGeneration = 0;

const compareLoadedMessageIds = (conversationId, left, right) => {
    if (!left || !right) return null;
    if (left === right) return 0;
    const messages = state.messagesByConversation[conversationId] || [];
    const leftIndex = messages.findIndex((item) => item.public_id === left);
    const rightIndex = messages.findIndex((item) => item.public_id === right);
    if (leftIndex < 0 || rightIndex < 0) return null;
    return leftIndex > rightIndex ? 1 : -1;
};

const messageIdAtLeast = (conversationId, value, reference) => {
    const comparison = compareLoadedMessageIds(
        conversationId,
        value,
        reference
    );
    return comparison !== null && comparison >= 0;
};

const rememberConfirmedRead = (conversationId, throughMessageId) => {
    const confirmed = confirmedReadWatermarkByConversation.get(conversationId);
    if (
        !confirmed ||
        compareLoadedMessageIds(conversationId, throughMessageId, confirmed) ===
            1
    )
        confirmedReadWatermarkByConversation.set(
            conversationId,
            throughMessageId
        );
};

const requestKey = (params = {}) =>
    JSON.stringify(
        Object.keys(params)
            .sort()
            .reduce((result, key) => {
                if (params[key] !== undefined && params[key] !== null)
                    result[key] = params[key];
                return result;
            }, {})
    );

const requestStatus = (error) => Number(error?.response?.status || 0);

const conversationChangeKey = (change) => {
    let subject = change.message_reference?.public_id || change.message_id;
    if (change.reaction)
        subject = [
            change.reaction.message_id,
            change.reaction.user_id,
            change.reaction.reaction,
            Number(Boolean(change.reaction.active)),
        ].join(":");
    else if (change.acknowledgement)
        subject = [
            change.acknowledgement.message_id,
            change.acknowledgement.user_id,
            change.acknowledgement.acknowledged_at,
        ].join(":");
    else if (change.receipt)
        subject = [
            change.receipt.reader_id,
            change.receipt.through_message_id,
            change.receipt.read_at,
        ].join(":");
    subject ||= [
        change.actor_id,
        change.removed_user_id,
        change.updated_user_id,
    ]
        .filter((value) => value !== undefined && value !== null)
        .join(":");

    return [
        change.action,
        change.conversation_id,
        change.occurred_at,
        subject || "conversation",
    ].join(":");
};

const purgeConversationState = (id) => {
    const conversation =
        state.conversations.find((item) => item.public_id === id) ||
        (state.activeConversation?.public_id === id
            ? state.activeConversation
            : null);
    const unread = Number(conversation?.unread_count || 0);
    if (unread > 0) {
        state.summary.unread_messages = Math.max(
            0,
            Number(state.summary.unread_messages || 0) - unread
        );
        state.summary.unread_conversations = Math.max(
            0,
            Number(state.summary.unread_conversations || 0) - 1
        );
        notifyCountersChanged();
    }
    state.conversations = state.conversations.filter(
        (conversation) => conversation.public_id !== id
    );
    delete state.messagesByConversation[id];
    delete state.hasOlderByConversation[id];
    syncCursorByConversation.delete(id);
    confirmedReadWatermarkByConversation.delete(id);
    readRequests.delete(id);
    activePollRequests.delete(id);
    if (state.activeConversation?.public_id === id) {
        openRequestSequence += 1;
        state.activeConversation = null;
        state.loading.messages = false;
    }
};

const notifyCountersChanged = () => {
    if (typeof window !== "undefined")
        window.dispatchEvent(new CustomEvent("internal-notifications:refresh"));
};

const notifyLocalTransportEvent = (kind, payload) => {
    if (typeof window !== "undefined")
        window.dispatchEvent(
            new CustomEvent("messaging:local-event", {
                detail: { kind, payload },
            })
        );
};

const sortConversations = () => {
    state.conversations.sort((a, b) => {
        if (Boolean(a.pinned) !== Boolean(b.pinned)) return a.pinned ? -1 : 1;
        return String(b.last_message_at || "").localeCompare(
            String(a.last_message_at || "")
        );
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
    state.messagesByConversation[id] = [...map.values()].sort((a, b) =>
        String(a.sent_at).localeCompare(String(b.sent_at))
    );
};

const currentUserId = () => Number(state.config?.user?.id || 0);

const normalizeMessage = (message) => {
    if (!message) return message;
    return { ...message };
};

const recoverConversationMessages = async (id, afterId) => {
    const limit = Number(state.config?.polling?.recovery_limit || 100);
    let cursor = afterId;
    let hasMore = true;
    let syncCursor = null;

    while (hasMore) {
        const { data } = await api.messages(id, { after_id: cursor, limit });
        const items = (data.data || []).map(normalizeMessage);
        if (!syncCursor && data.sync_cursor) syncCursor = data.sync_cursor;
        mergeMessages(id, items);
        const nextCursor = items.at(-1)?.public_id;
        hasMore = Boolean(data.has_more && nextCursor && nextCursor !== cursor);
        if (nextCursor) cursor = nextCursor;
    }

    return { syncCursor };
};

const recoverUpdatedMessages = async (id, updatedSince) => {
    const limit = Number(state.config?.polling?.recovery_limit || 100);
    let before = null;
    let hasMore = true;
    let syncCursor = null;
    const visited = new Set();

    while (hasMore) {
        const params = { updated_since: updatedSince, limit };
        if (before) params.before = before;
        const { data } = await api.messages(id, params);
        const items = (data.data || []).map(normalizeMessage);
        if (!syncCursor && data.sync_cursor) syncCursor = data.sync_cursor;
        const deletedIds = new Set(
            (data.deleted || []).map((item) => item.public_id)
        );
        if (deletedIds.size)
            state.messagesByConversation[id] = (
                state.messagesByConversation[id] || []
            ).filter((message) => !deletedIds.has(message.public_id));
        mergeMessages(id, items);

        const nextBefore = data.before || items.at(-1)?.public_id;
        hasMore = Boolean(
            data.has_more &&
                nextBefore &&
                nextBefore !== before &&
                !visited.has(nextBefore)
        );
        if (nextBefore) {
            visited.add(nextBefore);
            before = nextBefore;
        }
    }

    return { syncCursor };
};

const synchronizeConversationMessages = async (id) => {
    const messages = state.messagesByConversation[id] || [];
    const knownIds = new Set(messages.map((message) => message.public_id));
    const last = messages
        .filter(
            (message) => !String(message.public_id).startsWith("pending-")
        )
        .at(-1);
    const previousSyncCursor =
        syncCursorByConversation.get(id) ||
        new Date(Date.now() - 15000).toISOString();

    let creationSyncCursor = null;
    let updateSyncCursor = null;
    if (last) {
        try {
            ({ syncCursor: creationSyncCursor } =
                await recoverConversationMessages(id, last.public_id));
        } catch (error) {
            if (requestStatus(error) !== 404) throw error;
            // El cursor puede ser un mensaje eliminado mientras la pestaña
            // estuvo desconectada. El delta autorizado entrega su tombstone.
            ({ syncCursor: updateSyncCursor } =
                await recoverUpdatedMessages(id, previousSyncCursor));
        }
    }
    if (!updateSyncCursor)
        ({ syncCursor: updateSyncCursor } = await recoverUpdatedMessages(
            id,
            previousSyncCursor
        ));
    const nextSyncCursor = updateSyncCursor || creationSyncCursor;
    if (nextSyncCursor) syncCursorByConversation.set(id, nextSyncCursor);

    const addedMessages = (state.messagesByConversation[id] || []).filter(
        (message) => !knownIds.has(message.public_id)
    );
    const added = addedMessages.length;
    const hasIncomingMessage = addedMessages.some(
        (message) => Number(message.sender_id) !== currentUserId()
    );
    if (
        hasIncomingMessage &&
        state.activeConversation?.public_id === id &&
        document.visibilityState === "visible"
    ) {
        const latest = state.messagesByConversation[id]?.at(-1);
        if (
            latest &&
            !messageIdAtLeast(
                id,
                confirmedReadWatermarkByConversation.get(id),
                latest.public_id
            )
        )
            await messagingStore.markRead(id, latest.public_id);
    }

    return added;
};

const replaceConversation = (conversation, preserveUnread = true) => {
    const index = state.conversations.findIndex(
        (item) => item.public_id === conversation.public_id
    );
    if (index >= 0) {
        const unread = state.conversations[index].unread_count;
        state.conversations[index] = {
            ...state.conversations[index],
            ...conversation,
        };
        if (preserveUnread && !Number(conversation.unread_count))
            state.conversations[index].unread_count = unread;
    } else {
        state.conversations.push(conversation);
    }
    if (state.activeConversation?.public_id === conversation.public_id) {
        state.activeConversation = {
            ...state.activeConversation,
            ...conversation,
        };
    }
    sortConversations();
};

const loadConversationDetail = (id) => {
    if (conversationDetailRequests.has(id))
        return conversationDetailRequests.get(id);
    const request = api
        .conversation(id)
        .then(({ data }) => {
            replaceConversation(data.data);
            return data.data;
        })
        .finally(() => {
            if (conversationDetailRequests.get(id) === request)
                conversationDetailRequests.delete(id);
        });
    conversationDetailRequests.set(id, request);
    return request;
};

const scheduleRealtimeGlobalRefresh = (includeSummary = false) => {
    realtimeGlobalRefreshNeedsSummary ||= includeSummary;
    if (realtimeGlobalRefreshRequest) return realtimeGlobalRefreshRequest;

    const delay = import.meta.env.MODE === "test"
        ? 0
        : Math.round(250 + Math.random() * 1750);
    realtimeGlobalRefreshRequest = new Promise((resolve, reject) => {
        setTimeout(async () => {
            try {
                await messagingStore.loadConversations(
                    lastConversationParams,
                    true
                );
                if (realtimeGlobalRefreshNeedsSummary)
                    await messagingStore.loadSummary();
                resolve();
            } catch (error) {
                reject(error);
            } finally {
                realtimeGlobalRefreshNeedsSummary = false;
                realtimeGlobalRefreshRequest = null;
            }
        }, delay);
    });

    return realtimeGlobalRefreshRequest;
};

export const messagingStore = {
    state: readonly(state),

    async loadConfig(force = false) {
        if (configLoaded && !force) return state.config;
        if (configRequest) return configRequest;

        const generation = accessGeneration;
        const request = api
            .config()
            .then(({ data }) => {
                if (generation !== accessGeneration) return state.config;
                state.config = data;
                configLoaded = true;
                return state.config;
            })
            .finally(() => {
                if (configRequest === request) configRequest = null;
            });
        configRequest = request;

        return configRequest;
    },

    async loadSummary() {
        if (summaryRequest) return summaryRequest;

        const generation = accessGeneration;
        const request = api
            .summary()
            .then(({ data }) => {
                if (generation !== accessGeneration) return state.summary;
                const previousUnread = Number(
                    state.summary.unread_messages || 0
                );
                state.summary = data;
                if (
                    previousUnread !==
                    Number(state.summary.unread_messages || 0)
                )
                    notifyCountersChanged();
                return state.summary;
            })
            .finally(() => {
                if (summaryRequest === request) summaryRequest = null;
            });
        summaryRequest = request;

        return summaryRequest;
    },

    async loadConversations(params = {}, silent = false) {
        if (!silent) {
            state.loading.conversations = true;
            lastConversationParams = { ...params };
        }
        const key = requestKey(params);
        const sequence = ++conversationRequestSequence;
        const generation = accessGeneration;
        const existingRequest = conversationRequests.get(key);
        if (existingRequest) {
            existingRequest.sequence = sequence;
            try {
                return await existingRequest.promise;
            } finally {
                if (!silent) state.loading.conversations = false;
            }
        }
        const entry = { sequence, promise: null };
        const request = api
            .conversations(params)
            .then(({ data }) => {
                if (
                    generation === accessGeneration &&
                    entry.sequence >= appliedConversationRequestSequence
                ) {
                    appliedConversationRequestSequence = entry.sequence;
                    state.conversations = data.data || [];
                    sortConversations();
                }
                return data.data || [];
            })
            .finally(() => {
                if (conversationRequests.get(key) === entry)
                    conversationRequests.delete(key);
            });
        entry.promise = request;
        conversationRequests.set(key, entry);

        try {
            return await request;
        } finally {
            if (!silent) state.loading.conversations = false;
        }
    },

    async markRead(id, throughMessageId) {
        if (!id || !throughMessageId) return null;
        if (
            messageIdAtLeast(
                id,
                confirmedReadWatermarkByConversation.get(id),
                throughMessageId
            )
        )
            return null;

        const pending = readRequests.get(id);
        if (
            pending &&
            messageIdAtLeast(
                id,
                pending.throughMessageId,
                throughMessageId
            )
        )
            return pending.request;

        const previousRequest = pending?.request || Promise.resolve();
        const request = previousRequest
            .catch(() => null)
            .then(async () => {
                if (
                    messageIdAtLeast(
                        id,
                        confirmedReadWatermarkByConversation.get(id),
                        throughMessageId
                    )
                )
                    return null;
                const conversation = state.conversations.find(
                    (item) => item.public_id === id
                );
                const previousUnread = Number(
                    conversation?.unread_count ||
                        (state.activeConversation?.public_id === id
                            ? state.activeConversation?.unread_count
                            : 0) ||
                        0
                );
                const { data } = await api.read(id, throughMessageId);
                rememberConfirmedRead(id, throughMessageId);
                const updated = Number(data.data?.updated || 0);
                if (conversation) conversation.unread_count = 0;
                if (state.activeConversation?.public_id === id)
                    state.activeConversation.unread_count = 0;
                state.summary.unread_messages = Math.max(
                    0,
                    Number(state.summary.unread_messages || 0) -
                        Math.max(previousUnread, updated)
                );
                if (previousUnread > 0)
                    state.summary.unread_conversations = Math.max(
                        0,
                        Number(state.summary.unread_conversations || 0) - 1
                    );
                if (previousUnread > 0 || updated > 0)
                    notifyCountersChanged();
                return data.data;
            })
            .finally(() => {
                if (readRequests.get(id)?.request === request)
                    readRequests.delete(id);
            });

        readRequests.set(id, { throughMessageId, request });
        return request;
    },

    async open(id) {
        const sequence = ++openRequestSequence;
        state.loading.messages = true;
        state.error = null;
        try {
            const [conversation, messages] = await Promise.all([
                api.conversation(id),
                api.messages(id),
            ]);
            if (sequence !== openRequestSequence) return null;
            state.activeConversation = conversation.data.data;
            state.messagesByConversation[id] = (
                messages.data.data || []
            ).reverse();
            if (messages.data.sync_cursor)
                syncCursorByConversation.set(id, messages.data.sync_cursor);
            state.hasOlderByConversation[id] = Boolean(messages.data.has_more);
            const last = state.messagesByConversation[id].at(-1);
            const detailUnread = Number(
                state.activeConversation.unread_count || 0
            );
            const detailLastId =
                state.activeConversation.last_message?.public_id || null;
            const canTrustReadState = Boolean(
                detailLastId && detailLastId === last?.public_id
            );
            if (last && (!canTrustReadState || detailUnread > 0))
                await this.markRead(id, last.public_id);
            else if (last && canTrustReadState)
                rememberConfirmedRead(id, last.public_id);
            return last;
        } catch (error) {
            if (sequence !== openRequestSequence) return null;
            state.error =
                error.response?.data?.message ||
                "No fue posible abrir la conversación.";
            throw error;
        } finally {
            if (sequence === openRequestSequence)
                state.loading.messages = false;
        }
    },

    close() {
        openRequestSequence += 1;
        state.activeConversation = null;
        state.loading.messages = false;
    },

    async refreshActiveConversation() {
        const id = state.activeConversation?.public_id;
        if (!id) return null;
        const { data } = await api.conversation(id);
        replaceConversation(data.data);
        return state.activeConversation;
    },

    async pollActiveConversation() {
        const id = state.activeConversation?.public_id;
        if (!id) return 0;
        if (activePollRequests.has(id)) return activePollRequests.get(id);

        let request;
        request = synchronizeConversationMessages(id)
            .catch((error) => {
                if ([403, 404].includes(requestStatus(error))) {
                    purgeConversationState(id);
                    return 0;
                }
                throw error;
            })
            .finally(() => {
                if (activePollRequests.get(id) === request)
                    activePollRequests.delete(id);
            });

        activePollRequests.set(id, request);
        return request;
    },

    async loadOlder() {
        const id = state.activeConversation?.public_id;
        const first = state.messagesByConversation[id]?.[0];
        if (
            !id ||
            !first ||
            state.loading.older ||
            state.hasOlderByConversation[id] === false
        )
            return 0;
        state.loading.older = true;
        try {
            const { data } = await api.messages(id, {
                before: first.public_id,
            });
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
            const requests = [
                this.loadConversations(lastConversationParams, true),
                this.loadSummary(),
            ];
            if (id) requests.push(this.pollActiveConversation());
            await Promise.all(requests);
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
            state.messagesByConversation[id] = state.messagesByConversation[
                id
            ].filter((message) => message.public_id !== optimistic.public_id);
            const sent = { ...data.data, conversation_id: id };
            mergeMessages(id, [sent]);
            const conversation = state.conversations.find(
                (item) => item.public_id === id
            );
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
            notifyLocalTransportEvent("sentMessage", { message: sent });
            return sent;
        } catch (error) {
            optimistic.status = "error";
            mergeMessages(id, [optimistic]);
            state.error =
                error.response?.data?.message ||
                "No fue posible enviar el mensaje. Reintentar.";
            throw error;
        } finally {
            state.loading.sending = false;
        }
    },

    async toggleReaction(message, reaction) {
        const id = state.activeConversation?.public_id;
        if (!id) return;
        const mine = Boolean(
            message.reactions?.find((item) => item.reaction === reaction)?.mine
        );
        this.applyReactionChange({
            message_id: message.public_id,
            user_id: currentUserId(),
            reaction,
            active: !mine,
        });
        try {
            if (mine) await api.unreact(message.public_id, reaction);
            else await api.react(message.public_id, reaction);
        } catch (error) {
            this.applyReactionChange({
                message_id: message.public_id,
                user_id: currentUserId(),
                reaction,
                active: mine,
            });
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
        const normalized = normalizeMessage(message);
        const id = state.activeConversation?.public_id;
        if (id && normalized?.conversation_id === id)
            mergeMessages(id, [normalized]);
    },

    async applyRealtimeMessageReference(
        action,
        reference,
        { refreshGlobal = true } = {}
    ) {
        const conversationId = reference?.conversation_id;
        if (!conversationId) return null;

        // Los eventos sólo son referencias compactas. El contenido se obtiene
        // siempre desde un endpoint autorizado antes de incorporarlo al hilo.
        if (state.activeConversation?.public_id === conversationId)
            return this.pollActiveConversation();

        if (!refreshGlobal) return null;

        await scheduleRealtimeGlobalRefresh(action === "created");
        return null;
    },

    applySentMessageFromTab(message) {
        if (!message?.conversation_id) return;
        const id = message.conversation_id;
        if (
            state.activeConversation?.public_id === id ||
            state.messagesByConversation[id]
        )
            mergeMessages(id, [normalizeMessage(message)]);
        const conversation = state.conversations.find(
            (item) => item.public_id === id
        );
        if (conversation) {
            conversation.last_message = {
                public_id: message.public_id,
                body: message.body,
                subject: message.subject,
                sender: message.sender?.name,
                priority: message.priority,
                sent_at: message.sent_at,
            };
            conversation.last_message_at = message.sent_at;
            sortConversations();
        }
    },

    updateRealtimeMessage(message) {
        const id = state.activeConversation?.public_id;
        if (!id || message?.conversation_id !== id) return;
        const current = state.messagesByConversation[id]?.find(
            (item) => item.public_id === message.public_id
        );
        const reactions = (message.reactions || []).map((reaction) => ({
            ...reaction,
            mine:
                current?.reactions?.find(
                    (item) => item.reaction === reaction.reaction
                )?.mine || false,
        }));
        mergeMessages(id, [
            normalizeMessage({ ...message, reactions }),
        ]);
    },

    removeRealtimeMessage({
        conversation_id: conversationId,
        message_id: messageId,
    }) {
        if (!conversationId || !state.messagesByConversation[conversationId])
            return;
        state.messagesByConversation[conversationId] =
            state.messagesByConversation[conversationId].filter(
                (message) => message.public_id !== messageId
            );
    },

    applyReactionChange({
        message_id: messageId,
        user_id: userId,
        reaction,
        active,
    }) {
        const id = state.activeConversation?.public_id;
        const message = id
            ? state.messagesByConversation[id]?.find(
                  (item) => item.public_id === messageId
              )
            : null;
        if (!message) return;
        const reactions = [...(message.reactions || [])];
        const index = reactions.findIndex((item) => item.reaction === reaction);
        if (active) {
            if (index >= 0) {
                reactions[index] = {
                    ...reactions[index],
                    count: Number(reactions[index].count || 0) + 1,
                    mine:
                        reactions[index].mine ||
                        Number(userId) === currentUserId(),
                };
            } else {
                reactions.push({
                    reaction,
                    count: 1,
                    mine: Number(userId) === currentUserId(),
                });
            }
        } else if (index >= 0) {
            const count = Math.max(0, Number(reactions[index].count || 0) - 1);
            if (count)
                reactions[index] = {
                    ...reactions[index],
                    count,
                    mine:
                        Number(userId) === currentUserId()
                            ? false
                            : reactions[index].mine,
                };
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
        const message = id
            ? state.messagesByConversation[id]?.find(
                  (item) => item.public_id === acknowledgement?.message_id
        )
            : null;
        if (!message) return;
        const key = `${acknowledgement.message_id}:${acknowledgement.user_id}`;
        if (processedAcknowledgements.has(key)) return;
        processedAcknowledgements.add(key);
        if (processedAcknowledgements.size > 1000)
            processedAcknowledgements.delete(
                processedAcknowledgements.values().next().value
            );
        const patch = { ...message };
        const acknowledgedByCurrentUser =
            Number(acknowledgement.user_id) === currentUserId();
        if (acknowledgedByCurrentUser) {
            patch.acknowledgement_status = "acknowledged";
            state.summary.pending_acknowledgements = Math.max(
                0,
                Number(state.summary.pending_acknowledgements || 0) - 1
            );
        }
        if (Number(message.sender_id) === currentUserId()) {
            if (acknowledgement.summary)
                patch.acknowledgement_summary = acknowledgement.summary;
            else if (patch.acknowledgement_summary) {
                const summary = { ...patch.acknowledgement_summary };
                summary.acknowledged = Math.min(
                    Number(summary.total || Number.MAX_SAFE_INTEGER),
                    Number(summary.acknowledged || 0) + 1
                );
                if (Number(summary.overdue || 0) > 0)
                    summary.overdue = Math.max(
                        0,
                        Number(summary.overdue) - 1
                    );
                else
                    summary.pending = Math.max(
                        0,
                        Number(summary.pending || 0) - 1
                    );
                patch.acknowledgement_summary = summary;
            }
        }
        mergeMessages(id, [patch]);
    },

    async applyConversationChange(change, allowRefresh = true) {
        if (!change?.conversation_id) return;
        const changeKey = conversationChangeKey(change);
        if (processedChanges.has(changeKey)) return;
        processedChanges.add(changeKey);
        if (processedChanges.size > 500)
            processedChanges.delete(processedChanges.values().next().value);

        const userId = currentUserId();
        if (
            change.action === "participant_removed" &&
            Number(change.removed_user_id) === userId
        ) {
            purgeConversationState(change.conversation_id);
            if (allowRefresh) await this.loadSummary();
            return;
        }

        let conversation = state.conversations.find(
            (item) => item.public_id === change.conversation_id
        );
        if (
            !conversation &&
            state.activeConversation?.public_id === change.conversation_id
        )
            conversation = state.activeConversation;
        if (!conversation) {
            const refreshableUnknownActions = [
                "conversation_created",
                "message_created",
                "message_updated",
            ];
            if (
                !allowRefresh ||
                !refreshableUnknownActions.includes(change.action)
            )
                return;
            await scheduleRealtimeGlobalRefresh(
                change.action !== "conversation_created"
            );
            conversation = state.conversations.find(
                (item) => item.public_id === change.conversation_id
            );
            if (!conversation) return;
        }

        if (Object.prototype.hasOwnProperty.call(change, "is_locked"))
            conversation.is_locked = change.is_locked;
        if (
            Object.prototype.hasOwnProperty.call(change, "last_message") &&
            (change.last_message !== null ||
                change.action === "message_deleted")
        )
            conversation.last_message = change.last_message;
        if (change.last_message?.sent_at)
            conversation.last_message_at = change.last_message.sent_at;
        if (change.last_message_at !== undefined)
            conversation.last_message_at = change.last_message_at;
        if (change.action === "message_deleted" && change.message_id)
            this.removeRealtimeMessage({
                conversation_id: change.conversation_id,
                message_id: change.message_id,
            });
        if (
            change.action === "message_reaction_updated" &&
            change.reaction
        )
            this.applyReactionChange(change.reaction);
        if (change.action === "messages_read" && change.receipt)
            this.applyReadReceipt(change.receipt);

        let readWhileVisible = false;
        if (
            ["message_created", "message_updated"].includes(change.action) &&
            state.activeConversation?.public_id === change.conversation_id &&
            document.visibilityState === "visible"
        ) {
            try {
                const added = await this.pollActiveConversation();
                const referencedId =
                    change.message_reference?.public_id ||
                    change.last_message?.public_id;
                readWhileVisible =
                    change.action === "message_created" &&
                    (added > 0 ||
                        Boolean(
                            referencedId &&
                                messageIdAtLeast(
                                    change.conversation_id,
                                    confirmedReadWatermarkByConversation.get(
                                        change.conversation_id
                                    ),
                                    referencedId
                                )
                        ));
            } catch (_) {
                readWhileVisible = false;
            }
        }

        if (
            change.action === "message_created" &&
            Number(change.actor_id) !== userId
        ) {

            if (!readWhileVisible) {
                const unreadDelta = Number(change.unread_delta || 1);
                const previouslyUnread = Number(
                    conversation.unread_count || 0
                );
                conversation.unread_count = previouslyUnread + unreadDelta;
                state.summary.unread_messages =
                    Number(state.summary.unread_messages || 0) + unreadDelta;
                if (previouslyUnread === 0)
                    state.summary.unread_conversations =
                        Number(state.summary.unread_conversations || 0) + 1;
                notifyCountersChanged();
            }
        }

        if (
            change.action === "messages_read" &&
            Number(change.reader_id) === userId
        ) {
            const previous = Number(conversation.unread_count || 0);
            conversation.unread_count = Number(change.unread_count || 0);
            state.summary.unread_messages = Math.max(
                0,
                Number(state.summary.unread_messages || 0) -
                    Math.max(previous, Number(change.read_count || 0))
            );
            if (previous > 0)
                state.summary.unread_conversations = Math.max(
                    0,
                    Number(state.summary.unread_conversations || 0) - 1
                );
            notifyCountersChanged();
        }

        if (
            change.action === "message_acknowledged" &&
            change.acknowledgement
        ) {
            this.applyAcknowledgement(change.acknowledgement);
        }

        if (
            [
                "conversation_updated",
                "participants_updated",
                "ownership_transferred",
            ].includes(change.action) &&
            state.activeConversation?.public_id === change.conversation_id
        ) {
            try {
                await loadConversationDetail(change.conversation_id);
            } catch (error) {
                if (
                    error.response?.status === 403 ||
                    error.response?.status === 404
                ) {
                    purgeConversationState(change.conversation_id);
                }
            }
        }

        if (change.action === "message_deleted" && allowRefresh)
            this.loadSummary().catch(() => {});
        sortConversations();
    },

    applyTransportSummary(summary) {
        if (!summary) return;
        const previousUnread = Number(state.summary.unread_messages || 0);
        state.summary = { ...state.summary, ...summary };
        if (
            previousUnread !== Number(state.summary.unread_messages || 0)
        )
            notifyCountersChanged();
    },

    setPollingState(status) {
        state.pollingConnectionState = status;
    },

    resetForAccessRevoked() {
        accessGeneration += 1;
        openRequestSequence += 1;
        conversationRequestSequence += 1;
        appliedConversationRequestSequence = conversationRequestSequence;
        recovering = false;
        lastConversationParams = {};
        configLoaded = false;
        configRequest = null;
        summaryRequest = null;
        realtimeGlobalRefreshRequest = null;
        realtimeGlobalRefreshNeedsSummary = false;
        readRequests.clear();
        processedChanges.clear();
        processedAcknowledgements.clear();
        syncCursorByConversation.clear();
        confirmedReadWatermarkByConversation.clear();
        conversationRequests.clear();
        activePollRequests.clear();
        conversationDetailRequests.clear();
        state.conversations = [];
        state.activeConversation = null;
        state.messagesByConversation = {};
        state.hasOlderByConversation = {};
        state.summary = {};
        state.config = {};
        state.error = null;
        Object.assign(state.loading, {
            conversations: false,
            messages: false,
            older: false,
            sending: false,
        });
        state.pollingConnectionState = "unavailable";
    },
};
