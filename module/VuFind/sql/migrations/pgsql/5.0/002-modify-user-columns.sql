--
-- Modifications to table `user`
--

ALTER TABLE "user"
  ADD COLUMN last_login timestamp NOT NULL DEFAULT '1970-01-01 00:00:00',
  ADD COLUMN auth_method varchar(50) DEFAULT NULL;
