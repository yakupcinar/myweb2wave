// M3b (A16): ödeme sonrası ekran — huninin tanımındaki `sonrasi` (başlık +
// bloklar, panelde düzenlenir). Giriş bağlantısı alanı sayfanın sabit
// parçası (basarili.ts): yönetici yalnız başlığı, blokları ve düğme metnini
// değiştirir — müşteriyi uygulamaya girilemez bırakamaz.
import { render } from "preact";
import { Blok, SimgeBaglami } from "../../../huni-bilesenleri/bilesenler";
import type { components } from "../sozlesme/api";
import Upsell from "./Upsell";

type Sonrasi = NonNullable<components["schemas"]["HuniTanimi"]["sonrasi"]>;
type Tema = components["schemas"]["HuniTanimi"]["tema"];
type EkUrunler = components["schemas"]["HuniYayini"]["ekUrunler"];

export function sonrasiCiz(
  sonrasi: Sonrasi,
  medyaKoku: string,
  tema: Tema,
  // M3c: upsell yalnız ödenmiş oturum kimliği ve okunabilen ürün fiyatıyla.
  ekUrunler?: EkUrunler,
  oturum?: string | null,
): void {
  const u = sonrasi.upsell;
  const fiyat = u ? ekUrunler?.[u.urun] : undefined;
  const baslik = document.querySelector("main h1");
  if (baslik) baslik.textContent = sonrasi.baslik;
  const kutu = document.getElementById("sonrasi");
  if (kutu)
    render(
      <SimgeBaglami.Provider
        value={{ stil: tema?.simgeler ?? "cihaz", kok: medyaKoku }}
      >
        <div className="bloklar">
          {sonrasi.bloklar.map((b, i) => (
            <Blok key={i} blok={b} medyaKoku={medyaKoku} />
          ))}
        </div>
        {u && fiyat && oturum ? (
          <Upsell
            app="keepchat"
            oturum={oturum}
            urun={u.urun}
            baslik={u.baslik}
            metin={u.metin}
            dugme={u.dugme}
            tutar={fiyat.tutar}
            paraBirimi={fiyat.paraBirimi}
          />
        ) : null}
      </SimgeBaglami.Provider>,
      kutu,
    );
  const dugme = document.getElementById("giris-baglantisi");
  if (dugme && sonrasi.dugme) dugme.textContent = sonrasi.dugme;
}
