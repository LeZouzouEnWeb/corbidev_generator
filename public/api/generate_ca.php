<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$files = [];

$script = basename(__FILE__);
if ($script === 'generate_ca.php') {
  $files[] = ['name'=>'ca.key','content'=>"-----BEGIN PRIVATE KEY-----\n...DEMO...\n-----END PRIVATE KEY-----"];
  $files[] = ['name'=>'ca.crt','content'=>"-----BEGIN CERTIFICATE-----\n...DEMO...\n-----END CERTIFICATE-----"];
} elseif ($script === 'generate_server.php') {
  $cn = $input['cnServer'] ?? 'server.local';
  $files[] = ['name'=> $cn.'.key','content'=>"-----BEGIN PRIVATE KEY-----\n...DEMO...\n-----END PRIVATE KEY-----"];
  $files[] = ['name'=> $cn.'.crt','content'=>"-----BEGIN CERTIFICATE-----\n...DEMO...\n-----END CERTIFICATE-----"];
} else {
  $cn = $input['cnClient'] ?? 'client.local';
  $files[] = ['name'=> $cn.'.key','content'=>"-----BEGIN PRIVATE KEY-----\n...DEMO...\n-----END PRIVATE KEY-----"];
  $files[] = ['name'=> $cn.'.crt','content'=>"-----BEGIN CERTIFICATE-----\n...DEMO...\n-----END CERTIFICATE-----"];
}

echo json_encode(['ok'=>true,'files'=>$files], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
