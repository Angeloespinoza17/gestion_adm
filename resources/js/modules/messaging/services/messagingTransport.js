import { messagingStore } from "../stores/messagingStore";
import { MESSAGING_ACCESS_REVOKED_EVENT } from "./messagingAccess";
import { createMessagingTabCoordinator } from "./messagingTabCoordinator";

const consumers = new Map();

let echo = null;
let echoPromise = null;
let ownsEcho = false;
let connectedUserId = null;
let userChannel = null;
let realtimeConnected = false;
let hasConnectedBefore = false;
let fallbackCompleted = false;
let fallbackTimer = null;
let fallbackRunning = false;
let fallbackFailures = 0;
let fallbackBlocked = false;
let reconciliationTimer = null;
let realtimeRetryTimer = null;
let realtimeRetryFailures = 0;
let connectTimeoutTimer = null;
let activationRetryTimer = null;
let activationFailures = 0;
let listenersInstalled = false;
let activationPromise = null;
let reconfigureQueued = false;
let activationGeneration = 0;
let realtimeGeneration = 0;
let leaderTransportState = null;
let tabCoordinator = null;
let coordinatorUserId = null;
let stopCoordinatorMessages = null;
let stopLeadershipChanges = null;

const browserAvailable = () =>
    typeof window !== "undefined" && typeof document !== "undefined";

const online = () =>
    !browserAvailable() || typeof navigator === "undefined" || navigator.onLine;

const visible = () =>
    !browserAvailable() || document.visibilityState !== "hidden";

const numberConfig = (value, fallback) => {
    const number = Number(value);
    return Number.isFinite(number) && number > 0 ? number : fallback;
};

const pollingConfig = () => messagingStore.state.config?.polling || {};

const enabledConsumers = () =>
    [...consumers.values()].filter((consumer) => {
        try {
            return consumer.enabled();
        } catch (_) {
            return false;
        }
    });

const selectedConsumer = () => enabledConsumers().at(-1) || null;

const selectedActiveId = () => {
    const consumer = selectedConsumer();
    try {
        return consumer?.activeId?.() || null;
    } catch (_) {
        return null;
    }
};

const setState = (state) => {
    messagingStore.setPollingState(state);
    publishTransportState(state);
};

const isLeaderTab = () => !tabCoordinator || tabCoordinator.isLeader();

const httpStatus = (error) =>
    Number(error?.response?.status || error?.status || 0);

const isAuthorizationFailure = (error) =>
    [401, 403, 419].includes(httpStatus(error));

const isPermanentAvailabilityFailure = (error) =>
    isAuthorizationFailure(error) ||
    error?.response?.data?.code === "MESSAGING_DISABLED";

const leaderHasRealtime = () =>
    !isLeaderTab() && leaderTransportState === "connected";

const clearTimer = (name) => {
    const timer = {
        fallback: fallbackTimer,
        reconciliation: reconciliationTimer,
        realtimeRetry: realtimeRetryTimer,
        connectTimeout: connectTimeoutTimer,
        activationRetry: activationRetryTimer,
    }[name];
    if (timer && browserAvailable()) window.clearTimeout(timer);
    if (name === "fallback") fallbackTimer = null;
    if (name === "reconciliation") reconciliationTimer = null;
    if (name === "realtimeRetry") realtimeRetryTimer = null;
    if (name === "connectTimeout") connectTimeoutTimer = null;
    if (name === "activationRetry") activationRetryTimer = null;
};

const jittered = (milliseconds) => {
    const ratio = Math.min(
        0.75,
        Math.max(0, Number(pollingConfig().jitter_ratio ?? 0.2))
    );
    const factor = 1 + (Math.random() * 2 - 1) * ratio;
    return Math.max(1000, Math.round(milliseconds * factor));
};

const fallbackBaseInterval = () => {
    const active = Boolean(selectedActiveId());
    return Math.max(
        5000,
        numberConfig(
            active
                ? pollingConfig().active_interval_ms
                : pollingConfig().interval_ms,
            active ? 15000 : 60000
        )
    );
};

const fallbackDelay = () => {
    const maximum = Math.max(
        fallbackBaseInterval(),
        numberConfig(pollingConfig().max_interval_ms, 120000)
    );
    const exponential = Math.min(
        maximum,
        fallbackBaseInterval() * 2 ** Math.max(0, fallbackFailures - 1)
    );
    return jittered(exponential);
};

const realtimeRetryDelay = () =>
    jittered(Math.min(120000, 5000 * 2 ** realtimeRetryFailures));

