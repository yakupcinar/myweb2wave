// Huni oynatıcısı (A7, B4/H2): tanımı çekirdekten okur (yayınlanmış en son
// sürüm, ya da ?onizleme=<anahtar> ile taslak) ve adımları TEK SAYFADA
// sırayla gösterir. Adımları panel önizlemesiyle aynı bileşenler çizer
// (huni-bilesenleri). Ölçüm (ziyaret, adım, cevap, ödeme) oynatıcının işi;
// tanımda ne olursa olsun otomatik. Önizlemede hiçbir şey kaydedilmez,
// ödeme kapalı. D1–D15: docs/decisions/huni-oynatici-h0.md (b).
import { useEffect, useRef, useState, type ReactNode } from "react";
import { Adim } from "../../../huni-bilesenleri/bilesenler";
import { temaDegiskenleri } from "../../../huni-bilesenleri/tema";
import { cekirdek } from "../lib/cekirdek";
import { izinOku, izinYaz, type Izin } from "../lib/izin";
import {
  ilerlemeBildir,
  izinBildir,
  ziyaretBaslat,
  type Govde,
} from "../lib/ziyaret";
import type { components } from "../sozlesme/api";
import { tanimIstegi } from "./erken-istek";
import IzinSeridi from "./IzinSeridi";

type Yayin = components["schemas"]["HuniYayini"];
type App = Govde<"/huni/ziyaretler">["app"];

declare global {
  interface Window {
    /** Sayfadaki satır içi betiğin başlattığı tanım isteği (erken-istek.ts). */
    huniTanimi?: Promise<unknown>;
  }
}

const KOK = import.meta.env.PUBLIC_CORE_URL;

