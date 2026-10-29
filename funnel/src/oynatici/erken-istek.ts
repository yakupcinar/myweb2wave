// Tanım isteği JS paketini BEKLEMEDEN başlar (B4/H2). H0 ölçümü: içerik
// HTML → JS → tanım sırasıyla geliyordu; React bu zinciri uzatırdı.
// HuniKabugu.astro bu fonksiyonun KAYNAĞINI sayfaya satır içi betik olarak
// gömer — bu yüzden kendi kendine yetmeli: içe aktarma, dış değişken yok.
// Seçimi çekirdek yapar (A8); YALNIZ seçim parametreleri gider — fbclid
// gibi her tıklamada farklı değerler önbelleği bozardı (D5).
export function tanimIstegi(app: string, kok: string): Promise<unknown> {
  const adres = new URLSearchParams(window.location.search);
  const onizleme = adres.get("onizleme");
  const sorgu = new URLSearchParams();
  let yol = `/huni/${app}`;
  if (onizleme) {
    yol += "/taslak";
    sorgu.set("huni", adres.get("huni") ?? "");
    sorgu.set("anahtar", onizleme);
    // Ç70: panelden yayındaki sürüme ölçümsüz bakış.
    if (adres.get("yayin") === "1") sorgu.set("yayin", "1");
  } else {
    for (const ad of [
      "k",
      "utm_source",
      "utm_campaign",
      "utm_content",
      "huni",
    ]) {
      const deger = adres.get(ad);
      if (deger) sorgu.set(ad, deger.slice(0, 200));
    }
  }
  const metin = sorgu.toString();
  return fetch(`${kok}${yol}${metin ? `?${metin}` : ""}`)
    .then((y) => (y.ok ? y.json() : undefined))
    .catch(() => undefined);
}
