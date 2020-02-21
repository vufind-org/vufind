--
-- Table structure for table auth_hash
--

CREATE TABLE auth_hash (
id BIGSERIAL,
session_id varchar(128),
hash varchar(255),
type varchar(50),
data text,
created timestamp NOT NULL default '1970-01-01 00:00:00',
PRIMARY KEY (id),
UNIQUE (hash, type)
);
CREATE INDEX auth_hash_created_idx on auth_hash(created);
