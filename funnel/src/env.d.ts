/// <reference types="vite/client" />
interface ImportMetaEnv {
  /** Çekirdek API kökü, derlemede gömülür (huni statik). */
  readonly PUBLIC_CORE_URL: string;
  /** TikTok tarayıcı pikseli; boşsa yüklenmez. Meta Pixel'i panelden (A30). */
  readonly PUBLIC_TIKTOK_PIXEL_ID: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
