import { expect, it } from "vitest";
import { bagimliliklar, eksikOnyuklemeler } from "./onyukleme-denetimi";

// Vite çıktısının biçimi (ölçüldü, funnel/dist): giriş "/assets/ad.js",
// modüller birbirini "./ad.js" ile içe aktarır.
const dosyalar: Record<string, string> = {
  "huni-a.js":
    'import{a as b}from"./preact-c.js";import{d}from"./cekirdek-e.js";',
  "preact-c.js": "export const a=1;",
  "cekirdek-e.js": 'import"./ortak-g.js";',
  "ortak-g.js": "",
};
const oku = (d: string): string => dosyalar[d] ?? "";
const sayfa = (onyukleme: string[]): string =>
  `<html><head><script type="module" crossorigin src="/assets/huni-a.js"></script>${onyukleme
    .map((d) => `<link rel="modulepreload" crossorigin href="/assets/${d}">`)
    .join("")}</head><body></body></html>`;

it("girişin bütün durağan bağımlılıkları, her biri bir kez, giriş hariç", () => {
  expect(bagimliliklar(sayfa([]), oku).sort()).toEqual([
    "cekirdek-e.js",
    "ortak-g.js",
    "preact-c.js",
  ]);
});

it("hepsi önden yükleniyorsa eksik yok; biri eksikse adıyla", () => {
  expect(
    eksikOnyuklemeler(
      sayfa(["preact-c.js", "cekirdek-e.js", "ortak-g.js"]),
      oku,
    ),
  ).toEqual([]);
  expect(
    eksikOnyuklemeler(sayfa(["preact-c.js", "cekirdek-e.js"]), oku),
  ).toEqual(["ortak-g.js"]);
});

it("bağımlılığı olmayan giriş: denetlenecek bir şey yok", () => {
  expect(
    eksikOnyuklemeler(sayfa([]), (d) => (d === "huni-a.js" ? "" : "x")),
  ).toEqual([]);
});
