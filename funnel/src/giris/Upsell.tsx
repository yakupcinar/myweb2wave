// M3c (A16): ödeme sonrası tek tık ek ürün — aboneliğin kayıtlı kartıyla, kart
// tekrar girilmez; tutar düğmede yazar. Banka doğrulama isterse 3D Secure
// sayfada (Stripe.js), sonra aynı istek satışı kapatır.
import { useState } from "preact/hooks";
import { cekirdek } from "../lib/cekirdek";
import { tutarMetni } from "../lib/fiyat";
import { stripeYukle } from "../lib/stripe";

const HATA: Record<string, string> = {
  card_declined: "Kartınız bu ödemeyi kabul etmedi.",
  session_not_eligible: "Bu teklifin süresi doldu.",
  currency_mismatch: "Bu teklif şu an kullanılamıyor.",
};

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
        setHata("Banka doğrulaması tamamlanmadı.");
        return;
      }
      ({ data, error } = await iste());
    }
    if (data?.durum === "odendi") {
      setDurum("odendi");
      return;
    }
    setDurum("bos");
    setHata(
      (error && "error" in error && HATA[String(error.error)]) ||
        "Bir şeyler ters gitti. Lütfen tekrar deneyin.",
    );
  }
  return (
    <div className="upsell">
      <h2>{baslik}</h2>
      {metin ? <p>{metin}</p> : null}
      {durum === "odendi" ? (
        <p role="status" className="upsell-tamam">
          Eklendi — ödemeniz alındı.
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
            {dugme} · {tutarMetni(tutar, paraBirimi)}
          </button>
          <p className="kosul">
            Tek seferlik ödeme; abonelikte kullandığın kartından alınır.
          </p>
        </>
      )}
    </div>
  );
}
