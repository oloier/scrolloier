<?php

require_once("class.db.php");

define('APP_PATH',    '/share/');
define('ASSETS_PATH', APP_PATH . 'assets/');
define('FILES_LIMIT',   4000000);
define('FILES_ALLOWED', serialize(['jpg', 'png', 'gif', 'svg', 'jpeg']));
define('POST_LIMIT', 500);

class aggregator
{
    public $postCount;
    private $db, $dbc;
    private $page;

    function __construct($pageNumber = 1)
    {
        $this->db  = new db('share.db');
        $this->dbc = $this->db->db;
        $this->migrate();
        if (empty($pageNumber) || $pageNumber < 1) $pageNumber = 1;
        $this->page = (int) $pageNumber;
        $this->postCount = $this->getTotalPosts();
    }

    private function migrate()
    {
        $this->dbc->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id    INTEGER PRIMARY KEY NOT NULL,
                date  REAL    DEFAULT (datetime('now','localtime')),
                title TEXT    NOT NULL,
                file  TEXT    NULL,
                url   TEXT    NULL,
                image BLOB    NULL,
                mime  TEXT    NULL
            )
        ");
        $this->dbc->exec("
            CREATE TABLE IF NOT EXISTS comments (
                id      INTEGER PRIMARY KEY NOT NULL,
                post    INTEGER NOT NULL,
                date    REAL    DEFAULT (datetime('now','localtime')),
                name    TEXT    NOT NULL,
                comment TEXT    NOT NULL
            )
        ");
        $this->dbc->exec("
            CREATE TABLE IF NOT EXISTS url_cache (
                url       TEXT    PRIMARY KEY NOT NULL,
                thumbnail TEXT    NULL,
                title     TEXT    NULL,
                fetched   INTEGER NOT NULL DEFAULT 0
            )
        ");
        $cols = array_column(
            $this->dbc->query("PRAGMA table_info(posts)")->fetchAll(),
            'name'
        );
        if (!in_array('image', $cols)) {
            $this->dbc->exec("ALTER TABLE posts ADD COLUMN image BLOB");
            $this->dbc->exec("ALTER TABLE posts ADD COLUMN mime TEXT");
        }
    }

    public function saveComment($postData)
    {
        $postid  = (int) $postData['submittedComment'];
        $name    = htmlspecialchars($postData['name'] ?? '');
        $comment = parseMarkdown($postData['comment'] ?? '');
        $stmt = $this->dbc->prepare("INSERT INTO comments (post, name, comment) VALUES (?, ?, ?)");
        $stmt->execute([$postid, $name, $comment]);
        return ['name' => $name, 'comment' => $comment];
    }

    public function savePost($postData, $filesArray = [])
    {
        $gameOn = true;
        $title  = $postData['title'] ?? '';
        $url    = '';

        $haveUpload = is_uploaded_file($filesArray['file']['tmp_name'] ?? '');
        if (!$haveUpload) {
            $url = $postData['url'] ?? '';
            if (empty($url)) {
                $gameOn = false;
            }
        } else {
            $fileName = $filesArray['file']['name'];
            $fileTmp  = $filesArray['file']['tmp_name'];
            $fileSize = $filesArray['file']['size'];
            $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed  = unserialize(FILES_ALLOWED);
            if ($fileSize > FILES_LIMIT) {
                $this->db->report('Filesize exceeds the allowed 4MB.');
                $gameOn = false;
            }
            if (!in_array($fileExt, $allowed)) {
                $this->db->report('File type not allowed.');
                $gameOn = false;
            }
        }

        if ($gameOn) {
            if ($haveUpload) {
                $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                            'gif' => 'image/gif', 'svg' => 'image/svg+xml'];
                $mime = $mimeMap[$fileExt] ?? 'application/octet-stream';
                $data = file_get_contents($fileTmp);
                $stmt = $this->dbc->prepare("INSERT INTO posts (title, mime, image, url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $mime, $data, '']);
            } else {
                $stmt = $this->dbc->prepare("INSERT INTO posts (title, url) VALUES (?, ?)");
                $stmt->execute([$title, $url]);
            }
        }
    }

    private function getTotalPosts()
    {
        $stmt = $this->dbc->prepare("SELECT COUNT(*) FROM posts");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function getPostComments($postid)
    {
        $stmt = $this->dbc->prepare("SELECT id, post, name, comment, date FROM comments WHERE post=?");
        $stmt->execute([(int) $postid]);
        return $stmt->fetchAll();
    }

    public function getAllPosts()
    {
        $sql = 'SELECT posts.id, posts.date, posts.title, posts.file, posts.url, (posts.image IS NOT NULL) as has_image
                FROM posts
                LEFT JOIN comments ON comments.post = posts.id
                WHERE comments.id = (
                    SELECT comments.id FROM comments
                    WHERE comments.post = posts.id
                    ORDER BY comments.date DESC, comments.id DESC
                ) OR comments.id IS NULL
                ORDER BY CASE
                    WHEN comments.date > posts.date THEN comments.date
                    ELSE posts.date
                END DESC';
        $stmt = $this->dbc->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllOrderedPosts($postid = null)
    {
        if (!isset($postid)) {
            $offset    = POST_LIMIT * ($this->page - 1);
            $sqlOffset = 'LIMIT ' . POST_LIMIT . ($this->page > 1 ? ' OFFSET ' . $offset : '');

            $sql = 'SELECT posts.id, posts.date, posts.title, posts.file, posts.url, (posts.image IS NOT NULL) as has_image
                    FROM posts
                    LEFT JOIN comments ON comments.post = posts.id
                    WHERE comments.id = (
                        SELECT comments.id FROM comments
                        WHERE comments.post = posts.id
                        ORDER BY comments.date DESC, comments.id DESC
                    ) OR comments.id IS NULL
                    ORDER BY CASE
                        WHEN comments.date > posts.date THEN comments.date
                        ELSE posts.date
                    END DESC ' . $sqlOffset;
            $stmt = $this->dbc->prepare($sql);
            $stmt->execute();
        } else {
            $stmt = $this->dbc->prepare('SELECT id, title, file, url, date, (image IS NOT NULL) as has_image FROM posts WHERE id=?');
            $stmt->execute([(int) $postid]);
        }

        while ($row = $stmt->fetch()) {
            echo $this->renderPostMarkup($row);
        }
    }

    public function renderPagination()
    {
        $pageCount = ceil($this->postCount / POST_LIMIT);
        if ($pageCount <= 1) return;

        if ($this->page > 1) {
            $prev = $this->page - 1;
            echo '<a class="page-prev" href="' . APP_PATH . '?page=' . $prev . '">&laquo; Prev</a>';
        }
        foreach (range(1, $pageCount) as $num) {
            $active = $num == $this->page ? ' class="active"' : '';
            echo '<a href="' . APP_PATH . '?page=' . $num . '"' . $active . '>' . $num . '</a>';
        }
        if ($this->page < $pageCount) {
            $next = $this->page + 1;
            echo '<a class="page-next" href="' . APP_PATH . '?page=' . $next . '">Next &raquo;</a>';
        }
    }

    private function renderPostMarkup($row)
    {
        $rowid     = (int) $row['id'];
        $postTitle = htmlspecialchars($row['title'] ?? '');
        $postDate  = $row['date'] ?? '';
        $postDateISO = $postDate ? str_replace(' ', 'T', $postDate) : '';
        $postImg   = '';
        $thumbnail = '';
        $embedDirect = '';
        $commentClass = '';

        $postUrlCheck = $row['url'] ?? '';
        if ($postUrlCheck && strpos($postUrlCheck, 'youtube') !== false) {
            $postUrlCheck .= '&autoplay=1';
        }

        $hasLocalImage = !empty($row['file']) || !empty($row['has_image']);
        if ($hasLocalImage) {
            $src = APP_PATH . 'img.php?id=' . $rowid;
            $postImg = '<a href="' . $src . '" rel="lightbox">'
                     . '<img src="' . $src . '" alt="' . $postTitle . '" loading="lazy" />'
                     . '</a>';
        } elseif (!empty($postUrlCheck)) {
            $postUrl = embed($postUrlCheck);
            if (is_array($postUrl)) {
                $embedHTML = htmlspecialchars(stripcslashes($postUrl['html']), ENT_QUOTES);
                $thumb     = htmlspecialchars($postUrl['thumbnail_url'] ?? '');
                $thumbnail = '<img src="' . $thumb . '" alt="" data-embed="' . $embedHTML . '" /><a>&#9654;</a>';
            } elseif (!empty($postUrl)) {
                $embedDirect = stripcslashes($postUrl);
            }
        }

        $postComments  = $this->getPostComments($rowid);
        $comments      = '';
        $commentsCount = count($postComments);
        if ($commentsCount > 0) {
            $commentClass = 'class="active"';
            foreach ($postComments as $comment) {
                $cName = htmlspecialchars($comment['name']);
                $cText = $comment['comment'];
                $comments .= "<li><b>$cName</b><p>$cText</p></li>";
            }
        }

        return "
            <dl class=\"item\">
                <dt>
                    <article>
                        $thumbnail $embedDirect $postImg
                    </article>
                </dt>
                <dd>
                    <h3><a href=\"" . APP_PATH . "?post=$rowid\">$postTitle</a></h3>
                    <details>
                        <summary><var $commentClass>$commentsCount</var> comments</summary>
                        <ul>$comments</ul>
                        <form method=\"post\" action=\"" . APP_PATH . "\">
                            <input type=\"text\" name=\"name\" placeholder=\"name\" maxlength=\"100\" required />
                            <textarea name=\"comment\" placeholder=\"comment\" maxlength=\"1000\" required></textarea>
                            <input type=\"hidden\" name=\"submittedComment\" value=\"$rowid\" />
                            <button type=\"submit\">comment</button>
                        </form>
                    </details>
                    <footer><time datetime=\"$postDateISO\">$postDate</time></footer>
                </dd>
            </dl>";
    }
}

function parseMarkdown($text)
{
    $text = htmlspecialchars($text, ENT_QUOTES);
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);
    $text = preg_replace('/^- (.+)/m', '<li>$1</li>', $text);
    $text = preg_replace('/((?:<li>[^\n]*\n?)+)/', '<ul>$1</ul>', $text);
    return nl2br($text);
}

function embed($url)
{
    if (empty($url)) return '';

    if (preg_match('/https?:\/\/\S+\.(?:png|jpg|jpeg|gif|svg)(\?[^\s]*)?$/i', $url)) {
        $safe = htmlspecialchars($url, ENT_QUOTES);
        return '<a class="loader" href="' . $safe . '" rel="lightbox"><img src="' . $safe . '" alt="" loading="lazy" /></a>';
    }

    if (preg_match('/https?:\/\/\S+\.(?:mp4|webm|ogg)(\?[^\s]*)?$/i', $url)) {
        $safe = htmlspecialchars($url, ENT_QUOTES);
        return '<video controls><source src="' . $safe . '"></video>';
    }

    if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        $id    = $m[1];
        $thumb = 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
        $embed = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $id . '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
        return '<img src="' . $thumb . '" alt="" data-embed="' . htmlspecialchars($embed, ENT_QUOTES) . '" loading="lazy" /><a>&#9654;</a>';
    }

    if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
        $safe  = htmlspecialchars($url, ENT_QUOTES);
        return '<a href="' . $safe . '" class="regular">&#9654; Vimeo</a>';
    }

    $safe = htmlspecialchars($url, ENT_QUOTES);
    return '<a href="' . $safe . '" class="regular">' . htmlspecialchars($url) . '</a>';
}
