import { expect, it } from "vitest";
import { oluBaglantilar } from "./baglanti";

// Ç23: taşınan iptal sayfası "/"a (404) bağlanıyordu; tip denetimi ve
// derleme yakalamadı. Derleme çıktısındaki her iç bağlantı bir dosyaya
// çözülmeli.
const dosyalar = new Set([
  "keepchat/index.html",
  "keepchat/iptal/index.html",
  "_astro/Huni.a.js",
  "_astro/huni.b.css",
]);
const varMi = (yol: string): boolean => dosyalar.has(yol);

it("dizin, index.html ve varlık bağlantıları çözülür; sorgu ve çapa yok sayılır", () => {
  const sayfa =
    '<a href="/keepchat/">a</a><a href="/keepchat/iptal">b</a>' +
    '<link href="/_astro/huni.b.css" rel="stylesheet"><script src="/_astro/Huni.a.js"></script>' +
    '<a href="/keepchat/?k=x#ust">c</a>';
  expect(oluBaglantilar({ "keepchat/index.html": sayfa }, varMi)).toEqual([]);
});

it("çözülmeyen iç bağlantı sayfasıyla raporlanır", () => {
  const sayfa =
    '<a href="/">kök</a><a href="/keepchat/yok">x</a><img src="/_astro/eksik.png">';
  expect(oluBaglantilar({ "keepchat/iptal/index.html": sayfa }, varMi)).toEqual(
    [
      "keepchat/iptal/index.html → /",
      "keepchat/iptal/index.html → /keepchat/yok",
      "keepchat/iptal/index.html → /_astro/eksik.png",
    ],
  );
});

it("dış, protokolsüz ve sayfa içi bağlantılar denetlenmez", () => {
  const sayfa =
    '<a href="https://ornek.com/x">a</a><a href="//cdn.ornek/x">b</a>' +
    '<a href="#ust">c</a><a href="mailto:a@b.c">d</a><a href="keepchat://giris">e</a>';
  expect(oluBaglantilar({ "s.html": sayfa }, varMi)).toEqual([]);
});
