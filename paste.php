<?php
require_once('class.db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['img'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$file    = $_FILES['img'];
$mime    = mime_content_type($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($mime, $allowed, true) || $file['size'] > 4 * 1024 * 1024) {
    echo json_encode(['ok' => false]);
    exit;
}

$db   = new db('share.db');
$data = file_get_contents($file['tmp_name']);
$stmt = $db->db->prepare("INSERT INTO posts (title, mime, image, url, user) VALUES ('', ?, ?, '', '_attachment')");
$stmt->execute([$mime, $data]);

echo json_encode(['ok' => true, 'id' => (int) $db->db->lastInsertId()]);
