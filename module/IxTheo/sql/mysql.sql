CREATE TABLE ixtheo_notations (
       id INT(11) NOT NULL,
       ixtheo_notation_list VARCHAR(8192) NOT NULL,
       FOREIGN KEY (id) REFERENCES user(id)
);

CREATE TABLE ixtheo_id_result_sets (
       id INT(11) NOT NULL,
       ids VARCHAR(128) NOT NULL,
       FOREIGN KEY (id) REFERENCES search(id)
);

CREATE TABLE ixtheo_journal_subscriptions (
       id INT(11) NOT NULL,
       journal_control_number VARCHAR(255) NOT NULL,
       max_last_modification_time DATETIME NOT NULL,
       FOREIGN KEY (id) REFERENCES user(id),
       PRIMARY KEY (id,journal_control_number)
) DEFAULT CHARSET=utf8;

CREATE TABLE ixtheo_pda_subscriptions (
       id INT(11) NOT NULL,
       book_title VARCHAR(255) NOT NULL,
       book_author VARCHAR(255) NOT NULL,
       book_year VARCHAR(32) NOT NULL,
       book_ppn VARCHAR(10) NOT NULL,
       book_isbn VARCHAR(13) NOT NULL,
       FOREIGN KEY (id) REFERENCES user(id),
       PRIMARY KEY (id, book_ppn)
) DEFAULT CHARSET=utf8;

CREATE TABLE ixtheo_user (
       id INT(11) NOT NULL,
       user_type ENUM('ixtheo', 'relbib') DEFAULT 'ixtheo',
       appellation VARCHAR(64),
       title VARCHAR(64),
       institution VARCHAR(255),
       country VARCHAR(255),
       language VARCHAR(20),
       can_use_tad BOOLEAN DEFAULT FALSE,
       FOREIGN KEY (id) REFERENCES user(id),
       PRIMARY KEY (id)
) DEFAULT CHARSET=utf8;

CREATE TABLE relbib_ids (
  record_id VARCHAR(10) NOT NULL PRIMARY KEY) DEFAULT CHARSET=utf8mb4;

GRANT DROP ON vufind.relbib_ids TO 'vufind'@'localhost';

CREATE VIEW resource_tags_relbib AS (
  SELECT * FROM resource_tags WHERE resource_id IN
  (SELECT resource.id FROM resource JOIN relbib_ids
   ON resource.record_id = relbib_ids.record_id)
);

CREATE TABLE bibstudies_ids (
  record_id VARCHAR(10) NOT NULL PRIMARY KEY) DEFAULT CHARSET=utf8mb4;

GRANT DROP ON vufind.bibstudies_ids TO 'vufind'@'localhost';

CREATE VIEW resource_tags_bibstudies AS (
  SELECT * FROM resource_tags WHERE resource_id IN
  (SELECT resource.id FROM resource JOIN bibstudies_ids
   ON resource.record_id = bibstudies_ids.record_id)
);

GRANT CREATE TEMPORARY TABLES ON `vufind`.* TO 'vufind'@'localhost';
