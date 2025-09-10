<?php
declare(strict_types=1);

function fail_json(string $message, int $code = 500): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function ssh_string(string $s): string { return pack('N', strlen($s)) . $s; }
function build_openssh_private_ed25519(string $pub32, string $priv64, string $comment): string {
    $keytype = 'ssh-ed25519';
    $magic = "openssh-key-v1\0";
    $ciphername = 'none'; $kdfname = 'none'; $kdfoptions = '';
    $pubkey_blob = ssh_string($keytype) . ssh_string($pub32);
    $check = random_int(0, 0xFFFFFFFF);
    $checkints = pack('N', $check) . pack('N', $check);
    $key_entry = ssh_string($keytype) . ssh_string($pub32) . ssh_string($priv64) . ssh_string($comment);
    $private_block = $checkints . $key_entry;
    $pad_needed = (8 - (strlen($private_block) % 8)) % 8;
    if ($pad_needed > 0) { $pad=''; for ($i=1;$i<=$pad_needed;$i++) $pad.=chr($i); $private_block.=$pad; }
    $buf = $magic . ssh_string($ciphername) . ssh_string($kdfname) . ssh_string($kdfoptions)
        . pack('N',1) . ssh_string($pubkey_blob) . ssh_string($private_block);
    $b64 = base64_encode($buf);
    $b64_wrapped = trim(chunk_split($b64, 70, "\n"));
    return "-----BEGIN OPENSSH PRIVATE KEY-----\n" . $b64_wrapped . "\n-----END OPENSSH PRIVATE KEY-----\n";
}
function build_openssh_public_ed25519(string $pub32, string $comment): string {
    $keytype = 'ssh-ed25519';
    $b64 = base64_encode(ssh_string($keytype) . ssh_string($pub32));
    $comment = str_replace(["\r","\n"], ' ', $comment);
    return $keytype . ' ' . $b64 . ($comment!=='' ? ' ' . $comment : '');
}
function find_ssh_keygen(): ?string {
    $paths=[]; $rc=null;
    if (stripos(PHP_OS, 'WIN') === 0) {
        @exec('where ssh-keygen 2> NUL', $paths, $rc);
        if ($rc === 0 && !empty($paths)) return $paths[0];
    } else {
        @exec('command -v ssh-keygen 2>/dev/null', $paths, $rc);
        if ($rc === 0 && !empty($paths)) return $paths[0];
    }
    return null;
}
function make_temp_dir(string $prefix='sshk_'): string {
    $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
    if (!mkdir($base, 0700) && !is_dir($base)) throw new RuntimeException('mktemp dir failed');
    return $base;
}

$HAS_SODIUM = extension_loaded('sodium');
$comment = isset($_REQUEST['comment']) ? trim((string)$_REQUEST['comment']) : '';
$passphrase = isset($_REQUEST['passphrase']) ? (string)$_REQUEST['passphrase'] : '';
if ($comment === '') $comment = 'generated@corbidev_generator';

try {
    if ($passphrase !== '') {
        $ssh = find_ssh_keygen();
        if ($ssh === null) fail_json('Passphrase fournie mais ssh-keygen introuvable sur le serveur.', 500);
        $dir = make_temp_dir();
        $keyfile = $dir . DIRECTORY_SEPARATOR . 'id_ed25519';
        $cmd = escapeshellarg($ssh) . ' -t ed25519 -C ' . escapeshellarg($comment)
            . ' -N ' . escapeshellarg($passphrase) . ' -f ' . escapeshellarg($keyfile) . ' -q';
        $out=[]; $rc=0; @exec($cmd, $out, $rc);
        if ($rc !== 0 || !is_file($keyfile) || !is_file($keyfile.'.pub')) throw new RuntimeException('ssh-keygen a échoué (code '.$rc.').');
        $private_openssh = file_get_contents($keyfile);
        $public_line = trim((string)file_get_contents($keyfile.'.pub'));
        @unlink($keyfile); @unlink($keyfile.'.pub'); @rmdir($dir);
    } else if ($HAS_SODIUM) {
        $kp = sodium_crypto_sign_keypair();
        $pub = sodium_crypto_sign_publickey($kp);
        $sec = sodium_crypto_sign_secretkey($kp);
        $private_openssh = build_openssh_private_ed25519($pub, $sec, $comment);
        $public_line = build_openssh_public_ed25519($pub, $comment);
    } else {
        $ssh = find_ssh_keygen();
        if ($ssh === null) fail_json('Ni sodium ni ssh-keygen disponibles.', 500);
        $dir = make_temp_dir();
        $keyfile = $dir . DIRECTORY_SEPARATOR . 'id_ed25519';
        $cmd = escapeshellarg($ssh) . ' -t ed25519 -C ' . escapeshellarg($comment)
            . ' -N ' . escapeshellarg('') . ' -f ' . escapeshellarg($keyfile) . ' -q';
        $out=[]; $rc=0; @exec($cmd, $out, $rc);
        if ($rc !== 0 || !is_file($keyfile) || !is_file($keyfile.'.pub')) throw new RuntimeException('ssh-keygen a échoué (code '.$rc.').');
        $private_openssh = file_get_contents($keyfile);
        $public_line = trim((string)file_get_contents($keyfile.'.pub'));
        @unlink($keyfile); @unlink($keyfile.'.pub'); @rmdir($dir);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'id_ed25519' => $private_openssh,
        'id_ed25519_pub' => $public_line,
        'encrypted' => $passphrase !== ''
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    fail_json('SSH JSON: ' . $e->getMessage(), 500);
}

