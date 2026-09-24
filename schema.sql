-- ============================================================
--  IMezon — Baza strukturasi (schema.sql)
--  Avtomatik hosil qilingan: 2026-09-12 08:26:01
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ------------------------------------------------------------
-- Jadval: `im_ai_hisobot`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_ai_hisobot`;
CREATE TABLE IF NOT EXISTS `im_ai_hisobot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tur` varchar(20) NOT NULL DEFAULT 'kunlik',
  `sana` date NOT NULL,
  `filial_id` int(11) NOT NULL DEFAULT '0',
  `matn` mediumtext,
  `kontekst` mediumtext,
  `model` varchar(60) DEFAULT NULL,
  `tokens_in` int(11) NOT NULL DEFAULT '0',
  `tokens_out` int(11) NOT NULL DEFAULT '0',
  `xato` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tur_sana_filial` (`tur`,`sana`,`filial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_ai_log`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_ai_log`;
CREATE TABLE IF NOT EXISTS `im_ai_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `xodim_id` int(11) NOT NULL DEFAULT '0',
  `filial_id` int(11) NOT NULL DEFAULT '0',
  `savol` text,
  `javob` mediumtext,
  `vositalar` text,
  `model` varchar(60) DEFAULT NULL,
  `tokens_in` int(11) NOT NULL DEFAULT '0',
  `tokens_out` int(11) NOT NULL DEFAULT '0',
  `ms` int(11) NOT NULL DEFAULT '0',
  `xato` varchar(500) DEFAULT NULL,
  `vaqt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `xodim_vaqt` (`xodim_id`,`vaqt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_ai_suhbat`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_ai_suhbat`;
CREATE TABLE IF NOT EXISTS `im_ai_suhbat` (
  `id` varchar(40) NOT NULL,
  `xodim_id` int(11) NOT NULL DEFAULT '0',
  `filial_id` int(11) NOT NULL DEFAULT '0',
  `model` varchar(60) DEFAULT NULL,
  `effort` varchar(10) DEFAULT NULL,
  `sarlavha` varchar(200) DEFAULT NULL,
  `xabarlar` mediumtext,
  `soni` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `xodim_yangi` (`xodim_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_balans`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_balans`;
CREATE TABLE IF NOT EXISTS `im_balans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tur` enum('kirim','chiqim') COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategoriya` enum('sotuv_naqd','sotuv_karta','sotuv_bank','sotuv_usd','usd_qaytim','nasiya_tolov','postavshik_tolov','harajat','maosh','admin_harajat','inkasasiya_chiqim','inkasasiya_kirim','inkasso','vozvrat_chiqim','vozvrat_kirim','admin_pul_olish','boshqa_kirim','boshqa_chiqim','smena_ochish') COLLATE utf8mb4_unicode_ci NOT NULL,
  `summa_som` decimal(15,2) NOT NULL DEFAULT '0.00',
  `summa_usd` decimal(10,4) DEFAULT '0.0000',
  `usd_kurs` decimal(12,2) DEFAULT '0.00',
  `manba_id` int(11) DEFAULT NULL,
  `manba_tur` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `xodim_id` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tur_sana` (`tur`,`sana`),
  KEY `filial_id` (`filial_id`),
  KEY `kategoriya` (`kategoriya`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_barkod`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_barkod`;
CREATE TABLE IF NOT EXISTS `im_barkod` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mahsulot_id` int(11) NOT NULL,
  `partiya_item_id` int(11) DEFAULT NULL,
  `barkod` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `soni` int(11) DEFAULT '1',
  `chiqdi` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  KEY `barkod` (`barkod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_chegirmalar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_chegirmalar`;
CREATE TABLE IF NOT EXISTS `im_chegirmalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tur` enum('mahsulot','kategoriya') COLLATE utf8mb4_unicode_ci NOT NULL,
  `mahsulot_id` int(11) DEFAULT NULL,
  `kategoriya_id` int(11) DEFAULT NULL,
  `chegirma_foiz` decimal(5,2) NOT NULL,
  `bosh_sana` date DEFAULT NULL,
  `tug_sana` date DEFAULT NULL,
  `aktiv` tinyint(4) DEFAULT '1',
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  KEY `kategoriya_id` (`kategoriya_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_fifo_layers`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_fifo_layers`;
CREATE TABLE IF NOT EXISTS `im_fifo_layers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `location_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `partiya_item_id` int(11) DEFAULT NULL,
  `source` varchar(40) NOT NULL,
  `source_id` bigint(20) NOT NULL,
  `initial_qty` decimal(18,3) NOT NULL,
  `remaining_qty` decimal(18,3) NOT NULL,
  `unit_cost` decimal(20,6) NOT NULL,
  `created_at` datetime NOT NULL,
  `cancelled` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fifo_order` (`location_id`,`mahsulot_id`,`cancelled`,`id`),
  KEY `origin` (`source`,`source_id`),
  KEY `partiya` (`partiya_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_fifo_locks`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_fifo_locks`;
CREATE TABLE IF NOT EXISTS `im_fifo_locks` (
  `location_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  PRIMARY KEY (`location_id`,`mahsulot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_fifo_meta`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_fifo_meta`;
CREATE TABLE IF NOT EXISTS `im_fifo_meta` (
  `name` varchar(60) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_fifo_movements`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_fifo_movements`;
CREATE TABLE IF NOT EXISTS `im_fifo_movements` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `layer_id` bigint(20) NOT NULL,
  `source` varchar(40) NOT NULL,
  `source_id` bigint(20) NOT NULL,
  `kind` varchar(12) NOT NULL,
  `qty` decimal(18,3) NOT NULL,
  `unit_cost` decimal(20,6) NOT NULL,
  `reversed_qty` decimal(18,3) NOT NULL DEFAULT '0.000',
  `original_id` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `origin` (`source`,`source_id`,`kind`),
  KEY `layer` (`layer_id`),
  CONSTRAINT `fifo_movement_layer` FOREIGN KEY (`layer_id`) REFERENCES `im_fifo_layers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_inventarizatsiya` (sanoq / korrektirovka)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_inventarizatsiya_items`;
DROP TABLE IF EXISTS `im_inventarizatsiya`;
CREATE TABLE IF NOT EXISTS `im_inventarizatsiya` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` int(11) NOT NULL,
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
  `hisob_soni` decimal(18,3) NOT NULL,
  `real_soni` decimal(18,3) NOT NULL,
  `farq` decimal(18,3) NOT NULL,
  `birlik_narx` decimal(20,6) NOT NULL DEFAULT '0.000000',
  `summa` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `inv_id` (`inv_id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  CONSTRAINT `im_inv_item_header` FOREIGN KEY (`inv_id`) REFERENCES `im_inventarizatsiya` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Jadval: `im_filial_qoldiq`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_filial_qoldiq`;
CREATE TABLE IF NOT EXISTS `im_filial_qoldiq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filial_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `soni` decimal(10,3) NOT NULL DEFAULT '0.000',
  `olchov_kg` decimal(10,3) DEFAULT '0.000',
  `olchov_m` decimal(10,2) DEFAULT '0.00',
  `sotuv_narxi` decimal(15,2) DEFAULT NULL,
  `kelish_narxi` decimal(20,6) DEFAULT '0.000000',
  `qoldiq` decimal(10,3) DEFAULT '0.000',
  PRIMARY KEY (`id`),
  UNIQUE KEY `filial_mah` (`filial_id`,`mahsulot_id`),
  KEY `mahsulot_id` (`mahsulot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_filiallar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_filiallar`;
CREATE TABLE IF NOT EXISTS `im_filiallar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kod` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manzil` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefon` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rang` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '#e2b96f',
  `tartib` int(11) DEFAULT '1',
  `status` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_harajatlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_harajatlar`;
CREATE TABLE IF NOT EXISTS `im_harajatlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tur` enum('ijara','maosh','yuk','kommunal','reklama','boshqa') COLLATE utf8mb4_unicode_ci DEFAULT 'boshqa',
  `summa` decimal(12,2) NOT NULL,
  `tolov_turi` enum('naqd','karta','bank','usd') COLLATE utf8mb4_unicode_ci DEFAULT 'naqd',
  `usd_summa` decimal(10,4) DEFAULT '0.0000',
  `usd_kurs` decimal(12,2) DEFAULT '0.00',
  `sana` date NOT NULL,
  `xodim_id` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_filial_sana` (`filial_id`,`sana`),
  KEY `idx_tolov_sana` (`tolov_turi`,`sana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_inkasasiya`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_inkasasiya`;
CREATE TABLE IF NOT EXISTS `im_inkasasiya` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filial_id` int(11) DEFAULT NULL,
  `xodim_id` int(11) DEFAULT NULL,
  `summa` decimal(15,2) DEFAULT NULL,
  `tolov_turi` enum('naqd','karta','bank','usd') COLLATE utf8mb4_unicode_ci DEFAULT 'naqd',
  `usd_summa` decimal(10,4) DEFAULT '0.0000',
  `usd_kurs` decimal(12,2) DEFAULT '0.00',
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `holat` enum('kutilmoqda','qabul_qilindi','bekor_qilindi') COLLATE utf8mb4_unicode_ci DEFAULT 'kutilmoqda',
  `admin_id` int(11) DEFAULT NULL,
  `qabul_sana` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_filial_holat_sana` (`filial_id`,`holat`,`sana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_ishlab_chiqarish`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_ishlab_chiqarish`;
CREATE TABLE IF NOT EXISTS `im_ishlab_chiqarish` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `retsept_id` int(11) NOT NULL,
  `tur` enum('ishlab_chiqarish','maydalash') COLLATE utf8mb4_unicode_ci NOT NULL,
  `filial_id` int(11) DEFAULT NULL COMMENT 'NULL=sklad, >0=filial',
  `soni` decimal(10,3) NOT NULL COMMENT 'Necha marta (koeffitsient)',
  `mahsulot_id` int(11) NOT NULL COMMENT 'ishlab_chiqarish: tayyor; maydalash: asosiy',
  `chiqish_soni` decimal(10,3) DEFAULT '0.000' COMMENT 'Jami hosil bolgan/sarflangan',
  `tannarx` decimal(20,6) DEFAULT '0.000000',
  `qoshimcha_xarajat` decimal(15,2) DEFAULT '0.00' COMMENT 'REZERV: kelajak uchun, hozir 0',
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `xodim_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `holat` enum('bajarildi','bekor','isrof') COLLATE utf8mb4_unicode_ci DEFAULT 'bajarildi',
  `isrof_vaqt` datetime DEFAULT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `returned_servings` decimal(18,3) NOT NULL DEFAULT '0.000',
  PRIMARY KEY (`id`),
  KEY `retsept_id` (`retsept_id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  KEY `filial_id` (`filial_id`),
  KEY `sana` (`sana`),
  KEY `idx_isrof` (`holat`,`isrof_vaqt`),
  CONSTRAINT `im_ishlab_chiqarish_ibfk_1` FOREIGN KEY (`retsept_id`) REFERENCES `im_retseptlar` (`id`),
  CONSTRAINT `im_ishlab_chiqarish_ibfk_2` FOREIGN KEY (`mahsulot_id`) REFERENCES `im_mahsulotlar` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_ishlab_chiqarish_items`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_ishlab_chiqarish_items`;
CREATE TABLE IF NOT EXISTS `im_ishlab_chiqarish_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ishlab_id` int(11) NOT NULL,
  `tur` enum('kirish','chiqish') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'kirish=sarflandi, chiqish=hosil boldi',
  `mahsulot_id` int(11) NOT NULL,
  `partiya_item_id` int(11) DEFAULT NULL COMMENT 'FIFO: qaysi partiyadan',
  `soni` decimal(10,3) NOT NULL,
  `kelish_narxi` decimal(20,6) DEFAULT '0.000000',
  PRIMARY KEY (`id`),
  KEY `ishlab_id` (`ishlab_id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  KEY `nh_ishlab_chiqarish_items_ibfk_3` (`partiya_item_id`),
  CONSTRAINT `im_ishlab_chiqarish_items_ibfk_1` FOREIGN KEY (`ishlab_id`) REFERENCES `im_ishlab_chiqarish` (`id`) ON DELETE CASCADE,
  CONSTRAINT `im_ishlab_chiqarish_items_ibfk_2` FOREIGN KEY (`mahsulot_id`) REFERENCES `im_mahsulotlar` (`id`),
  CONSTRAINT `im_ishlab_chiqarish_items_ibfk_3` FOREIGN KEY (`partiya_item_id`) REFERENCES `im_partiya_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_istoriya`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_istoriya`;
CREATE TABLE IF NOT EXISTS `im_istoriya` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `jadval` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ob_id` int(11) DEFAULT NULL,
  `amal` enum('insert','update','delete','login','login_xato','logout','export','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `eski` mediumtext COLLATE utf8mb4_unicode_ci,
  `yangi` mediumtext COLLATE utf8mb4_unicode_ci,
  `izoh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xodim_id` int(11) DEFAULT NULL,
  `xodim_ism` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `ip_adres` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jadval_ob` (`jadval`,`ob_id`),
  KEY `xodim_id` (`xodim_id`),
  KEY `amal` (`amal`),
  KEY `sana` (`sana`),
  KEY `idx_amal_sana` (`amal`,`sana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_kassa`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_kassa`;
CREATE TABLE IF NOT EXISTS `im_kassa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `naqd_balans` decimal(15,2) DEFAULT '0.00',
  `karta_balans` decimal(15,2) DEFAULT '0.00',
  `bank_balans` decimal(15,2) DEFAULT '0.00',
  `usd_balans` decimal(10,4) DEFAULT '0.0000',
  `filial_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filial_id` (`filial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_kategoriyalar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_kategoriyalar`;
CREATE TABLE IF NOT EXISTS `im_kategoriyalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rang` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#6c757d',
  `status` tinyint(4) DEFAULT '1',
  `tavsif` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `oshpaz_kerak` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_mahsulotlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_mahsulotlar`;
CREATE TABLE IF NOT EXISTS `im_mahsulotlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barcode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomi` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategoriya_id` int(11) DEFAULT NULL,
  `postavshik_id` int(11) DEFAULT NULL,
  `birlik` enum('dona','kg','metr','litr','juft','komplekt','quti','porsiya') COLLATE utf8mb4_unicode_ci DEFAULT 'dona',
  `sotuv_qadami` decimal(6,3) NOT NULL DEFAULT '1.000',
  `tasvir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tavsif` text COLLATE utf8mb4_unicode_ci,
  `rasm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mahsulot rasmi (uploads/products/ ichida)',
  `barkod` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(4) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sotiladi` tinyint(1) NOT NULL DEFAULT '1',
  `faqat_ishlab_chiqarish` tinyint(1) NOT NULL DEFAULT '0',
  `oshpaz_kerak` tinyint(1) NOT NULL DEFAULT '0',
  `retsept_avto` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Retsepti bor, oshpaz tasdigi shart emas ? xomashyo SOTUV paytida avtomatik yechiladi (choy, kofe...)',
  `qozon_rejim` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Kunlik qozon rejimi ÔÇö xomashyo qozon ochilganda yechiladi',
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `kategoriya_id` (`kategoriya_id`),
  KEY `postavshik_id` (`postavshik_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_maosh_tarixi`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_maosh_tarixi`;
CREATE TABLE IF NOT EXISTS `im_maosh_tarixi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` int(11) NOT NULL,
  `summa` decimal(12,2) NOT NULL,
  `tolov_turi` enum('naqd','karta','bank','usd') COLLATE utf8mb4_unicode_ci DEFAULT 'naqd',
  `oy` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `beruvchi_id` int(11) DEFAULT NULL,
  `sana` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `worker_id` (`worker_id`),
  KEY `filial_id` (`filial_id`),
  KEY `oy` (`oy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_mijoz_toifalar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_mijoz_toifalar`;
CREATE TABLE IF NOT EXISTS `im_mijoz_toifalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chegirma` decimal(5,2) DEFAULT '0.00',
  `izoh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_mijoz_toifalari`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_mijoz_toifalari`;
CREATE TABLE IF NOT EXISTS `im_mijoz_toifalari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chegirma_foiz` decimal(5,2) DEFAULT '0.00',
  `rang` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#6c757d',
  `tavsif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(4) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_mijozlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_mijozlar`;
CREATE TABLE IF NOT EXISTS `im_mijozlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ism` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `toifa_id` int(11) DEFAULT '1',
  `jami_xarid` decimal(15,2) DEFAULT '0.00',
  `nasiya_qoldiq` decimal(15,2) DEFAULT '0.00',
  `manzil` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(4) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `toifa_id` (`toifa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_narx_ulgurji`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_narx_ulgurji`;
CREATE TABLE IF NOT EXISTS `im_narx_ulgurji` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mahsulot_id` int(11) NOT NULL,
  `min_soni` int(11) NOT NULL DEFAULT '5',
  `ulgurji_narxi` decimal(12,2) NOT NULL,
  `aktiv` tinyint(4) DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mahsulot_id` (`mahsulot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_narxlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_narxlar`;
CREATE TABLE IF NOT EXISTS `im_narxlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mahsulot_id` int(11) NOT NULL,
  `sotish_narxi` decimal(12,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mahsulot_id` (`mahsulot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_nasiya`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_nasiya`;
CREATE TABLE IF NOT EXISTS `im_nasiya` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sotuv_id` int(11) NOT NULL,
  `mijoz_id` int(11) NOT NULL,
  `qarz_summa` decimal(15,2) NOT NULL,
  `tolangan` decimal(15,2) DEFAULT '0.00',
  `qoldiq` decimal(15,2) DEFAULT NULL,
  `qaytarish_sana` date NOT NULL,
  `uzaytirilgan` int(11) DEFAULT '0',
  `holat` enum('aktiv','yopildi','muddati_otdi') COLLATE utf8mb4_unicode_ci DEFAULT 'aktiv',
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `kassir_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sotuv_id` (`sotuv_id`),
  KEY `mijoz_id` (`mijoz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_nasiya_tarix`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_nasiya_tarix`;
CREATE TABLE IF NOT EXISTS `im_nasiya_tarix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nasiya_id` int(11) NOT NULL,
  `amal` enum('muddat_uzaytirish','yopish','muddati_otdi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `eski_muddat` date DEFAULT NULL,
  `yangi_muddat` date DEFAULT NULL,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `admin_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nasiya_id` (`nasiya_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_nasiya_tolov`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_nasiya_tolov`;
CREATE TABLE IF NOT EXISTS `im_nasiya_tolov` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nasiya_id` int(11) NOT NULL,
  `summa` decimal(15,2) NOT NULL,
  `tolov_turi` enum('naqd','karta','bank','usd') COLLATE utf8mb4_unicode_ci NOT NULL,
  `usd_summa` decimal(10,4) DEFAULT '0.0000',
  `usd_kurs` decimal(12,2) DEFAULT '0.00',
  `kassir_id` int(11) DEFAULT NULL,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nasiya_id` (`nasiya_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_nasiya_tolovlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_nasiya_tolovlar`;
CREATE TABLE IF NOT EXISTS `im_nasiya_tolovlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nasiya_id` int(11) NOT NULL,
  `summa` decimal(15,2) NOT NULL,
  `tolov_turi` enum('naqd','karta','bank','usd') COLLATE utf8mb4_unicode_ci DEFAULT 'naqd',
  `izoh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xodim_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nasiya_id` (`nasiya_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_order_item_log`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_order_item_log`;
CREATE TABLE IF NOT EXISTS `im_order_item_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `xodim_id` int(11) NOT NULL,
  `filial_id` int(11) NOT NULL DEFAULT '1',
  `amal` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'qoshildi | oshirildi | kamaytir | ochirildi',
  `eski_soni` decimal(10,3) NOT NULL DEFAULT '0.000',
  `yangi_soni` decimal(10,3) NOT NULL DEFAULT '0.000',
  `nomi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `vaqt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_xodim` (`xodim_id`),
  KEY `idx_filial` (`filial_id`),
  KEY `idx_vaqt` (`vaqt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_osh_qozon`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_osh_qozon`;
CREATE TABLE IF NOT EXISTS `im_osh_qozon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filial_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL COMMENT 'Tayyor mahsulot (osh)',
  `sana` date NOT NULL COMMENT 'Qaysi kun',
  `moljal_porsiya` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT 'Oshpaz mo''ljali',
  `haqiqiy_porsiya` decimal(10,3) DEFAULT NULL,
  `xomashyo_summa` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Yechilgan xomashyo tannarxi',
  `yakuniy_tannarx` decimal(20,6) DEFAULT NULL,
  `holat` enum('ochiq','yopildi') NOT NULL DEFAULT 'ochiq',
  `qoldi_porsiya` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT 'Yopishda: qozonda qolgan (spisaniye)',
  `qoldi_isrofmi` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Yopishdagi qoldi_porsiya isrofmi (1) yoki keyingi kunga o''tdimi (0)',
  `ochgan_xodim_id` int(11) DEFAULT NULL,
  `ochildi_vaqt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `yopgan_xodim_id` int(11) DEFAULT NULL,
  `yopildi_vaqt` datetime DEFAULT NULL,
  `izoh` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_filial_sana` (`filial_id`,`sana`),
  KEY `idx_holat` (`holat`),
  KEY `idx_mahsulot` (`mahsulot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_osh_qozon_items`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_osh_qozon_items`;
CREATE TABLE IF NOT EXISTS `im_osh_qozon_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `qozon_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `soni` decimal(10,3) NOT NULL COMMENT 'Real o''lchov (kg/litr)',
  `birlik` varchar(16) DEFAULT NULL,
  `kelish_narxi` decimal(20,6) DEFAULT '0.000000',
  `summa` decimal(15,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_qozon` (`qozon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_partiya_items`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_partiya_items`;
CREATE TABLE IF NOT EXISTS `im_partiya_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `partiya_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `soni` decimal(12,3) NOT NULL DEFAULT '0.000',
  `kelish_narxi` decimal(12,2) NOT NULL,
  `sotish_narxi` decimal(12,2) DEFAULT '0.00',
  `sklad_qoldi` decimal(12,3) NOT NULL DEFAULT '0.000',
  `dukon_qoldi` decimal(12,3) NOT NULL DEFAULT '0.000',
  `kirim_narx` decimal(15,2) DEFAULT '0.00',
  `sotuv_narx` decimal(15,2) DEFAULT '0.00',
  `chegirma_narx` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `partiya_id` (`partiya_id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  KEY `idx_mah_qoldi` (`mahsulot_id`,`sklad_qoldi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_partiyalar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_partiyalar`;
CREATE TABLE IF NOT EXISTS `im_partiyalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postavshik_id` int(11) NOT NULL,
  `faktura_nomer` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sana` date NOT NULL,
  `jami_summa` decimal(15,2) DEFAULT '0.00',
  `tolov_turi` enum('naqd','karta','bank','usd','qarz') COLLATE utf8mb4_unicode_ci DEFAULT 'naqd',
  `usd_summa` decimal(10,4) DEFAULT '0.0000',
  `usd_kurs` decimal(12,2) DEFAULT '0.00',
  `tolandi` decimal(15,2) DEFAULT '0.00',
  `qarz_qoldi` decimal(15,2) DEFAULT '0.00',
  `holat` enum('ochiq','yopiq','bekor') COLLATE utf8mb4_unicode_ci DEFAULT 'ochiq',
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `xodim_id` int(11) DEFAULT NULL,
  `qabul_filial_id` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `postavshik_id` (`postavshik_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_postavshik_qarz`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_postavshik_qarz`;
CREATE TABLE IF NOT EXISTS `im_postavshik_qarz` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postavshik_id` int(11) NOT NULL,
  `partiya_id` int(11) DEFAULT NULL,
  `qarz_summa` decimal(15,2) NOT NULL,
  `tolandi` decimal(15,2) DEFAULT '0.00',
  `qoldiq` decimal(15,2) DEFAULT NULL,
  `muddat` date DEFAULT NULL,
  `status` enum('ochiq','yopildi','muddati_otdi') COLLATE utf8mb4_unicode_ci DEFAULT 'ochiq',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `postavshik_id` (`postavshik_id`),
  KEY `partiya_id` (`partiya_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_postavshik_vozvrat`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_postavshik_vozvrat`;
CREATE TABLE IF NOT EXISTS `im_postavshik_vozvrat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postavshik_id` int(11) NOT NULL,
  `partiya_item_id` int(11) DEFAULT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `soni` int(11) NOT NULL,
  `sabab` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amal` enum('qarzdan_chegiladi','pul_qaytarildi') COLLATE utf8mb4_unicode_ci DEFAULT 'qarzdan_chegiladi',
  `summa` decimal(12,2) DEFAULT '0.00',
  `admin_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `postavshik_id` (`postavshik_id`),
  KEY `mahsulot_id` (`mahsulot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_postavshiklar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_postavshiklar`;
CREATE TABLE IF NOT EXISTS `im_postavshiklar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefon` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shahar` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontakt_ism` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontakt_telefon` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(4) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_qarz_tarix`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_qarz_tarix`;
CREATE TABLE IF NOT EXISTS `im_qarz_tarix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `qarz_id` int(11) NOT NULL,
  `amal` enum('muddat_uzaytirish','yopish') COLLATE utf8mb4_unicode_ci NOT NULL,
  `eski_muddat` date DEFAULT NULL,
  `yangi_muddat` date DEFAULT NULL,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `admin_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `qarz_id` (`qarz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_qarz_tolovlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_qarz_tolovlar`;
CREATE TABLE IF NOT EXISTS `im_qarz_tolovlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `qarz_id` int(11) NOT NULL,
  `summa` decimal(15,2) NOT NULL,
  `tolov_turi` enum('naqd','karta','bank','usd') COLLATE utf8mb4_unicode_ci DEFAULT 'naqd',
  `usd_summa` decimal(10,4) DEFAULT '0.0000',
  `usd_kurs` decimal(12,2) DEFAULT '0.00',
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `admin_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `qarz_id` (`qarz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_retsept_items`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_retsept_items`;
CREATE TABLE IF NOT EXISTS `im_retsept_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `retsept_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `soni` decimal(10,3) NOT NULL COMMENT '1 marta uchun kerakli miqdor',
  `birlik` enum('dona','kg','litr','metr') COLLATE utf8mb4_unicode_ci DEFAULT 'dona',
  `izoh` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `retsept_id` (`retsept_id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  CONSTRAINT `im_retsept_items_ibfk_1` FOREIGN KEY (`retsept_id`) REFERENCES `im_retseptlar` (`id`) ON DELETE CASCADE,
  CONSTRAINT `im_retsept_items_ibfk_2` FOREIGN KEY (`mahsulot_id`) REFERENCES `im_mahsulotlar` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_retseptlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_retseptlar`;
CREATE TABLE IF NOT EXISTS `im_retseptlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tur` enum('ishlab_chiqarish','maydalash') COLLATE utf8mb4_unicode_ci NOT NULL,
  `mahsulot_id` int(11) NOT NULL COMMENT 'ishlab_chiqarish: tayyor mahsulot; maydalash: asosiy mahsulot',
  `chiqish_soni` decimal(10,3) NOT NULL DEFAULT '1.000' COMMENT '1 marta bajarganda chiqadigan miqdor (faqat ishlab_chiqarish)',
  `birlik` enum('dona','kg','litr','metr') COLLATE utf8mb4_unicode_ci DEFAULT 'dona',
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(4) DEFAULT '1',
  `xodim_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  CONSTRAINT `im_retseptlar_ibfk_1` FOREIGN KEY (`mahsulot_id`) REFERENCES `im_mahsulotlar` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_set_items`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_set_items`;
CREATE TABLE IF NOT EXISTS `im_set_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `set_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `soni` decimal(10,3) NOT NULL DEFAULT '1.000',
  `tartib` int(11) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `set_id` (`set_id`),
  KEY `mahsulot_id` (`mahsulot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_setlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_setlar`;
CREATE TABLE IF NOT EXISTS `im_setlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tavsif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `narxi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `rang` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '#e2b96f',
  `filial_id` int(11) NOT NULL,
  `aktiv` tinyint(1) DEFAULT '1',
  `tartib` int(11) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `filial_id` (`filial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_sklad_send`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_sklad_send`;
CREATE TABLE IF NOT EXISTS `im_sklad_send` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mahsulot_id` int(11) NOT NULL,
  `partiya_item_id` int(11) NOT NULL,
  `soni` decimal(12,3) NOT NULL COMMENT 'Kasr miqdorlar (kg, litr) uchun',
  `xodim_id` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT '0',
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  KEY `partiya_item_id` (`partiya_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_smena`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_smena`;
CREATE TABLE IF NOT EXISTS `im_smena` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sana` date NOT NULL,
  `kassir_id` int(11) NOT NULL,
  `ochish_naqd` decimal(15,2) DEFAULT '0.00',
  `yopish_naqd` decimal(15,2) DEFAULT NULL,
  `tannarx` decimal(15,2) DEFAULT NULL,
  `isrof` decimal(15,2) DEFAULT NULL,
  `sof_foyda` decimal(15,2) DEFAULT NULL,
  `holat` enum('ochiq','yopiq') COLLATE utf8mb4_unicode_ci DEFAULT 'ochiq',
  `ochildi` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `yopildi` timestamp NULL DEFAULT NULL,
  `filial_id` int(11) DEFAULT '1',
  `xodim_id` int(11) DEFAULT NULL,
  `naqd_kirim` decimal(15,2) DEFAULT '0.00',
  `karta_kirim` decimal(15,2) DEFAULT '0.00',
  `boshlanish_naqd` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `kassir_id` (`kassir_id`),
  KEY `idx_filial_holat` (`filial_id`,`holat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_sotuv_items`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_sotuv_items`;
CREATE TABLE IF NOT EXISTS `im_sotuv_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sotuv_id` int(11) NOT NULL,
  `set_id` int(11) DEFAULT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `partiya_item_id` int(11) DEFAULT NULL,
  `soni` decimal(10,3) NOT NULL DEFAULT '0.000',
  `sotish_narxi` decimal(12,2) NOT NULL,
  `chegirma_foiz` decimal(5,2) DEFAULT '0.00',
  `chegirma_narxi` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tannarx` decimal(20,6) DEFAULT '0.000000',
  `fifo_return_mode` enum('stock','waste') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qozon_id` int(11) DEFAULT NULL,
  `narx` decimal(15,2) DEFAULT '0.00',
  `chegirma` decimal(15,2) DEFAULT '0.00',
  `olib_ketish_soni` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT 'Shu qatordan nechtasi saboyga olindi',
  PRIMARY KEY (`id`),
  KEY `sotuv_id` (`sotuv_id`),
  KEY `mahsulot_id` (`mahsulot_id`),
  KEY `partiya_item_id` (`partiya_item_id`),
  KEY `qozon_id` (`qozon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_sotuvchi_order`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_sotuvchi_order`;
CREATE TABLE IF NOT EXISTS `im_sotuvchi_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sotuvchi_id` int(11) NOT NULL,
  `filial_id` int(11) NOT NULL,
  `mijoz_ism` varchar(120) DEFAULT '',
  `mijoz_id` int(11) DEFAULT NULL,
  `stol_id` int(11) DEFAULT NULL,
  `olib_ketish` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(50) DEFAULT 'kutilmoqda',
  `izoh` text,
  `client_token` varchar(40) DEFAULT NULL COMMENT 'Brauzer bergan bir martalik kalit ÔÇö takroriy yuborishdan himoya',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_client_token` (`client_token`),
  KEY `idx_status_filial` (`status`,`filial_id`),
  KEY `idx_sotuvchi` (`sotuvchi_id`),
  KEY `idx_stol` (`stol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_sotuvchi_order_item`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_sotuvchi_order_item`;
CREATE TABLE IF NOT EXISTS `im_sotuvchi_order_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `set_id` int(11) DEFAULT NULL COMMENT 'im_setlar.id ÔÇö NULL bo''lsa alohida (├á la carte)',
  `soni` decimal(10,3) NOT NULL DEFAULT '1.000',
  `narx` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tayyorlandi_soni` decimal(10,3) NOT NULL DEFAULT '0.000',
  `olib_ketish_soni` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT 'Shu qatordan nechtasi saboyga olinadi (xizmat haqisiz)',
  `rezerv_soni` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT 'Shu qator uchun filial qoldigidan band qilingan miqdor',
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_set` (`set_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Jadval: `im_sotuvchilar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_sotuvchilar`;
CREATE TABLE IF NOT EXISTS `im_sotuvchilar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ism` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` enum('sotuvchi','yordamchi') COLLATE utf8mb4_unicode_ci DEFAULT 'sotuvchi',
  `oylik` decimal(12,2) DEFAULT '0.00',
  `status` tinyint(4) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_sotuvlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_sotuvlar`;
CREATE TABLE IF NOT EXISTS `im_sotuvlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chek_nomer` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `smena_id` int(11) DEFAULT NULL,
  `mijoz_id` int(11) DEFAULT NULL,
  `kassir_id` int(11) DEFAULT NULL,
  `sotuvchi_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL COMMENT 'im_sotuvchi_order.id ? qaysi stol buyurtmasidan',
  `stol_id` int(11) DEFAULT NULL COMMENT 'im_stollar.id ? stol savdosi bo lsa',
  `manba` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Stol 5 / Dastavka / Olib ketish / To gridan-to gri',
  `naqd_summa` decimal(15,2) DEFAULT '0.00',
  `karta_summa` decimal(15,2) DEFAULT '0.00',
  `bank_summa` decimal(15,2) DEFAULT '0.00',
  `usd_summa` decimal(10,4) DEFAULT '0.0000',
  `usd_kurs` decimal(12,2) DEFAULT '0.00',
  `usd_som_ekviv` decimal(15,2) DEFAULT '0.00',
  `nasiya_summa` decimal(15,2) DEFAULT '0.00',
  `jami_summa` decimal(15,2) NOT NULL DEFAULT '0.00',
  `chegirma_foiz` decimal(5,2) DEFAULT '0.00',
  `chegirma_summa` decimal(15,2) DEFAULT '0.00',
  `chegirma` decimal(15,2) DEFAULT '0.00',
  `xizmat_foiz` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Xizmat haqi foizi (otsluga)',
  `xizmat_summa` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT 'Xizmat haqi summasi',
  `tolov_summa` decimal(15,2) NOT NULL DEFAULT '0.00',
  `naqd_berildi` decimal(15,2) DEFAULT '0.00',
  `usd_qaytim_som` decimal(15,2) NOT NULL DEFAULT '0.00',
  `filial_id` int(11) DEFAULT '1',
  `xodim_id` int(11) DEFAULT NULL,
  `holat` enum('aktiv','qaytarilgan','bekor') COLLATE utf8mb4_unicode_ci DEFAULT 'aktiv',
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chek_nomer` (`chek_nomer`),
  KEY `smena_id` (`smena_id`),
  KEY `mijoz_id` (`mijoz_id`),
  KEY `kassir_id` (`kassir_id`),
  KEY `filial_id` (`filial_id`),
  KEY `sana` (`sana`),
  KEY `idx_sana_filial` (`sana`,`filial_id`),
  KEY `idx_filial_sana` (`filial_id`,`sana`),
  KEY `idx_smena_sana` (`smena_id`,`sana`),
  KEY `idx_stol` (`stol_id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_sotuvchi` (`sotuvchi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_sozlamalar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_sozlamalar`;
CREATE TABLE IF NOT EXISTS `im_sozlamalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kalit` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qiymat` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kalit` (`kalit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_stollar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_stollar`;
CREATE TABLE IF NOT EXISTS `im_stollar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filial_id` int(11) NOT NULL,
  `zona_id` int(11) DEFAULT NULL COMMENT 'im_zonalar.id ÔÇö NULL bo''lsa Boshqa',
  `nomi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tartib` int(11) DEFAULT '1',
  `status` tinyint(4) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filial_zona_nomi` (`filial_id`,`zona_id`,`nomi`),
  KEY `idx_filial_status` (`filial_id`,`status`),
  KEY `idx_zona` (`zona_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_valyuta`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_valyuta`;
CREATE TABLE IF NOT EXISTS `im_valyuta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kod` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `nomi` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Dollar',
  `kurs` decimal(12,2) NOT NULL DEFAULT '12700.00',
  `sana` date DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_valyuta_kurs`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_valyuta_kurs`;
CREATE TABLE IF NOT EXISTS `im_valyuta_kurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sana` date NOT NULL,
  `usd_kurs` decimal(12,2) NOT NULL,
  `manba` enum('cbu_api','qolda') COLLATE utf8mb4_unicode_ci DEFAULT 'qolda',
  `tasdiqlandi` tinyint(4) DEFAULT '0',
  `admin_id` int(11) DEFAULT NULL,
  `qo_shgan_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sana` (`sana`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_voucher`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_voucher`;
CREATE TABLE IF NOT EXISTS `im_voucher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kod` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tur` enum('foiz','summa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `qiymat` decimal(10,2) NOT NULL,
  `min_summa` decimal(15,2) DEFAULT '0.00',
  `max_chegirma` decimal(15,2) DEFAULT NULL,
  `umumiy_soni` int(11) DEFAULT '1',
  `ishlatilgan` int(11) DEFAULT '0',
  `boshlanish` date DEFAULT NULL,
  `tugash` date DEFAULT NULL,
  `mijoz_id` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `status` enum('aktiv','nofaol','tugagan') COLLATE utf8mb4_unicode_ci DEFAULT 'aktiv',
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kod` (`kod`),
  KEY `mijoz_id` (`mijoz_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_voucher_ishlatish`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_voucher_ishlatish`;
CREATE TABLE IF NOT EXISTS `im_voucher_ishlatish` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `sotuv_id` int(11) DEFAULT NULL,
  `mijoz_id` int(11) DEFAULT NULL,
  `chegirma_summa` decimal(15,2) NOT NULL,
  `asl_summa` decimal(15,2) DEFAULT NULL,
  `tolov_summa` decimal(15,2) DEFAULT NULL,
  `kassir_id` int(11) DEFAULT NULL,
  `filial_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `voucher_id` (`voucher_id`),
  KEY `sotuv_id` (`sotuv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_vozvratlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_vozvratlar`;
CREATE TABLE IF NOT EXISTS `im_vozvratlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sotuv_id` int(11) DEFAULT NULL,
  `mahsulot_id` int(11) NOT NULL,
  `partiya_item_id` int(11) DEFAULT NULL,
  `soni` decimal(18,3) NOT NULL DEFAULT '0.000',
  `sabab` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qaytarildi_tur` enum('naqd','karta','bank','usd') COLLATE utf8mb4_unicode_ci DEFAULT 'naqd',
  `qaytarish_summa` decimal(12,2) NOT NULL,
  `kassir_id` int(11) DEFAULT NULL,
  `sana` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sotuv_item_id` int(11) DEFAULT NULL,
  `tannarx` decimal(20,6) DEFAULT '0.000000' COMMENT 'JAMI qaytarilgan tannarx (birlik EMAS): birlik = tannarx / soni',
  PRIMARY KEY (`id`),
  KEY `sotuv_id` (`sotuv_id`),
  KEY `mahsulot_id` (`mahsulot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_workers`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_workers`;
CREATE TABLE IF NOT EXISTS `im_workers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ism` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefon` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lavozim` enum('kassir','sotuvchi','sklad','haydovchi','tozalovchi','boshqa') COLLATE utf8mb4_unicode_ci DEFAULT 'boshqa',
  `oylik_stavka` decimal(12,2) DEFAULT '0.00',
  `filial_id` int(11) DEFAULT NULL,
  `xodim_id` int(11) DEFAULT NULL,
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `status` tinyint(4) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `filial_id` (`filial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_xodim_log`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_xodim_log`;
CREATE TABLE IF NOT EXISTS `im_xodim_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `xodim_id` int(11) NOT NULL DEFAULT '0',
  `filial_id` int(11) NOT NULL DEFAULT '1',
  `amal` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `order_id` int(11) DEFAULT NULL,
  `mijoz_ism` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summa` decimal(15,2) NOT NULL DEFAULT '0.00',
  `izoh` text COLLATE utf8mb4_unicode_ci,
  `vaqt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_xodim` (`xodim_id`),
  KEY `idx_filial` (`filial_id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_vaqt` (`vaqt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_xodimlar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_xodimlar`;
CREATE TABLE IF NOT EXISTS `im_xodimlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ism` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parol` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'kassir',
  `telefon` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oylik` decimal(12,2) DEFAULT '0.00',
  `filial_id` int(11) DEFAULT '1',
  `status` tinyint(4) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jadval: `im_zonalar`
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `im_zonalar`;
CREATE TABLE IF NOT EXISTS `im_zonalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filial_id` int(11) NOT NULL,
  `nomi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rang` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#64748b' COMMENT 'Hex ÔÇö tab va stol kartasi ramkasi rangi',
  `ikonka` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT 'bi-grid-3x3-gap-fill' COMMENT 'Bootstrap-icons klassi',
  `tartib` int(11) DEFAULT '1',
  `status` tinyint(4) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `filial_nomi` (`filial_id`,`nomi`),
  KEY `idx_filial_status` (`filial_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Boshlang'ich ma'lumotlar: im_filiallar
-- ------------------------------------------------------------
INSERT INTO `im_filiallar` (`id`, `nomi`, `kod`, `manzil`, `telefon`, `rang`, `tartib`, `status`) VALUES
(1, 'Asosiy filial', 'NH1', 'Samarqand sh., Registon', '+998 71 123-45-67', '#e2b96f', 1, 1);

-- ------------------------------------------------------------
-- Boshlang'ich ma'lumotlar: im_kassa
-- ------------------------------------------------------------
INSERT INTO `im_kassa` (`id`, `naqd_balans`, `karta_balans`, `bank_balans`, `usd_balans`, `filial_id`) VALUES
(1, 0.00, 0.00, 0.00, 0.0000, 0),
(2, 0.00, 0.00, 0.00, 0.0000, 1);

-- ------------------------------------------------------------
-- Boshlang'ich ma'lumotlar: im_fifo_meta
-- ------------------------------------------------------------
INSERT INTO `im_fifo_meta` (`name`, `value`) VALUES
('opening_complete', '1'),
('opening_history', 'Initial deployment schema');

-- ------------------------------------------------------------
-- Boshlang'ich ma'lumotlar: im_zonalar
-- ------------------------------------------------------------
INSERT INTO `im_zonalar` (`id`, `filial_id`, `nomi`, `rang`, `ikonka`, `tartib`, `status`) VALUES
(1, 1, 'VIP', '#14b8a6', 'bi-grid-3x3-gap-fill', 1, 1),
(2, 1, 'ZAL', '#0f766e', 'bi-grid-3x3-gap-fill', 2, 1);

-- ------------------------------------------------------------
-- Boshlang'ich ma'lumotlar: im_stollar
-- ------------------------------------------------------------
INSERT INTO `im_stollar` (`id`, `filial_id`, `zona_id`, `nomi`, `tartib`, `status`) VALUES
(1, 1, 2, 'Stol 1', 1, 1),
(2, 1, 2, 'Stol 2', 2, 1),
(3, 1, 1, 'VIP kabina', 3, 1),
(4, 1, 2, 'Stol 3', 4, 1);

-- ------------------------------------------------------------
-- Boshlang'ich ma'lumotlar: im_sozlamalar (API kalitlar bo'sh)
-- ------------------------------------------------------------
INSERT INTO `im_sozlamalar` (`kalit`, `qiymat`) VALUES
('dukon_nomi', 'IMezon'),
('dukon_manzil', 'Samarqand sh., Registon'),
('dukon_telefon', '+998 71 123-45-67'),
('dukon_url', 'IMezon.uz'),
('chek_izoh', 'Tovar sifatiga kafolat beriladi'),
('chek_rahmat', 'Xaridingiz uchun rahmat!'),
('kassir_max_chegirma', '10'),
('nasiya_kun', '30'),
('ulg_min_soni', '5'),
('smena_avto_yopish', '0'),
('xizmat_foiz', '10'),
('tts_yoniq', '1'),
('tts_provider', 'mohir'),
('tts_key', ''),
('tts_ovoz', 'jasur'),
('tts_region', ''),
('tts_endpoint', ''),
('tts_auth', 'raw'),
('tts_max_item', '4'),
('ai_yoniq', '1'),
('ai_provider', 'on'),
('ai_key', ''),
('ai_model', 'deepseek/deepseek-v4-flash-0731'),
('ai_endpoint', ''),
('ai_effort', 'medium'),
('ai_max_tokens', '16000'),
('ai_qadam', '8'),
('ai_kunlik', '1'),
('ai_tarix', '10'),
('ai_key_claude', ''),
('ai_key_or', ''),
('dukon_valyuta', 'so\'m'),
('chek_telegram', ''),
('chek_instagram', '');

-- ------------------------------------------------------------
-- Boshlang'ich ma'lumotlar: im_xodimlar
-- ------------------------------------------------------------
INSERT INTO `im_xodimlar` (`ism`, `login`, `parol`, `rol`, `telefon`, `oylik`, `filial_id`, `status`) VALUES
('Admin', 'admin1', '$2y$10$/.rshi9IqDnpjQcJy.WsVO4OWYQAfg3bsF4ggEqiF4K8VVP7Wa.EC', 'admin', '', 0, 1, 1),
('Do\'kon kassiri', 'dukon1', '$2y$10$9t/TTjk7QyoF.QZ34cu8m.bsy672esFU7p41bH4uyXMsOGGa8qkRa', 'kassir', '', 0, 1, 1),
('Sotuvchi 1', 'sotuvchi1', '$2y$10$Dm0un5TUvyScXj8/Z4seQOHvt/TG2sDCojz5IrhC2MZqZwE5yJeSG', 'sotuvchi', '', 0, 1, 1),
('Sklad 1', 'sklad1', '$2y$10$JKmRcJnosdPwLOm5j2973eM4ZPLlq/KYYAORZjPmMNLfJcQafArxG', 'sklad', '', 0, 1, 1),
('Oshpaz 1', 'oshpaz1', '$2y$10$DMGw.I6GZvjjzUbmKzrc3.CpHxDxk/dSJdV5Tif1qlI4oXEEDI1ly', 'oshpaz', '', 0, 1, 1),
('Dilnoza Karimova', 'kassa', '$2y$10$lYXeLzu7jR/5U46WLIY9D.sTEb.zGMH/K48xePL/iBrGoqw8MsX/.', 'bosh_kassir', '+998 90 111-22-33', 0, 1, 1);

-- ------------------------------------------------------------
-- Boshlang'ich ma'lumotlar: im_valyuta_kurs
-- ------------------------------------------------------------
INSERT INTO `im_valyuta_kurs` (`sana`, `usd_kurs`, `manba`, `tasdiqlandi`) VALUES
(CURDATE(), 12000.00, 'qolda', 1);

SET foreign_key_checks = 1;
