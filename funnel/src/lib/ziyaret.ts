// Huni ziyareti ve anket ilerlemesi (A6); ziyaret görülen huni sürümünü taşır (A7). Çerez/tarayıcı deposu yok:
// ziyaret kimliği yalnız sayfanın belleğinde. Hiçbiri kullanıcıyı
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
  "fbclid",
  "ttclid",
] as const;

/** Sayfa açılınca bir kez: URL'deki reklam alanlarıyla ziyaret kaydı. */
export function ziyaretBaslat(
  app: Govde<"/huni/ziyaretler">["app"],
  surum?: number,
): Promise<string | undefined> {
  const adres = new URLSearchParams(window.location.search);
  const govde: Govde<"/huni/ziyaretler"> = { app };
  if (surum) govde.surum = surum;
  for (const ad of ALANLAR) {
    const deger = adres.get(ad)?.slice(0, 500);
    if (deger) govde[ad] = deger;
  }
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
