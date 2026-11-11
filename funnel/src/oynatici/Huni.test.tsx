// Oynatıcının korunacak davranışları (H0 (b), D1–D15). Ağ sahte: çekirdeğe
// giden her istek kaydedilir — "kaç istek, hangi gövde" doğrudan ölçülür.
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/preact";
import {
  afterEach,
  beforeEach,
  describe,
  expect,
  it,
  onTestFinished,
  vi,
} from "vitest";
import { stripeYukle } from "../lib/stripe";
import type { components } from "../sozlesme/api";
import Huni from "./Huni";

// M2d: Stripe.js dışarıdan yüklenir — testte yükleyici sahte; varsayılan
// "yüklenemedi" (eski yol: barındırılan sayfaya yönlendirme).
vi.mock("../lib/stripe", () => ({ stripeYukle: vi.fn() }));

type Yayin = components["schemas"]["HuniYayini"];
type Istek = { yontem: string; yol: string; sorgu: string; govde: unknown };
type Yanit = { durum: number; govde?: unknown };
type Cevap = Yanit | "ag-hatasi";

// openapi-fetch fetch'i istemci kurulurken alır (cekirdek.ts, içe aktarmada):
// sahte, içe aktarmalardan ÖNCE yerinde olmalı.
const ag = vi.hoisted(() => {
  const ag = {
    istekler: [] as Istek[],
    // Söz dönerse yanıt bekletilir: uçuştaki istekle yarış ölçülür (Ç58).
    yanitla: (_i: Istek): Cevap | Promise<Cevap> => ({ durum: 404 }),
  };
  globalThis.fetch = async (girdi: RequestInfo | URL, init?: RequestInit) => {
    const r = new Request(girdi, init);
    const metin = await r.text();
    const adres = new URL(r.url);
    const istek = {
      yontem: r.method,
      yol: adres.pathname,
      sorgu: adres.search,
      govde: metin ? JSON.parse(metin) : undefined,
    };
    ag.istekler.push(istek);
    const y = await ag.yanitla(istek);
    if (y === "ag-hatasi") throw new TypeError("Failed to fetch");
    return new Response(
      y.govde === undefined ? null : JSON.stringify(y.govde),
      { status: y.durum, headers: { "Content-Type": "application/json" } },
    );
  };
  return ag;
});

const KOK = import.meta.env.PUBLIC_CORE_URL;
const G = "c".repeat(64);
const Z = "11111111-1111-4111-8111-111111111111";

const yayin = (ek: Partial<Yayin["tanim"]> = {}): Yayin => ({
  huni: "varsayilan",
  surum: 4,
  tanim: {
    sayfaBasligi: "KeepChat Pro",
    adimlar: [
      {
        id: "karsilama",
        tur: "ekran",
        baslik: "Merhaba",
        bloklar: [{ tur: "metin", metin: "Kısa" }],
        dugme: "Başla",
        gorsel: G,
      },
      {
        id: "kiminle",
        tur: "ekran",
        baslik: "Kiminle?",
        bloklar: [
          {
            tur: "soru",
            secenekler: [
              { id: "aile", metin: "Ailemle" },
              { id: "is", metin: "İş" },
            ],
          },
        ],
      },
      {
        id: "paywall",
        tur: "paywall",
        baslik: "Pro",
        metin: "9,99 €",
        dugme: "Abone ol",
        plan: "monthly",
      },
    ],
    ...ek,
  },
});

// T1c: soru adımı yok — "kiminle" ekranı farklı soru bloklarıyla.
type TanimAdimi = Yayin["tanim"]["adimlar"][number];
type Blok = Extract<TanimAdimi, { tur: "ekran" }>["bloklar"][number];
const SECENEKLER = [
  { id: "aile", metin: "Ailemle" },
  { id: "is", metin: "İş" },
];
const kiminle = (bloklar: Blok[], dugme: string): TanimAdimi => ({
  id: "kiminle",
  tur: "ekran",
  baslik: "Kiminle?",
  bloklar,
  dugme,
});

let odeme: Yanit | "ag-hatasi" = {
  durum: 201,
  govde: { url: "https://odeme.ornek/x" },
};
let tanim: Yanit = { durum: 200, govde: yayin() };
let ziyaret: Yanit = { durum: 201, govde: { ziyaret: Z } };

function kur(arama = ""): void {
  history.replaceState(null, "", `/keepchat/${arama}`);
  render(<Huni app="keepchat" />);
}
const istekler = (yontem: string, yol: string | RegExp): Istek[] =>
  ag.istekler.filter(
    (i) =>
      i.yontem === yontem &&
      (typeof yol === "string" ? i.yol === yol : yol.test(i.yol)),
  );
const gorunenBaslik = (): string =>
  document.querySelector("[data-sira]:not([hidden]) h1")?.textContent ?? "";
const ilerlemeler = (): unknown[] =>
  istekler("POST", `/huni/ziyaretler/${Z}/ilerleme`).map((i) => i.govde);
const yuklendi = (): Promise<HTMLElement> => screen.findByText("Merhaba");

beforeEach(() => {
  ag.istekler = [];
  tanim = { durum: 200, govde: yayin() };
  ziyaret = { durum: 201, govde: { ziyaret: Z } };
  odeme = { durum: 201, govde: { url: "https://odeme.ornek/x" } };
  vi.mocked(stripeYukle).mockResolvedValue(null);
  ag.yanitla = (i) => {
    if (i.yontem === "GET" && i.yol.startsWith("/huni/keepchat")) return tanim;
    if (i.yol === "/huni/ziyaretler") return ziyaret;
    if (i.yol.endsWith("/ilerleme")) return { durum: 204 };
    if (i.yol.endsWith("/eposta")) return { durum: 204 };
    if (i.yol === "/checkout/sessions") return odeme;
    return { durum: 404 };
  };
  delete window.huniTanimi;
  window.localStorage.clear(); // izin seçimi testler arasında sızmasın
  window.sessionStorage.clear(); // Ç34: sekmedeki ziyaret de
  document.documentElement.removeAttribute("style");
  document.body.removeAttribute("data-hareket");
  document.title = "";
});
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("D15 · D4 — yükleniyor ve açılamadı", () => {
  // M9d2: tanım gelmeden dil tarayıcıdan; bu metinler Türkçe tarayıcıda.
  beforeEach(() => {
    vi.spyOn(navigator, "language", "get").mockReturnValue("tr-TR");
  });
  it("tanım gelene kadar Yükleniyor…, sonra kalkar", async () => {
    kur();
    expect(screen.getByText("Yükleniyor…")).toBeTruthy();
    await yuklendi();
    expect(screen.queryByText("Yükleniyor…")).toBeNull();
  });
  it("yayın açılamazsa yenile metni", async () => {
    tanim = { durum: 404 };
    kur();
    await screen.findByText("Sayfa yüklenemedi. Lütfen yenileyin.");
  });
  it("ağ hatasında da aynı metin", async () => {
    ag.yanitla = () => "ag-hatasi";
    kur();
    await screen.findByText("Sayfa yüklenemedi. Lütfen yenileyin.");
  });
  it("önizleme açılamazsa süresi dolmuş metni", async () => {
    tanim = { durum: 404 };
    kur("?onizleme=abc");
    await screen.findByText(
      "Önizleme açılamadı: bağlantının süresi dolmuş olabilir.",
    );
  });
});

describe("M9d2 — huninin dili", () => {
  it("tanım gelmeden tarayıcının dili; ?dil= önce", async () => {
    vi.spyOn(navigator, "language", "get").mockReturnValue("en-GB");
    tanim = { durum: 404 };
    kur();
    expect(screen.getByText("Loading…")).toBeTruthy();
    await screen.findByText("The page couldn't load. Please refresh.");
    cleanup();
    kur("?dil=tr");
    await screen.findByText("Sayfa yüklenemedi. Lütfen yenileyin.");
  });

  it("İngilizce huni: sayfa dili, izin şeridi, geri, plan ve fiyat İngilizce; Türkçe huni tr", async () => {
    const temel = yayin({ dil: "en" });
    const adimlar = temel.tanim.adimlar.map((a) =>
      a.tur === "paywall"
        ? { ...a, planlar: ["yearly", "monthly"], onerilen: "yearly" }
        : a,
    );
    tanim = {
      durum: 200,
      govde: {
        ...temel,
        tanim: { ...temel.tanim, adimlar },
        fiyatlar: {
          monthly: {
            tutar: 9.99,
            paraBirimi: "EUR",
            aralik: "month",
            ad: "Monthly",
            teklif: { tur: "deneme", gun: 7 },
          },
          yearly: {
            tutar: 59.99,
            paraBirimi: "EUR",
            aralik: "year",
            ad: "Yearly",
          },
        },
      },
    };
    kur();
    await yuklendi();
    expect(document.documentElement.lang).toBe("en");
    // M9d2b: Stripe dönüşündeki sayfalar için oturum belleğinde.
    expect(sessionStorage.getItem("huni-dil")).toBe("en");
    expect(screen.getByRole("button", { name: "Accept" })).toBeTruthy();
    expect(screen.getByRole("button", { name: "Decline" })).toBeTruthy();
    fireEvent.click(screen.getByText("Başla"));
    expect(screen.getByRole("button", { name: "Back" })).toBeTruthy();
    fireEvent.click(screen.getByText("Ailemle"));
    const kart = (plan: string) =>
      document.querySelector<HTMLButtonElement>(
        `[data-adim="paywall"] [role="radio"][data-plan="${plan}"]`,
      )!;
    await waitFor(() => expect(kart("yearly")).toBeTruthy());
    expect(kart("yearly").textContent).toContain("Recommended");
    expect(kart("monthly").textContent).toContain(
      "7\u00a0days free, then\u00a0€9.99\u00a0/\u00a0month",
    );
    fireEvent.click(kart("monthly"));
    expect(
      document.querySelector('[data-adim="paywall"] p.kosul')?.textContent,
    ).toBe(
      "Nothing to pay today. First payment in 7\u00a0days; the subscription renews every\u00a0month at €9.99.",
    );
    cleanup();
    tanim = { durum: 200, govde: yayin() };
    kur();
    await yuklendi();
    expect(document.documentElement.lang).toBe("tr");
  });
});

