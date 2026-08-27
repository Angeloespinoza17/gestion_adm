// @vitest-environment jsdom

import { defineComponent, h, ref } from "vue";
import { mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const store = vi.hoisted(() => ({
    state: {
        config: {
            polling: { interval_ms: 5000, active_interval_ms: 5000 },
        },
    },
    loadSummary: vi.fn().mockResolvedValue({}),
    loadConversations: vi.fn().mockResolvedValue([]),
    pollActiveConversation: vi.fn().mockResolvedValue(0),
    setPollingState: vi.fn(),
}));

vi.mock("../../resources/js/modules/messaging/stores/messagingStore", () => ({
    messagingStore: store,
}));

import { useMessagingHttpPolling } from "../../resources/js/modules/messaging/composables/useMessagingHttpPolling";

describe("mensajería por consultas HTTP", () => {
    beforeEach(() => {
        vi.useFakeTimers();
        Object.defineProperty(globalThis, "navigator", {
            value: { onLine: true },
            configurable: true,
        });
        Object.defineProperty(document, "visibilityState", {
            value: "visible",
            configurable: true,
        });
        Object.values(store)
            .filter((value) => typeof value?.mockClear === "function")
            .forEach((mock) => mock.mockClear());
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it("refreshes summary and conversations without creating a WebSocket", async () => {
        const enabled = ref(true);
        const activeId = ref(null);
        const refreshConversations = vi.fn().mockResolvedValue([]);
        const wrapper = mount(
            defineComponent({
                setup() {
                    useMessagingHttpPolling(activeId, {
                        enabled,
                        refreshConversations,
                    });
                    return () => h("div");
                },
            })
        );

        await vi.runOnlyPendingTimersAsync();
        expect(store.loadSummary).toHaveBeenCalled();
        expect(refreshConversations).toHaveBeenCalled();
        expect(store.setPollingState).toHaveBeenCalledWith("polling");

        wrapper.unmount();
    });

    it("pauses cleanly while offline and removes timers on unmount", async () => {
        const enabled = ref(true);
        const wrapper = mount(
            defineComponent({
                setup() {
                    useMessagingHttpPolling(ref(null), { enabled });
                    return () => h("div");
                },
            })
        );

        window.dispatchEvent(new Event("offline"));
        expect(store.setPollingState).toHaveBeenCalledWith("offline");
        wrapper.unmount();
        const calls = store.loadSummary.mock.calls.length;
        await vi.advanceTimersByTimeAsync(30000);
        expect(store.loadSummary).toHaveBeenCalledTimes(calls);
    });
});
