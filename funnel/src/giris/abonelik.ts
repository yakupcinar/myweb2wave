// M6b2 (A19): uygulamayı silen müşteri de aboneliğini yönetsin — e-posta →
// çekirdek tek kullanımlık portal bağlantısı yollar. Yanıt abonelik olsun
// olmasın aynı (hesap sızdırmaz); sayfa da aynı metni gösterir.
import "../../../huni-bilesenleri/huni.css";
import { cekirdek } from "../lib/cekirdek";
import { temaUygula } from "../lib/sonuc-tema";

temaUygula(import.meta.env.PUBLIC_CORE_URL);

export function portalFormu(form: HTMLFormElement, durum: HTMLElement): void {
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const eposta = String(new FormData(form).get("eposta") ?? "").trim();
    const dugme = form.querySelector("button");
    if (dugme) dugme.disabled = true;
    void cekirdek
      .POST("/huni/portal-baglantisi", { body: { app: "keepchat", eposta } })
      .then(({ response }) => {
        durum.textContent =
          response.status === 202
            ? "Bu adrese bağlı bir abonelik varsa bağlantıyı gönderdik. E-postanı kontrol et (1 saat geçerli)."
            : "E-posta adresini kontrol edip yeniden dene.";
      })
      .catch(() => {
        durum.textContent = "Şu an gönderilemedi, birazdan yeniden dene.";
      })
      .finally(() => {
        durum.hidden = false;
        if (dugme) dugme.disabled = false;
      });
  });
}

const form = document.getElementById("portal-formu");
const durum = document.getElementById("portal-durum");
if (form instanceof HTMLFormElement && durum) portalFormu(form, durum);
