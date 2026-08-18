// @vitest-environment jsdom

import { describe, expect, it } from "vitest";
import { resolveMessagingRealtimeOptions } from "../../resources/js/modules/messaging/services/messagingRealtime";

describe("resolveMessagingRealtimeOptions", () => {
  it("never exposes a local development socket to a production browser", () => {
    expect(resolveMessagingRealtimeOptions(
      {
        VITE_REVERB_HOST: "localhost",
        VITE_REVERB_PORT: "8080",
        VITE_REVERB_SCHEME: "http",
      },
      {
        hostname: "www.cnscgestion.cl",
        protocol: "https:",
      },
    )).toEqual({
      host: "www.cnscgestion.cl",
      scheme: "https",
      port: 443,
      forceTLS: true,
    });
  });

  it("preserves an explicitly configured public Reverb endpoint", () => {
    expect(resolveMessagingRealtimeOptions(
      {
        VITE_REVERB_HOST: "realtime.cnscgestion.cl",
        VITE_REVERB_PORT: "443",
        VITE_REVERB_SCHEME: "https",
      },
      {
        hostname: "www.cnscgestion.cl",
        protocol: "https:",
      },
    )).toEqual({
      host: "realtime.cnscgestion.cl",
      scheme: "https",
      port: 443,
      forceTLS: true,
    });
  });
});
