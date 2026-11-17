/// <reference types="vite/client" />
interface ImportMetaEnv {
  /** Çekirdek API kökü, derlemede gömülür (huni statik). */
  readonly PUBLIC_CORE_URL: string;
  /* Meta (A30) ve TikTok (A31) Pixel'leri panelden — giris-baglantisi yanıtında. */
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
