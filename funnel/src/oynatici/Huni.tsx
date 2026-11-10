// Huni oynatıcısı (A7, B4/H2): tanımı çekirdekten okur (yayınlanmış en son
// sürüm, ya da ?onizleme=<anahtar> ile taslak) ve adımları TEK SAYFADA
// sırayla gösterir. Adımları panel önizlemesiyle aynı bileşenler çizer
// (huni-bilesenleri). Ölçüm (ziyaret, adım, cevap, ödeme) oynatıcının işi;
// tanımda ne olursa olsun otomatik. Önizlemede hiçbir şey kaydedilmez,
// ödeme kapalı. D1–D15: docs/decisions/huni-oynatici-h0.md (b).
import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import {
  Adim,
  HareketBaglami,
  kalanSure,
  Logo,
  OLCEK,
  SimgeBaglami,
} from "../../../huni-bilesenleri/bilesenler";
import {
  degerleriUygula,
  grupTutar,
  yonlendir,
} from "../../../huni-bilesenleri/kosul";
import {
  ozellikler,
  type Olay,
  type Ozellikler,
} from "../../../huni-bilesenleri/ozellikler";
import { temaDegiskenleri } from "../../../huni-bilesenleri/tema";
import { cekirdek } from "../lib/cekirdek";
import { fiyatMetni, kosulMetni } from "../lib/fiyat";
import { izinOku, izinYaz, type Izin } from "../lib/izin";
import { temaKaydet } from "../lib/sonuc-tema";
import {
  epostaBildir,
  ilerlemeBildir,
  izinBildir,
  izinGeriAl,
  ziyaretBaslat,
  type Govde,
} from "../lib/ziyaret";
import type { components } from "../sozlesme/api";
import { stripeYukle, type StripeYapici } from "../lib/stripe";
import DownsellPenceresi from "./DownsellPenceresi";
import GomuluOdeme from "./GomuluOdeme";
import { tanimIstegi } from "./erken-istek";
import IzinSeridi from "./IzinSeridi";

type Yayin = components["schemas"]["HuniYayini"];
export type App = Govde<"/huni/ziyaretler">["app"];

declare global {
  interface Window {
    /** Sayfadaki satır içi betiğin başlattığı tanım isteği (erken-istek.ts). */
    huniTanimi?: Promise<unknown>;
  }
}

const KOK = import.meta.env.PUBLIC_CORE_URL;

type TanimAdimi = Yayin["tanim"]["adimlar"][number];
type Secenek = components["schemas"]["SoruSecenegi"];

/**
 * Adımın sorusu: ekrandaki soru bloğu (T1b; T1c'den beri soru adımı yok).
 * Cevap adımın kimliğiyle kaydedilir — ekranda en çok bir soru bloğu
 * (çekirdek denetler).
 */
/** T3c: ekranın ve bloklarının koşullu değerleri uygulanmış hâli. */
function kisisel(adim: TanimAdimi, o: Ozellikler): TanimAdimi {
  if (adim.tur !== "ekran") return adim;
  const ekran = degerleriUygula(adim, o);
  return {
    ...ekran,
    bloklar: ekran.bloklar.map((b) =>
      "degerler" in b ? degerleriUygula(b, o) : b,
    ),
  } as TanimAdimi;
}

function sorusu(adim: TanimAdimi): {
  id: string;
  secimTuru?: "tekli" | "coklu" | "metin" | "eposta" | "olcek" | undefined;
  secenekler?: Secenek[] | undefined;
  yanitZorunlu?: boolean | undefined;
} | null {
  if (adim.tur !== "ekran") return null;
  for (const b of adim.bloklar)
    if (b.tur === "soru")
      return {
        id: adim.id,
        secimTuru: b.secimTuru,
        // V5: ölçeğin seçenekleri kendiliğinden — cevap kimliği "1"…"5".
        secenekler:
          b.secimTuru === "olcek"
            ? OLCEK.map((id) => ({ id, metin: id }))
            : b.secenekler,
        yanitZorunlu: b.yanitZorunlu,
      };
  return null;
}

