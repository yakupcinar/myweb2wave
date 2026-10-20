// Panel sürükle-bırak huni düzenleyicisi (A7 / E2.4). Twig sayfası
// #puck-duzenleyici'ye taslağı ve uç adreslerini öznitelik olarak gömer.
// "Kaydet" taslağı çekirdeğin mevcut ucuna yollar; doğrulama orada.
import { Puck } from "@puckeditor/core";
import "@puckeditor/core/puck.css";
import { StrictMode, useState } from "react";
import { createRoot } from "react-dom/client";
import {
    puckTanim,
    tanimdanPuck,
    type HuniVerisi,
    type Tanim,
    type Tema,
} from "./donustur";
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
    const ilk = tanimdanPuck(
        json<Tanim>("taslak"),
        json<Tema>("temaVarsayilan"),
    );
    createRoot(kok).render(
        <StrictMode>
            <Duzenleyici
                ilk={ilk}
                ortam={ortam}
                kaydetAdresi={ds["kaydet"] ?? ""}
                baslik={ds["baslik"] ?? ""}
            />
        </StrictMode>,
    );
}

function Duzenleyici({
    ilk,
    ortam,
    kaydetAdresi,
    baslik,
}: {
    ilk: HuniVerisi;
    ortam: Ortam;
    kaydetAdresi: string;
    baslik: string;
}) {
    const [durum, setDurum] = useState<{
        tur: "tamam" | "hata";
        metin: string;
        hatalar: string[];
    } | null>(null);
    const kaydet = async (veri: HuniVerisi): Promise<void> => {
        setDurum(null);
        try {
            const y = await fetch(kaydetAdresi, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": ortam.jeton,
                },
                body: JSON.stringify({ tanim: puckTanim(veri) }),
            });
            const j = (await y.json()) as { hatalar?: string[] };
            setDurum(
                y.ok
                    ? {
                          tur: "tamam",
                          metin: "Kaydedildi. Yayınlamak için panele dön.",
                          hatalar: [],
                      }
                    : {
                          tur: "hata",
                          metin: "Kaydedilmedi — düzelt:",
                          hatalar: j.hatalar ?? [],
                      },
            );
        } catch {
            setDurum({
                tur: "hata",
                metin: "Bağlantı hatası — kaydedilmedi.",
                hatalar: [],
            });
        }
    };
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
                config={yapilandirma(ortam)}
                data={ilk}
                headerTitle={baslik}
                onPublish={(veri) => void kaydet(veri)}
                overrides={{
                    headerActions: ({ children }) => <>{children}</>,
                }}
            />
        </>
    );
}
