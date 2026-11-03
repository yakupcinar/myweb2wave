// Tanım isteğini HTML ayrıştırılırken başlatan satır içi betik (B4/H0):
// sayfadaki `<!-- erken-istek:<uygulama> -->` işareti, `tanimIstegi`
// fonksiyonunun KAYNAĞIYLA değiştirilir — JS paketini beklemez. Astro'daki
// `<script is:inline set:html>` yerine (Astro'yu kaldırma, S2). Değerler sabit
// (uygulama adı, derlemedeki çekirdek adresi) ve JSON ile kaçışlanır.
import type { Plugin } from "vite";
import { tanimIstegi } from "./oynatici/erken-istek";

const ISARET = /<!-- erken-istek:([a-z0-9-]+) -->/;

export function erkenIstekEkle(html: string, kok: string): string {
  return html.replace(
    ISARET,
    (_, app: string) =>
      `<script>window.huniTanimi=(${tanimIstegi.toString()})(${JSON.stringify(app)},${JSON.stringify(kok)});</script>`,
  );
}

export default function erkenIstek(kok: string): Plugin {
  return {
    name: "erken-istek",
    transformIndexHtml: (html) => erkenIstekEkle(html, kok),
  };
}
