<?php
// ============================================================
//  IMezon — Migratsiya: im_xodim_log jadvali yaratish
//  Bir marta ishga tushirish kifoya
// ============================================================
require_once __DIR__ . '/../ximoya.php';
require_once __DIR__ . '/../config.php';
im_rol_check(['admin']);

$sqls = [
    "CREATE TABLE IF NOT EXISTS `im_xodim_log` (
        `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `xodim_id`    INT NOT NULL,
        `filial_id`   INT NOT NULL DEFAULT 0,
        `amal`        VARCHAR(60)  NOT NULL COMMENT 'hold|create|cancel|oshpaz_qabul|login|logout',
        `order_id`    INT NULL     COMMENT 'Bog''liq buyurtma ID',
        `mijoz_ism`   VARCHAR(120) NULL,
        `summa`       DECIMAL(14,2) NOT NULL DEFAULT 0,
        `izoh`        VARCHAR(255) NULL,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_xodim`  (`xodim_id`, `created_at`),
        INDEX `idx_filial` (`filial_id`, `created_at`),
        INDEX `idx_order`  (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$errors = [];
foreach ($sqls as $sql) {
    if (!mysqli_query($link, $sql)) {
        $errors[] = mysqli_error($link);
    }
}

if ($errors) {
    echo '<pre style="color:red">Xato:<br>' . implode("\n", $errors) . '</pre>';
} else {
    echo '<div style="font-family:monospace;color:green;padding:20px">
        ✅ im_xodim_log jadvali muvaffaqiyatli yaratildi!<br>
        <a href="../admin/xodim-tarixi.php">→ Tarixni ko\'rish</a>
    </div>';
}
