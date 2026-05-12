-- This script renames indexes to match the new naming convention

-- change_tracker table
ALTER TABLE `change_tracker` 
DROP INDEX `deleted_index`,
ADD INDEX `change_tracker_deleted_idx` (`deleted`);

-- comments table
ALTER TABLE `comments` 
DROP INDEX `user_id`,
DROP INDEX `resource_id`,
ADD INDEX `comments_user_id_idx` (`user_id`),
ADD INDEX `comments_resource_id_idx` (`resource_id`);

-- ratings table
ALTER TABLE `ratings` 
DROP INDEX `user_id`,
DROP INDEX `resource_id`,
ADD INDEX `ratings_user_id_idx` (`user_id`),
ADD INDEX `ratings_resource_id_idx` (`resource_id`);

-- oai_resumption table
ALTER TABLE `oai_resumption`
DROP INDEX `token`,
ADD UNIQUE INDEX `oai_resumption_token_idx` (`token`);

-- resource table
ALTER TABLE `resource`
DROP INDEX `record_id`,
ADD INDEX `resource_record_id_idx` (`record_id`(190));

-- resource_tags table
ALTER TABLE `resource_tags`
DROP INDEX `user_id`,
DROP INDEX `resource_id`,
DROP INDEX `tag_id`,
DROP INDEX `list_id`,
ADD INDEX `resource_tags_user_id_idx` (`user_id`),
ADD INDEX `resource_tags_resource_id_idx` (`resource_id`),
ADD INDEX `resource_tags_tag_id_idx` (`tag_id`),
ADD INDEX `resource_tags_list_id_idx` (`list_id`);

-- search table
ALTER TABLE `search`
DROP INDEX `user_id`,
DROP INDEX `session_id`,
DROP INDEX `notification_frequency`,
DROP INDEX `notification_base_url`,
DROP INDEX `created_saved`,
ADD INDEX `search_user_id_idx` (`user_id`),
ADD INDEX `session_id_idx` (`session_id`),
ADD INDEX `notification_frequency_idx` (`notification_frequency`),
ADD INDEX `notification_base_url_idx` (`notification_base_url`(190)),
ADD INDEX `search_created_saved_idx` (`created`, `saved`);

-- session table
ALTER TABLE `session`
DROP INDEX `session_id`,
DROP INDEX `last_used`,
ADD UNIQUE INDEX `session_session_id_idx` (`session_id`),
ADD INDEX `session_last_used_idx` (`last_used`);

-- external_session table
ALTER TABLE `external_session`
DROP INDEX `session_id`,
DROP INDEX `external_session_id`,
ADD UNIQUE INDEX `external_session_session_id_idx` (`session_id`),
ADD INDEX `external_session_id_idx` (`external_session_id`(190));

-- shortlinks table
ALTER TABLE `shortlinks`
DROP INDEX `shortlinks_hash_IDX`,
ADD UNIQUE INDEX `shortlinks_hash_idx` USING HASH (`hash`);

-- user table
ALTER TABLE `user`
DROP INDEX `username`,
DROP INDEX `cat_id`,
DROP INDEX `email`,
DROP INDEX `verify_hash`,
ADD UNIQUE INDEX `user_username_idx` (`username`(190)),
ADD UNIQUE INDEX `user_cat_id_idx` (`cat_id`(190)),
ADD INDEX `user_email_idx` (`email`(190)),
ADD INDEX `user_verify_hash_idx` (`verify_hash`);

-- user_list table
ALTER TABLE `user_list`
DROP INDEX `user_id`,
ADD INDEX `user_list_user_id_idx` (`user_id`);

-- user_resource table
ALTER TABLE `user_resource`
DROP INDEX `resource_id`,
DROP INDEX `user_id`,
DROP INDEX `list_id`,
ADD INDEX `user_resource_resource_id_idx` (`resource_id`),
ADD INDEX `user_resource_user_id_idx` (`user_id`),
ADD INDEX `user_resource_list_id_idx` (`list_id`);

-- user_card table
ALTER TABLE `user_card`
DROP INDEX `user_id`,
DROP INDEX `user_card_cat_username`,
ADD INDEX `user_card_user_id_idx` (`user_id`),
ADD INDEX `user_card_cat_username_idx` (`cat_username`);

-- record table
ALTER TABLE `record`
DROP INDEX `record_id_source`,
ADD UNIQUE INDEX `record_record_id_source_index` (`record_id`(140), `source`);

-- auth_hash table
ALTER TABLE `auth_hash`
DROP INDEX `session_id`,
DROP INDEX `hash_type`,
DROP INDEX `created`,
ADD INDEX `auth_hash_session_id_idx` (`session_id`),
ADD UNIQUE INDEX `auth_hash_hash_type_idx` (`hash`(140), `type`),
ADD INDEX `auth_hash_created_idx` (`created`);

-- feedback table
ALTER TABLE `feedback`
DROP INDEX `user_id`,
DROP INDEX `created`,
DROP INDEX `status`,
DROP INDEX `form_name`,
ADD INDEX `feedback_user_id_idx` (`user_id`),
ADD INDEX `feedback_updated_by_idx` (`updated_by`),
ADD INDEX `feedback_created_idx` (`created`),
ADD INDEX `feedback_status_idx` (`status`(191)),
ADD INDEX `feedback_form_name_idx` (`form_name`(191));

-- access_token table
ALTER TABLE `access_token`
DROP INDEX `user_id`,
ADD INDEX `access_token_user_id_idx` (`user_id`);

-- login_token table
ALTER TABLE `login_token`
DROP INDEX `user_id`,
DROP INDEX `series`,
ADD INDEX `login_token_user_id_idx` (`user_id`),
ADD INDEX `login_token_series_idx` (`series`),
ADD CONSTRAINT `login_token_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
