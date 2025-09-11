<?php
/**
 * cert_generator.php - pro-plus5
 * - Algorithmes indépendants par type (CA/Serveur/Client)
 * - Validités spécifiques par type
 * - SAN serveur
 * - PKCS#12 binaire + Base64
 * - Empreintes / validités / Apache & Nginx
 * - Jeton configurable: longueur + caractères spéciaux
 * - Fichiers temporaires par élément + ZIP global
 */
if (!defined('API_GATEWAY')) { define('API_GATEWAY', true); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Méthode non autorisée');
}

// Robust JSON-only output: capture any warnings/notices and convert to exceptions
// Prevent HTML error output from corrupting JSON
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
if (!headers_sent()) { header_remove('X-Powered-By'); }
ob_start();
set_error_handler(function($severity, $message, $file = null, $line = null) {
    // Respect error_reporting; convert any reported error to exception
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, (string)$file, (int)$line);
});

function generateKey(string $alg, int $rsaBits = 2048, string $ecCurve = 'prime256v1'): OpenSSLAsymmetricKey {
    if ($alg === 'EC') {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $ecCurve]);
        if ($key === false) { throw new RuntimeException('Échec génération clé EC'); }
        return $key;
    }
    $key = openssl_pkey_new(['private_key_bits' => $rsaBits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($key === false) { throw new RuntimeException('Échec génération clé RSA'); }
    return $key;
}
function exportPrivateKey(OpenSSLAsymmetricKey $key): string {
    if (!openssl_pkey_export($key, $out)) { throw new RuntimeException('Échec export clé privée'); }
    return $out;
}
function exportPublicKey(OpenSSLAsymmetricKey $privateKey): string {
    $details = openssl_pkey_get_details($privateKey);
    if ($details === false || empty($details['key'])) { throw new RuntimeException('Échec export clé publique'); }
    return $details['key'];
}
function createOpenSslConfigWithSAN(array $sans): string {
    $sanLine = 'subjectAltName=' . implode(',', $sans);
    $content = "[ req ]
distinguished_name = req_distinguished_name
prompt = no
[ req_distinguished_name ]
CN = temp
[ v3_req ]
{$sanLine}
";
    $path = tempnam(sys_get_temp_dir(), 'ossl_');
    file_put_contents($path, $content);
    return $path;
}
function generateCertificate(array $dn, OpenSSLAsymmetricKey $privateKey, $caCert = null, ?OpenSSLAsymmetricKey $caKey = null, int $days = 365, array $sans = []): string {
    $args = ['digest_alg' => 'sha256'];
    $temp = null;
    if (!empty($sans)) {
        $temp = createOpenSslConfigWithSAN($sans);
        $args['config'] = $temp;
        $args['req_extensions'] = 'v3_req';
        $args['x509_extensions'] = 'v3_req';
    }
    $csr = openssl_csr_new($dn, $privateKey, $args);
    if ($csr === false) { if ($temp) @unlink($temp); throw new RuntimeException('Échec création CSR'); }
    $cert = openssl_csr_sign($csr, $caCert, $caKey ?? $privateKey, $days, $args);
    if ($temp) @unlink($temp);
    if ($cert === false) { throw new RuntimeException('Échec signature certificat'); }
    if (!openssl_x509_export($cert, $out)) { throw new RuntimeException('Échec export certificat'); }
    return $out;
}
function exportPkcs12(string $certPEM, OpenSSLAsymmetricKey $key, string $password, string $friendlyName): array {
    $pkcs12 = '';
    if (!openssl_pkcs12_export($certPEM, $pkcs12, $key, $password, ['friendly_name' => $friendlyName])) {
        throw new RuntimeException('Échec export PKCS#12');
    }
    return ['binary' => $pkcs12, 'b64' => base64_encode($pkcs12)];
}
function certFingerprintSha256(string $certPEM): string { return openssl_x509_fingerprint($certPEM, 'sha256') ?: ''; }
function certValidityRange(string $certPEM): array {
    $p = openssl_x509_parse($certPEM);
    if (!is_array($p)) return ['from'=>'','to'=>''];
    return [
        'from' => isset($p['validFrom_time_t']) ? gmdate('Y-m-d\TH:i:s\Z', $p['validFrom_time_t']) : '',
        'to'   => isset($p['validTo_time_t'])   ? gmdate('Y-m-d\TH:i:s\Z', $p['validTo_time_t'])   : ''
    ];
}
function createZip(array $files): ?string {
    if (empty($files)) { return null; }
    $zipFile = tempnam(sys_get_temp_dir(), 'certs_');
    if ($zipFile === false) { return null; }
    $zipFile .= '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) !== true) { return null; }
    foreach ($files as $name => $content) { $zip->addFromString($name, $content); }
    $zip->close();
    return $zipFile;
}
function writeTempFile(string $filename, string $content): string {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    $i = 0; $info = pathinfo($filename);
    while (file_exists($path)) {
        $i++; $alt = $info['filename'] . "_$i" . (isset($info['extension']) ? ".".$info['extension'] : "");
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $alt;
        $filename = $alt;
    }
    file_put_contents($path, $content);
    return $path;
}
function generateToken(int $length, bool $withSpecial): string {
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $special  = '!@#$%^&*()-_=+[]{};:,.?/';
    $pool = $alphabet . ($withSpecial ? $special : '');
    $poolLen = strlen($pool);
    if ($length < 8) $length = 8;
    if ($length > 256) $length = 256;
    $out = '';
    for ($i=0; $i<$length; $i++) {
        $idx = random_int(0, $poolLen-1);
        $out .= $pool[$idx];
    }
    return $out;
}

// Inputs
$options = $_POST['generate_option'] ?? [];
if (!is_array($options)) { $options = [$options]; }
$wantCA     = in_array('ca', $options, true);
$wantServer = in_array('server', $options, true);
$wantClient = in_array('client', $options, true);
$wantToken  = in_array('token_uuid', $options, true);
$wantKey    = in_array('keypair', $options, true);

// Per-type algos + sizes/curves
$caAlg  = $_POST['ca_key_alg'] ?? 'RSA';
$caBits = (int)($_POST['ca_rsa_bits'] ?? 2048);
$caCurve= (string)($_POST['ca_ec_curve'] ?? 'prime256v1');

$srvAlg = $_POST['srv_key_alg'] ?? 'RSA';
$srvBits= (int)($_POST['srv_rsa_bits'] ?? 2048);
$srvCurve=(string)($_POST['srv_ec_curve'] ?? 'prime256v1');

$cliAlg = $_POST['cli_key_alg'] ?? 'RSA';
$cliBits= (int)($_POST['cli_rsa_bits'] ?? 2048);
$cliCurve=(string)($_POST['cli_ec_curve'] ?? 'prime256v1');

foreach (['caBits'=>&$caBits,'srvBits'=>&$srvBits,'cliBits'=>&$cliBits] as $k=>&$v) {
    if (!in_array($v, [2048,3072,4096], true)) $v = 2048;
}
foreach (['caCurve'=>&$caCurve,'srvCurve'=>&$srvCurve,'cliCurve'=>&$cliCurve] as $k=>&$v) {
    if (!in_array($v, ['prime256v1','secp384r1','secp521r1'], true)) $v = 'prime256v1';
}

// Validités
$daysCA     = max(1, (int)($_POST['days_ca'] ?? 3650));
$daysServer = max(1, (int)($_POST['days_server'] ?? 825));
$daysClient = max(1, (int)($_POST['days_client'] ?? 825));

$serverCN = trim((string)($_POST['server_cn'] ?? ''));
$clientCN = trim((string)($_POST['client_cn'] ?? ''));
$serverSAN = trim((string)($_POST['server_san'] ?? ''));
$sanArray = [];
if ($serverSAN !== '') {
    foreach (array_map('trim', explode(',', $serverSAN)) as $p) {
        if ($p !== '') { $sanArray[] = $p; }
    }
}

$includeP12Srv = isset($_POST['include_p12_server']) && $_POST['include_p12_server'] === '1';
$includeP12Cli = isset($_POST['include_p12_client']) && $_POST['include_p12_client'] === '1';
$p12PassSrv = (string)($_POST['p12_password_server'] ?? '');
$p12PassCli = (string)($_POST['p12_password_client'] ?? '');

// Token options (support both legacy and current field names)
$tokenLength = isset($_POST['token_length']) ? (int)$_POST['token_length'] : 32;
$tokenWithSpecial = isset($_POST['token_include_special']) && $_POST['token_include_special'] === '1';
$tokenBytes = isset($_POST['token_bytes']) ? (int)$_POST['token_bytes'] : null; // Base64 mode if provided
$tokenUrlSafe = isset($_POST['token_urlsafe']) && $_POST['token_urlsafe'] === '1';
$uuidCount = isset($_POST['uuid_count']) ? (int)$_POST['uuid_count'] : 1;
if ($uuidCount < 1) $uuidCount = 1; if ($uuidCount > 50) $uuidCount = 50;

$result = ['files' => []];
$filesForZip = [];

try {
    // CA if needed
    $caKey = null;
    $caCert = null;
    if ($wantCA || $wantServer || $wantClient) {
        $caKey  = generateKey($caAlg, $caBits, $caCurve);
        $caCert = generateCertificate(['CN' => 'MyPrivateCA'], $caKey, null, null, $daysCA);
        $result['ca_cert'] = $caCert;
        $result['ca_key']  = exportPrivateKey($caKey);
        $result['ca_fingerprint'] = certFingerprintSha256($caCert);
        $val = certValidityRange($caCert);
        $result['ca_valid_from'] = $val['from'];
        $result['ca_valid_to']   = $val['to'];
        $filesForZip['ca_cert.pem'] = $caCert;
        $filesForZip['ca_key.pem']  = $result['ca_key'];
        $result['files']['ca_cert.pem'] = writeTempFile('ca_cert.pem', $caCert);
        $result['files']['ca_key.pem']  = writeTempFile('ca_key.pem', $result['ca_key']);
    }

    if ($wantServer) {
        $cn = $serverCN !== '' ? $serverCN : 'localhost';
        $srvKey  = generateKey($srvAlg, $srvBits, $srvCurve);
        $srvCert = generateCertificate(['CN' => $cn], $srvKey, $caCert, $caKey, $daysServer, $sanArray);
        $result['server_cert'] = $srvCert;
        $result['server_key']  = exportPrivateKey($srvKey);
        $result['server_filename_cert'] = $cn . '_server_cert.pem';
        $result['server_filename_key']  = $cn . '_server_key.pem';
        $filesForZip[$result['server_filename_cert']] = $srvCert;
        $filesForZip[$result['server_filename_key']]  = $result['server_key'];
        $result['files'][$result['server_filename_cert']] = writeTempFile($result['server_filename_cert'], $srvCert);
        $result['files'][$result['server_filename_key']]  = writeTempFile($result['server_filename_key'], $result['server_key']);

        if ($includeP12Srv && $p12PassSrv !== '') {
            $p12 = exportPkcs12($srvCert, $srvKey, $p12PassSrv, 'server');
            $p12Filename = $cn . '_server.p12';
            $result['server_filename_p12'] = $p12Filename;
            $result['server_p12_b64'] = $p12['b64'];
            $filesForZip[$p12Filename] = $p12['binary'];
            $filesForZip[$p12Filename . '.b64.txt'] = $p12['b64'];
            $result['files'][$p12Filename] = writeTempFile($p12Filename, $p12['binary']);
            $result['files'][$p12Filename . '.b64.txt'] = writeTempFile($p12Filename . '.b64.txt', $p12['b64']);
        }

        $result['server_fingerprint'] = certFingerprintSha256($srvCert);
        $val = certValidityRange($srvCert);
        $result['server_valid_from'] = $val['from'];
        $result['server_valid_to']   = $val['to'];

        $apache =
"SSLEngine on
SSLCertificateFile    /etc/ssl/certs/{$cn}_server_cert.pem
SSLCertificateKeyFile /etc/ssl/private/{$cn}_server_key.pem
SSLCACertificateFile  /etc/ssl/certs/ca_cert.pem
SSLProtocol           all -SSLv2 -SSLv3
SSLCipherSuite        HIGH:!aNULL:!MD5";
        $nginx =
"ssl_certificate     /etc/ssl/certs/{$cn}_server_cert.pem;
ssl_certificate_key /etc/ssl/private/{$cn}_server_key.pem;
ssl_client_certificate /etc/ssl/certs/ca_cert.pem;
ssl_verify_client optional;";
        $result['apache_hint'] = $apache;
        $result['nginx_hint']  = $nginx;
        $filesForZip['apache_hint.txt'] = $apache;
        $filesForZip['nginx_hint.conf'] = $nginx;
        $result['files']['apache_hint.txt'] = writeTempFile('apache_hint.txt', $apache);
        $result['files']['nginx_hint.conf'] = writeTempFile('nginx_hint.conf', $nginx);
    }

    if ($wantClient) {
        $cn = $clientCN !== '' ? $clientCN : 'client';
        $cliKey  = generateKey($cliAlg, $cliBits, $cliCurve);
        $cliCert = generateCertificate(['CN' => $cn], $cliKey, $caCert, $caKey, $daysClient);
        $result['client_cert'] = $cliCert;
        $result['client_key']  = exportPrivateKey($cliKey);
        $result['client_filename_cert'] = $cn . '_client_cert.pem';
        $result['client_filename_key']  = $cn . '_client_key.pem';
        $filesForZip[$result['client_filename_cert']] = $cliCert;
        $filesForZip[$result['client_filename_key']]  = $result['client_key'];
        $result['files'][$result['client_filename_cert']] = writeTempFile($result['client_filename_cert'], $cliCert);
        $result['files'][$result['client_filename_key']]  = writeTempFile($result['client_filename_key'], $result['client_key']);

        if ($includeP12Cli && $p12PassCli !== '') {
            $p12 = exportPkcs12($cliCert, $cliKey, $p12PassCli, 'client');
            $p12Filename = $cn . '_client.p12';
            $result['client_filename_p12'] = $p12Filename;
            $result['client_p12_b64'] = $p12['b64'];
            $filesForZip[$p12Filename] = $p12['binary'];
            $filesForZip[$p12Filename . '.b64.txt'] = $p12['b64'];
            $result['files'][$p12Filename] = writeTempFile($p12Filename, $p12['binary']);
            $result['files'][$p12Filename . '.b64.txt'] = writeTempFile($p12Filename . '.b64.txt', $p12['b64']);
        }

        $result['client_fingerprint'] = certFingerprintSha256($cliCert);
        $val = certValidityRange($cliCert);
        $result['client_valid_from'] = $val['from'];
        $result['client_valid_to']   = $val['to'];
    }

    if ($wantKey) {
        $kp = generateKey($caAlg, $caBits, $caCurve);
        $result['private_key'] = exportPrivateKey($kp);
        $result['public_key']  = exportPublicKey($kp);
        $filesForZip['private_key.pem'] = $result['private_key'];
        $filesForZip['public_key.pem']  = $result['public_key'];
        $result['files']['private_key.pem'] = writeTempFile('private_key.pem', $result['private_key']);
        $result['files']['public_key.pem']  = writeTempFile('public_key.pem', $result['public_key']);
    }

    if ($wantToken) {
        // UUID(s)
        $uuids = [];
        for ($i=0; $i<$uuidCount; $i++) {
            $one = (function(){
                if (function_exists('uuid_create')) { return uuid_create(UUID_TYPE_RANDOM); }
                $data = random_bytes(16);
                $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
                $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
                return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
            })();
            $uuids[] = $one;
        }
        $uuidOut = implode("\n", $uuids);

        // Token: either Base64(url-safe) from bytes, or legacy charset token
        if ($tokenBytes !== null && $tokenBytes > 0) {
            $bytes = max(4, min(512, $tokenBytes));
            $raw = random_bytes($bytes);
            $b64 = base64_encode($raw);
            if ($tokenUrlSafe) { $b64 = rtrim(strtr($b64, '+/', '-_'), '='); }
            $token = $b64;
        } else {
            $token = generateToken($tokenLength, $tokenWithSpecial);
        }

        $result['uuid']  = $uuidOut;
        $result['token'] = $token;
        $filesForZip['uuid.txt']  = $uuidOut;
        $filesForZip['token.txt'] = $token;
        $result['files']['uuid.txt']  = writeTempFile('uuid.txt', $uuidOut);
        $result['files']['token.txt'] = writeTempFile('token.txt', $token);
    }

    $zipPath = createZip($filesForZip);
    if ($zipPath !== null) { $result['zip_path'] = $zipPath; }

    // Discard any buffered warnings/notices to keep response clean JSON
    if (ob_get_length() !== false) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    $noise = '';
    try { $noise = (string)ob_get_contents(); } catch (Throwable $_) { $noise = ''; }
    if (ob_get_length() !== false) { ob_end_clean(); }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $payload = ['error' => $e->getMessage()];
    if ($noise !== '') { $payload['debug'] = mb_substr($noise, 0, 400); }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
