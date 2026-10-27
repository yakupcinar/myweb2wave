// Panel sürükle-bırak huni düzenleyicisi (A7 / E2.4). Twig sayfası
// #puck-duzenleyici'ye taslağı ve uç adreslerini öznitelik olarak gömer.
// Üst çubuk (b1): Kaydet · Önizle · Yayınla · Sürümler — rakiplerdeki gibi
// düzenleyicinin içinde. Doğrulama çekirdekte.
import {
    createUsePuck,
    Puck,
    useGetPuck,
    type Config,
    type Data,
} from "@puckeditor/core";
import "@puckeditor/core/puck.css";
import { StrictMode, useCallback, useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import {
    puckTanim,
    tanimdanPuck,
    type Bilesenler,
    type HuniVerisi,
    type KokProps,
    type Tanim,
    type Tema,
} from "./donustur";
import { hedefleriYenidenAdlandir, ulasilamayanlar } from "./akis";
import { OTURUM_DUSTU, oturumDustu } from "./oturum";
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
                ilkImza={ds["imza"] ?? ""}
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
    ilkImza,
    durumAdresi,
    baslik,
}: {
    taslak: Tanim;
    varsayilanTema: Tema;
    ortam: Ortam;
    kaydetAdresi: string;
    /** Ç39: açılıştaki taslak imzası; çekirdek eşleşmezse 409 (başka sekme kaydetti). */
    ilkImza: string;
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
    const [imzaDegeri, setImza] = useState(ilkImza);
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
        if (oturumDustu(y)) throw new Error(OTURUM_DUSTU);
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
        } catch (e) {
            const metin =
                e instanceof Error && e.message === OTURUM_DUSTU
                    ? OTURUM_DUSTU
                    : hataMetni;
            setDurum({ tur: "hata", metin, hatalar: [] });
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
                imza: imzaDegeri,
            });
            if (!ok)
                return {
                    tur: "hata",
                    metin: "Kaydedilmedi — düzelt:",
                    hatalar: hatalar(json),
                };
            setKayitli(imza(veri));
            setImza(String(json["imza"]));
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
            setImza(String(json["imza"]));
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

    /**
     * Tıklama anında Puck deposundan bir kez daha denetlenir: düğmenin açık
     * olması onChange'le güncellenen duruma bağlı ve kaydetmeden sonra bu
     * durum (gizli sekmede) saniyelerce eski kaldı — ölçüldü,
     * huni-dallanma-h5.md. Doğruluğun kaynağı depo.
     */
    function kaydedilmis(veri: HuniVerisi): boolean {
        const simdi = imza(veri);
        if (simdi === kayitli) return true;
        setGuncel(simdi);
        setDurum({
            tur: "hata",
            metin: "Kaydedilmemiş değişiklik var — önce kaydet.",
            hatalar: [],
        });
        return false;
    }

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
                        <>
                            <AkisBekcisi />
                            <UstCubuk
                                acik={acik}
                                yayin={yayin}
                                onKaydet={(veri) => void kaydet(veri)}
                                onYayinla={(veri) => {
                                    if (kaydedilmis(veri)) void yayinla();
                                }}
                                onGeriYukle={(s, veri) => {
                                    if (kaydedilmis(veri)) void geriYukle(s);
                                }}
                            />
                        </>
                    ),
                }}
            />
        </>
    );
}

const usePuck = createUsePuck<Config<Bilesenler, KokProps>>();

/**
 * Ç55: adım kimliği değişince ona işaret eden hedefleri taşır (Puck'ın
 * kendi deposuna setData) ve hiçbir yolla varılamayan adımları uyarır —
 * yayın engellenmez (A13).
 */
function AkisBekcisi() {
    const veri = usePuck((s) => s.appState.data);
    const dispatch = usePuck((s) => s.dispatch);
    const onceki = useRef(veri);
    useEffect(() => {
        const duzelmis = hedefleriYenidenAdlandir(onceki.current, veri);
        onceki.current = duzelmis ?? veri;
        // Puck dispatch tipi yapılandırma türünü taşımıyor (0.23).
        if (duzelmis)
            dispatch({ type: "setData", data: duzelmis as unknown as Data });
    }, [veri, dispatch]);
    const kopuk = ulasilamayanlar(puckTanim(veri));
    return kopuk.length ? (
        <span className="puck-not puck-not-uyari" role="status">
            Hiçbir yoldan ulaşılmayan adım: {kopuk.join(", ")}
        </span>
    ) : null;
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
    onYayinla: (veri: HuniVerisi) => void;
    onGeriYukle: (surum: number, veri: HuniVerisi) => void;
}) {
    // Güncel veri Puck'ın deposundan, tıklama anında.
    const getPuck = useGetPuck();
    const guncelVeri = (): HuniVerisi => getPuck().appState.data as HuniVerisi;
    return (
        <div className="puck-ust-cubuk">
            <span
                className={`puck-not puck-not-${acik.durum}`}
                role="status"
                aria-live="polite"
            >
                {acik.durum === "mesgul" ? "İşleniyor…" : acik.not}
            </span>
            <button
                type="button"
                disabled={!acik.kaydet}
                onClick={() => onKaydet(guncelVeri())}
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
                onClick={() => onYayinla(guncelVeri())}
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
                                onClick={() =>
                                    onGeriYukle(s.surum, guncelVeri())
                                }
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
