// Önden yükleme denetimi (B4, Ç52 (1)): sayfanın giriş modülünün durağan
// içe aktarmaları — özyinelemeli — <link rel="modulepreload"> ile ilk turda
// inmeli; yoksa tarayıcı onları ancak giriş inip çözümlenince öğrenir
// (ikinci, sıralı JS turu; ölçüldü: huni-oynatici-h2.md). Astro döneminde
// bunu kendi entegrasyonumuz yazıyordu; Vite girişler için kendisi yazar —
// burada yalnız DENETLENİR (bin/kontrol "funnel-onyukleme").
const ICE_AKTARMA = /(?:from|import)\s*"\.\/([^"]+\.js)"/g;
const GIRIS = /<script type="module"[^>]*src="\/assets\/([^"]+\.js)"/g;
const ONYUKLEME = /<link rel="modulepreload"[^>]*href="\/assets\/([^"]+\.js)"/g;

/** Girişlerin bütün durağan bağımlılıkları (girişler hariç). */
export function bagimliliklar(
  html: string,
  oku: (dosya: string) => string,
): string[] {
  const girisler = [...html.matchAll(GIRIS)].map((m) => m[1] ?? "");
  const goruldu = new Set<string>(girisler);
  const sira = [...girisler];
  while (sira.length) {
    const dosya = sira.shift() ?? "";
    for (const m of oku(dosya).matchAll(ICE_AKTARMA)) {
      const ad = m[1] ?? "";
      if (!goruldu.has(ad)) {
        goruldu.add(ad);
        sira.push(ad);
      }
    }
  }
  return [...goruldu].filter((d) => !girisler.includes(d));
}

/** Önden yüklenmeyen bağımlılıklar — boş olmalı. */
export function eksikOnyuklemeler(
  html: string,
  oku: (dosya: string) => string,
): string[] {
  const var_ = new Set([...html.matchAll(ONYUKLEME)].map((m) => m[1] ?? ""));
  return bagimliliklar(html, oku).filter((d) => !var_.has(d));
}