describe("D5 — seçim parametreleri", () => {
  it("yalnız k/utm_source/utm_campaign/utm_content/huni gider, her biri ≤ 200", async () => {
    const uzun = "h".repeat(250);
    kur(
      `?k=fitness&utm_source=meta&utm_term=t&fbclid=xyz&ttclid=q&utm_campaign=c&utm_content=i&huni=${uzun}`,
    );
    await yuklendi();
    const [g] = istekler("GET", "/huni/keepchat");
    // M4a: sekme tohumu da gider (ayrıca erken-istek.test.ts).
    expect(Object.fromEntries(new URLSearchParams(g?.sorgu))).toEqual({
      k: "fitness",
      utm_source: "meta",
      utm_campaign: "c",
      utm_content: "i",
      huni: "h".repeat(200),
      tohum: sessionStorage.getItem("huni-tohum"),
    });
  });
  it("M1c: ülke testi (?ulke=TR) tanım isteğine gider; biçimsizse gitmez", async () => {
    kur("?k=fitness&ulke=TR");
    await yuklendi();
    expect(
      Object.fromEntries(
        new URLSearchParams(istekler("GET", "/huni/keepchat")[0]?.sorgu),
      ),
    ).toEqual({
      k: "fitness",
      ulke: "TR",
      tohum: sessionStorage.getItem("huni-tohum"),
    });
    cleanup();
    kur("?ulke=tr");
    await yuklendi();
    expect(
      new URLSearchParams(istekler("GET", "/huni/keepchat").at(-1)?.sorgu).has(
        "ulke",
      ),
    ).toBe(false);
  });
  it("sayfadaki erken istek varsa oynatıcı tanımı yeniden istemez", async () => {
    window.huniTanimi = Promise.resolve(yayin());
    kur();
    await yuklendi();
    expect(istekler("GET", /^\/huni\/keepchat/)).toHaveLength(0);
  });
});

describe("D3 — önizleme", () => {
  it("taslak ucu, şerit; ziyaret/adım/cevap yok, ödeme kapalı", async () => {
    kur("?onizleme=abc&huni=fitness&k=x");
    await yuklendi();
    const [g] = istekler("GET", "/huni/keepchat/taslak");
    expect(Object.fromEntries(new URLSearchParams(g?.sorgu))).toEqual({
      huni: "fitness",
      anahtar: "abc",
    });
    screen.getByText(
      "Önizleme — taslak; hiçbir şey kaydedilmez, ödeme kapalı.",
    );
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    const dugme = screen.getByText("Abone ol") as HTMLButtonElement;
    expect(dugme.disabled).toBe(true);
    fireEvent.click(dugme);
    await new Promise((r) => setTimeout(r, 20));
    expect(ag.istekler.filter((i) => i.yontem === "POST")).toEqual([]);
  });
});

describe("Ç70 — yayındaki sürümün önizlemesi", () => {
  it("yayin=1: taslak ucuna yayin gider, şerit yayındaki der; ziyaret yok, ödeme kapalı", async () => {
    kur("?huni=fitness&yayin=1&onizleme=abc");
    await yuklendi();
    const [g] = istekler("GET", "/huni/keepchat/taslak");
    expect(Object.fromEntries(new URLSearchParams(g?.sorgu))).toEqual({
      huni: "fitness",
      anahtar: "abc",
      yayin: "1",
    });
    screen.getByText(
      "Önizleme — yayındaki sürüm; hiçbir şey kaydedilmez, ödeme kapalı.",
    );
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    expect((screen.getByText("Abone ol") as HTMLButtonElement).disabled).toBe(
      true,
    );
    await new Promise((r) => setTimeout(r, 20));
    expect(ag.istekler.filter((i) => i.yontem === "POST")).toEqual([]);
  });
});

describe("Ç72 — önizlemede adım seçimi (ekran görüntüsü)", () => {
  // Adımların hepsi DOM'da; görünmeyen `hidden` taşır — görünürlük ölçülür.
  const gorunur = (metin: string): boolean =>
    screen.getByText(metin).closest("[hidden]") === null;
  it("önizlemede adim=<kimlik> o adımdan başlar", async () => {
    kur("?huni=fitness&onizleme=abc&adim=kiminle");
    await yuklendi();
    expect(gorunur("Kiminle?")).toBe(true);
    expect(gorunur("Merhaba")).toBe(false);
  });
  it("bilinmeyen adım ilk adımdan başlar", async () => {
    kur("?huni=fitness&onizleme=abc&adim=yok");
    await yuklendi();
    expect(gorunur("Merhaba")).toBe(true);
  });
  it("Ç75 ekran=1: şerit yok, ödeme düğmesi ziyaretçideki gibi etkin; tıklanınca ödeme başlamaz", async () => {
    kur("?huni=fitness&onizleme=abc&ekran=1&adim=paywall");
    await yuklendi();
    expect(screen.queryByText(/Önizleme —/)).toBeNull();
    const dugme = screen.getByText("Abone ol") as HTMLButtonElement;
    expect(gorunur("Abone ol")).toBe(true);
    expect(dugme.disabled).toBe(false);
    fireEvent.click(dugme);
    await new Promise((r) => setTimeout(r, 20));
    expect(ag.istekler.filter((i) => i.yontem === "POST")).toEqual([]);
  });
  it("gerçek ziyarette adim yok sayılır (ölçüm ilk adımdan)", async () => {
    kur("?adim=kiminle");
    await yuklendi();
    expect(gorunur("Merhaba")).toBe(true);
    expect(gorunur("Kiminle?")).toBe(false);
  });
});

