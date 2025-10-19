CREATE TABLE api_key (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  user_id int(11) DEFAULT NULL,
  title varchar(255) NOT NULL,
  token varchar(255) NOT NULL,
  revoked tinyint(1) NOT NULL DEFAULT '0',
  created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  last_used timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (id),
);
CREATE INDEX api_key_user_id_idx ON api_key (user_id);
CREATE INDEX api_key_token_idx ON api_key (token);
ADD CONSTRAINT api_key_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE,
