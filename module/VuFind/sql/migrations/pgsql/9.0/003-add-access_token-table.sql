CREATE TABLE access_token (
id varchar(255) NOT NULL,
type varchar(128) NOT NULL,
user_id int DEFAULT NULL,
created timestamp NOT NULL default '1970-01-01 00:00:00',
data text,
revoked boolean NOT NULL DEFAULT '0',
PRIMARY KEY (id, type),
CONSTRAINT access_token_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
);
