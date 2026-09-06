import "bootstrap";

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from "axios";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

const token = localStorage.getItem("token");
if (token) {
    window.axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
    const secure = window.location.protocol === "https:" ? "; secure" : "";
    document.cookie = `cnsc_token=${encodeURIComponent(
        token
    )}; path=/; samesite=lax${secure}`;
}

window.axios.interceptors.request.use((config) => {
    const token = localStorage.getItem("token");
    if (token) {
        config.headers = config.headers || {};
        const value = `Bearer ${token}`;
        config.headers.Authorization = config.headers.Authorization || value;
        config.headers["X-Authorization"] =
            config.headers["X-Authorization"] || value;
        config.headers["X-Api-Token"] = config.headers["X-Api-Token"] || token;
    }
    const socketId = window.Echo?.socketId?.();
    if (socketId) {
        config.headers = config.headers || {};
        config.headers["X-Socket-ID"] =
            config.headers["X-Socket-ID"] || socketId;
    }
    return config;
});

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error?.response?.status === 401) {
            localStorage.removeItem("token");
            localStorage.removeItem("user");
            localStorage.removeItem("permissions");
            localStorage.removeItem("impersonator_token");
        }
        return Promise.reject(error);
    }
);
