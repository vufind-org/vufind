-- Update comments table default timestamp
ALTER TABLE comments ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';

-- Update ratings table - add NOT NULL constraint and update defaults
UPDATE ratings SET resource_id = '0' WHERE resource_id IS NULL; -- Check if null value exists before setting the column as not null
ALTER TABLE ratings ALTER COLUMN resource_id SET DEFAULT '0';
ALTER TABLE ratings ALTER COLUMN resource_id SET NOT NULL;
ALTER TABLE ratings ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';

-- Update shortlinks table - make path NOT NULL
UPDATE shortlinks SET path = '' WHERE path IS NULL;
ALTER TABLE shortlinks ALTER COLUMN path SET NOT NULL;

-- Update user table defaults
ALTER TABLE "user" ALTER COLUMN cat_pass_enc TYPE varchar(255);
ALTER TABLE "user" ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';
ALTER TABLE "user" ALTER COLUMN last_login SET DEFAULT '2000-01-01 00:00:00';

-- Update search table defaults
ALTER TABLE search ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';
ALTER TABLE search ALTER COLUMN last_notification_sent SET DEFAULT '2000-01-01 00:00:00';

-- Update user_list table default
ALTER TABLE user_list ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';

-- Update session table default
ALTER TABLE session ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';

-- Update external_session table default
ALTER TABLE external_session ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';

-- Update oai_resumption table default
ALTER TABLE oai_resumption ALTER COLUMN expires SET DEFAULT '2000-01-01 00:00:00';

-- Update record table - add NOT NULL constraint and default
UPDATE record SET updated = '2000-01-01 00:00:00' WHERE updated IS NULL;
ALTER TABLE record ALTER COLUMN updated SET DEFAULT '2000-01-01 00:00:00';
ALTER TABLE record ALTER COLUMN updated SET NOT NULL;

-- Update user_card table defaults and column types
ALTER TABLE user_card ALTER COLUMN cat_password TYPE varchar(70);
ALTER TABLE user_card ALTER COLUMN cat_pass_enc TYPE varchar(255);
ALTER TABLE user_card ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';

-- Update auth_hash table defaults and constraints
ALTER TABLE auth_hash ALTER COLUMN session_id DROP NOT NULL;
UPDATE auth_hash SET hash = '' WHERE hash IS NULL;
ALTER TABLE auth_hash ALTER COLUMN hash SET DEFAULT '';
ALTER TABLE auth_hash ALTER COLUMN hash SET NOT NULL;
ALTER TABLE auth_hash ALTER COLUMN created SET DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE auth_hash ALTER COLUMN type DROP NOT NULL;

-- Update feedback table - change form_data default
ALTER TABLE feedback ALTER COLUMN form_data SET DEFAULT NULL;

-- Update access_token table default
ALTER TABLE access_token ALTER COLUMN created SET DEFAULT '2000-01-01 00:00:00';

-- Update change_tracker table columns to allow NULL

ALTER TABLE change_tracker ALTER COLUMN first_indexed DROP NOT NULL;
ALTER TABLE change_tracker ALTER COLUMN last_indexed DROP NOT NULL;
ALTER TABLE change_tracker ALTER COLUMN last_record_change DROP NOT NULL;
ALTER TABLE change_tracker ALTER COLUMN deleted DROP NOT NULL;

-- Add unique indexes for user table
CREATE UNIQUE INDEX IF NOT EXISTS user_username_idx ON "user" (username);
CREATE UNIQUE INDEX IF NOT EXISTS user_cat_id_idx ON "user" (cat_id);

-- Update session table indexes
DROP INDEX IF EXISTS last_used_idx;
CREATE INDEX IF NOT EXISTS session_last_used_idx ON session(last_used);
CREATE UNIQUE INDEX IF NOT EXISTS session_session_id_idx ON session (session_id);

-- Update external_session indexes
DROP INDEX IF EXISTS external_session_id;
CREATE INDEX IF NOT EXISTS external_session_id_idx ON external_session(external_session_id);
CREATE UNIQUE INDEX IF NOT EXISTS external_session_session_id_idx ON external_session (session_id);

-- Update oai_resumption index
CREATE UNIQUE INDEX IF NOT EXISTS oai_resumption_token_idx ON oai_resumption (token);

-- Update record table index
CREATE UNIQUE INDEX IF NOT EXISTS record_record_id_source_index ON record (record_id, source);

-- Add new auth_hash indexes
CREATE INDEX IF NOT EXISTS auth_hash_session_id_idx ON auth_hash(session_id);
CREATE UNIQUE INDEX IF NOT EXISTS auth_hash_hash_type_idx ON auth_hash(hash, type);

-- Add new feedback indexes
CREATE INDEX IF NOT EXISTS feedback_user_id_idx ON feedback (user_id);
CREATE INDEX IF NOT EXISTS feedback_updated_by_idx ON feedback (updated_by);

-- Add access_token index
CREATE INDEX IF NOT EXISTS access_token_user_id_idx ON access_token (user_id);

-- Remove old foreign key constraint from search table (if it exists)
ALTER TABLE search DROP CONSTRAINT IF EXISTS search_ibfk_1;

-- Remove old constraints from user_resource table
ALTER TABLE user_resource DROP CONSTRAINT IF EXISTS user_resource_ibfk_1;
ALTER TABLE user_resource DROP CONSTRAINT IF EXISTS user_resource_ibfk_2;
ALTER TABLE user_resource DROP CONSTRAINT IF EXISTS user_resource_ibfk_3;
ALTER TABLE user_resource DROP CONSTRAINT IF EXISTS user_resource_ibfk_4;
ALTER TABLE user_resource DROP CONSTRAINT IF EXISTS user_resource_ibfk_5;

-- Add new/updated foreign key constraints
ALTER TABLE search ADD CONSTRAINT search_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;

ALTER TABLE user_card DROP CONSTRAINT IF EXISTS user_card_ibfk_1;
ALTER TABLE user_card ADD CONSTRAINT user_card_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;

ALTER TABLE user_resource ADD CONSTRAINT user_resource_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;
ALTER TABLE user_resource ADD CONSTRAINT user_resource_ibfk_2 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE user_resource ADD CONSTRAINT user_resource_ibfk_5 FOREIGN KEY (list_id) REFERENCES user_list (id) ON DELETE CASCADE;

ALTER TABLE login_token ADD CONSTRAINT login_token_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;

-- Clean up any duplicate or old index names
DROP INDEX IF EXISTS last_used_idx;
DROP INDEX IF EXISTS external_session_id;
DROP INDEX IF EXISTS shortlinks_hash_idx;
CREATE UNIQUE INDEX IF NOT EXISTS shortlinks_hash_idx ON shortlinks (hash);

ALTER TABLE auth_hash DROP CONSTRAINT IF EXISTS auth_hash_hash_type_key;
ALTER TABLE session DROP CONSTRAINT IF EXISTS session_session_id_key;
ALTER TABLE external_session DROP CONSTRAINT IF EXISTS external_session_session_id_key;
ALTER TABLE "user" DROP CONSTRAINT IF EXISTS user_cat_id_key;
ALTER TABLE "user" DROP CONSTRAINT IF EXISTS user_username_key;
ALTER TABLE oai_resumption DROP CONSTRAINT IF EXISTS oai_resumption_token_key;
ALTER TABLE record DROP CONSTRAINT IF EXISTS record_record_id_source_key;
