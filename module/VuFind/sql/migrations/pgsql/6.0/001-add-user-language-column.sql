--
-- Modifications to table `user`
--

ALTER TABLE "user"
  ADD COLUMN last_language varchar(30) NOT NULL DEFAULT '';
