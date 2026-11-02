// Oynatıcı testleri (B4/H2): tarayıcı yerine happy-dom. PUBLIC_ öneki
// .env'den okunur — çekirdek adresi testte de derlemedeki gibi.
import { defineConfig } from "vitest/config";

export default defineConfig({
  envPrefix: ["PUBLIC_"],
  // L20: üretimdeki çalışma zamanı Preact (compat) — testler de onunla koşar,
  // yoksa React'le yeşil olup Preact'te kırılan davranışı görmezdik.
  resolve: {
    alias: {
      react: "preact/compat",
      "react-dom/test-utils": "preact/test-utils",
      "react-dom": "preact/compat",
      "react/jsx-runtime": "preact/jsx-runtime",
    },
  },
  test: {
    environment: "happy-dom",
    include: ["src/**/*.test.{ts,tsx}"],
  },
});
