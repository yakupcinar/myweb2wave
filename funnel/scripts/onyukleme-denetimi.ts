// bin/kontrol "funnel-onyukleme": dist'teki her sayfanın giriş modülünün
// durağan bağımlılıkları önden yükleniyor mu (Ç52 (1)).
import { readFileSync, readdirSync } from "node:fs";
import { join } from "node:path";
import { eksikOnyuklemeler } from "../src/onyukleme-denetimi.ts";

const kok = "dist";
const oku = (d: string): string => {
  try {
    return readFileSync(join(kok, "assets", d), "utf8");
  } catch {
    return "";
  }
};
let eksik = 0;
for (const ad of readdirSync(kok, { recursive: true, encoding: "utf8" })) {
  if (!ad.endsWith(".html")) continue;
  const e = eksikOnyuklemeler(readFileSync(join(kok, ad), "utf8"), oku);
  console.log(
    `${ad}: ${e.length ? `önden yüklenmeyen ${e.join(", ")}` : "tamam"}`,
  );
  eksik += e.length;
}
process.exit(eksik ? 1 : 0);
