--
-- Table structure for table `login_token`
--

DROP TABLE IF EXISTS "login_token";

CREATE TABLE login_token (
  id SERIAL,
  user_id int NOT NULL,
  token varchar(255) NOT NULL,
  series varchar(255) NOT NULL,
  last_login timestamp NOT NULL,
  browser varchar(255),
  platform varchar(255),
  expires int NOT NULL,
  last_session_id varchar(255),
  PRIMARY KEY (id)
);
CREATE INDEX login_token_user_id_series_idx ON login_token (user_id, series);
