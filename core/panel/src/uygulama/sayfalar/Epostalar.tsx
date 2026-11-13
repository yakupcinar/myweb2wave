// E-postalar (E1b): müşteriye giden yedi e-postanın konusu ve metni, şablon ×
// dil. Kaydedilmemiş şablonda koddaki varsayılan gider; "Varsayılana dön"
// kaydı siler. Veri /panel/api/uygulamalar/{app}/epostalar (sözleşme
// PanelEpostalar); yazma kabuğun CSRF jetonuyla (api.ts). Düzen Yasal
// metinlerdeki gibi: şablon kartında başlık ve dil, altında form.
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { bolmeliSinif, bolmeSinifi } from "../ui/bolmeli";
import { SayfaBasi } from "../Duzen";
import { alanSinifi } from "../ui/Alan";
import { cn } from "../ui/cn";
import { Dugme } from "../ui/Dugme";
import { Kart } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";

type Sablon = components["schemas"]["PanelEpostalar"]["sablonlar"][number];

const DIL_ADI = { tr: "Türkçe", en: "İngilizce" } as const;

function hata(error: { hatalar?: string[] } | undefined): Error {
    return new Error((error?.hatalar ?? ["Kaydedilmedi."]).join(" "));
}

const yerTutucu = (y: string) => `{{${y}}}`;

export function Epostalar(): ReactNode {
    const uygulamalar = useQuery({
        queryKey: ["uygulamalar"],
        queryFn: async () =>
            (await api.GET("/panel/api/uygulamalar")).data?.uygulamalar ?? [],
    });
    const [app, setApp] = useState<string | null>(null);
    const secili = app ?? uygulamalar.data?.[0] ?? null;

    return (
        <div className="max-w-[880px]">
            <SayfaBasi
                baslik="E-postalar"
                aciklama="Müşteriye giden e-postaların konusu ve metni — Türkçe ve İngilizce."
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
            <p
                role="note"
                className="mb-7 rounded-[14px] bg-yuzey-2 px-[18px] py-3.5 text-[14px] text-metin"
            >
                E-postalar düz metin gider; {"{{…}}"} yer tutucular gönderimde
                doldurulur. Düzenlemediğiniz e-postada varsayılan metin gider.
            </p>
            {secili ? (
                <EpostaIcerik key={secili} app={secili} />
            ) : (
                <p className="text-soluk">Yükleniyor…</p>
            )}
        </div>
    );
}

function EpostaIcerik({ app }: { app: string }): ReactNode {
    const sorgu = useQuery({
        queryKey: ["epostalar", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/epostalar",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("e-posta şablonları alınamadı");
            return data;
        },
    });
    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                E-posta şablonları yüklenemedi. Sayfayı yenileyin.
            </p>
        );
    // Form veri gelince çizilir (yazılanı gelen veri ezmesin).
    if (!sorgu.data)
        return (
            <p role="status" className="text-soluk">
                Yükleniyor…
            </p>
        );
    const sablonlar = [...new Set(sorgu.data.sablonlar.map((s) => s.sablon))];
    return (
        <div className="flex flex-col gap-2.5">
            {sablonlar.map((sablon) => (
                <SablonKarti
                    key={sablon}
                    app={app}
                    diller={sorgu.data.sablonlar.filter(
                        (s) => s.sablon === sablon,
                    )}
                />
            ))}
        </div>
    );
}

function SablonKarti({
    app,
    diller,
}: {
    app: string;
    diller: Sablon[];
}): ReactNode {
    const [dil, setDil] = useState<Sablon["dil"]>("tr");
    const secili = diller.find((d) => d.dil === dil) ?? diller[0];
    if (!secili) return null;
    return (
        <Kart aria-label={secili.ad}>
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-kenar px-[18px] py-3.5">
                <div className="min-w-0">
                    <h3 className="text-[17px] font-semibold">{secili.ad}</h3>
                    <p className="text-[13px] text-soluk">{secili.aciklama}</p>
                </div>
                <div role="group" aria-label="Dil" className={bolmeliSinif}>
                    {diller.map((d) => (
                        <button
                            key={d.dil}
                            type="button"
                            aria-pressed={d.dil === dil}
                            onClick={() => setDil(d.dil)}
                            className={bolmeSinifi}
                        >
                            {DIL_ADI[d.dil]}
                        </button>
                    ))}
                </div>
            </div>
            <SablonFormu
                key={`${secili.sablon}/${secili.dil}/${secili.guncellendi ?? ""}`}
                app={app}
                sablon={secili}
            />
        </Kart>
    );
}

