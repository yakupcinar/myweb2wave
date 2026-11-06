import { describe, expect, it } from "vitest";
import { fiyatMetni, kosulMetni } from "./fiyat";

// Ç19/Ç21: beklenen metinler elle (Intl'in tr-TR biçimi), koddan hesaplanmaz.
// M1b: \u00a0 bölünmez boşluk — tutar birimiyle aynı satırda kalır.
describe("fiyat metni", () => {
  it("ana birim + para birimi + aralık", () => {
    expect(
      fiyatMetni({ tutar: 9.99, paraBirimi: "EUR", aralik: "month" }),
    ).toBe("€9,99\u00a0/\u00a0ay");
    expect(fiyatMetni({ tutar: 20, paraBirimi: "USD", aralik: "year" })).toBe(
      "$20,00\u00a0/\u00a0yıl",
    );
    expect(fiyatMetni({ tutar: 1200, paraBirimi: "JPY", aralik: "week" })).toBe(
      "¥1.200\u00a0/\u00a0hafta",
    );
  });
  it("M1b: teklif başta, normal fiyat 'sonra' ile", () => {
    const aylik = { tutar: 9.99, paraBirimi: "EUR", aralik: "month" } as const;
    expect(fiyatMetni({ ...aylik, teklif: { tur: "deneme", gun: 7 } })).toBe(
      "7\u00a0gün ücretsiz, sonra\u00a0€9,99\u00a0/\u00a0ay",
    );
    expect(
      fiyatMetni({ ...aylik, teklif: { tur: "giris", gun: 3, tutar: 0.99 } }),
    ).toBe("3\u00a0gün\u00a0€0,99, sonra\u00a0€9,99\u00a0/\u00a0ay");
    expect(
      fiyatMetni({
        tutar: 59.99,
        paraBirimi: "USD",
        aralik: "year",
        teklif: { tur: "indirim", yuzde: 50 },
      }),
    ).toBe(
      "İlk\u00a0yıl %50\u00a0indirimli, sonra\u00a0$59,99\u00a0/\u00a0yıl",
    );
  });
  it("fiyat yoksa metin yok", () => {
    expect(fiyatMetni(undefined)).toBeUndefined();
    expect(kosulMetni(undefined)).toBeUndefined();
  });
  // M2a: ödeme koşulu — bugün ne, ilk ödeme ne zaman, ne ile yenilenir (elle).
  it("M2a: ödeme koşulu teklife göre", () => {
    const aylik = { tutar: 9.99, paraBirimi: "EUR", aralik: "month" } as const;
    expect(kosulMetni(aylik)).toBe(
      "Bugün €9,99 ödenir; abonelik her\u00a0ay €9,99 ile yenilenir.",
    );
    expect(kosulMetni({ ...aylik, teklif: { tur: "deneme", gun: 7 } })).toBe(
      "Bugün ödeme yok. 7\u00a0gün sonra ilk ödeme alınır; abonelik her\u00a0ay €9,99 ile yenilenir.",
    );
    expect(
      kosulMetni({ ...aylik, teklif: { tur: "giris", gun: 3, tutar: 0.99 } }),
    ).toBe(
      "Bugün €0,99 ödenir. 3\u00a0gün sonra ilk ödeme alınır; abonelik her\u00a0ay €9,99 ile yenilenir.",
    );
    expect(
      kosulMetni({
        tutar: 59.99,
        paraBirimi: "USD",
        aralik: "year",
        teklif: { tur: "indirim", yuzde: 50 },
      }),
    ).toBe(
      "İlk\u00a0yıl %50\u00a0indirimli ödenir; sonra abonelik her\u00a0yıl $59,99 ile yenilenir.",
    );
  });
});
