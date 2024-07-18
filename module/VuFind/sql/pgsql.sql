--
-- Table structure for table comments
--

DROP TABLE IF EXISTS "comments";

CREATE TABLE comments (
id SERIAL,
user_id int DEFAULT NULL,
resource_id int NOT NULL DEFAULT '0',
comment text NOT NULL,
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
PRIMARY KEY (id)
);
CREATE INDEX comments_user_id_idx ON comments (user_id);
CREATE INDEX comments_resource_id_idx ON comments (resource_id);


-- --------------------------------------------------------

--
-- Table structure for table resource
--

DROP TABLE IF EXISTS "resource";

CREATE TABLE resource (
id SERIAL,
record_id varchar(255) NOT NULL DEFAULT '',
title varchar(255) NOT NULL DEFAULT '',
author varchar(255) DEFAULT NULL,
year int DEFAULT NULL,
source varchar(50) NOT NULL DEFAULT 'Solr',
extra_metadata text DEFAULT NULL,
PRIMARY KEY (id)
);
CREATE INDEX resource_record_id_idx ON resource (record_id);


-- --------------------------------------------------------

--
-- Table structure for table resource_tags
--

DROP TABLE IF EXISTS "resource_tags";

CREATE TABLE resource_tags (
id SERIAL,
resource_id int DEFAULT NULL,
tag_id int NOT NULL DEFAULT '0',
list_id int DEFAULT NULL,
user_id int DEFAULT NULL,
posted timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id)
);
CREATE INDEX resource_tags_user_id_idx ON resource_tags (user_id);
CREATE INDEX resource_tags_resource_id_idx ON resource_tags (resource_id);
CREATE INDEX resource_tags_tag_id_idx ON resource_tags (tag_id);
CREATE INDEX resource_tags_list_id_idx ON resource_tags (list_id);


-- --------------------------------------------------------

--
-- Table structure for table ratings
--

DROP TABLE IF EXISTS "ratings";

CREATE TABLE ratings (
id SERIAL,
user_id int DEFAULT NULL,
resource_id int DEFAULT NULL,
rating int NOT NULL,
created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id)
);
CREATE INDEX ratings_user_id_idx ON ratings (user_id);
CREATE INDEX ratings_resource_id_idx ON ratings (resource_id);


-- --------------------------------------------------------

--
-- Table structure for table search. Than fixed created column default value. Old value is 0000-00-00.
--

DROP TABLE IF EXISTS "search";

CREATE TABLE search (
id BIGSERIAL,
user_id int DEFAULT NULL,
session_id varchar(128),
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
title varchar(20) DEFAULT NULL,
saved boolean NOT NULL DEFAULT '0',
search_object bytea,
checksum int DEFAULT NULL,
notification_frequency int NOT NULL DEFAULT '0',
last_notification_sent timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
notification_base_url varchar(255) NOT NULL DEFAULT '',
PRIMARY KEY (id)
);
CREATE INDEX search_user_id_idx ON search (user_id);
CREATE INDEX session_id_idx ON search (session_id);
CREATE INDEX notification_frequency_idx ON search (notification_frequency);
CREATE INDEX notification_base_url_idx ON search (notification_base_url);
CREATE INDEX search_created_saved_idx ON search (created, saved);

-- --------------------------------------------------------

--
-- Table structure for table shortlinks
--

DROP TABLE IF EXISTS "shortlinks";

CREATE TABLE shortlinks (
id SERIAL,
path text,
hash varchar(32),
created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id)
);
CREATE UNIQUE INDEX shortlinks_hash_idx ON shortlinks (hash);

-- --------------------------------------------------------

--
-- Table structure for table tags
--

DROP TABLE IF EXISTS "tags";

