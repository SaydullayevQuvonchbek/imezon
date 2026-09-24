-- ============================================================
--  IMezon — Sotuvchi moduli tuzatishlari uchun migratsiya (2026-09)
--  Qo'llash:  mariadb -uroot imezon_db < migratsiya-2026-09-sotuvchi.sql
--  Oldin:     mariadb-dump -uroot imezon_db > backup-YYYY-MM-DD.sql
--  Idempotent emas — bir marta bajaring. Qaytarish SQL'i faylning oxirida.
-- ============================================================

-- Takroriy yuborishdan himoya (idempotentlik kaliti).
-- Muammo: ofitsant "Pauza" ni bosadi, server orderni YARATADI va rezervni
-- band qiladi, lekin javob tarmoqda yo'qoladi. Brauzerda order_id hamon 0
-- bo'lgani uchun ofitsant qayta bosadi — natijada bitta stolda ikkita bir
-- xil "olib ketish" orderi va ikki barobar band qilingan qoldiq.
-- Endi brauzer har bir yangi buyurtma uchun bir martalik token yuboradi:
-- shu token bilan order allaqachon bo'lsa, yangisi ochilmaydi. UNIQUE
-- indeks ikkita so'rov AYNI paytda kelgan holatni ham to'xtatadi.
ALTER TABLE im_sotuvchi_order
  ADD COLUMN client_token VARCHAR(40) NULL DEFAULT NULL
    COMMENT 'Brauzer bergan bir martalik kalit — takroriy yuborishdan himoya'
    AFTER izoh,
  ADD UNIQUE KEY uniq_client_token (client_token);

-- ------------------------------------------------------------
-- QAYTARISH (rollback) — faqat kerak bo'lsa, qo'lda:
-- ALTER TABLE im_sotuvchi_order DROP INDEX uniq_client_token, DROP COLUMN client_token;
-- ------------------------------------------------------------
