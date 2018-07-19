--
-- Modifications to table `resource`
--

ALTER TABLE "resource"
  ADD COLUMN extra_metadata varchar(256) DEFAULT NULL;
