// Panel testlerinin ortak kurulumu (Ç134): Testing Library'nin bekleme üst
// sınırı 1 sn → 3 sn. bin/kontrol'ün yükü altında findBy / waitFor iki kez
// (Huniler, Deneyler) 1 sn'yi aştı, yalnız koşuda hep yeşildi. Üst sınırdır:
// geçen test ilk denemede biter, süre uzamaz.
import { configure } from "@testing-library/react";

configure({ asyncUtilTimeout: 3000 });
