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
  expect(istenen).toEqual(["http://cekirdek/huni/keepchat?k=fitness"]);
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
