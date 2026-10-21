// Huni sayfaları derlemede üretilir ve CDN'den sunulur (pre-setup B2):
// sunucusu yok, kullanıcıya özel içerik yok. Oynatıcı tek React adacığı
// (B4/H2), yalnız tarayıcıda çizilir (client:only).
import react from "@astrojs/react";
import { defineConfig } from "astro/config";

export default defineConfig({
  output: "static",
  integrations: [react()],
  vite: {
    // Ortak bileşenler kök dizinin dışında (B4/H1); geliştirme sunucusu
    // izinsiz dosyayı 403 ile reddeder. Derlemede kısıt yok.
    server: { fs: { allow: [".", "../huni-bilesenleri"] } },
    // huni-bilesenleri/ react'i kendi yolundan (aynı birimin ikinci
    // bağlaması) çözebilir → pakette ikinci kopya. dedupe'u @astrojs/react
    // kendisi ekliyor (ölçüldü: kendi satırımız silinince derleme aynı);
    // bekçi entegrasyon bunu bırakırsa yakalar.
    plugins: [
      {
        // Bekçi: dedupe kalkarsa derleme kırmızı (kırma denemesiyle ölçüldü).
        name: "tek-react",
        load(id) {
          if (id.includes("/huni-bilesenleri/node_modules/"))
            this.error(`ikinci kopya pakete giriyor: ${id}`);
        },
      },
    ],
  },
});
