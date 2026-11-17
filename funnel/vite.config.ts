// Huni sayfaları derlemede üretilir ve CDN'den sunulur (pre-setup B2):
// sunucusu yok, kullanıcıya özel içerik yok. Vite çok sayfalı: her sayfa bir
// HTML girişi, adresler ve dist düzeni Astro dönemindekiyle aynı
// (astro-kaldirma-arastirmasi.md). Oynatıcı Preact (L20); React adları
// preact/compat'a takma ad — testler de aynı yapılandırmayla koşar.
import { existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { loadEnv } from "vite";
import { defineConfig } from "vitest/config";
import erkenIstek from "./src/erken-istek-eklentisi.ts";

const sayfa = (yol: string): string =>
  fileURLToPath(new URL(yol, import.meta.url));

export default defineConfig(({ mode }) => ({
  envPrefix: ["PUBLIC_"],
  resolve: {
    alias: {
      react: "preact/compat",
      "react-dom/test-utils": "preact/test-utils",
      "react-dom": "preact/compat",
      "react/jsx-runtime": "preact/jsx-runtime",
    },
    // huni-bilesenleri/ preact'i kendi node_modules'undan çözerdi → ikinci
    // kopya (bekçi aşağıda). Astro döneminde @astrojs/preact ekliyordu.
    dedupe: ["preact"],
  },
  oxc: { jsx: { runtime: "automatic", importSource: "preact" } },
  plugins: [
    erkenIstek(
      loadEnv(mode, process.cwd(), "PUBLIC_")["PUBLIC_CORE_URL"] ?? "",
    ),
    {
      // Yalnız geliştirme: /keepchat/basarili → /keepchat/basarili/ (Stripe
      // dönüş adresi eğik çizgisiz). Üretimde Caddy file_server aynısını
      // yapar (dizin → eğik çizgili adres); Astro'nun geliştirme sunucusu
      // ikisini de kabul ediyordu, Vite'ınki 404 veriyordu (ölçüldü).
      name: "egik-cizgi",
      configureServer(sunucu) {
        sunucu.middlewares.use((istek, yanit, sonraki) => {
          const [yol = "", sorgu] = (istek.url ?? "").split("?");
          if (
            !yol.endsWith("/") &&
            !/\.[a-z0-9]+$/i.test(yol) &&
            existsSync(sayfa(`.${yol}/index.html`))
          ) {
            yanit.statusCode = 301;
            yanit.setHeader("Location", `${yol}/${sorgu ? `?${sorgu}` : ""}`);
            yanit.end();
            return;
          }
          sonraki();
        });
      },
    },
    {
      // Bekçi: huni-bilesenleri/ kendi node_modules'undan ikinci kopya
      // getirirse derleme kırmızı (kırma denemesiyle ölçüldü, H1).
      name: "tek-react",
      load(id) {
        if (id.includes("/huni-bilesenleri/node_modules/"))
          this.error(`ikinci kopya pakete giriyor: ${id}`);
      },
    },
  ],
  server: {
    port: 4321,
    // Ortak bileşenler kök dizinin dışında (B4/H1).
    fs: { allow: [".", "../huni-bilesenleri"] },
  },
  build: {
    rollupOptions: {
      input: {
        huni: sayfa("./keepchat/index.html"),
        basarili: sayfa("./keepchat/basarili/index.html"),
        iptal: sayfa("./keepchat/iptal/index.html"),
        abonelik: sayfa("./keepchat/abonelik/index.html"),
      },
    },
  },
  test: {
    environment: "happy-dom",
    include: ["src/**/*.test.{ts,tsx}"],
  },
}));
