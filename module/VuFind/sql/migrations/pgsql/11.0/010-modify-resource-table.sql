ALTER TABLE "resource"
  ADD COLUMN display_title varchar(255) DEFAULT NULL;
ALTER TABLE "resource"
  ADD COLUMN updated timestamp NOT NULL DEFAULT '2000-01-01 00:00:00';

CREATE INDEX resource_updated_idx ON resource (updated);
