<?php

require_once("class.db.php");

define('APP_PATH',    '/share/');
define('ASSETS_PATH', APP_PATH . 'assets/');
define('FILES_LIMIT',   4000000);
define('FILES_ALLOWED', serialize(['jpg', 'png', 'gif', 'svg', 'jpeg']));
define('POST_LIMIT', 500);
define('DELETE_TOKEN', 'scroll-del-7f3a9c');

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

    public function deletePost($id)
    {
        $this->dbc->prepare("DELETE FROM posts WHERE id=?")->execute([$id]);
        $this->dbc->prepare("DELETE FROM comments WHERE post=?")->execute([$id]);
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
            $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                        'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp'];
            if ($haveUpload) {
                $mime = $mimeMap[$fileExt] ?? 'application/octet-stream';
                $data = file_get_contents($fileTmp);
                $stmt = $this->dbc->prepare("INSERT INTO posts (title, mime, image, url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $mime, $data, '']);
            } else {
                if (preg_match('/https?:\/\/(?:i\.)?imgur\.com\/([a-zA-Z0-9]+)\.gifv/i', $url, $m)) {
                    $data = fetchUrl('https://i.imgur.com/' . $m[1] . '.mp4');
                    if ($data) {
                        $stmt = $this->dbc->prepare("INSERT INTO posts (title, mime, image, url) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$title, 'video/mp4', $data, '']);
                    } else {
                        $stmt = $this->dbc->prepare("INSERT INTO posts (title, url) VALUES (?, ?)");
                        $stmt->execute([$title, $url]);
                    }
                } else {
                    $ext  = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    $mime = $mimeMap[$ext] ?? null;
                    if ($mime && ($data = fetchUrl($url))) {
                        $stmt = $this->dbc->prepare("INSERT INTO posts (title, mime, image, url) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$title, $mime, $data, '']);
                    } else {
                        $stmt = $this->dbc->prepare("INSERT INTO posts (title, url) VALUES (?, ?)");
                        $stmt->execute([$title, $url]);
                    }
                }
            }
            return (int) $this->dbc->lastInsertId();
        }
        return 0;
    }

    public function renderPost($id)
    {
        $stmt = $this->dbc->prepare('SELECT id, title, file, url, date, mime, (image IS NOT NULL) as has_image FROM posts WHERE id=?');
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch();
        return $row ? $this->renderPostMarkup($row) : '';
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
        $sql = 'SELECT posts.id, posts.date, posts.title, posts.file, posts.url, posts.mime, (posts.image IS NOT NULL) as has_image
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
        $single = isset($postid);
        if (!$single) {
            $offset    = POST_LIMIT * ($this->page - 1);
            $sqlOffset = 'LIMIT ' . POST_LIMIT . ($this->page > 1 ? ' OFFSET ' . $offset : '');

            $sql = 'SELECT posts.id, posts.date, posts.title, posts.file, posts.url, posts.mime, (posts.image IS NOT NULL) as has_image
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
            $stmt = $this->dbc->prepare('SELECT id, title, file, url, date, mime, (image IS NOT NULL) as has_image FROM posts WHERE id=?');
            $stmt->execute([(int) $postid]);
        }

        while ($row = $stmt->fetch()) {
            echo $this->renderPostMarkup($row, $single);
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

    private function renderPostMarkup($row, $openComments = false)
    {
        $rowid     = (int) $row['id'];
        $rawTitle  = $row['title'] ?? '';
        $postTitle = htmlspecialchars($rawTitle);
        $titleHtml = $postTitle !== '' ? $postTitle : '<span class="untitled">untitled</span>';
        $postDate  = $row['date'] ?? '';
        $postDateISO = $postDate ? str_replace(' ', 'T', $postDate) : '';
        $postImg   = '';
        $thumbnail = '';
        $embedDirect = '';
        $commentClass = '';

        $url  = $row['url'] ?? '';
        $safe = htmlspecialchars($url, ENT_QUOTES);

        $hasLocalMedia = !empty($row['file']) || !empty($row['has_image']);
        if ($hasLocalMedia) {
            $src  = APP_PATH . 'img.php?id=' . $rowid;
            $mime = $row['mime'] ?? '';
            if (strpos($mime, 'video/') === 0) {
                $embedDirect = '<video controls loop playsinline><source src="' . $src . '" type="' . htmlspecialchars($mime) . '"></video>';
            } else {
                $postImg = '<a href="' . $src . '" rel="lightbox">'
                         . '<img src="' . $src . '" alt="' . $postTitle . '" loading="lazy" />'
                         . '</a>';
            }
        } elseif (!empty($url)) {
            if (preg_match('/https?:\/\/\S+\.(?:png|jpg|jpeg|gif|svg|webp)(\?[^\s]*)?$/i', $url)) {
                $postImg = '<a class="loader" href="' . $safe . '" rel="lightbox"><img src="' . $safe . '" alt="" loading="lazy" /></a>';
            } elseif (preg_match('/https?:\/\/\S+\.(?:mp4|webm|ogg)(\?[^\s]*)?$/i', $url)) {
                $embedDirect = '<video controls><source src="' . $safe . '"></video>';
            } elseif (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
                $id    = $m[1];
                $embed = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $id . '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
                $thumb = 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
                $thumbnail = '<img src="' . $thumb . '" alt="" data-embed="' . htmlspecialchars($embed, ENT_QUOTES) . '" loading="lazy" /><a>&#9654;</a>';
            } elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
                $id    = $m[1];
                $embed = '<iframe src="https://player.vimeo.com/video/' . $id . '?autoplay=1" width="640" height="360" frameborder="0" allowfullscreen></iframe>';
                $meta  = $this->getUrlMeta($url);
                if (!empty($meta['thumbnail'])) {
                    $thumb = htmlspecialchars($meta['thumbnail'], ENT_QUOTES);
                    $thumbnail = '<img src="' . $thumb . '" alt="" data-embed="' . htmlspecialchars($embed, ENT_QUOTES) . '" loading="lazy" /><a>&#9654;</a>';
                } else {
                    $embedDirect = '<a href="' . $safe . '" class="regular">&#9654; Vimeo</a>';
                }
            } elseif (preg_match('/https?:\/\/(?:i\.)?imgur\.com\/([a-zA-Z0-9]+)\.gifv/i', $url, $m)) {
                $mp4 = htmlspecialchars('https://i.imgur.com/' . $m[1] . '.mp4', ENT_QUOTES);
                $embedDirect = '<video controls loop playsinline><source src="' . $mp4 . '" type="video/mp4"></video>';
            } else {
                $meta = $this->getUrlMeta($url);
                if (!empty($meta['thumbnail'])) {
                    $thumb = htmlspecialchars($meta['thumbnail'], ENT_QUOTES);
                    $thumbnail = '<a href="' . $safe . '" target="_blank" rel="noopener"><img src="' . $thumb . '" alt="" loading="lazy" /></a>';
                } else {
                    $embedDirect = '<a href="' . $safe . '" class="regular">' . htmlspecialchars($url) . '</a>';
                }
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
                    <h3><a href=\"" . APP_PATH . "?post=$rowid\">$titleHtml</a></h3>
                    <details" . ($openComments ? ' open' : '') . ">
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
    private function getUrlMeta($url)
    {
        $stmt = $this->dbc->prepare("SELECT thumbnail, title, fetched FROM url_cache WHERE url = ?");
        $stmt->execute([$url]);
        $cached = $stmt->fetch();
        if ($cached && $cached['fetched'] > time() - 604800) {
            return $cached;
        }

        $thumbnail = null;
        $title     = null;

        if (strpos($url, 'soundcloud.com') !== false) {
            $json = fetchUrl('https://soundcloud.com/oembed?format=json&url=' . urlencode($url));
            if ($json && ($data = json_decode($json, true))) {
                $thumbnail = $data['thumbnail_url'] ?? null;
                $title     = $data['title'] ?? null;
            }
        } else {
            $html = fetchUrl($url);
            if ($html) {
                foreach ([
                    '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
                    '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
                ] as $re) {
                    if (preg_match($re, $html, $m)) { $thumbnail = $m[1]; break; }
                }
                foreach ([
                    '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i',
                    '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']/i',
                ] as $re) {
                    if (preg_match($re, $html, $m)) { $title = html_entity_decode($m[1], ENT_QUOTES); break; }
                }
            }
        }

        $stmt = $this->dbc->prepare("INSERT OR REPLACE INTO url_cache (url, thumbnail, title, fetched) VALUES (?, ?, ?, ?)");
        $stmt->execute([$url, $thumbnail, $title, time()]);
        return ['thumbnail' => $thumbnail, 'title' => $title];
    }
}

function fetchUrl($url)
{
    $curl = curl_init($url);
    if (!$curl) return false;
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $data = curl_exec($curl);
    $err  = curl_errno($curl);
    curl_close($curl);
    return $err ? false : $data;
}

function parseMarkdown($text)
{
    $text = htmlspecialchars($text, ENT_QUOTES);
    $text = preg_replace('/\*(.+?)\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/_(.+?)_/s', '<em>$1</em>', $text);
    $text = preg_replace('/^- (.+)/m', '<li>$1</li>', $text);
    $text = preg_replace('/((?:<li>[^\n]*\n?)+)/', '<ul>$1</ul>', $text);
    return nl2br($text);
}

