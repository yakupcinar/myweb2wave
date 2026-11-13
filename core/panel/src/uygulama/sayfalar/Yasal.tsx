// Yasal metinler (M9b1, A23): şirket verileri + belge × dil metinleri. Kaydedilmemiş
// belge taslaktır ve yayında değildir; kaydetmek yayınlar. Veri
// /panel/api/uygulamalar/{app}/yasal (sözleşme PanelYasal); yazma kabuğun CSRF
// jetonuyla (api.ts). P7e: Uygulama sayfasının dilinde — Şirket bilgileri
// (solda etiket) ve Metinler bölümleri; belge kartında durum ve dil.
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { bolmeliSinif, bolmeSinifi } from "../ui/bolmeli";
import { SayfaBasi } from "../Duzen";
import { alanSinifi, Satir } from "../ui/Alan";
import { Bolum } from "../ui/Bolum";
import { cn } from "../ui/cn";
import { Dugme } from "../ui/Dugme";
import { Kart } from "../ui/Kart";
import { Rozet } from "../ui/Rozet";

type PanelYasal = components["schemas"]["PanelYasal"];
type YasalVeriler = components["schemas"]["YasalVeriler"];
type Belge = PanelYasal["belgeler"][number];

const VERI_ALANLARI: {
    ad: keyof YasalVeriler;
    etiket: string;
    azami: number;
    ornek: string;
}[] = [
    {
        ad: "unvan",
        etiket: "Şirket unvanı",
        azami: 200,
        ornek: "Örnek Yazılım A.Ş.",
    },
    { ad: "adres", etiket: "Adres", azami: 500, ornek: "Mahalle, ilçe, il" },
    {
        ad: "kayitNo",
        etiket: "Kayıt no (isteğe bağlı)",
        azami: 100,
        ornek: "MERSİS / vergi no",
    },
];

const DIL_ADI = { tr: "Türkçe", en: "İngilizce" } as const;

function hata(error: { hatalar?: string[] } | undefined): Error {
    return new Error((error?.hatalar ?? ["Kaydedilmedi."]).join(" "));
}

export function Yasal(): ReactNode {
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
                baslik="Yasal metinler"
                aciklama="Kullanım şartları, gizlilik, KVKK ve çerez sayfaları — Türkçe ve İngilizce."
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
                className="mb-7 rounded-[14px] bg-uyari-zemin px-[18px] py-3.5 text-[14px] text-metin"
            >
                Hazır gelen metinler{" "}
                <strong>taslaktır, hukuki görüş değildir</strong>; yayınlamadan
                önce bir hukukçuya gösterin. Kaydettiğiniz metin hemen yayına
                çıkar.
            </p>
            {secili ? (
                <YasalIcerik key={secili} app={secili} />
            ) : (
                <p className="text-soluk">Yükleniyor…</p>
            )}
        </div>
    );
}

function YasalIcerik({ app }: { app: string }): ReactNode {
    const sorgu = useQuery({
        queryKey: ["yasal", app],
        queryFn: async () => {
            const { data } = await api.GET(
                "/panel/api/uygulamalar/{app}/yasal",
                { params: { path: { app } } },
            );
            if (!data) throw new Error("yasal metinler alınamadı");
            return data;
        },
    });
    if (sorgu.isError)
        return (
            <p role="alert" className="text-tehlike">
                Yasal metinler yüklenemedi. Sayfayı yenileyin.
            </p>
        );
    // Form veri gelince çizilir (Baglam ile aynı sebep: yazılanı gelen veri ezmesin).
    if (!sorgu.data)
        return (
            <p role="status" className="text-soluk">
                Yükleniyor…
            </p>
        );
    const belgeler = [...new Set(sorgu.data.belgeler.map((b) => b.belge))];
    return (
        <div className="flex flex-col gap-7">
            <VeriFormu app={app} kayitli={sorgu.data.veriler} />
            <Bolum
                baslik="Metinler"
                aciklama="Kaydetmek yayınlar; kaydedilmemiş belgenin sayfası yoktur."
            >
                {belgeler.map((belge) => (
                    <BelgeKarti
                        key={belge}
                        app={app}
                        diller={sorgu.data.belgeler.filter(
                            (b) => b.belge === belge,
                        )}
                    />
                ))}
            </Bolum>
        </div>
    );
}

