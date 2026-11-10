// Yasal metinler (M9b1, A23): şirket verileri + belge × dil metinleri. Kaydedilmemiş
// belge taslaktır ve yayında değildir; kaydetmek yayınlar. Veri
// /panel/api/uygulamalar/{app}/yasal (sözleşme PanelYasal); yazma kabuğun CSRF
// jetonuyla (api.ts).
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import type { components } from "../../sozlesme";
import { api } from "../api";
import { SayfaBasi } from "../Duzen";
import { Dugme } from "../ui/Dugme";
import { Kart, KartBasi } from "../ui/Kart";

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
        <>
            <SayfaBasi
                baslik="Yasal metinler"
                aciklama="Kullanım şartları, gizlilik, KVKK ve çerez sayfaları — Türkçe ve İngilizce."
            >
                {uygulamalar.data && uygulamalar.data.length > 1 ? (
                    <select
                        aria-label="Uygulama"
                        value={secili ?? ""}
                        onChange={(e) => setApp(e.currentTarget.value)}
                        className="min-h-9 rounded-lg border border-kenar bg-yuzey px-2.5"
                    >
                        {uygulamalar.data.map((a) => (
                            <option key={a}>{a}</option>
                        ))}
                    </select>
                ) : null}
            </SayfaBasi>
            <p
                role="note"
                className="mb-4 rounded-lg border border-kenar bg-yuzey px-4 py-3 text-[14px]"
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
        </>
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
        <div className="grid gap-4">
            <VeriFormu app={app} kayitli={sorgu.data.veriler} />
            {belgeler.map((belge) => (
                <BelgeKarti
                    key={belge}
                    app={app}
                    diller={sorgu.data.belgeler.filter(
                        (b) => b.belge === belge,
                    )}
                />
            ))}
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
        <Kart>
            <KartBasi
                baslik="Yasal veriler"
                aciklama="Metinlerdeki {{unvan}}, {{adres}}, {{kayitNo}} yerine geçer; iletişim e-postası ve uygulama adı Ayarlar'dan."
            />
            <form
                className="grid gap-4 px-4.5 py-4"
                noValidate
                onSubmit={(e) => {
                    e.preventDefault();
                    kayit.mutate(form);
                }}
            >
                {VERI_ALANLARI.map((a) => (
                    <div key={a.ad} className="grid gap-1.5">
                        <label
                            htmlFor={`yasal-${a.ad}`}
                            className="font-semibold"
                        >
                            {a.etiket}
                        </label>
                        <input
                            id={`yasal-${a.ad}`}
                            value={form[a.ad]}
                            maxLength={a.azami}
                            placeholder={a.ornek}
                            className="w-full rounded-lg border border-kenar bg-zemin px-3 py-2 text-metin"
                            onChange={(e) => {
                                const deger = e.currentTarget.value;
                                setForm((f) => ({ ...f, [a.ad]: deger }));
                            }}
                        />
                    </div>
                ))}
                <div className="flex flex-wrap items-center gap-3">
                    <Dugme
                        tur="ana"
                        type="submit"
                        disabled={!degisti || kayit.isPending}
                    >
                        {kayit.isPending ? "Kaydediliyor…" : "Kaydet"}
                    </Dugme>
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
                </div>
            </form>
        </Kart>
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
            <KartBasi baslik={secili.baslik}>
                <div
                    role="group"
                    aria-label="Dil"
                    className="inline-flex gap-0.5 rounded-[9px] bg-yuzey-2 p-[2px]"
                >
                    {diller.map((d) => (
                        <button
                            key={d.dil}
                            type="button"
                            aria-pressed={d.dil === dil}
                            onClick={() => setDil(d.dil)}
                            className="rounded-[7px] px-3 py-1 text-[13px] whitespace-nowrap text-metin hover:bg-yuzey/60 aria-pressed:bg-yuzey aria-pressed:font-semibold aria-pressed:shadow-sm"
                        >
                            {DIL_ADI[d.dil]}
                        </button>
                    ))}
                </div>
            </KartBasi>
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
            className="grid gap-3 px-4.5 py-4"
            onSubmit={(e) => {
                e.preventDefault();
                kayit.mutate(metin);
            }}
        >
            <p className="text-[14px]">
                {belge.kayitli ? (
                    <>
                        <span className="font-semibold text-iyi">Yayında</span>
                        {" · "}
                        <a href={belge.adres} target="_blank" rel="noreferrer">
                            {belge.adres}
                        </a>
                    </>
                ) : (
                    <span className="font-semibold text-soluk">
                        Taslak — yayında değil
                    </span>
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
                className="w-full rounded-lg border border-kenar bg-zemin px-3 py-2 font-mono text-[13px] text-metin"
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
            <div className="flex flex-wrap items-center gap-3">
                <Dugme tur="ana" type="submit" disabled={kayit.isPending}>
                    {kayit.isPending ? "Kaydediliyor…" : "Kaydet ve yayınla"}
                </Dugme>
                {kayit.isError ? (
                    <span role="alert" className="text-tehlike">
                        {kayit.error.message}
                    </span>
                ) : null}
            </div>
        </form>
    );
}
