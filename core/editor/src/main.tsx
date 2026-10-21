// Panel sürükle-bırak huni düzenleyicisi (A7 / E2.4). Twig sayfası
// #puck-duzenleyici'ye taslağı ve uç adreslerini öznitelik olarak gömer.
// Üst çubuk (b1): Kaydet · Önizle · Yayınla · Sürümler — rakiplerdeki gibi
// düzenleyicinin içinde. Doğrulama çekirdekte.
import { Puck, useGetPuck } from "@puckeditor/core";
import "@puckeditor/core/puck.css";
import { StrictMode, useCallback, useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import {
    puckTanim,
    tanimdanPuck,
    type HuniVerisi,
    type Tanim,
    type Tema,
} from "./donustur";
import { dugmeler, type YayinDurumu } from "./ustCubuk";
import { yapilandirma, type Ortam } from "./yapilandirma";

const kok = document.getElementById("puck-duzenleyici");
if (kok) {
    const ds = kok.dataset;
    const json = <T,>(ad: string): T => JSON.parse(ds[ad] ?? "null") as T;
    const ortam: Ortam = {
        planlar: json<string[]>("planlar"),
        yaziTipleri: json<string[]>("yaziTipleri"),
        medyaAdresi: ds["medya"] ?? "",
        jeton: ds["jeton"] ?? "",
    };
    createRoot(kok).render(
        <StrictMode>
            <Duzenleyici
                taslak={json<Tanim>("taslak")}
                varsayilanTema={json<Tema>("temaVarsayilan")}
                ortam={ortam}
                kaydetAdresi={ds["kaydet"] ?? ""}
                durumAdresi={ds["durum"] ?? ""}
                baslik={ds["baslik"] ?? ""}
            />
        </StrictMode>,
    );
}

type Bildirim = {
    tur: "tamam" | "hata";
    metin: string;
    hatalar: string[];
};

/** Kaydedilmemiş değişikliği bulmak için: aynı çeviriden geçen tanımın metni. */
const imza = (veri: HuniVerisi): string => JSON.stringify(puckTanim(veri));

function Duzenleyici({
    taslak,
    varsayilanTema,
    ortam,
    kaydetAdresi,
    durumAdresi,
    baslik,
}: {
    taslak: Tanim;
    varsayilanTema: Tema;
    ortam: Ortam;
    kaydetAdresi: string;
    durumAdresi: string;
    baslik: string;
}) {
    const [ilk, setIlk] = useState(() => tanimdanPuck(taslak, varsayilanTema));
    // Geri yüklemede Puck yeni veriyle baştan açılır (data yalnız ilk değer).
    const [acilis, setAcilis] = useState(0);
    const [kayitli, setKayitli] = useState(() => imza(ilk));
    const [guncel, setGuncel] = useState(() => imza(ilk));
    const [yayin, setYayin] = useState<YayinDurumu | null>(null);
    const [mesgul, setMesgul] = useState(false);
    const [durum, setDurum] = useState<Bildirim | null>(null);

    const yenile = useCallback(async (): Promise<void> => {
        try {
            const y = await fetch(durumAdresi);
            setYayin(y.ok ? ((await y.json()) as YayinDurumu) : null);
        } catch {
            setYayin(null);
        }
    }, [durumAdresi]);
    useEffect(() => {
        void yenile();
    }, [yenile]);

    /** POST + JSON; hatalar gövdeden, ağ hatası ayrı. */
    async function gonder(
        adres: string,
        govde?: unknown,
    ): Promise<{ ok: boolean; json: Record<string, unknown> }> {
        const y = await fetch(adres, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": ortam.jeton,
            },
            body: govde === undefined ? null : JSON.stringify(govde),
        });
        return {
            ok: y.ok,
            json: (await y.json()) as Record<string, unknown>,
        };
    }

    async function islem(
        calis: () => Promise<Bildirim>,
        hataMetni: string,
    ): Promise<void> {
        setDurum(null);
        setMesgul(true);
        try {
            setDurum(await calis());
        } catch {
            setDurum({ tur: "hata", metin: hataMetni, hatalar: [] });
        }
        await yenile();
        setMesgul(false);
    }

    const hatalar = (j: Record<string, unknown>): string[] =>
        Array.isArray(j["hatalar"]) ? (j["hatalar"] as string[]) : [];

    const kaydet = (veri: HuniVerisi): Promise<void> =>
        islem(async () => {
            const { ok, json } = await gonder(kaydetAdresi, {
                tanim: puckTanim(veri),
            });
            if (!ok)
                return {
                    tur: "hata",
                    metin: "Kaydedilmedi — düzelt:",
                    hatalar: hatalar(json),
                };
            setKayitli(imza(veri));
            return {
                tur: "tamam",
                metin: "Kaydedildi. Önizleyebilir ya da yayınlayabilirsin.",
                hatalar: [],
            };
        }, "Bağlantı hatası — kaydedilmedi.");

    const yayinla = (): Promise<void> =>
        islem(async () => {
            const { ok, json } = await gonder(`${durumAdresi}/yayinla`);
            return ok
                ? {
                      tur: "tamam",
                      metin: `v${String(json["surum"])} yayında. Ziyaretçiler en geç 1 dakika içinde görür.`,
                      hatalar: [],
                  }
                : {
                      tur: "hata",
                      metin: "Yayınlanmadı:",
                      hatalar: hatalar(json),
                  };
        }, "Bağlantı hatası — yayınlanmadı.");

    const geriYukle = (surum: number): Promise<void> =>
        islem(async () => {
            const { ok, json } = await gonder(
                `${durumAdresi}/surumler/${surum}/taslaga`,
            );
            if (!ok)
                return {
                    tur: "hata",
                    metin: "Geri yüklenmedi:",
                    hatalar: hatalar(json),
                };
            const veri = tanimdanPuck(json["tanim"] as Tanim, varsayilanTema);
            setIlk(veri);
            setKayitli(imza(veri));
            setGuncel(imza(veri));
            setAcilis((n) => n + 1);
            return {
                tur: "tamam",
                metin: `v${surum} taslağa alındı. Yayınlamak için "Yayınla".`,
                hatalar: [],
            };
        }, "Bağlantı hatası — geri yüklenmedi.");

    const acik = dugmeler(yayin, guncel !== kayitli, mesgul);

    return (
        <>
            {durum ? (
                <div role="status" className={`puck-durum ${durum.tur}`}>
                    <strong>{durum.metin}</strong>
                    {durum.hatalar.length ? (
                        <ul>
                            {durum.hatalar.map((h) => (
                                <li key={h}>{h}</li>
                            ))}
                        </ul>
                    ) : null}
                    <button type="button" onClick={() => setDurum(null)}>
                        ×
                    </button>
                </div>
            ) : null}
            <Puck
                key={acilis}
                config={yapilandirma(ortam)}
                data={ilk}
                headerTitle={baslik}
                onChange={(veri) => setGuncel(imza(veri))}
                overrides={{
                    // Puck'ın kendi "Publish" düğmesi yok: bizde kaydetmek
                    // demekti ve yanıltıcıydı (Ç45).
                    headerActions: () => (
                        <UstCubuk
                            acik={acik}
                            yayin={yayin}
                            onKaydet={(veri) => void kaydet(veri)}
                            onYayinla={() => void yayinla()}
                            onGeriYukle={(s) => void geriYukle(s)}
                        />
                    ),
                }}
            />
        </>
    );
}

