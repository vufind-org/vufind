--
-- Modifications to table `search`
--

CREATE INDEX search_created_saved_idx ON search (created, saved);