function clearNetworkTimers() {
    clearTimer("fallback");
    clearTimer("reconciliation");
    clearTimer("realtimeRetry");
    clearTimer("connectTimeout");
    clearTimer("activationRetry");
}

async function runConsumerConversationRefresh() {
    const consumer = selectedConsumer();
    if (typeof consumer?.refreshConversations !== "function") return null;
    return consumer.refreshConversations();
}

async function runFallbackCycle() {
    clearTimer("fallback");
    if (
        fallbackRunning ||
        realtimeConnected ||
        leaderHasRealtime() ||
        fallbackBlocked ||
        !enabledConsumers().length ||
        pollingConfig().enabled === false
    )
        return;
    if (!isLeaderTab() && !selectedActiveId()) {
        setState("follower");
        return;
    }
    if (!online()) {
        setState("offline");
        return;
    }
    if (!visible()) return;

    fallbackRunning = true;
    setState("syncing");
    try {
        const leader = isLeaderTab();
        const tasks = [];
        if (leader) {
            tasks.push(messagingStore.loadSummary());
            const conversationRefresh = runConsumerConversationRefresh();
            if (conversationRefresh) tasks.push(conversationRefresh);
        }
        if (selectedActiveId())
            tasks.push(messagingStore.pollActiveConversation());
        if (!tasks.length) return;
        await Promise.all(tasks);
        if (leader)
            tabCoordinator?.publish({
                type: "summary-snapshot",
                summary: JSON.parse(
                    JSON.stringify(messagingStore.state.summary || {})
                ),
            });
        fallbackFailures = 0;
        fallbackCompleted = true;
        setState(leader ? "polling" : "follower");
    } catch (error) {
        if (isPermanentAvailabilityFailure(error)) fallbackBlocked = true;
        fallbackFailures += 1;
        setState(online() ? "unavailable" : "offline");
    } finally {
        fallbackRunning = false;
        scheduleFallback();
    }
}

function scheduleFallback(immediate = false) {
    clearTimer("fallback");
    if (
        !browserAvailable() ||
        realtimeConnected ||
        leaderHasRealtime() ||
        fallbackBlocked ||
        !enabledConsumers().length ||
        pollingConfig().enabled === false ||
        !online() ||
        !visible() ||
        (!isLeaderTab() && !selectedActiveId())
    )
        return;
    fallbackTimer = window.setTimeout(
        runFallbackCycle,
        immediate ? 0 : fallbackDelay()
    );
}

function scheduleReconciliation() {
    clearTimer("reconciliation");
    if (
        !browserAvailable() ||
        !realtimeConnected ||
        !isLeaderTab() ||
        !enabledConsumers().length ||
        !online() ||
        !visible()
    )
        return;
    const interval = Math.max(
        60000,
        numberConfig(pollingConfig().reconciliation_interval_ms, 300000)
    );
    reconciliationTimer = window.setTimeout(async () => {
        try {
            await messagingStore.recoverAfterReconnect();
        } catch (_) {
            // El socket sigue siendo la fuente primaria; se reintentará luego.
        } finally {
            scheduleReconciliation();
        }
    }, jittered(interval));
}

function scheduleRealtimeRetry() {
    clearTimer("realtimeRetry");
    if (
        !browserAvailable() ||
        realtimeConnected ||
        !isLeaderTab() ||
        !messagingStore.state.config?.realtime?.enabled ||
        !enabledConsumers().length ||
        !online() ||
        !visible()
    )
        return;
    realtimeRetryTimer = window.setTimeout(() => {
        realtimeRetryFailures = Math.min(realtimeRetryFailures + 1, 5);
        if (echo?.connect) echo.connect();
        else ensureRealtime().catch(() => {});
        armConnectTimeout();
    }, realtimeRetryDelay());
}

function enterFallback() {
    realtimeConnected = false;
    clearTimer("reconciliation");
    setState(online() ? "reconnecting" : "offline");
    scheduleFallback(true);
    scheduleRealtimeRetry();
}

function scheduleActivationRetry() {
    clearTimer("activationRetry");
    if (
        !browserAvailable() ||
        !enabledConsumers().length ||
        !online() ||
        !visible()
    )
        return;
    const base = Math.max(
        15000,
        numberConfig(pollingConfig().interval_ms, 60000)
    );
    const maximum = Math.max(
        base,
        numberConfig(pollingConfig().max_interval_ms, 120000)
    );
    const delay = jittered(
        Math.min(maximum, base * 2 ** Math.max(0, activationFailures - 1))
    );
    activationRetryTimer = window.setTimeout(reconfigure, delay);
}

