--
-- Modifications to table `user`
--

ALTER TABLE "user"
  ADD COLUMN email_verified timestamp DEFAULT NULL;

