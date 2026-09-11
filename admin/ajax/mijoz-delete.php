<?php
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['admin']);
$db = new Cyber();
$id = (int)($_POST['id'] ?? 0);
if (!$id) im_json('error', 'ID kerak');
$m = $db->row("SELECT ism, nasiya_qoldiq FROM im_mijozlar WHERE id=$id");
if (!$m) im_json('error', 'Topilmadi');
if ((float)$m['nasiya_qoldiq'] > 0) im_json('error', "Mijozda nasiya qoldi bor (".im_money($m['nasiya_qoldiq'])." so'm). Avval to'lating.");
$db->q("UPDATE im_mijozlar SET status=0 WHERE id=$id");
im_json('ok', "«{$m['ism']}» o'chirildi");
