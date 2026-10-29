import json, sys
from PIL import Image, ImageChops
kok = sys.argv[1]
V = list(json.load(open(kok + '/g1-varyantlar.json')).keys())
adimlar = ['karsilama', 'kiminle', 'paywall']
def olc(a, b):
    A = Image.open(a).convert('RGB'); B = Image.open(b).convert('RGB')
    if A.size != B.size: return (float('nan'),) * 2
    d = ImageChops.difference(A, B); px = list(d.getdata()); n = len(px)
    return sum(1 for p in px if max(p) > 32) / n * 100, sum(sum(p) for p in px) / (n * 3) / 255 * 100
satir = []
for v in V[1:]:
    r = [olc(f'{kok}/g1/V0-{s}.png', f'{kok}/g1/{v}-{s}.png') for s in adimlar]
    satir.append((v, sum(x[0] for x in r) / 3, sum(x[1] for x in r) / 3, [x[0] for x in r]))
satir.sort(key=lambda x: -x[1])
print(f"{'varyant':24} {'değişen%':>9} {'ort.fark%':>9}   tanıtım / soru / ödeme")
for v, d, o, dg in satir:
    print(f"{v:24} {d:9.1f} {o:9.1f}   {' / '.join(f'{x:.1f}' for x in dg)}")
