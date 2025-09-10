CREATE TABLE payment (
  id SERIAL,
  local_identifier varchar(255) NOT NULL,
  remote_identifier varchar(255),
  user_id int NOT NULL,
  source_ils varchar(255) NOT NULL,
  cat_username varchar(50) NOT NULL,
  amount int NOT NULL,
  currency varchar(3) NOT NULL,
  service_fee int NOT NULL,
  created timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  paid timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  registration_started timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  registered timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  status int NOT NULL DEFAULT 0,
  status_message varchar(255),
  reported timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (id)
);
CREATE INDEX payment_local_identifier_idx ON payment (local_identifier);
CREATE INDEX payment_user_id_idx ON payment (user_id);
CREATE INDEX payment_status_cat_username_created_idx ON payment (status, cat_username, created);
CREATE INDEX payment_paid_reported_idx ON payment (paid, reported);

CREATE TABLE payment_fee (
  id SERIAL,
  payment_id int NOT NULL,
  title varchar(255) NOT NULL DEFAULT '',
  type varchar(255) NOT NULL DEFAULT '',
  description varchar(255) NOT NULL DEFAULT '',
  amount int NOT NULL DEFAULT 0,
  tax_percent int NOT NULL DEFAULT 0,
  currency varchar(3) NOT NULL,
  fine_id varchar(1024) NOT NULL DEFAULT '',
  organization varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
);
CREATE INDEX payment_fee_payment_id_idx ON payment_fee (payment_id);

ALTER TABLE payment
ADD CONSTRAINT payment_ibfk_1 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE;

ALTER TABLE payment_fee
ADD CONSTRAINT payment_fee_ibfk_1 FOREIGN KEY (payment_id) REFERENCES "payment" (id) ON DELETE CASCADE;