describe("D1 · D2 · D6 · D8 — çizim", () => {
  it("metin HTML olarak yorumlanmaz", async () => {
    tanim = {
      durum: 200,
      govde: yayin({
        adimlar: [
          {
            id: "a",
            tur: "ekran",
            baslik: "<b>kalın</b>",
            bloklar: [{ tur: "metin", metin: "<img src=x onerror=alert(1)>" }],
            dugme: "Başla",
          },
        ],
      }),
    };
    kur();
    await screen.findByText("<b>kalın</b>");
    expect(document.querySelector("main b, body b")).toBeNull();
    expect(document.querySelector("img[onerror]")).toBeNull();
  });
  it("görsel çekirdeğin medya adresinden, başlık sekmede, çubuk adım sayısında", async () => {
    kur();
    await yuklendi();
    expect(document.querySelector("img.kapak")?.getAttribute("src")).toBe(
      `${KOK}/medya/${G}.webp`,
    );
    // Başlık ve tema effect'te yazılır: metnin görünmesi effect'in koştuğunu
    // garanti etmez (kırma denemelerinde aralıklı kırmızı — ölçüldü).
    await waitFor(() => expect(document.title).toBe("KeepChat Pro"));
    const cubuk = document.querySelector("progress") as HTMLProgressElement;
    expect([cubuk.max, cubuk.value]).toEqual([3, 1]);
    // L19 / Ç54 (3): görünen çubuk dolgu; yerel progress ekran okuyucu için.
    const dolum = (): number =>
      parseFloat(
        (document.querySelector(".ilerleme-dolum") as HTMLElement).style.width,
      );
    expect(dolum()).toBeCloseTo(33.33, 1);
    fireEvent.click(screen.getByText("Başla"));
    expect(cubuk.value).toBe(2);
    expect(dolum()).toBeCloseTo(66.67, 1);
  });

  it("ilerleme çubuğu yalnız kapatıldığı adımda gizlenir", async () => {
    tanim = {
      durum: 200,
      govde: yayin({
        adimlar: [
          { ...yayin().tanim.adimlar[0]!, ilerlemeGoster: false },
          ...yayin().tanim.adimlar.slice(1),
        ],
      }),
    };
    kur();
    await yuklendi();
    expect(document.querySelector("progress")).toBeNull();
    fireEvent.click(screen.getByText("Başla"));
    expect(document.querySelector("progress")).not.toBeNull();
  });

  it("çoklu seçimde yanıtları işaretler, düğmeyle gönderir ve sonraki adıma geçer", async () => {
    const temel = yayin();
    tanim = {
      durum: 200,
      govde: {
        ...temel,
        tanim: {
          ...temel.tanim,
          adimlar: temel.tanim.adimlar.map((adim) =>
            adim.id === "kiminle"
              ? kiminle(
                  [
                    {
                      tur: "metin",
                      metin: "Birden fazla seçenek seçebilirsin.",
                    },
                    { tur: "soru", secimTuru: "coklu", secenekler: SECENEKLER },
                  ],
                  "Seçimleri gönder",
                )
              : adim,
          ),
        },
      },
    };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    screen.getByText("Birden fazla seçenek seçebilirsin.");

    const devamDugmesi = screen.getByRole("button", {
      name: "Seçimleri gönder",
    });
    expect((devamDugmesi as HTMLButtonElement).disabled).toBe(true);
    fireEvent.click(screen.getByText("Ailemle"));
    fireEvent.click(screen.getByText("İş"));
    expect(
      screen
        .getByRole("button", { name: "Ailemle" })
        .getAttribute("aria-pressed"),
    ).toBe("true");
    expect((devamDugmesi as HTMLButtonElement).disabled).toBe(false);
    expect(gorunenBaslik()).toBe("Kiminle?");
    fireEvent.click(devamDugmesi);

    await waitFor(() => expect(gorunenBaslik()).toBe("Pro"));
    await waitFor(() => expect(ilerlemeler()).toHaveLength(4));
    expect(ilerlemeler()[2]).toEqual({
      sira: 1,
      adim: "kiminle",
      cevaplar: ["aile", "is"],
    });
  });

  it("serbest yanıtı yazıp isteğe bağlı Devam ile ayrı alan olarak gönderir", async () => {
    const temel = yayin();
    tanim = {
      durum: 200,
      govde: {
        ...temel,
        tanim: {
          ...temel.tanim,
          adimlar: temel.tanim.adimlar.map((adim) =>
            adim.id === "kiminle"
              ? kiminle([{ tur: "soru", secimTuru: "metin" }], "Devam et")
              : adim,
          ),
        },
      },
    };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    const alan = screen.getByRole("textbox", { name: "Yanıtınız" });
    fireEvent.change(alan, { target: { value: "Daha iyi uyumak istiyorum" } });
    expect(gorunenBaslik()).toBe("Kiminle?");
    fireEvent.click(screen.getByRole("button", { name: "Devam et" }));
    await waitFor(() => expect(gorunenBaslik()).toBe("Pro"));
    await waitFor(() => expect(ilerlemeler()).toHaveLength(4));
    expect(ilerlemeler()[2]).toEqual({
      sira: 1,
      adim: "kiminle",
      cevapMetni: "Daha iyi uyumak istiyorum",
    });
  });

  it("zorunlu serbest yanıtta boşken ilerlemez", async () => {
    const temel = yayin();
    tanim = {
      durum: 200,
      govde: {
        ...temel,
        tanim: {
          ...temel.tanim,
          adimlar: temel.tanim.adimlar.map((adim) =>
            adim.id === "kiminle"
              ? kiminle(
                  [{ tur: "soru", secimTuru: "metin", yanitZorunlu: true }],
                  "Devam",
                )
              : adim,
          ),
        },
      },
    };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    const devam = screen.getByRole("button", { name: "Devam" });
    expect((devam as HTMLButtonElement).disabled).toBe(true);
    fireEvent.click(devam);
    expect(gorunenBaslik()).toBe("Kiminle?");
    fireEvent.change(screen.getByRole("textbox", { name: "Yanıtınız" }), {
      target: { value: "Bir cevap" },
    });
    expect((devam as HTMLButtonElement).disabled).toBe(false);
  });

  // E1: e-posta adımı — adres ayrı uca, ilerlemeye (koşullar, serbest
  // yanıtlar) girmez; izin yalnız kutu işaretlenince.
  const epostaYayini = (
    blok: Partial<Extract<Blok, { tur: "soru" }>> = {},
  ): Yanit => {
    const temel = yayin();
    return {
      durum: 200,
      govde: {
        ...temel,
        tanim: {
          ...temel.tanim,
          adimlar: temel.tanim.adimlar.map((adim) =>
            adim.id === "kiminle"
              ? kiminle(
                  [{ tur: "soru", secimTuru: "eposta", ...blok }],
                  "Planımı gönder",
                )
              : adim,
          ),
        },
      },
    };
  };
  const epostalar = (): unknown[] =>
    istekler("POST", `/huni/ziyaretler/${Z}/eposta`).map((i) => i.govde);

  it("E1: e-posta izin kutusuyla ayrı uca gider, ilerlemede cevap yok", async () => {
    tanim = epostaYayini({
      yanitZorunlu: true,
      izinMetni: "İpuçları gönderilebilir.",
    });
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    fireEvent.change(
      screen.getByRole("textbox", { name: "E-posta adresiniz" }),
      { target: { value: "  ayse@ornek.com " } },
    );
    fireEvent.click(
      screen.getByRole("checkbox", { name: "İpuçları gönderilebilir." }),
    );
    fireEvent.click(screen.getByRole("button", { name: "Planımı gönder" }));
    await waitFor(() => expect(gorunenBaslik()).toBe("Pro"));
    await waitFor(() => expect(epostalar()).toHaveLength(1));
    expect(epostalar()[0]).toEqual({
      eposta: "ayse@ornek.com",
      pazarlamaIzni: true,
    });
    await waitFor(() => expect(ilerlemeler()).toHaveLength(3));
    expect(ilerlemeler()[1]).toEqual({ sira: 1, adim: "kiminle" });
  });

  it("E1: kutu işaretlenmezse izin false; kutu metni yoksa kutu yok", async () => {
    tanim = epostaYayini({ izinMetni: "İpuçları gönderilebilir." });
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    fireEvent.change(
      screen.getByRole("textbox", { name: "E-posta adresiniz" }),
      { target: { value: "ayse@ornek.com" } },
    );
    fireEvent.click(screen.getByRole("button", { name: "Planımı gönder" }));
    await waitFor(() => expect(epostalar()).toHaveLength(1));
    expect(epostalar()[0]).toEqual({
      eposta: "ayse@ornek.com",
      pazarlamaIzni: false,
    });
    cleanup();
    tanim = epostaYayini();
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    expect(screen.queryByRole("checkbox")).toBeNull();
  });

  it("E1: zorunluda boş ve bozuk adres geçmez; isteğe bağlıda boş geçer, bozuk geçmez", async () => {
    tanim = epostaYayini({ yanitZorunlu: true });
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    const devam = (): HTMLButtonElement =>
      screen.getByRole("button", { name: "Planımı gönder" });
    const alan = (): HTMLElement =>
      screen.getByRole("textbox", { name: "E-posta adresiniz" });
    expect(devam().disabled).toBe(true);
    for (const bozuk of ["ayse", "ayse@ornek", "ay se@ornek.com"]) {
      fireEvent.change(alan(), { target: { value: bozuk } });
      expect(devam().disabled).toBe(true);
      fireEvent.click(devam());
    }
    expect(gorunenBaslik()).toBe("Kiminle?");
    expect(epostalar()).toHaveLength(0);

    cleanup();
    tanim = epostaYayini();
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    expect(screen.getByText(/E-posta adresiniz \(isteğe bağlı\)/)).toBeTruthy();
    fireEvent.change(alan(), { target: { value: "ayse@" } });
    expect(devam().disabled).toBe(true);
    fireEvent.change(alan(), { target: { value: "" } });
    fireEvent.click(devam());
    await waitFor(() => expect(gorunenBaslik()).toBe("Pro"));
    expect(epostalar()).toHaveLength(0);
  });

  it("V5: ölçekte dokunulan sayı cevap kimliği olarak gider, sonraki adıma geçer", async () => {
    const temel = yayin();
    tanim = {
      durum: 200,
      govde: {
        ...temel,
        tanim: {
          ...temel.tanim,
          adimlar: temel.tanim.adimlar.map((adim) =>
            adim.id === "kiminle"
              ? {
                  id: "kiminle",
                  tur: "ekran" as const,
                  baslik: "Kiminle?",
                  bloklar: [
                    {
                      tur: "soru" as const,
                      secimTuru: "olcek" as const,
                      olcekSol: "Hiç",
                      olcekSag: "Tamamen",
                    },
                  ],
                }
              : adim,
          ),
        },
      },
    };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    fireEvent.click(screen.getByRole("button", { name: "4" }));
    await waitFor(() => expect(gorunenBaslik()).toBe("Pro"));
    await waitFor(() =>
      expect(ilerlemeler()).toContainEqual({
        sira: 1,
        adim: "kiminle",
        cevap: "4",
      }),
    );
  });

  it("yorum bloklu ekran alıntıyı gösterir ve düğmeyle sonraki adıma ilerler", async () => {
    const temel = yayin();
    tanim = {
      durum: 200,
      govde: {
        ...temel,
        tanim: {
          ...temel.tanim,
          adimlar: [
            temel.tanim.adimlar[0]!,
            temel.tanim.adimlar[1]!,
            {
              id: "yorum",
              tur: "ekran",
              baslik: "Kullanıcı deneyimi",
              bloklar: [
                {
                  tur: "yorum",
                  metin: "Günlük işlerim artık daha kolay.",
                  yazar: "Ayşe",
                  unvan: "KeepChat kullanıcısı",
                },
              ],
              dugme: "Devam et",
            },
            temel.tanim.adimlar[2]!,
          ],
        },
      },
    };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Kullanıcı deneyimi"));
    screen.getByText("Günlük işlerim artık daha kolay.");
    screen.getByText("Ayşe");
    fireEvent.click(screen.getByText("Devam et"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Pro"));
  });
});

describe("D7 — tema", () => {
  it("tema varsa değişkenler kökte; koyu yeşilde düğme yazısı siyah", async () => {
    // Bağımsız: #16a34a parlaklığı ≈ 0,27 → siyahta 6,4, beyazda 3,3.
    tanim = {
      durum: 200,
      govde: yayin({
        tema: {
          anaRenk: "#16a34a",
          arkaPlan: "#0b1220",
          metinRengi: "#f8fafc",
          yaziTipi: "mono",
          kose: 20,
        },
      }),
    };
    kur();
    await yuklendi();
    const s = document.documentElement.style;
    await waitFor(() =>
      expect(
        ["--ana", "--ana-yazi", "--arka", "--metin", "--kose"].map((d) =>
          s.getPropertyValue(d),
        ),
      ).toEqual(["#16a34a", "#000000", "#0b1220", "#f8fafc", "20px"]),
    );
    expect(s.getPropertyValue("--yazi-tipi")).toContain("ui-monospace");
  });
  it("G3: zemin görseli çekirdeğin medya adresinden kökte", async () => {
    tanim = {
      durum: 200,
      govde: yayin({
        tema: {
          anaRenk: "#2563eb",
          arkaPlan: "#ffffff",
          metinRengi: "#111827",
          yaziTipi: "sistem",
          kose: 10,
          zemin: { tur: "gorsel", gorsel: G, karartma: 70 },
        },
      }),
    };
    kur();
    await yuklendi();
    await waitFor(() =>
      expect(
        document.documentElement.style.getPropertyValue("--zemin"),
      ).toContain(`url("${KOK}/medya/${G}.webp")`),
    );
  });
  it("Mobil görünüm 5: tema sonuç sayfası için oturum belleğine kaydedilir", async () => {
    tanim = {
      durum: 200,
      govde: yayin({
        tema: {
          anaRenk: "#16a34a",
          arkaPlan: "#0b1220",
          metinRengi: "#f8fafc",
          yaziTipi: "mono",
          kose: 20,
        },
      }),
    };
    kur();
    await yuklendi();
    await waitFor(() =>
      expect(
        JSON.parse(sessionStorage.getItem("huni-tema") ?? "{}"),
      ).toMatchObject({
        anaRenk: "#16a34a",
      }),
    );
  });
  it("tema yoksa kökte değişken yazılmaz (huni.css varsayılanı)", async () => {
    kur();
    await yuklendi();
    // Olumsuz iddia: önce effect'in koştuğunu gör (başlık aynı effect'te).
    await waitFor(() => expect(document.title).toBe("KeepChat Pro"));
    expect(document.documentElement.style.getPropertyValue("--ana")).toBe("");
  });

  it("V4: hareket düzeyi body.huni'de data-hareket; tema yoksa sakin", async () => {
    kur();
    await yuklendi();
    await waitFor(() => expect(document.body.dataset["hareket"]).toBe("sakin"));
    cleanup();
    tanim = {
      durum: 200,
      govde: yayin({
        tema: {
          anaRenk: "#f97316",
          arkaPlan: "#111111",
          metinRengi: "#f5f5f5",
          yaziTipi: "sistem",
          kose: 12,
          hareket: "canli",
        },
      }),
    };
    kur();
    await yuklendi();
    await waitFor(() => expect(document.body.dataset["hareket"]).toBe("canli"));
  });
});

describe("Mobil görünüm 4 — yükleme adımı kendiliğinden geçer", () => {
  const yuklemeli = (): Yayin => {
    const y = yayin();
    y.tanim.adimlar.splice(2, 0, {
      id: "hazirlik",
      tur: "yukleme",
      baslik: "Planın hazırlanıyor",
      sure: 2,
    });
    return y;
  };
  const gorunur = (metin: string): boolean =>
    screen.getByText(metin).closest("[hidden]") === null;
  it("süre dolunca sonraki adım, ilerleme bildirilir", async () => {
    tanim = { durum: 200, govde: yuklemeli() };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    expect(gorunur("Planın hazırlanıyor")).toBe(true);
    await waitFor(() => expect(gorunur("Pro")).toBe(true), { timeout: 3500 });
    await waitFor(() =>
      expect(ilerlemeler()).toContainEqual(
        expect.objectContaining({ adim: "paywall" }),
      ),
    );
  }, 8000);
  it("geri dönülürse zamanlayıcı iptal: süre geçince yine önceki adımda", async () => {
    tanim = { durum: 200, govde: yuklemeli() };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    expect(gorunur("Planın hazırlanıyor")).toBe(true);
    window.dispatchEvent(new PopStateEvent("popstate", { state: { adim: 1 } }));
    await waitFor(() => expect(gorunur("Kiminle?")).toBe(true));
    await new Promise((r) => setTimeout(r, 2600));
    expect(gorunur("Kiminle?")).toBe(true);
  }, 8000);
  it("ekran çekiminde (ekran=1) geçmez — AI yükleme ekranını görür", async () => {
    tanim = { durum: 200, govde: yuklemeli() };
    kur("?huni=fitness&onizleme=abc&ekran=1&adim=hazirlik");
    await yuklendi();
    expect(gorunur("Planın hazırlanıyor")).toBe(true);
    await new Promise((r) => setTimeout(r, 2600));
    expect(gorunur("Planın hazırlanıyor")).toBe(true);
  }, 8000);
});

describe("D9 · D10 · D11 · D12 — ilerleme", () => {
  it("ziyaret bir kez, huni + sürümle; ilk adım bildirilir", async () => {
    kur("?utm_source=meta");
    await yuklendi();
    await waitFor(() => expect(ilerlemeler()).toHaveLength(1));
    const z = istekler("POST", "/huni/ziyaretler");
    expect(z).toHaveLength(1);
    expect(z[0]?.govde).toMatchObject({
      app: "keepchat",
      huni: "varsayilan",
      surum: 4,
      utm_source: "meta",
    });
    expect(ilerlemeler()).toEqual([{ sira: 0, adim: "karsilama" }]);
  });
  it("seçenek önce cevabı, sonra sonraki adımı bildirir", async () => {
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("İş"));
    await waitFor(() => expect(ilerlemeler()).toHaveLength(4));
    expect(ilerlemeler()).toEqual([
      { sira: 0, adim: "karsilama" },
      { sira: 1, adim: "kiminle" },
      { sira: 1, adim: "kiminle", cevap: "is" },
      { sira: 2, adim: "paywall" },
    ]);
    expect(istekler("POST", "/huni/ziyaretler")).toHaveLength(1);
  });
  it("her adım geçmişe yazılır; geri tuşu önceki adıma döner", async () => {
    kur();
    await yuklendi();
    await waitFor(() => expect(history.state).toEqual({ adim: 0 }));
    fireEvent.click(screen.getByText("Başla"));
    expect(history.state).toEqual({ adim: 1 });
    expect(gorunenBaslik()).toBe("Kiminle?");
    fireEvent(window, new PopStateEvent("popstate", { state: { adim: 0 } }));
    await waitFor(() => expect(gorunenBaslik()).toBe("Merhaba"));
  });
  it("paywall fiyatı yanıttaki Stripe fiyatından; yoksa satır yok (Ç19/Ç21)", async () => {
    tanim = {
      durum: 200,
      govde: {
        ...yayin(),
        fiyatlar: {
          monthly: { tutar: 9.99, paraBirimi: "EUR", aralik: "month" },
        },
      },
    };
    kur();
    await yuklendi();
    expect(
      document.querySelector('[data-adim="paywall"] p.fiyat')?.textContent,
    ).toBe("€9,99\u00a0/\u00a0ay");
    // M1c: fiyat ülkeye (IP) göre seçildi → DB-IP atfı (CC BY 4.0), fiyatsızda yok.
    const atif = () =>
      document.querySelector<HTMLAnchorElement>(
        '[data-adim="paywall"] a[href="https://db-ip.com"]',
      );
    expect(atif()?.textContent).toBe("IP Geolocation by DB-IP");
    cleanup();
    tanim = { durum: 200, govde: yayin() };
    kur();
    await yuklendi();
    expect(document.querySelector("p.fiyat")).toBeNull();
    expect(atif()).toBeNull();
  });
  it("aynı sekmede yeniden yükleme yeni ziyaret açmaz; başka sürüm açar (Ç34)", async () => {
    kur();
    await yuklendi();
    await waitFor(() =>
      expect(istekler("POST", "/huni/ziyaretler")).toHaveLength(1),
    );
    cleanup();
    kur(); // sayfa yenilendi
    await yuklendi();
    await waitFor(() => expect(ilerlemeler().length).toBeGreaterThan(1));
    expect(istekler("POST", "/huni/ziyaretler")).toHaveLength(1);
    cleanup();
    tanim = { durum: 200, govde: { ...yayin(), surum: 5 } };
    kur();
    await yuklendi();
    await waitFor(() =>
      expect(istekler("POST", "/huni/ziyaretler")).toHaveLength(2),
    );
  });

  it("sekme deposu yoksa yine ziyaret açılır (Ç34)", async () => {
    const asil = Object.getOwnPropertyDescriptor(window, "sessionStorage");
    Object.defineProperty(window, "sessionStorage", {
      configurable: true,
      get: () => {
        throw new DOMException("engelli", "SecurityError");
      },
    });
    try {
      kur();
      await yuklendi();
      await waitFor(() =>
        expect(istekler("POST", "/huni/ziyaretler")).toHaveLength(1),
      );
    } finally {
      if (asil) Object.defineProperty(window, "sessionStorage", asil);
    }
  });

  it("logo varsa sayfanın başında, yoksa hiç yok (Ç61)", async () => {
    tanim = {
      durum: 200,
      govde: { ...yayin(), tanim: { ...yayin().tanim, logo: G } },
    };
    kur();
    await yuklendi();
    expect(document.querySelector("img.logo")?.getAttribute("src")).toBe(
      `${KOK}/medya/${G}.webp`,
    );
    cleanup();
    tanim = { durum: 200, govde: yayin() };
    kur();
    await yuklendi();
    expect(document.querySelector("img.logo")).toBeNull();
  });
  it("Geri düğmesi ilk adımda yok; sonrakinde önceki adıma döner (Ç61)", async () => {
    kur();
    await yuklendi();
    const geri = () => screen.queryByRole("button", { name: "Geri" });
    expect(geri()).toBeNull();
    // Geri sütunu ilk adımda da üst çubukta yer tutar: ikinci adımda
    // ilerleme çubuğu ve içerik kaymaz.
    expect(
      document.querySelector(".ust-cubuk > .ilerleme > progress"),
    ).not.toBeNull();
    // Mobil görünüm: ana düğme adımın .eylem alanında (CSS onu .adimlar
    // altında alta yapıştırır).
    expect(
      document.querySelector(
        '.adimlar > [data-sira="0"] > section > .eylem > button.ana',
      ),
    ).not.toBeNull();
    // İlk kaydı effect yazar; beklenmezse geçmiş null kalır (aralıklı, §33).
    await waitFor(() => expect(history.state).toEqual({ adim: 0 }));
    fireEvent.click(screen.getByText("Başla"));
    expect(gorunenBaslik()).toBe("Kiminle?");
    expect(
      document.querySelector(".ust-cubuk > button.geri + .ilerleme"),
    ).not.toBeNull();
    // Tarayıcının geri tuşuyla aynı yol: geçmişten bir adım geri (dallanmada
    // da gelinen adıma döner).
    fireEvent.click(geri()!);
    await waitFor(() => expect(gorunenBaslik()).toBe("Merhaba"));
    expect(history.state).toEqual({ adim: 0 });
    expect(geri()).toBeNull();
  });
  it("adım değişince başlığa odak; ilk adımda odak çalınmaz", async () => {
    kur();
    await yuklendi();
    // Olumsuz iddia: önce ilk adımın effect'i koşmuş olmalı (bildirimi aynı yerde).
    await waitFor(() => expect(ilerlemeler()).toHaveLength(1));
    expect(document.activeElement?.tagName).not.toBe("H1");
    fireEvent.click(screen.getByText("Başla"));
    await waitFor(() =>
      expect(document.activeElement?.textContent).toBe("Kiminle?"),
    );
  });
  it("ziyaret kaydı başarısızsa huni ve ödeme yine çalışır", async () => {
    ziyaret = { durum: 500 };
    const git = vi
      .spyOn(window.location, "assign")
      .mockImplementation(() => {});
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    fireEvent.click(screen.getByText("Abone ol"));
    await waitFor(() => expect(git).toHaveBeenCalled());
    expect(ilerlemeler()).toEqual([]);
    expect(istekler("POST", "/checkout/sessions")[0]?.govde).toEqual({
      app: "keepchat",
      plan: "monthly",
    });
  });
});

describe("M2a — plan seçici", () => {
  it("varsayılan seçili, Önerilen etiketi; seçim değişince koşul değişir, ödemeye seçili plan gider", async () => {
    const temel = yayin();
    const adimlar = temel.tanim.adimlar.map((a) =>
      a.tur === "paywall"
        ? {
            ...a,
            plan: "monthly",
            planlar: ["yearly", "monthly"],
            onerilen: "yearly",
          }
        : a,
    );
    tanim = {
      durum: 200,
      govde: {
        ...temel,
        tanim: { ...temel.tanim, adimlar },
        fiyatlar: {
          monthly: {
            tutar: 9.99,
            paraBirimi: "EUR",
            aralik: "month",
            ad: "Aylık",
            teklif: { tur: "deneme", gun: 7 },
          },
          yearly: {
            tutar: 59.99,
            paraBirimi: "EUR",
            aralik: "year",
            ad: "Yıllık",
          },
        },
      },
    };
    ziyaret = { durum: 500 };
    const git = vi
      .spyOn(window.location, "assign")
      .mockImplementation(() => {});
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    const kart = (plan: string) =>
      document.querySelector<HTMLButtonElement>(
        `[data-adim="paywall"] [role="radio"][data-plan="${plan}"]`,
      )!;
    const kosul = () =>
      document.querySelector('[data-adim="paywall"] p.kosul')?.textContent;
    // Sıra tanımdaki gibi; varsayılan plan seçili; önerilen yalnız yıllıkta.
    expect(
      [
        ...document.querySelectorAll('[data-adim="paywall"] [role="radio"]'),
      ].map((b) => b.getAttribute("data-plan")),
    ).toEqual(["yearly", "monthly"]);
    expect(kart("monthly").getAttribute("aria-checked")).toBe("true");
    expect(kart("yearly").textContent).toContain("Önerilen");
    expect(kart("monthly").textContent).not.toContain("Önerilen");
    expect(kart("yearly").textContent).toContain("Yıllık");
    // Elle (fiyat.ts biçimi): aylık deneme koşulu.
    expect(kosul()).toBe(
      "Bugün ödeme yok. 7\u00a0gün sonra ilk ödeme alınır; abonelik her\u00a0ay €9,99 ile yenilenir.",
    );
    fireEvent.click(kart("yearly"));
    expect(kart("yearly").getAttribute("aria-checked")).toBe("true");
    expect(kart("monthly").getAttribute("aria-checked")).toBe("false");
    expect(kosul()).toBe(
      "Bugün €59,99 ödenir; abonelik her\u00a0yıl €59,99 ile yenilenir.",
    );
    fireEvent.click(screen.getByText("Abone ol"));
    await waitFor(() => expect(git).toHaveBeenCalled());
    expect(istekler("POST", "/checkout/sessions")[0]?.govde).toEqual({
      app: "keepchat",
      plan: "yearly",
    });
  });
});

describe("M3a — downsell", () => {
  const downselli = (): Yayin => {
    const t = yayin();
    return {
      ...t,
      tanim: {
        ...t.tanim,
        adimlar: t.tanim.adimlar.map((a) =>
          a.tur === "paywall"
            ? {
                ...a,
                downsell: {
                  plan: "yearly",
                  baslik: "Yıllıkla daha uygun",
                  dugme: "Yıllığı başlat",
                },
              }
            : a,
        ),
      },
      fiyatlar: {
        monthly: { tutar: 9.99, paraBirimi: "EUR", aralik: "month" },
        yearly: { tutar: 59.99, paraBirimi: "EUR", aralik: "year" },
      },
    };
  };
  const paywallda = async () => {
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Pro"));
  };
  const geri = () =>
    fireEvent(window, new PopStateEvent("popstate", { state: { adim: 1 } }));
  beforeEach(() => sessionStorage.clear());
  it("paywall'da ilk geri: pencere açılır, paywall'da kalınır; ret → ikinci geri önceki adıma döner, pencere bir daha yok", async () => {
    tanim = { durum: 200, govde: downselli() };
    await paywallda();
    geri();
    const pencere = await screen.findByRole("dialog", { name: "Teklif" });
    expect(gorunenBaslik()).toBe("Pro");
    // Fiyat ve koşul Stripe fiyatından (elle, fiyat.ts biçimi).
    expect(pencere.textContent).toContain("€59,99\u00a0/\u00a0yıl");
    expect(pencere.textContent).toContain(
      "Bugün €59,99 ödenir; abonelik her\u00a0yıl €59,99 ile yenilenir.",
    );
    fireEvent.click(screen.getByText("Hayır, teşekkürler"));
    await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull());
    geri();
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    expect(screen.queryByRole("dialog")).toBeNull();
  });
  it("kabul: downsell planıyla ödeme açılır", async () => {
    tanim = { durum: 200, govde: downselli() };
    ziyaret = { durum: 500 };
    const git = vi
      .spyOn(window.location, "assign")
      .mockImplementation(() => {});
    await paywallda();
    geri();
    fireEvent.click(await screen.findByText("Yıllığı başlat"));
    await waitFor(() => expect(git).toHaveBeenCalled());
    expect(istekler("POST", "/checkout/sessions")[0]?.govde).toEqual({
      app: "keepchat",
      plan: "yearly",
    });
  });
  it("ödeme panelini ödemeden kapatmak pencereyi açar", async () => {
    vi.mocked(stripeYukle).mockResolvedValue(() => ({
      handleNextAction: async () => ({}),
      createEmbeddedCheckoutPage: async () => ({
        mount: () => {},
        destroy: () => {},
      }),
    }));
    odeme = { durum: 201, govde: { clientSecret: "s", anahtar: "pk" } };
    tanim = { durum: 200, govde: downselli() };
    await paywallda();
    fireEvent.click(screen.getByText("Abone ol"));
    await screen.findByRole("dialog", { name: "Ödeme" });
    fireEvent.click(screen.getByRole("button", { name: "Kapat" }));
    expect(await screen.findByRole("dialog", { name: "Teklif" })).toBeTruthy();
  });
  it("depo kapalıyken (gizli sekme) de bir kez: ret → paywall'a dönülüp yeniden geri → pencere yok", async () => {
    // happy-dom Storage.prototype'u kullanmıyor (ilk deneme sahte hatayı hiç
    // tetiklemedi — kırma K6 yeşil kaldı); erişimin kendisi hata versin.
    vi.spyOn(window, "sessionStorage", "get").mockImplementation(() => {
      throw new Error("depo kapalı");
    });
    tanim = { durum: 200, govde: downselli() };
    await paywallda();
    geri();
    fireEvent.click(await screen.findByText("Hayır, teşekkürler"));
    await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull());
    geri();
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    fireEvent.click(screen.getByText("Ailemle"));
    await waitFor(() => expect(gorunenBaslik()).toBe("Pro"));
    geri();
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    expect(screen.queryByRole("dialog")).toBeNull();
  });
  it("aynı sekmede daha önce gösterildiyse (yenileme) açılmaz; downsell'siz paywall'da geri normal", async () => {
    sessionStorage.setItem("downsell:keepchat:varsayilan:paywall", "1");
    tanim = { durum: 200, govde: downselli() };
    await paywallda();
    geri();
    await waitFor(() => expect(gorunenBaslik()).toBe("Kiminle?"));
    expect(screen.queryByRole("dialog")).toBeNull();
  });
});

