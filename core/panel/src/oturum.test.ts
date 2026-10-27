import { expect, it } from "vitest";
import { oturumDustu } from "./oturum";

// Ç41: oturum düşünce panel ucu giriş sayfasına yönlendirir; fetch
// yönlendirmeyi izler ve HTML döner — "bağlantı hatası" sanılmasın.
const yanit = (url: string, tur: string, yonlendi: boolean): Response => {
    const y = new Response("", { headers: { "Content-Type": tur } });
    Object.defineProperty(y, "url", { value: url });
    Object.defineProperty(y, "redirected", { value: yonlendi });
    return y;
};

it("giriş sayfasına yönlenen yanıt oturum düştü demektir", () => {
    expect(
        oturumDustu(
            yanit("http://x/panel/giris", "text/html; charset=UTF-8", true),
        ),
    ).toBe(true);
});

it("JSON yanıt (başarı ya da 422/409) oturum düşmesi değildir", () => {
    expect(
        oturumDustu(
            yanit(
                "http://x/panel/huniler/k/g/taslak",
                "application/json",
                false,
            ),
        ),
    ).toBe(false);
});
