// Huni kabuğu (A7, B4/H2): statik HTML + tek Preact kökü. İçerik çalışma
// anında geldiği için sunucuda çizilecek veri yok; "Yükleniyor…" HTML'de,
// oynatıcı kurulunca yerini alır. Görünüm panel düzenleyicisiyle ortak (H1).
import "../../../huni-bilesenleri/huni.css";
import { render } from "preact";
import Huni, { type App } from "../oynatici/Huni";

const kok = document.getElementById("huni");
if (kok) {
  kok.textContent = "";
  render(<Huni app={kok.dataset["app"] as App} />, kok);
}
