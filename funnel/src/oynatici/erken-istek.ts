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
  // M4a (A17): deney varyantı sekme tohumundan — ziyaret kimliğiyle aynı depo
  // (Ç34, çerez değil). Depo ya da randomUUID yoksa tohum yok → deney dışı.
  if (!onizleme) {
    try {
      const anahtar = "huni-tohum";
      let tohum = window.sessionStorage.getItem(anahtar);
      if (!tohum && window.crypto?.randomUUID) {
        tohum = window.crypto.randomUUID();
        window.sessionStorage.setItem(anahtar, tohum);
      }
      if (tohum) sorgu.set("tohum", tohum);
    } catch {
      // engelli depo: deney yok, huni yine açılır
    }
  }
  // M1c: ülke testi (?ulke=DE) fiyatın para birimini seçer — yayında da önizlemede de.
  const ulke = adres.get("ulke");
  if (ulke && /^[A-Z]{2}$/.test(ulke)) sorgu.set("ulke", ulke);
  // M9d1: dil testi / dil değiştirici (?dil=en) — yoksa çekirdek Accept-Language'e bakar.
  const dil = adres.get("dil");
  if (dil && /^[a-z]{2}$/.test(dil)) sorgu.set("dil", dil);
  const metin = sorgu.toString();
  return fetch(`${kok}${yol}${metin ? `?${metin}` : ""}`)
    .then((y) => (y.ok ? y.json() : undefined))
    .catch(() => undefined);
}
