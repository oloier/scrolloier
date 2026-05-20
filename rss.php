<?php
header('Content-Type: application/rss+xml; charset=utf-8');
require_once('class.aggregator.php');

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base  = $proto . '://' . $_SERVER['HTTP_HOST'] . APP_PATH;

$agg  = new aggregator();
$rows = $agg->getAllPosts();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?><rss version="2.0">
<channel>
    <title>Scrolloier</title>
    <link><?= htmlspecialchars($base) ?></link>
    <description>shared stuff</description>
<?php foreach ($rows as $row):
    $id      = (int) $row['id'];
    $link    = $base . '?post=' . $id;
    $pubDate = date(DATE_RSS, strtotime($row['date']));
    $desc    = '';
    if (!empty($row['has_image'])) {
        $src   = $base . 'img.php?id=' . $id;
        $desc .= '<img src="' . htmlspecialchars($src) . '" />';
    } elseif (!empty($row['url'])) {
        $u     = htmlspecialchars($row['url']);
        $desc .= '<a href="' . $u . '">' . $u . '</a>';
    }
    foreach ($agg->getPostComments($id) as $c) {
        $cName = htmlspecialchars($c['name']);
        $desc .= '<blockquote><b>' . $cName . '</b><p>' . $c['comment'] . '</p></blockquote>';
    }
?>    <item>
        <title><?= htmlspecialchars($row['title'] ?? '') ?></title>
        <link><?= htmlspecialchars($link) ?></link>
        <guid><?= htmlspecialchars($link) ?></guid>
        <pubDate><?= $pubDate ?></pubDate>
        <description><?= htmlspecialchars($desc) ?></description>
    </item>
<?php endforeach ?>
</channel>
</rss>
