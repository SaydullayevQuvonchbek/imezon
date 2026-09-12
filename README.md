# IMezon — Do'kon va restoran boshqaruv tizimi

Klassik PHP + MySQLi monolit (framework yo'q, build-tool yo'q). Har bir
sahifa o'zi to'liq: PHP (server logika) + HTML + inline JS bitta faylda.

## Qayerdan boshlash kerak

1. **[config.php](config.php)** — DB ulanish, muhit sozlamalari, va butun
   loyiha tayanadigan "yagona manba" funksiyalari: `im_rollar()` (rol
   ro'yxati), `im_tt_label()` (to'lov turi nomlari), `im_hall_stollar()`
   (zal xaritasi), `im_rezerv()`/`im_filial_qoldiq_yech()` (ombordan
   atomar yechish). Yangi konstanta yoki umumiy funksiya kerak bo'lsa —
   birinchi navbatda shu faylga qarang, boshqa joyda takrorlamang.
2. **[ximoya.php](ximoya.php)** — auth guard. Har bir himoyalangan
   sahifa boshida `require_once __DIR__ . '/../ximoya.php'` bo'ladi.
   Sessiya, CSRF va rol tekshiruvi (`im_rol_check([...])`) shu yerda.
3. **[assets/js/main.js](assets/js/main.js)** — global JS: `IMAjax`
   (fetch o'ramchisi, CSRF avtomatik qo'shiladi), `NHToast`, `NHModal`,
   `NHConfirm`, va `im_esc()`/`im_esc_attr_js()` — bazadan kelgan matnni
   `innerHTML` ga qo'yishdan oldin ALBATTA shular orqali o'tkazing
   (pastdagi "Xavfsizlik qoidalari" bo'limiga qarang).

## Modullar (rol → papka)

Rol ro'yxati va kirish sahifasi **faqat** `config.php`dagi
`im_rollar()`da yozilgan — yangi rol qo'shsangiz shu yerni tahrirlang,
boshqa hech qayerda takrorlash shart emas.

| Rol | Papka | Vazifasi |
|---|---|---|
| `admin` | [admin/](admin/) | Dashboard, xodimlar, moliya, hisobot, sozlamalar, AI tahlilchi |
| `bosh_kassir` | [kassa/](kassa/) | Markaz xazinachisi — inkasso, harajat, maosh, qarz |
| `sklad` | [sklad/](sklad/) | Tovar qabul qilish, mahsulotlar, barkod, filialga jo'natish |
| `kassir` | [dukon/](dukon/) | Do'kon POS kassasi — sotuv, smena, nasiya, vozvrat, zal xaritasi |
| `sotuvchi` | [sotuvchi/](sotuvchi/) | Ofitsant — stol/dastavka buyurtmalari |
| `oshpaz` | [oshpaz/](oshpaz/) | Oshxona ekrani — real vaqt buyurtmalar, qozon hisobi |

Qo'shimcha: [qayta-ishlash/](qayta-ishlash/) — retseptlar va ishlab
chiqarish (admin/sklad kiradi), [print/](print/) — chek/smena chop
etish shablon fayllari.

## Baza va o'rnatish (Deploy)

Yangi serverda bazani sozlash uchun loyiha ildizidagi [schema.sql](schema.sql) faylini MySQL bazangizga import qiling:

```bash
mysql -u foydalanuvchi -p baza_nomi < schema.sql
```

Ushbu fayl barcha 62 ta jadval strukturasini va boshlang'ich tizim ma'lumotlarini (standart sozlamalar, rollar, admin foydalanuvchi) o'z ichiga oladi.

## Muhit sozlamalari

`.env` fayli ishlatilmaydi — muhit o'zgaruvchilari `getenv()` orqali
o'qiladi, sozlanmasa **lokal ishlash uchun mos** standart qiymatlarga
tushadi (pastga qarang). Production serverda buni haqiqiy qiymatlarga
almashtiring — [.env.example](.env.example) da to'liq ro'yxat va izoh.

Lokal (OSPanel) uchun standart holatning o'zi ishlaydi: baza nomi
`u1782683_imezon`, foydalanuvchi `root`, parolsiz.

## Xavfsizlik qoidalari (buzmang)

- **Matn maydonlari bazada XOM saqlanadi** (faqat
  `mysqli_real_escape_string()` bilan SQL uchun escape qilingan).
  HTML-escape hech qachon yozishda emas, **chiqishda** qo'llanadi:
  PHP tarafida `im_f()`, JS tarafida (AJAX/JSON orqali kelgan va
  `innerHTML`ga qo'yiladigan matn uchun) `im_esc()` — HTML-atribut
  ichidagi bir tirnoqli JS satr uchun esa `im_esc_attr_js()`
  ([assets/js/main.js](assets/js/main.js)). Yangi joyda mahsulot nomi,
  mijoz ismi, izoh kabi bazadan kelgan matnni `innerHTML`ga qo'yishdan
  oldin shu funksiyalardan birini albatta chaqiring — aks holda
  saqlanuvchi XSS ochilib qoladi.
- **CSRF** — har bir yozuv (POST) endpointi `ximoya.php`dan avtomatik
  himoyalangan (`X-IM-CSRF` sarlavhasi, `main.js` o'zi qo'shadi).
  Alohida tekshiruv yozish shart emas.
- **Ombordan yechish/band qilish atomar bo'lishi shart** — hech qachon
  "avval SELECT, keyin UPDATE" yozmang (poyga holati). `config.php`dagi
  `im_rezerv()` yoki `im_filial_qoldiq_yech()`ni chaqiring (ular
  `UPDATE ... WHERE soni>=$x` + `affected()` tekshiruvi orqali ishlaydi),
  yoki bir nechta qatorni FIFO tartibida yechayotgan bo'lsangiz
  `SELECT ... FOR UPDATE` bilan qulflang ([dukon/ajax/sotuv-save.php](dukon/ajax/sotuv-save.php)
  ga qarang).
- **Rol tekshiruvi** — har bir sahifa/ajax endpoint boshida
  `im_rol_check(['admin', ...])` chaqiring.

## Testlar

[tests/](tests/) papkasida DB'ga ulanadigan (o'qish/yozish
tranzaksiya ichida, oxirida rollback) va sof-funksiya testlari bor.
Ishga tushirish:

```bash
php tests/run.php
```

PHP CLI PATH'da bo'lmasligi mumkin — OSPanel'da odatda
`C:/OSPanel/modules/php/PHP_7.4/php.exe`.
