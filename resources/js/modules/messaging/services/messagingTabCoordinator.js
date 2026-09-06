const parseLease = (value) => {
    try {
        const lease = JSON.parse(value || "null");
        return lease?.owner && Number(lease.expiresAt) > 0 ? lease : null;
    } catch (_) {
        return null;
    }
};

const createTabId = () =>
    globalThis.crypto?.randomUUID?.() ||
    `tab-${Date.now()}-${Math.random().toString(36).slice(2)}`;

export function createMessagingTabCoordinator(userId, options = {}) {
    const browserWindow = options.window || globalThis.window;
    const storage = options.storage || browserWindow?.localStorage;
    const now = options.now || (() => Date.now());
    const leaseMs = Number(options.leaseMs || 12000);
    const heartbeatMs = Number(options.heartbeatMs || 4000);
    const tabId = options.tabId || createTabId();
    const leaseKey = `messaging:transport-leader:${userId}:v1`;
    const channelName = `messaging:transport:${userId}:v1`;
    const Channel = Object.prototype.hasOwnProperty.call(
        options,
        "BroadcastChannel"
    )
        ? options.BroadcastChannel
        : globalThis.BroadcastChannel;
    const subscribers = new Set();
    const leadershipSubscribers = new Set();

    let channel = null;
    let heartbeatTimer = null;
    let started = false;
    let leader = false;

    const notifyLeadership = () =>
        leadershipSubscribers.forEach((subscriber) => subscriber(leader));

    const setLeader = (value) => {
        const changed = leader !== value;
        leader = value;
        if (changed) notifyLeadership();
    };

    const readLease = () => parseLease(storage?.getItem(leaseKey));

    const writeLease = () => {
        storage?.setItem(
            leaseKey,
            JSON.stringify({ owner: tabId, expiresAt: now() + leaseMs })
        );
    };

    const publish = (message) => {
        channel?.postMessage({ ...message, senderTabId: tabId });
    };

    const acquire = () => {
        try {
            const lease = readLease();
            if (lease && lease.owner !== tabId && lease.expiresAt > now()) {
                setLeader(false);
                return false;
            }
            writeLease();
            const confirmed = readLease()?.owner === tabId;
            setLeader(confirmed);
            if (confirmed) publish({ type: "leader-heartbeat" });
            return confirmed;
        } catch (_) {
            // Sin storage compartido, esta pestaña opera como instancia única.
            setLeader(true);
            return true;
        }
    };

    const tick = () => {
        if (leader) {
            try {
                const lease = readLease();
                if (lease && lease.owner !== tabId && lease.expiresAt > now()) {
                    setLeader(false);
                    return;
                }
                writeLease();
                publish({ type: "leader-heartbeat" });
            } catch (_) {
                setLeader(true);
            }
            return;
        }
        try {
            const lease = readLease();
            if (!lease || lease.expiresAt <= now()) acquire();
        } catch (_) {
            // Si storage deja de estar disponible, evita detener el transporte.
            setLeader(true);
        }
    };

    const handleStorage = (event) => {
        if (event.key !== leaseKey) return;
        const lease = parseLease(event.newValue);
        if (lease?.owner !== tabId && lease?.expiresAt > now()) setLeader(false);
        else if (!lease || lease.expiresAt <= now()) acquire();
    };

    const handleMessage = (event) => {
        const message = event.data || {};
        if (message.senderTabId === tabId) return;
        if (message.type === "leader-released") acquire();
        subscribers.forEach((subscriber) => subscriber(message));
    };

    const release = () => {
        if (!leader) return;
        try {
            if (readLease()?.owner === tabId) storage?.removeItem(leaseKey);
        } catch (_) {
            // La pestaña se está cerrando; el lease expirará por sí solo.
        }
        publish({ type: "leader-released" });
        setLeader(false);
    };

    return {
        tabId,
        leaseKey,

        start() {
            if (started) return;
            started = true;
            if (!Channel) {
                // Sin un bus inter-pestaña no es seguro elegir líder: cada
                // pestaña debe conservar su propio transporte para no quedar
                // obsoleta sin forma de recibir eventos o snapshots.
                setLeader(true);
                return;
            }
            channel = new Channel(channelName);
            channel.addEventListener?.("message", handleMessage);
            if (!("addEventListener" in channel))
                channel.onmessage = handleMessage;
            browserWindow?.addEventListener?.("storage", handleStorage);
            browserWindow?.addEventListener?.("pagehide", release);
            acquire();
            publish({ type: "tab-joined" });
            heartbeatTimer = browserWindow?.setInterval?.(tick, heartbeatMs);
        },

        stop() {
            if (!started) return;
            release();
            if (heartbeatTimer)
                browserWindow?.clearInterval?.(heartbeatTimer);
            heartbeatTimer = null;
            browserWindow?.removeEventListener?.("storage", handleStorage);
            browserWindow?.removeEventListener?.("pagehide", release);
            channel?.removeEventListener?.("message", handleMessage);
            channel?.close?.();
            channel = null;
            started = false;
        },

        isLeader: () => leader,
        acquire,
        publish,
        subscribe(callback) {
            subscribers.add(callback);
            return () => subscribers.delete(callback);
        },
        onLeadershipChange(callback) {
            leadershipSubscribers.add(callback);
            return () => leadershipSubscribers.delete(callback);
        },
    };
}
