ALTER TABLE "audit_event"
  ADD COLUMN payment_id int DEFAULT NULL;

CREATE INDEX audit_event_payment_id_idx ON audit_event (payment_id);
ADD CONSTRAINT audit_event_ibfk_2 FOREIGN KEY (payment_id) REFERENCES "payment" (id) ON DELETE CASCADE;