export default function Huni({ app }: { app: App }): ReactNode {
  const [onizleme] = useState(() =>
    new URLSearchParams(window.location.search).has("onizleme"),
  );
  // undefined: yükleniyor · null: açılamadı
  const [yayin, setYayin] = useState<Yayin | null>();
  useEffect(() => {
    let iptal = false;
    void (window.huniTanimi ?? tanimIstegi(app, KOK)).then((y) => {
      if (!iptal) setYayin((y as Yayin | undefined) ?? null);
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
  // Ziyaret bir kez açılır; önizlemede hiç açılmaz, adım/cevap da gitmez.
  // Tıklama kimlikleri yalnız önceden verilmiş izinle (yoksa Kabul'de ayrıca).
  const [ziyaretSozu] = useState(() =>
    onizleme
      ? Promise.resolve(undefined)
      : ziyaretBaslat(
          app,
          yayin.huni,
          yayin.surum ?? undefined,
          izinOku() === "evet",
        ),
  );
  const [mevcut, setMevcut] = useState(0);
  const [secimler, setSecimler] = useState<Record<string, string[]>>({});
  const [serbestYanilar, setSerbestYanilar] = useState<Record<string, string>>(
    {},
  );
  // H4: geçiş animasyonu yalnız adım DEĞİŞİNCE — ilk adım hemen görünür
  // (opaklık 0'dan başlasa ilk anlamlı boyama gecikirdi). Adımla aynı
  // çizimde açılır; effect'te açılsa yeni adım bir kare görünüp titrerdi.
  const [gecti, setGecti] = useState(false);
  const [odeme, setOdeme] = useState({ suruyor: false, hata: "" });
  const kutu = useRef<HTMLDivElement>(null);
  const ilkAdim = useRef(true);

  // Tema: değerler çekirdekte doğrulandı; yine de CSS'e setProperty ile.
  useEffect(() => {
    document.title = tanim.sayfaBasligi;
    if (!tanim.tema) return;
    const kok = document.documentElement.style;
    for (const [ad, deger] of Object.entries(temaDegiskenleri(tanim.tema)))
      kok.setProperty(ad, deger);
  }, [tanim]);

  // Geri tuşu bir önceki adıma döner, sayfadan çıkmaz.
  useEffect(() => {
    history.replaceState({ adim: 0 }, "");
    const geri = (e: PopStateEvent): void => {
      setGecti(true);
      setMevcut((e.state as { adim?: number } | null)?.adim ?? 0);
    };
    window.addEventListener("popstate", geri);
    return () => window.removeEventListener("popstate", geri);
  }, []);

  // Her gösterilen adım bildirilir; ilk adım dışında başlığa odak.
  useEffect(() => {
    ilerlemeBildir(ziyaretSozu, mevcut, tanim.adimlar[mevcut]?.id ?? "");
    if (ilkAdim.current) {
      ilkAdim.current = false;
      return;
    }
    kutu.current
      ?.querySelector<HTMLElement>(`[data-sira="${mevcut}"] h1`)
      ?.focus();
  }, [mevcut, tanim, ziyaretSozu]);

  /** H5: hedef varsa o adıma (çekirdek yalnız ileriyi kabul eder), yoksa sıradakine. */
  function ileri(hedef?: string): void {
    const sira = hedef
      ? tanim.adimlar.findIndex((a) => a.id === hedef)
      : mevcut + 1;
    if (sira <= mevcut || sira >= tanim.adimlar.length) return;
    history.pushState({ adim: sira }, "");
    setGecti(true);
    setMevcut(sira);
  }

  // Paywall: plan adını çekirdeğe gönderir, dönen ödeme adresine yönlenir.
  async function aboneOl(plan: Govde<"/checkout/sessions">["plan"]) {
    setOdeme({ suruyor: true, hata: "" });
    let hata: string;
    try {
      const govde: Govde<"/checkout/sessions"> = { app, plan };
      const ziyaret = await ziyaretSozu;
      if (ziyaret) govde.ziyaret = ziyaret;
      const { data, error, response } = await cekirdek.POST(
        "/checkout/sessions",
        { body: govde },
      );
      if (data) {
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

  function izinSec(secim: Izin): void {
    izinYaz(secim);
    setIzin(secim);
    if (secim === "evet") izinBildir(ziyaretSozu);
  }

  return (
    <>
      {!onizleme && izin === null ? <IzinSeridi onSec={izinSec} /> : null}
      {onizleme ? (
        <p className="onizleme">
          Önizleme — taslak; hiçbir şey kaydedilmez, ödeme kapalı.
        </p>
      ) : null}
      {tanim.adimlar[mevcut]?.ilerlemeGoster !== false ? (
        <progress max={tanim.adimlar.length} value={mevcut + 1} />
      ) : null}
      <div ref={kutu} data-gecis={gecti ? "" : undefined}>
        {tanim.adimlar.map((adim, i) => {
          const soru = adim.tur === "soru" ? adim : null;
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
                  ileri(adim.tur === "tanitim" ? adim.hedef : undefined)
                }
                onSecim={(s) => {
                  if (adim.tur !== "soru") return;
                  ilerlemeBildir(
                    ziyaretSozu,
                    mevcut,
                    adim.id,
                    adim.secenekler?.[s]?.id,
                  );
                  ileri(adim.secenekler?.[s]?.hedef);
                }}
                secili={secili}
                cevapMetni={cevapMetni}
                onMetinDegistir={(metin) => {
                  if (adim.tur !== "soru" || adim.secimTuru !== "metin") return;
                  setSerbestYanilar((once) => ({ ...once, [adim.id]: metin }));
                }}
                onSecenekDegistir={(s) => {
                  if (adim.tur !== "soru") return;
                  const id = adim.secenekler?.[s]?.id;
                  if (!id) return;
                  setSecimler((once) => {
                    const seciliCevaplar = once[adim.id] ?? [];
                    return {
                      ...once,
                      [adim.id]: seciliCevaplar.includes(id)
                        ? seciliCevaplar.filter((cevap) => cevap !== id)
                        : [...seciliCevaplar, id],
                    };
                  });
                }}
                onCokluDevam={() => {
                  if (adim.tur !== "soru") return;
                  if (adim.secimTuru === "metin") {
                    if (adim.yanitZorunlu && !cevapMetni.trim()) return;
                    ilerlemeBildir(
                      ziyaretSozu,
                      mevcut,
                      adim.id,
                      undefined,
                      cevapMetni,
                    );
                    ileri();
                    return;
                  }
                  if (!cevaplar.length) return;
                  ilerlemeBildir(ziyaretSozu, mevcut, adim.id, cevaplar);
                  ileri();
                }}
                odeme={{
                  kapali: onizleme || odeme.suruyor,
                  hata: odeme.hata,
                  onDugme: () => {
                    if (adim.tur === "paywall") void aboneOl(adim.plan);
                  },
                }}
              />
            </div>
          );
        })}
      </div>
    </>
  );
}
