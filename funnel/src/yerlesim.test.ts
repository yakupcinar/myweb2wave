// V1: telefonda düğme alta yapışır — main'in yüksekliği flex zinciriyle
// adımlara iner (huni.css). Bağlama kökü (#huni) zincirde kutu üretirse zincir
// kopar, düğme içeriğin hemen altında kalır; hata yok, happy-dom görmez
// (Vite geçişinde oldu, ekran ölçümü buldu). Vitest CSS'i boşaltır (§32):
// dosyalar diskten okunur. Gerçek ölçüm: ekran_goruntusu → olcum.
import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import { expect, it } from "vitest";

// Ortam happy-dom: import.meta.url dosya adresi değil; kök npm'in dizini.
const kok = process.cwd();
const css = readFileSync(join(kok, "../huni-bilesenleri/huni.css"), "utf8");

it("her uygulama sayfasında #huni main'in doğrudan çocuğu ve kutu üretmiyor", () => {
  const sayfalar = readdirSync(kok, { withFileTypes: true }).filter(
    (d) =>
      d.isDirectory() &&
      d.name !== "node_modules" &&
      readdirSync(join(kok, d.name)).includes("index.html"),
  );
  expect(sayfalar.length).toBeGreaterThan(0);
  for (const s of sayfalar) {
    const html = readFileSync(join(kok, s.name, "index.html"), "utf8");
    expect(html, s.name).toMatch(/<main>\s*<div id="huni"/);
  }
  expect(css).toMatch(/\.huni main > #huni \{\s*display: contents;/);
});
