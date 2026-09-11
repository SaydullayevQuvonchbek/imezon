<?php
require_once __DIR__ . '/config.php';
session_name('IMEZON_SESS');
if (session_status() === PHP_SESSION_NONE) session_start();
session_unset();
session_destroy();
header('Location: ' . im_BASE . 'login.php');
exit;
