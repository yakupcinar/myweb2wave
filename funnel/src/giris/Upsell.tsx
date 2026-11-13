// M3c (A16): ödeme sonrası tek tık ek ürün — aboneliğin kayıtlı kartıyla, kart
// tekrar girilmez; tutar düğmede yazar. Banka doğrulama isterse 3D Secure
// sayfada (Stripe.js), sonra aynı istek satışı kapatır.
import { useState } from "preact/hooks";
import { METINLER, useDil } from "../../../huni-bilesenleri/metinler";
import { cekirdek } from "../lib/cekirdek";
import { tutarMetni } from "../lib/fiyat";
import { stripeYukle } from "../lib/stripe";

// M9d2b: çekirdeğin hata kodu → sözlük anahtarı.
const HATA = {
  card_declined: "kartReddetti",
  session_not_eligible: "teklifSuresiDoldu",
  currency_mismatch: "teklifKullanilamiyor",
} as const;

export default function Upsell({
  app,
  oturum,
  urun,
  baslik,
  metin,
  dugme,
  tutar,
  paraBirimi,
}: {
  app: string;
  oturum: string;
  urun: string;
  baslik: string;
  metin?: string | undefined;
  dugme: string;
  tutar: number;
  paraBirimi: string;
}) {
  const dil = useDil();
  const m = METINLER[dil];
  const [durum, setDurum] = useState<"bos" | "suruyor" | "odendi">("bos");
  const [hata, setHata] = useState("");
  async function sat(): Promise<void> {
    setDurum("suruyor");
    setHata("");
    const iste = () =>
      cekirdek
        .POST("/huni/ek-satis", { body: { app, oturum, urun } })
        .catch(() => ({ data: undefined, error: undefined }));
    let { data, error } = await iste();
    if (data?.durum === "dogrulama") {
      const yapici = await stripeYukle();
      const sonuc = yapici
        ? await yapici(data.anahtar).handleNextAction({
            clientSecret: data.clientSecret,
          })
        : { error: { message: "" } };
      if (sonuc.error) {
        setDurum("bos");
        setHata(m.bankaDogrulamasi);
        return;
      }
      ({ data, error } = await iste());
    }
    if (data?.durum === "odendi") {
      setDurum("odendi");
      return;
    }
    setDurum("bos");
    const kod = error && "error" in error ? String(error.error) : "";
    setHata(kod in HATA ? m[HATA[kod as keyof typeof HATA]] : m.birSeylerTers);
  }
  return (
    <div className="upsell">
      <h2>{baslik}</h2>
      {metin ? <p>{metin}</p> : null}
      {durum === "odendi" ? (
        <p role="status" className="upsell-tamam">
          {m.eklendiOdendi}
        </p>
      ) : (
        <>
          {hata ? <p role="alert">{hata}</p> : null}
          <button
            type="button"
            className="ana"
            disabled={durum === "suruyor"}
            onClick={() => void sat()}
          >
            {dugme} · {tutarMetni(tutar, paraBirimi, dil)}
          </button>
          <p className="kosul">{m.tekSeferlik}</p>
        </>
      )}
    </div>
  );
}
