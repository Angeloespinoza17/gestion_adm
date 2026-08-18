import { onBeforeUnmount, watch } from "vue";
import { messagingStore } from "../stores/messagingStore";
import {
  disconnectMessagingRealtime,
  ensureMessagingRealtime,
  reconnectMessagingRealtime,
  subscribeMessagingConversation,
} from "../services/messagingRealtime";

export function useMessagingRealtime(activeId) {
  const stopConnectionWatch = watch(
    () => [messagingStore.state.config?.realtime?.enabled, messagingStore.state.config?.user?.id],
    ([enabled, userId]) => {
      if (enabled && userId) {
        ensureMessagingRealtime(userId).catch(() => {});
        return;
      }
      disconnectMessagingRealtime();
    },
    { immediate: true }
  );

  const stopConversationWatch = watch(activeId, (id) => {
    subscribeMessagingConversation(id || null).catch(() => messagingStore.setRealtime("unavailable"));
  }, { immediate: true });

  const handleOnline = () => reconnectMessagingRealtime();
  const handleOffline = () => messagingStore.setRealtime("offline");
  window.addEventListener("online", handleOnline);
  window.addEventListener("offline", handleOffline);

  const stop = () => {
    stopConnectionWatch();
    stopConversationWatch();
    window.removeEventListener("online", handleOnline);
    window.removeEventListener("offline", handleOffline);
  };

  onBeforeUnmount(stop);

  return { stop, reconnect: handleOnline };
}
