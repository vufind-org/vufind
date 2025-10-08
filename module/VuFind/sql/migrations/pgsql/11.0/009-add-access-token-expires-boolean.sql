ALTER TABLE access_token ADD COLUMN expires boolean NOT NULL DEFAULT '1';
CREATE INDEX IF NOT EXISTS access_token_expires_idx ON access_token(expires);
ALTER TABLE access_token ADD COLUMN title varchar(255) NOT NULL DEFAULT '';
ALTER TABLE access_token ADD KEY access_token_title_idx ON access_token(title);
