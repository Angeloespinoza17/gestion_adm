// @vitest-environment jsdom

import { defineComponent, h, ref } from "vue";
import { flushPromises, mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const store = vi.hoisted(() => ({
    state: {
        config: {},
        summary: {},
    },
    loadConfig: vi.fn(),
    loadSummary: vi.fn().mockResolvedValue({}),
    loadConversations: vi.fn().mockResolvedValue([]),
    pollActiveConversation: vi.fn().mockResolvedValue(0),
    recoverAfterReconnect: vi.fn().mockResolvedValue(undefined),
    addRealtimeMessage: vi.fn(),
    applyRealtimeMessageReference: vi.fn().mockResolvedValue(0),
    updateRealtimeMessage: vi.fn(),
    removeRealtimeMessage: vi.fn(),
    applyReactionChange: vi.fn(),
    applyReadReceipt: vi.fn(),
    applyAcknowledgement: vi.fn(),
    applyConversationChange: vi.fn(),
    applyTransportSummary: vi.fn(),
    resetForAccessRevoked: vi.fn(),
    setPollingState: vi.fn((state) => {
        store.state.pollingConnectionState = state;
    }),
}));

vi.mock("../../resources/js/modules/messaging/stores/messagingStore", () => ({
    messagingStore: store,
}));

import { useMessagingTransport } from "../../resources/js/modules/messaging/composables/useMessagingTransport";
import { messagingTransport } from "../../resources/js/modules/messaging/services/messagingTransport";

const originalBroadcastChannel = globalThis.BroadcastChannel;

class RecordingBroadcastChannel {
    static messages = [];

    addEventListener() {}
    removeEventListener() {}
    close() {}
    postMessage(message) {
        RecordingBroadcastChannel.messages.push(message);
    }
}

const componentUsingTransport = (activeId, enabled, refreshConversations) =>
    mount(
        defineComponent({
            setup() {
                useMessagingTransport(activeId, {
                    enabled,
                    refreshConversations,
                });
                return () => h("div");
            },
        })
    );

const connectedEcho = () => {
    const handlers = new Map();
    const connection = {
        state: "connected",
        bind: vi.fn((event, callback) => handlers.set(event, callback)),
    };
    const channels = new Map();
    const instance = {
        connector: { pusher: { connection } },
        private: vi.fn((name) => {
            if (!channels.has(name)) {
                const channel = {
                    listen: vi.fn(() => channel),
                    error: vi.fn(() => channel),
                };
                channels.set(name, channel);
            }
            return channels.get(name);
        }),
        leave: vi.fn(),
        connect: vi.fn(),
        disconnect: vi.fn(),
        channels,
    };
    return instance;
};

describe("transporte híbrido singleton de mensajería", () => {
    beforeEach(() => {
        vi.useFakeTimers();
        localStorage.clear();
        localStorage.setItem("token", "test-token");
        Object.defineProperty(document, "visibilityState", {
            configurable: true,
            value: "visible",
        });
        Object.defineProperty(navigator, "onLine", {
            configurable: true,
            value: true,
        });
        Object.values(store)
            .filter((value) => typeof value?.mockClear === "function")
            .forEach((mock) => mock.mockClear());
        store.state.config = {
            enabled: true,
            user: { id: 17 },
            realtime: { enabled: true, connection_grace_ms: 10000 },
            polling: {
                enabled: true,
                interval_ms: 60000,
                active_interval_ms: 20000,
                max_interval_ms: 120000,
                reconciliation_interval_ms: 300000,
                jitter_ratio: 0,
            },
        };
        store.loadConfig.mockImplementation(async () => store.state.config);
        RecordingBroadcastChannel.messages = [];
    });

    afterEach(() => {
        messagingTransport.shutdown();
        delete window.__createMessagingEcho;
        delete window.Echo;
        Object.defineProperty(globalThis, "BroadcastChannel", {
            configurable: true,
            value: originalBroadcastChannel,
        });
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it("comparte una sola conexión Echo entre mini chat y vista completa", async () => {
        const echo = connectedEcho();
        window.__createMessagingEcho = vi.fn(async () => echo);
        const first = componentUsingTransport(ref("conversation-a"), ref(true));
        const second = componentUsingTransport(ref("conversation-a"), ref(true));

        await flushPromises();

        expect(window.__createMessagingEcho).toHaveBeenCalledTimes(1);
        expect(echo.private).toHaveBeenCalledWith("messaging.user.17");
        expect(echo.private).toHaveBeenCalledTimes(1);
        expect(store.setPollingState).toHaveBeenCalledWith("connected");

        await vi.advanceTimersByTimeAsync(120000);
        expect(store.loadSummary).not.toHaveBeenCalled();
        expect(store.pollActiveConversation).not.toHaveBeenCalled();

        first.unmount();
        expect(echo.disconnect).not.toHaveBeenCalled();
        second.unmount();
        expect(echo.disconnect).toHaveBeenCalledTimes(1);
    });

    it("corta transporte y purga estado cuando se revoca el acceso funcionario", async () => {
        const echo = connectedEcho();
        window.__createMessagingEcho = vi.fn(async () => echo);
        const wrapper = componentUsingTransport(
            ref("conversation-revoked"),
            ref(true)
        );
        await flushPromises();

        window.dispatchEvent(
            new CustomEvent("messaging:access-revoked", {
                detail: { code: "MESSAGING_STAFF_ONLY", status: 403 },
            })
        );
        await flushPromises();

        expect(store.resetForAccessRevoked).toHaveBeenCalledTimes(1);
        expect(echo.disconnect).toHaveBeenCalledTimes(1);
        expect(store.setPollingState).toHaveBeenCalledWith("unavailable");
        wrapper.unmount();
    });

    it("hidrata referencias compactas desde HTTP antes de tocar el hilo", async () => {
        Object.defineProperty(globalThis, "BroadcastChannel", {
            configurable: true,
            value: RecordingBroadcastChannel,
        });
        const echo = connectedEcho();
        window.__createMessagingEcho = vi.fn(async () => echo);
        const wrapper = componentUsingTransport(
            ref("conversation-secure"),
            ref(true)
        );
        await flushPromises();

        const channel = echo.channels.get("messaging.user.17");
        const listener = channel.listen.mock.calls.find(
            ([event]) => event === ".messaging.conversation.changed"
        )[1];
        listener({
            change: {
                action: "message_created",
                conversation_id: "conversation-secure",
                message_reference: { public_id: "message-reference" },
            },
        });
        await flushPromises();

        expect(store.applyConversationChange).toHaveBeenCalledWith(
            expect.objectContaining({
                action: "message_created",
                message_reference: { public_id: "message-reference" },
            }),
            true
        );
        expect(store.addRealtimeMessage).not.toHaveBeenCalled();
        expect(RecordingBroadcastChannel.messages).toContainEqual(
            expect.objectContaining({ type: "summary-snapshot" })
        );

        const remainingChanges = [
            {
                action: "message_updated",
                conversation_id: "conversation-secure",
                message_reference: { public_id: "message-reference" },
            },
            {
                action: "message_deleted",
                conversation_id: "conversation-secure",
                message_id: "message-reference",
            },
            {
                action: "message_reaction_updated",
                conversation_id: "conversation-secure",
                reaction: {
                    message_id: "message-reference",
                    user_id: 33,
                    reaction: "like",
                    active: true,
                },
            },
            {
                action: "messages_read",
                conversation_id: "conversation-secure",
                receipt: {
                    conversation_id: "conversation-secure",
                    reader_id: 33,
                    through_message_id: "message-reference",
                },
            },
            {
                action: "message_acknowledged",
                conversation_id: "conversation-secure",
                acknowledgement: {
                    message_id: "message-reference",
                    user_id: 33,
                },
            },
        ];
        remainingChanges.forEach((change) => listener({ change }));
        await flushPromises();

        remainingChanges.forEach((change) =>
            expect(store.applyConversationChange).toHaveBeenCalledWith(
                change,
                true
            )
        );
        expect(channel.listen).toHaveBeenCalledTimes(1);
        expect(store.applyReactionChange).not.toHaveBeenCalled();
        expect(store.applyReadReceipt).not.toHaveBeenCalled();
        expect(store.applyAcknowledgement).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it("usa fallback con pausa por visibilidad y recuperación inmediata", async () => {
        store.state.config.realtime.enabled = false;
        store.state.config.polling.active_interval_ms = 5000;
        const refreshConversations = vi.fn().mockResolvedValue([]);
        const wrapper = componentUsingTransport(
            ref("conversation-b"),
            ref(true),
            refreshConversations
        );
        await flushPromises();

        await vi.advanceTimersByTimeAsync(5000);
        expect(store.loadSummary).toHaveBeenCalledTimes(1);
        expect(refreshConversations).toHaveBeenCalledTimes(1);
        expect(store.pollActiveConversation).toHaveBeenCalledTimes(1);

        Object.defineProperty(document, "visibilityState", {
            configurable: true,
            value: "hidden",
        });
        document.dispatchEvent(new Event("visibilitychange"));
        await vi.advanceTimersByTimeAsync(60000);
        expect(store.loadSummary).toHaveBeenCalledTimes(1);

        Object.defineProperty(document, "visibilityState", {
            configurable: true,
            value: "visible",
        });
        document.dispatchEvent(new Event("visibilitychange"));
        await vi.advanceTimersByTimeAsync(0);
        expect(store.loadSummary).toHaveBeenCalledTimes(2);
        expect(store.pollActiveConversation).toHaveBeenCalledTimes(2);

        wrapper.unmount();
    });

    it("captura 503 de configuración y reintenta con espera exponencial", async () => {
        store.loadConfig.mockRejectedValueOnce({ response: { status: 503 } });
        const wrapper = componentUsingTransport(ref(null), ref(true));
        await flushPromises();

        expect(store.setPollingState).toHaveBeenCalledWith("unavailable");
        expect(store.loadConfig).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(59999);
        expect(store.loadConfig).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();
        expect(store.loadConfig).toHaveBeenCalledTimes(2);
        wrapper.unmount();
    });

    it("no insiste ante una configuración no autorizada", async () => {
        store.loadConfig.mockRejectedValueOnce({ response: { status: 401 } });
        const wrapper = componentUsingTransport(ref(null), ref(true));
        await flushPromises();

        expect(store.setPollingState).toHaveBeenCalledWith("unavailable");
        await vi.advanceTimersByTimeAsync(300000);
        expect(store.loadConfig).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
});
