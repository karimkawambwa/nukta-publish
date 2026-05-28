<?php

/**
 * Deploy mu-plugins and server-side patches to publish.nukta.co.tz
 * Usage: php scripts/deploy-mu-plugin.php
 */

if (php_sapi_name() !== 'cli') {
    exit(0);
}

$baseDir = dirname(__DIR__);
$sshHost = '82.198.227.109';
$sshUser = 'u620189679';
$sshPort = '65002';
$remoteRoot = '/home/u620189679/domains/publish.nukta.co.tz/public_html';
$sshKey = getenv('HOME') . '/.ssh/id_ed25519';

$files = [
    'wordpress-mu-plugins/nukta-publish-enhancements.php' => $remoteRoot . '/wp-content/mu-plugins/nukta-publish-enhancements.php',
    'scripts/patch-hero-auth-form.php' => '/tmp/nukta-patch-hero-auth-form.php',
];

echo "[*] Deploying Nukta Publish mu-plugin to publish.nukta.co.tz\n";

foreach ($files as $local => $remote) {
    $localPath = $baseDir . '/' . $local;
    if (!file_exists($localPath)) {
        fwrite(STDERR, "[✗] Missing file: {$local}\n");
        exit(1);
    }

    $remoteDir = dirname($remote);
    $mkdir = sprintf(
        'ssh -p %s -i %s -o StrictHostKeyChecking=no %s@%s %s',
        escapeshellarg($sshPort),
        escapeshellarg($sshKey),
        escapeshellarg($sshUser),
        escapeshellarg($sshHost),
        escapeshellarg('mkdir -p ' . $remoteDir)
    );
    exec($mkdir, $out, $code);
    if ($code !== 0) {
        fwrite(STDERR, "[✗] Failed to create remote directory: {$remoteDir}\n");
        exit(1);
    }

    $scp = sprintf(
        'scp -P %s -i %s -o StrictHostKeyChecking=no %s %s@%s:%s',
        escapeshellarg($sshPort),
        escapeshellarg($sshKey),
        escapeshellarg($localPath),
        escapeshellarg($sshUser),
        escapeshellarg($sshHost),
        escapeshellarg($remote)
    );
    echo "[*] Uploading {$local}\n";
    exec($scp, $scpOut, $scpCode);
    if ($scpCode !== 0) {
        fwrite(STDERR, "[✗] SCP failed for {$local} (exit {$scpCode})\n");
        exit(1);
    }
}

$ssh = sprintf(
    'ssh -p %s -i %s -o StrictHostKeyChecking=no %s@%s %s',
    escapeshellarg($sshPort),
    escapeshellarg($sshKey),
    escapeshellarg($sshUser),
    escapeshellarg($sshHost),
    escapeshellarg(
        'cd ' . $remoteRoot .
        ' && wp eval-file /tmp/nukta-patch-hero-auth-form.php 2>/dev/null || true' .
        ' && wp litespeed-purge all 2>/dev/null || wp cache flush'
    )
);
echo "[*] Flushing cache on server\n";
passthru($ssh, $sshCode);
if ($sshCode !== 0) {
    fwrite(STDERR, "[✗] Remote post-deploy failed (exit {$sshCode})\n");
    exit(1);
}

echo "[✓] Deploy complete — https://publish.nukta.co.tz\n";
