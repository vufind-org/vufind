-- 
-- Modifications to table `user`
--

ALTER TABLE "user"
  ALTER COLUMN username TYPE varchar(255),
  ALTER COLUMN email TYPE varchar(255);
