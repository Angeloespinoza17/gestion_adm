import { messagingStore } from "../stores/messagingStore";

let echo = null;
let echoPromise = null;
let connectedUserId = null;
let userChannel = null;
let activeChannel = null;
let activeConversationId = null;
let hasConnected = false;

const connectionState = (state) => {
  const normalized = {
    initialized: "connecting",
    connecting: "connecting",
    connected: "connected",
    unavailable: "reconnecting",
    disconnected: navigator.onLine ? "reconnecting" : "offline",
    failed: "unavailable",
  }[state] || state;
  messagingStore.setRealtime(normalized);
};

const authHeaders = () => {
  const token = localStorage.getItem("token");
  return token ? { Authorization: `Bearer ${token}` } : {};
};

function bindConnection(instance) {
  const connection = instance.connector?.pusher?.connection;
  if (!connection) return;

  ["initialized", "connecting", "unavailable", "disconnected", "failed"].forEach((state) => {
    connection.bind(state, () => connectionState(state));
  });
  connection.bind("connected", () => {
    connectionState("connected");
    if (hasConnected) messagingStore.recoverAfterReconnect().catch(() => {});
    hasConnected = true;
  });
  connection.bind("error", () => connectionState(navigator.onLine ? "reconnecting" : "offline"));
}

function subscribeUser(userId) {
  if (!echo || !userId || userChannel) return;
  userChannel = echo.private(`messaging.user.${userId}`)
    .listen(".messaging.conversation.changed", ({ change }) => messagingStore.applyConversationChange(change));
  userChannel.error(() => connectionState("unavailable"));
}

export async function ensureMessagingRealtime(userId) {
  if (!userId || !localStorage.getItem("token")) return null;
  if (echo && Number(connectedUserId) === Number(userId)) return echo;
  if (echoPromise) return echoPromise;

  if (echo && Number(connectedUserId) !== Number(userId)) disconnectMessagingRealtime();

  connectedUserId = Number(userId);
  messagingStore.setRealtime(navigator.onLine ? "connecting" : "offline");
  echoPromise = Promise.all([import("laravel-echo"), import("pusher-js")]).then(([echoModule, pusherModule]) => {
    const Echo = echoModule.default;
    window.Pusher = pusherModule.default;
    echo = new Echo({
      broadcaster: "reverb",
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
      wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(":", "")) === "https",
      enabledTransports: ["ws", "wss"],
      authEndpoint: "/broadcasting/auth",
      auth: { headers: authHeaders() },
    });
    window.Echo = echo;
    bindConnection(echo);
    subscribeUser(connectedUserId);
    return echo;
  }).catch((error) => {
    echoPromise = null;
    messagingStore.setRealtime(navigator.onLine ? "unavailable" : "offline");
    throw error;
  });

  return echoPromise;
}

export async function subscribeMessagingConversation(conversationId) {
  if (conversationId === activeConversationId) return;
  leaveMessagingConversation();
  if (!conversationId || !connectedUserId) return;

  const instance = await ensureMessagingRealtime(connectedUserId);
  if (!instance) return;
  activeConversationId = conversationId;
  activeChannel = instance.private(`messaging.conversation.${conversationId}`)
    .listen(".messaging.message.created", ({ message }) => messagingStore.addRealtimeMessage(message))
    .listen(".messaging.message.updated", ({ message }) => messagingStore.updateRealtimeMessage(message))
    .listen(".messaging.message.deleted", (payload) => messagingStore.removeRealtimeMessage(payload))
    .listen(".messaging.message.reaction.updated", ({ reaction }) => messagingStore.applyReactionChange(reaction))
    .listen(".messaging.message.read", ({ receipt }) => messagingStore.applyReadReceipt(receipt))
    .listen(".messaging.message.acknowledged", ({ acknowledgement }) => messagingStore.applyAcknowledgement(acknowledgement));
  activeChannel.error(() => connectionState("unavailable"));
}

export function leaveMessagingConversation() {
  if (echo && activeConversationId) echo.leave(`messaging.conversation.${activeConversationId}`);
  activeConversationId = null;
  activeChannel = null;
}

export function reconnectMessagingRealtime() {
  if (!echo) return;
  messagingStore.setRealtime(navigator.onLine ? "reconnecting" : "offline");
  echo.connect();
}

export function disconnectMessagingRealtime() {
  leaveMessagingConversation();
  if (echo && connectedUserId) echo.leave(`messaging.user.${connectedUserId}`);
  echo?.disconnect();
  echo = null;
  echoPromise = null;
  userChannel = null;
  connectedUserId = null;
  hasConnected = false;
  if (window.Echo) delete window.Echo;
  messagingStore.setRealtime("disconnected");
}
