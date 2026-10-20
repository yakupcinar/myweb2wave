// Panel huni düzenleyicisi (A7 / E1.2). Derleme adımı yok, bağımlılık yok.
// Taslağı #duzenleyici'nin data-taslak'ından okur, formu kurar, "Kaydet"le
// JSON olarak çekirdeğe yollar; doğrulama çekirdekte (Tanim), burada yalnız
// hatası gösterilir. Metin yalnız textContent / value ile yazılır.
(() => {
  "use strict";
  const kok = document.getElementById("duzenleyici");
  if (!kok) return;

  let tanim = JSON.parse(kok.dataset.taslak);
  const planlar = JSON.parse(kok.dataset.planlar);
  const temaVarsayilan = JSON.parse(kok.dataset.temaVarsayilan);
  const yaziTipleri = JSON.parse(kok.dataset.yaziTipleri);
  let degisti = false;

  const yayinla = document.getElementById("yayinla");

  function oge(etiket, ozellikler = {}, ...cocuklar) {
    const e = document.createElement(etiket);
    for (const [k, v] of Object.entries(ozellikler)) {
      if (k === "metin") e.textContent = v;
      else if (k.startsWith("on")) e.addEventListener(k.slice(2), v);
      else if (v !== false && v !== undefined) e.setAttribute(k, v === true ? "" : v);
    }
    e.append(...cocuklar);
    return e;
  }

  function isaretle() {
    degisti = true;
    durumYaz("Kaydedilmemiş değişiklik var.", "reklam");
    if (yayinla) yayinla.disabled = true; // önce kaydet: yayın taslağı yayınlar
  }

  /** Metin alanı: değişince tanımdaki alanı günceller, formu yeniden kurmaz (odak kaybolmasın). */
  function alan(etiket, nesne, anahtar, { cok = false, sinif = "" } = {}) {
    const giris = cok ? oge("textarea", { rows: 3 }) : oge("input", { type: "text" });
    giris.value = nesne[anahtar] ?? "";
    giris.addEventListener("input", () => {
      nesne[anahtar] = giris.value;
      isaretle();
    });
    return oge("label", { class: sinif }, oge("span", { metin: etiket }), giris);
  }

  function yeniKimlik(on, liste) {
    let i = liste.length + 1;
    while (liste.some((x) => x.id === `${on}-${i}`)) i++;
    return `${on}-${i}`;
  }

  /** Yapı değişti: diziyi değiştir, formu yeniden kur. */
  function degistir(islem) {
    islem();
    isaretle();
    kur();
  }

  function tasiDugmeleri(liste, i, { enAz = 0, enCok = liste.length - 1 } = {}) {
    const tasi = (j) => () =>
      degistir(() => {
        [liste[i], liste[j]] = [liste[j], liste[i]];
      });
    return [
      oge("button", { type: "button", metin: "↑", title: "Yukarı", disabled: i <= enAz, onclick: tasi(i - 1) }),
      oge("button", { type: "button", metin: "↓", title: "Aşağı", disabled: i >= enCok, onclick: tasi(i + 1) }),
    ];
  }

  function adimKur(adim, i) {
    const adimlar = tanim.adimlar;
    const sonIndeks = adimlar.length - 1;
    const paywall = adim.tur === "paywall";
    const tur = { tanitim: "Tanıtım", soru: "Soru", paywall: "Ödeme (paywall) — her zaman en sonda" }[adim.tur];
    const kutu = oge("fieldset", { "data-adim": adim.id }, oge("legend", { metin: `Adım ${i + 1} · ${tur}` }));

    if (!paywall) {
      kutu.append(
        oge(
          "div",
          { class: "satir" },
          ...tasiDugmeleri(adimlar, i, { enCok: sonIndeks - 1 }),
          oge("button", {
            type: "button",
            metin: "Sil",
            onclick: () => degistir(() => adimlar.splice(i, 1)),
          }),
        ),
      );
    }
    kutu.append(alan("Kimlik (panelde görünür; yayından sonra değiştirmek eski veriyle eşleşmeyi koparır)", adim, "id", { sinif: "kimlik" }));

    if (adim.tur === "soru") {
      kutu.append(alan("Soru", adim, "soru"));
      adim.secenekler.forEach((s, j) => {
        kutu.append(
          oge(
            "div",
            { class: "satir", "data-secenek": s.id },
            alan(`Seçenek ${j + 1}`, s, "metin"),
            alan("Kimlik", s, "id", { sinif: "kimlik" }),
            ...tasiDugmeleri(adim.secenekler, j),
            oge("button", { type: "button", metin: "Sil", onclick: () => degistir(() => adim.secenekler.splice(j, 1)) }),
          ),
        );
      });
      kutu.append(
        oge("button", {
          type: "button",
          metin: "+ Seçenek",
          onclick: () => degistir(() => adim.secenekler.push({ id: yeniKimlik("secenek", adim.secenekler), metin: "" })),
        }),
      );
      return kutu;
    }

    kutu.append(alan("Başlık", adim, "baslik"), alan(paywall ? "Fiyat satırı" : "Metin", adim, "metin", { cok: !paywall }), alan("Düğme", adim, "dugme"));
    if (paywall) {
      const secim = oge("select", { onchange: () => { adim.plan = secim.value; isaretle(); } }, ...planlar.map((p) => oge("option", { value: p, metin: p })));
      secim.value = adim.plan;
      kutu.append(oge("label", {}, oge("span", { metin: "Plan" }), secim));
    }
    return kutu;
  }

  /** WCAG kontrast (çekirdekteki Tema::kontrast ile aynı) — yalnız bilgi; karar çekirdekte. */
  function kontrast(a, b) {
    const p = (hex) => {
      const k = (i) => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
      };
      return 0.2126 * k(1) + 0.7152 * k(3) + 0.0722 * k(5);
    };
    const [x, y] = [p(a), p(b)];
    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
  }

  /** Görünüm (E2.2): tema yoksa huni varsayılanla görünür; kutu işaretlenince özel tema. */
  function temaKur() {
    const kutu = oge("fieldset", { "data-bolum": "tema" }, oge("legend", { metin: "Görünüm" }));
    const ozel = oge("input", { type: "checkbox" });
    ozel.checked = !!tanim.tema;
    ozel.addEventListener("change", () =>
      degistir(() => {
        if (ozel.checked) tanim.tema = { ...temaVarsayilan };
        else delete tanim.tema;
      }),
    );
    kutu.append(oge("label", { class: "satir" }, ozel, oge("span", { metin: "Bu huniye özel renk ve yazı tipi" })));
    if (!tanim.tema) return kutu;

    const t = tanim.tema;
    const oran = oge("span");
    const oranYaz = () => {
      const r = kontrast(t.metinRengi, t.arkaPlan);
      oran.textContent = `Metin okunurluğu ${r.toFixed(2)}:1 ${r >= 4.5 ? "✓" : "— en az 4,5:1 olmalı, yayınlanamaz"}`;
      oran.className = r >= 4.5 ? "odedi" : "hata";
    };
    const renk = (etiket, anahtar) => {
      const g = oge("input", { type: "color" });
      g.value = t[anahtar];
      g.addEventListener("input", () => {
        t[anahtar] = g.value.toLowerCase();
        oranYaz();
        isaretle();
      });
      return oge("label", {}, oge("span", { metin: etiket }), g);
    };
    const yazi = oge("select", {}, ...yaziTipleri.map((y) => oge("option", { value: y, metin: y })));
    yazi.value = t.yaziTipi;
    yazi.addEventListener("change", () => {
      t.yaziTipi = yazi.value;
      isaretle();
    });
    const kose = oge("input", { type: "number", min: 0, max: 32, step: 1 });
    kose.value = String(t.kose);
    kose.addEventListener("input", () => {
      t.kose = Number.parseInt(kose.value, 10) || 0;
      isaretle();
    });
    kutu.append(
      oge("div", { class: "satir" }, renk("Ana renk (düğme)", "anaRenk"), renk("Arka plan", "arkaPlan"), renk("Metin", "metinRengi")),
      oge("div", { class: "satir" }, oge("label", {}, oge("span", { metin: "Yazı tipi" }), yazi), oge("label", {}, oge("span", { metin: "Köşe (px)" }), kose)),
      oran,
    );
    oranYaz();
    return kutu;
  }

  function ekleDugmesi(metin, uret) {
    return oge("button", {
      type: "button",
      metin,
      // Yeni adım ödemenin hemen önüne eklenir (ödeme hep sonda).
      onclick: () => degistir(() => tanim.adimlar.splice(tanim.adimlar.length - 1, 0, uret())),
    });
  }

  let durumSatiri;
  function durumYaz(metin, sinif = "") {
    if (!durumSatiri) return;
    durumSatiri.className = sinif;
    durumSatiri.textContent = metin;
  }

  const hataListesi = oge("ul", { class: "hata", role: "alert" });

  async function kaydet(dugme) {
    dugme.disabled = true;
    hataListesi.replaceChildren();
    try {
      const yanit = await fetch(kok.dataset.kaydet, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": kok.dataset.jeton },
        body: JSON.stringify({ tanim }),
      });
      const govde = await yanit.json();
      if (yanit.ok) {
        degisti = false;
        durumYaz("Kaydedildi. Önizleyebilir ya da yayınlayabilirsin.", "odedi");
        if (yayinla) {
          yayinla.disabled = false;
          yayinla.textContent = "Yayınla";
        }
      } else {
        durumYaz("Kaydedilmedi — düzelt:", "hata");
        for (const h of govde.hatalar ?? []) hataListesi.append(oge("li", { metin: h }));
      }
    } catch {
      durumYaz("Bağlantı hatası — kaydedilmedi.", "hata");
    } finally {
      dugme.disabled = false;
    }
  }

  function kur() {
    const kaydetDugmesi = oge("button", { type: "button", class: "ana", metin: "Kaydet", onclick: () => kaydet(kaydetDugmesi) });
    durumSatiri = oge("span", { metin: degisti ? "Kaydedilmemiş değişiklik var." : "", class: degisti ? "reklam" : "" });
    kok.replaceChildren(
      alan("Sayfa başlığı (tarayıcı sekmesi)", tanim, "sayfaBasligi"),
      temaKur(),
      ...tanim.adimlar.map(adimKur),
      oge(
        "div",
        { class: "satir" },
        ekleDugmesi("+ Tanıtım adımı", () => ({ id: yeniKimlik("tanitim", tanim.adimlar), tur: "tanitim", baslik: "", metin: "", dugme: "Devam" })),
        ekleDugmesi("+ Soru", () => ({
          id: yeniKimlik("soru", tanim.adimlar),
          tur: "soru",
          soru: "",
          secenekler: [
            { id: "secenek-1", metin: "" },
            { id: "secenek-2", metin: "" },
          ],
        })),
      ),
      // Hatalar sabit alt çubukta: formun dibinde kalınca görünmüyordu (tarayıcıda ölçüldü).
      oge("div", { class: "durum" }, hataListesi, kaydetDugmesi, durumSatiri),
    );
  }

  window.addEventListener("beforeunload", (e) => {
    if (degisti) e.preventDefault();
  });
  kur();
})();