CREATE TABLE tags (
id SERIAL,
tag varchar(64) NOT NULL DEFAULT '',
PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Table structure for table user
--

DROP TABLE IF EXISTS "user";

CREATE TABLE "user"(
id SERIAL,
username varchar(255) NOT NULL DEFAULT '',
password varchar(32) NOT NULL DEFAULT '',
pass_hash varchar(60) DEFAULT NULL,
firstname varchar(50) NOT NULL DEFAULT '',
lastname varchar(50) NOT NULL DEFAULT '',
email varchar(255) NOT NULL DEFAULT '',
email_verified timestamp DEFAULT NULL,
pending_email varchar(255) NOT NULL DEFAULT '',
user_provided_email boolean NOT NULL DEFAULT '0',
cat_id varchar(255) DEFAULT NULL,
cat_username varchar(50) DEFAULT NULL,
cat_password varchar(70) DEFAULT NULL,
cat_pass_enc varchar(170) DEFAULT NULL,
college varchar(100) NOT NULL DEFAULT '',
major varchar(100) NOT NULL DEFAULT '',
home_library varchar(100) DEFAULT '',
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
verify_hash varchar(42) NOT NULL DEFAULT '',
last_login timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
auth_method varchar(50) DEFAULT NULL,
last_language varchar(30) NOT NULL DEFAULT '',
PRIMARY KEY (id),
UNIQUE (username),
UNIQUE (cat_id)
);

CREATE INDEX user_email_idx ON "user" (email);
CREATE INDEX user_verify_hash_idx ON "user" (verify_hash);

-- --------------------------------------------------------

--
-- Table structure for table user_list
--

DROP TABLE IF EXISTS "user_list";

CREATE TABLE user_list (
id SERIAL,
user_id int NOT NULL,
title varchar(200) NOT NULL,
description text DEFAULT NULL,
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
public boolean NOT NULL DEFAULT '0',
PRIMARY KEY (id)
);
CREATE INDEX user_list_user_id_idx ON user_list (user_id);


-- --------------------------------------------------------

--
-- Table structure for table user_resource
--

DROP TABLE IF EXISTS "user_resource";

CREATE TABLE user_resource (
id SERIAL,
user_id int NOT NULL,
resource_id int NOT NULL,
list_id int DEFAULT NULL,
notes text,
saved timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
CONSTRAINT user_resource_ibfk_2 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE,
CONSTRAINT user_resource_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
);
CREATE INDEX user_resource_resource_id_idx ON user_resource (resource_id);
CREATE INDEX user_resource_user_id_idx ON user_resource (user_id);
CREATE INDEX user_resource_list_id_idx ON user_resource (list_id);


--
-- Table structure for table session
--

DROP TABLE IF EXISTS "session";

CREATE TABLE session (
id BIGSERIAL,
session_id varchar(128),
data text,
last_used int NOT NULL default 0,
created timestamp NOT NULL default '1970-01-01 00:00:00',
PRIMARY KEY (id),
UNIQUE (session_id)
);
CREATE INDEX last_used_idx on session(last_used);

--
-- Table structure for table external_session
--

DROP TABLE IF EXISTS "external_session";

CREATE TABLE external_session (
id BIGSERIAL,
session_id varchar(128) NOT NULL,
external_session_id varchar(255) NOT NULL,
created timestamp NOT NULL default '1970-01-01 00:00:00',
PRIMARY KEY (id),
UNIQUE (session_id)
);
CREATE INDEX external_session_id on external_session(external_session_id);

--
-- Table structure for table change_tracker
--

DROP TABLE IF EXISTS "change_tracker";

CREATE TABLE change_tracker (
core varchar(30) NOT NULL,              -- solr core containing record
id varchar(120) NOT NULL,               -- ID of record within core
first_indexed timestamp,                -- first time added to index
last_indexed timestamp,                 -- last time changed in index
last_record_change timestamp,           -- last time original record was edited
deleted timestamp,                      -- time record was removed from index
PRIMARY KEY (core, id)
);
CREATE INDEX change_tracker_deleted_idx on change_tracker(deleted);

--
-- Table structure for table oai_resumption
--

DROP TABLE IF EXISTS "oai_resumption";

CREATE TABLE oai_resumption (
id SERIAL,
params text,
expires timestamp NOT NULL default '1970-01-01 00:00:00',
PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Table structure for table record
--

DROP TABLE IF EXISTS "record";

CREATE TABLE record (
  id serial NOT NULL,
  record_id varchar(255),
  source varchar(50),
  version varchar(20) NOT NULL,
  data text,
  updated timestamp without time zone,
  PRIMARY KEY (id),
  UNIQUE(record_id, source)
);

-- --------------------------------------------------------

--
-- Table structure for table user_card
--

DROP TABLE IF EXISTS "user_card";

CREATE TABLE user_card (
id SERIAL,
user_id int NOT NULL,
card_name varchar(255) NOT NULL DEFAULT '',
cat_username varchar(50) NOT NULL DEFAULT '',
cat_password varchar(50) DEFAULT NULL,
cat_pass_enc varchar(110) DEFAULT NULL,
home_library varchar(100) DEFAULT '',
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
saved timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
CONSTRAINT user_card_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
);
CREATE INDEX user_card_cat_username_idx ON user_card (cat_username);
CREATE INDEX user_card_user_id_idx ON user_card (user_id);

--
-- Table structure for table auth_hash
--

DROP TABLE IF EXISTS "auth_hash";

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

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS "feedback";

CREATE TABLE feedback (
id SERIAL,
user_id int DEFAULT NULL,
message text,
form_data json DEFAULT '{}'::jsonb,
form_name varchar(255) NOT NULL,
created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_by int DEFAULT NULL,
status varchar(255) NOT NULL DEFAULT 'open',
site_url varchar(255) NOT NULL,
PRIMARY KEY (id)
);

--
-- Table structure for table `access_token`
--

DROP TABLE IF EXISTS "access_token";

CREATE TABLE access_token (
id varchar(255) NOT NULL,
type varchar(128) NOT NULL,
user_id int DEFAULT NULL,
created timestamp NOT NULL default '1970-01-01 00:00:00',
data text,
revoked boolean NOT NULL DEFAULT '0',
PRIMARY KEY (id, type)
);

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

-- --------------------------------------------------------

--
-- Constraints for dumped tables
--

--
-- Constraints for table comments
--
ALTER TABLE comments
ADD CONSTRAINT comments_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
ADD CONSTRAINT comments_ibfk_2 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;


--
-- Constraints for table resource_tags
--
ALTER TABLE resource_tags
ADD CONSTRAINT resource_tags_ibfk_14 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE,
ADD CONSTRAINT resource_tags_ibfk_15 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE,
ADD CONSTRAINT resource_tags_ibfk_16 FOREIGN KEY (list_id) REFERENCES user_list (id) ON DELETE SET NULL,
ADD CONSTRAINT resource_tags_ibfk_17 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL;


--
-- Constraints for table ratings
--
ALTER TABLE ratings
ADD CONSTRAINT ratings_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
ADD CONSTRAINT ratings_ibfk_2 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;


--
-- Constraints for table user_list
--
ALTER TABLE user_list
ADD CONSTRAINT user_list_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;


--
-- Constraints for table user_resource
--
ALTER TABLE user_resource
ADD CONSTRAINT user_resource_ibfk_3 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE,
ADD CONSTRAINT user_resource_ibfk_4 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE,
ADD CONSTRAINT user_resource_ibfk_5 FOREIGN KEY (list_id) REFERENCES user_list (id) ON DELETE CASCADE;


--
-- Constraints for table `feedback`
--
ALTER TABLE feedback
ADD CONSTRAINT feedback_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
ADD CONSTRAINT feedback_ibfk_2 FOREIGN KEY (updated_by) REFERENCES "user" (id) ON DELETE SET NULL;

CREATE INDEX feedback_created_idx ON feedback (created);
CREATE INDEX feedback_status_idx ON feedback (status);
CREATE INDEX feedback_form_name_idx ON feedback (form_name);

-- Constraints for table access_token
--
ALTER TABLE access_token
ADD CONSTRAINT access_token_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;

-- --------------------------------------------------------
