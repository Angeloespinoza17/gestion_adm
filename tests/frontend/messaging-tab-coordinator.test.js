// @vitest-environment jsdom

import { afterEach, describe, expect, it } from "vitest";
import { createMessagingTabCoordinator } from "../../resources/js/modules/messaging/services/messagingTabCoordinator";
import { resolveMessagingRealtimeEndpoint } from "../../resources/js/modules/messaging/services/messagingTransport";

class FakeBroadcastChannel {
    static rooms = new Map();

    constructor(name) {
        this.name = name;
        this.listeners = new Set();
        if (!FakeBroadcastChannel.rooms.has(name))
            FakeBroadcastChannel.rooms.set(name, new Set());
        FakeBroadcastChannel.rooms.get(name).add(this);
    }

    addEventListener(_, listener) {
        this.listeners.add(listener);
    }

    removeEventListener(_, listener) {
        this.listeners.delete(listener);
    }

    postMessage(data) {
        FakeBroadcastChannel.rooms.get(this.name)?.forEach((channel) => {
            if (channel !== this)
                channel.listeners.forEach((listener) => listener({ data }));
        });
    }

    close() {
        FakeBroadcastChannel.rooms.get(this.name)?.delete(this);
    }
}

const memoryStorage = () => {
    const values = new Map();
    return {
        getItem: (key) => values.get(key) || null,
        setItem: (key, value) => values.set(key, String(value)),
        removeItem: (key) => values.delete(key),
    };
};

const fakeWindow = (storage) => ({
    localStorage: storage,
    addEventListener() {},
    removeEventListener() {},
    setInterval: () => 1,
    clearInterval() {},
});

describe("coordinación de mensajería entre pestañas", () => {
    afterEach(() => FakeBroadcastChannel.rooms.clear());

    it("elige una sola líder y libera el lease al cerrarse", () => {
        const storage = memoryStorage();
        const first = createMessagingTabCoordinator(25, {
            tabId: "first",
            storage,
            window: fakeWindow(storage),
            BroadcastChannel: FakeBroadcastChannel,
        });
        const second = createMessagingTabCoordinator(25, {
            tabId: "second",
            storage,
            window: fakeWindow(storage),
            BroadcastChannel: FakeBroadcastChannel,
        });

        first.start();
        second.start();

        expect(first.isLeader()).toBe(true);
        expect(second.isLeader()).toBe(false);

        const received = [];
        second.subscribe((message) => received.push(message));
        first.publish({
            type: "messaging-event",
            kind: "sentMessage",
            payload: { message: { public_id: "message-one" } },
        });
        expect(received).toContainEqual(
            expect.objectContaining({
                type: "messaging-event",
                kind: "sentMessage",
                payload: { message: { public_id: "message-one" } },
            })
        );

        first.stop();

        expect(second.isLeader()).toBe(true);
        second.stop();
    });

    it("mantiene cada pestaña independiente si no existe BroadcastChannel", () => {
        const storage = memoryStorage();
        const first = createMessagingTabCoordinator(26, {
            tabId: "without-channel-one",
            storage,
            window: fakeWindow(storage),
            BroadcastChannel: null,
        });
        const second = createMessagingTabCoordinator(26, {
            tabId: "without-channel-two",
            storage,
            window: fakeWindow(storage),
            BroadcastChannel: null,
        });

        first.start();
        second.start();

        expect(first.isLeader()).toBe(true);
        expect(second.isLeader()).toBe(true);
        expect(storage.getItem(first.leaseKey)).toBeNull();

        first.stop();
        second.stop();
    });

    it("nunca usa localhost como socket desde una página productiva", () => {
        expect(
            resolveMessagingRealtimeEndpoint(
                {
                    host: "127.0.0.1",
                    port: 8080,
                    scheme: "http",
                    path: "/socket",
                },
                {},
                { hostname: "cnscvaldivia.cl", protocol: "https:" }
            )
        ).toEqual({
            host: "cnscvaldivia.cl",
            port: 443,
            scheme: "https",
            path: "/socket",
        });
    });
});
