--
-- Modifications to table `user`
--

ALTER TABLE "user"
  ADD COLUMN cat_id varchar(255);

CREATE UNIQUE INDEX cat_id ON "user" (cat_id);
