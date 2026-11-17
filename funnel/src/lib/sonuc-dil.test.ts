// @vitest-environment happy-dom
// M9d2b: ödeme sonrası sayfaların dili — oturum belleği → ?dil= → tarayıcı;
// sabit HTML metinleri data-metin ile sözlükten. Sayfalardaki her işaret
// sözlükte olmalı (yazım hatası sessizce Türkçe bırakırdı).
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { afterEach, describe, expect, it, vi } from "vitest";
import { METINLER } from "../../../huni-bilesenleri/metinler";
import { dilKaydet, metinleriUygula, sonucDili } from "./sonuc-dil";

afterEach(() => {
  sessionStorage.clear();
  history.replaceState(null, "", "/");
  document.documentElement.lang = "";
  document.head.innerHTML = "";
  document.body.innerHTML = "";
  vi.restoreAllMocks();
});

describe("sonuç sayfasının dili", () => {
  it("oturum belleği önce, sonra ?dil=, sonra tarayıcı; bilinmeyen tr", () => {
    vi.spyOn(navigator, "language", "get").mockReturnValue("en-US");
    expect(sonucDili()).toBe("en");
    history.replaceState(null, "", "/?dil=tr");
    expect(sonucDili()).toBe("tr");
    dilKaydet("en");
    expect(sonucDili()).toBe("en");
    sessionStorage.clear();
    history.replaceState(null, "", "/");
    vi.spyOn(navigator, "language", "get").mockReturnValue("de-DE");
    expect(sonucDili()).toBe("tr");
  });

  it("işaretli öğeleri, başlığı ve sayfa dilini yazar; bilinmeyen işaret dokunulmaz", () => {
    document.head.innerHTML =
      '<title data-metin="tesekkurler">Teşekkürler</title>';
    document.body.innerHTML =
      '<h1 data-metin="tesekkurlerBaslik">Teşekkürler!</h1><p data-metin="yok">aynen</p>';
    const m = metinleriUygula("en");
    expect(m).toBe(METINLER.en);
    expect(document.documentElement.lang).toBe("en");
    expect(document.title).toBe("Thank you");
    expect(document.querySelector("h1")?.textContent).toBe("Thank you!");
    expect(document.querySelector("p")?.textContent).toBe("aynen");
  });

  it("sayfalardaki her data-metin sözlükte bir metin", () => {
    const sayfalar = ["iptal", "basarili", "abonelik"].map((s) =>
      readFileSync(join(__dirname, `../../keepchat/${s}/index.html`), "utf8"),
    );
    const anahtarlar = sayfalar.flatMap((h) =>
      [...h.matchAll(/data-metin="([^"]+)"/g)].map((e) => e[1]!),
    );
    expect(anahtarlar.length).toBeGreaterThanOrEqual(14);
    for (const a of anahtarlar)
      for (const dil of ["tr", "en"] as const)
        expect(
          typeof METINLER[dil][a as keyof typeof METINLER.tr],
          `${dil}.${a}`,
        ).toBe("string");
  });
});
