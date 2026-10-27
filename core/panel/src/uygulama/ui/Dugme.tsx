// Düğme (B5): görünüm türleri tek yerde; bağlantı da aynı görünümü alabilir
// (dugmeSinifi). Davranış yerel <button>.
import { cva, type VariantProps } from "class-variance-authority";
import type { ComponentProps, ReactNode } from "react";
import { cn } from "./cn";

export const dugmeSinifi = cva(
    "inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border px-3 text-[13px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-vurgu disabled:cursor-not-allowed disabled:opacity-45",
    {
        variants: {
            tur: {
                ana: "border-vurgu bg-vurgu text-vurgu-yazi hover:brightness-110",
                ikincil: "border-kenar bg-yuzey text-metin hover:bg-yuzey-2",
                tehlike:
                    "border-kenar bg-yuzey text-tehlike hover:border-transparent hover:bg-tehlike-zemin",
            },
        },
        defaultVariants: { tur: "ikincil" },
    },
);

export function Dugme({
    tur,
    className,
    type = "button",
    ...oz
}: ComponentProps<"button"> & VariantProps<typeof dugmeSinifi>): ReactNode {
    return (
        <button
            type={type}
            className={cn(dugmeSinifi({ tur }), className)}
            {...oz}
        />
    );
}
