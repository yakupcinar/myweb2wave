// bin/kontrol "funnel-baglanti": dist'teki her HTML'in iç bağlantıları (Ç23).
import { readFileSync, readdirSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import { oluBaglantilar } from "../src/baglanti.ts";

const kok = "dist";
const sayfalar: Record<string, string> = {};
for (const ad of readdirSync(kok, { recursive: true, encoding: "utf8" }))
  if (ad.endsWith(".html"))
    sayfalar[relative(kok, join(kok, ad))] = readFileSync(
      join(kok, ad),
      "utf8",
    );
// Yalnız dosya: dizin "var" sayılsaydı index.html'siz yol geçerdi.
let denetlenen = 0;
const olu = oluBaglantilar(sayfalar, (yol) => {
  denetlenen++;
  return statSync(join(kok, yol), { throwIfNoEntry: false })?.isFile() ?? false;
});
console.log(
  `${Object.keys(sayfalar).length} sayfa, ${denetlenen} çözüm denemesi, ${olu.length} ölü bağlantı`,
);
for (const o of olu) console.log(`  ${o}`);
process.exit(olu.length ? 1 : 0);
