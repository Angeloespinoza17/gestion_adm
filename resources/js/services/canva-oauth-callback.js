const callbackStatuses = new Set(["connected", "error"]);
const callbackQueryKeys = ["canva", "code", "connection"];

function safeSupportCode(value) {
    const code = String(value || "").trim();
    return /^[A-Z0-9_]{1,80}$/.test(code) ? code : "";
}

export function consumeCanvaOAuthCallback(browser = typeof window === "undefined" ? null : window) {
    if (!browser?.location?.href || typeof browser?.history?.replaceState !== "function") return null;

    let url;
    try {
        url = new URL(browser.location.href);
    } catch {
        return null;
    }

    const status = String(url.searchParams.get("canva") || "").toLowerCase();
    if (!callbackStatuses.has(status)) return null;

    const callback = {
        status,
        code: safeSupportCode(url.searchParams.get("code")),
        connection: String(url.searchParams.get("connection") || "").trim(),
    };

    callbackQueryKeys.forEach((key) => url.searchParams.delete(key));
    browser.history.replaceState(browser.history.state, "", `${url.pathname}${url.search}${url.hash}`);

    return callback;
}

export function canvaOAuthCallbackNotice(callback, connection = {}) {
    if (!callback) return null;
    const code = safeSupportCode(callback.code);

    if (callback.status === "connected") {
        if (connection.connected !== true) {
            return {
                tone: "danger",
                text: "Canva devolvió la autorización, pero no pudimos verificar la conexión. Pulsa “Verificar conexión”; si continúa, vuelve a conectar la cuenta.",
            };
        }

        const mode = String(connection.mode || "").toLowerCase();
        const autofillAvailable = connection.autofill_available === true
            || ["enterprise", "development_trial"].includes(mode);
        if (!autofillAvailable) {
            return {
                tone: "warning",
                text: "La cuenta Canva quedó conectada, pero su plan no incluye Autofill. Cambia a una cuenta habilitada o solicita acceso a Brand Templates + Autofill.",
            };
        }

        return {
            tone: "success",
            text: "Canva quedó conectado y verificado. Elige una plantilla compatible para continuar.",
        };
    }

    if (code === "CANVA_OAUTH_DENIED") {
        return {
            tone: "warning",
            text: "La autorización de Canva fue cancelada. Pulsa “Conectar Canva” cuando quieras intentarlo nuevamente.",
        };
    }
    if (["CANVA_OAUTH_STATE_INVALID", "CANVA_OAUTH_CALLBACK_INVALID"].includes(code)) {
        return {
            tone: "warning",
            text: "El enlace de autorización de Canva venció o ya fue utilizado. Pulsa “Conectar Canva” para iniciar un intento nuevo.",
        };
    }
    if (code === "CANVA_NOT_CONFIGURED") {
        return {
            tone: "danger",
            text: "Canva no está configurado en este entorno. Solicita a un administrador que revise la integración antes de volver a intentarlo.",
        };
    }

    const supportCode = code ? ` Código de soporte: ${code}.` : "";
    return {
        tone: "danger",
        text: `Canva no pudo completar la conexión. Pulsa “Conectar Canva” para intentarlo nuevamente; si continúa, informa el problema al administrador.${supportCode}`,
    };
}
