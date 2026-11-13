// Erken tanım isteği (B4/H0): işaret, tanimIstegi'nin kaynağıyla değişir;
// değerler JSON ile kaçışlanır; kabuk sayfası işareti taşır.
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { expect, it } from "vitest";
import { erkenIstekEkle } from "./erken-istek-eklentisi";
import { tanimIstegi } from "./oynatici/erken-istek";

it("işaret satır içi betikle değişir: fonksiyonun kaynağı, uygulama ve kök", () => {
  const html = erkenIstekEkle(
    "<head><!-- erken-istek:keepchat --></head>",
    'https://api.ornek/"x',
  );
  expect(html).toBe(
    `<head><script>window.huniTanimi=(${tanimIstegi.toString()})("keepchat","https://api.ornek/\\"x");</script></head>`,
  );
});

it("işaretsiz sayfa değişmez", () => {
  expect(erkenIstekEkle("<head></head>", "k")).toBe("<head></head>");
});

it("huni kabuğu işareti taşır, sonuç sayfaları taşımaz", () => {
  const oku = (y: string) =>
    readFileSync(join(__dirname, "../keepchat", y), "utf8");
  expect(oku("index.html")).toContain("<!-- erken-istek:keepchat -->");
  expect(oku("basarili/index.html")).not.toContain("erken-istek");
});
