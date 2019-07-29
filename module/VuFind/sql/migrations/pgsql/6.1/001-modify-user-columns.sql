--
-- Modifications to table `user`
--

ALTER TABLE "user"
  ADD COLUMN pending_email varchar(255) NOT NULL DEFAULT '';

