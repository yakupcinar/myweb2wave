// Panel düzeni (B5): solda menü (dar ekranda üstte), sağda sayfa. Bulunulan
// sayfa NavLink'le işaretlenir (aria-current). P6: Apple tarzı — menü dört
// grupta (Analiz · Satış · Müşteriler · Ayarlar); dar ekranda grup adları
// gizlenir, bağlantılar tek satırda kayar. P7a: menünün altında tema seçici.
// P7d (A25): Webhook'lar, API anahtarları, Ürün bağlamı → tek Entegrasyonlar.
import {
    ChartColumn,
    FlaskConical,
    Filter,
    LogOut,
    Plug,
    Scale,
    Settings,
    Tags,
    Users,
} from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import { Link, NavLink, Outlet } from "react-router";
import { sistemiDinle, temaOku, temaSec, type Tema } from "./tema";
import { bolmeliSinif, bolmeSinifi } from "./ui/bolmeli";
import { cn } from "./ui/cn";

const ogeSinifi =
    "flex items-center gap-2.5 rounded-lg px-2.5 py-[7px] text-metin hover:bg-yuzey-2 [&>svg]:text-vurgu aria-[current=page]:bg-vurgu aria-[current=page]:font-medium aria-[current=page]:text-vurgu-yazi [&[aria-current=page]>svg]:text-vurgu-yazi";

function Grup({
    ad,
    children,
}: {
    ad: string;
    children: ReactNode;
}): ReactNode {
    return (
        <div className="contents md:flex md:flex-col md:gap-0.5">
            <span className="hidden px-2.5 pb-1 text-[11px] font-semibold tracking-wider text-soluk uppercase md:block">
                {ad}
            </span>
            {children}
        </div>
    );
}

export function Duzen(): ReactNode {
    return (
        <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] md:grid-cols-[248px_minmax(0,1fr)]">
            <nav
                aria-label="Panel"
                className="flex flex-wrap items-center gap-1 border-b border-kenar bg-yuzey-2 px-3 py-2.5 md:sticky md:top-0 md:h-screen md:flex-col md:flex-nowrap md:items-stretch md:gap-4.5 md:overflow-y-auto md:border-r md:border-b-0 md:px-3 md:py-5"
            >
                <Link
                    to="/panel"
                    className="flex items-center gap-2.5 px-2.5 font-semibold text-metin md:pb-1"
                >
                    <span
                        aria-hidden="true"
                        className="grid size-7 place-items-center rounded-lg bg-vurgu text-vurgu-yazi"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2.2"
                            strokeLinecap="round"
                        >
                            <path d="M3 12c2-3 4-3 6 0s4 3 6 0 4-3 6 0" />
                        </svg>
                    </span>
                    <span className="flex flex-col leading-tight">
                        <span className="text-[15px]">MyWeb2Wave</span>
                        <span className="hidden text-xs font-normal text-soluk md:block">
                            Yönetim paneli
                        </span>
                    </span>
                </Link>
                <Grup ad="Analiz">
                    <NavLink to="/panel" end className={ogeSinifi}>
                        <ChartColumn aria-hidden="true" className="size-4.5" />
                        Rapor
                    </NavLink>
                    <NavLink to="/panel/deneyler" className={ogeSinifi}>
                        <FlaskConical aria-hidden="true" className="size-4.5" />
                        Deneyler
                    </NavLink>
                </Grup>
                <Grup ad="Satış">
                    <NavLink to="/panel/huniler" className={ogeSinifi}>
                        <Filter aria-hidden="true" className="size-4.5" />
                        Huniler
                    </NavLink>
                    <NavLink to="/panel/planlar" className={ogeSinifi}>
                        <Tags aria-hidden="true" className="size-4.5" />
                        Planlar ve fiyatlar
                    </NavLink>
                </Grup>
                <Grup ad="Müşteriler">
                    <NavLink to="/panel/kullanicilar" className={ogeSinifi}>
                        <Users aria-hidden="true" className="size-4.5" />
                        Aboneler
                    </NavLink>
                </Grup>
                <Grup ad="Ayarlar">
                    <NavLink to="/panel/ayarlar" className={ogeSinifi}>
                        <Settings aria-hidden="true" className="size-4.5" />
                        Uygulama
                    </NavLink>
                    <NavLink to="/panel/yasal" className={ogeSinifi}>
                        <Scale aria-hidden="true" className="size-4.5" />
                        Yasal metinler
                    </NavLink>
                    <NavLink to="/panel/entegrasyonlar" className={ogeSinifi}>
                        <Plug aria-hidden="true" className="size-4.5" />
                        Entegrasyonlar
                    </NavLink>
                </Grup>
                <TemaSecici />
                <a
                    href="/panel/cikis"
                    className={cn(
                        ogeSinifi,
                        "text-soluk md:-mt-2 [&>svg]:text-soluk",
                    )}
                >
                    <LogOut aria-hidden="true" className="size-4.5" />
                    Çıkış
                </a>
            </nav>
            <main className="w-full max-w-[1240px] px-4 pt-6 pb-10 md:px-12 md:pt-9 md:pb-16">
                <Outlet />
            </main>
        </div>
    );
}

const TEMALAR: [Tema, string][] = [
    ["acik", "Açık"],
    ["koyu", "Koyu"],
    ["sistem", "Sistem"],
];

function TemaSecici(): ReactNode {
    const [tema, setTema] = useState(temaOku);
    useEffect(() => (tema === "sistem" ? sistemiDinle() : undefined), [tema]);
    return (
        <div
            role="group"
            aria-label="Tema"
            className={cn(
                bolmeliSinif,
                "ml-auto md:mt-auto md:ml-2.5 md:self-start",
            )}
        >
            {TEMALAR.map(([t, ad]) => (
                <button
                    key={t}
                    type="button"
                    aria-pressed={tema === t}
                    className={bolmeSinifi}
                    onClick={() => {
                        temaSec(t);
                        setTema(t);
                    }}
                >
                    {ad}
                </button>
            ))}
        </div>
    );
}

export function SayfaBasi({
    baslik,
    aciklama,
    children,
}: {
    baslik: string;
    aciklama?: string;
    children?: ReactNode;
}): ReactNode {
    return (
        <header className="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-[28px] leading-tight font-bold tracking-tight md:text-[34px]">
                    {baslik}
                </h1>
                {aciklama ? (
                    <p className="mt-1.5 text-[15px] text-soluk">{aciklama}</p>
                ) : null}
            </div>
            {children}
        </header>
    );
}
