// Sayfalama (P7a2): büyüyen tablolar sunucuda 25'er satır (kullanıcı kararı,
// web2wave gibi numaralı). Altta "1–25 / 312 abonelik · ‹ Önceki  Sonraki ›";
// tek sayfaya sığan listede yalnız sayı yazar.
import type { ReactNode } from "react";
import { Dugme } from "./Dugme";

export function Sayfalama({
    sayfa,
    boyut,
    toplam,
    birim,
    sec,
}: {
    sayfa: number;
    boyut: number;
    toplam: number;
    birim: string;
    sec: (sayfa: number) => void;
}): ReactNode {
    const bas = Math.min((sayfa - 1) * boyut + 1, toplam);
    const son = Math.min(sayfa * boyut, toplam);
    return (
        <nav
            aria-label="Sayfalama"
            className="mt-3 flex flex-wrap items-center justify-between gap-3 text-[13px] text-soluk"
        >
            <span>
                {toplam > boyut || sayfa > 1
                    ? `${bas}–${son} / ${toplam} ${birim}`
                    : `${toplam} ${birim}`}
            </span>
            {toplam > boyut || sayfa > 1 ? (
                <span className="inline-flex gap-2">
                    <Dugme
                        className="min-h-8 px-3 font-normal"
                        disabled={sayfa <= 1}
                        onClick={() => sec(sayfa - 1)}
                    >
                        ‹ Önceki
                    </Dugme>
                    <Dugme
                        className="min-h-8 px-3 font-normal"
                        disabled={son >= toplam}
                        onClick={() => sec(sayfa + 1)}
                    >
                        Sonraki ›
                    </Dugme>
                </span>
            ) : null}
        </nav>
    );
}
