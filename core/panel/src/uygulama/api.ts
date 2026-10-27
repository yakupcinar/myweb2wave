// Panel API istemcisi (B5). Tipler contract/openapi.yaml'dan ÜRETİLİR
// (src/sozlesme.ts). Oturum düşünce güvenlik duvarı JSON ucunu giriş
// sayfasına yönlendirir (oturum.ts) — o zaman tarayıcı girişe gider.
import createClient from "openapi-fetch";
import type { paths } from "../sozlesme";
import { oturumDustu } from "../oturum";

export const GIRIS = "/panel/giris";

export const api = createClient<paths>({
    baseUrl: "",
    fetch: async (istek) => {
        const yanit = await fetch(istek);
        if (oturumDustu(yanit)) {
            window.location.assign(GIRIS);
            throw new Error("oturum düştü");
        }
        return yanit;
    },
});
