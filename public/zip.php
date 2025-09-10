<?php
declare(strict_types=1);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="gen-data.zip"');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$files = $payload['files'] ?? [];

$tmp = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();
$zip->open($tmp, ZipArchive::OVERWRITE);

$added = 0;
foreach ($files as $f) {
  $name = preg_replace('/[^\w.\-\/]+/','_', $f['name'] ?? ('file'.($added+1).'.txt'));
  $content = (string)($f['content'] ?? '');
  // éviter ZIP vide
  if ($content === '') $content = " ";
  $zip->addFromString($name, $content);
  $added++;
}
$zip->close();

readfile($tmp);
@unlink($tmp);
