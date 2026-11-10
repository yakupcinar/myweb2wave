// Panel uygulaması (B5): Symfony /panel'de bu kabuğu verir; sayfaları
// istemci yönlendirir, veriler /panel/api/* uçlarından (TanStack Query).
// Geçiş P1–P5 (PLAN.md): Huniler (P2) ve düzenleyici (P3) burada.
import "./stil.css";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { lazy, StrictMode, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { createBrowserRouter, RouterProvider } from "react-router";
import { Duzen } from "./Duzen";
import { Anahtarlar } from "./sayfalar/Anahtarlar";
import { Ayarlar } from "./sayfalar/Ayarlar";
import { Baglam } from "./sayfalar/Baglam";
import { Deneyler } from "./sayfalar/Deneyler";
import { Webhooklar } from "./sayfalar/Webhooklar";
import { Yasal } from "./sayfalar/Yasal";
import { Huniler } from "./sayfalar/Huniler";
import { Kullanicilar } from "./sayfalar/Kullanicilar";
import { Planlar } from "./sayfalar/Planlar";
import { Rapor } from "./sayfalar/Rapor";

// P3: düzenleyici (Puck) ayrı parça — yalnız düzenleyici sayfası indirir.
const HuniDuzenle = lazy(async () => ({
    default: (await import("./sayfalar/HuniDuzenle")).HuniDuzenle,
}));

export const yollar = [
    {
        path: "/panel",
        element: <Duzen />,
        children: [
            { index: true, element: <Rapor /> },
            { path: "huniler", element: <Huniler /> },
            { path: "kullanicilar", element: <Kullanicilar /> },
            { path: "planlar", element: <Planlar /> },
            { path: "deneyler", element: <Deneyler /> },
            { path: "webhooklar", element: <Webhooklar /> },
            { path: "baglam", element: <Baglam /> },
            { path: "anahtarlar", element: <Anahtarlar /> },
            { path: "ayarlar", element: <Ayarlar /> },
            { path: "yasal", element: <Yasal /> },
        ],
    },
    {
        // Tam ekran: sol menü yok (Puck kendi düzenini kurar).
        path: "/panel/huniler/:app/:ad",
        element: (
            <Suspense fallback={<p className="p-4">Düzenleyici yükleniyor…</p>}>
                <HuniDuzenle />
            </Suspense>
        ),
    },
];

const kok = document.getElementById("panel-uygulama");
if (kok) {
    // Panel tek kişilik: pencereye dönünce yeniden çekmek gereksiz istek.
    const sorgu = new QueryClient({
        defaultOptions: { queries: { refetchOnWindowFocus: false, retry: 1 } },
    });
    createRoot(kok).render(
        <StrictMode>
            <QueryClientProvider client={sorgu}>
                <RouterProvider router={createBrowserRouter(yollar)} />
            </QueryClientProvider>
        </StrictMode>,
    );
}
