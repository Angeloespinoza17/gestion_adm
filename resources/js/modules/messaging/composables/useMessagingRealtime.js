import { onBeforeUnmount, watch } from "vue";
import { messagingStore } from "../stores/messagingStore";

export function useMessagingRealtime(activeId) {
  let timer = null;
  let channel = null;

  const syncNow = () => {
    messagingStore.sync().catch(() => messagingStore.setRealtime("polling"));
  };

  const scheduleNextSync = () => {
    if (timer) window.clearTimeout(timer);
    const configuredDelay = Number(messagingStore.state.config?.realtime?.poll_interval_ms || 5000);
    const delay = Math.max(configuredDelay, 3000);
    timer = window.setTimeout(async () => {
      syncNow();
      scheduleNextSync();
    }, delay);
  };

  const unsubscribeChannel = () => {
    if (channel && window.Echo) window.Echo.leave(channel);
    channel = null;
  };

  const subscribe = (id) => {
    unsubscribeChannel();

    if (id && window.Echo && messagingStore.state.config?.realtime?.enabled) {
      try {
        channel = `messaging.conversation.${id}`;
        window.Echo.private(channel)
          .listen(".messaging.message.created", ({ message }) => messagingStore.addRealtimeMessage(message));
        messagingStore.setRealtime("websocket");
      } catch (_) {
        messagingStore.setRealtime("polling");
      }
    } else {
      messagingStore.setRealtime("polling");
    }

    syncNow();
  };

  const stopConversationWatch = watch(activeId, subscribe, { immediate: true });
  const stopIntervalWatch = watch(
    () => messagingStore.state.config?.realtime?.poll_interval_ms,
    scheduleNextSync,
    { immediate: true }
  );

  document.addEventListener("visibilitychange", syncNow);
  window.addEventListener("focus", syncNow);
  window.addEventListener("online", syncNow);

  const stop = () => {
    if (timer) window.clearTimeout(timer);
    timer = null;
    stopConversationWatch();
    stopIntervalWatch();
    unsubscribeChannel();
    document.removeEventListener("visibilitychange", syncNow);
    window.removeEventListener("focus", syncNow);
    window.removeEventListener("online", syncNow);
  };

  onBeforeUnmount(stop);

  return { stop, syncNow };
}
