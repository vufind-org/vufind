-- !!! WARNING: This script will erase data from the current database!!!
--
-- To use this script:
--
-- 1.) Create the database and tables (remember both base mysql.sql and Finna mysql.sql
--
-- 2.) Make sure the source database is correct (vufind by default) 
--
-- 3.) Run this script using the source command in mysql
--

DELETE FROM record;
DELETE FROM oai_resumption;
DELETE FROM finna_fee;
DELETE FROM finna_transaction;
DELETE FROM finna_comments_record;
DELETE FROM finna_comments_inappropriate;
DELETE FROM comments;
DELETE FROM user_resource;
DELETE FROM resource_tags;
DELETE FROM tags;
DELETE FROM resource;
DELETE FROM search;
DELETE FROM user_list;
DELETE FROM user_card;
DELETE FROM user;
DELETE FROM change_tracker;

INSERT INTO change_tracker (core, id, first_indexed, last_indexed, last_record_change, deleted)
  SELECT core, id, first_indexed, last_indexed, last_record_change, deleted 
    FROM vufind.change_tracker;

INSERT INTO user (id, username, password, firstname, lastname, email, cat_username, cat_password, college, major, home_library, created, finna_language, finna_due_date_reminder, finna_last_login, finna_auth_method)
  SELECT id, username, password, firstname, lastname, email, cat_username, cat_password, college, major, home_library, created, language, due_date_reminder, last_login, authMethod
    FROM vufind.user;

INSERT INTO user_card (id, user_id, card_name, cat_username, cat_password, home_library, created, saved)
  SELECT id, user_id, account_name, cat_username, cat_password, home_library, created, saved
    FROM vufind.user_account;

INSERT INTO user_list (id, user_id, title, description, created, public, finna_updated)
  SELECT id, user_id, title, description, created, public, modified
    FROM vufind.user_list;    
    
INSERT INTO search (id, user_id, session_id, folder_id, created, title, saved, search_object, finna_schedule, finna_last_executed, finna_schedule_base_url)
  SELECT id, user_id, session_id, folder_id, created, title, saved, search_object, schedule, last_executed, schedule_base_url
    FROM vufind.search
    WHERE saved=1;

-- Add class to Solr searches     
UPDATE search SET search_object=CONCAT('O:5:"minSO":9', SUBSTRING(search_object, 14, LENGTH(search_object)-14), 's:2:"cl";s:4:"Solr";', '}')
  WHERE search_object like '%s:2:"ty";s:5:"basic"%' OR search_object like '%s:2:"ty";s:8:"advanced"%';    

-- Add class to Primo searches     
UPDATE search SET search_object=CONCAT('O:5:"minSO":9', SUBSTRING(search_object, 14, INSTR(search_object, 's:2:"ty";s:3:"PCI";')-14), 's:2:"ty";s:5:"basic";s:2:"cl";s:5:"Primo";', SUBSTRING(search_object, INSTR(search_object, 's:2:"ty";s:3:"PCI";')+19))
  WHERE search_object like '%s:2:"ty";s:3:"PCI"%';    
                  
-- Add class to MetaLib searches     
UPDATE search SET search_object=CONCAT('O:5:"minSO":9', SUBSTRING(search_object, 14, INSTR(search_object, 's:2:"ty";s:7:"MetaLib";')-14), 's:2:"ty";s:5:"basic";s:2:"cl";s:7:"MetaLib";', SUBSTRING(search_object, INSTR(search_object, 's:2:"ty";s:7:"MetaLib";')+23))
  WHERE search_object like '%s:2:"ty";s:7:"MetaLib"%';    
                  
INSERT INTO resource (id, record_id, title, author, source)
  SELECT id, record_id, title, author_sort, CASE WHEN source='VuFind' THEN 'Solr' WHEN source='PCI' THEN 'Primo' ELSE source END
    FROM vufind.resource;

INSERT INTO tags (id, tag)
  SELECT id, tag
    FROM vufind.tags;  
      
INSERT INTO resource_tags (id, resource_id, tag_id, list_id, user_id, posted)
  SELECT id, resource_id, tag_id, list_id, user_id, posted
    FROM vufind.resource_tags;
    
INSERT INTO user_resource (id, user_id, resource_id, list_id, notes, saved)
  SELECT id, user_id, resource_id, list_id, notes, saved
    FROM vufind.user_resource; 

INSERT INTO comments (id, user_id, resource_id, comment, created, finna_visible, finna_rating, finna_type, finna_updated)
  SELECT id, user_id, resource_id, comment, created, visible, rating, type, updated 
    FROM vufind.comments
    WHERE EXISTS (SELECT * FROM vufind.resource WHERE resource.id = comments.resource_id);
  
INSERT INTO finna_comments_inappropriate (id, user_id, comment_id, created, reason)
  SELECT id, user_id, comment_id, created, reason 
    FROM vufind.comments_inappropriate;

INSERT INTO finna_comments_record (id, record_id, comment_id)
  SELECT id, record_id, comment_id 
    FROM vufind.comments_record
    WHERE EXISTS (SELECT * FROM vufind.comments WHERE EXISTS (SELECT * FROM vufind.resource WHERE resource.id = comments.resource_id));
  
INSERT INTO finna_transaction (id, transaction_id, user_id, driver, amount, currency, transaction_fee, created, paid, registered, complete, status, cat_username, reported)
  SELECT id, transaction_id, user_id, driver, amount, currency, transaction_fee, created, paid, registered, complete, status, cat_username, reported 
    FROM vufind.transaction;  
  
INSERT INTO finna_fee (id, user_id, transaction_id, title, type, amount, currency) 
  SELECT id, user_id, (SELECT transaction_id FROM vufind.transaction_fees WHERE transaction_fees.fee_id=fee.id), title, type, amount, currency 
    FROM vufind.fee
    WHERE EXISTS (SELECT transaction_id FROM vufind.transaction_fees WHERE transaction_fees.fee_id=fee.id);

INSERT INTO oai_resumption (id, params, expires)
  SELECT id, params, expires 
    FROM vufind.oai_resumption;

INSERT INTO record (record_id, source, version, data, updated)
  SELECT record_id, CASE WHEN source='VuFind' THEN 'Solr' WHEN source='PCI' THEN 'Primo' ELSE source END, '2.5.2', data, NOW() 
    FROM vufind.resource;
