--
-- Modifications to table `search`
--

ALTER TABLE "search"
  ALTER COLUMN saved DROP DEFAULT,
  ALTER COLUMN saved TYPE boolean USING saved::boolean,
  ALTER COLUMN saved SET NOT NULL,
  ALTER COLUMN saved SET DEFAULT '0';
--
-- Modifications to table `user_list`
--

ALTER TABLE "user_list"
  ALTER COLUMN public DROP DEFAULT,
  ALTER COLUMN public TYPE boolean USING public::boolean,
  ALTER COLUMN public SET NOT NULL,
  ALTER COLUMN public SET DEFAULT '0';