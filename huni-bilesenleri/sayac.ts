// V4: istatistik değerinin sayan gösterimi için parçalar. İlk tam sayı
// sayar; önü/arkası olduğu gibi kalır ("%87", "3 dk", "10.000+").
// Binlik ayırıcı (nokta ya da boşluk) korunur; ondalıklı değer ("4,8")
// sayılmaz — yarım sayı yanlış görünürdü.
export type SayiParcalari = {
    once: string;
    sayi: number;
    sonra: string;
    ayirici: string;
};

export function sayiParcalari(deger: string): SayiParcalari | null {
    const m = /\d{1,3}(?:([. ])\d{3})+|\d+/.exec(deger);
    if (!m) return null;
    const sonra = deger.slice(m.index + m[0].length);
    if (/^,\d/.test(sonra)) return null;
    return {
        once: deger.slice(0, m.index),
        sayi: Number(m[0].replace(/[. ]/g, "")),
        sonra,
        ayirici: m[1] ?? "",
    };
}

export function sayiYaz(p: SayiParcalari, n: number): string {
    const tam = String(Math.round(n));
    const gruplu = p.ayirici
        ? tam.replace(/\B(?=(\d{3})+(?!\d))/g, p.ayirici)
        : tam;
    return `${p.once}${gruplu}${p.sonra}`;
}
