-- discovery zone schema
-- sqlite auto-creates share.db; aggregator.migrate() runs these on first boot

CREATE TABLE IF NOT EXISTS posts (
    id    INTEGER PRIMARY KEY NOT NULL,
    date  REAL    DEFAULT (datetime('now','localtime')),
    title TEXT    NOT NULL,
    file  TEXT    NULL,   -- legacy: original file extension (pre-blob storage)
    url   TEXT    NULL,
    image BLOB    NULL,   -- binary image data
    mime  TEXT    NULL
);

CREATE TABLE IF NOT EXISTS comments (
    id      INTEGER PRIMARY KEY NOT NULL,
    post    INTEGER NOT NULL,
    date    REAL    DEFAULT (datetime('now','localtime')),
    name    TEXT    NOT NULL,
    comment TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS url_cache (
    url       TEXT    PRIMARY KEY NOT NULL,
    thumbnail TEXT    NULL,   -- og:image or oembed thumbnail url
    title     TEXT    NULL,   -- og:title or oembed title
    fetched   INTEGER NOT NULL DEFAULT 0  -- unix timestamp of last fetch
);
