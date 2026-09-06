// @vitest-environment jsdom

import { describe, expect, it, vi } from "vitest";
import axios from "axios";
import api from "../../resources/js/modules/messaging/api/messagingApi";
import { messagingStore } from "../../resources/js/modules/messaging/stores/messagingStore";

vi.mock("axios", () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));

describe("messagingApi", () => {
  it("uses cursor history and explicit acknowledgement endpoints", async () => {
    axios.get.mockResolvedValue({ data: { data: [] } });
    axios.post.mockResolvedValue({ data: { data: { status: "acknowledged" } } });
    await api.messages("conversation-ulid", { before: "message-ulid" });
    await api.participants("conversation-ulid", { limit: 100, cursor: "participant-cursor" });
    await api.receipts("message-ulid", { limit: 100, cursor: "receipt-cursor" });
    await api.acknowledge("message-ulid", "Recibido");
    expect(axios.get).toHaveBeenCalledWith("/api/messaging/conversations/conversation-ulid/messages", { params: { before: "message-ulid" } });
    expect(axios.get).toHaveBeenCalledWith("/api/messaging/conversations/conversation-ulid/participants", { params: { limit: 100, cursor: "participant-cursor" } });
    expect(axios.get).toHaveBeenCalledWith("/api/messaging/messages/message-ulid/receipts", { params: { limit: 100, cursor: "receipt-cursor" } });
    expect(axios.post).toHaveBeenCalledWith("/api/messaging/messages/message-ulid/acknowledge", { comment: "Recibido" });
  });

  it("performs one cursor catch-up after reconnecting", async () => {
    Object.defineProperty(globalThis, "navigator", { value: { onLine: true }, configurable: true });
    Object.defineProperty(document, "visibilityState", { value: "hidden", configurable: true });
    axios.post.mockResolvedValue({ data: { data: { updated: 0 } } });
    axios.get.mockImplementation((url, options = {}) => {
      if (url.endsWith("/conversations/recovery-conversation")) {
        return Promise.resolve({ data: { data: { public_id: "recovery-conversation", unread_count: 0 } } });
      }
      if (url.endsWith("/conversations/recovery-conversation/messages")) {
        if (options.params?.updated_since) {
          return Promise.resolve({ data: {
            data: [{ public_id: "message-one", body: "Editado durante la desconexión", sent_at: "2026-08-17T08:00:00Z" }],
            deleted: [{ public_id: "message-stale", deleted_at: "2026-08-17T08:02:30Z" }],
            has_more: false,
            sync_cursor: "2026-08-17T08:03:00Z",
          } });
        }
        if (options.params?.after_id === "message-one") {
          return Promise.resolve({ data: { data: [{ public_id: "message-two", sent_at: "2026-08-17T08:01:00Z" }], has_more: true } });
        }
        if (options.params?.after_id === "message-two") {
          return Promise.resolve({ data: { data: [{ public_id: "message-three", sent_at: "2026-08-17T08:02:00Z" }], has_more: false } });
        }
        return Promise.resolve({ data: { data: [
          { public_id: "message-one", sent_at: "2026-08-17T08:00:00Z" },
          { public_id: "message-stale", sent_at: "2026-08-17T07:59:00Z" },
        ], has_more: false } });
      }
      if (url.endsWith("/conversations")) return Promise.resolve({ data: { data: [] } });
      if (url.endsWith("/summary")) return Promise.resolve({ data: { data: {} } });
      return Promise.resolve({ data: { data: [] } });
    });

    await messagingStore.open("recovery-conversation");
    axios.get.mockClear();
    await messagingStore.recoverAfterReconnect();

    expect(axios.get).toHaveBeenCalledWith(
      "/api/messaging/conversations/recovery-conversation/messages",
      { params: { after_id: "message-one", limit: 100 } }
    );
    expect(axios.get).toHaveBeenCalledWith(
      "/api/messaging/conversations/recovery-conversation/messages",
      { params: { after_id: "message-two", limit: 100 } }
    );
    expect(axios.get.mock.calls.filter(([url]) => url.endsWith("/recovery-conversation/messages"))).toHaveLength(3);
    expect(messagingStore.state.messagesByConversation["recovery-conversation"].map((message) => message.public_id)).toEqual([
      "message-one",
      "message-two",
      "message-three",
    ]);
    expect(messagingStore.state.messagesByConversation["recovery-conversation"][0].body).toBe("Editado durante la desconexión");
  });

  it("deduplicates active polling and does not mark read without new data", async () => {
    Object.defineProperty(document, "visibilityState", { value: "visible", configurable: true });
    axios.post.mockResolvedValue({ data: { data: { updated: 1 } } });
    let polling = false;
    axios.get.mockImplementation((url) => {
      if (url.endsWith("/conversations/dedupe-conversation")) {
        return Promise.resolve({ data: { data: { public_id: "dedupe-conversation", unread_count: 1 } } });
      }
      if (url.endsWith("/conversations/dedupe-conversation/messages")) {
        return Promise.resolve({ data: polling
          ? { data: [{ public_id: "01K0000000000000000000000A", body: "Editado", sent_at: "2026-09-06T12:00:00Z" }], has_more: false, sync_cursor: "2026-09-06T12:00:10Z" }
          : { data: [
              { public_id: "01K0000000000000000000000A", sent_at: "2026-09-06T12:00:00Z" },
              { public_id: "01K0000000000000000000000Z", sent_at: "2026-09-06T12:00:00Z" },
            ], has_more: false, sync_cursor: "2026-09-06T12:00:01Z" } });
      }
      return Promise.resolve({ data: { data: [] } });
    });

    await messagingStore.open("dedupe-conversation");
    polling = true;
    axios.get.mockClear();
    axios.post.mockClear();

    await Promise.all([
      messagingStore.pollActiveConversation(),
      messagingStore.pollActiveConversation(),
    ]);

    expect(axios.get.mock.calls.filter(([url]) => url.endsWith("/dedupe-conversation/messages"))).toHaveLength(2);
    expect(axios.post).not.toHaveBeenCalled();

    // Los ULID comparten milisegundo y su orden léxico es el inverso del
    // orden entregado por el backend. Debe prevalecer el orden cargado.
    await messagingStore.markRead("dedupe-conversation", "01K0000000000000000000000Z");
    expect(axios.post).not.toHaveBeenCalled();
  });

  it("pagina ráfagas mayores al límite sin perder mensajes", async () => {
    Object.defineProperty(document, "visibilityState", { value: "hidden", configurable: true });
    axios.post.mockResolvedValue({ data: { data: { updated: 0 } } });
    let polling = false;
    const page = (from, count) => Array.from({ length: count }, (_, index) => {
      const number = from + index;
      return {
        public_id: `burst-${String(number).padStart(4, "0")}`,
        sent_at: new Date(Date.UTC(2026, 8, 6, 12, 0, number)).toISOString(),
      };
    });
    axios.get.mockImplementation((url, options = {}) => {
      if (url.endsWith("/conversations/burst-conversation")) {
        return Promise.resolve({ data: { data: { public_id: "burst-conversation", unread_count: 0 } } });
      }
      if (url.endsWith("/conversations/burst-conversation/messages")) {
        if (!polling) return Promise.resolve({ data: { data: [{ public_id: "burst-0000", sent_at: "2026-09-06T12:00:00Z" }], has_more: false, sync_cursor: "2026-09-06T12:00:01Z" } });
        if (options.params?.updated_since) return Promise.resolve({ data: { data: [], has_more: false, sync_cursor: "2026-09-06T12:05:00Z" } });
        if (options.params?.after_id === "burst-0000") return Promise.resolve({ data: { data: page(1, 100), has_more: true } });
        if (options.params?.after_id === "burst-0100") return Promise.resolve({ data: { data: page(101, 100), has_more: true } });
        if (options.params?.after_id === "burst-0200") return Promise.resolve({ data: { data: page(201, 50), has_more: false } });
      }
      return Promise.resolve({ data: { data: [] } });
    });

    await messagingStore.open("burst-conversation");
    polling = true;
    axios.get.mockClear();
    await messagingStore.pollActiveConversation();

    const messageCalls = axios.get.mock.calls.filter(([url]) => url.endsWith("/burst-conversation/messages"));
    expect(messageCalls.filter(([, options]) => options.params?.after_id)).toHaveLength(3);
    expect(messagingStore.state.messagesByConversation["burst-conversation"]).toHaveLength(251);
    expect(messagingStore.state.messagesByConversation["burst-conversation"].at(-1).public_id).toBe("burst-0250");
  });

  it("usa el unread fiable de la lista antes de confirmar lectura", async () => {
    const listed = [
      { public_id: "unread-conversation", unread_count: 2, last_message: { public_id: "unread-message" } },
      { public_id: "read-conversation", unread_count: 0, last_message: { public_id: "read-message" } },
    ];
    axios.post.mockResolvedValue({ data: { data: { updated: 2 } } });
    axios.get.mockImplementation((url) => {
      if (url.endsWith("/conversations")) return Promise.resolve({ data: { data: listed } });
      if (url.endsWith("/conversations/unread-conversation")) return Promise.resolve({ data: { data: listed[0] } });
      if (url.endsWith("/conversations/read-conversation")) return Promise.resolve({ data: { data: listed[1] } });
      if (url.endsWith("/conversations/unread-conversation/messages")) return Promise.resolve({ data: { data: [{ public_id: "unread-message", sent_at: "2026-09-06T13:00:00Z" }], has_more: false } });
      if (url.endsWith("/conversations/read-conversation/messages")) return Promise.resolve({ data: { data: [{ public_id: "read-message", sent_at: "2026-09-06T13:01:00Z" }], has_more: false } });
      return Promise.resolve({ data: { data: [] } });
    });

    await messagingStore.loadConversations({ contract: "read-state" });
    await messagingStore.open("unread-conversation");
    expect(axios.post).toHaveBeenCalledTimes(1);

    axios.post.mockClear();
    await messagingStore.open("read-conversation");
    expect(axios.post).not.toHaveBeenCalled();
  });

  it("keeps the latest conversation when opens resolve out of order", async () => {
    const deferred = new Map();
    const response = (key) => new Promise((resolve) => deferred.set(key, resolve));
    axios.get.mockImplementation((url) => {
      if (url.endsWith("/messages")) return response(`${url}:messages`);
      return response(`${url}:conversation`);
    });

    const first = messagingStore.open("conversation-a");
    const second = messagingStore.open("conversation-b");
    deferred.get("/api/messaging/conversations/conversation-b:conversation")({ data: { data: { public_id: "conversation-b" } } });
    deferred.get("/api/messaging/conversations/conversation-b/messages:messages")({ data: { data: [], has_more: false } });
    await second;
    deferred.get("/api/messaging/conversations/conversation-a:conversation")({ data: { data: { public_id: "conversation-a" } } });
    deferred.get("/api/messaging/conversations/conversation-a/messages:messages")({ data: { data: [], has_more: false } });
    await first;

    expect(messagingStore.state.activeConversation.public_id).toBe("conversation-b");
  });

  it("hydrates created and updated references and applies all consolidated conversation deltas", async () => {
    Object.defineProperty(document, "visibilityState", { value: "visible", configurable: true });
    let phase = "initial";
    axios.get.mockImplementation((url, options = {}) => {
      if (url.endsWith("/config")) {
        return Promise.resolve({ data: {
          enabled: true,
          user: { id: 17 },
          polling: { recovery_limit: 100 },
        } });
      }
      if (url.endsWith("/conversations/event-conversation")) {
        return Promise.resolve({ data: { data: {
          public_id: "event-conversation",
          unread_count: 0,
          last_message: { public_id: "base-two", body: "Segundo" },
        } } });
      }
      if (url.endsWith("/conversations/event-conversation/messages")) {
        if (phase === "initial") {
          return Promise.resolve({ data: {
            data: [
              {
                public_id: "base-two",
                sender_id: 55,
                body: "Segundo",
                sent_at: "2026-09-06T14:01:00Z",
                reactions: [],
              },
              {
                public_id: "base-one",
                sender_id: 17,
                body: "Primero",
                sent_at: "2026-09-06T14:00:00Z",
                reactions: [],
                acknowledgement_summary: { total: 2, acknowledged: 0, pending: 2, overdue: 0 },
              },
            ],
            has_more: false,
            sync_cursor: "2026-09-06T14:01:30Z",
          } });
        }
        if (phase === "created" && options.params?.after_id === "base-two") {
          return Promise.resolve({ data: {
            data: [{
              public_id: "own-device-message",
              conversation_id: "event-conversation",
              sender_id: 17,
              body: "Creado desde otro dispositivo",
              sent_at: "2026-09-06T14:02:00Z",
              reactions: [],
            }],
            has_more: false,
          } });
        }
        if (phase === "updated" && options.params?.updated_since) {
          return Promise.resolve({ data: {
            data: [{
              public_id: "base-one",
              sender_id: 17,
              body: "Primero editado",
              sent_at: "2026-09-06T14:00:00Z",
              reactions: [],
              acknowledgement_summary: { total: 2, acknowledged: 0, pending: 2, overdue: 0 },
            }],
            has_more: false,
            sync_cursor: "2026-09-06T14:03:00Z",
          } });
        }
        return Promise.resolve({ data: {
          data: [],
          has_more: false,
          sync_cursor: phase === "created"
            ? "2026-09-06T14:02:30Z"
            : "2026-09-06T14:03:00Z",
        } });
      }
      return Promise.resolve({ data: { data: [] } });
    });

    await messagingStore.loadConfig(true);
    await messagingStore.open("event-conversation");
    axios.post.mockClear();

    phase = "created";
    await messagingStore.applyConversationChange({
      action: "message_created",
      conversation_id: "event-conversation",
      actor_id: 17,
      occurred_at: "2026-09-06T14:02:01Z",
      message_reference: { public_id: "own-device-message" },
      last_message: { public_id: "own-device-message", body: "Creado desde otro dispositivo" },
    }, false);
    expect(messagingStore.state.messagesByConversation["event-conversation"])
      .toEqual(expect.arrayContaining([
        expect.objectContaining({ public_id: "own-device-message" }),
      ]));

    phase = "updated";
    await messagingStore.applyConversationChange({
      action: "message_updated",
      conversation_id: "event-conversation",
      actor_id: 17,
      occurred_at: "2026-09-06T14:03:01Z",
      message_reference: { public_id: "base-one", version: 2 },
      last_message: null,
    }, false);

    const sharedTimestamp = "2026-09-06T14:04:00Z";
    await messagingStore.applyConversationChange({
      action: "message_reaction_updated",
      conversation_id: "event-conversation",
      occurred_at: sharedTimestamp,
      reaction: { message_id: "base-one", user_id: 55, reaction: "like", active: true },
    }, false);
    await messagingStore.applyConversationChange({
      action: "message_reaction_updated",
      conversation_id: "event-conversation",
      occurred_at: sharedTimestamp,
      reaction: { message_id: "base-two", user_id: 55, reaction: "like", active: true },
    }, false);
    const receipt = {
      conversation_id: "event-conversation",
      reader_id: 55,
      through_message_id: "own-device-message",
      read_at: "2026-09-06T14:04:30Z",
    };
    await messagingStore.applyConversationChange({
      action: "messages_read",
      conversation_id: "event-conversation",
      occurred_at: "2026-09-06T14:04:30Z",
      reader_id: 55,
      receipt,
    }, false);
    await messagingStore.applyConversationChange({
      action: "message_acknowledged",
      conversation_id: "event-conversation",
      occurred_at: "2026-09-06T14:05:00Z",
      message_id: "base-one",
      acknowledgement: {
        message_id: "base-one",
        user_id: 55,
        acknowledged_at: "2026-09-06T14:05:00Z",
      },
    }, false);
    await messagingStore.applyConversationChange({
      action: "message_deleted",
      conversation_id: "event-conversation",
      occurred_at: "2026-09-06T14:06:00Z",
      message_id: "own-device-message",
      last_message: { public_id: "base-two", body: "Segundo" },
    }, false);

    const messages = messagingStore.state.messagesByConversation["event-conversation"];
    expect(messages.find(({ public_id }) => public_id === "base-one")).toMatchObject({
      body: "Primero editado",
      reactions: [{ reaction: "like", count: 1 }],
      acknowledgement_summary: { total: 2, acknowledged: 1, pending: 1, overdue: 0 },
    });
    expect(messages.find(({ public_id }) => public_id === "base-two")).toMatchObject({
      reactions: [{ reaction: "like", count: 1 }],
    });
    expect(messages.some(({ public_id }) => public_id === "own-device-message")).toBe(false);
    expect(messagingStore.state.activeConversation.last_read_receipt).toEqual(receipt);
    expect(messagingStore.state.activeConversation.last_message.public_id).toBe("base-two");
    expect(axios.post).not.toHaveBeenCalled();
  });

  it("coalesces global refresh for an event outside the loaded conversation page", async () => {
    axios.get.mockImplementation((url) => {
      if (url.endsWith("/conversations")) return Promise.resolve({ data: { data: [] } });
      if (url.endsWith("/summary")) return Promise.resolve({ data: { unread_messages: 9, unread_conversations: 3 } });
      return Promise.resolve({ data: { data: [] } });
    });
    axios.get.mockClear();

    await Promise.all([
      messagingStore.applyConversationChange({
        action: "message_created",
        conversation_id: "outside-page-conversation",
        occurred_at: "2026-09-06T15:00:00Z",
        message_reference: { public_id: "outside-message-one" },
      }, true),
      messagingStore.applyConversationChange({
        action: "message_updated",
        conversation_id: "outside-page-conversation",
        occurred_at: "2026-09-06T15:00:00Z",
        message_reference: { public_id: "outside-message-two" },
      }, true),
    ]);

    expect(axios.get.mock.calls.filter(([url]) => url.endsWith("/conversations"))).toHaveLength(1);
    expect(axios.get.mock.calls.filter(([url]) => url.endsWith("/summary"))).toHaveLength(1);
    expect(messagingStore.state.summary.unread_messages).toBe(9);
  });

  it("announces a locally sent message so sibling tabs receive it", async () => {
    const activeConversationId = messagingStore.state.activeConversation.public_id;
    const sent = {
      public_id: "01K0000000000000000000000Z",
      body: "Mensaje entre pestañas",
      sent_at: "2026-09-06T12:10:00Z",
      sender: { name: "Usuario" },
    };
    axios.post.mockResolvedValue({ data: { data: sent } });
    const listener = vi.fn();
    window.addEventListener("messaging:local-event", listener, { once: true });

    await messagingStore.send({ body: sent.body, sender_id: 1 });

    expect(listener).toHaveBeenCalledTimes(1);
    expect(listener.mock.calls[0][0].detail).toMatchObject({
      kind: "sentMessage",
      payload: { message: { public_id: sent.public_id, conversation_id: activeConversationId } },
    });
  });

  it("purges all loaded data when a fallback check reports revoked staff access", async () => {
    expect(messagingStore.state.activeConversation).not.toBeNull();
    expect(Object.keys(messagingStore.state.messagesByConversation).length).toBeGreaterThan(0);
    const revoked = vi.fn(() => messagingStore.resetForAccessRevoked());
    window.addEventListener("messaging:access-revoked", revoked, { once: true });
    axios.get.mockRejectedValueOnce({
      response: {
        status: 403,
        data: { code: "MESSAGING_STAFF_ONLY" },
      },
    });

    await expect(api.summary()).rejects.toMatchObject({ response: { status: 403 } });

    expect(revoked).toHaveBeenCalledTimes(1);
    expect(messagingStore.state.config).toEqual({});
    expect(messagingStore.state.summary).toEqual({});
    expect(messagingStore.state.conversations).toEqual([]);
    expect(messagingStore.state.activeConversation).toBeNull();
    expect(messagingStore.state.messagesByConversation).toEqual({});
    expect(messagingStore.state.hasOlderByConversation).toEqual({});
    expect(messagingStore.state.pollingConnectionState).toBe("unavailable");
  });
});
