// Bölmeli seçici (P6–P7a, Apple "segmented control"): gri zeminde seçili parça
// beyaz. Seçili parça aria-pressed ya da aria-current ile işaretlenir.
// P7f: sığmazsa kendi içinde kayar — telefonda Aboneler'in altı seçenekli
// "Durum"u sayfayı 435 px'e açıyordu (ekran döngüsü ölçtü).
export const bolmeliSinif =
    "inline-flex max-w-full shrink-0 gap-0.5 overflow-x-auto rounded-[9px] bg-dolgu p-[2px]";
export const bolmeSinifi =
    "rounded-[7px] px-3 py-1 text-[13px] whitespace-nowrap text-metin hover:bg-yuzey/60 aria-pressed:bg-yuzey aria-pressed:font-semibold aria-pressed:shadow-sm aria-[current=page]:bg-yuzey aria-[current=page]:font-semibold aria-[current=page]:shadow-sm";