function handleActivationError(error) {
    if (!enabledConsumers().length) return;
    clearTimer("fallback");
    clearTimer("reconciliation");
    setState(online() ? "unavailable" : "offline");
    if (isPermanentAvailabilityFailure(error)) {
        fallbackBlocked = true;
        clearTimer("activationRetry");
        return;
    }
    activationFailures = Math.min(activationFailures + 1, 6);
    scheduleActivationRetry();
}

function armConnectTimeout() {
    clearTimer("connectTimeout");
    if (!browserAvailable() || realtimeConnected) return;
    connectTimeoutTimer = window.setTimeout(() => {
        if (!realtimeConnected) enterFallback();
    }, numberConfig(
        messagingStore.state.config?.realtime?.connection_grace_ms,
        10000
    ));
}

function applySharedEvent(kind, payload) {
    const handlers = {
        conversation: ({ change }) =>
            messagingStore.applyConversationChange(change, isLeaderTab()),
        messageCreated: ({ message }) =>
            messagingStore.applyRealtimeMessageReference(
                "created",
                message,
                { refreshGlobal: isLeaderTab() }
            ),
        sentMessage: ({ message }) =>
            messagingStore.applySentMessageFromTab(message),
        messageUpdated: ({ message }) =>
            messagingStore.applyRealtimeMessageReference(
                "updated",
                message,
                { refreshGlobal: isLeaderTab() }
            ),
        messageDeleted: (event) =>
            messagingStore.removeRealtimeMessage(event),
        reactionUpdated: ({ reaction }) =>
            messagingStore.applyReactionChange(reaction),
        messageRead: ({ receipt }) =>
            messagingStore.applyReadReceipt(receipt),
        messageAcknowledged: ({ acknowledgement }) =>
            messagingStore.applyAcknowledgement(acknowledgement),
    };
    return handlers[kind]?.(payload);
}

function publishTransportState(state, targetTabId = null) {
    if (isLeaderTab())
        tabCoordinator?.publish({
            type: "transport-state",
            state,
            targetTabId,
        });
}

function handleConversationEvent(kind) {
    return (payload = {}) => {
        try {
            const result = applySharedEvent(kind, payload);
            if (isLeaderTab())
                tabCoordinator?.publish({
                    type: "messaging-event",
                    kind,
                    payload,
                });
            if (kind === "conversation")
                Promise.resolve(result)
                    .then(() => {
                        if (isLeaderTab())
                            tabCoordinator?.publish({
                                type: "summary-snapshot",
                                summary: JSON.parse(
                                    JSON.stringify(
                                        messagingStore.state.summary || {}
                                    )
                                ),
                            });
                    })
                    .catch(() => {});
            else if (result?.catch) result.catch(() => {});
        } catch (_) {
            // La reconciliación periódica repara cualquier evento incompleto.
        }
    };
}

function subscribeUserChannel() {
    if (!echo || !connectedUserId || userChannel) return;
    userChannel = echo
        .private(`messaging.user.${connectedUserId}`)
        .listen(
            ".messaging.conversation.changed",
            handleConversationEvent("conversation")
        );
    userChannel.error?.(() => enterFallback());
}

function handleRealtimeConnected() {
    const shouldRecover = hasConnectedBefore || fallbackCompleted;
    realtimeConnected = true;
    hasConnectedBefore = true;
    realtimeRetryFailures = 0;
    fallbackFailures = 0;
    clearTimer("fallback");
    clearTimer("realtimeRetry");
    clearTimer("connectTimeout");
    setState("connected");
    subscribeUserChannel();
    scheduleReconciliation();
    if (shouldRecover)
        messagingStore.recoverAfterReconnect().catch(() => enterFallback());
}

function bindRealtimeConnection(instance) {
    const connection = instance.connector?.pusher?.connection;
    if (!connection) throw new Error("El cliente Echo no expone su conexión.");

    const whileCurrent = (callback) => (...args) => {
        if (echo === instance && enabledConsumers().length) callback(...args);
    };
    connection.bind("connected", whileCurrent(handleRealtimeConnected));
    connection.bind("connecting", whileCurrent(() => setState("connecting")));
    connection.bind("unavailable", whileCurrent(enterFallback));
    connection.bind("disconnected", whileCurrent(enterFallback));
    connection.bind("failed", whileCurrent(enterFallback));
    connection.bind("error", whileCurrent(enterFallback));
    if (connection.state === "connected") handleRealtimeConnected();
    else armConnectTimeout();
}

