// Panel (B5; E2.4 huni düzenleyicisi: React + Puck). Çıktı çekirdeğin public/
// dizinine, içerik özetli adlarla (tuzak §30) + manifest; Twig manifestten okur.
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        react(),
        tailwindcss(),
        {
            // Bekçi: dedupe kalkarsa derleme kırmızı (kırma denemesiyle ölçüldü).
            name: "tek-react",
            load(id) {
                if (id.includes("/huni-bilesenleri/node_modules/"))
                    this.error(`ikinci kopya pakete giriyor: ${id}`);
            },
        },
    ],
    // huni-bilesenleri/ react'i kendi yolundan (aynı birimin ikinci bağlaması)
    // çözerdi → pakette ikinci jsx-runtime (+346 B; hook kullanınca ikinci
    // React). dedupe kökten çözdürür (B4/H1).
    resolve: { dedupe: ["react", "react-dom"] },
    base: "/varliklar/panel/",
    build: {
        outDir: "../public/varliklar/panel",
        emptyOutDir: true,
        manifest: true,
        // İki giriş (B5 geçişi): düzenleyici sayfası ve panel uygulaması;
        // P3'te düzenleyici uygulamanın sayfası olur.
        rollupOptions: { input: ["src/main.tsx", "src/uygulama/main.tsx"] },
    },
});
