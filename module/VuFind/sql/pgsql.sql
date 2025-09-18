--
-- Table structure for table comments
--

DROP TABLE IF EXISTS "comments";

CREATE TABLE comments (
id SERIAL,
user_id int DEFAULT NULL,
resource_id int NOT NULL DEFAULT '0',
comment text NOT NULL,
created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
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
resource_id int NOT NULL DEFAULT '0',
rating int NOT NULL,
created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
PRIMARY KEY (id)
);
CREATE INDEX ratings_user_id_idx ON ratings (user_id);
CREATE INDEX ratings_resource_id_idx ON ratings (resource_id);

-- --------------------------------------------------------

--
-- Table structure for table shortlinks
--

DROP TABLE IF EXISTS "shortlinks";

CREATE TABLE shortlinks (
id SERIAL,
path text NOT NULL,
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

CREATE TABLE "user" (
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
cat_pass_enc varchar(255) DEFAULT NULL,
college varchar(100) NOT NULL DEFAULT '',
major varchar(100) NOT NULL DEFAULT '',
home_library varchar(100) DEFAULT '',
created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
verify_hash varchar(42) NOT NULL DEFAULT '',
last_login timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
auth_method varchar(50) DEFAULT NULL,
last_language varchar(30) NOT NULL DEFAULT '',
PRIMARY KEY (id)
);

CREATE INDEX user_email_idx ON "user" (email);
CREATE INDEX user_verify_hash_idx ON "user" (verify_hash);
CREATE UNIQUE INDEX user_username_idx on "user" (username);
CREATE UNIQUE INDEX user_cat_id_idx on "user" (cat_id);

-- --------------------------------------------------------

--
-- Table structure for table search. Than fixed created column default value. Old value is 0000-00-00.
--

DROP TABLE IF EXISTS "search";

CREATE TABLE "search" (
id BIGSERIAL,
user_id int DEFAULT NULL,
session_id varchar(128),
created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
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
-- Table structure for table user_list
--

DROP TABLE IF EXISTS "user_list";

CREATE TABLE user_list (
id SERIAL,
user_id int NOT NULL,
title varchar(200) NOT NULL,
description text DEFAULT NULL,
created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
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
PRIMARY KEY (id)
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
created timestamp NOT NULL default '2000-01-01 00:00:00',
PRIMARY KEY (id)
);
CREATE INDEX session_last_used_idx on session(last_used);
CREATE UNIQUE INDEX session_session_id_idx ON session (session_id);

--
-- Table structure for table external_session
--

DROP TABLE IF EXISTS "external_session";

CREATE TABLE external_session (
id BIGSERIAL,
session_id varchar(128) NOT NULL,
external_session_id varchar(255) NOT NULL,
created timestamp NOT NULL default '2000-01-01 00:00:00',
PRIMARY KEY (id)
);
CREATE INDEX external_session_id_idx on external_session(external_session_id);
CREATE UNIQUE INDEX external_session_session_id_idx ON external_session (session_id);

--
-- Table structure for table change_tracker
--

DROP TABLE IF EXISTS "change_tracker";

CREATE TABLE change_tracker (
core varchar(30) NOT NULL,                           -- solr core containing record
id varchar(120) NOT NULL,                            -- ID of record within core
first_indexed timestamp DEFAULT NULL,                -- first time added to index
last_indexed timestamp DEFAULT NULL,                 -- last time changed in index
last_record_change timestamp DEFAULT NULL,           -- last time original record was edited
deleted timestamp DEFAULT NULL,                      -- time record was removed from index
PRIMARY KEY (core, id)
);
CREATE INDEX change_tracker_deleted_idx on change_tracker(deleted);

--
-- Table structure for table oai_resumption
--

DROP TABLE IF EXISTS "oai_resumption";

CREATE TABLE oai_resumption (
id SERIAL,
token varchar(255) DEFAULT NULL,
params text,
expires timestamp NOT NULL default '2000-01-01 00:00:00',
PRIMARY KEY (id)
);
CREATE UNIQUE INDEX oai_resumption_token_idx ON oai_resumption (token);

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
  updated timestamp without time zone NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (id)
);
CREATE UNIQUE INDEX record_record_id_source_index ON record (record_id, source);

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
cat_password varchar(70) DEFAULT NULL,
cat_pass_enc varchar(255) DEFAULT NULL,
home_library varchar(100) DEFAULT '',
created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
saved timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (id)
);
CREATE INDEX user_card_cat_username_idx ON user_card (cat_username);
CREATE INDEX user_card_user_id_idx ON user_card (user_id);

--
-- Table structure for table auth_hash
--

DROP TABLE IF EXISTS "auth_hash";

CREATE TABLE auth_hash (
id BIGSERIAL,
session_id varchar(128) DEFAULT NULL,
hash varchar(255) NOT NULL DEFAULT '',
type varchar(50) DEFAULT NULL,
data text,
created timestamp NOT NULL default CURRENT_TIMESTAMP,
PRIMARY KEY (id)
);
CREATE INDEX auth_hash_created_idx on auth_hash(created);
CREATE INDEX auth_hash_session_id_idx on auth_hash(session_id);
CREATE UNIQUE INDEX auth_hash_hash_type_idx on auth_hash(hash, type);

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS "feedback";

