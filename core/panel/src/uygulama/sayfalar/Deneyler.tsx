// M4a: deneyler — trafik yönlendirme ya da aynı huni içinde içerik varyantı.
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import { api } from "../api";
import { SayfaBasi } from "../Duzen";
import { Kart, KartBasi } from "../ui/Kart";

type Varyant = { ad: string; pay: number; huni?: string };
type Deney = { ad: string; tur: "yonlendirme" | "icerik"; huni: string; varyantlar: Varyant[]; durum: "taslak" | "aktif" | "durdu" | "bitti"; baslangic: string | null; bitis: string | null };
type Yanit = { deneyler: Deney[]; hatalar?: string[] };

function jeton(): string {
    return document.getElementById("panel-uygulama")?.dataset["jeton"] ?? "";
}

async function istek(adres: string, method = "GET", body?: unknown): Promise<Yanit> {
    const yanit = await fetch(adres, {
        method,
        headers: method === "GET" ? {} : { "Content-Type": "application/json", "X-CSRF-Token": jeton() },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const veri = (await yanit.json()) as Yanit;
    if (!yanit.ok) throw new Error((veri.hatalar ?? ["İşlem yapılamadı."]).join(" "));
    return veri;
}

export function Deneyler(): ReactNode {
    const uygulamalar = useQuery({ queryKey: ["uygulamalar"], queryFn: async () => (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [] });
    const app = uygulamalar.data?.[0];
    if (!app) return <p className="text-soluk">Yükleniyor…</p>;
    return <DeneyListesi app={app} />;
}

function DeneyListesi({ app }: { app: string }): ReactNode {
    const anahtar = ["deneyler", app];
    const istemci = useQueryClient();
    const sorgu = useQuery({ queryKey: anahtar, queryFn: () => istek(`/panel/api/uygulamalar/${app}/deneyler`) });
    const [hata, setHata] = useState("");
    const [yeni, setYeni] = useState({ ad: "", tur: "icerik" as Deney["tur"], huni: "", a: "kontrol", b: "varyant", oran: "50", hedef: "" });
    const yaz = (d: Yanit) => istemci.setQueryData(anahtar, d);
    const kaydet = async (e: React.FormEvent) => {
        e.preventDefault(); setHata("");
        const varyantlar: Varyant[] = [
            { ad: yeni.a, pay: Number(yeni.oran), ...(yeni.tur === "yonlendirme" ? { huni: yeni.huni } : {}) },
            { ad: yeni.b, pay: 100 - Number(yeni.oran), ...(yeni.tur === "yonlendirme" ? { huni: yeni.hedef } : {}) },
        ];
        try { yaz(await istek(`/panel/api/uygulamalar/${app}/deneyler`, "POST", { ad: yeni.ad, tur: yeni.tur, huni: yeni.huni, varyantlar })); setYeni({ ...yeni, ad: "" }); } catch (x) { setHata(x instanceof Error ? x.message : "İşlem yapılamadı."); }
    };
    const durum = async (ad: string, durum: "aktif" | "durdu" | "bitti") => {
        setHata(""); try { yaz(await istek(`/panel/api/uygulamalar/${app}/deneyler/${ad}/durum`, "PUT", { durum })); } catch (x) { setHata(x instanceof Error ? x.message : "İşlem yapılamadı."); }
    };
    return <>
        <SayfaBasi baslik="Deneyler" aciklama="Taslakta varyantları kurun; aktif olduktan sonra sonuçlar karışmasın diye kilitlenir." />
        <Kart aria-label="Deneyler"><KartBasi baslik="Deneyler" aciklama="Yönlendirme trafiği hunilere böler, içerik ise koşullarda deney.ad olarak kullanılır." />
            {sorgu.isError ? <p role="alert">Deneyler yüklenemedi.</p> : null}
            {sorgu.data?.deneyler.map((d) => <div key={d.ad} className="mb-3 rounded-lg border border-kenar p-3"><strong>{d.ad}</strong> · {d.tur} · {d.huni} · {d.durum}<br /><small>{d.varyantlar.map(v => `${v.ad} %${v.pay}${v.huni ? ` → ${v.huni}` : ""}`).join(" · ")}</small><div className="mt-2 flex gap-2">{d.durum === "taslak" ? <button type="button" onClick={() => void durum(d.ad, "aktif")}>Başlat</button> : null}{d.durum === "aktif" ? <button type="button" onClick={() => void durum(d.ad, "durdu")}>Durdur</button> : null}{d.durum === "durdu" ? <button type="button" onClick={() => void durum(d.ad, "aktif")}>Sürdür</button> : null}{d.durum !== "bitti" && d.durum !== "taslak" ? <button type="button" onClick={() => void durum(d.ad, "bitti")}>Bitir</button> : null}</div></div>)}
            {!sorgu.isLoading && !sorgu.data?.deneyler.length ? <p className="text-soluk">Henüz deney yok.</p> : null}
        </Kart>
        <Kart className="mt-5" aria-label="Yeni deney"><KartBasi baslik="Yeni deney" />
            <form aria-label="Yeni deney" className="grid gap-3" onSubmit={(e) => void kaydet(e)}>
                <label>Deney adı<input required value={yeni.ad} onChange={e => setYeni({ ...yeni, ad: e.target.value })} /></label>
                <label>Tür<select value={yeni.tur} onChange={e => setYeni({ ...yeni, tur: e.target.value as Deney["tur"] })}><option value="icerik">İçerik</option><option value="yonlendirme">Yönlendirme</option></select></label>
                <label>{yeni.tur === "yonlendirme" ? "Kontrol hunisi" : "Huni"}<input required value={yeni.huni} onChange={e => setYeni({ ...yeni, huni: e.target.value })} /></label>
                <label>Kontrol varyantı<input required value={yeni.a} onChange={e => setYeni({ ...yeni, a: e.target.value })} /></label>
                <label>Kontrol payı (%)<input required type="number" min="1" max="99" value={yeni.oran} onChange={e => setYeni({ ...yeni, oran: e.target.value })} /></label>
                <label>Varyant<input required value={yeni.b} onChange={e => setYeni({ ...yeni, b: e.target.value })} /></label>
                {yeni.tur === "yonlendirme" ? <label>Varyant hunisi<input required value={yeni.hedef} onChange={e => setYeni({ ...yeni, hedef: e.target.value })} /></label> : null}
                {hata ? <p role="alert">{hata}</p> : null}<button type="submit">Taslağı kaydet</button>
            </form>
        </Kart>
    </>;
}
