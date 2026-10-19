interface ImportMetaEnv {
  /** Çekirdek API kökü, derlemede gömülür (huni statik). */
  readonly PUBLIC_CORE_URL: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
