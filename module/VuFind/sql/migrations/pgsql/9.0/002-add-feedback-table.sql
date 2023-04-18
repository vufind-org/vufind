CREATE TABLE feedback (
id SERIAL,
user_id int DEFAULT NULL,
message text,
form_data json DEFAULT '{}'::jsonb,
form_name varchar(255) NOT NULL,
created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_by int DEFAULT NULL,
status varchar(255) NOT NULL DEFAULT 'open',
site_url varchar(255) NOT NULL,
PRIMARY KEY (id),
CONSTRAINT feedback_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL,
CONSTRAINT feedback_ibfk_2 FOREIGN KEY (updated_by) REFERENCES "user" (id) ON DELETE SET NULL
);
CREATE INDEX feedback_created_idx ON feedback (created);
CREATE INDEX feedback_status_idx ON feedback (status);
CREATE INDEX feedback_form_name_idx ON feedback (form_name);

