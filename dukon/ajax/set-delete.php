<?php
// ============================================================
//  IMezon — Dukon: Set o'chirish (soft delete)
//  POST: { id }
// ============================================================
require_once __DIR__ . '/../../ximoya.php';
require_once __DIR__ . '/../../config.php';
im_rol_check(['kassir', 'admin']);
$db = new Cyber();

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true) ?: $_POST;
$id    = (int)($input['id'] ?? 0);
$filial = (int)$im_filial_id;

if (!$id) im_json('error', 'ID kerak');

// Kassir faqat o'z filialidagi setni o'chira oladi
$where = $im_rol === 'admin' ? "id=$id" : "id=$id AND filial_id=$filial";
$set = $db->row("SELECT id FROM im_setlar WHERE $where");
if (!$set) im_json('error', 'Set topilmadi yoki ruxsat yo\'q');

$db->q("UPDATE im_setlar SET aktiv=0 WHERE id=$id");
im_json('ok', 'Set o\'chirildi');
