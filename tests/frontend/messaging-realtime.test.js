// @vitest-environment jsdom

import { defineComponent, h, ref } from "vue";
import { mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { useMessagingRealtime } from "../../resources/js/modules/messaging/composables/useMessagingRealtime";
import { messagingStore } from "../../resources/js/modules/messaging/stores/messagingStore";

describe("useMessagingRealtime", () => {
  let syncSpy;

  beforeEach(() => {
    vi.useFakeTimers();
    Object.defineProperty(document, "visibilityState", { value: "visible", configurable: true });
    Object.defineProperty(globalThis, "navigator", { value: { onLine: true }, configurable: true });
    syncSpy = vi.spyOn(messagingStore, "sync").mockResolvedValue();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it("synchronizes every five seconds when config has not loaded yet", async () => {
    const wrapper = mount(defineComponent({
      setup() {
        useMessagingRealtime(ref(null));
        return () => h("div");
      },
    }));

    syncSpy.mockClear();
    await vi.advanceTimersByTimeAsync(4999);
    expect(syncSpy).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(1);
    expect(syncSpy).toHaveBeenCalledTimes(1);

    await vi.advanceTimersByTimeAsync(5000);
    expect(syncSpy).toHaveBeenCalledTimes(2);

    wrapper.unmount();
    await vi.advanceTimersByTimeAsync(5000);
    expect(syncSpy).toHaveBeenCalledTimes(2);
  });

  it("continues polling while the browser tab is in the background", async () => {
    Object.defineProperty(document, "visibilityState", { value: "hidden", configurable: true });
    const wrapper = mount(defineComponent({
      setup() {
        useMessagingRealtime(ref(null));
        return () => h("div");
      },
    }));

    syncSpy.mockClear();
    await vi.advanceTimersByTimeAsync(5000);
    expect(syncSpy).toHaveBeenCalledTimes(1);

    wrapper.unmount();
  });
});
