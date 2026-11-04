// Stripe ödeme sonrası buraya döner (?session_id=…). Aboneliğin gerçek
// durumu webhook'la çekirdeğe yazılır. Sayfa çekirdekten tek kullanımlık
// giriş bağlantısı ister (Ç46); webhook sayfadan geç gelebilir → birkaç
// kez yeniden dener. Bağlantı alınamazsa e-posta ve "Aboneliğimi bağla"
// yolu kalır. Mobil görünüm 5: huni görünümünde, huninin temasıyla
// (oturum belleğinden, lib/sonuc-tema.ts).
import "../../../huni-bilesenleri/huni.css";
import { cekirdek } from "../lib/cekirdek";
import { temaUygula } from "../lib/sonuc-tema";
import { izinOku } from "../lib/izin";
import { satinAlmaOlayiGonder } from "../lib/reklam-pikselleri";
import { sonrasiCiz } from "./sonrasi";

temaUygula(import.meta.env.PUBLIC_CORE_URL);

const DENEME = 8;
const ARALIK_MS = 1500;

const adres = new URLSearchParams(window.location.search);
const oturum = adres.get("session_id");
// M3b: ödeme dönüşü huninin adını taşır — o huninin ödeme sonrası ekranı.
const huni = adres.get("huni");
// Ödeme kimliği adreste kalmasın: geçmişe ve sayfadaki betiklere
// sızmasın (Ç46). Yalnız bu sayfanın belleğinde yaşar.
if (oturum) window.history.replaceState(null, "", window.location.pathname);

// M3b: huninin `sonrasi` ekranı varsa başlık + bloklar + düğme metni; yoksa ya
// da okunamazsa sayfanın varsayılan metni kalır (giriş akışı her durumda aynı).
if (huni && /^[a-z0-9_-]{1,64}$/.test(huni))
  void cekirdek
    .GET("/huni/{app}", {
      params: { path: { app: "keepchat" }, query: { huni } },
    })
    .then(({ data }) => {
      if (data?.tanim.sonrasi)
        sonrasiCiz(
          data.tanim.sonrasi,
          import.meta.env.PUBLIC_CORE_URL,
          data.tanim.tema,
        );
    })
    .catch(() => undefined);

const goster = (id: string) => {
  const el = document.getElementById(id);
  if (el) el.hidden = false;
};
const durum = document.getElementById("durum");

async function iste(): Promise<string | undefined> {
  if (!oturum?.startsWith("cs_")) return undefined;
  for (let i = 0; i < DENEME; i++) {
    const { data, response } = await cekirdek
      .POST("/huni/giris-baglantisi", {
        body: { app: "keepchat", oturum },
      })
      .catch(() => ({ data: undefined, response: undefined }));
    if (data) return data.uygulama_adresi;
    // Yalnız "webhook henüz gelmedi" yeniden denenir.
    if (response?.status !== 404) return undefined;
    await new Promise((r) => setTimeout(r, ARALIK_MS));
  }
  return undefined;
}

void iste().then((adres) => {
  if (durum) durum.textContent = "Ödemeniz alındı.";
  const a = document.getElementById("giris-baglantisi");
  if (adres && a) {
    // Giriş bağlantısı yalnız ödenmiş Stripe oturumu doğrulanınca verilir.
    // session_id yukarıda URL'den silindi; yalnız izin varsa piksele gider.
    satinAlmaOlayiGonder({
      izin: izinOku() === "evet",
      oturum,
      metaPixelId: import.meta.env.PUBLIC_META_PIXEL_ID,
      tiktokPixelId: import.meta.env.PUBLIC_TIKTOK_PIXEL_ID,
    });
    a.setAttribute("href", adres);
    goster("giris");
    goster("kurulum");
  } else {
    goster("yedek");
  }
});
