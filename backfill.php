<?php
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('cli only'); }

$db = new PDO('sqlite:' . __DIR__ . '/share.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
          'gif' => 'image/gif', 'svg' => 'image/svg+xml'];

$posts = $db->query("SELECT id, file FROM posts WHERE file IS NOT NULL AND image IS NULL")->fetchAll();

if (empty($posts)) { echo "nothing to backfill\n"; exit; }

$upd = $db->prepare("UPDATE posts SET image = ?, mime = ? WHERE id = ?");

foreach ($posts as $post) {
    $id   = $post['id'];
    $ext  = strtolower($post['file']);
    $path = __DIR__ . '/assets/uploads/' . $id . '.' . $ext;
    if (!file_exists($path)) { echo "missing: $path\n"; continue; }
    $data = file_get_contents($path);
    $upd->execute([$data, $mimes[$ext] ?? 'application/octet-stream', $id]);
    echo "post $id ($ext, " . round(strlen($data) / 1024) . "kb)\n";
}

echo "done\n";
