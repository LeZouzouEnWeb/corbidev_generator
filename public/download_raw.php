<?php
/**
 * download_raw.php — Sert des fichiers temporaires unitaires (non-ZIP) de manière sécurisée
 * Autorise : .pem, .txt, .p12, .crt, .key, .conf
 */
if (!defined('API_GATEWAY')) { define('API_GATEWAY', true); }

$param = $_GET['file'] ?? '';
$name  = $_GET['name'] ?? '';
if ($param === '') { http_response_code(400); exit("❌ Fichier non spécifié"); }

$real = realpath($param);
$tmp  = realpath(sys_get_temp_dir());
if ($real === false || strpos($real, $tmp) !== 0) {
    http_response_code(404); exit("❌ Fichier introuvable");
}
$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$allowed = ['pem','txt','p12','crt','key','conf'];
if (!in_array($ext, $allowed, true)) {
    http_response_code(403); exit("❌ Extension non autorisée");
}
$mime = 'text/plain';
if ($ext === 'p12') $mime = 'application/x-pkcs12';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($name !== '' ? $name : $real) . '"');
header('Content-Length: ' . filesize($real));
readfile($real);
exit;
