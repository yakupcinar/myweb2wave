interface ImportMetaEnv {
  /** Çekirdek API kökü, derlemede gömülür (huni statik). */
  readonly PUBLIC_CORE_URL: string;
  /** Tarayıcı piksel kimlikleri; boşsa ilgili piksel yüklenmez. */
  readonly PUBLIC_META_PIXEL_ID: string;
  readonly PUBLIC_TIKTOK_PIXEL_ID: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