describe("M2d — gömülü ödeme", () => {
  it("Stripe.js yüklendiyse gomulu istenir, form sayfada açılır; kapatınca yok edilir, paywall'da kalınır", async () => {
    const takilan: HTMLElement[] = [];
    const yokEt = vi.fn();
    const sirlar: string[] = [];
    const anahtarlar: string[] = [];
    vi.mocked(stripeYukle).mockResolvedValue((anahtar: string) => {
      anahtarlar.push(anahtar);
      return {
        handleNextAction: async () => ({}),
        createEmbeddedCheckoutPage: async ({ fetchClientSecret }) => {
          sirlar.push(await fetchClientSecret());
          return { mount: (k: HTMLElement) => takilan.push(k), destroy: yokEt };
        },
      };
    });
    odeme = {
      durum: 201,
      govde: { clientSecret: "cs_sir", anahtar: "pk_test_x" },
    };
    ziyaret = { durum: 500 };
    const git = vi.spyOn(window.location, "assign");
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    fireEvent.click(screen.getByText("Abone ol"));
    const panel = await screen.findByRole("dialog", { name: "Ödeme" });
    await waitFor(() => expect(takilan).toHaveLength(1));
    expect(panel.contains(takilan[0]!)).toBe(true);
    expect([anahtarlar, sirlar]).toEqual([["pk_test_x"], ["cs_sir"]]);
    expect(istekler("POST", "/checkout/sessions")[0]?.govde).toEqual({
      app: "keepchat",
      plan: "monthly",
      gomulu: true,
    });
    expect(git).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "Kapat" }));
    await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull());
    expect(yokEt).toHaveBeenCalledTimes(1);
    expect(gorunenBaslik()).toBe("Pro");
  });
  it("Escape paneli kapatır", async () => {
    vi.mocked(stripeYukle).mockResolvedValue(() => ({
      handleNextAction: async () => ({}),
      createEmbeddedCheckoutPage: async () => ({
        mount: () => {},
        destroy: () => {},
      }),
    }));
    odeme = { durum: 201, govde: { clientSecret: "s", anahtar: "pk" } };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    fireEvent.click(screen.getByText("Abone ol"));
    await screen.findByRole("dialog", { name: "Ödeme" });
    // §33: efekt (dinleyici + odak) boyamadan sonra koşar — önce odak kapatta.
    await waitFor(() =>
      expect(document.activeElement?.getAttribute("aria-label")).toBe("Kapat"),
    );
    fireEvent.keyDown(window, { key: "Escape" });
    await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull());
  });
  it("Stripe.js yüklenemezse gomulu istenmez, barındırılan sayfaya gidilir", async () => {
    ziyaret = { durum: 500 };
    const git = vi
      .spyOn(window.location, "assign")
      .mockImplementation(() => {});
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    fireEvent.click(screen.getByText("Abone ol"));
    await waitFor(() =>
      expect(git).toHaveBeenCalledWith("https://odeme.ornek/x"),
    );
    expect(istekler("POST", "/checkout/sessions")[0]?.govde).not.toHaveProperty(
      "gomulu",
    );
    expect(screen.queryByRole("dialog")).toBeNull();
  });
});

