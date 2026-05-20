<?php require_once("class.aggregator.php");

$postid = $_GET['post'] ?? null;
$page   = $_GET['page'] ?? null;

$agg = new aggregator($page);

if (isset($_GET['delete']) && ($_GET['token'] ?? '') === DELETE_TOKEN) {
    $agg->deletePost((int) $_GET['delete']);
    header('Location: ' . APP_PATH);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Scrolloier</title>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>style.css" />
    <link rel="icon" type="image/svg+xml" href="<?= ASSETS_PATH ?>img/logo.svg" />
    <link rel="alternate" type="application/rss+xml" title="Scrolloier" href="<?= APP_PATH ?>rss.php" />
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
        <input type="hidden" name="submittedPost" value="1" />
        <button type="submit">post it</button>
    </form>
    <button id="toggle-new">+ new post</button>
</div>

<?php
    $singlePost = isset($postid);
    if ($singlePost) echo '<a href="' . APP_PATH . '" id="back-link">← all posts</a>';
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
