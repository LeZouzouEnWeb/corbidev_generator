<?php
// Génération d'une paire de clés SSH Ed25519 au format OpenSSH
// Sortie: un ZIP avec id_ed25519 (clé privée OpenSSH) et id_ed25519.pub (clé publique)
// Paramètres facultatifs: comment, passphrase

declare(strict_types=1);

// Réponse JSON d'erreur et arrêt
function fail_json(string $message, int $code = 500): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Encodage "SSH string" (longueur 32-bit big-endian + octets)
function ssh_string(string $s): string {
    return pack('N', strlen($s)) . $s;
}

// Construit une clé privée OpenSSH (openssh-key-v1) pour Ed25519, non chiffrée (ciphername=none)
function build_openssh_private_ed25519(string $pub32, string $priv64, string $comment): string {
    $keytype = 'ssh-ed25519';

    // En-tête du format OpenSSH
    $magic = "openssh-key-v1\0";
    $ciphername = 'none';
    $kdfname = 'none';
    $kdfoptions = '';

    // BLOB de clé publique (utilisé à deux endroits)
    $pubkey_blob = ssh_string($keytype) . ssh_string($pub32);

    // Private block (non chiffré): checkint1, checkint2, entrée(s) clés, padding
    $check = random_int(0, 0xFFFFFFFF);
    $checkints = pack('N', $check) . pack('N', $check);

    // Pour Ed25519, le champ private_key est la concaténation (32 octets privés + 32 octets publics) = 64 octets
    $key_entry = ssh_string($keytype)
        . ssh_string($pub32)
        . ssh_string($priv64)
        . ssh_string($comment);

    $private_block = $checkints . $key_entry;

    // Padding à un multiple de 8 bytes (valeurs 1,2,3,...) pour compatibilité OpenSSH
    $block_len = strlen($private_block);
    $pad_needed = (8 - ($block_len % 8)) % 8;
    if ($pad_needed > 0) {
        $pad = '';
        for ($i = 1; $i <= $pad_needed; $i++) {
            $pad .= chr($i);
        }
        $private_block .= $pad;
    }

    // Construction finale
    $buf = $magic
        . ssh_string($ciphername)
        . ssh_string($kdfname)
        . ssh_string($kdfoptions)
        . pack('N', 1)                 // nombre de clés publiques
        . ssh_string($pubkey_blob)     // blob public
        . ssh_string($private_block);  // bloc privé (non chiffré)

    $b64 = base64_encode($buf);
    // Découpage en lignes (conventionnel ~70 colonnes)
    $b64_wrapped = trim(chunk_split($b64, 70, "\n"));

    return "-----BEGIN OPENSSH PRIVATE KEY-----\n" . $b64_wrapped . "\n-----END OPENSSH PRIVATE KEY-----\n";
}

// Construit la ligne de clé publique OpenSSH: "ssh-ed25519 <base64> <comment>"
function build_openssh_public_ed25519(string $pub32, string $comment): string {
    $keytype = 'ssh-ed25519';
    $pubkey_blob = ssh_string($keytype) . ssh_string($pub32);
    $b64 = base64_encode($pubkey_blob);
    $comment = str_replace(["\r", "\n"], ' ', $comment);
    return $keytype . ' ' . $b64 . ($comment !== '' ? ' ' . $comment : '');
}

// État des extensions/outils
$HAS_SODIUM = extension_loaded('sodium');

// Entrées
$comment = isset($_REQUEST['comment']) ? trim((string)$_REQUEST['comment']) : '';
$passphrase = isset($_REQUEST['passphrase']) ? (string)$_REQUEST['passphrase'] : '';
if ($comment === '') {
    // Valeur par défaut discrète si non fournie
    $comment = 'generated@corbidev_generator';
}

// Utilitaire: trouver ssh-keygen si disponible
function find_ssh_keygen(): ?string {
    $paths = [];
    if (stripos(PHP_OS, 'WIN') === 0) {
        @exec('where ssh-keygen 2> NUL', $paths, $rc);
        if (isset($rc) && $rc === 0 && !empty($paths)) return $paths[0];
    } else {
        @exec('command -v ssh-keygen 2>/dev/null', $paths, $rc);
        if (isset($rc) && $rc === 0 && !empty($paths)) return $paths[0];
    }
    return null;
}

// Utilitaire: créer un dossier temporaire unique
function make_temp_dir(string $prefix = 'sshk_'): string {
    $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
    if (!mkdir($base, 0700) && !is_dir($base)) {
        throw new RuntimeException('mktemp dir failed');
    }
    return $base;
}