export default function Huni({ app }: { app: App }): ReactNode {
  const [onizleme] = useState(() =>
    new URLSearchParams(window.location.search).has("onizleme"),
  );
  // undefined: yükleniyor · null: açılamadı
  const [yayin, setYayin] = useState<Yayin | null>();
  useEffect(() => {
    let iptal = false;
    void (window.huniTanimi ?? tanimIstegi(app, KOK)).then((y) => {
      if (!iptal) setYayin(y ? yasalAdresleri(y as Yayin) : null);
    });
    return () => {
      iptal = true;
    };
  }, [app]);

  if (yayin === undefined) return <p id="durum">Yükleniyor…</p>;
  if (yayin === null)
    return (
      <p id="durum">
        {onizleme
          ? "Önizleme açılamadı: bağlantının süresi dolmuş olabilir."
          : "Sayfa yüklenemedi. Lütfen yenileyin."}
      </p>
    );
  return <Oynatici app={app} yayin={yayin} onizleme={onizleme} />;
}

/**
 * M9b2 (A23): paywall adımında şartlar / gizlilik adresi yoksa uygulamanın
 * YAYINDAKİ yasal sayfası (çekirdek yalnız kaydedilmiş belgeleri verir).
 * Adımın kendi adresi her zaman önce gelir.
 */
function yasalAdresleri(yayin: Yayin): Yayin {
  const y = yayin.yasal;
  if (!y) return yayin;
  return {
    ...yayin,
    tanim: {
      ...yayin.tanim,
      adimlar: yayin.tanim.adimlar.map((a) => {
        if (a.tur !== "paywall") return a;
        const sartlar = a.sartlar ?? y.sartlar;
        const gizlilik = a.gizlilik ?? y.gizlilik;
        return {
          ...a,
          ...(sartlar ? { sartlar } : {}),
          ...(gizlilik ? { gizlilik } : {}),
        };
      }),
    },
  };
}

