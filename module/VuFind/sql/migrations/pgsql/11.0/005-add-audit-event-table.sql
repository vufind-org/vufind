CREATE TABLE audit_event (
  id SERIAL,
  date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  type varchar(50) NOT NULL,
  subtype varchar(50) NOT NULL,
  user_id int,
  session_id varchar(128),
  username varchar(255),
  client_ip varchar(255),
  server_ip varchar(255),
  server_name varchar(255),
  message  varchar(255),
  data json,
  PRIMARY KEY (id)
);
CREATE INDEX audit_event_user_id_idx ON audit_event (user_id);

ALTER TABLE audit_event
ADD CONSTRAINT audit_event_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL;
