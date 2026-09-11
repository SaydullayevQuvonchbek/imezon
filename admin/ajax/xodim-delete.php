<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();

$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', 'ID kerak');
if ($id === $im_user_id) im_json('error', 'O\'zingizni o\'chira olmaysiz');

$x = $db->row("SELECT ism FROM im_xodimlar WHERE id=$id");
if (!$x) im_json('error', 'Topilmadi');

$db->q("UPDATE im_xodimlar SET status=0 WHERE id=$id");
im_json('ok', "«{$x['ism']}» xodim bloklandi");
