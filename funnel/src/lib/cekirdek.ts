// Çekirdek API istemcisi. Tipler contract/openapi.yaml'dan ÜRETİLİR
// (src/sozlesme/api.ts — elle düzenleme); sözleşmede olmayan bir alanı
// kullanmak `astro check`'i düşürür (R2).
import createClient from "openapi-fetch";
import type { paths } from "../sozlesme/api";

export const cekirdek = createClient<paths>({
  baseUrl: import.meta.env.PUBLIC_CORE_URL,
});
