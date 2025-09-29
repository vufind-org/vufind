CREATE TABLE log_table (
  id SERIAL,
  logtime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ident char(16) NOT NULL DEFAULT '',
  priority int NOT NULL DEFAULT '0',
  message text,
  PRIMARY KEY (id)
);
