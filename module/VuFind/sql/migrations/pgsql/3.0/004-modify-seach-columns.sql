-- 
-- Modifications to table `search`
--

ALTER TABLE "search"
  ALTER COLUMN created TYPE timestamp NOT NULL SET DEFAULT '1970-01-01 00:00:00',
  ADD COLUMN checksum int DEFAULT NULL;
