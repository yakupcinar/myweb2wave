# Huni adım ayarları — ilerleme çubuğu ve hedef etiketleri — 2026-10-22

## Ne yapıldı

Her tanıtım, soru ve ödeme adımı `ilerlemeGoster` ayarıyla ilerleme çubuğunu
kendi üzerinde gösterebilir veya gizleyebilir. Panelde kutu varsayılan olarak
işaretlidir. Ayar sözleşmede isteğe bağlıdır; eski tanımlar aynı görünür ve
varsayılan açık değer kayda gereksiz alan olarak yazılmaz. Hedef açılır
listeleri “Devam düğmesiyle gidilecek adım” ve “Bu yanıt seçilince gidilecek
adım” diye ayrıştırıldı; sıradaki adım seçeneğinin etiketi sadeleştirildi.

## Neden böyle

Ayar adım düzeyindedir; kullanıcı bir adımda ilerleme bilgisini kaldırıp
sonraki adımda gösterebilir. Sözleşmede `default` kullanmak üretilen TypeScript
tipinde alanı zorunlu yaptı ve eski tanımların derlenmesini bozdu. Bu yüzden
sözleşme alanı isteğe bağlı; varsayılan davranış panel, dönüşüm ve oynatıcıda
uygulanıyor. `reference.php` bu çalışmadan bağımsız yerel değişiklik olduğundan
korundu ve commit'e alınmadı.

## Kırma denemeleri

| Deneme | Sonuç |
|---|---|
| Çekirdekte boolean doğrulaması kaldırıldı | 🔴 İlgili HuniTest başarısız oldu |
| Oynatıcıdaki koşullu çubuk kaldırıldı | 🔴 Gizli adım testi başarısız oldu; aynı dosyada 32 test geçti |
| Editör dönüşümünde soru adımı tercihi hep açık yapıldı | 🔴 Dönüşüm testi başarısız oldu; aynı dosyada 5 test geçti |

## Ölçüm

- `bin/kontrol`: EXIT=0, yeşil; çekirdek 187 test / 1160 assertion.
- Editör: 16 test geçti; TypeScript kontrolü ve üretim derlemesi geçti.
- Huni oynatıcı: 36 test geçti; tam kontroldeki Astro derlemesi ve boyut
  ölçümü geçti.
- API hedefli `HuniTest`: 51 test / 359 assertion.
- Tam kontrolde `df -h` kullanılabilir alanı 8,3 GiB, doluluğu %96 gösterdi.
- Panel login ve huni adresleri HTTP 200 döndürdü. Yerel tarayıcı otomasyonu
  Work ayarlarında kapalı olduğundan panelde kutunun görsel yerleşimi elle
  doğrulanamadı; panel URL'si açılmak üzere uygulama paneline gönderildi.

## Açık

- Tarayıcıda panelde ilerleme kutusunu ve hedef etiketlerini görsel olarak
  kontrol etmek.