function VeriFormu({
    app,
    kayitli,
}: {
    app: string;
    kayitli: YasalVeriler;
}): ReactNode {
    const istemci = useQueryClient();
    const [form, setForm] = useState<YasalVeriler>(kayitli);
    const kayit = useMutation({
        mutationFn: async (govde: YasalVeriler) => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/yasal/veriler",
                {
                    params: { path: { app }, header: { "X-CSRF-Token": "" } },
                    body: govde,
                },
            );
            if (!data) throw hata(error);
            return data;
        },
        onSuccess: (data) => istemci.setQueryData(["yasal", app], data),
    });
    const degisti = JSON.stringify(form) !== JSON.stringify(kayitli);

    return (
        <Bolum
            baslik="Şirket bilgileri"
            aciklama="Metinlerdeki {{unvan}}, {{adres}}, {{kayitNo}} yerine geçer; iletişim e-postası ve uygulama adı Uygulama sayfasından."
        >
            <form
                className="flex flex-col gap-2.5"
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    kayit.mutate(form);
                }}
            >
                <Kart>
                    {VERI_ALANLARI.map((a) => (
                        <Satir key={a.ad} etiket={a.etiket}>
                            {(id) => (
                                <input
                                    id={id}
                                    value={form[a.ad]}
                                    maxLength={a.azami}
                                    placeholder={a.ornek}
                                    className={alanSinifi}
                                    onChange={(e) => {
                                        const deger = e.currentTarget.value;
                                        setForm((f) => ({
                                            ...f,
                                            [a.ad]: deger,
                                        }));
                                    }}
                                />
                            )}
                        </Satir>
                    ))}
                </Kart>
                <div className="flex flex-wrap items-center justify-end gap-3">
                    {kayit.isSuccess && !degisti ? (
                        <span role="status" className="text-iyi">
                            Kaydedildi.
                        </span>
                    ) : null}
                    {kayit.isError ? (
                        <span role="alert" className="text-tehlike">
                            {kayit.error.message}
                        </span>
                    ) : null}
                    <Dugme
                        tur="ana"
                        type="submit"
                        className="min-h-11 px-5.5 text-[15px]"
                        disabled={!degisti || kayit.isPending}
                    >
                        {kayit.isPending ? "Kaydediliyor…" : "Kaydet"}
                    </Dugme>
                </div>
            </form>
        </Bolum>
    );
}

function BelgeKarti({
    app,
    diller,
}: {
    app: string;
    diller: Belge[];
}): ReactNode {
    const [dil, setDil] = useState<Belge["dil"]>("tr");
    const secili = diller.find((d) => d.dil === dil) ?? diller[0];
    if (!secili) return null;
    return (
        <Kart>
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-kenar px-[18px] py-3.5">
                <h3 className="text-[17px] font-semibold">{secili.baslik}</h3>
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
            <MetinFormu
                key={`${secili.belge}/${secili.dil}/${secili.guncellendi ?? ""}`}
                app={app}
                belge={secili}
            />
        </Kart>
    );
}

function MetinFormu({ app, belge }: { app: string; belge: Belge }): ReactNode {
    const istemci = useQueryClient();
    const [metin, setMetin] = useState(belge.metin);
    const kayit = useMutation({
        mutationFn: async (govde: string) => {
            const { data, error } = await api.PUT(
                "/panel/api/uygulamalar/{app}/yasal/{belge}/{dil}",
                {
                    params: {
                        path: { app, belge: belge.belge, dil: belge.dil },
                        header: { "X-CSRF-Token": "" },
                    },
                    body: { metin: govde },
                },
            );
            if (!data) throw hata(error);
            return data;
        },
        onSuccess: (data) => istemci.setQueryData(["yasal", app], data),
    });
    const id = `metin-${belge.belge}-${belge.dil}`;

    return (
        <form
            className="grid gap-3 px-[18px] py-4"
            onSubmit={(e) => {
                e.preventDefault();
                kayit.mutate(metin);
            }}
        >
            <p className="flex min-w-0 flex-wrap items-center gap-2 text-[14px]">
                {belge.kayitli ? (
                    <>
                        <Rozet tur="iyi">Yayında</Rozet>
                        <a
                            href={belge.adres}
                            target="_blank"
                            rel="noreferrer"
                            className="min-w-0 truncate text-vurgu"
                        >
                            {belge.adres}
                        </a>
                    </>
                ) : (
                    <Rozet>Taslak — yayında değil</Rozet>
                )}
            </p>
            <label htmlFor={id} className="sr-only">
                {belge.baslik} ({DIL_ADI[belge.dil]})
            </label>
            <textarea
                id={id}
                rows={14}
                value={metin}
                onChange={(e) => setMetin(e.currentTarget.value)}
                className={cn(alanSinifi, "font-mono text-[13px]")}
            />
            <p className="text-[13px] text-soluk">
                "## " başlık, "- " madde, **kalın**, boş satır paragraf. HTML
                yazılmaz, olduğu gibi görünür.
            </p>
            {belge.eksikler.length > 0 ? (
                <p role="status" className="text-[13px] text-tehlike">
                    Boş değer sayfada "—" görünür:{" "}
                    {belge.eksikler.map((e) => `{{${e}}}`).join(", ")}
                </p>
            ) : null}
            <div className="flex flex-wrap items-center justify-end gap-3">
                {kayit.isError ? (
                    <span role="alert" className="text-tehlike">
                        {kayit.error.message}
                    </span>
                ) : null}
                <Dugme tur="ana" type="submit" disabled={kayit.isPending}>
                    {kayit.isPending ? "Kaydediliyor…" : "Kaydet ve yayınla"}
                </Dugme>
            </div>
        </form>
    );
}
