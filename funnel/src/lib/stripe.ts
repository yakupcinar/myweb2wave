// M2d: gömülü ödeme için Stripe.js — PCI gereği js.stripe.com'dan yüklenir,
// pakete girmez (JS bütçesine de girmez). Sürüm hesabın API sürümüyle aynı
// (2026-09-30.endive — gerçek API'den okundu). Yüklenemezse (reklam engelleyici,
// ağ) null: oynatıcı Stripe'ın barındırılan sayfasına yönlenir, ödeme kırılmaz.
const ADRES = "https://js.stripe.com/endive/stripe.js";

export type GomuluOdeme = {
  mount(kutu: HTMLElement): void;
  destroy(): void;
};
export type StripeYapici = (anahtar: string) => {
  createEmbeddedCheckoutPage(secenekler: {
    fetchClientSecret: () => Promise<string>;
  }): Promise<GomuluOdeme>;
  /** M3c: ek ürün ödemesinde banka doğrulaması (3D Secure). */
  handleNextAction(secenekler: {
    clientSecret: string;
  }): Promise<{ error?: { message?: string } }>;
};

let soz: Promise<StripeYapici | null> | null = null;

export function stripeYukle(zamanAsimi = 8000): Promise<StripeYapici | null> {
  const p = window as unknown as { Stripe?: StripeYapici };
  if (p.Stripe) return Promise.resolve(p.Stripe);
  if (soz) return soz;
  soz = new Promise((coz) => {
    const betik = document.createElement("script");
    betik.src = ADRES;
    betik.async = true;
    const bekleme = setTimeout(() => {
      soz = null;
      coz(null);
    }, zamanAsimi);
    betik.onload = () => {
      clearTimeout(bekleme);
      coz(p.Stripe ?? null);
    };
    betik.onerror = () => {
      clearTimeout(bekleme);
      soz = null;
      coz(null);
    };
    document.head.append(betik);
  });
  return soz;
}
