CREATE TABLE feedback (
id SERIAL,
user_id int DEFAULT NULL,
referrer text DEFAULT NULL,
user_agent varchar(255) DEFAULT NULL,
user_name varchar(255) DEFAULT NULL,
user_email varchar(255) DEFAULT NULL,
message text,
PRIMARY KEY (id),
KEY user_id (user_id),
CONSTRAINT feedback_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL
);
