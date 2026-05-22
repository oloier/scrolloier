<?php
header('Content-Type: application/rss+xml; charset=utf-8');
require_once('class.aggregator.php');

// $routeUser may be set by the index.php router; fall back to query string
$rssUser = $routeUser ?? (isset($_GET['user']) && in_array($_GET['user'], USERS) ? $_GET['user'] : null);

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base  = $proto . '://' . $_SERVER['HTTP_HOST'] . APP_PATH;

$agg   = new aggregator(1, $rssUser);
$rows  = $agg->getAllPosts();

$feedTitle = 'scrolloier' . ($rssUser ? ' / ' . $rssUser : '');

$feedSelf = $base . ($rssUser ? 'feed/' . $rssUser : 'rss.php');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?= htmlspecialchars($feedTitle) ?></title>
    <link><?= htmlspecialchars($base . ($rssUser ?? '')) ?></link>
    <atom:link href="<?= htmlspecialchars($feedSelf) ?>" rel="self" type="application/rss+xml" />
    <description>shared stuff</description>
<?php foreach ($rows as $row):
    $id      = (int) $row['id'];
    $link    = $base . 'post/' . $id;
    $pubDate = date(DATE_RSS, strtotime($row['date'])); // already COALESCE(bumped, date)
    $url     = $row['url'] ?? '';
    $desc    = '';

    if (preg_match('/yt\.php\?v=([A-Za-z0-9_-]{11})/', $url, $ytm)) {
        $url = 'https://www.youtube.com/watch?v=' . $ytm[1];
    }

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
        $cName = $c['name'] ? '<b>' . htmlspecialchars($c['name']) . '</b>: ' : '';
        $desc .= '<p>' . $cName . $c['comment'] . '</p>';
    }
?>    <item>
        <title><?= htmlspecialchars($row['title'] ?: ($url ?: 'untitled')) ?></title>
        <link><?= htmlspecialchars($link) ?></link>
        <guid><?= htmlspecialchars($link . ($row['bumped'] ? '?bump=' . strtotime($row['bumped']) : '')) ?></guid>
        <pubDate><?= $pubDate ?></pubDate>
        <description><![CDATA[<?= $desc ?>]]></description>
    </item>
<?php endforeach ?>
</channel>
</rss>
