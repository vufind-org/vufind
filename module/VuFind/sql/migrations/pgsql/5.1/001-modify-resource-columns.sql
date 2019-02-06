--
-- Modifications to table `resource`
--

ALTER TABLE "resource"
  ADD COLUMN extra_metadata text DEFAULT NULL;
