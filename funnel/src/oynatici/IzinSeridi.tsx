// Reklam ölçümü onay şeridi (H6a, A12). Herkese gösterilir; Kabul ve Reddet
// eşit ağırlıkta (reddetmek kabul etmek kadar kolay). Sayfanın üstünde
// akışta durur — alttan sabit bir şerit küçük ekranda adım düğmelerini
// örterdi. Metin hukuken doğrulanmadı (Ç12).
import { useMetinler } from "../../../huni-bilesenleri/metinler";
import type { ReactNode } from "react";
import type { Izin } from "../lib/izin";

export default function IzinSeridi({
  onSec,
  bekliyor = false,
  hata,
  cerez,
  gizlilik,
}: {
  onSec: (izin: Izin) => void;
  bekliyor?: boolean;
  hata?: string;
  /** M9b2: yayındaki çerez bildirimi ve gizlilik politikası (yoksa bağlantı yok). */
  cerez?: string | undefined;
  gizlilik?: string | undefined;
}): ReactNode {
  const m = useMetinler();
  return (
    <aside className="izin-seridi" aria-label={m.izinSeridi}>
      <p>{m.izinMetni}</p>
      {cerez || gizlilik ? (
        <p className="izin-baglantilari">
          {cerez ? (
            <a href={cerez} target="_blank" rel="noopener noreferrer">
              {m.cerezBildirimi}
            </a>
          ) : null}
          {cerez && gizlilik ? " · " : null}
          {gizlilik ? (
            <a href={gizlilik} target="_blank" rel="noopener noreferrer">
              {m.gizlilikPolitikasi}
            </a>
          ) : null}
        </p>
      ) : null}
      {hata ? <p role="alert">{hata}</p> : null}
      <div className="izin-dugmeleri">
        <button
          type="button"
          disabled={bekliyor}
          onClick={() => onSec("hayir")}
        >
          {m.reddet}
        </button>
        <button type="button" disabled={bekliyor} onClick={() => onSec("evet")}>
          {m.kabulEt}
        </button>
      </div>
      {bekliyor ? <p role="status">{m.tercihKaydediliyor}</p> : null}
    </aside>
  );
}
