UPDATE search SET user_id=null WHERE user_id NOT IN (SELECT id FROM "user");

ALTER TABLE search
    ADD CONSTRAINT search_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;
