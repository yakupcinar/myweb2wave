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
