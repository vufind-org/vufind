CREATE INDEX login_token_user_id_idx ON login_token (user_id);
CREATE INDEX login_token_series_idx ON login_token (series);
DROP INDEX login_token_user_id_series_idx;
