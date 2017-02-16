-- 
-- Modifications to table `search`
--

ALTER TABLE "search"
   ALTER COLUMN created TYPE timestamp,
   ALTER COLUMN created SET NOT NULL,
   ALTER COLUMN created SET DEFAULT '1970-01-01 00:00:00',
   ADD COLUMN checksum int DEFAULT NULL;
