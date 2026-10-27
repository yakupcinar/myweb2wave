// Panel uygulaması (B5): Symfony /panel'de bu kabuğu verir; sayfaları
// istemci yönlendirir, veriler /panel/api/* uçlarından (TanStack Query).
// Geçiş P1–P5 (PLAN.md): Huniler ve düzenleyici sırayla buraya taşınır.
import "./stil.css";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { createBrowserRouter, RouterProvider } from "react-router";
import { Duzen } from "./Duzen";
import { Rapor } from "./sayfalar/Rapor";

export const yollar = [
    {
        path: "/panel",
        element: <Duzen />,
        children: [{ index: true, element: <Rapor /> }],
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
