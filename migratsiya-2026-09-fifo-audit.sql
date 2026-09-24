-- ============================================================
--  IMezon — FIFO audit tuzatishlari uchun migratsiya (2026-09)
--  Qo'llash:  mariadb -uroot imezon_db < migratsiya-2026-09-fifo-audit.sql
--  Oldin:     mariadb-dump -uroot imezon_db > backup-YYYY-MM-DD.sql
--  Idempotent emas — bir marta bajaring. Qaytarish SQL'i faylning oxirida.
-- ============================================================

-- O2: bekor qilingan buyurtmadagi pishirilgan taom = oshxona isrofi.
--     Xomashyo FIFO'dan yechilgan holda qoladi (jismonan to'g'ri), faqat
--     sabab yorlig'i va vaqti qo'shiladi — hisobotlar shu bo'yicha chegiradi.
ALTER TABLE im_ishlab_chiqarish
  MODIFY holat ENUM('bajarildi','bekor','isrof') COLLATE utf8mb4_unicode_ci DEFAULT 'bajarildi',
  ADD COLUMN isrof_vaqt DATETIME NULL DEFAULT NULL AFTER holat,
  ADD KEY idx_isrof (holat, isrof_vaqt);

-- O3-h: smena yopilganda hisoblangan tannarx/foyda saqlanadi —
--       smena-detail.php ularni qayta ko'rsata oladi.
ALTER TABLE im_smena
  ADD COLUMN tannarx   DECIMAL(15,2) NULL DEFAULT NULL AFTER yopish_naqd,
  ADD COLUMN isrof     DECIMAL(15,2) NULL DEFAULT NULL AFTER tannarx,
  ADD COLUMN sof_foyda DECIMAL(15,2) NULL DEFAULT NULL AFTER isrof;

-- K2: konvensiyani ustunning o'zida hujjatlash (vozvrat-save.php JAMI yozadi).
ALTER TABLE im_vozvratlar
  MODIFY tannarx DECIMAL(20,6) DEFAULT '0.000000'
    COMMENT 'JAMI qaytarilgan tannarx (birlik EMAS): birlik = tannarx / soni';

-- ------------------------------------------------------------
-- QAYTARISH (rollback) — faqat kerak bo'lsa, qo'lda:
-- ALTER TABLE im_smena DROP COLUMN sof_foyda, DROP COLUMN isrof, DROP COLUMN tannarx;
-- ALTER TABLE im_ishlab_chiqarish DROP KEY idx_isrof, DROP COLUMN isrof_vaqt;
-- UPDATE im_ishlab_chiqarish SET holat='bekor' WHERE holat='isrof';
-- ALTER TABLE im_ishlab_chiqarish MODIFY holat ENUM('bajarildi','bekor') DEFAULT 'bajarildi';
-- ------------------------------------------------------------

-- ============================================================
--  2-qism (4-bosqich: texnik qarz) — 2026-09-21
-- ============================================================

-- K3: INVENTARIZATSIYA (sanoq / korrektirovka).
--     FIFO daftarini real qoldiq bilan moslashtirishning yagona rasmiy yo'li.
CREATE TABLE IF NOT EXISTS `im_inventarizatsiya` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` int(11) NOT NULL COMMENT '0 = ombor, >0 = filial',
  `xodim_id` int(11) DEFAULT NULL,
  `sana` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `izoh` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ortiqcha_summa` decimal(15,2) NOT NULL DEFAULT '0.00',
  `kamomad_summa` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `joy_sana` (`location_id`,`sana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `im_inventarizatsiya_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inv_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `hisob_soni` decimal(18,3) NOT NULL COMMENT 'Tizim (FIFO) qoldig\'i',
  `real_soni` decimal(18,3) NOT NULL COMMENT 'Sanoqda topilgan',
  `farq` decimal(18,3) NOT NULL,
  `birlik_narx` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `summa` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Musbat = ortiqcha, manfiy = kamomad',
  PRIMARY KEY (`id`),
  KEY `inv_id` (`inv_id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  CONSTRAINT `im_inv_item_header` FOREIGN KEY (`inv_id`) REFERENCES `im_inventarizatsiya` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- K3: yopilgan partiyani bekor qilish uchun terminal holat.
ALTER TABLE `im_partiyalar`
  MODIFY `holat` ENUM('ochiq','yopiq','bekor') COLLATE utf8mb4_unicode_ci DEFAULT 'ochiq';

-- ------------------------------------------------------------
-- QAYTARISH (2-qism):
-- ALTER TABLE im_partiyalar MODIFY holat ENUM('ochiq','yopiq') DEFAULT 'ochiq';
-- DROP TABLE im_inventarizatsiya_items; DROP TABLE im_inventarizatsiya;
-- ------------------------------------------------------------
