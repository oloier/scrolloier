<?php

require_once('class.aggregator.php');

// Routing — use APP_PATH (hardcoded) rather than SCRIPT_NAME (unreliable with try_files)
$_basePath = rtrim(APP_PATH, '/');
$_uriPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_path     = substr($_uriPath, strlen($_basePath));
$_segs     = array_values(array_filter(explode('/', ltrim($_path, '/'))));

$routeUser   = null;
$routePostId = null;

// /img/123  → media
if (($_segs[0] ?? '') === 'img' && !empty($_segs[1])) {
    $_GET['id'] = (int) $_segs[1];
    require 'img.php';
    exit;
}

// /feed/username  → RSS
if (($_segs[0] ?? '') === 'feed' && !empty($_segs[1]) && in_array($_segs[1], USERS)) {
    $routeUser = $_segs[1];
    require 'rss.php';
    exit;
}

// /post/123  → single post
if (($_segs[0] ?? '') === 'post' && !empty($_segs[1])) {
    $routePostId = (int) $_segs[1];
// /username  → user feed
} elseif (count($_segs) === 1 && in_array($_segs[0] ?? '', USERS)) {
    $routeUser = $_segs[0];
// backward compat query string
} elseif (isset($_GET['post'])) {
    $routePostId = (int) $_GET['post'];
}

$postid = $routePostId;
$page   = $_GET['page'] ?? null;
$agg    = new aggregator($page, $routeUser);

if (isset($_GET['delete']) && ($_GET['token'] ?? '') === DELETE_TOKEN) {
    $agg->deletePost((int) $_GET['delete']);
    header('Location: ' . APP_PATH);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Access-Control-Allow-Origin: *');
    if (!empty($_POST['submittedComment'])) {
        $saved = $agg->saveComment($_POST);
        if (!empty($_POST['_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true] + $saved);
            exit;
        }
        header('Location: ' . APP_PATH);
        exit;
    }
    if (!empty($_POST['submittedPost'])) {
        $newId = $agg->savePost($_POST, $_FILES);
        if (!empty($_POST['_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => (bool) $newId, 'html' => $newId ? $agg->renderPost($newId) : '']);
            exit;
        }
        header('Location: ' . APP_PATH);
        exit;
    }
}

$feedHref = $routeUser ? APP_PATH . 'feed/' . $routeUser : APP_PATH . 'feed/';
$pageTitle = 'scrolloier' . ($routeUser ? ' / ' . $routeUser : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>style.css" />
    <link rel="icon" type="image/svg+xml" href="<?= ASSETS_PATH ?>img/logo.svg" />
    <link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars($pageTitle) ?>" href="<?= htmlspecialchars($feedHref) ?>" />
</head>
<body>

<div id="drawer-wrap">
    <form id="new-post" method="post" action="<?= APP_PATH ?>" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="title" />
        <input type="url" name="url" placeholder="url" />
        <label id="file-label">
            <span>or a file</span>
            <input type="file" name="file" />
        </label>
        <?php if ($routeUser): ?>
        <input type="hidden" name="user" value="<?= htmlspecialchars($routeUser) ?>" />
        <?php endif ?>
        <input type="hidden" name="submittedPost" value="1" />
        <button type="submit">post it</button>
        <a href="<?= APP_PATH ?>bookmarklets.html" class="drawer-tools-link">bookmarklets &amp; shortcuts</a>
    </form>
    <button id="toggle-new">+ new post</button>
</div>

<?php
    $singlePost = isset($postid);
    $backUrl    = $routeUser ? APP_PATH . $routeUser : APP_PATH;
    if ($singlePost) echo '<a href="' . htmlspecialchars($backUrl) . '" id="back-link">← back</a>';
    echo '<main role="main" id="posts"' . ($singlePost ? ' class="single"' : '') . '>';
    $agg->getAllOrderedPosts($postid);
    echo '</main>';

    echo '<nav id="pagination">';
    $agg->renderPagination();
    echo '</nav>';
?>

<dialog id="lightbox">
    <img src="" alt="" />
</dialog>

<script src="<?= ASSETS_PATH ?>js/script.js"></script>

</body>
</html>
