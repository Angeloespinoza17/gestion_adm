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
    await api.acknowledge("message-ulid", "Recibido");
    expect(axios.get).toHaveBeenCalledWith("/api/messaging/conversations/conversation-ulid/messages", { params: { before: "message-ulid" } });
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
        if (options.params?.after_id === "message-one") {
          return Promise.resolve({ data: { data: [{ public_id: "message-two", sent_at: "2026-08-17T08:01:00Z" }], has_more: true } });
        }
        if (options.params?.after_id === "message-two") {
          return Promise.resolve({ data: { data: [{ public_id: "message-three", sent_at: "2026-08-17T08:02:00Z" }], has_more: false } });
        }
        return Promise.resolve({ data: { data: [{ public_id: "message-one", sent_at: "2026-08-17T08:00:00Z" }], has_more: false } });
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
    expect(axios.get.mock.calls.filter(([url]) => url.endsWith("/recovery-conversation/messages"))).toHaveLength(2);
    expect(messagingStore.state.messagesByConversation["recovery-conversation"].map((message) => message.public_id)).toEqual([
      "message-one",
      "message-two",
      "message-three",
    ]);
  });
});
