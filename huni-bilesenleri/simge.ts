// V3a: emoji → Fluent simge anahtarı. Çekirdekte aynı kural (Simgeler::kod):
// kod noktaları onaltılık, FE0F (biçim seçici) atılır, "-" ile birleşir.
// İki dilde aynı olduğunu simge.test.ts + SimgeTest ortak örnekle ölçer.
export function simgeKodu(emoji: string): string {
    return Array.from(emoji)
        .map((k) => k.codePointAt(0) ?? 0)
        .filter((n) => n !== 0xfe0f)
        .map((n) => n.toString(16))
        .join("-");
}
