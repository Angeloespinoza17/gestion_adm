import { onBeforeUnmount, unref, watch } from "vue";
import { messagingTransport } from "../services/messagingTransport";

export function useMessagingTransport(activeId, options = {}) {
    const enabled = options.enabled;
    const registration = messagingTransport.register({
        enabled: () => (enabled === undefined ? true : Boolean(unref(enabled))),
        activeId: () => unref(activeId) || null,
        refreshConversations: options.refreshConversations,
    });

    const stopWatch = watch(
        () => [
            enabled === undefined ? true : Boolean(unref(enabled)),
            unref(activeId) || null,
        ],
        () => registration.update(),
        { immediate: true }
    );

    const stop = () => {
        stopWatch();
        registration.remove();
    };

    onBeforeUnmount(stop);

    return { refresh: () => messagingTransport.refresh(), stop };
}
