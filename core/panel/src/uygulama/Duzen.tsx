// Panel düzeni (B5): solda menü (dar ekranda üstte), sağda sayfa. Bulunulan
// sayfa NavLink'le işaretlenir (aria-current). Huniler P2'ye kadar Twig
// sayfası → düz bağlantı (tam sayfa yükleme).
import { ChartColumn, Filter, KeyRound, LogOut, Sparkles } from "lucide-react";
import type { ReactNode } from "react";
import { Link, NavLink, Outlet } from "react-router";
import { cn } from "./ui/cn";

const ogeSinifi =
    "flex items-center gap-2.5 rounded-lg px-2.5 py-2 font-medium text-soluk hover:bg-yuzey-2 hover:text-metin aria-[current=page]:bg-yuzey-2 aria-[current=page]:text-metin [&[aria-current=page]>svg]:text-vurgu";

export function Duzen(): ReactNode {
    return (
        <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] md:grid-cols-[232px_minmax(0,1fr)]">
            <nav
                aria-label="Panel"
                className="flex flex-wrap items-center gap-1 border-b border-kenar bg-yuzey px-3 py-2.5 md:sticky md:top-0 md:h-screen md:flex-col md:flex-nowrap md:items-stretch md:border-r md:border-b-0 md:px-3 md:py-5"
            >
                <Link
                    to="/panel"
                    className="flex items-center gap-2.5 px-2.5 pb-0 font-bold text-metin md:pb-5"
                >
                    <span
                        aria-hidden="true"
                        className="grid size-7.5 place-items-center rounded-lg bg-linear-135 from-[#5b8cff] to-[#22c55e] font-extrabold text-white"
                    >
                        W
                    </span>
                    MyWeb2Wave
                </Link>
                <NavLink to="/panel" end className={ogeSinifi}>
                    <ChartColumn aria-hidden="true" className="size-4.5" />
                    Rapor
                </NavLink>
                <a href="/panel/huniler" className={ogeSinifi}>
                    <Filter aria-hidden="true" className="size-4.5" />
                    Huniler
                </a>
                <NavLink to="/panel/baglam" className={ogeSinifi}>
                    <Sparkles aria-hidden="true" className="size-4.5" />
                    Ürün bağlamı
                </NavLink>
                <NavLink to="/panel/anahtarlar" className={ogeSinifi}>
                    <KeyRound aria-hidden="true" className="size-4.5" />
                    API anahtarları
                </NavLink>
                <a
                    href="/panel/cikis"
                    className={cn(ogeSinifi, "ml-auto md:mt-auto md:ml-0")}
                >
                    <LogOut aria-hidden="true" className="size-4.5" />
                    Çıkış
                </a>
            </nav>
            <main className="w-full max-w-[1240px] px-4 pt-5 pb-10 md:px-8 md:pt-7 md:pb-12">
                <Outlet />
            </main>
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
        <header className="mb-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 className="text-[22px] font-bold tracking-tight">
                    {baslik}
                </h1>
                {aciklama ? (
                    <p className="mt-1 text-soluk">{aciklama}</p>
                ) : null}
            </div>
            {children}
        </header>
    );
}
