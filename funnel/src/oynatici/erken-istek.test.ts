import { afterEach, expect, it, vi } from "vitest";
import { tanimIstegi } from "./erken-istek";

afterEach(() => {
  vi.unstubAllGlobals();
});

// HuniKabugu.astro fonksiyonu KAYNAĞINDAN sayfaya gömer. Dış bir ada
// (içe aktarma, modül sabiti) dayanırsa sayfada ReferenceError ile sessizce
// düşer ve tanım yine JS'i bekler. Burada kaynaktan, yalıtık kapsamda kurulur.
it("kaynağından yeniden kurulunca da aynı isteği atar", async () => {
  const istenen: string[] = [];
  vi.stubGlobal("fetch", (adres: string) => {
    istenen.push(adres);
    return Promise.resolve(Response.json({ tamam: true }));
  });
  history.replaceState(null, "", "/keepchat/?k=fitness&fbclid=x");
  const yalitik = new Function(`return (${tanimIstegi.toString()})`)() as (
    app: string,
    kok: string,
  ) => Promise<unknown>;
  expect(await yalitik("keepchat", "http://cekirdek")).toEqual({ tamam: true });
  expect(istenen).toEqual([
    `http://cekirdek/huni/keepchat?k=fitness&tohum=${sessionStorage.getItem("huni-tohum")}`,
  ]);
});

// M4a (A17): varyant tohumdan hesaplanır — aynı sekmede aynı tohum, yoksa
// yenilemede başka varyant görülür. Çekirdeğin kalıbına uymalı.
it("tohum sekme deposunda kalır, çekirdeğin kalıbına uyar", async () => {
  const istenen: string[] = [];
  vi.stubGlobal("fetch", (adres: string) => {
    istenen.push(adres);
    return Promise.resolve(Response.json({}));
  });
  sessionStorage.removeItem("huni-tohum");
  history.replaceState(null, "", "/keepchat/");
  await tanimIstegi("keepchat", "http://cekirdek");
  await tanimIstegi("keepchat", "http://cekirdek");
  const tohumlar = istenen.map((a) => new URL(a).searchParams.get("tohum"));
  expect(tohumlar[0]).toMatch(/^[A-Za-z0-9-]{8,64}$/);
  expect(tohumlar[1]).toBe(tohumlar[0]);
  expect(sessionStorage.getItem("huni-tohum")).toBe(tohumlar[0]);
});

it("depo engelliyse tohum gitmez, istek yine atılır", async () => {
  const istenen: string[] = [];
  vi.stubGlobal("fetch", (adres: string) => {
    istenen.push(adres);
    return Promise.resolve(Response.json({}));
  });
  const engel = vi
    .spyOn(window, "sessionStorage", "get")
    .mockImplementation(() => {
      throw new DOMException("engelli", "SecurityError");
    });
  history.replaceState(null, "", "/keepchat/?k=a");
  await tanimIstegi("keepchat", "http://cekirdek");
  engel.mockRestore();
  expect(istenen).toEqual(["http://cekirdek/huni/keepchat?k=a"]);
});

it("yayin=1 önizlemesi taslak ucuna yayin parametresini taşır", async () => {
  const istenen: string[] = [];
  vi.stubGlobal("fetch", (adres: string) => {
    istenen.push(adres);
    return Promise.resolve(Response.json({}));
  });
  history.replaceState(null, "", "/keepchat/?huni=genel&yayin=1&onizleme=a.b");
  await tanimIstegi("keepchat", "http://cekirdek");
  expect(istenen).toEqual([
    "http://cekirdek/huni/keepchat/taslak?huni=genel&anahtar=a.b&yayin=1",
  ]);
});

// M9d1: dil testi / dil değiştirici çekirdeğe gider; biçimsiz değer gitmez
// (çekirdek o zaman Accept-Language'e bakar).
it("?dil= yalnız iki küçük harfse iletilir", async () => {
  const istenen: string[] = [];
  vi.stubGlobal("fetch", (adres: string) => {
    istenen.push(adres);
    return Promise.resolve(Response.json({}));
  });
  sessionStorage.removeItem("huni-tohum");
  for (const arama of ["?dil=en", "?dil=EN", "?dil=eng"]) {
    history.replaceState(null, "", `/keepchat/${arama}`);
    await tanimIstegi("keepchat", "http://cekirdek");
  }
  expect(istenen.map((a) => new URL(a).searchParams.get("dil"))).toEqual([
    "en",
    null,
    null,
  ]);
});
