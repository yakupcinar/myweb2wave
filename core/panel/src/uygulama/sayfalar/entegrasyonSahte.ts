// Testler için (P7d): Entegrasyonlar sayfası üç bölümü birlikte yükler; her
// bölümün testi kendi ucunu sahteler, diğerleri buradan boş döner.
export function bosEntegrasyon(yol: string): Response | null {
    const j = (o: unknown) => new Response(JSON.stringify(o));
    if (yol === "/panel/api/uygulamalar")
        return j({ uygulamalar: ["keepchat"] });
    if (yol.endsWith("/webhooklar")) return j({ hedefler: [], olaylar: [] });
    if (yol.endsWith("/anahtarlar"))
        return j({ mcpAdresi: "https://cekirdek.test/mcp", anahtarlar: [] });
    if (yol.endsWith("/baglam"))
        return j({ urun: "", kategori: "", kitle: "", ton: "", yasaklar: "" });
    return null;
}
