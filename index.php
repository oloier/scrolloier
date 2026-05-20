<?php require_once("class.aggregator.php");

$postid = $_GET['post'] ?? null;
$page   = $_GET['page'] ?? null;

$agg = new aggregator($page);

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
        $agg->savePost($_POST, $_FILES);
        header('Location: ' . APP_PATH);
        exit;
    }
}

$titleAdd = '';
if (isset($page)) {
    $titleAdd = "Page $page | ";
} elseif (isset($postid)) {
    $titleAdd = "Post #$postid | ";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $titleAdd ?>Discovery Zone</title>
    <meta charset="utf-8" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>style.css" />
    <link rel="alternate" type="application/rss+xml" title="RSS" href="http://oloier.com/share/rss.php" />
    <link rel="shortcut icon" href="<?= APP_PATH ?>favicon.ico" />
</head>
<body>

<header>
    <a href="<?= APP_PATH ?>" id="logo"><img src="<?= ASSETS_PATH ?>img/logo.svg" alt="logo" /></a>
    <button id="add">New Post</button>
</header>

<?php
    echo '<main role="main" id="posts">';
    $agg->getAllOrderedPosts($postid);
    echo '</main>';

    echo '<nav id="pagination">';
    $agg->renderPagination();
    echo '</nav>';
?>

<dialog id="new">
    <h3>Add new post</h3>
    <form method="post" action="" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="title" />
        <input type="url" name="url" placeholder="url" />
        <details>
            <summary>or upload a file</summary>
            <input type="file" name="file" />
        </details>
        <input type="hidden" name="submittedPost" value="1" />
        <button type="submit">Submit Post</button>
        <button type="button" id="close-new">Cancel</button>
    </form>
</dialog>

<dialog id="lightbox">
    <img src="" alt="" />
</dialog>

<script src="<?= ASSETS_PATH ?>js/script.js"></script>

</body>
</html>
