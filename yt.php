<?php

$channelId = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['channel'] ?? '');
if (!$channelId) {
    http_response_code(400);
    exit('channel parameter required');
}

$cacheFile = sys_get_temp_dir() . '/yt_' . $channelId . '.xml';
$cacheTTL  = 1800;

if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $cacheTTL) {
    $raw = file_get_contents($cacheFile);
} else {
    $ch = curl_init('https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode($channelId));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$raw || $code !== 200) { http_response_code(502); exit('failed to fetch YouTube feed'); }
    file_put_contents($cacheFile, $raw);
}

header('Content-Type: application/rss+xml; charset=utf-8');

libxml_use_internal_errors(true);
$atom = simplexml_load_string($raw);
if (!$atom) { http_response_code(502); exit('failed to parse feed'); }

$NS_YT    = 'http://www.youtube.com/xml/schemas/2015';
$NS_MEDIA = 'http://search.yahoo.com/mrss/';

$channelTitle = (string) $atom->title;
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
<title><?= htmlspecialchars($channelTitle) ?></title>
<link>https://www.youtube.com/channel/<?= htmlspecialchars($channelId) ?></link>
<description><?= htmlspecialchars($channelTitle) ?></description>
<?php foreach ($atom->entry as $entry):
    $videoId = (string) $entry->children($NS_YT)->videoId;
    $title   = (string) $entry->title;
    $pubDate = date('r', strtotime((string) $entry->published));
    $media   = $entry->children($NS_MEDIA)->group;
    $desc    = $media ? htmlspecialchars((string) $media->description) : '';
    $embed   = '<iframe width="560" height="285" src="https://www.youtube.com/embed/' . $videoId . '" frameborder="0" allowfullscreen="allowfullscreen"></iframe>';
?>
<item>
<title><?= htmlspecialchars($title) ?></title>
<link>https://www.youtube.com/watch?v=<?= htmlspecialchars($videoId) ?></link>
<guid isPermaLink="true">https://www.youtube.com/watch?v=<?= htmlspecialchars($videoId) ?></guid>
<pubDate><?= $pubDate ?></pubDate>
<description><![CDATA[<?= $desc ? '<p>' . nl2br($desc) . '</p>' : '' ?>]]></description>
<content:encoded><![CDATA[<?= $embed ?><?= $desc ? '<p>' . nl2br($desc) . '</p>' : '' ?>]]></content:encoded>
</item>
<?php endforeach ?>
</channel>
</rss>