function Oynatici({
  app,
  yayin,
  onizleme,
}: {
  app: App;
  yayin: Yayin;
  onizleme: boolean;
}): ReactNode {
  const { tanim } = yayin;
  // H6a: reklam ölçümü izni — daha önce seçildiyse bu tarayıcıda hatırlanır.
  const [izin, setIzin] = useState<Izin | null>(izinOku);
  const [geriAlGerekir, setGeriAlGerekir] = useState(false);
  const [izinKaydediliyor, setIzinKaydediliyor] = useState(false);
  const [izinHatasi, setIzinHatasi] = useState("");
  // Uçuştaki Kabul isteği: geri alma bunu bekler (Ç58).
  const izinIstegi = useRef<Promise<void>>(Promise.resolve());
  // Ziyaret bir kez açılır; önizlemede hiç açılmaz, adım/cevap da gitmez.
  // Tıklama kimlikleri yalnız önceden verilmiş izinle (yoksa Kabul'de ayrıca).
  const [ziyaretSozu] = useState(() =>
    onizleme
      ? Promise.resolve(undefined)
      : ziyaretBaslat(
          app,
          yayin.huni,
          yayin.surum ?? undefined,
          izinOku(),
          yayin.deneyler,
        ),
  );
  // Ç72: önizlemede ?adim=<kimlik> o adımdan başlar (ekran görüntüsü aracı);
  // gerçek ziyarette yok sayılır — ölçüm hep ilk adımdan.
  const [ilkSira] = useState(() => {
    if (!onizleme) return 0;
    const adim = new URLSearchParams(window.location.search).get("adim");
    return Math.max(
      0,
      tanim.adimlar.findIndex((a) => a.id === adim),
    );
  });
  const [mevcut, setMevcut] = useState(ilkSira);
  // M3a: geri tuşu işleyicisi ilk çizimde bağlanır — güncel adımı ref'ten okur.
  const mevcutRef = useRef(mevcut);
  mevcutRef.current = mevcut;
  // M3a (A16): açık downsell penceresinin paywall kimliği. Ziyaretçi başına
  // paywall başına BİR KEZ (sekme oturumunda, yenilemede de); depo yoksa bellek.
  const [downsell, setDownsell] = useState<string | null>(null);
  const gosterilenDownsell = useRef(new Set<string>());
  const downsellDene = (
    adim: (typeof tanim.adimlar)[number] | undefined,
  ): boolean => {
    if (adim?.tur !== "paywall" || !adim.downsell) return false;
    const anahtar = `downsell:${app}:${yayin.huni}:${adim.id}`;
    if (gosterilenDownsell.current.has(anahtar)) return false;
    gosterilenDownsell.current.add(anahtar);
    try {
      if (sessionStorage.getItem(anahtar)) return false;
      sessionStorage.setItem(anahtar, "1");
    } catch {
      // Depo kapalı: bellekteki işaret yeter.
    }
    setDownsell(adim.id);
    return true;
  };
  const downsellKapat = useCallback(() => setDownsell(null), []);
  // Ç75: AI'ın ekran görüntüsü ziyaretçinin gördüğünü göstersin — şerit yok,
  // ödeme düğmesi soluk değil (önizlemede yine ödeme başlamaz, aşağıda).
  const [ekranCekimi] = useState(
    () => new URLSearchParams(window.location.search).get("ekran") === "1",
  );
  const [secimler, setSecimler] = useState<Record<string, string[]>>({});
  // T3a: çekirdeğe giden olayların kopyası — koşullar aynı olaylardan aynı
  // özellikleri görür (T2b). Karar anında okunur, çizim tetiklemez.
  const olaylar = useRef<Olay[]>([]);
  function bildir(
    sira: number,
    adim: string,
    cevap?: string | string[],
    cevapMetni?: string,
  ): void {
    olaylar.current.push({
      sira,
      adim,
      ...(typeof cevap === "string" ? { cevap } : {}),
      ...(Array.isArray(cevap) ? { cevaplar: cevap } : {}),
      ...(cevapMetni !== undefined ? { cevapMetni } : {}),
    });
    ilerlemeBildir(ziyaretSozu, sira, adim, cevap, cevapMetni);
  }
  const simdikiOzellikler = () =>
    ozellikler({
      huni: yayin.huni,
      surum: yayin.surum ?? undefined,
      adres: window.location.search,
      izin,
      olaylar: olaylar.current,
      deneyler: yayin.deneyler,
    });
  /** T3a: ekrandan çıkış — ilk tutan kural, yoksa verilen (seçenek/ekran) hedef. */
  function cik(adim: TanimAdimi, hedef?: string): void {
    const kural =
      adim.tur === "ekran"
        ? yonlendir(adim.yonlendirme, simdikiOzellikler())
        : undefined;
    ileri(kural ?? hedef);
  }
  const [serbestYanilar, setSerbestYanilar] = useState<Record<string, string>>(
    {},
  );
  // E1: e-posta sorusunun pazarlama izni kutusu (adım kimliği → işaretli mi).
  const [epostaIzinleri, setEpostaIzinleri] = useState<Record<string, boolean>>(
    {},
  );
  // H4: geçiş animasyonu yalnız adım DEĞİŞİNCE — ilk adım hemen görünür
  // (opaklık 0'dan başlasa ilk anlamlı boyama gecikirdi). Adımla aynı
  // çizimde açılır; effect'te açılsa yeni adım bir kare görünüp titrerdi.
  // L19: yön de taşınır — ileri sağdan, geri soldan kayar.
  const [gecis, setGecis] = useState<"ileri" | "geri">();
  const [odeme, setOdeme] = useState({ suruyor: false, hata: "" });
  // M2a: plan seçicide seçilen plan (paywall kimliği → plan); yoksa adımın varsayılanı.
  const [secilenPlan, setSecilenPlan] = useState<Record<string, string>>({});
  // M2d: açık gömülü ödeme paneli (Stripe.js yüklendiyse).
  const [gomulu, setGomulu] = useState<{
    yapici: StripeYapici;
    anahtar: string;
    sir: string;
  } | null>(null);
  // M2b: paywall kimliği → şart onay kutusu.
  const [sartOnaylari, setSartOnaylari] = useState<Record<string, boolean>>({});
  // M2b: saniyelik saat — yalnız bitişli teklif varken (sayaç + sürenin
  // dolduğu an teklifin paywall'dan kalkması; sunucu da bitişte keser).
  const [simdi, setSimdi] = useState(() => Date.now());
  const bitisVar = Object.values(yayin?.fiyatlar ?? {}).some(
    (f) => f.teklif?.bitis,
  );
  useEffect(() => {
    if (!bitisVar) return;
    const z = setInterval(() => setSimdi(Date.now()), 1000);
    return () => clearInterval(z);
  }, [bitisVar]);
  const etkin = (
    plan: string,
  ): components["schemas"]["PlanFiyati"] | undefined => {
    const f = yayin?.fiyatlar?.[plan];
    if (!f?.teklif?.bitis || Date.parse(f.teklif.bitis) > simdi) return f;
    const { teklif: _bitti, ...teklifsiz } = f;
    return teklifsiz;
  };
  const kutu = useRef<HTMLDivElement>(null);
  const ilkAdim = useRef(true);

  // Tema: değerler çekirdekte doğrulandı; yine de CSS'e setProperty ile.
  // İlk boyamadan ÖNCE (layout effect): sonra yazılırsa düğme önce varsayılan
  // renkle çizilip 120 ms'de temaya geçiyordu — ziyaretçi parlama, ekran
  // görüntüsü ara renk görüyordu (ölçüm: gorunum-g3.md).
  useLayoutEffect(() => {
    document.title = tanim.sayfaBasligi;
    // Mobil görünüm 5: sonuç sayfası aynı temayla çizilsin (önizlemede de
    // zararsız — yalnız bu sekmenin oturum belleği).
    temaKaydet(tanim.tema);
    // V4: hareket düzeyi CSS'te .huni'nin (body) özniteliğinden okunur.
    document.body.dataset["hareket"] = tanim.tema?.hareket ?? "sakin";
    if (!tanim.tema) return;
    const kok = document.documentElement.style;
    for (const [ad, deger] of Object.entries(temaDegiskenleri(tanim.tema, KOK)))
      kok.setProperty(ad, deger);
  }, [tanim]);

  // Geri tuşu bir önceki adıma döner, sayfadan çıkmaz.
  useEffect(() => {
    history.replaceState({ adim: ilkSira }, "");
    const geri = (e: PopStateEvent): void => {
      // M3a: paywall'dan ilk geri denemesi downsell'i açar, sayfada kalınır.
      if (downsellDene(tanim.adimlar[mevcutRef.current])) {
        history.pushState({ adim: mevcutRef.current }, "");
        return;
      }
      setGecis("geri");
      setMevcut((e.state as { adim?: number } | null)?.adim ?? 0);
    };
    window.addEventListener("popstate", geri);
    return () => window.removeEventListener("popstate", geri);
  }, [ilkSira]);

  // Her gösterilen adım bildirilir; ilk adım dışında başlığa odak.
  useEffect(() => {
    bildir(mevcut, tanim.adimlar[mevcut]?.id ?? "");
    if (ilkAdim.current) {
      ilkAdim.current = false;
      return;
    }
    kutu.current
      ?.querySelector<HTMLElement>(`[data-sira="${mevcut}"] h1`)
      ?.focus();
  }, [mevcut, tanim, ziyaretSozu]);

  // Mobil görünüm 4: yükleme adımı süre dolunca sıradakine geçer; adım
  // değişirse (geri tuşu) zamanlayıcı iptal. Ekran çekiminde geçmez — AI
  // yükleme ekranını görsün.
  useEffect(() => {
    const adim = tanim.adimlar[mevcut];
    if (adim?.tur !== "yukleme" || ekranCekimi) return;
    const zaman = setTimeout(() => ileri(), (adim.sure ?? 4) * 1000);
    return () => clearTimeout(zaman);
  }, [mevcut, tanim, ekranCekimi]);

  /** H5: hedef varsa o adıma (çekirdek yalnız ileriyi kabul eder), yoksa sıradakine. */
  function ileri(hedef?: string): void {
    let sira = hedef
      ? tanim.adimlar.findIndex((a) => a.id === hedef)
      : mevcut + 1;
    if (sira <= mevcut || sira >= tanim.adimlar.length) return;
    // T3b: koşulu tutmayan ekran atlanır; paywall koşulsuz (M2c: son adım hep paywall — döngü orada durur).
    const o = simdikiOzellikler();
    for (
      let a = tanim.adimlar[sira];
      a?.tur === "ekran" && a.goster && !grupTutar(a.goster, o);
      a = tanim.adimlar[sira]
    )
      sira++;
    history.pushState({ adim: sira }, "");
    setGecis("ileri");
    setMevcut(sira);
  }

  // Paywall: plan adını çekirdeğe gönderir, dönen ödeme adresine yönlenir.
  async function aboneOl(
    plan: Govde<"/checkout/sessions">["plan"],
    sartOnayi = false,
  ) {
    setOdeme({ suruyor: true, hata: "" });
    let hata: string;
    try {
      const govde: Govde<"/checkout/sessions"> = { app, plan };
      if (sartOnayi) govde.sartOnayi = true;
      // M2d: Stripe.js yüklenebildiyse ödeme sayfada; yoksa barındırılan sayfa.
      const yapici = await stripeYukle();
      if (yapici) govde.gomulu = true;
      // M1c: paywall'daki para birimini seçen ülke testi ödemeye de gider.
      const ulke = new URLSearchParams(window.location.search).get("ulke");
      if (ulke && /^[A-Z]{2}$/.test(ulke)) govde.ulke = ulke;
      const ziyaret = await ziyaretSozu;
      if (ziyaret) govde.ziyaret = ziyaret;
      const { data, error, response } = await cekirdek.POST(
        "/checkout/sessions",
        { body: govde },
      );
      if (data && "clientSecret" in data && yapici) {
        setGomulu({ yapici, anahtar: data.anahtar, sir: data.clientSecret });
        setOdeme({ suruyor: false, hata: "" });
        return;
      }
      if (data && "url" in data) {
        window.location.assign(data.url);
        return;
      }
      hata =
        response.status === 502 &&
        error?.error === "payment_provider_unavailable"
          ? "Ödeme sağlayıcısına şu an ulaşılamıyor. Lütfen biraz sonra tekrar deneyin."
          : "Bir şeyler ters gitti. Lütfen tekrar deneyin.";
    } catch {
      // Ağ hatası: çekirdeğe hiç ulaşılamadı.
      hata = "Bağlantı kurulamadı. İnternetinizi kontrol edip tekrar deneyin.";
    }
    setOdeme({ suruyor: false, hata });
  }

  async function izinSec(secim: Izin): Promise<void> {
    if (secim === "hayir" && geriAlGerekir) {
      setIzinKaydediliyor(true);
      await izinIstegi.current;
      const geriAlindi = await izinGeriAl(ziyaretSozu);
      setIzinKaydediliyor(false);
      if (!geriAlindi) {
        setIzinHatasi(
          "İzin tercihiniz sunucuya iletilemedi. Lütfen tekrar deneyin.",
        );
        return;
      }
    }
    izinYaz(secim);
    setIzin(secim);
    setGeriAlGerekir(false);
    setIzinHatasi("");
    if (secim === "evet") izinIstegi.current = izinBildir(ziyaretSozu);
    // Ç56 (3): ilk ret de çekirdeğe yazılsın (oran raporu); hata yutulur.
    else if (!geriAlGerekir) void izinGeriAl(ziyaretSozu);
  }

  return (
    // V3a: emoji simgelerinin biçimi temadan; görseller çekirdekte.
    <SimgeBaglami.Provider
      value={{ stil: tanim.tema?.simgeler ?? "cihaz", kok: KOK }}
    >
      <HareketBaglami.Provider value={tanim.tema?.hareket ?? "sakin"}>
        {!onizleme && izin === null ? (
          <IzinSeridi
            onSec={izinSec}
            bekliyor={izinKaydediliyor}
            hata={izinHatasi}
            cerez={yayin.yasal?.cerez}
            gizlilik={yayin.yasal?.gizlilik}
          />
        ) : null}
        {tanim.logo ? <Logo gorsel={tanim.logo} medyaKoku={KOK} /> : null}
        {onizleme && !ekranCekimi ? (
          <p className="onizleme">
            Önizleme —{" "}
            {new URLSearchParams(window.location.search).get("yayin") === "1"
              ? "yayındaki sürüm"
              : "taslak"}
            ; hiçbir şey kaydedilmez, ödeme kapalı.
          </p>
        ) : null}
        {mevcut > 0 || tanim.adimlar[mevcut]?.ilerlemeGoster !== false ? (
          // Geri ve ilerleme tek satırda; geri sütunu ilk adımda da yer tutar,
          // ikinci adımda sayfa kaymaz.
          <div className="ust-cubuk">
            {mevcut > 0 ? (
              // Ç61: tarayıcı geri tuşuyla aynı yol (popstate) — dallanmada da
              // gelinen adıma döner.
              <button
                className="geri"
                type="button"
                aria-label="Geri"
                onClick={() => history.back()}
              >
                <svg
                  viewBox="0 0 24 24"
                  width="22"
                  height="22"
                  aria-hidden="true"
                >
                  <path
                    d="M15 5l-7 7 7 7"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.25"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </button>
            ) : null}
            {tanim.adimlar[mevcut]?.ilerlemeGoster !== false ? (
              // L19 / Ç54 (3): yerel progress geçiş desteklemiyor (atlıyordu) —
              // ekran okuyucu için yerinde ama görünmez; görünen dolgu yumuşak.
              <div className="ilerleme">
                {/* M2c: her paywall huninin sonu — öndeki paywall'da da çubuk dolu. */}
                <progress
                  max={tanim.adimlar.length}
                  value={
                    tanim.adimlar[mevcut]?.tur === "paywall"
                      ? tanim.adimlar.length
                      : mevcut + 1
                  }
                />
                <span
                  className="ilerleme-dolum"
                  aria-hidden="true"
                  style={{
                    width: `${tanim.adimlar[mevcut]?.tur === "paywall" ? 100 : ((mevcut + 1) / tanim.adimlar.length) * 100}%`,
                  }}
                />
              </div>
            ) : null}
          </div>
        ) : null}
        <div ref={kutu} className="adimlar" data-gecis={gecis}>
          {tanim.adimlar.map((ham, i) => {
            // T3c: değerler çizimde (her adım değişimi yeniden çizer).
            const adim = kisisel(ham, simdikiOzellikler());
            const soru = sorusu(adim);
            const cevaplar = soru ? (secimler[soru.id] ?? []) : [];
            const cevapMetni = soru ? (serbestYanilar[soru.id] ?? "") : "";
            const secili = soru
              ? (soru.secenekler ?? []).flatMap((s, j) =>
                  cevaplar.includes(s.id) ? [j] : [],
                )
              : [];
            return (
              <div
                key={`${i}-${adim.id}`}
                data-adim={adim.id}
                data-sira={i}
                hidden={i !== mevcut}
              >
                <Adim
                  adim={adim}
                  medyaKoku={KOK}
                  onIleri={() =>
                    cik(adim, adim.tur === "ekran" ? adim.hedef : undefined)
                  }
                  onSecim={(s) => {
                    if (!soru) return;
                    bildir(mevcut, soru.id, soru.secenekler?.[s]?.id);
                    cik(adim, soru.secenekler?.[s]?.hedef);
                  }}
                  secili={secili}
                  cevapMetni={cevapMetni}
                  onMetinDegistir={(metin) => {
                    if (
                      soru?.secimTuru !== "metin" &&
                      soru?.secimTuru !== "eposta"
                    )
                      return;
                    setSerbestYanilar((once) => ({
                      ...once,
                      [soru.id]: metin,
                    }));
                  }}
                  izinVar={soru ? !!epostaIzinleri[soru.id] : false}
                  onIzinDegistir={(izinVar) => {
                    if (soru?.secimTuru !== "eposta") return;
                    setEpostaIzinleri((once) => ({
                      ...once,
                      [soru.id]: izinVar,
                    }));
                  }}
                  onSecenekDegistir={(s) => {
                    if (!soru) return;
                    const id = soru.secenekler?.[s]?.id;
                    if (!id) return;
                    setSecimler((once) => {
                      const seciliCevaplar = once[soru.id] ?? [];
                      return {
                        ...once,
                        [soru.id]: seciliCevaplar.includes(id)
                          ? seciliCevaplar.filter((cevap) => cevap !== id)
                          : [...seciliCevaplar, id],
                      };
                    });
                  }}
                  onCokluDevam={() => {
                    if (!soru) return;
                    const hedef = adim.tur === "ekran" ? adim.hedef : undefined;
                    if (soru.secimTuru === "eposta") {
                      // Geçersiz/eksik adreste düğme kapalı (Adim); burada
                      // ikinci denetim ölçülemedi (kırma K16), yazılmadı.
                      if (cevapMetni.trim())
                        epostaBildir(
                          ziyaretSozu,
                          cevapMetni,
                          !!epostaIzinleri[soru.id],
                        );
                      cik(adim, hedef);
                      return;
                    }
                    if (soru.secimTuru === "metin") {
                      if (soru.yanitZorunlu && !cevapMetni.trim()) return;
                      bildir(mevcut, soru.id, undefined, cevapMetni);
                      cik(adim, hedef);
                      return;
                    }
                    if (!cevaplar.length) return;
                    bildir(mevcut, soru.id, cevaplar);
                    cik(adim, hedef);
                  }}
                  odeme={{
                    kapali: (onizleme && !ekranCekimi) || odeme.suruyor,
                    hata: odeme.hata,
                    fiyat:
                      adim.tur === "paywall"
                        ? fiyatMetni(etkin(adim.plan))
                        : undefined,
                    // M1c: fiyat varsa para birimi IP'den bulunan ülkeyle seçildi (DB-IP lisansı).
                    konumAtfi:
                      adim.tur === "paywall" &&
                      (adim.planlar ?? [adim.plan]).some((p) =>
                        Boolean(yayin.fiyatlar?.[p]),
                      ),
                    ...(adim.tur === "paywall"
                      ? {
                          planlar: (adim.planlar ?? []).map((p) => ({
                            anahtar: p,
                            ad: yayin.fiyatlar?.[p]?.ad ?? p,
                            fiyat: fiyatMetni(etkin(p)),
                            onerilen: p === adim.onerilen,
                          })),
                          secili: secilenPlan[adim.id] ?? adim.plan,
                          onPlanSec: (p: string) =>
                            setSecilenPlan((s) => ({ ...s, [adim.id]: p })),
                          kosul: kosulMetni(
                            etkin(secilenPlan[adim.id] ?? adim.plan),
                          ),
                          kalan: ((b) =>
                            b ? kalanSure(Date.parse(b) - simdi) : null)(
                            etkin(secilenPlan[adim.id] ?? adim.plan)?.teklif
                              ?.bitis,
                          ),
                          guvenceler: adim.guvenceler,
                          sartOnayi: adim.sartOnayi
                            ? {
                                onay: sartOnaylari[adim.id] ?? false,
                                onDegistir: (onay: boolean) =>
                                  setSartOnaylari((s) => ({
                                    ...s,
                                    [adim.id]: onay,
                                  })),
                              }
                            : undefined,
                        }
                      : {}),
                    onDugme: () => {
                      // Düğme ekran çekiminde etkin görünür; önizlemede ödeme hiç başlamaz.
                      if (onizleme) return;
                      if (adim.tur === "paywall")
                        void aboneOl(
                          secilenPlan[adim.id] ?? adim.plan,
                          Boolean(adim.sartOnayi && sartOnaylari[adim.id]),
                        );
                    },
                  }}
                />
              </div>
            );
          })}
        </div>
        {gomulu ? (
          <GomuluOdeme
            yapici={gomulu.yapici}
            anahtar={gomulu.anahtar}
            sir={gomulu.sir}
            onKapat={() => {
              setGomulu(null);
              // M3a: ödemeden kapatılan panel → downsell (gösterildiyse bir daha değil).
              downsellDene(tanim.adimlar[mevcut]);
            }}
          />
        ) : null}
        {(() => {
          const a = tanim.adimlar.find((x) => x.id === downsell);
          if (gomulu || a?.tur !== "paywall" || !a.downsell) return null;
          const d = a.downsell;
          const onayli = Boolean(a.sartOnayi && sartOnaylari[a.id]);
          return (
            <DownsellPenceresi
              baslik={d.baslik}
              metin={d.metin}
              dugme={d.dugme}
              fiyat={fiyatMetni(etkin(d.plan))}
              kosul={kosulMetni(etkin(d.plan))}
              sartOnayi={
                a.sartOnayi && a.sartlar
                  ? {
                      adres: a.sartlar,
                      onay: sartOnaylari[a.id] ?? false,
                      onDegistir: (onay: boolean) =>
                        setSartOnaylari((s) => ({ ...s, [a.id]: onay })),
                    }
                  : undefined
              }
              kapali={(onizleme && !ekranCekimi) || odeme.suruyor}
              onKabul={() => {
                setDownsell(null);
                if (onizleme) return;
                void aboneOl(d.plan, onayli);
              }}
              onRed={downsellKapat}
            />
          );
        })()}
        {!onizleme && izin !== null ? (
          <button
            className="izin-ayarlari"
            type="button"
            onClick={() => {
              setGeriAlGerekir(izin === "evet");
              setIzinHatasi("");
              setIzin(null);
            }}
          >
            Gizlilik ayarları
          </button>
        ) : null}
      </HareketBaglami.Provider>
    </SimgeBaglami.Provider>
  );
}
