// Form alanları (P7-temel): taslaktaki gibi — 15 px yazı, kenar-2 çerçeve,
// 8 px köşe. Satır: solda etiket (220 px), sağda alan + yardım (dar ekranda
// alt alta). Tailwind sıfırlaması çıplak <input>'u çerçevesiz bırakır; her
// alan bu sınıfı kullanır (p7-taslak-farki.md).
import { useId, type ReactNode } from "react";

export const alanSinifi =
    "w-full min-w-0 rounded-lg border border-kenar-2 bg-yuzey px-2.5 py-2 text-[15px] text-metin placeholder:text-soluk focus:outline-2 focus:-outline-offset-1 focus:outline-vurgu disabled:bg-yuzey-2 disabled:text-soluk";

export const kutuSinifi = "size-4 shrink-0 accent-vurgu";

export function Satir({
    etiket,
    yardim,
    children,
}: {
    etiket: ReactNode;
    yardim?: ReactNode;
    children: (id: string) => ReactNode;
}): ReactNode {
    const id = useId();
    return (
        <div className="grid items-start gap-x-4 gap-y-1.5 border-t border-kenar px-[18px] py-3.5 first:border-t-0 md:grid-cols-[220px_minmax(0,1fr)]">
            <label htmlFor={id} className="text-[15px] md:pt-2">
                {etiket}
            </label>
            <div className="grid gap-1.5">
                {children(id)}
                {yardim ? (
                    <p className="text-[13px] text-soluk">{yardim}</p>
                ) : null}
            </div>
        </div>
    );
}
