// Panel huni düzenleyicisi (E2.4): React + Puck. Çıktı çekirdeğin public/
// dizinine, içerik özetli adlarla (tuzak §30) + manifest; Twig manifestten okur.
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        react(),
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
    base: "/varliklar/editor/",
    build: {
        outDir: "../public/varliklar/editor",
        emptyOutDir: true,
        manifest: true,
        rollupOptions: { input: "src/main.tsx" },
    },
});
