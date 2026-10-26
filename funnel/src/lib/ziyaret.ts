// Huni ziyareti ve anket ilerlemesi (A6); ziyaret görülen huniyi ve sürümünü taşır (A7, A8). Çerez yok:
// ziyaret kimliği yalnız sayfanın belleğinde. Tıklama kimlikleri (fbclid, ttclid) yalnız reklam ölçümü
// izniyle gider (H6a, A12; izin seçimi lib/izin.ts). Hiçbiri kullanıcıyı
// durdurmaz — kayıt başarısızsa huni ve ödeme yine çalışır.
import { cekirdek } from "./cekirdek";
import type { paths } from "../sozlesme/api";

// Gövdeler sözleşmeden üretilen tiple kurulur: yanlış alan adı tip
// hatasıdır. Nesne yayma (...) fazla alanı DENETLEMEZ (tuzak §25).
export type Govde<P extends keyof paths> = paths[P] extends {
  post: { requestBody: { content: { "application/json": infer G } } };
}
  ? G
  : never;

const ALANLAR = [
  "utm_source",
  "utm_medium",
  "utm_campaign",
  "utm_content",
  "utm_term",
] as const;
/** Kişiye bağlanabilen tıklama kimlikleri: yalnız izinle (A12). */
const TIKLAMA = ["fbclid", "ttclid"] as const;

function tiklamaKimlikleri(): { fbclid?: string; ttclid?: string } {
  const adres = new URLSearchParams(window.location.search);
  const k: { fbclid?: string; ttclid?: string } = {};
  for (const ad of TIKLAMA) {
    const deger = adres.get(ad)?.slice(0, 500);
    if (deger) k[ad] = deger;
  }
  return k;
}

/**
 * Ç34: aynı sekmede yeniden yükleme yeni ziyaret saymasın — kimlik sekme
 * deposunda (sekme kapanınca biter; çerez değil). Anahtar huni + sürüm:
 * başka sürüm başka ziyarettir. Depo engelliyse her yükleme yeni ziyaret.
 */
const sekmeAnahtari = (app: string, huni?: string, surum?: number): string =>
  `huni-ziyaret:${app}:${huni ?? ""}:${surum ?? ""}`;

function sekmedenOku(anahtar: string): string | undefined {
  try {
    return window.sessionStorage.getItem(anahtar) ?? undefined;
  } catch {
    return undefined;
  }
}

function sekmeyeYaz(anahtar: string, ziyaret: string): void {
  try {
    window.sessionStorage.setItem(anahtar, ziyaret);
  } catch {
    // engelli depo: ziyaret yine çalışır, yalnız yenilemede tekrar sayılır
  }
}

/** Sayfa açılınca bir kez: URL'deki reklam alanlarıyla ziyaret kaydı. */
export function ziyaretBaslat(
  app: Govde<"/huni/ziyaretler">["app"],
  huni?: string,
  surum?: number,
  /** Ç56 (3): önceki tercih — "hayir" de çekirdeğe gider (oran raporu). */
  izin: "evet" | "hayir" | null = null,
): Promise<string | undefined> {
  const anahtar = sekmeAnahtari(app, huni, surum);
  const onceki = sekmedenOku(anahtar);
  if (onceki) {
    const soz = Promise.resolve(onceki);
    // Ziyaret izinsiz açılmış olabilir; izin sonradan verildiyse bildir.
    if (izin === "evet") void izinBildir(soz);
    return soz;
  }
  const adres = new URLSearchParams(window.location.search);
  const govde: Govde<"/huni/ziyaretler"> = { app };
  if (huni && surum) {
    govde.huni = huni;
    govde.surum = surum;
  }
  for (const ad of ALANLAR) {
    const deger = adres.get(ad)?.slice(0, 500);
    if (deger) govde[ad] = deger;
  }
  if (izin === "evet")
    Object.assign(govde, tiklamaKimlikleri(), { izin: true });
  else if (izin === "hayir") govde.izin = false;
  return cekirdek
    .POST("/huni/ziyaretler", { body: govde })
    .then(({ data }) => {
      if (data?.ziyaret) sekmeyeYaz(anahtar, data.ziyaret);
      return data?.ziyaret;
    })
    .catch(() => undefined);
}

/** Adıma ulaşıldı ya da adımın cevabı verildi. Beklenmez, hata yutulur. */
export function ilerlemeBildir(
  ziyaretSozu: Promise<string | undefined>,
  sira: number,
  adim: string,
  cevap?: string | string[],
  cevapMetni?: string,
): void {
  void ziyaretSozu
    .then((ziyaret) => {
      if (!ziyaret) return;
      const govde: Govde<"/huni/ziyaretler/{ziyaret}/ilerleme"> = {
        sira,
        adim,
      };
      if (typeof cevap === "string") govde.cevap = cevap;
      else if (cevap) govde.cevaplar = cevap;
      if (cevapMetni !== undefined) govde.cevapMetni = cevapMetni;
      return cekirdek.POST("/huni/ziyaretler/{ziyaret}/ilerleme", {
        params: { path: { ziyaret } },
        body: govde,
      });
    })
    .catch(() => undefined);
}

/**
 * Onay şeridinde "Kabul": izin + o anki tıklama kimlikleri. Hata yutulur.
 * Söz istek bitince çözülür: geri alma onu bekler, yoksa önce varan silmeyi
 * geç varan Kabul ezer (Ç58).
 */
export function izinBildir(
  ziyaretSozu: Promise<string | undefined>,
): Promise<void> {
  return ziyaretSozu
    .then((ziyaret) => {
      if (!ziyaret) return;
      const govde: Govde<"/huni/ziyaretler/{ziyaret}/izin"> =
        tiklamaKimlikleri();
      return cekirdek.POST("/huni/ziyaretler/{ziyaret}/izin", {
        params: { path: { ziyaret } },
        body: govde,
      });
    })
    .then(
      () => undefined,
      () => undefined,
    );
}

/** İzni geri alır; çekirdek eski tıklama/IP/UA'yı ve bekleyen event payload'larını siler. */
export async function izinGeriAl(
  ziyaretSozu: Promise<string | undefined>,
): Promise<boolean> {
  try {
    const ziyaret = await ziyaretSozu;
    if (!ziyaret) return true;
    const { response } = await cekirdek.DELETE(
      "/huni/ziyaretler/{ziyaret}/izin",
      { params: { path: { ziyaret } } },
    );
    return response.status === 204 || response.status === 404;
  } catch {
    return false;
  }
}
