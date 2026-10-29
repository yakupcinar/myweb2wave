// Panel AI asistanı (A3): sohbet ucunun Server-Sent Events akışını okur.
// Olay biçimi sözleşmeden (PanelAiOlayi) — elle tip yok (R2).
import type { components } from "./sozlesme";

export type AiOlayi = components["schemas"]["PanelAiOlayi"];
export type AiDurumu = components["schemas"]["PanelAiDurumu"];

/**
 * Akıştaki her `event:/data:` bloğunu sırayla bildirir. Ağ parçaları blok
 * sınırına denk gelmeyebilir: tampon "\n\n"e kadar bekler.
 */
export async function sseOku(
    govde: ReadableStream<Uint8Array>,
    olay: (o: AiOlayi) => void,
): Promise<void> {
    const okuyucu = govde.getReader();
    const cozucu = new TextDecoder();
    let tampon = "";
    for (;;) {
        const { done, value } = await okuyucu.read();
        tampon += cozucu.decode(value, { stream: !done });
        let sinir: number;
        while ((sinir = tampon.indexOf("\n\n")) !== -1) {
            const blok = tampon.slice(0, sinir);
            tampon = tampon.slice(sinir + 2);
            const veri = blok
                .split("\n")
                .find((s) => s.startsWith("data: "))
                ?.slice(6);
            if (veri) olay(JSON.parse(veri) as AiOlayi);
        }
        if (done) return;
    }
}

/** Araç adının yöneticiye görünen karşılığı. */
export const ARAC_ADI: Record<string, string> = {
    huniler_listele: "Huniler okunuyor",
    huni_oku: "Taslak okunuyor",
    tanim_dogrula: "Tanım denetleniyor",
    taslak_kaydet: "Taslak kaydediliyor",
    onizleme_adresi: "Önizleme bağlantısı alınıyor",
};