export function resolveMessagingRealtimeEndpoint(
    runtime = {},
    environment = {},
    location = {}
) {
    const pageHost = String(location.hostname || "localhost").toLowerCase();
    const pageScheme = String(location.protocol || "https:")
        .replace(":", "")
        .toLowerCase();
    const isLoopback = (value) => {
        const host = String(value || "")
            .trim()
            .toLowerCase()
            .replace(/^\[|\]$/g, "");
        return (
            host === "localhost" ||
            host.endsWith(".localhost") ||
            host === "::1" ||
            /^127(?:\.\d{1,3}){3}$/.test(host)
        );
    };
    let host = String(runtime.host || environment.host || pageHost).toLowerCase();
    let scheme = String(
        runtime.scheme || environment.scheme || pageScheme
    ).toLowerCase();
    let port = numberConfig(
        runtime.port || environment.port,
        scheme === "https" ? 443 : 80
    );

    if (!isLoopback(pageHost) && isLoopback(host)) {
        host = pageHost;
        scheme = pageScheme;
        port = scheme === "https" ? 443 : 80;
    }

    return { host, scheme, port, path: runtime.path || environment.path };
}

function echoOptions() {
    const runtime = messagingStore.state.config?.realtime || {};
    const endpoint = resolveMessagingRealtimeEndpoint(
        runtime,
        {
            host: import.meta.env.VITE_REVERB_HOST,
            port: import.meta.env.VITE_REVERB_PORT,
            scheme: import.meta.env.VITE_REVERB_SCHEME,
        },
        browserAvailable() ? window.location : {}
    );
    return {
        broadcaster: "reverb",
        key: runtime.key || import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: endpoint.host,
        wsPort: endpoint.port,
        wssPort: endpoint.port,
        wsPath: endpoint.path || undefined,
        forceTLS: endpoint.scheme === "https",
        enabledTransports: ["ws", "wss"],
        authEndpoint: "/broadcasting/auth",
        auth: {
            headers: localStorage.getItem("token")
                ? { Authorization: `Bearer ${localStorage.getItem("token")}` }
                : {},
        },
    };
}

async function createEcho() {
    if (typeof window.__createMessagingEcho === "function") {
        return window.__createMessagingEcho(echoOptions());
    }

    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
        import("laravel-echo"),
        import("pusher-js"),
    ]);
    window.Pusher = Pusher;
    return new Echo(echoOptions());
}

async function ensureRealtime() {
    if (
        !messagingStore.state.config?.realtime?.enabled ||
        !isLeaderTab() ||
        !enabledConsumers().length ||
        !online() ||
        !visible()
    )
        return null;
    const userId = Number(messagingStore.state.config?.user?.id || 0);
    if (!userId) return null;
    if (echo && connectedUserId === userId) {
        if (!realtimeConnected) {
            setState("connecting");
            echo.connect?.();
            armConnectTimeout();
        }
        return echo;
    }
    if (echoPromise) return echoPromise;
    if (echo && connectedUserId !== userId) disconnectRealtime();

    const generation = realtimeGeneration;
    connectedUserId = userId;
    setState("connecting");
    const pending = createEcho()
        .then((instance) => {
            if (
                generation !== realtimeGeneration ||
                !isLeaderTab() ||
                !enabledConsumers().length
            ) {
                instance?.disconnect?.();
                return null;
            }
            echo = instance;
            ownsEcho = true;
            window.Echo = instance;
            bindRealtimeConnection(instance);
            subscribeUserChannel();
            return instance;
        })
        .catch((error) => {
            if (generation === realtimeGeneration) {
                echo = null;
                enterFallback();
            }
            throw error;
        })
        .finally(() => {
            if (echoPromise === pending) echoPromise = null;
        });
    echoPromise = pending;
    return echoPromise;
}

function disconnectRealtime() {
    realtimeGeneration += 1;
    if (echo && connectedUserId)
        echo.leave(`messaging.user.${connectedUserId}`);
    if (ownsEcho) echo?.disconnect?.();
    if (browserAvailable() && window.Echo === echo) delete window.Echo;
    echo = null;
    echoPromise = null;
    ownsEcho = false;
    connectedUserId = null;
    userChannel = null;
    realtimeConnected = false;
    hasConnectedBefore = false;
}

