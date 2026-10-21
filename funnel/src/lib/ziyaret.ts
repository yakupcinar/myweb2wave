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

/** Sayfa açılınca bir kez: URL'deki reklam alanlarıyla ziyaret kaydı. */
export function ziyaretBaslat(
  app: Govde<"/huni/ziyaretler">["app"],
  huni?: string,
  surum?: number,
  izin = false,
): Promise<string | undefined> {
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
  if (izin) Object.assign(govde, tiklamaKimlikleri(), { izin: true });
  return cekirdek
    .POST("/huni/ziyaretler", { body: govde })
    .then(({ data }) => data?.ziyaret)
    .catch(() => undefined);
}

/** Adıma ulaşıldı ya da adımın cevabı verildi. Beklenmez, hata yutulur. */
export function ilerlemeBildir(
  ziyaretSozu: Promise<string | undefined>,
  sira: number,
  adim: string,
  cevap?: string,
): void {
  void ziyaretSozu
    .then((ziyaret) => {
      if (!ziyaret) return;
      const govde: Govde<"/huni/ziyaretler/{ziyaret}/ilerleme"> = {
        sira,
        adim,
      };
      if (cevap) govde.cevap = cevap;
      return cekirdek.POST("/huni/ziyaretler/{ziyaret}/ilerleme", {
        params: { path: { ziyaret } },
        body: govde,
      });
    })
    .catch(() => undefined);
}

/** Onay şeridinde "Kabul": izin + o anki tıklama kimlikleri. Beklenmez, hata yutulur. */
export function izinBildir(ziyaretSozu: Promise<string | undefined>): void {
  void ziyaretSozu
    .then((ziyaret) => {
      if (!ziyaret) return;
      const govde: Govde<"/huni/ziyaretler/{ziyaret}/izin"> =
        tiklamaKimlikleri();
      return cekirdek.POST("/huni/ziyaretler/{ziyaret}/izin", {
        params: { path: { ziyaret } },
        body: govde,
      });
    })
    .catch(() => undefined);
}