describe("M2c — cevaba göre paywall", () => {
  // kiminle ─aile→ pw-yillik (sonda) · ─is→ sıradaki pw-aylik (önde).
  const iki = (): Yayin => {
    const t = yayin();
    const [karsilama, kiminle] = t.tanim.adimlar;
    return {
      ...t,
      tanim: {
        ...t.tanim,
        adimlar: [
          karsilama!,
          {
            ...kiminle!,
            bloklar: [
              {
                tur: "soru",
                secenekler: [
                  { id: "aile", metin: "Ailemle", hedef: "pw-yillik" },
                  { id: "is", metin: "İş" },
                ],
              },
            ],
          } as Yayin["tanim"]["adimlar"][number],
          {
            id: "pw-aylik",
            tur: "paywall",
            baslik: "Aylık plan",
            metin: "",
            dugme: "Aylık al",
            plan: "monthly",
          },
          {
            id: "pw-yillik",
            tur: "paywall",
            baslik: "Yıllık plan",
            metin: "",
            dugme: "Yıllık al",
            plan: "yearly",
          },
        ],
      },
    };
  };
  const dolu = () =>
    (document.querySelector("progress") as HTMLProgressElement | null)?.value;
  it.each([
    ["Ailemle", "Yıllık plan", "Yıllık al", "yearly"],
    ["İş", "Aylık plan", "Aylık al", "monthly"],
  ])(
    "%s → %s; o paywall'ın planı ödenir, çubuk dolu",
    async (secenek, baslik, dugme, plan) => {
      tanim = { durum: 200, govde: iki() };
      ziyaret = { durum: 500 };
      const git = vi
        .spyOn(window.location, "assign")
        .mockImplementation(() => {});
      kur();
      await yuklendi();
      fireEvent.click(screen.getByText("Başla"));
      fireEvent.click(screen.getByText(secenek));
      await waitFor(() => expect(gorunenBaslik()).toBe(baslik));
      // Öndeki paywall'da (3/4) da çubuk sonda: her paywall huninin sonu.
      expect(dolu()).toBe(4);
      fireEvent.click(screen.getByText(dugme));
      await waitFor(() => expect(git).toHaveBeenCalled());
      expect(istekler("POST", "/checkout/sessions")[0]?.govde).toEqual({
        app: "keepchat",
        plan,
      });
    },
  );
});

