# Tuzaklar — hata vermeden YANLIŞ CEVAP üretenler

> CLAUDE.md §4'teki her satırın tam metni burada. Yeni tuzak: **belirti**
> (ne gördün) · **sebep** · **ölçüm** (nasıl kanıtladın) · **doğrusu**.
> İkisine birden yazılır.

## §1 — Boru çıkış kodunu yutar
**Belirti:** kırmızı süit yeşil görünür. **Sebep:** `$?` borunun son
komutunundur. **Doğrusu:** `komut > log 2>&1; echo EXIT=$?`.

## §18 — Boş kapsayıcıya bağlı değiştirici koşmaz (SwiftUI)
**Belirti:** profil fotoğrafı hiç yüklenmedi, hata/istek yok.
**Sebep:** `Group { if let resim … }.task { … }` — `Group` değiştiricileri
çocuklarına dağıtır, çocuk yokken `.task` hiç çalışmaz. **Ölçüm**
(KeepChat Ç136): sunucu günlüğünde istek sayısı, önce 0 sonra 1.
**Doğrusu:** `ZStack`.

<!-- Diğer tuzaklar ilk kez ısırdıklarında burada açılır. -->
