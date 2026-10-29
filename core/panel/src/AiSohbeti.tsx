// Panel AI asistanı (A3): Puck'ın eklenti çubuğundaki sohbet. İstem açık
// huninin KAYITLI taslağına uygulanır (A2); sonuç tuvale konur, yayınlamak
// yine yöneticide. Puck'tan bağımsız: veriyi alma/koyma dışarıdan gelir
// (eklenti sarmalayıcısı Puck deposuna bağlar; test kendi işlevini verir).
import { useEffect, useState, type ReactNode } from "react";
import { ARAC_ADI, sseOku, type AiDurumu, type AiOlayi } from "./ai";
import type { HuniVerisi, Tanim } from "./donustur";
import { OTURUM_DUSTU, oturumDustu } from "./oturum";

type Satir =
    | { tur: "istem"; metin: string }
    | { tur: "metin"; metin: string }
    | { tur: "adim"; ad: string; hata: boolean | null }
    | { tur: "hata"; metin: string }
    | { tur: "bitti"; metin: string };

export function AiSohbeti({
    durumAdresi,
    sohbetAdresi,
    jeton,
    kayitliMi,
    veriAl,
    taslakGeldi,
    geriAl,
}: {
    durumAdresi: string;
    sohbetAdresi: string;
    jeton: string;
    /** Tuvaldeki veri kayıtlı taslakla aynı mı (AI kayıtlı taslakla çalışır). */
    kayitliMi: (veri: HuniVerisi) => boolean;
    veriAl: () => HuniVerisi;
    /** AI taslağı kaydetti: tuvale konur, imza düzenleyiciye geçer. */
    taslakGeldi: (tanim: Tanim, imza: string) => void;
    /** İstekten önceki veriyi tuvale koyup kaydeder. */
    geriAl: (veri: HuniVerisi) => void;
}): ReactNode {
    const [durum, setDurum] = useState<AiDurumu | null>(null);
    const [istem, setIstem] = useState("");
    const [satirlar, setSatirlar] = useState<Satir[]>([]);
    const [mesgul, setMesgul] = useState(false);
    const [onceki, setOnceki] = useState<HuniVerisi | null>(null);

    useEffect(() => {
        let iptal = false;
        void fetch(durumAdresi)
            .then(async (y) =>
                y.ok && !oturumDustu(y) ? ((await y.json()) as AiDurumu) : null,
            )
            .catch(() => null)
            .then((d) => {
                if (!iptal) setDurum(d);
            });
        return () => {
            iptal = true;
        };
    }, [durumAdresi]);

    const ekle = (s: Satir): void => setSatirlar((once) => [...once, s]);

    async function gonder(): Promise<void> {
        const metin = istem.trim();
        if (!metin || mesgul) return;
        const simdiki = veriAl();
        if (!kayitliMi(simdiki)) {
            ekle({
                tur: "hata",
                metin: "Kaydedilmemiş değişiklik var — önce kaydet. AI kayıtlı taslakla çalışır.",
            });
            return;
        }
        setMesgul(true);
        setOnceki(null);
        ekle({ tur: "istem", metin });
        setIstem("");
        let kaydedildi = false;
        try {
            const y = await fetch(sohbetAdresi, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": jeton,
                },
                body: JSON.stringify({ istem: metin }),
            });
            if (oturumDustu(y)) throw new Error(OTURUM_DUSTU);
            if (!y.ok || !y.body) {
                const j = (await y.json().catch(() => ({}))) as {
                    hatalar?: string[];
                };
                ekle({
                    tur: "hata",
                    metin:
                        j.hatalar?.join(" ") ??
                        `İstek başarısız (${y.status}).`,
                });
                return;
            }
            await sseOku(y.body, (o: AiOlayi) => {
                switch (o.tur) {
                    case "metin":
                        ekle({ tur: "metin", metin: o.metin });
                        break;
                    case "arac":
                        ekle({ tur: "adim", ad: o.ad, hata: null });
                        break;
                    case "arac_sonucu":
                        // Son açık adımı sonuçla kapat.
                        setSatirlar((once) => {
                            let i = once.length - 1;
                            while (i >= 0) {
                                const s = once[i];
                                if (
                                    s?.tur === "adim" &&
                                    s.ad === o.ad &&
                                    s.hata === null
                                )
                                    break;
                                i--;
                            }
                            return i < 0
                                ? once
                                : once.map((s, j) =>
                                      j === i && s.tur === "adim"
                                          ? { ...s, hata: o.hata }
                                          : s,
                                  );
                        });
                        break;
                    case "taslak":
                        kaydedildi = true;
                        taslakGeldi(o.tanim as Tanim, o.imza ?? "");
                        break;
                    case "hata":
                        ekle({ tur: "hata", metin: o.mesaj });
                        break;
                    case "bitti":
                        ekle({
                            tur: "bitti",
                            metin: o.kaydedildi
                                ? `Taslağa uygulandı · ${o.tur_sayisi} tur${maliyet(o.maliyetMikrodolar)}. Kontrol edip yayınla.`
                                : `Taslak değişmedi · ${o.tur_sayisi} tur${maliyet(o.maliyetMikrodolar)}.`,
                        });
                        break;
                }
            });
        } catch (e) {
            ekle({
                tur: "hata",
                metin:
                    e instanceof Error && e.message === OTURUM_DUSTU
                        ? OTURUM_DUSTU
                        : "Bağlantı hatası — istek tamamlanmadı.",
            });
        } finally {
            if (kaydedildi) setOnceki(simdiki);
            setMesgul(false);
        }
    }

    if (durum === null) return <div className="ai-sohbet">Yükleniyor…</div>;
    if (!durum.acik)
        return (
            <div className="ai-sohbet">
                <h3>AI asistanı</h3>
                <p className="ai-kapali" role="status">
                    AI kapalı: sunucuda <code>ANTHROPIC_API_KEY</code> tanımlı
                    değil. Yönetici <code>core/.env.local</code> dosyasına
                    ekleyince açılır.
                </p>
            </div>
        );
    return (
        <div className="ai-sohbet">
            <h3>AI asistanı</h3>
            <ol className="ai-satirlar" aria-live="polite">
                {satirlar.map((s, i) => (
                    <li key={i} className={`ai-${s.tur}`}>
                        {s.tur === "adim"
                            ? `${ARAC_ADI[s.ad] ?? s.ad}${s.hata === null ? "…" : s.hata ? " — hata, düzeltiliyor" : " ✓"}`
                            : s.metin}
                    </li>
                ))}
            </ol>
            {onceki && !mesgul ? (
                <button
                    type="button"
                    className="ai-geri-al"
                    onClick={() => {
                        geriAl(onceki);
                        setOnceki(null);
                        ekle({
                            tur: "bitti",
                            metin: "Önceki taslağa dönüldü.",
                        });
                    }}
                >
                    Bu değişikliği geri al
                </button>
            ) : null}
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    void gonder();
                }}
            >
                <label>
                    İstek
                    <textarea
                        value={istem}
                        maxLength={4000}
                        rows={4}
                        placeholder="Ör. İkinci sorudan sonra çoklu seçimli bir 'hedeflerin neler' sorusu ekle; seçeneklere göre dallanmasın."
                        onChange={(e) => setIstem(e.currentTarget.value)}
                    />
                </label>
                <button
                    type="submit"
                    className="ana"
                    disabled={mesgul || !istem.trim()}
                >
                    {mesgul ? "Çalışıyor…" : "Gönder"}
                </button>
            </form>
            <p className="ai-not">
                Yalnız taslağı değiştirir; yayınlamak senin elinde. Model{" "}
                {durum.model}.
            </p>
        </div>
    );
}

function maliyet(mikrodolar: number | null): string {
    return mikrodolar === null
        ? ""
        : ` · ~${(mikrodolar / 1_000_000).toFixed(3)} $`;
}
