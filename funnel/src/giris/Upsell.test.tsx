// M3c: tek tık ek ürün — tutar düğmede (Stripe'tan), satış isteği oturum +
// ürünle; 3D Secure gerekirse Stripe.js doğrular, aynı istek satışı kapatır.
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from "@testing-library/preact";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { stripeYukle } from "../lib/stripe";
import Upsell from "./Upsell";

vi.mock("../lib/stripe", () => ({ stripeYukle: vi.fn() }));

// openapi-fetch fetch'i istemci kurulurken yakalar — sahte, modüllerden önce
// (Huni.test ile aynı desen).
const ag = vi.hoisted(() => {
  const ag = {
    yanitlar: [] as { durum: number; govde: unknown }[],
    govdeler: [] as unknown[],
  };
  globalThis.fetch = async (girdi: RequestInfo | URL, init?: RequestInit) => {
    const istek = new Request(girdi, init);
    ag.govdeler.push(await istek.json());
    const y = ag.yanitlar.shift() ?? { durum: 500, govde: {} };
    return new Response(JSON.stringify(y.govde), { status: y.durum });
  };
  return ag;
});
const govdeler = ag.govdeler;
beforeEach(() => {
  govdeler.length = 0;
});
afterEach(() => {
  cleanup();
});

const ciz = () =>
  render(
    <Upsell
      app="keepchat"
      oturum="cs_test_x"
      urun="kocluk"
      baslik="Koçluk ekle"
      dugme="Ekle"
      tutar={19.99}
      paraBirimi="EUR"
    />,
  );

describe("M3c — upsell", () => {
  it("tutar düğmede; ödendi → onay metni, istek oturum + ürünle", async () => {
    ag.yanitlar = [{ durum: 200, govde: { durum: "odendi" } }];
    ciz();
    // Elle: tr-TR EUR biçimi.
    fireEvent.click(screen.getByText("Ekle · €19,99"));
    expect(await screen.findByRole("status")).toHaveProperty(
      "textContent",
      "Eklendi — ödemeniz alındı.",
    );
    expect(govdeler).toEqual([
      { app: "keepchat", oturum: "cs_test_x", urun: "kocluk" },
    ]);
  });
  it("3D Secure: Stripe.js doğrular, aynı istek yinelenir ve kapanır", async () => {
    const dogrula = vi.fn(async () => ({}));
    vi.mocked(stripeYukle).mockResolvedValue((() => ({
      handleNextAction: dogrula,
    })) as never);
    ag.yanitlar = [
      {
        durum: 200,
        govde: { durum: "dogrulama", clientSecret: "pi_s", anahtar: "pk" },
      },
      { durum: 200, govde: { durum: "odendi" } },
    ];
    ciz();
    fireEvent.click(screen.getByText("Ekle · €19,99"));
    await screen.findByRole("status");
    expect(dogrula).toHaveBeenCalledWith({ clientSecret: "pi_s" });
    expect(govdeler).toHaveLength(2);
  });
  it("doğrulama tamamlanmazsa ikinci istek yok; kart reddi Türkçe söylenir, düğme kalır", async () => {
    vi.mocked(stripeYukle).mockResolvedValue((() => ({
      handleNextAction: async () => ({ error: { message: "x" } }),
    })) as never);
    ag.yanitlar = [
      {
        durum: 200,
        govde: { durum: "dogrulama", clientSecret: "pi_s", anahtar: "pk" },
      },
    ];
    ciz();
    fireEvent.click(screen.getByText("Ekle · €19,99"));
    expect((await screen.findByRole("alert")).textContent).toBe(
      "Banka doğrulaması tamamlanmadı.",
    );
    expect(govdeler).toHaveLength(1);
    cleanup();
    ag.yanitlar = [{ durum: 422, govde: { error: "card_declined" } }];
    ciz();
    fireEvent.click(screen.getByText("Ekle · €19,99"));
    await waitFor(() =>
      expect(screen.getByRole("alert").textContent).toBe(
        "Kartınız bu ödemeyi kabul etmedi.",
      ),
    );
    expect(screen.getByText("Ekle · €19,99")).toBeTruthy();
  });
});