function UstCubuk({
    acik,
    yayin,
    onKaydet,
    onYayinla,
    onGeriYukle,
}: {
    acik: ReturnType<typeof dugmeler>;
    yayin: YayinDurumu | null;
    onKaydet: (veri: HuniVerisi) => void;
    onYayinla: () => void;
    onGeriYukle: (surum: number) => void;
}) {
    // Güncel veri Puck'ın deposundan, tıklama anında.
    const getPuck = useGetPuck();
    return (
        <div className="puck-ust-cubuk">
            <span className="puck-not">{acik.not}</span>
            <button
                type="button"
                disabled={!acik.kaydet}
                onClick={() => onKaydet(getPuck().appState.data as HuniVerisi)}
            >
                Kaydet
            </button>
            <a
                href={acik.onizle && yayin ? yayin.onizleme : undefined}
                aria-disabled={!acik.onizle}
                target="_blank"
                rel="noopener"
                className="puck-dugme"
            >
                Önizle ↗
            </a>
            <button
                type="button"
                className="ana"
                disabled={!acik.yayinla}
                onClick={onYayinla}
            >
                Yayınla
            </button>
            <details className="puck-surumler">
                <summary>Sürümler</summary>
                <ul>
                    {(yayin?.surumler ?? []).map((s, i) => (
                        <li key={s.surum}>
                            <span>
                                v{s.surum}
                                {i === 0 ? " · yayında" : ""} ·{" "}
                                {s.zaman.slice(0, 16)} UTC · {s.ziyaret} ziyaret
                            </span>
                            <button
                                type="button"
                                disabled={!acik.geriYukle}
                                onClick={() => onGeriYukle(s.surum)}
                            >
                                Taslağa geri yükle
                            </button>
                        </li>
                    ))}
                    {yayin?.surumler.length === 0 ? (
                        <li>Henüz yayın yok.</li>
                    ) : null}
                </ul>
            </details>
        </div>
    );
}
