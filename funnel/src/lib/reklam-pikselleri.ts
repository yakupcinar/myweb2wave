// H6c: üçüncü taraf ölçüm betikleri yalnız açık izin ve doğrulanmış ödeme
// sonrası yüklenir. Stripe oturum kimliği her iki kanalda tekilleştirme ID'si.
type MetaPixel = ((...args: unknown[]) => void) & {
  callMethod?: (...args: unknown[]) => void;
  queue?: unknown[];
  loaded?: boolean;
  version?: string;
  push?: (...args: unknown[]) => void;
};
type TikTokPixel = {
  _i?: Record<string, unknown>;
  _q?: unknown[][];
  loaded?: boolean;
  load?: (id: string) => void;
  page?: () => void;
  track?: (
    event: string,
    properties?: Record<string, unknown>,
    options?: { event_id: string },
  ) => void;
  [key: string]: unknown;
};

declare global {
  interface Window {
    fbq?: MetaPixel;
    _fbq?: MetaPixel;
    ttq?: TikTokPixel;
  }
}

function metaHazirla(): MetaPixel {
  if (window.fbq) return window.fbq;
  const fbq = function (...args: unknown[]) {
    if (fbq.callMethod) fbq.callMethod(...args);
    else (fbq.queue ??= []).push(args);
  } as MetaPixel;
  fbq.push = fbq;
  fbq.loaded = true;
  fbq.version = "2.0";
  fbq.queue = [];
  window.fbq = window._fbq = fbq;
  const script = document.createElement("script");
  script.async = true;
  script.src = "https://connect.facebook.net/en_US/fbevents.js";
  document.head.append(script);
  return fbq;
}

function tiktokHazirla(pixelId: string): TikTokPixel {
  const mevcut = window.ttq;
  if (mevcut?._i?.[pixelId]) return mevcut;
  const ttq: TikTokPixel = mevcut ?? {};
  ttq._i ??= {};
  ttq._q ??= [];
  const sirala =
    (ad: string) =>
    (...args: unknown[]) => {
      ttq._q!.push([ad, ...args]);
    };
  for (const ad of [
    "page",
    "track",
    "identify",
    "instances",
    "debug",
    "on",
    "off",
    "once",
    "ready",
    "alias",
    "group",
    "enableCookie",
    "disableCookie",
    "holdConsent",
    "revokeConsent",
    "grantConsent",
  ]) {
    if (typeof ttq[ad] !== "function") ttq[ad] = sirala(ad);
  }
  ttq.load ??= (id: string) => {
    if (ttq._i![id]) return;
    ttq._i![id] = true;
    const script = document.createElement("script");
    script.async = true;
    script.src = `https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=${encodeURIComponent(id)}&lib=ttq`;
    document.head.append(script);
  };
  window.ttq = ttq;
  ttq.load(pixelId);
  ttq.page?.();
  return ttq;
}

export function satinAlmaOlayiGonder({
  izin,
  oturum,
  metaPixelId,
  tiktokPixelId,
}: {
  izin: boolean;
  oturum: string | null;
  metaPixelId: string;
  tiktokPixelId: string;
}): void {
  if (!izin || !oturum?.startsWith("cs_")) return;

  if (metaPixelId) {
    const fbq = metaHazirla();
    fbq("init", metaPixelId);
    fbq("track", "Purchase", {}, { eventID: oturum });
  }

  if (tiktokPixelId) {
    const ttq = tiktokHazirla(tiktokPixelId);
    ttq.track?.("CompletePayment", {}, { event_id: oturum });
  }
}
