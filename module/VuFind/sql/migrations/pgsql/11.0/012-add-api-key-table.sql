CREATE TABLE api_key (
  id SERIAL,
  user_id int NOT NULL,
  title varchar(255) NOT NULL,
  token varchar(255) NOT NULL,
  revoked boolean NOT NULL DEFAULT '0',
  created timestamp NOT NULL default CURRENT_TIMESTAMP,
  last_used timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);
CREATE INDEX api_key_user_id_idx ON api_key (user_id);
CREATE INDEX api_key_token_idx ON api_key (token);
ALTER TABLE api_key
ADD CONSTRAINT api_key_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;
