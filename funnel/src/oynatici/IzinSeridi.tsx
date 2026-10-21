// Reklam ölçümü onay şeridi (H6a, A12). Herkese gösterilir; Kabul ve Reddet
// eşit ağırlıkta (reddetmek kabul etmek kadar kolay). Sayfanın üstünde
// akışta durur — alttan sabit bir şerit küçük ekranda adım düğmelerini
// örterdi. Metin hukuken doğrulanmadı (Ç12).
import type { ReactNode } from "react";
import type { Izin } from "../lib/izin";

export default function IzinSeridi({
  onSec,
}: {
  onSec: (izin: Izin) => void;
}): ReactNode {
  return (
    <aside className="izin-seridi" aria-label="Reklam ölçümü izni">
      <p>
        Reklamlarımızın işe yarayıp yaramadığını ölçmek için Meta ve TikTok'un
        ölçüm araçlarını kullanmak istiyoruz. İzin verirsen hangi reklamdan
        geldiğin ve abone olursan bu satın alma onlarla paylaşılır. İzin
        vermesen de her şey aynı çalışır.
      </p>
      <div className="izin-dugmeleri">
        <button type="button" onClick={() => onSec("hayir")}>
          Reddet
        </button>
        <button type="button" onClick={() => onSec("evet")}>
          Kabul et
        </button>
      </div>
    </aside>
  );
}
