ALTER TABLE access_token ADD COLUMN expires boolean NOT NULL DEFAULT '1';
CREATE INDEX IF NOT EXISTS access_token_expires_idx ON access_token(expires);