describe("M2b — sayaç, şart onayı, güven rozetleri", () => {
  const paywallli = (
    ek: Record<string, unknown>,
    fiyatlar: Yayin["fiyatlar"],
  ) => {
    const temel = yayin();
    return {
      ...temel,
      tanim: {
        ...temel.tanim,
        adimlar: temel.tanim.adimlar.map((a) =>
          a.tur === "paywall" ? { ...a, ...ek } : a,
        ),
      },
      fiyatlar,
    };
  };
  const paywallaGit = async () => {
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
  };
  it("sayaç gerçek bitişe sayar; süre dolunca sayaç VE teklif paywall'dan kalkar", async () => {
    tanim = {
      durum: 200,
      govde: paywallli(
        {},
        {
          monthly: {
            tutar: 9.99,
            paraBirimi: "EUR",
            aralik: "month",
            teklif: {
              tur: "deneme",
              gun: 7,
              bitis: new Date(Date.now() + 2000).toISOString(),
            },
          },
        },
      ),
    };
    await paywallaGit();
    const sayac = () =>
      document.querySelector('[data-adim="paywall"] p.sayac')?.textContent;
    const fiyat = () =>
      document.querySelector('[data-adim="paywall"] p.fiyat')?.textContent;
    expect(sayac()).toMatch(/^Teklifin bitmesine 00:00:0[12]$/);
    expect(fiyat()).toContain("ücretsiz");
    await waitFor(() => expect(sayac()).toBeUndefined(), { timeout: 4000 });
    expect(fiyat()).toBe("€9,99\u00a0/\u00a0ay");
  });
  it("şart onayı işaretlenmeden düğme kapalı; işaretlenince ödeme sartOnayi ile; rozetler", async () => {
    tanim = {
      durum: 200,
      govde: paywallli(
        {
          sartOnayi: true,
          sartlar: "https://ornek.com/sartlar",
          guvenceler: ["guvenli-odeme", "kart-saklanmaz"],
        },
        { monthly: { tutar: 9.99, paraBirimi: "EUR", aralik: "month" } },
      ),
    };
    ziyaret = { durum: 500 };
    const git = vi
      .spyOn(window.location, "assign")
      .mockImplementation(() => {});
    await paywallaGit();
    const dugme = screen.getByText("Abone ol") as HTMLButtonElement;
    expect(dugme.disabled).toBe(true);
    expect(
      [
        ...document.querySelectorAll('[data-adim="paywall"] ul.guvenceler li'),
      ].map((l) => l.textContent),
    ).toEqual(["🔒 Güvenli ödeme · Stripe", "💳 Kart bilgin bizde saklanmaz"]);
    fireEvent.click(
      document.querySelector<HTMLInputElement>(
        '[data-adim="paywall"] .sart-onayi input',
      )!,
    );
    expect(dugme.disabled).toBe(false);
    fireEvent.click(dugme);
    await waitFor(() => expect(git).toHaveBeenCalled());
    expect(istekler("POST", "/checkout/sessions")[0]?.govde).toEqual({
      app: "keepchat",
      plan: "monthly",
      sartOnayi: true,
    });
  });
});

describe("M1c — ülke testi ödemeye de gider", () => {
  it("?ulke=TR ödeme gövdesinde: paywall'daki para birimiyle aynı seçim", async () => {
    ziyaret = { durum: 500 };
    const git = vi
      .spyOn(window.location, "assign")
      .mockImplementation(() => {});
    kur("?ulke=TR");
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    fireEvent.click(screen.getByText("Abone ol"));
    await waitFor(() => expect(git).toHaveBeenCalled());
    expect(istekler("POST", "/checkout/sessions")[0]?.govde).toEqual({
      app: "keepchat",
      plan: "monthly",
      ulke: "TR",
    });
  });
});

describe("H5 — dallanma (yalnız ileri)", () => {
  // giris → kiminle ─aile→ aile-adimi ─(hedef)→ paywall
  //                 └─iş──→ is-adimi ─────────→ paywall
  const dallar = (): Yanit => ({
    durum: 200,
    govde: yayin({
      adimlar: [
        {
          id: "giris",
          tur: "ekran",
          baslik: "Merhaba",
          bloklar: [],
          dugme: "Başla",
        },
        {
          id: "kiminle",
          tur: "ekran",
          baslik: "Kiminle?",
          bloklar: [
            {
              tur: "soru",
              secenekler: [
                { id: "aile", metin: "Ailemle", hedef: "aile-adimi" },
                { id: "is", metin: "İş", hedef: "is-adimi" },
              ],
            },
          ],
        },
        {
          id: "aile-adimi",
          tur: "ekran",
          baslik: "Aile",
          bloklar: [],
          dugme: "Devam",
          hedef: "paywall",
        },
        {
          id: "is-adimi",
          tur: "ekran",
          baslik: "İş için",
          bloklar: [],
          dugme: "Devam",
        },
        {
          id: "paywall",
          tur: "paywall",
          baslik: "Pro",
          metin: "",
          dugme: "Abone ol",
          plan: "monthly",
        },
      ],
    }),
  });
  const devam = (): void => {
    const d = document.querySelector<HTMLButtonElement>(
      "[data-sira]:not([hidden]) button.ana",
    );
    if (d) fireEvent.click(d);
  };

  it("seçenek hedefe atlar; dalın sonu ortak adıma atlar (öteki dal görülmez)", async () => {
    tanim = dallar();
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    expect(gorunenBaslik()).toBe("Aile");
    devam();
    expect(gorunenBaslik()).toBe("Pro");
    expect(history.state).toEqual({ adim: 4 });
    const cubuk = document.querySelector("progress") as HTMLProgressElement;
    expect([cubuk.max, cubuk.value]).toEqual([5, 5]);
    await waitFor(() => expect(ilerlemeler()).toHaveLength(5));
    expect(ilerlemeler()).toEqual([
      { sira: 0, adim: "giris" },
      { sira: 1, adim: "kiminle" },
      { sira: 1, adim: "kiminle", cevap: "aile" },
      { sira: 2, adim: "aile-adimi" },
      { sira: 4, adim: "paywall" },
    ]);
  });

  it("T1: ekran adımı bloklarını çizer, düğmesi hedefe atlar, odak ekranın başlığında", async () => {
    tanim = {
      durum: 200,
      govde: yayin({
        adimlar: [
          {
            id: "giris",
            tur: "ekran",
            baslik: "Merhaba",
            bloklar: [],
            dugme: "Başla",
          },
          {
            id: "neden",
            tur: "ekran",
            baslik: "Neden KeepChat?",
            bloklar: [
              { tur: "istatistik", deger: "%87", metin: "düzenli kullanıyor" },
              { tur: "baslik", metin: "Ara başlık" },
              { tur: "maddeler", maddeler: ["Yedekleme"] },
            ],
            dugme: "İleri",
            hedef: "paywall",
          },
          {
            id: "atlanan",
            tur: "ekran",
            baslik: "Atlanan",
            bloklar: [],
            dugme: "Devam",
          },
          {
            id: "paywall",
            tur: "paywall",
            baslik: "Pro",
            metin: "",
            dugme: "Abone ol",
            plan: "monthly",
          },
        ],
      }),
    };
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    expect(gorunenBaslik()).toBe("Neden KeepChat?");
    await waitFor(() =>
      expect(document.activeElement?.textContent).toBe("Neden KeepChat?"),
    );
    screen.getByText("%87");
    screen.getByText("Yedekleme");
    fireEvent.click(screen.getByText("İleri"));
    expect(gorunenBaslik()).toBe("Pro");
    expect(history.state).toEqual({ adim: 3 });
  });

  // T3a: kural tabanlı yönlendirme — ilk tutan kural kazanır, cevap
  // verildiği anda hesaba girer; tutmazsa eski davranış.
  const yonlendirmeli = (): Yanit => ({
    durum: 200,
    govde: yayin({
      adimlar: [
        {
          id: "kiminle",
          tur: "ekran",
          baslik: "Kiminle?",
          bloklar: [
            {
              tur: "soru",
              secenekler: [
                { id: "aile", metin: "Ailemle" },
                { id: "is", metin: "İş" },
              ],
            },
          ],
          yonlendirme: [
            {
              kosullar: [
                { ozellik: "cevap.kiminle", islem: "icerir", deger: "aile" },
              ],
              hedef: "aile-ekrani",
            },
            {
              kosullar: [
                { ozellik: "utm.source", islem: "esit", deger: "meta" },
              ],
              hedef: "paywall",
            },
          ],
        },
        {
          id: "is-ekrani",
          tur: "ekran",
          baslik: "İş için",
          bloklar: [],
          dugme: "Devam",
        },
        {
          id: "aile-ekrani",
          tur: "ekran",
          baslik: "Aile",
          bloklar: [],
          dugme: "Devam",
        },
        {
          id: "paywall",
          tur: "paywall",
          baslik: "Pro",
          metin: "",
          dugme: "Abone ol",
          plan: "monthly",
        },
      ],
    }),
  });

  it("T3a: cevaba göre kural hedefi (ilk tutan kural)", async () => {
    tanim = yonlendirmeli();
    kur("?utm_source=meta");
    await screen.findByText("Kiminle?");
    fireEvent.click(screen.getByText("Ailemle"));
    expect(gorunenBaslik()).toBe("Aile");
  });

  // M4a (A17): içerik deneyi — çekirdeğin tanımla verdiği varyant koşulda
  // deney.<ad> olur ve ziyaret kaydına aynı atamayla gider.
  const deneyli = (varyant: string): Yanit => {
    const y = yayin();
    const [karsilama, ...kalan] = y.tanim.adimlar;
    return {
      durum: 200,
      govde: {
        ...y,
        deneyler: { baslik: varyant },
        tanim: {
          ...y.tanim,
          adimlar: [
            {
              ...(karsilama as Extract<TanimAdimi, { tur: "ekran" }>),
              yonlendirme: [
                {
                  kosullar: [
                    { ozellik: "deney.baslik", islem: "esit", deger: "b" },
                  ],
                  hedef: "paywall",
                },
              ],
            },
            ...kalan,
          ],
        },
      },
    };
  };
  it("M4a: deney varyantı koşulda deney.<ad>; ziyarete aynı atama gider", async () => {
    tanim = deneyli("b");
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    expect(gorunenBaslik()).toBe("Pro");
    await waitFor(() =>
      expect(istekler("POST", "/huni/ziyaretler")).toHaveLength(1),
    );
    expect(istekler("POST", "/huni/ziyaretler")[0]?.govde).toMatchObject({
      huni: "varsayilan",
      surum: 4,
      deneyler: { baslik: "b" },
    });
  });
  it("M4a: öteki varyant kuralı tutmaz, sıradaki adım", async () => {
    tanim = deneyli("a");
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    expect(gorunenBaslik()).toBe("Kiminle?");
  });
  it("M4a: atamasız tanımda ziyaret gövdesinde deneyler yok", async () => {
    kur();
    await yuklendi();
    await waitFor(() =>
      expect(istekler("POST", "/huni/ziyaretler")).toHaveLength(1),
    );
    expect(istekler("POST", "/huni/ziyaretler")[0]?.govde).not.toHaveProperty(
      "deneyler",
    );
  });

  // T3b: koşulu tutmayan ekran atlanır (ardışık iki ekran da), geçmişe
  // girmez — geri tuşu gösterilen son adıma döner.
  const gosterli = (): Yanit => {
    const aile = {
      kosullar: [
        { ozellik: "cevap.kiminle", islem: "icerir" as const, deger: "aile" },
      ],
    };
    return {
      durum: 200,
      govde: yayin({
        adimlar: [
          {
            id: "kiminle",
            tur: "ekran",
            baslik: "Kiminle?",
            bloklar: [
              {
                tur: "soru",
                secenekler: [
                  { id: "aile", metin: "Ailemle" },
                  { id: "is", metin: "İş" },
                ],
              },
            ],
          },
          {
            id: "aile-1",
            tur: "ekran",
            baslik: "Aile 1",
            bloklar: [],
            dugme: "Devam",
            goster: aile,
          },
          {
            id: "aile-2",
            tur: "ekran",
            baslik: "Aile 2",
            bloklar: [],
            dugme: "Devam",
            goster: aile,
          },
          {
            id: "son",
            tur: "ekran",
            baslik: "Son",
            bloklar: [],
            dugme: "Devam",
          },
          {
            id: "paywall",
            tur: "paywall",
            baslik: "Pro",
            metin: "",
            dugme: "Abone ol",
            plan: "monthly",
          },
        ],
      }),
    };
  };

  it("T3b: koşul tutmayan ardışık ekranlar atlanır, geçmişe tek adım yazılır", async () => {
    tanim = gosterli();
    kur("");
    await screen.findByText("Kiminle?");
    await waitFor(() => expect(history.state).toEqual({ adim: 0 }));
    const once = history.length;
    fireEvent.click(screen.getByText("İş"));
    expect(gorunenBaslik()).toBe("Son");
    expect(history.length - once).toBe(1);
    expect((history.state as { adim: number }).adim).toBe(3);
  });

  it("T3b: koşul tutunca ekran gösterilir", async () => {
    tanim = gosterli();
    kur("");
    await screen.findByText("Kiminle?");
    fireEvent.click(screen.getByText("Ailemle"));
    expect(gorunenBaslik()).toBe("Aile 1");
    fireEvent.click(
      document.querySelector<HTMLElement>("[data-sira]:not([hidden]) button")!,
    );
    expect(gorunenBaslik()).toBe("Aile 2");
  });

  // T3c: koşullu değer — ekran başlığı önceki cevaba, blok metni UTM'ye göre.
  const degerli = (): Yanit => ({
    durum: 200,
    govde: yayin({
      adimlar: [
        {
          id: "kiminle",
          tur: "ekran",
          baslik: "Kiminle?",
          bloklar: [
            {
              tur: "soru",
              secenekler: [
                { id: "aile", metin: "Ailemle" },
                { id: "is", metin: "İş" },
              ],
            },
          ],
        },
        {
          id: "sonuc",
          tur: "ekran",
          baslik: "Genel",
          bloklar: [
            {
              tur: "metin",
              metin: "Herkes",
              degerler: [
                {
                  kosullar: [
                    { ozellik: "utm.source", islem: "esit", deger: "meta" },
                  ],
                  alan: "metin",
                  deger: "Meta'dan",
                },
              ],
            },
          ],
          dugme: "Devam",
          degerler: [
            {
              kosullar: [
                { ozellik: "cevap.kiminle", islem: "icerir", deger: "aile" },
              ],
              alan: "baslik",
              deger: "Aile için",
            },
          ],
        },
        {
          id: "paywall",
          tur: "paywall",
          baslik: "Pro",
          metin: "",
          dugme: "Abone ol",
          plan: "monthly",
        },
      ],
    }),
  });
  const gorunenMetin = (): string =>
    document.querySelector("[data-sira]:not([hidden]) .bloklar p")
      ?.textContent ?? "";

  it("T3c: koşul tutunca ekran ve blok alanı kuralın değeri", async () => {
    tanim = degerli();
    kur("?utm_source=meta");
    await screen.findByText("Kiminle?");
    fireEvent.click(screen.getByText("Ailemle"));
    expect(gorunenBaslik()).toBe("Aile için");
    expect(gorunenMetin()).toBe("Meta'dan");
  });

  it("T3c: tutmazsa alanın kendi değeri", async () => {
    tanim = degerli();
    kur("");
    await screen.findByText("Kiminle?");
    fireEvent.click(screen.getByText("İş"));
    expect(gorunenBaslik()).toBe("Genel");
    expect(gorunenMetin()).toBe("Herkes");
  });

  it("T3a: tutan kural seçeneğin kendi hedefinden önce gelir", async () => {
    const t = yonlendirmeli();
    const adim = (t.govde as Yayin).tanim.adimlar[0];
    if (adim?.tur !== "ekran" || adim.bloklar[0]?.tur !== "soru") throw 0;
    adim.bloklar[0].secenekler![0]!.hedef = "is-ekrani";
    tanim = t;
    kur("");
    await screen.findByText("Kiminle?");
    fireEvent.click(screen.getByText("Ailemle"));
    expect(gorunenBaslik()).toBe("Aile");
  });

  it("T3a: cevap kuralı tutmazsa sonraki kural (utm), o da tutmazsa sıradaki", async () => {
    tanim = yonlendirmeli();
    kur("?utm_source=meta");
    await screen.findByText("Kiminle?");
    fireEvent.click(screen.getByText("İş"));
    expect(gorunenBaslik()).toBe("Pro");
    cleanup();
    tanim = yonlendirmeli();
    kur("?utm_source=tiktok");
    await screen.findByText("Kiminle?");
    fireEvent.click(screen.getByText("İş"));
    expect(gorunenBaslik()).toBe("İş için");
  });

  it("T1b: ekrandaki tek seçimli soru cevabı ekranın kimliğiyle bildirir, seçeneğin hedefine atlar", async () => {
    tanim = {
      durum: 200,
      govde: yayin({
        adimlar: [
          {
            id: "kiminle",
            tur: "ekran",
            baslik: "Kiminle?",
            bloklar: [
              { tur: "kutu", metin: "Cevabın gizli kalır." },
              {
                tur: "soru",
                secenekler: [
                  { id: "aile", metin: "Ailemle", hedef: "paywall" },
                  { id: "is", metin: "İş" },
                ],
              },
            ],
          },
          {
            id: "atlanan",
            tur: "ekran",
            baslik: "Atlanan",
            bloklar: [],
            dugme: "Devam",
          },
          {
            id: "paywall",
            tur: "paywall",
            baslik: "Pro",
            metin: "",
            dugme: "Abone ol",
            plan: "monthly",
          },
        ],
      }),
    };
    kur();
    await screen.findByText("Kiminle?");
    expect(document.querySelector("[data-sira='0'] button.ana")).toBeNull();
    fireEvent.click(screen.getByText("Ailemle"));
    expect(gorunenBaslik()).toBe("Pro");
    await waitFor(() =>
      expect(ilerlemeler()).toContainEqual({
        sira: 0,
        adim: "kiminle",
        cevap: "aile",
      }),
    );
  });

  it("T1b: ekrandaki çoklu soru düğmeyle gönderilir, ekranın hedefine geçer", async () => {
    tanim = {
      durum: 200,
      govde: yayin({
        adimlar: [
          {
            id: "ilgi",
            tur: "ekran",
            baslik: "Neler ilgini çeker?",
            bloklar: [
              {
                tur: "soru",
                secimTuru: "coklu",
                secenekler: [
                  { id: "yedek", metin: "Yedekleme" },
                  { id: "arama", metin: "Arama" },
                ],
              },
            ],
            dugme: "Gönder",
            hedef: "paywall",
          },
          {
            id: "atlanan",
            tur: "ekran",
            baslik: "Atlanan",
            bloklar: [],
            dugme: "Devam",
          },
          {
            id: "paywall",
            tur: "paywall",
            baslik: "Pro",
            metin: "",
            dugme: "Abone ol",
            plan: "monthly",
          },
        ],
      }),
    };
    kur();
    await screen.findByText("Neler ilgini çeker?");
    const gonder = screen.getByText("Gönder") as HTMLButtonElement;
    expect(gonder.disabled).toBe(true);
    fireEvent.click(screen.getByText("Arama"));
    fireEvent.click(screen.getByText("Yedekleme"));
    expect(gonder.disabled).toBe(false);
    fireEvent.click(gonder);
    expect(gorunenBaslik()).toBe("Pro");
    await waitFor(() =>
      expect(ilerlemeler()).toContainEqual({
        sira: 0,
        adim: "ilgi",
        cevaplar: ["arama", "yedek"],
      }),
    );
  });

  it("öteki dal kendi adımından sonra sıradakine geçer", async () => {
    tanim = dallar();
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("İş"));
    expect(gorunenBaslik()).toBe("İş için");
    expect(history.state).toEqual({ adim: 3 });
    devam();
    expect(gorunenBaslik()).toBe("Pro");
  });
});

