ALTER TABLE `audit_event`
  ADD COLUMN `payment_id` int DEFAULT NULL;

ALTER TABLE `audit_event` ADD KEY `audit_event_payment_id_idx` (`payment_id`);
ALTER TABLE `audit_event`
    ADD CONSTRAINT `audit_event_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payment` (`id`) ON DELETE CASCADE;
