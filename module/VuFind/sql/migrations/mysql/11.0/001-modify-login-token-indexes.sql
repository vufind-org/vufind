ALTER TABLE `login_token` ADD KEY `user_id` (`user_id`);
ALTER TABLE `login_token` ADD KEY `series` (`series`);
ALTER TABLE `login_token` DROP KEY `user_id_series`;
