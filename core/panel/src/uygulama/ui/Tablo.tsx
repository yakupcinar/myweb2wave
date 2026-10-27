// Tablo (B5): başlık satırı, hücre aralığı, yatay kaydırma tek yerde.
import type { ComponentProps, ReactNode } from "react";
import { cn } from "./cn";

export function Tablo({
    className,
    ...oz
}: ComponentProps<"table">): ReactNode {
    return (
        <div className="overflow-x-auto">
            <table
                className={cn(
                    "w-full border-collapse text-[13.5px]",
                    className,
                )}
                {...oz}
            />
        </div>
    );
}

export function Th({ className, ...oz }: ComponentProps<"th">): ReactNode {
    return (
        <th
            className={cn(
                "border-b border-kenar bg-yuzey-2 px-3 py-2.5 text-left text-xs font-semibold tracking-wide whitespace-nowrap text-soluk uppercase",
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
                "border-b border-kenar px-3 py-2.5 whitespace-nowrap [tr:last-child>&]:border-b-0",
                className,
            )}
            {...oz}
        />
    );
}
