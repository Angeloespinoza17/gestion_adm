import axios from "axios";
import { reactive, readonly } from "vue";

export const STAFF_ACCESS_MARKER = "__staff__";
export const MESSAGING_ACCESS_REVOKED_EVENT = "messaging:access-revoked";

const state = reactive({
    resolved: false,
    allowed: false,
});

let permissionRequest = null;
let permissionRequestToken = null;
let permissions = [];
let permissionsToken = null;
let permissionsLoadedAt = 0;
let generation = 0;

const normalizedPermissions = (items) =>
    Array.isArray(items)
        ? Array.from(
              new Set(
                  items.filter(
                      (permission) => typeof permission === "string"
                  )
              )
          )
        : [];

export const hasMessagingStaffAccess = (items) =>
    normalizedPermissions(items).includes(STAFF_ACCESS_MARKER);

export const filterMessagingStaffUsers = (items) =>
    (Array.isArray(items) ? items : []).filter(
        (user) => user?.is_staff === true
    );

export const isMessagingAccessRevokedError = (
    error,
    { accessProbe = false } = {}
) => {
    const status = Number(error?.response?.status || 0);
    const code = error?.response?.data?.code;

    return (
        status === 403 &&
        (accessProbe ||
            ["MESSAGING_STAFF_ONLY", "MESSAGING_USER_INACTIVE"].includes(code))
    );
};

export const clearMessagingSession = ({ code = null, status = 0 } = {}) => {
    resetMessagingAccess();
    localStorage.removeItem("permissions");
    if (typeof window !== "undefined") {
        window.dispatchEvent(
            new CustomEvent(MESSAGING_ACCESS_REVOKED_EVENT, {
                detail: { code, status },
            })
        );
    }
};

export const revokeMessagingAccess = (error = null) =>
    clearMessagingSession({
        code: error?.response?.data?.code || null,
        status: Number(error?.response?.status || 403),
    });

const authorizationHeaders = (token) => {
    const authorization = `Bearer ${token}`;

    return {
        Authorization: authorization,
        "X-Authorization": authorization,
        "X-Api-Token": token,
    };
};

const applyPermissions = (items, token) => {
    permissions = normalizedPermissions(items);
    permissionsToken = token;
    permissionsLoadedAt = Date.now();
    state.resolved = true;
    state.allowed = hasMessagingStaffAccess(permissions);
    localStorage.setItem("permissions", JSON.stringify(permissions));

    return permissions;
};

/**
 * Shares the existing /api/me/permissions request between App, router and
 * navigation. The short freshness window avoids duplicate calls during the
 * initial render without turning the client into an authorization cache.
 */
export const loadMessagingAccess = async ({
    token = localStorage.getItem("token"),
    maxAgeMs = 5000,
} = {}) => {
    if (!token) {
        resetMessagingAccess();
        return [];
    }

    if (
        permissionsToken === token &&
        permissionsLoadedAt > 0 &&
        Date.now() - permissionsLoadedAt <= maxAgeMs
    ) {
        return permissions;
    }

    if (permissionRequest && permissionRequestToken === token) {
        return permissionRequest;
    }

    const requestGeneration = generation;
    permissionRequestToken = token;
    permissionRequest = axios
        .get("/api/me/permissions", {
            headers: authorizationHeaders(token),
        })
        .then((response) => {
            if (
                requestGeneration !== generation ||
                localStorage.getItem("token") !== token
            ) {
                return [];
            }

            return applyPermissions(response.data.data || [], token);
        })
        .catch((error) => {
            if (
                requestGeneration === generation &&
                localStorage.getItem("token") === token
            ) {
                permissions = [];
                permissionsToken = token;
                permissionsLoadedAt = 0;
                state.resolved = true;
                state.allowed = false;
            }
            throw error;
        })
        .finally(() => {
            if (permissionRequestToken === token) {
                permissionRequest = null;
                permissionRequestToken = null;
            }
        });

    return permissionRequest;
};

export const resetMessagingAccess = () => {
    generation += 1;
    permissionRequest = null;
    permissionRequestToken = null;
    permissions = [];
    permissionsToken = null;
    permissionsLoadedAt = 0;
    state.resolved = false;
    state.allowed = false;
};

export const messagingAccess = readonly(state);
