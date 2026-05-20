CREATE TABLE posts (
  id      INTEGER   PRIMARY KEY  NOT NULL,
  date    REAL      DEFAULT (datetime('now', 'localtime')),
  title   TEXT      NOT NULL,
  file    TEXT      NULL,
  url     TEXT      NULL
);


CREATE TABLE comments (
  id      INTEGER   PRIMARY KEY  NOT NULL,
  post    INTEGER   NOT NULL,
  date    REAL      DEFAULT (datetime('now', 'localtime')),
  name    TEXT      NOT NULL,
  comment TEXT      NOT NULL
);

-- INSERT INTO comments ('post', 'name', 'comment') VALUES ('1', 'axel foley', 'IM THE BEVERLY HILLS FIVE OH MOTHA FUCKA');

-- INSERT INTO comments ('post', 'name', 'comment') VALUES ('1', 'Jaime Lannister', 'HEY CERSEI GET A SMALLER BOX FOR ALL THAT STANK');