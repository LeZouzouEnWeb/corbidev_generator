<?php
// download.php — sécurisé (répertoire temp + extension .zip)
if (!defined('API_GATEWAY')) { define('API_GATEWAY', true); }
$param = $_GET['file'] ?? '';
if ($param === '') { http_response_code(400); exit("❌ Fichier non spécifié"); }
$real = realpath($param);
$tmp  = realpath(sys_get_temp_dir());
if ($real === false || strpos($real, $tmp) !== 0 || pathinfo($real, PATHINFO_EXTENSION) !== 'zip') {
    http_response_code(404); exit("❌ Fichier introuvable");
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($real) . '"');
header('Content-Length: ' . filesize($real));
readfile($real);
@unlink($real);
exit;
