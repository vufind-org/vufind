--
-- Modifications to table `resource`
--

ALTER TABLE "resource"
  ADD COLUMN extra_metadata varchar(512) DEFAULT NULL;
