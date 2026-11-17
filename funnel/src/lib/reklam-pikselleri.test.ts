import { afterEach, expect, it } from "vitest";
import { satinAlmaOlayiGonder } from "./reklam-pikselleri";

afterEach(() => {
  delete window.fbq;
  delete window._fbq;
  delete window.ttq;
  document.head.replaceChildren();
});

it("ölçüm izni yoksa üçüncü taraf betik veya olay yüklemez", () => {
  satinAlmaOlayiGonder({
    izin: false,
    oturum: "cs_test",
    deneme: false,
    metaPixelId: "meta-1",
    tiktokPixelId: "tt-1",
  });

  expect(document.head.querySelectorAll("script")).toHaveLength(0);
  expect(window.fbq).toBeUndefined();
  expect(window.ttq).toBeUndefined();
});

it("geçerli ödeme oturumunu iki ağda aynı event ID ile tekilleştirir", () => {
  satinAlmaOlayiGonder({
    izin: true,
    oturum: "cs_test_123",
    deneme: false,
    metaPixelId: "meta-1",
    tiktokPixelId: "tt-1",
  });

  const metaKuyrugu = window.fbq?.queue as unknown[][];
  expect(metaKuyrugu).toContainEqual([
    "track",
    "Purchase",
    {},
    { eventID: "cs_test_123" },
  ]);
  expect(window.ttq?._q).toContainEqual([
    "track",
    "CompletePayment",
    {},
    { event_id: "cs_test_123" },
  ]);
  expect(
    document.head.querySelector(
      'script[src="https://connect.facebook.net/en_US/fbevents.js"]',
    ),
  ).not.toBeNull();
  expect(
    document.head.querySelector('script[src*="analytics.tiktok.com"]'),
  ).not.toBeNull();
});

it("oturum kimliği ve piksel ayarı yoksa hiçbir betik yüklemez", () => {
  satinAlmaOlayiGonder({
    izin: true,
    oturum: "not-a-stripe-session",
    deneme: false,
    metaPixelId: "",
    tiktokPixelId: "",
  });

  expect(document.head.querySelectorAll("script")).toHaveLength(0);
});

it("Ç118: kartlı deneme iki ağda da StartTrial, aynı event ID ile", () => {
  satinAlmaOlayiGonder({
    izin: true,
    oturum: "cs_test_deneme",
    deneme: true,
    metaPixelId: "meta-1",
    tiktokPixelId: "tt-1",
  });

  expect(window.fbq?.queue as unknown[][]).toEqual(
    expect.arrayContaining([
      ["track", "StartTrial", {}, { eventID: "cs_test_deneme" }],
    ]),
  );
  expect(
    (window.fbq?.queue as unknown[][]).some((o) => o[1] === "Purchase"),
  ).toBe(false);
  expect(window.ttq?._q).toContainEqual([
    "track",
    "StartTrial",
    {},
    { event_id: "cs_test_deneme" },
  ]);
  expect(window.ttq?._q?.some((o) => o[1] === "CompletePayment")).toBe(false);
});
