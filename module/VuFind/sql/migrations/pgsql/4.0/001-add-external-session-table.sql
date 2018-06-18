--
-- Table structure for table external_session
--

CREATE TABLE external_session (
id SERIAL,
session_id varchar(128) NOT NULL,
external_session_id varchar(255) NOT NULL,
created timestamp NOT NULL default '1970-01-01 00:00:00',
PRIMARY KEY (id),
UNIQUE (session_id)
);
CREATE INDEX external_session_id on external_session(external_session_id);
