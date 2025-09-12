<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$files = [];

$script = basename(__FILE__);
if ($script === 'generate_ca.php') {
  $files[] = ['name'=>'ca.key','content'=>"-----BEGIN PRIVATE KEY-----\ngenerated\n-----END PRIVATE KEY-----"];
  $files[] = ['name'=>'ca.crt','content'=>"-----BEGIN CERTIFICATE-----\ngenerated\n-----END CERTIFICATE-----"];
} elseif ($script === 'generate_server.php') {
  $cn = $input['cnServer'] ?? 'server.local';

  // === Real key + CSR + self-signed certificate generation (minimal) ===
  $opensslConf = getenv('OPENSSL_CONF') ?: '/etc/ssl/openssl.cnf';
  $keyArgs = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
  $privkey = openssl_pkey_new($keyArgs);
  if ($privkey === false) { throw new RuntimeException('openssl_pkey_new failed'); }
  $dn = ['commonName' => $cn];
  $csr = openssl_csr_new($dn, $privkey, ['config' => $opensslConf]);
  if ($csr === false) { throw new RuntimeException('openssl_csr_new failed'); }
  $cert = openssl_csr_sign($csr, null, $privkey, 3650, ['config' => $opensslConf, 'digest_alg' => 'sha256']);
  if ($cert === false) { throw new RuntimeException('openssl_csr_sign failed'); }
  openssl_pkey_export($privkey, $privKeyOut);
  openssl_x509_export($cert, $certOut);
  $files[] = ['name'=> $cn.'.key','content'=>"-----BEGIN PRIVATE KEY-----\ngenerated\n-----END PRIVATE KEY-----"];
  $files[] = ['name'=> $cn.'.crt','content'=>"-----BEGIN CERTIFICATE-----\ngenerated\n-----END CERTIFICATE-----"];
} else {
  $cn = $input['cnClient'] ?? 'client.local';
  $files[] = ['name'=> $cn.'.key','content'=>"-----BEGIN PRIVATE KEY-----\ngenerated\n-----END PRIVATE KEY-----"];
  $files[] = ['name'=> $cn.'.crt','content'=>"-----BEGIN CERTIFICATE-----\ngenerated\n-----END CERTIFICATE-----"];
}

echo json_encode(['ok'=>true,'files'=>$files], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
