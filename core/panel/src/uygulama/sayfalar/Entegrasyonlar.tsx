// Entegrasyonlar (P7d, A25): Webhook'lar, API anahtarları ve Ürün bağlamı
// tek sayfada; A30: Meta bağlantısı (#meta). Eski adresler (/panel/webhooklar…) sunucuda #bölüm'e
// yönlenir; çizimden sonra oraya kaydırılır (tarayıcı ilk yüklemede bölümü
// henüz bulamaz).
import { useQuery } from "@tanstack/react-query";
import { useEffect, useState, type ReactNode } from "react";
import { useLocation } from "react-router";
import { api } from "../api";
import { SayfaBasi } from "../Duzen";
import { AnahtarBolumu } from "./Anahtarlar";
import { BaglamBolumu } from "./Baglam";
import { MetaBolumu } from "./Meta";
import { WebhookBolumu } from "./Webhooklar";

export function Entegrasyonlar(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    const [app, setApp] = useState<string | null>(null);
    const secili = app ?? uygulamalar.data?.[0] ?? null;
    const { hash } = useLocation();
    useEffect(() => {
        if (secili && hash)
            document.getElementById(hash.slice(1))?.scrollIntoView();
    }, [secili, hash]);

    return (
        <div className="max-w-[1040px]">
            <SayfaBasi
                baslik="Entegrasyonlar"
                aciklama="Kendi sunucuna olay gönderme, Meta reklam hesabı, Claude bağlantısı ve AI'ın ürün bilgisi."
            >
                {uygulamalar.data && uygulamalar.data.length > 1 ? (
                    <select
                        aria-label="Uygulama"
                        value={secili ?? ""}
                        onChange={(e) => setApp(e.currentTarget.value)}
                        className="min-h-9 rounded-lg border border-kenar-2 bg-yuzey px-2.5"
                    >
                        {uygulamalar.data.map((a) => (
                            <option key={a}>{a}</option>
                        ))}
                    </select>
                ) : null}
            </SayfaBasi>
            {secili ? (
                <div className="flex flex-col gap-9">
                    <WebhookBolumu key={`w-${secili}`} app={secili} />
                    <MetaBolumu key={`m-${secili}`} app={secili} />
                    <AnahtarBolumu key={`a-${secili}`} app={secili} />
                    <BaglamBolumu key={`b-${secili}`} app={secili} />
                </div>
            ) : (
                <p className="text-soluk">Yükleniyor…</p>
            )}
        </div>
    );
}
