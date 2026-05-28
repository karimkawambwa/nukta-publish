<?php

/**
 * SCP Deployment Script for Nukta Publish WordPress Theme
 * Packages the theme as a zip and uploads it via SCP
 * Triggered extraction via SSH
 */

// Main deployment
if (php_sapi_name() === 'cli') {
  echo "\n";
  echo "╔════════════════════════════════════════════════════════════╗\n";
  echo "║     Nukta Publish - Theme Deployment Script               ║\n";
  echo "║     Usage: php scripts/deploy.php                         ║\n";
  echo "╚════════════════════════════════════════════════════════════╝\n\n";

  $baseDir = dirname(__DIR__);

  // Server Configuration
  $sshHost = '82.198.227.109';
  $sshUser = 'u620189679';
  $sshPort = '65002';
  $remoteDir = '/home/u620189679/domains/publish.nukta.co.tz/public_html/wp-content/themes/nukta-publish';
  $siteUrl = 'https://publish.nukta.co.tz';
  
  // 1. Create Zip Archive
  echo "[*] Creating theme package (zip)...\n";
  $zipFile = $baseDir . '/deploy_package.zip';
  if (file_exists($zipFile)) unlink($zipFile);

  $zip = new ZipArchive();
  if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
      fwrite(STDERR, "[✗] Cannot create zip file\n");
      exit(1);
  }

  // Files to include
  $filesToInclude = [
      'index.php',
      'style.css',
      'functions.php',
      'header.php',
      'footer.php',
      'screenshot.png' // Standard WP theme screenshot (if exists)
  ];

  foreach ($filesToInclude as $file) {
      if (file_exists($baseDir . '/' . $file)) {
          $zip->addFile($baseDir . '/' . $file, $file);
          echo "[*] Added {$file}\n";
      }
  }

  // Add directories (assets if any)
  $dirsToInclude = ['assets', 'inc', 'template-parts'];
  foreach ($dirsToInclude as $dir) {
      if (is_dir($baseDir . '/' . $dir)) {
          echo "[*] Adding {$dir}/ to package...\n";
          $files = new RecursiveIteratorIterator(
              new RecursiveDirectoryIterator($baseDir . '/' . $dir, RecursiveDirectoryIterator::SKIP_DOTS),
              RecursiveIteratorIterator::LEAVES_ONLY
          );
          foreach ($files as $file) {
              if ($file->isFile()) {
                  $filePath = $file->getRealPath();
                  $relativePath = $dir . '/' . substr($filePath, strlen($baseDir . '/' . $dir) + 1);
                  if (strpos($filePath, '.DS_Store') !== false) continue;
                  $zip->addFile($filePath, $relativePath);
              }
          }
      }
  }
  
  $zip->close();
  echo "[✓] Theme package created: " . round(filesize($zipFile) / 1024, 2) . " KB\n";

  // 2. Upload Zip to Server via SCP
  echo "\n[*] Uploading theme package via SCP...\n";
  $sshKey = getenv('HOME') . '/.ssh/id_ed25519';
  $scpCommand = sprintf(
      "scp -P %s -i %s -o StrictHostKeyChecking=no %s %s@%s:%s/deploy_package.zip",
      escapeshellarg($sshPort),
      escapeshellarg($sshKey),
      escapeshellarg($zipFile),
      escapeshellarg($sshUser),
      escapeshellarg($sshHost),
      escapeshellarg(dirname($remoteDir)) // Upload to themes directory
  );

  echo "[*] Running SCP Upload...\n";
  exec($scpCommand, $scpOutput, $scpReturn);

  if ($scpReturn !== 0) {
      echo "[✗] SCP Upload failed with exit code: $scpReturn\n";
      echo implode("\n", $scpOutput) . "\n";
      unlink($zipFile);
      exit(1);
  }
  echo "[✓] Package uploaded successfully.\n";

  // 3. Trigger Extraction via SSH
  echo "[*] Triggering extraction on server via SSH...\n";
  
  $sshCommand = sprintf(
      "ssh -p %s -i %s -o StrictHostKeyChecking=no %s@%s \"mkdir -p %s && cd %s && unzip -o ../deploy_package.zip && rm ../deploy_package.zip\"",
      escapeshellarg($sshPort),
      escapeshellarg($sshKey),
      escapeshellarg($sshUser),
      escapeshellarg($sshHost),
      escapeshellarg($remoteDir),
      escapeshellarg($remoteDir)
  );

  echo "[*] Running Command via SSH...\n";
  passthru($sshCommand, $sshReturn);

  if ($sshReturn === 0) {
      echo "[✓] Deployment completed successfully.\n";
  } else {
      echo "[✗] SSH command failed with exit code: $sshReturn\n";
      if (file_exists($zipFile)) unlink($zipFile);
      exit(1);
  }

  // Cleanup local zip
  if (file_exists($zipFile)) unlink($zipFile);

  echo "\n╔════════════════════════════════════════════════════════════╗\n";
  echo "║                     ✅ Deployment Complete!               ║\n";
  echo "║  Theme Folder: nukta-publish                               ║\n";
  echo "║  Site URL: {$siteUrl}                                     ║\n";
  echo "╚════════════════════════════════════════════════════════════╝\n\n";
}

?>
