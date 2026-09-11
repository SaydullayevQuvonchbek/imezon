<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', 'ID kerak');

// Bog'liq mijozlar bormi?
$count = (int)$db->val("SELECT COUNT(*) FROM im_mijozlar WHERE toifa_id=$id AND status=1");
if ($count > 0) im_json('error', "Bu toifaga $count ta aktiv mijoz bog'liq. Avval ularni o'zgartiring.");

$nomi = $db->val("SELECT nomi FROM im_mijoz_toifalari WHERE id=$id");
if (!$nomi) im_json('error', 'Toifa topilmadi');

$db->q("DELETE FROM im_mijoz_toifalari WHERE id=$id");
im_json('ok', '"' . $nomi . '" toifasi o\'chirildi');
