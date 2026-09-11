<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir']);
$db = new Cyber();

$q = trim($_GET['q'] ?? '');
if (!$q) im_json('error', 'Qidiruv kerak');
$qs = mysqli_real_escape_string($link, $q);

$rows = $db->rows(
    "SELECT m.id, m.ism, m.telefon,
            t.nomi AS toifa_nomi, t.chegirma_foiz,
            m.nasiya_qoldiq, m.jami_xarid
     FROM im_mijozlar m
     LEFT JOIN im_mijoz_toifalari t ON t.id=m.toifa_id
     WHERE m.status=1 AND (m.ism LIKE '%$qs%' OR m.telefon LIKE '%$qs%')
     ORDER BY m.ism ASC LIMIT 15"
);

im_json('ok', '', ['list' => $rows]);
