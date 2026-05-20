<?php
require_once('class.aggregator.php');

$db  = new db('share.db');
$dbc = $db->db;

$mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];

$rows = $dbc->query("SELECT id, url FROM posts WHERE url != '' AND url IS NOT NULL AND image IS NULL")->fetchAll();
echo count($rows) . " URL posts to check\n";

$update = $dbc->prepare("UPDATE posts SET image = ?, mime = ?, url = '' WHERE id = ?");

foreach ($rows as $row) {
    $url = $row['url'];

    if (preg_match('/https?:\/\/(?:i\.)?imgur\.com\/([a-zA-Z0-9]+)\.gifv/i', $url, $m)) {
        $data = fetchUrl('https://i.imgur.com/' . $m[1] . '.mp4');
        if ($data) {
            $update->execute([$data, 'video/mp4', $row['id']]);
            echo "OK gifv  : [{$row['id']}] $url\n";
        } else {
            echo "FAIL     : [{$row['id']}] $url\n";
        }
        continue;
    }

    $ext  = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    $mime = $mimeMap[$ext] ?? null;
    if (!$mime) {
        echo "SKIP     : [{$row['id']}] $url\n";
        continue;
    }

    $data = fetchUrl($url);
    if ($data) {
        $update->execute([$data, $mime, $row['id']]);
        echo "OK image : [{$row['id']}] $url\n";
    } else {
        echo "FAIL     : [{$row['id']}] $url\n";
    }
}

echo "done\n";
