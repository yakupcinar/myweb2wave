// Reklam ölçümü onay şeridi (H6a, A12). Herkese gösterilir; Kabul ve Reddet
// eşit ağırlıkta (reddetmek kabul etmek kadar kolay). Sayfanın üstünde
// akışta durur — alttan sabit bir şerit küçük ekranda adım düğmelerini
// örterdi. Metin hukuken doğrulanmadı (Ç12).
import type { ReactNode } from "react";
import type { Izin } from "../lib/izin";

export default function IzinSeridi({
  onSec,
  bekliyor = false,
  hata,
}: {
  onSec: (izin: Izin) => void;
  bekliyor?: boolean;
  hata?: string;
}): ReactNode {
  return (
    <aside className="izin-seridi" aria-label="Reklam ölçümü izni">
      <p>
        Reklamlarımızın işe yarayıp yaramadığını ölçmek için Meta ve TikTok'un
        ölçüm araçlarını kullanmak istiyoruz. İzin verirsen hangi reklamdan
        geldiğin ve abone olursan bu satın alma onlarla paylaşılır. İzin
        vermesen de her şey aynı çalışır.
      </p>
      {hata ? <p role="alert">{hata}</p> : null}
      <div className="izin-dugmeleri">
        <button
          type="button"
          disabled={bekliyor}
          onClick={() => onSec("hayir")}
        >
          Reddet
        </button>
        <button type="button" disabled={bekliyor} onClick={() => onSec("evet")}>
          Kabul et
        </button>
      </div>
      {bekliyor ? <p role="status">Tercihiniz kaydediliyor…</p> : null}
    </aside>
  );
}
