-- 
-- Modifications to table `resource`
--

ALTER TABLE "resource"
  ALTER COLUMN source SET DEFAULT 'Solr',
  ALTER COLUMN record_id TYPE varchar(255),
  ALTER COLUMN title TYPE varchar(255),
  ALTER COLUMN author TYPE varchar(255);
  
