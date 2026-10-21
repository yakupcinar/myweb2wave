// Huni sayfaları derlemede üretilir ve CDN'den sunulur (pre-setup B2):
// sunucusu yok, kullanıcıya özel içerik yok.
import { defineConfig } from "astro/config";

export default defineConfig({
  output: "static",
  // Ortak tema + CSS kök dizinin dışında (B4/H1); geliştirme sunucusu
  // izinsiz dosyayı 403 ile reddeder. Derlemede kısıt yok.
  vite: { server: { fs: { allow: [".", "../huni-bilesenleri"] } } },
});
