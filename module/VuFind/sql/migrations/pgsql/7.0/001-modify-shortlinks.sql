ALTER TABLE "shortlinks"
  ADD COLUMN hash varchar(32);
CREATE UNIQUE INDEX shortlinks_hash_idx ON shortlinks (hash);