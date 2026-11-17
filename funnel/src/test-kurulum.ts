// Huni testlerinin ortak kurulumu (Ç151, panelin Ç134'ü): Testing Library'nin
// bekleme üst sınırı 1 sn → 3 sn. bin/kontrol'ün yükü altında Huni.test'in Ç34
// testindeki waitFor iki kez 1 sn'yi aştı, yalnız koşuda hep yeşildi. Üst
// sınırdır: geçen test ilk denemede biter, süre uzamaz.
import { configure } from "@testing-library/preact";

configure({ asyncUtilTimeout: 3000 });
