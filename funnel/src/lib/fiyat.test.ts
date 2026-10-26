import { describe, expect, it } from "vitest";
import { fiyatMetni } from "./fiyat";

// Ç19/Ç21: beklenen metinler elle (Intl'in tr-TR biçimi), koddan hesaplanmaz.
describe("fiyat metni", () => {
  it("ana birim + para birimi + aralık", () => {
    expect(
      fiyatMetni({ tutar: 9.99, paraBirimi: "EUR", aralik: "month" }),
    ).toBe("€9,99 / ay");
    expect(fiyatMetni({ tutar: 20, paraBirimi: "USD", aralik: "year" })).toBe(
      "$20,00 / yıl",
    );
    expect(fiyatMetni({ tutar: 1200, paraBirimi: "JPY", aralik: "week" })).toBe(
      "¥1.200 / hafta",
    );
  });
  it("fiyat yoksa metin yok", () => {
    expect(fiyatMetni(undefined)).toBeUndefined();
  });
});
