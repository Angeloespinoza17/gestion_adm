// @vitest-environment jsdom

import { defineComponent, h, nextTick, ref } from "vue";
import { mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const realtime = vi.hoisted(() => ({
  ensureMessagingRealtime: vi.fn().mockResolvedValue(null),
  reconnectMessagingRealtime: vi.fn(),
  subscribeMessagingConversation: vi.fn().mockResolvedValue(null),
}));

vi.mock("../../resources/js/modules/messaging/services/messagingRealtime", () => realtime);

import { useMessagingRealtime } from "../../resources/js/modules/messaging/composables/useMessagingRealtime";
import { messagingStore } from "../../resources/js/modules/messaging/stores/messagingStore";

describe("useMessagingRealtime", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    Object.defineProperty(globalThis, "navigator", { value: { onLine: true }, configurable: true });
    Object.values(realtime).forEach((mock) => mock.mockClear());
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it("does not start a periodic HTTP synchronization timer", async () => {
    const wrapper = mount(defineComponent({
      setup() {
        useMessagingRealtime(ref(null));
        return () => h("div");
      },
    }));

    await vi.advanceTimersByTimeAsync(30000);
    expect(realtime.ensureMessagingRealtime).not.toHaveBeenCalled();
    expect(realtime.subscribeMessagingConversation).toHaveBeenCalledTimes(1);
    expect(realtime.subscribeMessagingConversation).toHaveBeenCalledWith(null);

    wrapper.unmount();
  });

  it("switches the active private channel and removes network listeners on unmount", async () => {
    const activeId = ref("conversation-one");
    const offlineSpy = vi.spyOn(messagingStore, "setRealtime");
    const wrapper = mount(defineComponent({
      setup() {
        useMessagingRealtime(activeId);
        return () => h("div");
      },
    }));

    expect(realtime.subscribeMessagingConversation).toHaveBeenLastCalledWith("conversation-one");
    activeId.value = "conversation-two";
    await nextTick();
    expect(realtime.subscribeMessagingConversation).toHaveBeenLastCalledWith("conversation-two");

    window.dispatchEvent(new Event("offline"));
    window.dispatchEvent(new Event("online"));
    expect(offlineSpy).toHaveBeenCalledWith("offline");
    expect(realtime.reconnectMessagingRealtime).toHaveBeenCalledTimes(1);

    wrapper.unmount();
    window.dispatchEvent(new Event("online"));
    expect(realtime.reconnectMessagingRealtime).toHaveBeenCalledTimes(1);
  });
});
