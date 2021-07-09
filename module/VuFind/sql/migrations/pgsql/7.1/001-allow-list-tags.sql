--
-- Modifications to table `resource_tags`
--

ALTER TABLE "resource_tags"
   ALTER COLUMN resource_id DROP NOT NULL;

ALTER TABLE "resource_tags"
   ALTER COLUMN resource_id SET DEFAULT NULL;
