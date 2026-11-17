import json, sys
from PIL import Image, ImageFilter
kok = sys.argv[1]
V = list(json.load(open(kok + '/g1-varyantlar.json')).keys())
adimlar = ['karsilama', 'kiminle', 'paywall']
def kenar(yol):
    g = Image.open(yol).convert('L').filter(ImageFilter.FIND_EDGES)
    return set(i for i, p in enumerate(g.getdata()) if p > 40)
taban = {s: kenar(f'{kok}/g1/V0-{s}.png') for s in adimlar}
renk = {l.split()[0]: l.split()[1] for l in open(sys.argv[2]).read().splitlines()[1:]}
satir = []
for v in V[1:]:
    r = []
    for s in adimlar:
        k = kenar(f'{kok}/g1/{v}-{s}.png'); t = taban[s]
        r.append(len(k ^ t) / max(1, len(k | t)) * 100)
    satir.append((v, sum(r) / 3, r))
satir.sort(key=lambda x: -x[1])
print(f"{'varyant':24} {'düzen%':>7} {'renk%':>7}   düzen: tanıtım / soru / ödeme")
for v, d, r in satir:
    print(f"{v:24} {d:7.1f} {float(renk[v]):7.1f}   {' / '.join(f'{x:.1f}' for x in r)}")
