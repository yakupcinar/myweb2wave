// Tailwind sınıflarını birleştirir; çakışanda sonuncu kazanır (bileşene
// dışarıdan verilen sınıf varsayılanı ezsin).
import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...siniflar: ClassValue[]): string {
    return twMerge(clsx(siniflar));
}