// Si une passphrase est fournie, tenter via ssh-keygen (clé privée chiffrée)
if ($passphrase !== '') {
    $ssh = find_ssh_keygen();
    if ($ssh === null) {
        fail_json('Passphrase demandée mais ssh-keygen introuvable sur le serveur. Impossible de chiffrer la clé privée.', 500);
    }
    try {
        $dir = make_temp_dir();
        $keyfile = $dir . DIRECTORY_SEPARATOR . 'id_ed25519';
        $cmd = escapeshellarg($ssh)
            . ' -t ed25519 -C ' . escapeshellarg($comment)
            . ' -N ' . escapeshellarg($passphrase)
            . ' -f ' . escapeshellarg($keyfile)
            . ' -q';
        $out = [];
        $rc = 0;
        @exec($cmd, $out, $rc);
        if ($rc !== 0 || !is_file($keyfile) || !is_file($keyfile . '.pub')) {
            throw new RuntimeException('ssh-keygen a échoué (code ' . $rc . ').');
        }
        $private_openssh = file_get_contents($keyfile);
        $public_line = trim((string)file_get_contents($keyfile . '.pub'));

        if (!class_exists('ZipArchive')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'format' => 'raw',
                'id_ed25519' => $private_openssh,
                'id_ed25519_pub' => $public_line,
                'encrypted' => true,
                'note' => 'ZipArchive indisponible; clés renvoyées en JSON.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // Nettoyage
            @unlink($keyfile); @unlink($keyfile . '.pub'); @rmdir($dir);
            exit;
        }

        $zip = new ZipArchive();
        $tmpZip = tempnam(sys_get_temp_dir(), 'ssh_');
        if ($tmpZip === false) throw new RuntimeException('tmp zip manquant');
        if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpZip);
            throw new RuntimeException('Ouverture ZIP impossible');
        }
        $zip->addFromString('id_ed25519', $private_openssh);
        $zip->addFromString('id_ed25519.pub', $public_line . "\n");
        $zip->close();

        $filename = 'ssh_ed25519_' . gmdate('Ymd_His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Length: ' . (string)filesize($tmpZip));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($tmpZip);
        @unlink($tmpZip);
        // Nettoyage
        @unlink($keyfile); @unlink($keyfile . '.pub'); @rmdir($dir);
        exit;
    } catch (Throwable $e) {
        fail_json('Échec ssh-keygen: ' . $e->getMessage(), 500);
    }
}

// Sinon: pas de passphrase -> génération non chiffrée
if ($HAS_SODIUM) {
    try {
        $kp = sodium_crypto_sign_keypair();
        $pub = sodium_crypto_sign_publickey($kp);     // 32 octets
        $sec = sodium_crypto_sign_secretkey($kp);     // 64 octets (32 privés + 32 publics)
    } catch (Throwable $e) {
        fail_json('Échec génération Ed25519: ' . $e->getMessage(), 500);
    }

    $public_line = build_openssh_public_ed25519($pub, $comment);
    $private_openssh = build_openssh_private_ed25519($pub, $sec, $comment);

    if (!class_exists('ZipArchive')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'format' => 'raw',
            'id_ed25519' => $private_openssh,
            'id_ed25519_pub' => $public_line,
            'encrypted' => false,
            'note' => 'ZipArchive indisponible; clés renvoyées en JSON.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $zip = new ZipArchive();
    $tmpZip = tempnam(sys_get_temp_dir(), 'ssh_');
    if ($tmpZip === false) {
        fail_json('Impossible de créer un fichier temporaire ZIP.');
    }
    if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpZip);
        fail_json('Impossible d’ouvrir le ZIP temporaire.');
    }
    $zip->addFromString('id_ed25519', $private_openssh);
    $zip->addFromString('id_ed25519.pub', $public_line . "\n");
    $zip->close();
    $filename = 'ssh_ed25519_' . gmdate('Ymd_His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . (string)filesize($tmpZip));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
} else {
    // Fallback: pas de sodium, tenter ssh-keygen non chiffré
    $ssh = find_ssh_keygen();
    if ($ssh === null) {
        fail_json('Ni sodium ni ssh-keygen disponibles sur le serveur. Impossible de générer la clé.', 500);
    }
    try {
        $dir = make_temp_dir();
        $keyfile = $dir . DIRECTORY_SEPARATOR . 'id_ed25519';
        $cmd = escapeshellarg($ssh)
            . ' -t ed25519 -C ' . escapeshellarg($comment)
            . ' -N ' . escapeshellarg('')
            . ' -f ' . escapeshellarg($keyfile)
            . ' -q';
        $out = [];
        $rc = 0;
        @exec($cmd, $out, $rc);
        if ($rc !== 0 || !is_file($keyfile) || !is_file($keyfile . '.pub')) {
            throw new RuntimeException('ssh-keygen a échoué (code ' . $rc . ').');
        }
        $private_openssh = file_get_contents($keyfile);
        $public_line = trim((string)file_get_contents($keyfile . '.pub'));

        if (!class_exists('ZipArchive')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'format' => 'raw',
                'id_ed25519' => $private_openssh,
                'id_ed25519_pub' => $public_line,
                'encrypted' => false,
                'note' => 'ZipArchive indisponible; clés renvoyées en JSON.'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            @unlink($keyfile); @unlink($keyfile . '.pub'); @rmdir($dir);
            exit;
        }

        $zip = new ZipArchive();
        $tmpZip = tempnam(sys_get_temp_dir(), 'ssh_');
        if ($tmpZip === false) throw new RuntimeException('tmp zip manquant');
        if ($zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpZip);
            throw new RuntimeException('Ouverture ZIP impossible');
        }
        $zip->addFromString('id_ed25519', $private_openssh);
        $zip->addFromString('id_ed25519.pub', $public_line . "\n");
        $zip->close();

        $filename = 'ssh_ed25519_' . gmdate('Ymd_His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Length: ' . (string)filesize($tmpZip));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($tmpZip);
        @unlink($tmpZip);
        @unlink($keyfile); @unlink($keyfile . '.pub'); @rmdir($dir);
        exit;
    } catch (Throwable $e) {
        fail_json('Échec ssh-keygen (fallback): ' . $e->getMessage(), 500);
    }
}
