// Kullanıcı Stripe ödeme sayfasından vazgeçince buraya döner. Mobil görünüm
// 5: huni görünümünde, huninin temasıyla (oturum belleğinden).
import "../../../huni-bilesenleri/huni.css";
import { metinleriUygula, sonucDili } from "../lib/sonuc-dil";
import { temaUygula } from "../lib/sonuc-tema";

temaUygula(import.meta.env.PUBLIC_CORE_URL);
// M9d2b: huninin dilinde.
metinleriUygula(sonucDili());
