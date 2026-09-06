// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from "vitest";

const axiosMock = vi.hoisted(() => {
    const requestHandlers = [];
    return {
        defaults: { headers: { common: {} } },
        interceptors: {
            request: {
                use: vi.fn((handler) => requestHandlers.push(handler)),
            },
            response: { use: vi.fn() },
        },
        requestHandlers,
    };
});

vi.mock("axios", () => ({ default: axiosMock }));
vi.mock("bootstrap", () => ({}));

describe("cabecera de socket de mensajería", () => {
    beforeEach(() => {
        localStorage.clear();
        localStorage.setItem("token", "token-value");
        axiosMock.requestHandlers.length = 0;
        axiosMock.interceptors.request.use.mockClear();
        axiosMock.interceptors.response.use.mockClear();
    });

    it("envía X-Socket-ID para que toOthers no rebote al emisor", async () => {
        window.Echo = { socketId: () => "1234.5678" };
        await import("../../resources/js/bootstrap.js");
        const interceptor = axiosMock.requestHandlers.at(-1);

        const config = interceptor({ headers: {} });

        expect(config.headers["X-Socket-ID"]).toBe("1234.5678");
        expect(config.headers.Authorization).toBe("Bearer token-value");
        delete window.Echo;
    });
});
