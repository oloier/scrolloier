<h1>test:</h1>
<?php require_once("class.aggregator.php");

$postUrlCheck = 'http://i.imgur.com/I41hSG6.gifv';
// $test = getUrlData($embedlyUrl . 'http://i.imgur.com/I41hSG6.gifv');

echo embed('');

$postUrl = embed($postUrlCheck); // , $postTitle;
if (is_array($postUrl)) {
    $embedHTML = stripcslashes($postUrl['html']);
    $thumbnail = '<img src="'. $postUrl['thumbnail_url'] .'" alt="" data-embed="'. $embedHTML .'"/><a>▶</a>';
} else {}
?>