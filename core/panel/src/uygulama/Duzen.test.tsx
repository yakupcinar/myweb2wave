// @vitest-environment happy-dom
// P7a: tema seçici — varsayılan açık; seçim <html data-tema>'ya ve tarayıcıya
// yazılır; "Sistem" işletim sisteminin temasını izler.
import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from "@testing-library/react";
import { createMemoryRouter, RouterProvider } from "react-router";
import { afterEach, describe, expect, it, vi } from "vitest";
import { Duzen } from "./Duzen";
import { ekliTarih, kisaTarih, uzunTarih } from "./bicim";

let sistemKoyu = false;
const dinleyiciler: (() => void)[] = [];

function kur(): ReturnType<typeof within> {
    vi.stubGlobal("matchMedia", (sorgu: string) => ({
        matches: sorgu === "(prefers-color-scheme: dark)" && sistemKoyu,
        addEventListener: (_: string, f: () => void) => dinleyiciler.push(f),
        removeEventListener: (_: string, f: () => void) =>
            dinleyiciler.splice(dinleyiciler.indexOf(f), 1),
    }));
    render(
        <RouterProvider
            router={createMemoryRouter(
                [{ path: "/panel", element: <Duzen /> }],
                {
                    initialEntries: ["/panel"],
                },
            )}
        />,
    );
    return within(screen.getByRole("group", { name: "Tema" }));
}
const tema = (): string | undefined => document.documentElement.dataset.tema;
const secili = (g: ReturnType<typeof within>): string[] =>
    g
        .getAllByRole("button")
        .filter((b: HTMLElement) => b.getAttribute("aria-pressed") === "true")
        .map((b: HTMLElement) => b.textContent ?? "");

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    localStorage.clear();
    delete document.documentElement.dataset.tema;
    sistemKoyu = false;
    dinleyiciler.length = 0;
});

describe("P7a: tema seçici", () => {
    it("varsayılan Açık; Koyu seçilince sayfa koyu ve seçim saklanır", () => {
        const g = kur();
        expect(secili(g)).toEqual(["Açık"]);
        fireEvent.click(g.getByRole("button", { name: "Koyu" }));
        expect(tema()).toBe("koyu");
        expect(localStorage.getItem("panel-tema")).toBe("koyu");
        expect(secili(g)).toEqual(["Koyu"]);
    });

    it("saklı seçimle açılır", () => {
        localStorage.setItem("panel-tema", "sistem");
        expect(secili(kur())).toEqual(["Sistem"]);
    });

    it("Sistem: işletim sisteminin teması ve değişimi izlenir; Açık'a dönünce izleme biter", () => {
        sistemKoyu = true;
        const g = kur();
        fireEvent.click(g.getByRole("button", { name: "Sistem" }));
        expect(tema()).toBe("koyu");
        sistemKoyu = false;
        dinleyiciler.forEach((f) => f());
        expect(tema()).toBe("acik");
        fireEvent.click(g.getByRole("button", { name: "Açık" }));
        expect(dinleyiciler).toEqual([]);
    });
});

describe("P7a: Türkçe tarih", () => {
    const simdi = new Date("2026-11-10T12:00:00Z");
    it("ay adına ünlü uyumlu ek; başka yılda yıla göre", () => {
        expect(ekliTarih("2026-11-03T10:00:00Z", "den", simdi)).toBe(
            "3 Kasım'dan",
        );
        expect(ekliTarih("2026-10-21T10:00:00Z", "den", simdi)).toBe(
            "21 Ekim'den",
        );
        expect(ekliTarih("2026-12-01T10:00:00Z", "e", simdi)).toBe(
            "1 Aralık'a",
        );
        expect(ekliTarih("2026-09-15T10:00:00Z", "de", simdi)).toBe(
            "15 Eylül'de",
        );
        expect(ekliTarih("2026-04-02T10:00:00Z", "de", simdi)).toBe(
            "2 Nisan'da",
        );
        expect(ekliTarih("2025-03-01T10:00:00Z", "den", simdi)).toBe(
            "1 Mart 2025'ten",
        );
        expect(ekliTarih("2027-01-05T10:00:00Z", "e", simdi)).toBe(
            "5 Ocak 2027'ye",
        );
        expect(kisaTarih("2026-11-03T10:00:00Z", simdi)).toBe("3 Kas");
        expect(kisaTarih("2025-08-30T10:00:00Z", simdi)).toBe("30 Ağu 2025");
        expect(uzunTarih("2026-11-03T23:30:00Z", simdi)).toBe("3 Kasım");
    });
});
