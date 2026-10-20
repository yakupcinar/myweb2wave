// Huni tanımı: anket + tanıtım adımları, en sonda paywall. Huniler kodda
// tanımlı (A1 — görsel editör yok); sayfalar derlemede üretilir. Kimlikler
// çekirdeğe gider ve panelde görünür: küçük harf, rakam, - ve _ (çekirdek
// aynı biçimi denetler, contract HuniKimligi).

export type Secenek = { id: string; metin: string };

export type Adim =
  | { id: string; tur: "tanitim"; baslik: string; metin: string; dugme: string }
  | { id: string; tur: "soru"; soru: string; secenekler: Secenek[] }
  | { id: string; tur: "paywall" };

export type Huni = {
  app: "keepchat";
  sayfaBasligi: string;
  adimlar: Adim[];
};
