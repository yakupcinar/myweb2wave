// M2d: Stripe gömülü ödeme paneli — paywall'ın üstünde, kapatınca paywall'a
// dönülür (oturum Stripe'ta süresi dolunca kendiliğinden kapanır, Ç59).
import { useEffect, useRef, useState, type ReactNode } from "react";
import type { GomuluOdeme as Odeme, StripeYapici } from "../lib/stripe";

export default function GomuluOdeme({
  yapici,
  anahtar,
  sir,
  onKapat,
}: {
  yapici: StripeYapici;
  anahtar: string;
  sir: string;
  onKapat: () => void;
}): ReactNode {
  const kutu = useRef<HTMLDivElement>(null);
  const kapat = useRef<HTMLButtonElement>(null);
  const [hata, setHata] = useState("");
  useEffect(() => {
    let odeme: Odeme | undefined;
    let bitti = false;
    yapici(anahtar)
      .createEmbeddedCheckoutPage({ fetchClientSecret: async () => sir })
      .then((o) => {
        if (bitti || !kutu.current) return o.destroy();
        odeme = o;
        o.mount(kutu.current);
      })
      .catch(() => setHata("Ödeme formu açılamadı. Lütfen tekrar deneyin."));
    return () => {
      bitti = true;
      odeme?.destroy();
    };
  }, [yapici, anahtar, sir]);
  useEffect(() => {
    kapat.current?.focus();
    const tus = (e: KeyboardEvent) => {
      if (e.key === "Escape") onKapat();
    };
    window.addEventListener("keydown", tus);
    return () => window.removeEventListener("keydown", tus);
  }, [onKapat]);
  return (
    <div
      className="odeme-katmani"
      role="dialog"
      aria-modal="true"
      aria-label="Ödeme"
    >
      <div className="odeme-paneli">
        <button
          ref={kapat}
          type="button"
          className="odeme-kapat"
          aria-label="Kapat"
          onClick={onKapat}
        >
          ×
        </button>
        {hata ? <p role="alert">{hata}</p> : null}
        <div ref={kutu} className="odeme-formu" />
      </div>
    </div>
  );
}
