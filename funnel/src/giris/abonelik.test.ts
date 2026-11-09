// @vitest-environment happy-dom
// M6b2: "Aboneliğimi yönet" sayfası — e-postayı çekirdeğe yollar; 202'de
// abonelik olsun olmasın aynı metin (hesap sızdırmaz), hata metinleri ayrı.
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { portalFormu } from "./abonelik";

// openapi-fetch fetch'i istemci kurulurken yakalar — sahte, modüllerden önce
// (Upsell.test ile aynı desen).
const ag = vi.hoisted(() => {
  const ag = {
    yanitlar: [] as (() => Response)[],
    istekler: [] as unknown[],
  };
  globalThis.fetch = async (girdi: RequestInfo | URL, init?: RequestInit) => {
    const istek = new Request(girdi, init);
    ag.istekler.push([new URL(istek.url).pathname, await istek.json()]);
    return (
      ag.yanitlar.shift() ?? (() => new Response(null, { status: 500 }))
    )();
  };
  return ag;
});

function kur(): { form: HTMLFormElement; durum: HTMLElement } {
  document.body.innerHTML = `<form id="f"><input name="eposta" value="  Ayse@Example.com "><button>Gönder</button></form><p id="d" hidden></p>`;
  const form = document.getElementById("f") as HTMLFormElement;
  const durum = document.getElementById("d")!;
  portalFormu(form, durum);
  return { form, durum };
}

beforeEach(() => {
  ag.istekler.length = 0;
  ag.yanitlar.length = 0;
});
afterEach(() => {
  document.body.innerHTML = "";
});

describe("M6b2 — portal bağlantısı formu", () => {
  it("e-postayı kırpılmış yollar; 202'de aynı (sızdırmayan) metin", async () => {
    ag.yanitlar.push(() => new Response(null, { status: 202 }));
    const { form, durum } = kur();
    form.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(durum.hidden).toBe(false));

    expect(ag.istekler).toEqual([
      [
        "/huni/portal-baglantisi",
        { app: "keepchat", eposta: "Ayse@Example.com" },
      ],
    ]);
    expect(durum.textContent).toBe(
      "Bu adrese bağlı bir abonelik varsa bağlantıyı gönderdik. E-postanı kontrol et (1 saat geçerli).",
    );
    expect(form.querySelector("button")!.disabled).toBe(false);
  });

  it("422 ve ağ hatası ayrı metin", async () => {
    ag.yanitlar.push(
      () =>
        new Response(JSON.stringify({ error: "invalid_request" }), {
          status: 422,
          headers: { "Content-Type": "application/json" },
        }),
    );
    const { form, durum } = kur();
    form.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(durum.hidden).toBe(false));
    expect(durum.textContent).toBe(
      "E-posta adresini kontrol edip yeniden dene.",
    );

    ag.yanitlar.push(() => {
      throw new TypeError("Failed to fetch");
    });
    const ikinci = kur();
    ikinci.form.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(ikinci.durum.hidden).toBe(false));
    expect(ikinci.durum.textContent).toBe(
      "Şu an gönderilemedi, birazdan yeniden dene.",
    );
  });
});
