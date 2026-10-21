import { expect, it } from "vitest";
import { adaModulleri, onyuklemeEkle } from "./onyukleme";

// Derlemedeki biçim (ölçüldü, funnel/dist): ada iki modül adı taşır,
// modüller birbirini "./ad.js" ile içe aktarır.
const dosyalar: Record<string, string> = {
  "Huni.a.js":
    'import{a as b}from"./react.c.js";import{d}from"./cekirdek.e.js";',
  "client.f.js": 'import{a}from"./react.c.js";',
  "react.c.js": "export const a=1;",
  "cekirdek.e.js": 'import"./ortak.g.js";',
  "ortak.g.js": "",
  "basarili.h.js": 'import"./cekirdek.e.js";',
};
const oku = (d: string): string => dosyalar[d] ?? "";
const ada =
  '<html><head><title>x</title></head><body><astro-island component-url="/_astro/Huni.a.js" renderer-url="/_astro/client.f.js"></astro-island></body></html>';

it("adanın modülleri ve bütün durağan bağımlılıkları, her biri bir kez", () => {
  expect(adaModulleri(ada, oku).sort()).toEqual([
    "Huni.a.js",
    "cekirdek.e.js",
    "client.f.js",
    "ortak.g.js",
    "react.c.js",
  ]);
});

it("bağlantılar head'in sonuna; adası olmayan sayfa değişmez", () => {
  const yeni = onyuklemeEkle(ada, oku);
  expect(yeni).toMatch(
    /<\/title>(<link rel="modulepreload" href="\/_astro\/[^"]+">){5}<\/head>/,
  );
  expect(yeni.match(/rel="modulepreload"/g)).toHaveLength(5);
  const adasiz = "<html><head></head><body><script>1</script></body></html>";
  expect(onyuklemeEkle(adasiz, oku)).toBe(adasiz);
});
