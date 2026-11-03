// Huni düzenleyicisi sayfası (P3): Puck tam ekran, panelin sol menüsü yok.
// Açılış verisi (taslak, imza, uç adresleri, düzenleyici jetonu) tek uçtan;
// kayıt/yayın/medya uçları düzenleyicinin kendi uçları (değişmedi). Düzenleyici
// ağır (Puck) — yollar.tsx'te tembel yüklenir, Rapor ve Huniler onu indirmez.
import { useQuery } from "@tanstack/react-query";
import { useLayoutEffect, type ReactNode } from "react";
import { Link, useParams } from "react-router";
import { Duzenleyici } from "../../duzenleyici";
import { api } from "../api";

/** Eski Twig sayfasının gövde kuralı (önizleme çerçevesine de kopyalanır). */
const GOVDE = "body { margin: 0; font: 15px/1.45 system-ui, sans-serif; }";

/** İşaret kuralı taşıyan stil düğümleri (`<style>` ve `<link>`). */
function isaretli(secici: string): (HTMLStyleElement | HTMLLinkElement)[] {
    return [
        ...document.querySelectorAll<HTMLStyleElement | HTMLLinkElement>(
            'style, link[rel="stylesheet"]',
        ),
    ].filter((d) => {
        try {
            return [...(d.sheet?.cssRules ?? [])].some(
                (k) => "selectorText" in k && k.selectorText === secici,
            );
        } catch {
            return false; // başka kaynaktan sayfa (okunamaz) — bizim değil
        }
    });
}

/**
 * P3: stil yalıtımı iki yönlü. Düzenleyici açıkken panelin stil sayfası
 * kapalı (media="not all" — Puck'ın çerçeveye kopyaladığı düğüm de kapalı
 * gelir) ve Twig sayfasının gövde kuralı açık. Çıkınca panel geri açılır,
 * düzenleyicinin sayfası (Puck + huni.css; parça bir kez yüklenince sayfada
 * kalır) KAPANIR — huni.css'in :root --metin'i panelinkiyle aynı ad
 * (ölçüldü: dönüşte panel yazısı koyu zeminde koyu). Puck'ı bekletmek
 * gerekmedi: çerçeve eşzamansız yüklenir (ölçüldü, bekletmesiz de fark 0).
 */
export function useYalitim(): void {
    useLayoutEffect(() => {
        const panel = isaretli("#panel-stil-isareti").map(
            (d) => [d, d.getAttribute("media")] as const,
        );
        const duzenleyici = isaretli("#duzenleyici-stil-isareti");
        for (const [d] of panel) d.setAttribute("media", "not all");
        for (const d of duzenleyici) d.removeAttribute("media");
        const govde = document.createElement("style");
        govde.textContent = GOVDE;
        document.head.append(govde);
        return () => {
            govde.remove();
            for (const d of duzenleyici) d.setAttribute("media", "not all");
            for (const [d, m] of panel)
                if (m === null) d.removeAttribute("media");
                else d.setAttribute("media", m);
        };
    }, []);
}

export function HuniDuzenle(): ReactNode {
    useYalitim();
    const { app = "", ad = "" } = useParams();
    const acilis = useQuery({
        queryKey: ["duzenleyici", app, ad],
        // Açılış bir kez: pencereye dönünce yeniden çekmek açık taslağı ezerdi.
        staleTime: Infinity,
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/huniler/{ad}/duzenleyici",
                { params: { path: { app, ad } } },
            );
            if (!data) throw new Error("düzenleyici açılamadı");
            return data;
        },
    });

    return (
        <div className="puck-kok">
            <nav className="puck-ust">
                <Link to="/panel/huniler">← Huniler ve kurallar</Link>
            </nav>
            {acilis.isError ? (
                <p role="alert" className="p-4 text-tehlike">
                    Huni açılamadı ({app}/{ad}).
                </p>
            ) : acilis.data ? (
                <Duzenleyici
                    // Başka huniye geçince düzenleyici baştan kurulur.
                    key={`${app}/${ad}`}
                    taslak={acilis.data.taslak}
                    varsayilanTema={acilis.data.temaVarsayilan}
                    ortam={{
                        planlar: acilis.data.planlar,
                        yaziTipleri: acilis.data.yaziTipleri,
                        ozellikler: acilis.data.ozellikler,
                        medyaAdresi: acilis.data.adresler.medya,
                        jeton: acilis.data.jeton,
                    }}
                    kaydetAdresi={acilis.data.adresler.kaydet}
                    ilkImza={acilis.data.imza}
                    durumAdresi={acilis.data.adresler.durum}
                    baslik={acilis.data.baslik}
                    ai={{
                        durumAdresi: acilis.data.adresler.aiDurum,
                        sohbetAdresi: acilis.data.adresler.aiSohbet,
                    }}
                />
            ) : (
                <p role="status" className="p-4 text-soluk">
                    Yükleniyor…
                </p>
            )}
        </div>
    );
}
