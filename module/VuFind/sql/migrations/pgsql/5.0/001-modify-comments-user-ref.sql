--
-- Modifications to table `comments`
--

ALTER TABLE "comments"
   ALTER COLUMN user_id DROP NOT NULL,
   DROP CONSTRAINT comments_ibfk_1;

ALTER TABLE comments
   ADD CONSTRAINT comments_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL;
