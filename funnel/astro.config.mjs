// Huni sayfaları derlemede üretilir ve CDN'den sunulur (pre-setup B2):
// sunucusu yok, kullanıcıya özel içerik yok.
import { defineConfig } from "astro/config";

export default defineConfig({
  output: "static",
});
