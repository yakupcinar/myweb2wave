// Durum rozeti (B5): aşama, kaynak, huni durumu.
import { cva, type VariantProps } from "class-variance-authority";
import type { ReactNode } from "react";

const rozetSinifi = cva(
    "inline-block rounded-full px-2 py-0.5 text-xs font-semibold",
    {
        variants: {
            tur: {
                notr: "bg-yuzey-2 text-soluk",
                iyi: "bg-iyi-zemin text-iyi",
                uyari: "bg-uyari-zemin text-uyari",
            },
        },
        defaultVariants: { tur: "notr" },
    },
);

export function Rozet({
    tur,
    children,
}: VariantProps<typeof rozetSinifi> & { children: ReactNode }): ReactNode {
    return <span className={rozetSinifi({ tur })}>{children}</span>;
}
