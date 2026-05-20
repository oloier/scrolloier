<?php
require_once('class.db.php');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit; }

$db   = new db('share.db');
$stmt = $db->db->prepare('SELECT image, mime, file FROM posts WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) { http_response_code(404); exit; }

header('Cache-Control: public, max-age=31536000, immutable');

if (!empty($post['image'])) {
    header('Content-Type: ' . ($post['mime'] ?: 'image/jpeg'));
    echo $post['image'];
    exit;
}

if (!empty($post['file'])) {
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
              'gif' => 'image/gif', 'svg' => 'image/svg+xml'];
    $path  = __DIR__ . '/assets/uploads/' . $id . '.' . $post['file'];
    if (file_exists($path)) {
        header('Content-Type: ' . ($mimes[strtolower($post['file'])] ?? 'image/jpeg'));
        readfile($path);
        exit;
    }
}

http_response_code(404);
