--
-- Table structure for table `user_card`
--

DROP TABLE IF EXISTS "user_card";

CREATE TABLE "user_card" (
id SERIAL,
user_id int NOT NULL,
card_name varchar(255) NOT NULL DEFAULT '',
cat_username varchar(50) NOT NULL DEFAULT '',
cat_password varchar(50) DEFAULT NULL,
cat_pass_enc varchar(110) DEFAULT NULL,
home_library varchar(100) NOT NULL DEFAULT '',
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
saved timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
CONSTRAINT user_card_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
);
CREATE INDEX user_card_cat_username_idx ON user_card (cat_username);
CREATE INDEX user_card_user_id_idx ON user_card (user_id);
