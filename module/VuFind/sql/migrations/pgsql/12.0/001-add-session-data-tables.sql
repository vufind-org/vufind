CREATE TABLE session_data (
  id BIGSERIAL,
  session_id varchar(128) NOT NULL,
  type varchar(50) NOT NULL,
  data JSON NOT NULL,
  PRIMARY KEY (id)
);
CREATE INDEX session_id_idx ON session_data (session_id);
CREATE UNIQUE INDEX session_data_session_id_type_index ON session_data (session_id, type);
