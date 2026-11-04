// M3a (A16): paywall'dan geri dönmeye çalışınca ya da ödeme panelini
// ödemeden kapatınca BİR KEZ sunulan plan. Fiyat ve ödeme koşulu Stripe'tan
// (fiyat.ts) — yönetici metni yalnız başlık/açıklama; sahte aciliyet yok.
import { useEffect, useRef, type ReactNode } from "react";

export default function DownsellPenceresi({
  baslik,
  metin,
  dugme,
  fiyat,
  kosul,
  sartOnayi,
  kapali,
  onKabul,
  onRed,
}: {
  baslik: string;
  metin?: string | undefined;
  dugme: string;
  fiyat?: string | undefined;
  kosul?: string | undefined;
  /** Paywall şart onayı istiyorsa aynı kutu burada da (aynı durum). */
  sartOnayi?:
    | { adres: string; onay: boolean; onDegistir: (onay: boolean) => void }
    | undefined;
  kapali: boolean;
  onKabul: () => void;
  onRed: () => void;
}): ReactNode {
  const ilk = useRef<HTMLHeadingElement>(null);
  useEffect(() => {
    ilk.current?.focus();
    const tus = (e: KeyboardEvent) => {
      if (e.key === "Escape") onRed();
    };
    window.addEventListener("keydown", tus);
    return () => window.removeEventListener("keydown", tus);
  }, [onRed]);
  return (
    <div
      className="odeme-katmani"
      role="dialog"
      aria-modal="true"
      aria-label="Teklif"
    >
      <div className="downsell">
        <h2 ref={ilk} tabIndex={-1}>
          {baslik}
        </h2>
        {metin ? <p>{metin}</p> : null}
        {fiyat ? <p className="fiyat">{fiyat}</p> : null}
        {sartOnayi ? (
          <label className="sart-onayi">
            <input
              type="checkbox"
              checked={sartOnayi.onay}
              onChange={(e) => sartOnayi.onDegistir(e.currentTarget.checked)}
            />
            <span>
              <a
                href={sartOnayi.adres}
                target="_blank"
                rel="noopener noreferrer"
              >
                Kullanım şartlarını
              </a>{" "}
              ve abonelik koşulunu okudum, kabul ediyorum.
            </span>
          </label>
        ) : null}
        <button
          type="button"
          className="ana"
          onClick={onKabul}
          disabled={kapali || (sartOnayi ? !sartOnayi.onay : false)}
        >
          {dugme}
        </button>
        {kosul ? <p className="kosul">{kosul}</p> : null}
        <button type="button" className="downsell-red" onClick={onRed}>
          Hayır, teşekkürler
        </button>
      </div>
    </div>
  );
}
