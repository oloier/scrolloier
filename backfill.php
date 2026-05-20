<?php
require_once('class.db.php');

$db   = new db('share.db');
$dbc  = $db->db;

$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
          'gif' => 'image/gif', 'svg' => 'image/svg+xml'];

$rows = $dbc->query("SELECT id, file FROM posts WHERE file IS NOT NULL AND file != '' AND image IS NULL")->fetchAll();

echo count($rows) . " posts to backfill\n";

$update = $dbc->prepare("UPDATE posts SET image = ?, mime = ? WHERE id = ?");

foreach ($rows as $row) {
    $path = __DIR__ . '/assets/uploads/' . $row['id'] . '.' . $row['file'];
    if (!file_exists($path)) {
        echo "MISSING: $path\n";
        continue;
    }
    $data = file_get_contents($path);
    $mime = $mimes[strtolower($row['file'])] ?? 'image/jpeg';
    $update->execute([$data, $mime, $row['id']]);
    echo "OK: {$row['id']}.{$row['file']}\n";
}

echo "done\n";
