<?php
header('Content-Type: application/rss+xml; charset=utf-8');
require_once('class.aggregator.php');

// $routeUser may be set by the index.php router; fall back to query string
$rssUser = $routeUser ?? (isset($_GET['user']) && in_array($_GET['user'], USERS) ? $_GET['user'] : null);

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base  = $proto . '://' . $_SERVER['HTTP_HOST'] . APP_PATH;

$agg   = new aggregator(1, $rssUser);
$rows  = $agg->getAllPosts();

$feedTitle = 'Scrolloier' . ($rssUser ? ' / ' . $rssUser : '');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?><rss version="2.0">
<channel>
    <title><?= htmlspecialchars($feedTitle) ?></title>
    <link><?= htmlspecialchars($base . ($rssUser ?? '')) ?></link>
    <description>shared stuff</description>
<?php foreach ($rows as $row):
    $id      = (int) $row['id'];
    $link    = $base . 'post/' . $id;
    $pubDate = date(DATE_RSS, strtotime($row['date'])); // already COALESCE(bumped, date)
    $url     = $row['url'] ?? '';
    $desc    = '';

    if (!empty($row['has_image'])) {
        $src   = $base . 'img.php?id=' . $id;
        $mime  = $row['mime'] ?? '';
        if (strpos($mime, 'video/') === 0) {
            $desc .= '<video controls><source src="' . $src . '" type="' . htmlspecialchars($mime) . '"></video>';
        } else {
            $desc .= '<img src="' . $src . '" style="max-width:100%" />';
        }
    } elseif (!empty($url)) {
        if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            $desc .= '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $m[1] . '" frameborder="0" allowfullscreen></iframe>';
        } elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            $desc .= '<iframe src="https://player.vimeo.com/video/' . $m[1] . '" width="560" height="315" frameborder="0" allowfullscreen></iframe>';
        } else {
            $u = htmlspecialchars($url);
            $desc .= '<a href="' . $u . '">' . $u . '</a>';
        }
    }

    foreach ($agg->getPostComments($id) as $c) {
        $desc .= '<blockquote><b>' . htmlspecialchars($c['name']) . '</b><p>' . $c['comment'] . '</p></blockquote>';
    }
?>    <item>
        <title><?= htmlspecialchars($row['title'] ?? 'untitled') ?></title>
        <link><?= htmlspecialchars($link) ?></link>
        <guid><?= htmlspecialchars($link) ?></guid>
        <pubDate><?= $pubDate ?></pubDate>
        <description><![CDATA[<?= $desc ?>]]></description>
    </item>
<?php endforeach ?>
</channel>
</rss>
