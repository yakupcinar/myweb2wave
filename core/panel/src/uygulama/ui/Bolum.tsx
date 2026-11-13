// Sayfa bölümü (P7c, P7d): taslaktaki gibi 20 px başlık, isteğe bağlı kısa
// açıklama ve sağda yan bilgi; altında kart(lar). id verilirse adresteki
// #bölüm buraya kaydırır (Entegrasyonlar).
import type { ReactNode } from "react";

export function Bolum({
    id,
    baslik,
    aciklama,
    sag,
    children,
}: {
    id?: string;
    baslik: string;
    aciklama?: ReactNode;
    sag?: ReactNode;
    children: ReactNode;
}): ReactNode {
    return (
        <section
            id={id}
            aria-label={baslik}
            className="flex scroll-mt-6 flex-col gap-2.5"
        >
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="text-[20px] font-semibold">{baslik}</h2>
                {sag}
            </div>
            {aciklama ? (
                <p className="-mt-1 text-[13px] text-soluk">{aciklama}</p>
            ) : null}
            {children}
        </section>
    );
}
