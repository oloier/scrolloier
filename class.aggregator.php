<?php

require_once("class.db.php");

define('APP_PATH',    '/share/');
define('ASSETS_PATH', APP_PATH . 'assets/');
define('FILES_LIMIT',   4000000);
define('FILES_ALLOWED', serialize(['jpg', 'png', 'gif', 'svg', 'jpeg', 'mp3', 'm4a', 'wav']));
define('POST_LIMIT', 500);
define('DELETE_TOKEN', 'scroll-del-7f3a9c');
define('USERS', ['scott', 'mike']);

class aggregator
{
    public $postCount;
    private $db, $dbc;
    private $page, $user;

    function __construct($pageNumber = 1, $user = null)
    {
        $this->db   = new db('share.db');
        $this->dbc  = $this->db->db;
        $this->user = (is_string($user) && in_array($user, USERS)) ? $user : null;
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
        if (!in_array('user', $cols)) {
            $this->dbc->exec("ALTER TABLE posts ADD COLUMN user TEXT");
        }
        if (!in_array('bumped', $cols)) {
            $this->dbc->exec("ALTER TABLE posts ADD COLUMN bumped REAL");
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
        $comment = self::parseMarkdown($postData['comment'] ?? '');
        $stmt = $this->dbc->prepare("INSERT INTO comments (post, name, comment) VALUES (?, ?, ?)");
        $stmt->execute([$postid, $name, $comment]);
        $this->dbc->prepare("UPDATE posts SET bumped=datetime('now','localtime') WHERE id=?")->execute([$postid]);
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
            $user    = isset($postData['user']) && in_array($postData['user'], USERS) ? $postData['user'] : null;
            $mimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                        'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp',
                        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav'];
            if ($haveUpload) {
                $mime = $mimeMap[$fileExt] ?? 'application/octet-stream';
                $data = file_get_contents($fileTmp);
                $stmt = $this->dbc->prepare("INSERT INTO posts (title, mime, image, url, user) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $mime, $data, '', $user]);
            } else {
                if (preg_match('/https?:\/\/(?:i\.)?imgur\.com\/([a-zA-Z0-9]+)\.gifv/i', $url, $m)) {
                    $data = self::fetchUrl('https://i.imgur.com/' . $m[1] . '.mp4');
                    if ($data) {
                        $stmt = $this->dbc->prepare("INSERT INTO posts (title, mime, image, url, user) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$title, 'video/mp4', $data, '', $user]);
                    } else {
                        $stmt = $this->dbc->prepare("INSERT INTO posts (title, url, user) VALUES (?, ?, ?)");
                        $stmt->execute([$title, $url, $user]);
                    }
                } else {
                    $ext  = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    $mime = $mimeMap[$ext] ?? null;
                    if ($mime) {
                        $data = self::fetchUrl($url);
                    } else {
                        [$data, $mime] = self::fetchUrlWithMime($url);
                        $allowedMimes = array_values($mimeMap);
                        if (!in_array($mime, $allowedMimes, true)) {
                            $data = null;
                            $mime = null;
                        }
                    }
                    if ($mime && $data) {
                        $stmt = $this->dbc->prepare("INSERT INTO posts (title, mime, image, url, user) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$title, $mime, $data, '', $user]);
                    } else {
                        $stmt = $this->dbc->prepare("INSERT INTO posts (title, url, user) VALUES (?, ?, ?)");
                        $stmt->execute([$title, $url, $user]);
                    }
                }
            }
            return (int) $this->dbc->lastInsertId();
        }
        return 0;
    }

    public function renderPost($id)
    {
        $stmt = $this->dbc->prepare('SELECT id, title, file, url, date, mime, user, (image IS NOT NULL) as has_image FROM posts WHERE id=?');
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch();
        return $row ? $this->renderPostMarkup($row) : '';
    }

    private function getTotalPosts()
    {
        if ($this->user) {
            $stmt = $this->dbc->prepare("SELECT COUNT(*) FROM posts WHERE user=?");
            $stmt->execute([$this->user]);
        } else {
            $stmt = $this->dbc->prepare("SELECT COUNT(*) FROM posts WHERE user IS NULL");
            $stmt->execute();
        }
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
        $where = $this->user ? 'WHERE user=?' : '';
        $sql   = "SELECT id, title, file, url, mime, user, bumped, (image IS NOT NULL) as has_image,
                         COALESCE(bumped, date) as date
                  FROM posts $where
                  ORDER BY COALESCE(bumped, date) DESC";
        $stmt  = $this->dbc->prepare($sql);
        $stmt->execute($this->user ? [$this->user] : []);
        return $stmt->fetchAll();
    }

    public function getAllOrderedPosts($postid = null)
    {
        $single = isset($postid);
        if (!$single) {
            $offset    = POST_LIMIT * ($this->page - 1);
            $sqlOffset = 'LIMIT ' . POST_LIMIT . ($this->page > 1 ? ' OFFSET ' . $offset : '');
            $where     = $this->user ? 'WHERE user=?' : 'WHERE user IS NULL';
            $sql       = "SELECT id, title, file, url, mime, user, (image IS NOT NULL) as has_image,
                                 COALESCE(bumped, date) as date
                          FROM posts $where
                          ORDER BY COALESCE(bumped, date) DESC $sqlOffset";
            $stmt = $this->dbc->prepare($sql);
            $stmt->execute($this->user ? [$this->user] : []);
        } else {
            $stmt = $this->dbc->prepare('SELECT id, title, file, url, date, mime, user, (image IS NOT NULL) as has_image FROM posts WHERE id=?');
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

        $base = $this->user ? APP_PATH . $this->user : APP_PATH;
        if ($this->page > 1) {
            $prev = $this->page - 1;
            echo '<a class="page-prev" href="' . $base . '?page=' . $prev . '">&laquo; Prev</a>';
        }
        foreach (range(1, $pageCount) as $num) {
            $active = $num == $this->page ? ' class="active"' : '';
            echo '<a href="' . $base . '?page=' . $num . '"' . $active . '>' . $num . '</a>';
        }
        if ($this->page < $pageCount) {
            $next = $this->page + 1;
            echo '<a class="page-next" href="' . $base . '?page=' . $next . '">Next &raquo;</a>';
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


        $url  = $row['url'] ?? '';
        $safe = htmlspecialchars($url, ENT_QUOTES);

        $hasLocalMedia = !empty($row['file']) || !empty($row['has_image']);
        if ($hasLocalMedia) {
            $src  = APP_PATH . 'img/' . $rowid;
            $mime = $row['mime'] ?? '';
            if (strpos($mime, 'video/') === 0) {
                $embedDirect = '<video autoplay controls loop muted playsinline><source src="' . $src . '" type="' . htmlspecialchars($mime) . '"></video>';
            } elseif (strpos($mime, 'audio/') === 0) {
                $embedDirect = '<audio controls><source src="' . $src . '" type="' . htmlspecialchars($mime) . '"></audio>';
            } else {
                $postImg = '<a href="' . $src . '" rel="lightbox">'
                         . '<img src="' . $src . '" alt="' . $postTitle . '" loading="lazy" />'
                         . '</a>';
            }
        } elseif (!empty($url)) {
            if (preg_match('/https?:\/\/\S+\.(?:png|jpg|jpeg|gif|svg|webp)(\?[^\s]*)?$/i', $url)) {
                $postImg = '<a class="loader" href="' . $safe . '" rel="lightbox"><img src="' . $safe . '" alt="" loading="lazy" /></a>';
            } elseif (preg_match('/https?:\/\/\S+\.(?:mp4|webm|ogg)(\?[^\s]*)?$/i', $url)) {
                $embedDirect = '<video autoplay controls loop muted playsinline><source src="' . $safe . '"></video>';
            } elseif (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/|yt\.php\?v=)([a-zA-Z0-9_-]{11})/', $url, $m)) {
                $id    = $m[1];
                $embed = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $id . '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
                $thumb = 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
                $thumbnail = '<img src="' . $thumb . '" alt="" data-embed="' . htmlspecialchars($embed, ENT_QUOTES) . '" loading="lazy" /><a></a>';
            } elseif (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
                $id    = $m[1];
                $embed = '<iframe src="https://player.vimeo.com/video/' . $id . '?autoplay=1" width="640" height="360" frameborder="0" allowfullscreen></iframe>';
                $meta  = $this->getUrlMeta($url);
                if (!empty($meta['thumbnail'])) {
                    $thumb = htmlspecialchars($meta['thumbnail'], ENT_QUOTES);
                    $thumbnail = '<img src="' . $thumb . '" alt="" data-embed="' . htmlspecialchars($embed, ENT_QUOTES) . '" loading="lazy" /><a></a>';
                } else {
                    $embedDirect = '<a href="' . $safe . '" class="regular"> Vimeo</a>';
                }
            } elseif (preg_match('/https?:\/\/(?:i\.)?imgur\.com\/([a-zA-Z0-9]+)\.gifv/i', $url, $m)) {
                $mp4 = htmlspecialchars('https://i.imgur.com/' . $m[1] . '.mp4', ENT_QUOTES);
                $embedDirect = '<video autoplay controls loop muted playsinline><source src="' . $mp4 . '" type="video/mp4"></video>';
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

            foreach ($postComments as $comment) {
                $cName = htmlspecialchars($comment['name']);
                $cText = $comment['comment'];
                $comments .= "<li>" . ($cName !== '' ? "<b>$cName</b>" : '') . "$cText</li>";
            }
        }

        $postUser = htmlspecialchars($row['user'] ?? '');
        $userHtml = $postUser ? " &middot; <span class=\"post-user\">$postUser</span>" : '';

        return "
            <article class=\"item\">
                <figure>
                    $thumbnail $embedDirect $postImg
                </figure>
                <div>
                    <h2><a href=\"" . APP_PATH . "post/$rowid\">$titleHtml</a></h2>
                    <details" . ($openComments ? ' open' : '') . ">
                        <summary>" . ($commentsCount === 0 ? 'add comment' : "<var>$commentsCount</var> comments") . "</summary>
                        <ul>$comments</ul>
                        <form method=\"post\" action=\"" . APP_PATH . "\">
                            <input type=\"text\" name=\"name\" placeholder=\"name\" maxlength=\"100\" />
                            <textarea name=\"comment\" placeholder=\"comment\" maxlength=\"1000\" required></textarea>
                            <input type=\"hidden\" name=\"submittedComment\" value=\"$rowid\" />
                            <button type=\"submit\">comment</button>
                        </form>
                    </details>
                    <footer><time datetime=\"$postDateISO\">$postDate</time>$userHtml</footer>
                </div>
            </article>";
    }
    private static function fetchUrl($url)
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

    private static function fetchUrlWithMime($url)
    {
        $curl = curl_init($url);
        if (!$curl) return [null, null];
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $data = curl_exec($curl);
        $err  = curl_errno($curl);
        $mime = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        curl_close($curl);
        if ($err || !$data) return [null, null];
        $mime = strtolower(preg_replace('/;.*$/', '', $mime ?? ''));
        return [$data, $mime ?: null];
    }

    private static function parseMarkdown($text)
    {
        $text = htmlspecialchars($text, ENT_QUOTES);
        $text = preg_replace('/\*(.+?)\*/s', '<strong>$1</strong>', $text);
        $text = preg_replace('/_(.+?)_/s', '<em>$1</em>', $text);
        $text = preg_replace('/^- (.+)/m', '<li>$1</li>', $text);
        $text = preg_replace('/((?:<li>[^\n]*\n?)+)/', '<ul>$1</ul>', $text);
        return nl2br($text);
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
            $json = self::fetchUrl('https://soundcloud.com/oembed?format=json&url=' . urlencode($url));
            if ($json && ($data = json_decode($json, true))) {
                $thumbnail = $data['thumbnail_url'] ?? null;
                $title     = $data['title'] ?? null;
            }
        } else {
            $html = self::fetchUrl($url);
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
