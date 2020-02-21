--
-- Modifications to table `search`
--

ALTER TABLE "search"
  ALTER COLUMN id SET DATA TYPE bigint;

--
-- Modifications to table `session`
--

ALTER TABLE "session"
  ALTER COLUMN id SET DATA TYPE bigint;

--
-- Modifications to table `external_session`
--

ALTER TABLE "external_session"
  ALTER COLUMN id SET DATA TYPE bigint;
