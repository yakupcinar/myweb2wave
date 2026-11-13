// Testler için (P7d): Entegrasyonlar sayfası bölümleri birlikte yükler (A30: Meta da); her
// bölümün testi kendi ucunu sahteler, diğerleri buradan boş döner.
export function bosEntegrasyon(yol: string): Response | null {
    const j = (o: unknown) => new Response(JSON.stringify(o));
    if (yol === "/panel/api/uygulamalar")
        return j({ uygulamalar: ["keepchat"] });
    if (yol.endsWith("/webhooklar")) return j({ hedefler: [], olaylar: [] });
    if (yol.endsWith("/anahtarlar"))
        return j({ mcpAdresi: "https://cekirdek.test/mcp", anahtarlar: [] });
    if (yol.endsWith("/meta"))
        return j({
            pixelId: "",
            capiAnahtari: { durum: "yok", son4: null },
            testKodu: "",
            reklamHesabi: "",
            insightsAnahtari: { durum: "yok", son4: null },
            sifrelemeHazir: true,
            surum: "v24.0",
            guncellendi: null,
        });
    if (yol.endsWith("/baglam"))
        return j({ urun: "", kategori: "", kitle: "", ton: "", yasaklar: "" });
    return null;
}
