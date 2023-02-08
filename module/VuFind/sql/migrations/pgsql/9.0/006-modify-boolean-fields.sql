--
-- Modifications to table `search`
--

ALTER TABLE "search"
  ALTER COLUMN saved TYPE boolean NOT NULL DEFAULT '0';

--
-- Modifications to table `user_list`
--

ALTER TABLE "user_list"
  ALTER COLUMN public TYPE boolean NOT NULL DEFAULT '0';