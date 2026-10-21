// Ada modüllerini önden yükleme (B4, Ç52 (1)). Astro adası bileşeni ve
// React'i sayfadaki betik çalışınca içe aktarır; onların durağan
// bağımlılıklarını (react, cekirdek) tarayıcı ancak o dosyalar inip
// çözümlenince öğrenir → ikinci, sıralı bir JS turu (ölçüldü:
// huni-oynatici-h2.md). Derleme bitince her sayfaya adanın bütün modülleri
// için <link rel="modulepreload"> yazılır; hepsi ilk turda iner.
import { readFile, readdir, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import type { AstroIntegration } from "astro";

const ICE_AKTARMA = /(?:from|import)\s*"\.\/([^"]+\.js)"/g;
const ADA = /(?:component|renderer)-url="\/_astro\/([^"]+\.js)"/g;

/** Sayfadaki adaların modülleri ve onların durağan içe aktarmaları (özyinelemeli). */
export function adaModulleri(
  html: string,
  oku: (dosya: string) => string,
): string[] {
  const goruldu = new Set<string>();
  const sira = [...html.matchAll(ADA)].map((m) => m[1] ?? "");
  while (sira.length) {
    const dosya = sira.shift() ?? "";
    if (goruldu.has(dosya)) continue;
    goruldu.add(dosya);
    for (const m of oku(dosya).matchAll(ICE_AKTARMA)) sira.push(m[1] ?? "");
  }
  return [...goruldu];
}

export function onyuklemeEkle(
  html: string,
  oku: (dosya: string) => string,
): string {
  const baglantilar = adaModulleri(html, oku)
    .map((d) => `<link rel="modulepreload" href="/_astro/${d}">`)
    .join("");
  return baglantilar ? html.replace("</head>", `${baglantilar}</head>`) : html;
}

export default function onyukleme(): AstroIntegration {
  return {
    name: "ada-onyukleme",
    hooks: {
      "astro:build:done": async ({ dir }) => {
        const kok = fileURLToPath(dir);
        const js = new Map<string, string>();
        for (const ad of await readdir(`${kok}_astro`))
          if (ad.endsWith(".js"))
            js.set(ad, await readFile(`${kok}_astro/${ad}`, "utf8"));
        const oku = (d: string): string => js.get(d) ?? "";
        for (const yol of await readdir(kok, { recursive: true })) {
          if (!yol.endsWith(".html")) continue;
          const html = await readFile(`${kok}${yol}`, "utf8");
          const yeni = onyuklemeEkle(html, oku);
          if (yeni !== html) await writeFile(`${kok}${yol}`, yeni);
        }
      },
    },
  };
}
