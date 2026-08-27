import { onBeforeUnmount, watch } from "vue";
import { messagingStore } from "../stores/messagingStore";

export function useMessagingHttpPolling(activeId, options = {}) {
    const enabled = options.enabled;
    const refreshConversations = options.refreshConversations;
    let timer = null;
    let syncing = false;
    let stopped = false;

    const isEnabled = () => enabled?.value !== false;
    const interval = () => {
        const configured = Number(
            activeId.value
                ? messagingStore.state.config?.polling?.active_interval_ms
                : messagingStore.state.config?.polling?.interval_ms
        );
        return Math.max(
            5000,
            configured || Number(options.intervalMs) || 15000
        );
    };

    function clearTimer() {
        if (timer) window.clearTimeout(timer);
        timer = null;
    }

    function schedule() {
        clearTimer();
        if (stopped || !isEnabled()) return;
        timer = window.setTimeout(refresh, interval());
    }

    async function refresh() {
        clearTimer();
        if (stopped || !isEnabled() || syncing) return;
        if (!navigator.onLine) {
            messagingStore.setPollingState("offline");
            schedule();
            return;
        }
        if (document.visibilityState === "hidden") {
            schedule();
            return;
        }

        syncing = true;
        messagingStore.setPollingState("syncing");
        try {
            const tasks = [messagingStore.loadSummary()];
            if (refreshConversations) tasks.push(refreshConversations());
            if (activeId.value)
                tasks.push(messagingStore.pollActiveConversation());
            await Promise.all(tasks);
            messagingStore.setPollingState("polling");
        } catch (error) {
            messagingStore.setPollingState(
                navigator.onLine ? "unavailable" : "offline"
            );
        } finally {
            syncing = false;
            schedule();
        }
    }

    const stopWatch = watch(
        () => [isEnabled(), activeId.value],
        ([active]) => {
            clearTimer();
            if (active) refresh();
            else messagingStore.setPollingState("disconnected");
        },
        { immediate: true }
    );

    const handleOnline = () => refresh();
    const handleOffline = () => messagingStore.setPollingState("offline");
    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);

    const stop = () => {
        stopped = true;
        clearTimer();
        stopWatch();
        window.removeEventListener("online", handleOnline);
        window.removeEventListener("offline", handleOffline);
    };

    onBeforeUnmount(stop);

    return { refresh, stop };
}
