// Oynatıcının korunacak davranışları (H0 (b), D1–D15). Ağ sahte: çekirdeğe
// giden her istek kaydedilir — "kaç istek, hangi gövde" doğrudan ölçülür.
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/react";
import {
  afterEach,
  beforeEach,
  describe,
  expect,
  it,
  onTestFinished,
  vi,
} from "vitest";
import type { components } from "../sozlesme/api";
import Huni from "./Huni";

type Yayin = components["schemas"]["HuniYayini"];
type Istek = { yontem: string; yol: string; sorgu: string; govde: unknown };
type Yanit = { durum: number; govde?: unknown };

// openapi-fetch fetch'i istemci kurulurken alır (cekirdek.ts, içe aktarmada):
// sahte, içe aktarmalardan ÖNCE yerinde olmalı.
const ag = vi.hoisted(() => {
  const ag = {
    istekler: [] as Istek[],
    yanitla: (_i: Istek): Yanit | "ag-hatasi" => ({ durum: 404 }),
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
    const y = ag.yanitla(istek);
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
        tur: "tanitim",
        baslik: "Merhaba",
        metin: "Kısa",
        dugme: "Başla",
        gorsel: G,
      },
      {
        id: "kiminle",
        tur: "soru",
        soru: "Kiminle?",
        secenekler: [
          { id: "aile", metin: "Ailemle" },
          { id: "is", metin: "İş" },
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
  ag.yanitla = (i) => {
    if (i.yontem === "GET" && i.yol.startsWith("/huni/keepchat")) return tanim;
    if (i.yol === "/huni/ziyaretler") return ziyaret;
    if (i.yol.endsWith("/ilerleme")) return { durum: 204 };
    if (i.yol === "/checkout/sessions") return odeme;
    return { durum: 404 };
  };
  delete window.huniTanimi;
  window.localStorage.clear(); // izin seçimi testler arasında sızmasın
  document.documentElement.removeAttribute("style");
  document.title = "";
});
afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("D15 · D4 — yükleniyor ve açılamadı", () => {
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

describe("D5 — seçim parametreleri", () => {
  it("yalnız k/utm_source/utm_campaign/utm_content/huni gider, her biri ≤ 200", async () => {
    const uzun = "h".repeat(250);
    kur(
      `?k=fitness&utm_source=meta&utm_term=t&fbclid=xyz&ttclid=q&utm_campaign=c&utm_content=i&huni=${uzun}`,
    );
    await yuklendi();
    const [g] = istekler("GET", "/huni/keepchat");
    expect(Object.fromEntries(new URLSearchParams(g?.sorgu))).toEqual({
      k: "fitness",
      utm_source: "meta",
      utm_campaign: "c",
      utm_content: "i",
      huni: "h".repeat(200),
    });
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

describe("D1 · D2 · D6 · D8 — çizim", () => {
  it("metin HTML olarak yorumlanmaz", async () => {
    tanim = {
      durum: 200,
      govde: yayin({
        adimlar: [
          {
            id: "a",
            tur: "tanitim",
            baslik: "<b>kalın</b>",
            metin: "<img src=x onerror=alert(1)>",
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
    fireEvent.click(screen.getByText("Başla"));
    expect(cubuk.value).toBe(2);
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
            adim.tur === "soru"
              ? {
                  ...adim,
                  aciklama: "Birden fazla seçenek seçebilirsin.",
                  secimTuru: "coklu",
                  devamDugmesi: "Seçimleri gönder",
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
            adim.tur === "soru"
              ? {
                  id: adim.id,
                  tur: "soru",
                  soru: adim.soru,
                  secimTuru: "metin",
                  devamDugmesi: "Devam et",
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
            adim.tur === "soru"
              ? {
                  id: adim.id,
                  tur: "soru",
                  soru: adim.soru,
                  secimTuru: "metin",
                  yanitZorunlu: true,
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
    const devam = screen.getByRole("button", { name: "Devam" });
    expect((devam as HTMLButtonElement).disabled).toBe(true);
    fireEvent.click(devam);
    expect(gorunenBaslik()).toBe("Kiminle?");
    fireEvent.change(screen.getByRole("textbox", { name: "Yanıtınız" }), {
      target: { value: "Bir cevap" },
    });
    expect((devam as HTMLButtonElement).disabled).toBe(false);
  });

  it("yorum adımı alıntıyı gösterir ve düğmeyle sonraki adıma ilerler", async () => {
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
              tur: "yorum",
              baslik: "Kullanıcı deneyimi",
              metin: "Günlük işlerim artık daha kolay.",
              yazar: "Ayşe",
              unvan: "KeepChat kullanıcısı",
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
  it("tema yoksa kökte değişken yazılmaz (huni.css varsayılanı)", async () => {
    kur();
    await yuklendi();
    // Olumsuz iddia: önce effect'in koştuğunu gör (başlık aynı effect'te).
    await waitFor(() => expect(document.title).toBe("KeepChat Pro"));
    expect(document.documentElement.style.getPropertyValue("--ana")).toBe("");
  });
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

describe("H5 — dallanma (yalnız ileri)", () => {
  // giris → kiminle ─aile→ aile-adimi ─(hedef)→ paywall
  //                 └─iş──→ is-adimi ─────────→ paywall
  const dallar = (): Yanit => ({
    durum: 200,
    govde: yayin({
      adimlar: [
        {
          id: "giris",
          tur: "tanitim",
          baslik: "Merhaba",
          metin: "",
          dugme: "Başla",
        },
        {
          id: "kiminle",
          tur: "soru",
          soru: "Kiminle?",
          secenekler: [
            { id: "aile", metin: "Ailemle", hedef: "aile-adimi" },
            { id: "is", metin: "İş", hedef: "is-adimi" },
          ],
        },
        {
          id: "aile-adimi",
          tur: "tanitim",
          baslik: "Aile",
          metin: "",
          dugme: "Devam",
          hedef: "paywall",
        },
        {
          id: "is-adimi",
          tur: "tanitim",
          baslik: "İş için",
          metin: "",
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
  it("ilk adımda yok; ileri geçişte var", async () => {
    kur();
    await yuklendi();
    expect(kutu()?.hasAttribute("data-gecis")).toBe(false);
    fireEvent.click(screen.getByText("Başla"));
    expect(kutu()?.hasAttribute("data-gecis")).toBe(true);
  });
  it("geri tuşuyla da var", async () => {
    kur();
    await yuklendi();
    // Dinleyici effect'te eklenir (aynı effect replaceState yapar): önce onu
    // gör, yoksa olay dinleyiciden önce gider — CI'da kırmızıydı (tuzak §33).
    await waitFor(() => expect(history.state).toEqual({ adim: 0 }));
    fireEvent(window, new PopStateEvent("popstate", { state: { adim: 0 } }));
    await waitFor(() => expect(kutu()?.hasAttribute("data-gecis")).toBe(true));
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
