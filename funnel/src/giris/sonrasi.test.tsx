// M3b: ödeme sonrası ekran — başlık, bloklar ve giriş düğmesi metni huninin
// `sonrasi`'ından; giriş alanı (bağlantı, yedek metin) sayfanın sabit parçası.
import { afterEach, describe, expect, it } from "vitest";
import { sonrasiCiz } from "./sonrasi";

const SAYFA = `<main><section><h1>Teşekkürler!</h1><p id="durum">Aboneliğiniz başlatılıyor…</p><div id="sonrasi"></div><div id="giris" hidden><a id="giris-baglantisi">KeepChat'i aç ve giriş yap</a></div></section></main>`;

afterEach(() => {
  document.body.innerHTML = "";
});

describe("M3b — ödeme sonrası ekran", () => {
  it("başlık, bloklar ve düğme metni tanımdan; giriş alanı yerinde kalır", () => {
    document.body.innerHTML = SAYFA;
    sonrasiCiz(
      {
        baslik: "Hoş geldin!",
        bloklar: [
          { tur: "metin", metin: "Uygulamayı açıp giriş yap." },
          {
            tur: "maddeler",
            maddeler: ["Bildirimleri aç", "Profilini doldur"],
          },
        ],
        dugme: "Uygulamaya geç",
      },
      "",
      undefined,
    );
    expect(document.querySelector("h1")?.textContent).toBe("Hoş geldin!");
    const bloklar = document.getElementById("sonrasi")!;
    expect(bloklar.textContent).toContain("Uygulamayı açıp giriş yap.");
    expect(
      [...bloklar.querySelectorAll("li")].map((l) => l.textContent),
    ).toEqual(["Bildirimleri aç", "Profilini doldur"]);
    expect(document.getElementById("giris-baglantisi")?.textContent).toBe(
      "Uygulamaya geç",
    );
    expect(document.getElementById("durum")?.textContent).toBe(
      "Aboneliğiniz başlatılıyor…",
    );
  });
  it("düğme metni yoksa sayfanın varsayılanı kalır; metin HTML olarak yorumlanmaz", () => {
    document.body.innerHTML = SAYFA;
    sonrasiCiz(
      { baslik: "<b>x</b>", bloklar: [{ tur: "metin", metin: "<img src=x>" }] },
      "",
      undefined,
    );
    expect(document.querySelector("h1")?.textContent).toBe("<b>x</b>");
    expect(document.querySelector("#sonrasi img")).toBeNull();
    expect(document.getElementById("giris-baglantisi")?.textContent).toBe(
      "KeepChat'i aç ve giriş yap",
    );
  });
});