function destroyTabCoordinator() {
    if (tabCoordinator && !isLeaderTab())
        tabCoordinator.publish({
            type: "tab-state",
            enabled: false,
        });
    stopCoordinatorMessages?.();
    stopLeadershipChanges?.();
    stopCoordinatorMessages = null;
    stopLeadershipChanges = null;
    tabCoordinator?.stop();
    tabCoordinator = null;
    coordinatorUserId = null;
    leaderTransportState = null;
}

function publishConsumerState(requestSnapshot = false) {
    if (!tabCoordinator || isLeaderTab()) return;
    const enabled = Boolean(enabledConsumers().length && visible());
    tabCoordinator.publish({
        type: "tab-state",
        enabled,
        requestSnapshot,
    });
}

function handleCoordinatorMessage(message) {
    if (
        message.targetTabId &&
        message.targetTabId !== tabCoordinator?.tabId
    )
        return;
    if (isLeaderTab()) {
        if (message.type === "local-action") {
            const result = applySharedEvent(
                message.kind,
                message.payload || {}
            );
            if (result?.catch) result.catch(() => {});
            tabCoordinator?.publish({
                type: "messaging-event",
                kind: message.kind,
                payload: message.payload || {},
            });
        }
        if (message.type === "tab-state" && message.senderTabId) {
            if (message.requestSnapshot) {
                publishTransportState(
                    realtimeConnected ? "connected" : "polling",
                    message.senderTabId
                );
                tabCoordinator?.publish({
                    type: "summary-snapshot",
                    targetTabId: message.senderTabId,
                    summary: JSON.parse(
                        JSON.stringify(messagingStore.state.summary || {})
                    ),
                });
            }
        }
        return;
    }
    if (message.type === "leader-heartbeat")
        publishConsumerState(leaderTransportState === null);
    if (message.type === "summary-snapshot")
        messagingStore.applyTransportSummary(message.summary);
    if (message.type === "messaging-event") {
        try {
            const result = applySharedEvent(message.kind, message.payload || {});
            if (result?.catch) result.catch(() => {});
        } catch (_) {
            // La pestaña seguidora se reconciliará si asume el liderazgo.
        }
    }
    if (message.type === "transport-state") {
        leaderTransportState = message.state;
        if (leaderHasRealtime()) {
            clearTimer("fallback");
            messagingStore.setPollingState("follower");
        } else {
            messagingStore.setPollingState(
                message.state === "offline" ? "offline" : "polling"
            );
            scheduleFallback(true);
        }
    }
}

function handleLocalTransportEvent(event) {
    const { kind, payload } = event.detail || {};
    if (!kind) return;
    if (isLeaderTab())
        tabCoordinator?.publish({ type: "messaging-event", kind, payload });
    else tabCoordinator?.publish({ type: "local-action", kind, payload });
}

function handleLeadershipChange(leader) {
    clearNetworkTimers();
    leaderTransportState = null;
    if (!leader) {
        disconnectRealtime();
        messagingStore.setPollingState("follower");
        scheduleFallback();
        publishConsumerState(true);
        return;
    }
    fallbackFailures = 0;
    fallbackCompleted = false;
    reconfigure();
}

function ensureTabCoordinator(userId) {
    if (!browserAvailable() || !userId) return;
    if (tabCoordinator && coordinatorUserId === userId) return;
    destroyTabCoordinator();
    coordinatorUserId = userId;
    tabCoordinator = createMessagingTabCoordinator(userId);
    stopCoordinatorMessages = tabCoordinator.subscribe(
        handleCoordinatorMessage
    );
    stopLeadershipChanges = tabCoordinator.onLeadershipChange(
        handleLeadershipChange
    );
    tabCoordinator.start();
    publishConsumerState(true);
}

async function activate() {
    if (!enabledConsumers().length) {
        clearTimer("fallback");
        clearTimer("reconciliation");
        return;
    }
    if (!online()) {
        clearNetworkTimers();
        setState("offline");
        return;
    }
    if (!visible()) {
        clearTimer("fallback");
        clearTimer("reconciliation");
        return;
    }

    await messagingStore.loadConfig();
    if (!enabledConsumers().length) return;
    activationFailures = 0;
    fallbackBlocked = false;
    clearTimer("activationRetry");
    if (messagingStore.state.config?.enabled === false) {
        clearNetworkTimers();
        disconnectRealtime();
        destroyTabCoordinator();
        setState("unavailable");
        return;
    }

    ensureTabCoordinator(
        Number(messagingStore.state.config?.user?.id || 0)
    );
    if (!isLeaderTab()) {
        disconnectRealtime();
        messagingStore.setPollingState("follower");
        publishConsumerState();
        scheduleFallback();
        return;
    }

    if (messagingStore.state.config?.realtime?.enabled) {
        const instance = await ensureRealtime().catch(() => null);
        if (
            !instance &&
            !realtimeConnected &&
            isLeaderTab() &&
            enabledConsumers().length
        )
            enterFallback();
        return;
    }

    disconnectRealtime();
    setState("polling");
    scheduleFallback();
}

