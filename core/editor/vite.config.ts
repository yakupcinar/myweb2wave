// Panel huni düzenleyicisi (E2.4): React + Puck. Çıktı çekirdeğin public/
// dizinine, içerik özetli adlarla (tuzak §30) + manifest; Twig manifestten okur.
import react from "@vitejs/plugin-react";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [react()],
    base: "/varliklar/editor/",
    build: {
        outDir: "../public/varliklar/editor",
        emptyOutDir: true,
        manifest: true,
        rollupOptions: { input: "src/main.tsx" },
    },
});
