// Tablo (B5): başlık satırı, hücre aralığı, yatay kaydırma tek yerde.
// P7b: hücre dolgusu taslaktaki gibi 12 × 18 px.
import type { ComponentProps, ReactNode } from "react";
import { cn } from "./cn";

export function Tablo({
    className,
    ...oz
}: ComponentProps<"table">): ReactNode {
    return (
        <div className="overflow-x-auto">
            <table
                className={cn("w-full border-collapse text-[14px]", className)}
                {...oz}
            />
        </div>
    );
}

/**
 * P7g (Ç136) + Ç137: telefonda aynı satır karta döner. Tek DOM (ikinci bir
 * kart listesi yok): testler ve ekran okuyucu tek kaynak görür. Masaüstü
 * tablo olduğu gibi. Telefonda sütun başlığı gizli; çıplak değerin önüne
 * `KartEtiketi` konur.
 */
export const kartTablo = "max-md:block";
export const kartSatir =
    "max-md:grid max-md:grid-cols-[minmax(0,1fr)_auto] max-md:gap-x-3 max-md:gap-y-2 max-md:border-b max-md:border-kenar max-md:px-[18px] max-md:py-3.5 max-md:last:border-b-0";
export const kartHucre = "max-md:block max-md:border-0 max-md:p-0";
export const kartTam = "max-md:col-span-2 max-md:whitespace-normal";

export function KartEtiketi({ children }: { children: ReactNode }): ReactNode {
    return (
        <span className="font-sans text-[14px] text-soluk md:hidden">
            {children}{" "}
        </span>
    );
}

export function Th({ className, ...oz }: ComponentProps<"th">): ReactNode {
    return (
        <th
            className={cn(
                "border-b border-kenar px-[18px] py-3 text-left text-xs font-medium whitespace-nowrap text-soluk",
                className,
            )}
            {...oz}
        />
    );
}

export function Td({ className, ...oz }: ComponentProps<"td">): ReactNode {
    return (
        <td
            className={cn(
                "border-b border-kenar px-[18px] py-3 whitespace-nowrap [tr:last-child>&]:border-b-0",
                className,
            )}
            {...oz}
        />
    );
}
