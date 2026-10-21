// Oynatıcı testleri (B4/H2): tarayıcı yerine happy-dom. PUBLIC_ öneki
// .env'den okunur — çekirdek adresi testte de derlemedeki gibi.
import { defineConfig } from "vitest/config";

export default defineConfig({
  envPrefix: ["PUBLIC_"],
  test: { environment: "happy-dom", include: ["src/**/*.test.{ts,tsx}"] },
});