function reconfigure() {
    if (reconfigureQueued) return;
    reconfigureQueued = true;
    Promise.resolve().then(() => {
        reconfigureQueued = false;
        if (!realtimeConnected) {
            clearTimer("fallback");
            scheduleFallback();
        }
        publishConsumerState();
        if (!activationPromise) {
            const generation = activationGeneration;
            const pending = activate()
                .catch((error) => {
                    if (generation === activationGeneration)
                        handleActivationError(error);
                })
                .finally(() => {
                    if (activationPromise === pending)
                        activationPromise = null;
                });
            activationPromise = pending;
        }
    });
}

function handleOnline() {
    setState("reconnecting");
    if (echo?.connect) echo.connect();
    activate()
        .then(() => {
            if (!realtimeConnected && !leaderHasRealtime())
                scheduleFallback(true);
            else messagingStore.recoverAfterReconnect().catch(() => {});
        })
        .catch(handleActivationError);
}

function handleOffline() {
    clearNetworkTimers();
    setState("offline");
    publishConsumerState();
}

function handleAccessRevoked() {
    messagingStore.resetForAccessRevoked();
    consumers.clear();
    activationGeneration += 1;
    clearNetworkTimers();
    disconnectRealtime();
    destroyTabCoordinator();
    fallbackRunning = false;
    fallbackBlocked = true;
    activationPromise = null;
    leaderTransportState = null;
    setState("unavailable");
}

function handleVisibilityChange() {
    if (!visible()) {
        clearTimer("fallback");
        clearTimer("reconciliation");
        publishConsumerState();
        return;
    }
    if (!online()) {
        setState("offline");
        return;
    }
    if (realtimeConnected) {
        messagingStore.recoverAfterReconnect().catch(() => enterFallback());
        scheduleReconciliation();
    } else {
        publishConsumerState();
        activate()
            .finally(() => {
                if (!realtimeConnected && !leaderHasRealtime())
                    scheduleFallback(true);
            })
            .catch(handleActivationError);
    }
}

function installListeners() {
    if (!browserAvailable() || listenersInstalled) return;
    listenersInstalled = true;
    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);
    document.addEventListener("visibilitychange", handleVisibilityChange);
    window.addEventListener("messaging:local-event", handleLocalTransportEvent);
    window.addEventListener(
        MESSAGING_ACCESS_REVOKED_EVENT,
        handleAccessRevoked
    );
}

function removeListeners() {
    if (!browserAvailable() || !listenersInstalled) return;
    listenersInstalled = false;
    window.removeEventListener("online", handleOnline);
    window.removeEventListener("offline", handleOffline);
    document.removeEventListener("visibilitychange", handleVisibilityChange);
    window.removeEventListener(
        "messaging:local-event",
        handleLocalTransportEvent
    );
    window.removeEventListener(
        MESSAGING_ACCESS_REVOKED_EVENT,
        handleAccessRevoked
    );
}

function shutdown() {
    activationGeneration += 1;
    clearNetworkTimers();
    disconnectRealtime();
    destroyTabCoordinator();
    removeListeners();
    fallbackRunning = false;
    fallbackFailures = 0;
    fallbackCompleted = false;
    fallbackBlocked = false;
    realtimeRetryFailures = 0;
    activationFailures = 0;
    activationPromise = null;
    reconfigureQueued = false;
    leaderTransportState = null;
    setState("disconnected");
}

export const messagingTransport = {
    register(options) {
        const token = Symbol("messaging-transport-consumer");
        consumers.set(token, {
            enabled: options.enabled || (() => true),
            activeId: options.activeId || (() => null),
            refreshConversations: options.refreshConversations,
        });
        installListeners();
        reconfigure();

        return {
            update: reconfigure,
            remove() {
                consumers.delete(token);
                if (!consumers.size) shutdown();
                else reconfigure();
            },
        };
    },

    async refresh() {
        if (realtimeConnected) return messagingStore.recoverAfterReconnect();
        return runFallbackCycle();
    },

    shutdown,
};