describe("H6a — reklam ölçümü izni (A12)", () => {
  const ADRES = "?utm_source=meta&fbclid=IwAR0abc&ttclid=E.C.P.x";
  const ziyaretGovdesi = (): unknown =>
    istekler("POST", "/huni/ziyaretler")[0]?.govde;
  const izinler = (): Istek[] => istekler("POST", `/huni/ziyaretler/${Z}/izin`);
  const bekleZiyaret = (): Promise<void> =>
    waitFor(() => expect(ilerlemeler()).toHaveLength(1));

  it("ilk ziyaret: şerit var, tıklama kimliği gitmez (UTM gider)", async () => {
    kur(ADRES);
    await yuklendi();
    await bekleZiyaret();
    expect(
      screen.getByRole("complementary", { name: "Reklam ölçümü izni" }),
    ).toBeTruthy();
    expect(ziyaretGovdesi()).toEqual({
      app: "keepchat",
      huni: "varsayilan",
      surum: 4,
      utm_source: "meta",
    });
  });

  it("Kabul: kimlikler izin ucuna gider, şerit kalkar, seçim hatırlanır", async () => {
    kur(ADRES);
    await yuklendi();
    fireEvent.click(screen.getByText("Kabul et"));
    await waitFor(() => expect(izinler()).toHaveLength(1));
    expect(izinler()[0]?.govde).toEqual({
      fbclid: "IwAR0abc",
      ttclid: "E.C.P.x",
    });
    expect(screen.queryByText("Kabul et")).toBeNull();
    expect(window.localStorage.getItem("huni-reklam-izni")).toBe("evet");
  });

  it("Reddet: izin isteği yok, şerit kalkar, seçim hatırlanır", async () => {
    kur(ADRES);
    await yuklendi();
    fireEvent.click(screen.getByText("Reddet"));
    await bekleZiyaret();
    expect(izinler()).toEqual([]);
    // Ç56 (3): ret çekirdeğe yazılır (oran raporu) — geri alma ucuyla.
    await waitFor(() =>
      expect(istekler("DELETE", `/huni/ziyaretler/${Z}/izin`)).toHaveLength(1),
    );
    expect(screen.queryByText("Reddet")).toBeNull();
    expect(window.localStorage.getItem("huni-reklam-izni")).toBe("hayir");
  });

  it("önceden izin verilmiş: şerit yok, kimlikler ziyaretle birlikte", async () => {
    window.localStorage.setItem("huni-reklam-izni", "evet");
    kur(ADRES);
    await yuklendi();
    await bekleZiyaret();
    expect(screen.queryByText("Kabul et")).toBeNull();
    expect(ziyaretGovdesi()).toMatchObject({
      fbclid: "IwAR0abc",
      ttclid: "E.C.P.x",
      izin: true,
    });
    expect(izinler()).toEqual([]);
  });

  it("önceden reddedilmiş: şerit yok, kimlik yok", async () => {
    window.localStorage.setItem("huni-reklam-izni", "hayir");
    kur(ADRES);
    await yuklendi();
    await bekleZiyaret();
    expect(screen.queryByText("Kabul et")).toBeNull();
    expect(ziyaretGovdesi()).not.toHaveProperty("fbclid");
    expect(ziyaretGovdesi()).toHaveProperty("izin", false); // Ç56 (3)
  });

  it("gizlilik ayarından izin geri alınca çekirdeğe silme isteği yollar", async () => {
    window.localStorage.setItem("huni-reklam-izni", "evet");
    const oncekiYanitle = ag.yanitla;
    ag.yanitla = (i) =>
      i.yontem === "DELETE" ? { durum: 204 } : oncekiYanitle(i);
    kur(ADRES);
    await yuklendi();

    fireEvent.click(screen.getByRole("button", { name: "Gizlilik ayarları" }));
    fireEvent.click(screen.getByText("Reddet"));

    await waitFor(() =>
      expect(istekler("DELETE", `/huni/ziyaretler/${Z}/izin`)).toHaveLength(1),
    );
    expect(window.localStorage.getItem("huni-reklam-izni")).toBe("hayir");
    expect(screen.queryByText("Reddet")).toBeNull();
  });

  it("izin geri alma isteği başarısızsa kullanıcı yeniden deneyebilsin diye şeridi açık tutar", async () => {
    window.localStorage.setItem("huni-reklam-izni", "evet");
    const oncekiYanitle = ag.yanitla;
    ag.yanitla = (i) =>
      i.yontem === "DELETE" ? "ag-hatasi" : oncekiYanitle(i);
    kur(ADRES);
    await yuklendi();

    fireEvent.click(screen.getByRole("button", { name: "Gizlilik ayarları" }));
    fireEvent.click(screen.getByText("Reddet"));

    await screen.findByRole("alert");
    expect(screen.getByText("Reddet")).toBeTruthy();
    expect(window.localStorage.getItem("huni-reklam-izni")).toBe("evet");
  });

  it("Kabul isteği uçuştayken geri alınırsa silme ondan SONRA gider (Ç58)", async () => {
    // Önce varan silmeyi geç varan Kabul ezerdi: tarayıcı "hayir", çekirdek
    // izinli → satın alma reklam ağlarına giderdi.
    let kabulBitir = (): void => undefined;
    const oncekiYanitle = ag.yanitla;
    ag.yanitla = (i) => {
      if (i.yontem === "DELETE") return { durum: 204 };
      if (i.yontem === "POST" && i.yol.endsWith("/izin"))
        return new Promise<Cevap>((coz) => {
          kabulBitir = () => coz({ durum: 204 });
        });
      return oncekiYanitle(i);
    };
    kur(ADRES);
    await yuklendi();

    fireEvent.click(screen.getByText("Kabul et"));
    await waitFor(() => expect(izinler()).toHaveLength(1));
    fireEvent.click(screen.getByRole("button", { name: "Gizlilik ayarları" }));
    fireEvent.click(screen.getByText("Reddet"));
    await new Promise((r) => setTimeout(r, 20));
    expect(istekler("DELETE", `/huni/ziyaretler/${Z}/izin`)).toEqual([]);

    kabulBitir();
    await waitFor(() =>
      expect(istekler("DELETE", `/huni/ziyaretler/${Z}/izin`)).toHaveLength(1),
    );
    expect(window.localStorage.getItem("huni-reklam-izni")).toBe("hayir");
  });

  it("yeniden kullanılan ziyarette önceden verilmiş izin izin ucuna gider (Ç34)", async () => {
    window.sessionStorage.setItem("huni-ziyaret:keepchat:varsayilan:4", Z);
    window.localStorage.setItem("huni-reklam-izni", "evet");
    kur(ADRES);
    await yuklendi();
    await waitFor(() => expect(izinler()).toHaveLength(1));
    expect(istekler("POST", "/huni/ziyaretler")).toEqual([]);
    expect(izinler()[0]?.govde).toEqual({
      fbclid: "IwAR0abc",
      ttclid: "E.C.P.x",
    });
  });

  it("önizlemede şerit yok", async () => {
    kur(`?onizleme=abc&${ADRES.slice(1)}`);
    await yuklendi();
    expect(screen.queryByText("Kabul et")).toBeNull();
  });

  it("tarayıcı deposu yoksa şerit yine çalışır", async () => {
    // Gizli pencere / engelli site verisi: localStorage'a ERİŞMEK hata verir.
    // (Storage.prototype sahtesi happy-dom'da tutmadı — kırma V10.)
    const asil = Object.getOwnPropertyDescriptor(window, "localStorage");
    Object.defineProperty(window, "localStorage", {
      configurable: true,
      get: () => {
        throw new DOMException("engelli", "SecurityError");
      },
    });
    onTestFinished(() => {
      if (asil) Object.defineProperty(window, "localStorage", asil);
    });
    kur(ADRES);
    await yuklendi();
    fireEvent.click(screen.getByText("Kabul et"));
    await waitFor(() => expect(izinler()).toHaveLength(1));
    expect(screen.queryByText("Kabul et")).toBeNull();
  });
});

