-- 
-- Modifications to table `user`
--

ALTER TABLE "user"
  ALTER COLUMN cat_password TYPE varchar(70),
  ALTER COLUMN cat_pass_enc TYPE varchar(170);
