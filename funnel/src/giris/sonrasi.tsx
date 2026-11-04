// M3b (A16): ödeme sonrası ekran — huninin tanımındaki `sonrasi` (başlık +
// bloklar, panelde düzenlenir). Giriş bağlantısı alanı sayfanın sabit
// parçası (basarili.ts): yönetici yalnız başlığı, blokları ve düğme metnini
// değiştirir — müşteriyi uygulamaya girilemez bırakamaz.
import { render } from "preact";
import { Blok, SimgeBaglami } from "../../../huni-bilesenleri/bilesenler";
import type { components } from "../sozlesme/api";

type Sonrasi = NonNullable<components["schemas"]["HuniTanimi"]["sonrasi"]>;
type Tema = components["schemas"]["HuniTanimi"]["tema"];

export function sonrasiCiz(
  sonrasi: Sonrasi,
  medyaKoku: string,
  tema: Tema,
): void {
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
      </SimgeBaglami.Provider>,
      kutu,
    );
  const dugme = document.getElementById("giris-baglantisi");
  if (dugme && sonrasi.dugme) dugme.textContent = sonrasi.dugme;
}