describe("H4 — geçiş", () => {
  // huni.css animasyonu yalnız [data-gecis] altında: ilk adım opaklık 0'dan
  // başlamasın (ilk anlamlı boyama), sonraki her adım değişiminde başlasın.
  const kutu = (): Element | null =>
    document.querySelector("[data-sira]")?.parentElement ?? null;
  it("ilk adımda yok; ileri geçişte 'ileri' (L19: yöne göre)", async () => {
    kur();
    await yuklendi();
    expect(kutu()?.hasAttribute("data-gecis")).toBe(false);
    fireEvent.click(screen.getByText("Başla"));
    expect(kutu()?.getAttribute("data-gecis")).toBe("ileri");
  });
  it("geri tuşuyla da var", async () => {
    kur();
    await yuklendi();
    // Dinleyici effect'te eklenir (aynı effect replaceState yapar): önce onu
    // gör, yoksa olay dinleyiciden önce gider — CI'da kırmızıydı (tuzak §33).
    await waitFor(() => expect(history.state).toEqual({ adim: 0 }));
    fireEvent(window, new PopStateEvent("popstate", { state: { adim: 0 } }));
    await waitFor(() =>
      expect(kutu()?.getAttribute("data-gecis")).toBe("geri"),
    );
  });
});

describe("D13 · D14 — ödeme", () => {
  async function odemeyeGel(): Promise<HTMLButtonElement> {
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
    return screen.getByText("Abone ol") as HTMLButtonElement;
  }

  it("plan + ziyaret gider, düğme kapanır, dönen adrese yönlenir", async () => {
    const git = vi
      .spyOn(window.location, "assign")
      .mockImplementation(() => {});
    const dugme = await odemeyeGel();
    fireEvent.click(dugme);
    expect(dugme.disabled).toBe(true);
    await waitFor(() =>
      expect(git).toHaveBeenCalledWith("https://odeme.ornek/x"),
    );
    expect(istekler("POST", "/checkout/sessions")).toEqual([
      expect.objectContaining({
        govde: { app: "keepchat", plan: "monthly", ziyaret: Z },
      }),
    ]);
    expect(dugme.disabled).toBe(true);
  });

  it.each([
    [
      { durum: 502, govde: { error: "payment_provider_unavailable" } },
      "Ödeme sağlayıcısına şu an ulaşılamıyor. Lütfen biraz sonra tekrar deneyin.",
    ],
    [
      { durum: 422, govde: { error: "x" } },
      "Bir şeyler ters gitti. Lütfen tekrar deneyin.",
    ],
    [
      { durum: 502, govde: { error: "baska" } },
      "Bir şeyler ters gitti. Lütfen tekrar deneyin.",
    ],
    [
      "ag-hatasi" as const,
      "Bağlantı kurulamadı. İnternetinizi kontrol edip tekrar deneyin.",
    ],
  ])("%j → %s; düğme yeniden açılır", async (yanit, metin) => {
    odeme = yanit;
    const dugme = await odemeyeGel();
    fireEvent.click(dugme);
    expect((await screen.findByRole("alert")).textContent).toBe(metin);
    expect(dugme.disabled).toBe(false);
  });
});

describe("M9b2 — yayındaki yasal sayfalar", () => {
  const YASAL = {
    sartlar: "http://cekirdek.test/yasal/keepchat/sartlar/tr",
    gizlilik: "http://cekirdek.test/yasal/keepchat/gizlilik/tr",
    cerez: "http://cekirdek.test/yasal/keepchat/cerez/tr",
  };
  const paywall = () =>
    document.querySelector<HTMLElement>('[data-adim="paywall"]')!;
  const adresler = (kap: HTMLElement): Record<string, string | null> =>
    Object.fromEntries(
      [...kap.querySelectorAll("a")].map((a) => [
        a.textContent ?? "",
        a.getAttribute("href"),
      ]),
    );
  const paywallaGit = async () => {
    kur();
    await yuklendi();
    fireEvent.click(screen.getByText("Başla"));
    fireEvent.click(screen.getByText("Ailemle"));
  };
  const ile = (adim: Record<string, unknown>, yasal?: object): Yanit => {
    const temel = yayin();
    return {
      durum: 200,
      govde: {
        ...temel,
        ...(yasal ? { yasal } : {}),
        tanim: {
          ...temel.tanim,
          adimlar: temel.tanim.adimlar.map((a) =>
            a.tur === "paywall" ? { ...a, ...adim } : a,
          ),
        },
      },
    };
  };

  it("paywall adımında adres yoksa uygulamanın yasal sayfası; adımın kendi adresi önce gelir", async () => {
    tanim = ile(
      { gizlilik: "https://ornek.com/gizlilik", sartOnayi: true },
      YASAL,
    );
    await paywallaGit();
    const bag = adresler(paywall());
    expect(bag["Kullanım şartları"]).toBe(YASAL.sartlar);
    expect(bag["Gizlilik politikası"]).toBe("https://ornek.com/gizlilik");
    // Şart onayı kutusu da aynı adrese bağlanır.
    expect(paywall().querySelector(".sart-onayi a")?.getAttribute("href")).toBe(
      YASAL.sartlar,
    );
  });

  it("yayında yasal sayfa yoksa bağlantı uydurulmaz", async () => {
    tanim = ile({});
    await paywallaGit();
    expect(adresler(paywall())).toEqual({});
  });

  it("izin şeridi çerez bildirimine ve gizliliğe bağlanır; yoksa bağlantı yok", async () => {
    tanim = ile({}, YASAL);
    kur();
    await yuklendi();
    const serit = screen.getByRole("complementary", {
      name: "Reklam ölçümü izni",
    });
    expect(adresler(serit)).toEqual({
      "Çerez ve izleme bildirimi": YASAL.cerez,
      "Gizlilik politikası": YASAL.gizlilik,
    });
    cleanup();

    tanim = ile({});
    kur();
    await yuklendi();
    expect(
      adresler(
        screen.getByRole("complementary", { name: "Reklam ölçümü izni" }),
      ),
    ).toEqual({});
  });
});
