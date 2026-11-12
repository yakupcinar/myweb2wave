// M6b2 (A19): uygulamayı silen müşteri de aboneliğini yönetsin — e-posta →
// çekirdek tek kullanımlık portal bağlantısı yollar. Yanıt abonelik olsun
// olmasın aynı (hesap sızdırmaz); sayfa da aynı metni gösterir.
import "../../../huni-bilesenleri/huni.css";
import { cekirdek } from "../lib/cekirdek";
import { METINLER, type Metinler } from "../../../huni-bilesenleri/metinler";
import { metinleriUygula, sonucDili } from "../lib/sonuc-dil";
import { temaUygula } from "../lib/sonuc-tema";

temaUygula(import.meta.env.PUBLIC_CORE_URL);

export function portalFormu(
  form: HTMLFormElement,
  durum: HTMLElement,
  m: Metinler = METINLER.tr,
): void {
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const eposta = String(new FormData(form).get("eposta") ?? "").trim();
    const dugme = form.querySelector("button");
    if (dugme) dugme.disabled = true;
    void cekirdek
      .POST("/huni/portal-baglantisi", {
        // E1a: e-posta sayfanın dilinde.
        body: { app: "keepchat", eposta, dil: m === METINLER.en ? "en" : "tr" },
      })
      .then(({ response }) => {
        durum.textContent =
          response.status === 202 ? m.portalGonderildi : m.portalEpostaKontrol;
      })
      .catch(() => {
        durum.textContent = m.portalGonderilemedi;
      })
      .finally(() => {
        durum.hidden = false;
        if (dugme) dugme.disabled = false;
      });
  });
}

const form = document.getElementById("portal-formu");
const durum = document.getElementById("portal-durum");
// M9d2b: e-postadaki bağlantıyla da gelinir — oturum belleği yoksa ?dil= / tarayıcı.
const m = metinleriUygula(sonucDili());
if (form instanceof HTMLFormElement && durum) portalFormu(form, durum, m);
