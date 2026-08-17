import { describe, expect, it, vi } from "vitest";
import axios from "axios";
import api from "../../resources/js/modules/messaging/api/messagingApi";
import { messagingStore } from "../../resources/js/modules/messaging/stores/messagingStore";

vi.mock("axios", () => ({ default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }));

describe("messagingApi", () => {
  it("uses incremental history and explicit acknowledgement endpoints", async () => {
    axios.get.mockResolvedValue({ data: { data: [] } });
    axios.post.mockResolvedValue({ data: { data: { status: "acknowledged" } } });
    await api.messages("conversation-ulid", { since: "message-ulid" });
    await api.acknowledge("message-ulid", "Recibido");
    expect(axios.get).toHaveBeenCalledWith("/api/messaging/conversations/conversation-ulid/messages", { params: { since: "message-ulid" } });
    expect(axios.post).toHaveBeenCalledWith("/api/messaging/messages/message-ulid/acknowledge", { comment: "Recibido" });
  });

  it("polls conversation changes even when the active conversation started empty", async () => {
    Object.defineProperty(globalThis, "navigator", { value: { onLine: true }, configurable: true });
    axios.get.mockImplementation((url, options) => {
      if (url.endsWith("/conversations/empty-conversation")) {
        return Promise.resolve({ data: { data: { public_id: "empty-conversation" } } });
      }
      if (url.endsWith("/conversations/empty-conversation/messages")) {
        return Promise.resolve({ data: { data: [] } });
      }
      if (url.endsWith("/conversations")) return Promise.resolve({ data: { data: [] } });
      if (url.endsWith("/summary")) return Promise.resolve({ data: {} });
      return Promise.resolve({ data: { data: [] }, options });
    });

    await messagingStore.open("empty-conversation");
    axios.get.mockClear();
    await messagingStore.sync();

    expect(axios.get).toHaveBeenCalledWith(
      "/api/messaging/conversations/empty-conversation/messages",
      { params: expect.objectContaining({ updated_since: expect.any(String), limit: 100 }) }
    );
  });
});
