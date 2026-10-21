// Vitest CSS'i varsayılan olarak boş dizgeye çeviriyor — "?raw" ile okunan
// huni.css de boş geliyordu, sınıf testi yalancı kırmızıydı (ölçüldü).
import { defineConfig } from "vitest/config";

export default defineConfig({
    test: { css: { include: [/huni\.css/] } },
});