CREATE TABLE feedback (
id SERIAL,
user_id int DEFAULT NULL,
message text,
form_data json DEFAULT NULL,
form_name varchar(255) NOT NULL,
created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_by int DEFAULT NULL,
status varchar(255) NOT NULL DEFAULT 'open',
site_url varchar(255) NOT NULL,
PRIMARY KEY (id)
);

CREATE INDEX feedback_created_idx ON feedback (created);
CREATE INDEX feedback_status_idx ON feedback (status);
CREATE INDEX feedback_form_name_idx ON feedback (form_name);
CREATE INDEX feedback_user_id_idx ON feedback (user_id);
CREATE INDEX feedback_updated_by_idx ON feedback (updated_by);

--
-- Table structure for table `access_token`
--

DROP TABLE IF EXISTS "access_token";

CREATE TABLE access_token (
id varchar(255) NOT NULL,
type varchar(128) NOT NULL,
user_id int DEFAULT NULL,
created timestamp NOT NULL default '2000-01-01 00:00:00',
data text,
revoked boolean NOT NULL DEFAULT '0',
PRIMARY KEY (id, type)
);
CREATE INDEX access_token_user_id_idx ON access_token (user_id);

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
CREATE INDEX login_token_user_id_idx ON login_token (user_id);
CREATE INDEX login_token_series_idx ON login_token (series);

--
-- Table structure for table `payment`
--

DROP TABLE IF EXISTS "payment";

CREATE TABLE payment (
  id SERIAL,
  local_identifier varchar(255) NOT NULL,
  remote_identifier varchar(255),
  user_id int NOT NULL,
  source_ils varchar(255) NOT NULL,
  cat_username varchar(50) NOT NULL,
  amount int NOT NULL,
  currency varchar(3) NOT NULL,
  service_fee int NOT NULL,
  created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  paid timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  registration_started timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  registered timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  status int NOT NULL DEFAULT 0,
  status_message varchar(255),
  reported timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (id)
);
CREATE INDEX payment_local_identifier_idx ON payment (local_identifier);
CREATE INDEX payment_user_id_idx ON payment (user_id);
CREATE INDEX payment_status_cat_username_created_idx ON payment (status, cat_username, created);
CREATE INDEX payment_paid_reported_idx ON payment (paid, reported);

--
-- Table structure for table `payment_fee`
--

DROP TABLE IF EXISTS "payment_fee";

CREATE TABLE payment_fee (
  id SERIAL,
  payment_id int NOT NULL,
  title varchar(255) NOT NULL DEFAULT '',
  type varchar(255) NOT NULL DEFAULT '',
  description varchar(255) NOT NULL DEFAULT '',
  amount int NOT NULL DEFAULT 0,
  tax_percent int NOT NULL DEFAULT 0,
  currency varchar(3) NOT NULL,
  fine_id varchar(1024) NOT NULL DEFAULT '',
  organization varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
);
CREATE INDEX payment_fee_payment_id_idx ON payment_fee (payment_id);

--
-- Table structure for table `audit_event`
--

CREATE TABLE audit_event (
  id SERIAL,
  date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  type varchar(50) NOT NULL,
  subtype varchar(50) NOT NULL,
  user_id int,
  payment_id int,
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
CREATE INDEX audit_event_payment_id_idx ON audit_event (payment_id);


--
-- Table structure for table `migrations`

CREATE TABLE migrations (
  id SERIAL,
  name varchar(255),
  status varchar(50) NOT NULL,
  target_version varchar(50) NOT NULL,
  date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

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
-- Constraints for table search
--
ALTER TABLE search
ADD CONSTRAINT search_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;


--
-- Constraints for table user_card
--
ALTER TABLE user_card
ADD CONSTRAINT user_card_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;


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
ADD CONSTRAINT user_resource_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE,
ADD CONSTRAINT user_resource_ibfk_2 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE,
ADD CONSTRAINT user_resource_ibfk_5 FOREIGN KEY (list_id) REFERENCES user_list (id) ON DELETE CASCADE;


--
-- Constraints for table `feedback`
--
ALTER TABLE feedback
ADD CONSTRAINT feedback_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
ADD CONSTRAINT feedback_ibfk_2 FOREIGN KEY (updated_by) REFERENCES "user" (id) ON DELETE SET NULL;

--
-- Constraints for table access_token
--
ALTER TABLE access_token
ADD CONSTRAINT access_token_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;

--
-- Constraints for table login_token
--
ALTER TABLE login_token
ADD CONSTRAINT login_token_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;

--
-- Constraints for table payment
--
ALTER TABLE payment
ADD CONSTRAINT payment_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;

--
-- Constraints for table payment_fee
--

ALTER TABLE payment_fee
ADD CONSTRAINT payment_fee_ibfk_1 FOREIGN KEY (payment_id) REFERENCES "payment" (id) ON DELETE CASCADE;

--
-- Constraints for table audit_event
--
ALTER TABLE audit_event
ADD CONSTRAINT audit_event_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
ADD CONSTRAINT audit_event_ibfk_2 FOREIGN KEY (payment_id) REFERENCES "payment" (id) ON DELETE CASCADE;