function SablonFormu({
    app,
    sablon,
}: {
    app: string;
    sablon: Sablon;
}): ReactNode {
    const istemci = useQueryClient();
    const [konu, setKonu] = useState(sablon.konu);
    const [metin, setMetin] = useState(sablon.metin);
    const yol = {
        path: { app, sablon: sablon.sablon, dil: sablon.dil },
        header: { "X-CSRF-Token": "" },
    };
    const kayit = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/epostalar/{sablon}/{dil}",
                { params: yol, body: { konu, metin } },
            );
            if (!data) throw hata(error);
            return data;
        },
        onSuccess: (data) => istemci.setQueryData(["epostalar", app], data),
    });
    const donus = useMutation({
        mutationFn: async () => {
            const { data, error } = await api.DELETE(
                "/panel/api/uygulamalar/{app}/epostalar/{sablon}/{dil}",
                { params: yol },
            );
            if (!data) throw hata(error);
            return data;
        },
        onSuccess: (data) => istemci.setQueryData(["epostalar", app], data),
    });
    const degisti = konu !== sablon.konu || metin !== sablon.metin;
    const mesgul = kayit.isPending || donus.isPending;
    const id = `eposta-${sablon.sablon}-${sablon.dil}`;
    const hataMesaji = kayit.error?.message ?? donus.error?.message;

    return (
        <form
            className="grid gap-3 px-[18px] py-4"
            onSubmit={(e) => {
                e.preventDefault();
                kayit.mutate();
            }}
        >
            <p className="text-[14px]">
                {sablon.kayitli ? (
                    <Rozet tur="iyi">Düzenlendi</Rozet>
                ) : (
                    <Rozet>Varsayılan metin</Rozet>
                )}
            </p>
            <div className="grid gap-1.5">
                <label
                    htmlFor={`${id}-konu`}
                    className="text-[13px] text-soluk"
                >
                    Konu ({DIL_ADI[sablon.dil]})
                </label>
                <input
                    id={`${id}-konu`}
                    value={konu}
                    maxLength={200}
                    onChange={(e) => setKonu(e.currentTarget.value)}
                    className={alanSinifi}
                />
            </div>
            <div className="grid gap-1.5">
                <label
                    htmlFor={`${id}-metin`}
                    className="text-[13px] text-soluk"
                >
                    Metin ({DIL_ADI[sablon.dil]})
                </label>
                <textarea
                    id={`${id}-metin`}
                    rows={8}
                    value={metin}
                    maxLength={5000}
                    onChange={(e) => setMetin(e.currentTarget.value)}
                    className={cn(alanSinifi, "font-mono text-[13px]")}
                />
            </div>
            <p className="text-[13px] text-soluk">
                Kullanılabilir: {sablon.yerTutucular.map(yerTutucu).join(", ")}
                {" · "}metinde olmalı:{" "}
                {sablon.zorunlu.map(yerTutucu).join(", ")}
            </p>
            <div className="flex flex-wrap items-center justify-end gap-3">
                {hataMesaji ? (
                    <span role="alert" className="text-tehlike">
                        {hataMesaji}
                    </span>
                ) : null}
                {sablon.kayitli ? (
                    <Dugme disabled={mesgul} onClick={() => donus.mutate()}>
                        Varsayılana dön
                    </Dugme>
                ) : null}
                <Dugme tur="ana" type="submit" disabled={!degisti || mesgul}>
                    {kayit.isPending ? "Kaydediliyor…" : "Kaydet"}
                </Dugme>
            </div>
        </form>
    );
}
