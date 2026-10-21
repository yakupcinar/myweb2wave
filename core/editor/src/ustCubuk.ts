// Üst çubuk kuralları (b1): hangi düğme ne zaman açık. Rakiplerde
// yayınla/önizleme/sürümler düzenleyicide (huni-editoru-arastirmasi.md § Ek
// 2026-10-21). Önizleme ve yayın KAYDEDİLMİŞ taslağı kullanır; kaydedilmemiş
// değişiklik varken açık olsalar kullanıcı gördüğünden başkasını yayınlardı.

export type Surum = { surum: number; zaman: string; ziyaret: number };
export type YayinDurumu = {
    surumler: Surum[];
    farkli: boolean;
    onizleme: string;
    yayindaki: string;
};

export type Dugmeler = {
    kaydet: boolean;
    onizle: boolean;
    yayinla: boolean;
    geriYukle: boolean;
    /** Yayınla düğmesinin yanındaki kısa açıklama. */
    not: string;
};

export function dugmeler(
    yayin: YayinDurumu | null,
    kirli: boolean,
    mesgul: boolean,
): Dugmeler {
    const hazir = yayin !== null && !mesgul;
    return {
        kaydet: !mesgul,
        onizle: hazir && !kirli,
        yayinla: hazir && !kirli && yayin.farkli,
        geriYukle: hazir && !kirli,
        not: kirli
            ? "Kaydedilmemiş değişiklik — önce kaydet"
            : yayin === null
              ? "Durum alınamadı"
              : yayin.farkli
                ? "Kaydedildi, yayında değil"
                : "Yayındakiyle aynı",
    };
}
