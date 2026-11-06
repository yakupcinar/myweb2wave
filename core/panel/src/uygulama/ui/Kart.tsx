// Kart ve başlığı (B5): panelin bütün bölümleri bu kabın içinde.
import type { ComponentProps, ReactNode } from "react";
import { cn } from "./cn";

export function Kart({
    className,
    ...oz
}: ComponentProps<"section">): ReactNode {
    return (
        <section
            className={cn(
                "min-w-0 rounded-xl border border-kenar bg-yuzey shadow-xs",
                className,
            )}
            {...oz}
        />
    );
}

export function KartBasi({
    baslik,
    aciklama,
    children,
}: {
    baslik: ReactNode;
    aciklama?: ReactNode;
    children?: ReactNode;
}): ReactNode {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-kenar px-4.5 py-4">
            <div>
                <h2 className="text-[15px] font-semibold">{baslik}</h2>
                {aciklama ? (
                    <p className="mt-0.5 text-[13px] text-soluk">{aciklama}</p>
                ) : null}
            </div>
            {children}
        </div>
    );
}
