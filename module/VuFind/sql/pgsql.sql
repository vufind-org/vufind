--
-- Table structure for table comments
--

DROP TABLE IF EXISTS "comments";

CREATE TABLE comments (
id SERIAL,
user_id int NOT NULL DEFAULT '0',
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

CREATE TABLE resource (
id SERIAL,
record_id varchar(120) NOT NULL DEFAULT '',
title varchar(200) NOT NULL DEFAULT '',
author varchar(200) DEFAULT NULL,
year int DEFAULT NULL,
source varchar(50) NOT NULL DEFAULT 'VuFind',
PRIMARY KEY (id)
);
CREATE INDEX resource_record_id_idx ON resource (record_id);


-- --------------------------------------------------------

--
-- Table structure for table resource_tags
--

CREATE TABLE resource_tags (
id SERIAL,
resource_id int NOT NULL DEFAULT '0',
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
-- Table structure for table search. Than fixed created column default value. Old value is 0000-00-00.
--

CREATE TABLE search (
id SERIAL,
user_id int NOT NULL DEFAULT '0',
session_id varchar(128),
folder_id int DEFAULT NULL,
created date NOT NULL DEFAULT '1970-01-01',
title varchar(20) DEFAULT NULL,
saved int NOT NULL DEFAULT '0',
search_object bytea,
PRIMARY KEY (id)
);
CREATE INDEX search_user_id_idx ON search (user_id);
CREATE INDEX search_folder_id_idx ON search (folder_id);
CREATE INDEX session_id_idx ON search (session_id);


-- --------------------------------------------------------

--
-- Table structure for table tags
--

CREATE TABLE tags (
id SERIAL,
tag varchar(64) NOT NULL DEFAULT '',
PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Table structure for table user
--

CREATE TABLE "user"(
id SERIAL,
username varchar(255) NOT NULL DEFAULT '',
password varchar(32) NOT NULL DEFAULT '',
pass_hash varchar(60) DEFAULT NULL,
firstname varchar(50) NOT NULL DEFAULT '',
lastname varchar(50) NOT NULL DEFAULT '',
email varchar(255) NOT NULL DEFAULT '',
cat_username varchar(50) DEFAULT NULL,
cat_password varchar(50) DEFAULT NULL,
cat_pass_enc varchar(110) DEFAULT NULL,
college varchar(100) NOT NULL DEFAULT '',
major varchar(100) NOT NULL DEFAULT '',
home_library varchar(100) NOT NULL DEFAULT '',
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
verify_hash varchar(42) NOT NULL DEFAULT '',
PRIMARY KEY (id),
UNIQUE (username)
);


-- --------------------------------------------------------

--
-- Table structure for table user_list
--

CREATE TABLE user_list (
id SERIAL,
user_id int NOT NULL,
title varchar(200) NOT NULL,
description text DEFAULT NULL,
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
public int NOT NULL DEFAULT '0',
PRIMARY KEY (id)
);
CREATE INDEX user_list_user_id_idx ON user_list (user_id);


-- --------------------------------------------------------

--
-- Table structure for table user_resource
--

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
id SERIAL,
session_id varchar(128),
data text,
last_used int NOT NULL default 0,
created timestamp NOT NULL default '1970-01-01 00:00:00',
PRIMARY KEY (id),
UNIQUE (session_id)
);
CREATE INDEX last_used_idx on session(last_used);

--
-- Table structure for table `change_tracker`
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
-- Table structure for table `oai_resumption`
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
-- Statistics tables
--

--
-- Table structure for table `statistics`
--

DROP TABLE IF EXISTS "user_stats_fields";

CREATE TABLE user_stats_fields (
id varchar(24) NOT NULL,
field varchar(32) NOT NULL,
value varchar(1024) NOT NULL,
PRIMARY KEY (id, field)
);

-- --------------------------------------------------------

--
-- Table structure for table `user_stats`
--

DROP TABLE IF EXISTS "user_stats";

CREATE TABLE user_stats (
id varchar(24) NOT NULL,
datestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
browser varchar(32) NOT NULL,
browserVersion varchar(8) NOT NULL,
ipaddress varchar(15) NOT NULL,
referrer varchar(512) NOT NULL,
url varchar(512) NOT NULL,
session varchar(64) NOT NULL,
PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Table structure for table `user_card`
--

DROP TABLE IF EXISTS "user_card";

CREATE TABLE `user_card` (
id SERIAL,
user_id int NOT NULL,
card_name varchar(255) NOT NULL DEFAULT '',
cat_username varchar(50) NOT NULL DEFAULT '',
cat_password varchar(50) DEFAULT NULL,
cat_pass_enc varchar(110) DEFAULT NULL,
home_library varchar(100) NOT NULL DEFAULT '',
created timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
saved timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id),
CONSTRAINT user_card_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE
);
CREATE INDEX user_card_cat_username_idx ON user_card (cat_username);
CREATE INDEX user_card_user_id_idx ON user_card (user_id);

-- --------------------------------------------------------

--
-- Constraints for dumped tables
--

--
-- Constraints for table comments
--
ALTER TABLE comments
ADD CONSTRAINT comments_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE,
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
