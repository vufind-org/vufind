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
       journal_title VARCHAR(256) NOT NULL,
       journal_author VARCHAR(256) NOT NULL,
       journal_year VARCHAR(32) NOT NULL,
       journal_control_number VARCHAR(256) NOT NULL,
       max_last_modification_time DATETIME NOT NULL,
       FOREIGN KEY (id) REFERENCES user(id),
       PRIMARY KEY (id,journal_control_number)
);

CREATE TABLE ixtheo_pda_subscriptions (
       id INT(11) NOT NULL,
       book_title VARCHAR(256) NOT NULL,
       book_author VARCHAR(256) NOT NULL,
       book_year VARCHAR(32) NOT NULL,
       book_ppn VARCHAR(10) NOT NULL,
       book_isbn VARCHAR(13) NOT NULL,
       FOREIGN KEY (id) REFERENCES user(id),
       PRIMARY KEY (id, book_ppn)
);

CREATE TABLE ixtheo_user (
       id INT(11) NOT NULL,
       user_type ENUM('ixtheo', 'relbib') DEFAULT 'ixtheo',
       appellation VARCHAR(64),
       title VARCHAR(64),
       institution VARCHAR(256),
       country VARCHAR(256),
       language VARCHAR(20),
       can_use_tad BOOLEAN DEFAULT FALSE,
       FOREIGN KEY (id) REFERENCES user(id),
       PRIMARY KEY (id)
);
