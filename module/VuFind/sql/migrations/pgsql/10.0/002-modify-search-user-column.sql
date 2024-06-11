--
-- Modifications to user_id column in table `comments`
--

ALTER TABLE "search"
   ALTER COLUMN user_id DROP NOT NULL;

ALTER TABLE "search"
   ALTER COLUMN user_id SET DEFAULT NULL;

UPDATE "search" SET user_id=NULL WHERE user_id='0' OR user_id NOT IN (SELECT DISTINCT id FROM "user");
