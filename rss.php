<?php header("Content-type: text/xml"); 

require_once("./class.rssembed.php");
require_once("./class.aggregator.php");

# throw down the rss headers
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <rss version='2.0' xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:media=\"http://search.yahoo.com/mrss/\" xmlns:atom=\"http://www.w3.org/2005/Atom\"> 
    <channel> 
        <title>share.ly</title> 
        <link></link>
        <description>Aggregator Feed</description> 
        <language>en-us</language>\n";

$agg = new aggregator();
$allRows = $agg->getAllPosts();

foreach ($allRows as $item) {

    $postid = $item['id'];
    $title = $item['title'];
    $file = $item['file'];
    $url = $item['url'];
    $pubDate = date(DATE_RSS, strtotime($item['date']));
    $content = null;
    $comments = $agg->getPostComments($postid);
    
    echo "<item>\n";
    echo "<title>$title</title>\n";
    echo "<link>http://oloier.com/share/post/$postid</link>\n";
    if (!empty($url)) {
        $em = new embed($url);
        $content = $em->embed();
    } elseif (!empty($file)) {
        $content = "<img src=\"http://oloier.com/share/assets/uploads/$postid.$file\" />";
    }
    
    foreach ($comments as $comment) {
        $name = $comment['name'];
        $cmmnt = $comment['comment'];
        $date = $comment['date'];
        if (!empty($date)) {
            $pubDate = date(DATE_RSS, strtotime($comment['date']));
        }
        $content .= "
            <blockquote>
                <b>$name</b>
                <div>$cmmnt</div>
            </blockquote>
        ";
    }
    
    echo "<pubDate>$pubDate</pubDate>\n";    
    echo '<description>'. htmlspecialchars($content) ."</description>\n";
    echo "</item>\n";
}


function pr($data){
    echo "<pre>"; print_r($data); // or var_dump($data);
    echo "</pre>";
}

echo "</channel></rss>"; 

?>