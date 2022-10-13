ALTER TABLE "user"
  ADD COLUMN `cypher_method` varchar(50) DEFAULT -1; -- -1 == undefined
-- TODO is there a way to set this to the config or automatically run switch_db_hash?