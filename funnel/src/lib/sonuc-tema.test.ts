import { readFileSync } from "node:fs";
import { join } from "node:path";
import { afterEach, describe, expect, it, vi } from "vitest";
import { temaKaydet, temaUygula } from "./sonuc-tema";

const TEMA = {
  anaRenk: "#db2777",
  arkaPlan: "#fff7ed",
  metinRengi: "#431407",
  yaziTipi: "yuvarlak" as const,
  kose: 24,
};
const kok = (): CSSStyleDeclaration => document.documentElement.style;

afterEach(() => {
  sessionStorage.clear();
  document.documentElement.removeAttribute("style");
  vi.restoreAllMocks();
});

// Mobil görünüm 5: Stripe'tan aynı sekmeye dönen sonuç sayfası huninin
// temasıyla çizilir — tema oturum belleğinde taşınır, adreste değil.
describe("sonuç sayfası teması", () => {
  it("kaydedilen tema sonuç sayfasında köke yazılır", () => {
    temaKaydet(TEMA);
    temaUygula("https://c");
    expect(kok().getPropertyValue("--ana")).toBe("#db2777");
    expect(kok().getPropertyValue("--yazi-tipi")).toContain("Nunito");
  });
  it("temasız huni kaydı siler — önceki huninin teması kalmaz", () => {
    temaKaydet(TEMA);
    temaKaydet(undefined);
    temaUygula("");
    expect(kok().getPropertyValue("--ana")).toBe("");
  });
  it("bozuk kayıt ya da kapalı depolama sayfayı düşürmez (varsayılan görünüm)", () => {
    sessionStorage.setItem("huni-tema", "{bozuk");
    expect(() => temaUygula("")).not.toThrow();
    expect(kok().getPropertyValue("--ana")).toBe("");
    vi.spyOn(Storage.prototype, "getItem").mockImplementation(() => {
      throw new Error("gizli sekme");
    });
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
      throw new Error("gizli sekme");
    });
    expect(() => temaKaydet(TEMA)).not.toThrow();
    expect(() => temaUygula("")).not.toThrow();
  });
});

describe("sonuç sayfaları huni görünümünde (kaynak)", () => {
  it.each(["basarili", "iptal"])(
    "%s: .huni gövde, huni.css, tema uygulanır",
    (ad) => {
      const sayfa = readFileSync(
        join(__dirname, `../../keepchat/${ad}/index.html`),
        "utf8",
      );
      const betik = readFileSync(join(__dirname, `../giris/${ad}.ts`), "utf8");
      expect(sayfa).toContain('<body class="huni">');
      expect(sayfa).toContain(`src="/src/giris/${ad}.ts"`);
      expect(betik).toContain('import "../../../huni-bilesenleri/huni.css";');
      expect(betik).toContain("temaUygula(");
    },
  );
});
