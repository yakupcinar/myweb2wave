// Ç23: derleme çıktısındaki iç bağlantılar (href/src, "/" ile başlayan)
// gerçekten bir dosyaya çözülüyor mu — taşınan sayfa ölü bağlantıyla
// yayınlanmasın. Dış, protokolsüz (//), sayfa içi (#) ve şema bağlantıları
// (mailto:, keepchat://) denetlenmez. bin/kontrol "funnel-baglanti" koşar.

/** @returns "sayfa → bağlantı" listesi; boşsa hepsi çözülüyor. */
export function oluBaglantilar(
  sayfalar: Record<string, string>,
  varMi: (yol: string) => boolean,
): string[] {
  const olu: string[] = [];
  for (const [sayfa, html] of Object.entries(sayfalar)) {
    for (const [, deger] of html.matchAll(/\s(?:href|src)="([^"]*)"/g)) {
      if (!deger?.startsWith("/") || deger.startsWith("//")) continue;
      const yol = deger.split(/[?#]/)[0]!.slice(1);
      const adaylar =
        yol === "" || yol.endsWith("/")
          ? [`${yol}index.html`]
          : [yol, `${yol}/index.html`];
      if (!adaylar.some(varMi)) olu.push(`${sayfa} → ${deger}`);
    }
  }
  return olu;
}
