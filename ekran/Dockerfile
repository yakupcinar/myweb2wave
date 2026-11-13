# AI ekran görüntüsü için başsız Chrome (Ç72). Taban imajda yalnız DejaVu var:
# emoji kutu olarak çiziliyordu, AI'ın gözü seçenek simgesini görmüyordu
# (Mobil görünüm 3, ölçüldü). Renkli emoji yazı tipi Debian'dan (OFL-1.1).
FROM chromedp/headless-shell:155.0.8059.40@sha256:0dc9356ce1428962cddcf938dab712a8c56de79adc326f9138298f13df440071

RUN apt-get update \
    && apt-get install -y --no-install-recommends fonts-noto-color-emoji \
    && rm -rf /var/lib/apt/lists/*
