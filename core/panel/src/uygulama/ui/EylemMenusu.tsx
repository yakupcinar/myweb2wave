// Satır eylem menüsü (P7g, Ç135): "…" düğmesi, seyrek kullanılan eylemleri
// toplar. Menü satırın içinde çizilir (testler ve ekran okuyucu satırla
// birlikte bulur) ama konumu düğmeden hesaplanır ve sabittir: tablonun
// yatay kaydırma kabı (overflow-x-auto) mutlak konumlu menüyü keserdi.
// Klavye: açılınca ilk öğe; ↑/↓ gezinir; Esc kapatır ve odağı düğmeye
// verir. Dışarı tıklama, kaydırma ve boyut değişimi kapatır.
import { Ellipsis } from "lucide-react";
import {
    useEffect,
    useId,
    useRef,
    useState,
    type KeyboardEvent,
    type ReactNode,
} from "react";
import { cn } from "./cn";
import { dugmeSinifi } from "./Dugme";

export type EylemOgesi = {
    ad: string;
    sec: () => void;
    tehlike?: boolean;
    aciklama?: string;
};

export function EylemMenusu({
    etiket,
    ogeler,
}: {
    etiket: string;
    ogeler: EylemOgesi[];
}): ReactNode {
    const [konum, setKonum] = useState<{ ust: number; sag: number } | null>(
        null,
    );
    const dugme = useRef<HTMLButtonElement>(null);
    const menu = useRef<HTMLDivElement>(null);
    const id = useId();
    const acik = konum !== null;

    const kapat = (odak = false): void => {
        setKonum(null);
        if (odak) dugme.current?.focus();
    };

    useEffect(() => {
        if (!acik) return;
        menu.current?.querySelector<HTMLElement>("[role=menuitem]")?.focus();
        const disari = (e: MouseEvent): void => {
            const hedef = e.target as Node;
            if (
                !menu.current?.contains(hedef) &&
                !dugme.current?.contains(hedef)
            )
                setKonum(null);
        };
        const birak = (): void => setKonum(null);
        document.addEventListener("mousedown", disari);
        window.addEventListener("scroll", birak, true);
        window.addEventListener("resize", birak);
        return () => {
            document.removeEventListener("mousedown", disari);
            window.removeEventListener("scroll", birak, true);
            window.removeEventListener("resize", birak);
        };
    }, [acik]);

    const tus = (e: KeyboardEvent<HTMLDivElement>): void => {
        const ogeler = [
            ...(menu.current?.querySelectorAll<HTMLElement>(
                "[role=menuitem]",
            ) ?? []),
        ];
        const sira = ogeler.indexOf(document.activeElement as HTMLElement);
        if (e.key === "Escape") {
            e.preventDefault();
            kapat(true);
        } else if (e.key === "ArrowDown" || e.key === "ArrowUp") {
            e.preventDefault();
            const adim = e.key === "ArrowDown" ? 1 : -1;
            ogeler[(sira + adim + ogeler.length) % ogeler.length]?.focus();
        } else if (e.key === "Tab") {
            kapat();
        }
    };

    return (
        <>
            <button
                ref={dugme}
                type="button"
                aria-label={etiket}
                aria-haspopup="menu"
                aria-expanded={acik}
                aria-controls={acik ? id : undefined}
                className={cn(dugmeSinifi(), "px-2.5")}
                onClick={() => {
                    if (acik) return kapat();
                    const r = dugme.current?.getBoundingClientRect();
                    // Görünür alana sıkıştır: telefonda tablo yatay kayınca düğme
                    // ekran dışında kalabiliyor, menü kenardan taşıyordu (ekran döngüsü).
                    if (r)
                        setKonum({
                            ust: r.bottom + 4,
                            sag: Math.max(8, window.innerWidth - r.right),
                        });
                }}
            >
                <Ellipsis aria-hidden="true" className="size-4" />
            </button>
            {konum ? (
                <div
                    ref={menu}
                    id={id}
                    role="menu"
                    aria-label={etiket}
                    onKeyDown={tus}
                    style={{ top: konum.ust, right: konum.sag }}
                    className="fixed z-20 min-w-60 rounded-xl border border-kenar bg-yuzey p-1 text-left whitespace-normal shadow-lg"
                >
                    {ogeler.map((o) => (
                        <button
                            key={o.ad}
                            type="button"
                            role="menuitem"
                            // Ad yalnız eylem; açıklama görünür yardım.
                            aria-label={o.ad}
                            tabIndex={-1}
                            className={cn(
                                "flex w-full flex-col rounded-lg px-3 py-2 text-left text-[14px] hover:bg-yuzey-2 focus:bg-yuzey-2 focus:outline-none",
                                o.tehlike ? "text-tehlike" : "text-metin",
                            )}
                            onClick={() => {
                                kapat(true);
                                o.sec();
                            }}
                        >
                            {o.ad}
                            {o.aciklama ? (
                                <span className="text-[12px] text-soluk">
                                    {o.aciklama}
                                </span>
                            ) : null}
                        </button>
                    ))}
                </div>
            ) : null}
        </>
    );
}
