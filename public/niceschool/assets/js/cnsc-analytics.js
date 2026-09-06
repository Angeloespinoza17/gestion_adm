(function () {
  "use strict";

  if (navigator.doNotTrack === "1" || window.doNotTrack === "1") return;

  var body = document.body;
  if (!body || body.dataset.analyticsDisabled === "true") return;

  var PAGE_VIEW_URL = "/api/public/web-analytics/page-view";
  var ENGAGEMENT_URL = "/api/public/web-analytics/engagement";
  var visitorId = storedUuid("localStorage", "cnsc_analytics_visitor");
  var sessionId = storedUuid("sessionStorage", "cnsc_analytics_session");
  var acquisition = storedAcquisition();
  var visitId = null;
  var activeStartedAt = document.visibilityState === "visible" ? Date.now() : null;
  var activeMilliseconds = 0;
  var maxScrollDepth = 0;
  var interactionCount = 0;
  var lastSentSnapshot = "";

  function uuid() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") {
      return window.crypto.randomUUID();
    }

    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (character) {
      var random = Math.random() * 16 | 0;
      var value = character === "x" ? random : (random & 3 | 8);
      return value.toString(16);
    });
  }

  function storage(type) {
    try {
      return window[type];
    } catch (error) {
      return null;
    }
  }

  function storedUuid(type, key) {
    var target = storage(type);
    var current = null;

    try {
      current = target ? target.getItem(key) : null;
    } catch (error) {
      current = null;
    }

    if (/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(current || "")) {
      return current;
    }

    current = uuid();

    try {
      if (target) target.setItem(key, current);
    } catch (error) {
      // Storage can be disabled; the in-memory identifier still protects navigation.
    }

    return current;
  }

  function referrerHost() {
    if (!document.referrer) return "";

    try {
      return new URL(document.referrer).hostname.replace(/^www\./, "").toLowerCase();
    } catch (error) {
      return "";
    }
  }

  function storedAcquisition() {
    var target = storage("sessionStorage");
    var key = "cnsc_analytics_acquisition";

    try {
      var stored = target ? JSON.parse(target.getItem(key) || "null") : null;
      if (stored && typeof stored === "object") return stored;
    } catch (error) {
      // Ignore malformed or unavailable session storage.
    }

    var params = new URLSearchParams(window.location.search);
    var value = {
      referrer_host: referrerHost(),
      campaign_source: params.get("utm_source") || "",
      campaign_medium: params.get("utm_medium") || "",
      campaign_name: params.get("utm_campaign") || "",
    };

    try {
      if (target) target.setItem(key, JSON.stringify(value));
    } catch (error) {
      // Acquisition remains available in memory for this page view.
    }

    return value;
  }

  function inferPageType() {
    if (body.dataset.analyticsPageType) return body.dataset.analyticsPageType;
    return body.dataset.analyticsContentType ? "detail" : "page";
  }

  function pageViewPayload() {
    return {
      visitor_id: visitorId,
      session_id: sessionId,
      path: window.location.pathname || "/",
      title: document.title || "",
      page_type: inferPageType(),
      content_type: body.dataset.analyticsContentType || "",
      content_identifier: body.dataset.analyticsContentId || "",
      content_slug: body.dataset.analyticsContentSlug || "",
      referrer_host: acquisition.referrer_host || "",
      campaign_source: acquisition.campaign_source || "",
      campaign_medium: acquisition.campaign_medium || "",
      campaign_name: acquisition.campaign_name || "",
      viewport_width: Math.round(window.innerWidth || document.documentElement.clientWidth || 0) || null,
    };
  }

  function post(url, payload, keepalive) {
    return window.fetch(url, {
      method: "POST",
      credentials: "same-origin",
      keepalive: Boolean(keepalive),
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(payload),
    });
  }

  function activeSeconds() {
    var total = activeMilliseconds;
    if (activeStartedAt !== null) total += Date.now() - activeStartedAt;
    return Math.min(43200, Math.max(0, Math.floor(total / 1000)));
  }

  function updateScrollDepth() {
    var root = document.documentElement;
    var total = Math.max(root.scrollHeight, body.scrollHeight) - window.innerHeight;
    var depth = total <= 0 ? 100 : Math.round((window.scrollY / total) * 100);
    maxScrollDepth = Math.max(maxScrollDepth, Math.min(100, Math.max(0, depth)));
  }

  function engagementPayload() {
    return {
      visit_id: visitId,
      visitor_id: visitorId,
      session_id: sessionId,
      engaged_seconds: activeSeconds(),
      max_scroll_depth: maxScrollDepth,
      interaction_count: Math.min(65535, interactionCount),
    };
  }

  function sendEngagement(keepalive) {
    if (!visitId) return;

    updateScrollDepth();
    var payload = engagementPayload();
    var snapshot = [payload.engaged_seconds, payload.max_scroll_depth, payload.interaction_count].join(":");
    if (!keepalive && snapshot === lastSentSnapshot) return;
    lastSentSnapshot = snapshot;

    post(ENGAGEMENT_URL, payload, keepalive).catch(function () {
      // Analytics must never interrupt the public experience.
    });
  }

  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "visible") {
      activeStartedAt = Date.now();
      return;
    }

    if (activeStartedAt !== null) {
      activeMilliseconds += Date.now() - activeStartedAt;
      activeStartedAt = null;
    }

    sendEngagement(true);
  });

  window.addEventListener("scroll", updateScrollDepth, { passive: true });
  document.addEventListener("click", function () {
    interactionCount += 1;
  }, { passive: true });
  window.addEventListener("pagehide", function () {
    if (activeStartedAt !== null) {
      activeMilliseconds += Date.now() - activeStartedAt;
      activeStartedAt = null;
    }
    sendEngagement(true);
  });

  post(PAGE_VIEW_URL, pageViewPayload(), false)
    .then(function (response) {
      if (!response.ok || response.status === 204) return null;
      return response.json();
    })
    .then(function (data) {
      visitId = data && data.visit_id ? data.visit_id : null;
    })
    .catch(function () {
      // Analytics must never interrupt the public experience.
    });

  window.setInterval(function () {
    if (document.visibilityState === "visible") sendEngagement(false);
  }, 20000);
})();
